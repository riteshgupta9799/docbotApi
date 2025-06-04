<?php

namespace App\Http\Controllers;

use App\Services\TcpdfService;
use Illuminate\Http\Request;
use App\Models\CustomerDeliveryAddress;
use App\Models\Art;
use App\Models\ArtImage;
use App\Models\Customer;
use DB;
use Auth;
use Hash;
use Str;
use TCPDF;
use Validator;

class PDFController extends Controller
{
    //

    public function genrate_invoice_order(Request $request)
    {

        // Validate input
        $validator = Validator::make($request->all(), [
            'order_unique_id' => 'required|exists:orders,order_unique_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $order = DB::table('orders')->where('order_unique_id', $request->order_unique_id)->first();
        $orderedArt = DB::table('ordered_arts')->where('order_id', $order->order_id)->get();




        if ($orderedArt->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No orders Art Found',
            ],);
        }
        $address = CustomerDeliveryAddress::where('customers_delivery_address_id', $order->customer_delivery_address_id)
            ->with([
                'countries' => function ($query) {
                    $query->select('country_id', 'country_name');
                },
                'states' => function ($query) {
                    $query->select('state_subdivision_id', 'state_subdivision_name');
                },
                'cities' => function ($query) {
                    $query->select('cities_id', 'name_of_city');
                },
            ])->first();
        $invoiceData = [
            'customer_name' => $address->full_name,
            // 'customer_email' => $address->email ?? 'N/A',
            'customer_mobile' => $address->mobile,
            'invoice_number' => 'INV' . str_pad($request->order_unique_id, 5, '0', STR_PAD_LEFT),
            // 'issue_date' => date('jS F Y'),
            'issue_date' => date('M d,Y'),
            'items' => [],
        ];
        $result = [];
        $orders = [];
        $artDetails = [];
        $total = 0;
        $totalAmount = 0;
        $returnAmount = 0;
        foreach ($orderedArt as $product) {

            $art = Art::where('art_id', $product->art_id)->first();
            $artImage = ArtImage::where('art_id', $product->art_id)->first();

            $price = floatval($art->price); // Convert to float (decimal numbers allowed)
            $tax = floatval($product->tax); // Convert to float
            $serviceFee = floatval($product->service_fee); // Convert to float
            $portalPercentage = floatval($art->portal_percentages); // Convert to float

            // Calculate the portal amount based on the portal percentage
            $portalAmount = ($price * $portalPercentage) / 100;
            $itemTotal = $price + $tax + $serviceFee;
            if ($product->art_order_status == 'Return') {
                $returnAmount += $itemTotal;
            }
            if ($product->art_order_status == 'Delivered') {
                $isRefund = false;

                $artDetails[] = [
                    'isRefund' => $isRefund,
                    'art_unique_id' => $art->art_unique_id,
                    'title' => $art->title,
                    'artist_name' => $art->artist_name,
                    'portal_percentages' => $art->portal_percentages,
                    'price' => $art->price,
                    'image' => url($artImage->image),
                    'art_order_status' => $product->art_order_status,
                    'tracking_id' => $product->tracking_id,
                    'tracking_status' => $product->tracking_status,
                    'tax' => $product->tax,
                    'service_fee' => $product->service_fee,
                    'total_amount' => (string)($price + $tax + $serviceFee),
                ];
            } else if ($product->art_order_status == 'Return') {
                // For products that are not 'Delivered', we still need to return the product info
                $isRefund = false;
                if ($product->return_tracking_status == 'Return-Received') {
                    $isRefund = true;
                }
                $artDetails[] = [
                    'isRefund' => $isRefund,
                    'art_unique_id' => $art->art_unique_id,
                    'title' => $art->title,
                    'artist_name' => $art->artist_name,
                    'portal_percentages' => $art->portal_percentages,
                    'price' => $art->price,
                    'image' => url($artImage->image),
                    'art_order_status' => $product->art_order_status,
                    'tracking_status' => $product->return_tracking_status,
                    'tax' => $product->tax,
                    'service_fee' => $product->service_fee,
                    'total_amount' => (string)($price + $tax + $serviceFee),

                ];
            } else if ($product->art_order_status == 'Declined') {
                $isRefund = true;
                $artDetails[] = [
                    'isRefund' => $isRefund,
                    'art_unique_id' => $art->art_unique_id,
                    'title' => $art->title,
                    'artist_name' => $art->artist_name,
                    'portal_percentages' => $art->portal_percentages,
                    'price' => $art->price,
                    'image' => url($artImage->image),
                    'art_order_status' => $product->art_order_status,
                    'tracking_id' => $product->tracking_id,
                    'tracking_status' => $product->tracking_status,
                    'tax' => $product->tax,
                    'service_fee' => $product->service_fee,
                    'total_amount' => (string)($price + $tax + $serviceFee),
                ];
            } else {
                $artDetails[] = [

                    'art_unique_id' => $art->art_unique_id,
                    'title' => $art->title,
                    'artist_name' => $art->artist_name,
                    'portal_percentages' => $art->portal_percentages,
                    'price' => $art->price,
                    'image' => url($artImage->image),
                    'art_order_status' => $product->art_order_status,
                    'tracking_id' => $product->tracking_id,
                    'tracking_status' => $product->tracking_status,
                    'tax' => $product->tax,
                    'service_fee' => $product->service_fee,
                    'total_amount' => (string)($price + $tax + $serviceFee),
                ];
            }

            $invoiceData['items'][] = [
                'name' => $art->title,
                'artist_name' => $art->artist_name,
                'buy_date' => date('M d ,Y', strtotime($product->inserted_date)),
                'payment_method' => $order->payment_method,
                'status' => $product->art_order_status,
                'amount' => (string)($price + $tax + $serviceFee),
            ];
            // Add the price, tax, and service fee to the total, subtract the portal amount
            // $total += $price + $tax + $serviceFee - $portalAmount;

            // $total += $art->price;
            $totalAmount += $itemTotal;
        }

        $finalTotal = $totalAmount - $returnAmount;



        $addressData = [
            'customers_delivery_address_id' => $address->customers_delivery_address_id,
            'full_name' => $address->full_name,
            'mobile' => $address->mobile,
            'country' => $address->countries->country_name,
            'state' => $address->states->state_subdivision_name,
            'city' => $address->cities->name_of_city,
            'address' => $address->address,
            'pincode' => $address->pincode,
            'customer_id' => $address->customer_id,

        ];

        $orderData = [
            'order_id' => $order->order_id,
            'order_unique_id' => $order->order_unique_id,
            'payment_id' => $order->payment_intent_id,
            'payment_method' => $order->payment_method,
            'inserted_date' => $order->inserted_date,
            'inserted_time' => $order->inserted_time,
            'amount' => $order->amount,
            'payment_status' => $order->payment_status,

        ];



        $company = [
            'name' => 'MIRAMONET',
            'logo' => public_path('logo/logo.png'),
            'address' => '123 Company Address, City, State, ZIP',
            'contact' => 'Phone: +123 456 7890 | Email: info@marrs.com',
        ];

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

        $pdf = new TCPDF();
        $pdf->SetCreator('Laravel');
        $pdf->SetAuthor($company['name']);
        $pdf->SetTitle('Invoice');
        $pdf->SetSubject('Invoice for Customer');

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $pdf->AddPage();

        // Add company logo and details
        $pdf->Image($company['logo'], 10, 10, 60, 20, '', '', '', false, 300, '', false, false, 1, false, false, false);
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'Invoice', 0, 1, 'R');
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, 'Invoice Number: ' . $invoiceData['invoice_number'], 0, 1, 'R');
        $pdf->Cell(0, 10, 'DATE ISSUED: ' . $invoiceData['issue_date'], 0, 1, 'R');
        $pdf->Ln(10);

        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor(220, 0, 0);
        $pdf->Cell(0, 10, 'MIRAMONET TEAM', 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(5);

        // Set the line width for border
        $pdf->SetLineWidth(0.5);

        // Draw the full border around the page (Refined border)
        $pdf->Rect(5, 5, $pdf->getPageWidth() - 10, $pdf->getPageHeight() - 10);


        $html = '
        <table border="1" cellpadding="5" cellspacing="0" style="width:100%; font-family: Helvetica, Arial, sans-serif; font-size: 12px; border-collapse: collapse;">
            <tr style="background-color:#f2f2f2; text-align: center; font-weight: bold;">
                <th>Customer Name</th>

                <th>Customer Mobile</th>
                <th>Invoice No.</th>
                <th>Issue Date</th>
            </tr>
            <tr style="text-align: center;">
                <td>' . $invoiceData['customer_name'] . '</td>

                <td>' . $invoiceData['customer_mobile'] . '</td>
                <td>' . $invoiceData['invoice_number'] . '</td>
                <td>' . $invoiceData['issue_date'] . '</td>
            </tr>
        </table>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Ln(10);

        // Items Table
        $html = '
        <table border="1" cellpadding="5" cellspacing="0" style="width:100%; font-family: Helvetica, Arial, sans-serif; font-size: 12px; border-collapse: collapse;">
            <thead>
                <tr style="background-color:#f2f2f2; text-align:center; font-weight: bold;">
                    <th>Sr. No.</th>
                    <th>Artwork</th>
                    <th>Artist</th>
                    <th>Payment Method</th>
                    <th>Buy Date</th>
                    <th>Item Status</th>
                    <th>Amount ($)</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($invoiceData['items'] as $index => $item) {
            $html .= '<tr style="text-align:center;">
                    <td>' . ($index + 1) . '</td>
                    <td>' . $item['name'] . '</td>
                    <td>' . $item['artist_name'] . '</td>
                    <td>' . $item['payment_method'] . '</td>
                    <td>' . $item['buy_date'] . '</td>
                    <td>' . $item['status'] . '</td>
                    <td>$ ' . $item['amount'] . '</td>
                 </tr>';
        }

        // Display Total & Return Deduction
        $html .= '<tr style="font-weight:bold;">
                 <td colspan="6" cellspacing="0" style="text-align:right;">Total Amount</td>
                 <td>$ ' . number_format($totalAmount, 2) . '/</td>
              </tr>';

        if ($returnAmount > 0) {
            $html .= '<tr style="font-weight:bold; color:red;">
                             <td colspan="6" style="text-align:right;">Return Deduction</td>
                             <td>-$' . number_format($returnAmount, 2, '.', ',') . '</td>
                          </tr>';

            $html .= '<tr style="font-weight:bold;">
                             <td colspan="6" style="text-align:right;">Final  Amount</td>
                             <td>$' . number_format($finalTotal, 2, '.', ',') . '</td>
                          </tr>';
        }

        $html .= '
            </tbody>
        </table>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Ln(10);

        // Footer note
        $pdf->SetFont('helvetica', 'I', 10);
        $pdf->Cell(0, 10, 'Thank you for your business!', 0, 1, 'C');

        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Expose-Headers: Content-Disposition");

        // Output the PDF
        $pdf->Output('invoice_' . $invoiceData['invoice_number'] . '.pdf', 'I');
    }

    public function generate_invoice_order_new(Request $request)
{
    // Validate input
    $validator = Validator::make($request->all(), [
        'order_unique_id' => 'required|exists:orders,order_unique_id',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'message' => $validator->errors()->first(),
        ], 400);
    }

    $order = DB::table('orders')->where('order_unique_id', $request->order_unique_id)->first();
    $orderedArt = DB::table('ordered_arts')->where('order_id', $order->order_id)->get();

    if ($orderedArt->isEmpty()) {
        return response()->json([
            'status' => false,
            'message' => 'No ordered art found',
        ]);
    }

    $address = CustomerDeliveryAddress::where('customers_delivery_address_id', $order->customer_delivery_address_id)->first();

    $company = [
        'name' => 'Company Name',
        'slogan' => 'Company slogan',
        'address' => 'Street Address, City, ST ZIP Code',
        'contact' => 'Phone Enter phone | Fax Enter fax',
        'email_website' => 'Email | Website',
    ];

    // Initialize TCPDF
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Laravel');
    $pdf->SetAuthor($company['name']);
    $pdf->SetTitle('Invoice');
    $pdf->SetSubject('Invoice for Customer');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();

    // Set background color to black
    // $pdf->SetFillColor(25, 25, 112); // Black
    $pdf->SetFillColor(227,7,19); // Dark Navy Blue

    $pdf->Rect(0, 0, 210, 297, 'F'); // Full page background

    // Set text color to white
    $pdf->SetTextColor(255, 255, 255); // White text
    $pdf->SetTextColor(255, 255, 255); // White text

    // Company Name & Invoice Title (Left & Right)
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(100, 6, $company['name'], 0, 0, 'L'); // Left
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetTextColor(0, 102, 255); // Blue color for Invoice
    $pdf->Cell(90, 6, 'INVOICE', 0, 1, 'R'); // Right
    $pdf->SetTextColor(255, 255, 255); // Reset to white

    $pdf->SetFont('helvetica', 'I', 10);
    $pdf->Cell(100, 6, $company['slogan'], 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(90, 6, 'INVOICE # ' . $order->order_unique_id, 0, 1, 'R');

    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(100, 6, $company['address'], 0, 0, 'L');
    $pdf->Cell(90, 6, 'DATE ' . date('M d, Y'), 0, 1, 'R');

    $pdf->Cell(100, 6, $company['contact'], 0, 1, 'L');
    $pdf->Cell(100, 6, $company['email_website'], 0, 1, 'L');
    $pdf->Ln(5);

    // Bill To
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 6, 'TO:', 0, 1, 'L');

    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, $address->full_name, 0, 1, 'L');
    $pdf->Cell(0, 6, $address->address . ', ' . $address->city . ', ' . $address->state . ', ' . $address->country, 0, 1, 'L');
    $pdf->Cell(0, 6, 'Phone: ' . $address->mobile . ' | Email', 0, 1, 'L');
    $pdf->Ln(5);

    // Table Header (Blue Text)
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(0, 102, 255); // Blue header
    $pdf->Cell(150, 6, 'Description', 0, 0, 'L');
    $pdf->Cell(40, 6, 'Amount', 0, 1, 'R');

    $pdf->SetTextColor(255, 255, 255); // Reset to white
    $pdf->SetFont('helvetica', '', 10);
    $totalAmount = 0;

    foreach ($orderedArt as $product) {
        $art = Art::where('art_id', $product->art_id)->first();
        $price = floatval($art->price);
        $totalAmount += $price;

        // Description Column
        $pdf->Cell(150, 6, '   ' . $art->title, 0, 0, 'L');

        // Amount Column
        $pdf->Cell(40, 6, '$' . number_format($price, 2), 0, 1, 'R');

        // Add a separator line below each row
        $pdf->SetDrawColor(50, 50, 50); // Grey line
        $pdf->Cell(190, 0, '', 'T', 1, 'L'); // Thin line
    }

    // Total Row
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(150, 6, 'Total', 0, 0, 'L');
    $pdf->Cell(40, 6, '$' . number_format($totalAmount, 2), 0, 1, 'R');

    $pdf->Ln(10);

    // Footer
    $pdf->SetFont('helvetica', 'I', 10);
    $pdf->Cell(0, 6, 'Make all checks payable to ' . $company['name'], 0, 1, 'L');
    $pdf->Cell(0, 6, 'Payment is due within 30 days.', 0, 1, 'L');
    $pdf->Cell(0, 6, 'If you have any questions concerning this invoice, contact us.', 0, 1, 'L');
    $pdf->Ln(10);
    $pdf->SetTextColor(0, 102, 255); // Blue color for footer
    $pdf->Cell(0, 6, 'THANK YOU FOR YOUR BUSINESS!', 0, 1, 'C');

    // Output PDF
    $pdf->Output('invoice_' . $order->order_unique_id . '.pdf', 'I');
}

//     public function generate_invoice_order_new(Request $request)
//     {
//         // Validate input
//         $validator = Validator::make($request->all(), [
//             'order_unique_id' => 'required|exists:orders,order_unique_id',
//         ]);

//         if ($validator->fails()) {
//             return response()->json([
//                 'status' => false,
//                 'message' => $validator->errors()->first(),
//             ], 400);
//         }

//         $order = DB::table('orders')->where('order_unique_id', $request->order_unique_id)->first();
//         $orderedArt = DB::table('ordered_arts')->where('order_id', $order->order_id)->get();

//         if ($orderedArt->isEmpty()) {
//             return response()->json([
//                 'status' => false,
//                 'message' => 'No ordered art found',
//             ]);
//         }

//         $address = CustomerDeliveryAddress::where('customers_delivery_address_id', $order->customer_delivery_address_id)->first();

//         $company = [
//             'name' => 'Company Name',
//             'slogan' => 'Company slogan',
//             'address' => 'Street Address, City, ST ZIP Code',
//             'contact' => 'Phone Enter phone | Fax Enter fax',
//             'email_website' => 'Email | Website',
//         ];

//         $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
//         $pdf->SetCreator('Laravel');
//         $pdf->SetAuthor($company['name']);
//         $pdf->SetTitle('Invoice');
//         $pdf->SetSubject('Invoice for Customer');
//         $pdf->setPrintHeader(false);
//         $pdf->setPrintFooter(false);
//         $pdf->AddPage();

//       // Company Name & Invoice Title on Same Line
// $pdf->SetFont('helvetica', 'B', 14);
// $pdf->Cell(100, 6, $company['name'], 0, 0, 'L'); // Company Name on Left
// $pdf->SetFont('helvetica', 'B', 16);
// $pdf->Cell(90, 6, 'INVOICE', 0, 1, 'R'); // Invoice Title on Right

// $pdf->SetFont('helvetica', 'I', 10);
// $pdf->Cell(100, 6, $company['slogan'], 0, 0, 'L'); // Slogan on Left
// $pdf->SetFont('helvetica', '', 12);
// $pdf->Cell(90, 6, 'Invoice No: ' . $order->order_unique_id, 0, 1, 'R'); // Invoice No on Right

// $pdf->SetFont('helvetica', '', 10);
// $pdf->Cell(100, 6, $company['address'], 0, 0, 'L'); // Address Left
// $pdf->Cell(90, 6, 'Date: ' . date('M d, Y'), 0, 1, 'R'); // Date Right

// $pdf->Cell(100, 6, $company['contact'], 0, 1, 'L'); // Contact Left
// $pdf->Cell(100, 6, $company['email_website'], 0, 1, 'L'); // Email & Website Left

// $pdf->Ln(5);

//         // Bill To
//         $pdf->SetFont('helvetica', 'B', 12);
//         $pdf->Cell(0, 6, 'TO:', 0, 1, 'L');

//         $pdf->SetFont('helvetica', '', 10);
//         $pdf->Cell(0, 6, $address->full_name, 0, 1, 'L');
//         $pdf->Cell(0, 6, $address->address . ', ' . $address->city . ', ' . $address->state . ', ' . $address->country, 0, 1, 'L');
//         $pdf->Cell(0, 6, 'Phone: ' . $address->mobile . ' | Email', 0, 1, 'L');
//         $pdf->Ln(5);

//         // // Invoice Title & Details
//         // $pdf->SetFont('helvetica', 'B', 16);
//         // $pdf->Cell(0, 10, 'INVOICE', 0, 1, 'R');

//         // $pdf->SetFont('helvetica', '', 12);
//         // $pdf->Cell(0, 6, 'Invoice No: ' . $order->order_unique_id, 0, 1, 'R');
//         // $pdf->Cell(0, 6, 'Date: ' . date('M d, Y'), 0, 1, 'R');
//         // $pdf->Ln(5);

//         // Table Header
//         $pdf->SetFont('helvetica', 'B', 10);
//         $pdf->Cell(150, 6, 'Description', 0, 0, 'L');
//         $pdf->Cell(40, 6, 'Amount', 0, 1, 'R');

//         // Table Content
//         $pdf->SetFont('helvetica', '', 10);
//         $totalAmount = 0;

//         foreach ($orderedArt as $product) {
//             $art = Art::where('art_id', $product->art_id)->first();
//             $price = floatval($art->price);
//             $totalAmount += $price;

//             // Description Column (Indented with padding)
//             $pdf->Cell(150, 6, '   ' . $art->title, 0, 0, 'L');

//             // Amount Column (Right aligned)
//             $pdf->Cell(40, 6, '$' . number_format($price, 2), 0, 1, 'R');

//             // Add a separator line below each row (for design)
//             $pdf->Cell(190, 0, '', 'T', 1, 'L'); // Thin horizontal line
//         }
//         // Total Row
//         $pdf->SetFont('helvetica', 'B', 10);
//         $pdf->Cell(150, 6, 'Total', 0, 0, 'L');
//         $pdf->Cell(40, 6, '$' . number_format($totalAmount, 2), 0, 1, 'R');

//         $pdf->Ln(10);

//         // Footer
//         $pdf->SetFont('helvetica', 'I', 10);
//         $pdf->Cell(0, 6, 'Make all checks payable to ' . $company['name'], 0, 1, 'L');
//         $pdf->Cell(0, 6, 'Payment is due within 30 days.', 0, 1, 'L');
//         $pdf->Cell(0, 6, 'If you have any questions concerning this invoice, contact us.', 0, 1, 'L');
//         $pdf->Ln(10);
//         $pdf->Cell(0, 6, 'THANK YOU FOR YOUR BUSINESS!', 0, 1, 'C');

//         // Output PDF
//         $pdf->Output('invoice_' . $order->order_unique_id . '.pdf', 'I');
//     }



    // public function genrate_invoice_art(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'ordered_art_id' => 'required',

    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => $validator->errors()->first(),
    //         ], 400);
    //     }


    //     $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();
    //     $ordered_arts = DB::table('ordered_arts')->where('ordered_art_id', $request->ordered_art_id)->first();
    //     $art = Art::where('art_id', $ordered_arts->art_id)->first();
    //     $order = DB::table('orders')->where('order_id', $ordered_arts->order_id)->first();
    //     if (!$ordered_arts) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Ordered Art not found.',
    //         ]);
    //     }

    //     $payable_amount = $ordered_arts->price + $ordered_arts->tax + $ordered_arts->service_fee;


    //     $invoiceData = [
    //         'customer_name' => $customer->name,
    //         'customer_unique_id' => $customer->customer_unique_id,
    //         'customer_email' => $customer->email,
    //         'invoice_number' => 'INV' . str_pad($request->ordered_art_id, 5, '0', STR_PAD_LEFT),
    //         'issue_date' => date('jS F Y'),
    //         'items' => [
    //             [
    //                 'name' => $art->title,
    //                 'buy_date' => date('jS F Y', strtotime($ordered_arts->inserted_date)),
    //                 'payment_method' => $order->payment_method,
    //                 'art_order_status' => $ordered_arts->art_order_status,
    //                 'amount' => $payable_amount,

    //             ],
    //         ],
    //     ];

    //     // Company details
    //     $company = [
    //         'name' => 'MiraMonet',
    //         'logo' => public_path('logo/Black.png'),
    //         'address' => '123 Company Address, City, State, ZIP',
    //         'contact' => 'Phone: +123 456 7890 | Email: info@marrs.com',
    //     ];

    //     $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

    //     $pdf->SetCreator('Laravel');
    //     $pdf->SetAuthor($company['name']);
    //     $pdf->SetTitle('Invoice');
    //     $pdf->SetSubject('Invoice for Customer');

    //     $pdf->setPrintHeader(false);
    //     $pdf->setPrintFooter(false);

    //     $pdf->AddPage();

    //     // Add company logo and details
    //     $pdf->Image($company['logo'], 10, 10, 40, 20, '', '', '', false, 300, '', false, false, 1, false, false, false);
    //     $pdf->SetFont('helvetica', 'B', 16);
    //     $pdf->Cell(0, 10, 'Invoice', 0, 1, 'R');
    //     $pdf->SetFont('helvetica', '', 12);
    //     $pdf->Cell(0, 10, 'Invoice Number: ' . $invoiceData['invoice_number'], 0, 1, 'R');
    //     $pdf->Cell(0, 10, 'DATE ISSUED: ' . $invoiceData['issue_date'], 0, 1, 'R');
    //     $pdf->Ln(10);

    //     $pdf->SetFont('helvetica', 'B', 14);
    //     $pdf->SetTextColor(220, 0, 0);
    //     $pdf->Cell(0, 10, 'HEADSTAART TEAM', 0, 1, 'C');
    //     $pdf->SetTextColor(0, 0, 0);
    //     $pdf->Ln(5);

    //     // Set the line width for border
    //     $pdf->SetLineWidth(0.5);

    //     // Draw the full border around the page (Refined border)
    //     $pdf->Rect(5, 5, $pdf->getPageWidth() - 10, $pdf->getPageHeight() - 10);

    //     // Customer details table
    //     $html = '
    // <table border="1" cellpadding="5" cellspacing="0" style="width:100%; font-family: Helvetica, Arial, sans-serif; border-collapse: collapse; font-size: 12px;">
    //     <tr style="background-color:#f2f2f2; text-align: center; font-weight: bold;">
    //         <th>Customer Id</th>
    //             <th>Customer Name</th>
    //             <th>Customer Email</th>
    //     </tr>
    //     <tr style="font-size: 12px; text-align: center;">
    //         <td style="padding: 8px;">' . ($invoiceData['customer_unique_id']) . '</td>
    //         <td style="padding: 8px;">' . ($invoiceData['customer_name'] ?? 'N/A') . '</td>
    //         <td style="padding: 8px;">' . $invoiceData['customer_email'] . '</td>
    //     </tr>
    // </table>';

    //     $pdf->writeHTML($html, true, false, true, false, '');
    //     $pdf->Ln(10);

    //     // Items table
    //     $html = '
    // <table border="1" cellpadding="5" cellspacing="0" style="width:100%; font-family: Helvetica, Arial, sans-serif; font-size: 12px; border-collapse: collapse;">
    //     <thead>
    //         <tr style="background-color:#f2f2f2; text-align:center;">
    //             <th>Sr. No.</th>
    //             <th>Subscription Name</th>
    //             <th>Payment Method</th>
    //             <th>Buy Date</th>
    //             <th>Item Status</th>
    //             <th>Subscription End Date</th>

    //         </tr>
    //     </thead>
    //     <tbody>';
    //     foreach ($invoiceData['items'] as $index => $item) {
    //         $html .= '<tr style="text-align:center;">
    //                 <td>' . ($index + 1) . '</td>
    //                 <td>' . $item['name'] . '</td>
    //                 <td>' . $item['payment_method'] . '</td>
    //                 <td>' . $item['buy_date'] . '</td>
    //                 <td>' . $item['status'] . '</td>
    //                 <td>' . $item['end_date'] . '</td>
    //              </tr>';
    //     }
    //     $html .= '<tr style="font-weight:bold;">
    //              <td colspan="5" style="text-align:right;">Total Amount</td>
    //              <td>$ ' . number_format($customer_subscription->payable_amount, 2) . '/-</td>
    //           </tr>
    //     </tbody>
    // </table>';

    //     $pdf->writeHTML($html, true, false, true, false, '');

    //     // Footer note
    //     $pdf->Ln(10);
    //     $pdf->SetFont('helvetica', 'I', 10);
    //     $pdf->Cell(0, 10, 'Thank you for your business!', 0, 1, 'C');

    //     // Output the PDF as an inline file
    //     $pdf->Output('subscription_' . $invoiceData['invoice_number'] . '.pdf', 'I');
    // }


    public function generateInvoice(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ordered_art_id' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }


        $ordered_arts = DB::table('ordered_arts')->where('ordered_art_id', $request->ordered_art_id)->first();
        $customer = Customer::where('customer_id', $ordered_arts->customer_id)->first();
        $art = Art::where('art_id', $ordered_arts->art_id)->first();
        $order = DB::table('orders')->where('order_id', $ordered_arts->order_id)->first();
        if (!$ordered_arts) {
            return response()->json([
                'status' => false,
                'message' => 'Ordered Art not found.',
            ]);
        }

        $payable_amount = $ordered_arts->price + $ordered_arts->tax + $ordered_arts->service_fee + $ordered_arts->buyer_premium;

        $invoiceData = [
            'customer_name' => $customer->name,
            'customer_unique_id' => $customer->customer_unique_id,
            'customer_email' => $customer->email,
            'invoice_number' => 'INV' . str_pad($request->ordered_art_id, 5, '0', STR_PAD_LEFT),
            // 'issue_date' => date('M, d Y'),
            'issue_date' => date('M d,Y'),
            'items' => [
                [
                    'name' => $art->title,
                    'buy_date' => date('M d ,Y', strtotime($ordered_arts->inserted_date)),
                    'payment_method' => $order->payment_method,
                    'art_order_status' => $ordered_arts->art_order_status,

                    'status' => $ordered_arts->art_order_status,
                ],
            ],
        ];

        // Company details
        $company = [
            'name' => 'MiraMonet',
            'logo' => public_path('logo/logo.png'),
            'address' => '123 Company Address, City, State, ZIP',
            'contact' => 'Phone: +123 456 7890 | Email: info@marrs.com',
        ];

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

        $pdf->SetCreator('Laravel');
        $pdf->SetAuthor($company['name']);
        $pdf->SetTitle('Invoice');
        $pdf->SetSubject('Invoice for Customer');

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $pdf->AddPage();

        // Add company logo and details
        $pdf->Image($company['logo'], 10, 10, 60, 20, '', '', '', false, 300, '', false, false, 0, false, false, false);
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'Invoice', 0, 1, 'R');
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, 'Invoice Number: ' . $invoiceData['invoice_number'], 0, 1, 'R');
        $pdf->Cell(0, 10, 'DATE ISSUED: ' . $invoiceData['issue_date'], 0, 1, 'R');
        $pdf->Ln(10);

        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor(220, 0, 0);
        $pdf->Cell(0, 10, 'MIRAMONET TEAM', 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(5);

        // Set the line width for border
        $pdf->SetLineWidth(0.5);

        // Draw the full border around the page (Refined border)
        $pdf->Rect(5, 5, $pdf->getPageWidth() - 10, $pdf->getPageHeight() - 10);

        // Customer details table
        $html = '
    <table border="1" cellpadding="5" cellspacing="0" style="width:100%; font-family: Helvetica, Arial, sans-serif; border-collapse: collapse; font-size: 12px;">
        <tr style="background-color:#f2f2f2; text-align: center; font-weight: bold;">
            <th>Customer Id</th>
            <th>Customer Name</th>
            <th>Customer Email</th>
        </tr>
        <tr style="font-size: 12px; text-align: center;">
            <td style="padding: 8px;">' . ($invoiceData['customer_unique_id']) . '</td>
            <td style="padding: 8px;">' . ($invoiceData['customer_name'] ?? 'N/A') . '</td>
            <td style="padding: 8px;">' . $invoiceData['customer_email'] . '</td>
        </tr>
    </table>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Ln(10);

        // Items table
        $html = '
    <table border="1" cellpadding="5" cellspacing="0" style="width:100%; font-family: Helvetica, Arial, sans-serif; font-size: 12px; border-collapse: collapse;">
        <thead>
            <tr style="background-color:#f2f2f2; text-align:center;">
                <th>Sr. No.</th>
                <th>Art Name</th>
                <th>Payment Method</th>
                <th>Buy Date</th>
                <th>Item Status</th>

            </tr>
        </thead>
        <tbody>';
        foreach ($invoiceData['items'] as $index => $item) {
            // Change text color if order is "Return"
            $amountColor = ($item['status'] === 'Return') ? 'red' : 'black';

            $html .= '<tr style="text-align:center;">
                <td>' . ($index + 1) . '</td>
                <td>' . $item['name'] . '</td>
                <td>' . $item['payment_method'] . '</td>
                <td>' . $item['buy_date'] . '</td>
                <td>' . $item['status'] . '</td>

             </tr>';
        }

        // Check if status is "Return" and set text color accordingly
        $totalAmountColor = ($invoiceData['items'][0]['status'] === 'Return') ? 'red' : 'black';

        $html .= '<tr style="font-weight:bold;">
    <td colspan="4" style="text-align:right;">Price</td>
    <td>$ ' . number_format($ordered_arts->price, 2) . '/-</td>
</tr>';
        $html .= '<tr style="font-weight:bold;">
    <td colspan="4" style="text-align:right;">Tax</td>
    <td>$ ' . number_format($ordered_arts->tax, 2) . '/-</td>
</tr>';

        $html .= '<tr style="font-weight:bold;">
    <td colspan="4" style="text-align:right;">Service Fee</td>
    <td>$ ' . number_format($ordered_arts->service_fee, 2) . '/-</td>
</tr>';
        $html .= '<tr style="font-weight:bold;">
    <td colspan="4" style="text-align:right;">Buyer Premium</td>
    <td>$ ' . number_format($ordered_arts->buyer_premium, 2) . '/-</td>
</tr>';


        $html .= '<tr style="font-weight:bold; color:' . $totalAmountColor . ';">
        <td colspan="4" style="text-align:right;">Total Amount</td>
        <td>$ <span style="color:' . $totalAmountColor . ';">' . number_format($payable_amount, 2) . '</span>/-</td>
     </tr>
    </tbody>
    </table>';

        $pdf->writeHTML($html, true, false, true, false, '');

        // Footer note
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', 'I', 10);
        $pdf->Cell(0, 10, 'Thank you for your business!', 0, 1, 'C');

        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Expose-Headers: Content-Disposition");

        // Output the PDF as an inline file
        $pdf->Output('subscription_' . $invoiceData['invoice_number'] . '.pdf', 'I');
    }
}
