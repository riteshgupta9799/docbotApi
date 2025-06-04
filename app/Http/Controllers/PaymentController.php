<?php

namespace App\Http\Controllers;

use App\Models\Art;
use App\Models\Customer;
use App\Models\Exhibition;
use App\Models\ExhibitionRegistration;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\StripeClient;
use Illuminate\Support\Facades\DB;
use App\Models\City;
use App\Models\State;
use App\Models\Country;
use App\Models\ArtImage;
use App\Models\Donations;
use Illuminate\Support\Facades\Auth;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Validator;
use Log;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Stripe\Checkout\Session;
use Illuminate\Support\Facades\Mail;
use App\Models\BankDetail;

class PaymentController extends Controller
{

    public function generateUniqueId($customer_delivery_address_id)
    {
        // Fetch the address details
        $address = DB::table('customers_delivery_address')
            ->leftJoin('countries', 'customers_delivery_address.country', '=', 'countries.country_id')
            ->leftJoin('states', 'customers_delivery_address.state', '=', 'states.state_subdivision_id')
            ->where('customers_delivery_address_id', $customer_delivery_address_id)
            ->select('countries.country_name', 'states.state_subdivision_name', 'customers_delivery_address.customer_id')
            ->first();

        // Check if address details were found
        if (!$address) {
            throw new \Exception("Delivery address not found for ID: " . $customer_delivery_address_id);
        }

        // Generate the unique ID components
        $countryCode = strtoupper(substr($address->country_name, 0, 2));
        $stateCode = strtoupper(substr($address->state_subdivision_name, 0, 2));
        $formattedCustomerId = str_pad($address->customer_id, 6, '0', STR_PAD_LEFT);

        do {
            $randomInt = rand(1000, 9999); // Generate a random integer between 1000 and 9999
            $order_unique_id = $countryCode . $formattedCustomerId . $stateCode . $randomInt;

            // Check if the generated ID already exists in the database
            $exists = DB::table('orders')->where('order_unique_id', $order_unique_id)->exists();
        } while ($exists); // Repeat until a unique ID is found

        return $order_unique_id; // Return the unique order ID
    }

    public function ordercreateCheckoutSession(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required|string',
            'customer_delivery_address_id' => 'required',
            'payment_method' => 'required',
            'amount' => 'required',
            'success_url' => 'required',
            'cancel_url' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $admin = User::find(1);

        if (!$admin || empty($admin->secret_key)) {
            return response()->json([
                'status' => false,
                'message' => 'Stripe API credentials not found in the database',
            ], 500);
        }

        if($admin->payment_accepted=='0'){
            return response()->json([
               'status' => false,
           'message' => 'Payments are currently unavailable. Please check back later or contact support for more details.',

            ]);

        }
        // Set Stripe API key dynamically using the stored secret key
        $stripe = new StripeClient($admin->secret_key);
        // $stripe = Stripe::setApiKey($admin->secret_key);;

        $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();
        $customer_id = $customer->customer_id;
        $customer_delivery_address_id = $request->customer_delivery_address_id;
        $payment_method = $request->payment_method;
        $amount = $request->amount;
        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        // if (!$customer_id) {
        //     return response()->json(['status' => false, 'message' => 'Customer ID is required']);
        // }

        $cartData = DB::table('art_cart')->where('customer_id', $customer_id)->get();

        if ($cartData->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'No products in the cart to place an order.']);
        }

        $total_item = count($cartData);




        try {
            $session = $stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => 'usd',
                            'product_data' => [
                                'name' => 'Total Items: ' . $total_item,
                            ],
                            'unit_amount' => max($amount, 0) * 100,
                        ],
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'payment',
                'success_url' => $request->success_url . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $request->cancel_url . '?session_id={CHECKOUT_SESSION_ID}',
            ]);


            $order_data = DB::table('order_data')
                ->insert([
                    'customer_id' => $customer_id,
                    'customer_delivery_address_id' => $customer_delivery_address_id,
                    'session_id' => $session->id,
                    'payment_method' => $payment_method,
                    'amount' => $amount,
                    'status' => false,
                    'inserted_date' => $insertDate,
                    'inserted_time' => $insertTime,
                ]);
            return response()->json(['id' => $session->id, 'data' => $session], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'=>false,
                'message' => 'Payment initiation failed, please try again.'

                // 'message' => $e->getMessage()
            ]);
        }
    }

    public function orderstripeCheckoutSuccess(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required',
            'status' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }
        $admin = User::find(1);

        if (!$admin || empty($admin->secret_key)) {
            return response()->json([
                'status' => false,
                'message' => 'Stripe API credentials not found in the database',
            ], 500);
        }

        if($admin->payment_accepted=='0'){
            return response()->json([
               'status' => false,
           'message' => 'Payments are currently unavailable. Please check back later or contact support for more details.',

            ]);

        }

        // Set Stripe API key dynamically using the stored secret key
        $stripe = new StripeClient($admin->secret_key);

        // Finalize the Stripe session
        try {
            $response = $stripe->checkout->sessions->retrieve($request->session_id);
            if ($request->status == true) {
                $session_id = $request->session_id;
                $order_data = DB::table('order_data')
                    ->where('session_id', $session_id)
                    ->first();

                $currentDateTime = Carbon::now('Asia/Kolkata');
                $insertDate = $currentDateTime->format('Y-m-d');
                $insertTime = $currentDateTime->format('H:i:s');

                // Generate unique order ID
                $order_unique_id = $this->generateUniqueId($order_data->customer_delivery_address_id);


                // // Insert the order into the database
                $orderId = DB::table('orders')->insertGetId([
                    'order_status' => 'Pending',
                    'payment_id' => $request->session_id,
                    'payment_intent_id'=>$response->payment_intent,
                    'payment_status' => $request->status,
                    'customer_id' => $order_data->customer_id,
                    'order_unique_id' => $order_unique_id,
                    'payment_method' => $order_data->payment_method,
                    'customer_delivery_address_id' => $order_data->customer_delivery_address_id,
                    'inserted_date' => $insertDate,
                    'inserted_time' => $insertTime,
                ]);

                // // Retrieve the cart data
                $cartData = DB::table('art_cart')->where('customer_id', $order_data->customer_id)->get();

                if ($cartData->isEmpty()) {
                    return response()->json(['status' => false, 'message' => 'No products in the cart to place an order.']);
                }

                // // // Initialize totals


                $cartTotal = 0;

                // Loop through cart items
                $customer = Customer::where('customer_id', $order_data->customer_id)->first();
                try{
                    $mailData= DB::table('orders')->where('order_id',$orderId)->first();
                    $addressData=DB::table('customer_delivery_address')
                            ->where('customer_delivery_address_id',$mailData->customer_delivery_address_id)
                            ->first();
                     $country=Country::where('country_id',$addressData->country)->first();
                     $state=State::where('state_subdivision_id',$addressData->state)->first();
                     $city=City::where('cities_id',$addressData->city)->first();
                     $email=$customer->email;
                     $name=$customer->name;
                    $mailSend=[
                        'name'=>$name,
                        'order_id'=>$mailData->order_unique_id,
                        'order_date'=>$mailData->inserted_date,
                        'order_amount'=>$mailData->amount,
                        'address_name'=>$addressData->address,
                        'pincode'=>$addressData->pincode,
                        'address_city'=>$city->name_of_city,
                        'address_state'=>$state->state_subdivision_name,

                    ];
                    $zeptoApiKey = "Zoho-enczapikey PHtE6r1fE+3r2jMvphdS5vG8FcT2Mows9O81JQcWsIoUW/UAGE1dqN56mzW3+Ex+APNLHf6bz4w5sLicu+6NcWfkYGlPCGqyqK3sx/VYSPOZsbq6x00ZuVwaf0DYV47tdd5i3C3SuNraNA==";
                    $templateId = "2518b.45dd43eafd6631e.k1.2c46ec50-0ad6-11f0-bb4e-cabf48e1bf81.195d6535f95";
                    $fromEmail = "noreply@miramonet.com"; // Use a verified sender email
                    $bounceEmail = "donotreply@bounce-zem.miramonet.com"; // Correct bounce address

                    $recipientEmail = $email ?? "abhisheksaini.iimt@gmail.com";
                    $recipientName = $request->name ?? "User";
                    $teamName = "MIRAMONET TEAM";
                    $productName = "MIRAMONET";

                    $curl = curl_init();



                    curl_setopt_array($curl, [
                        CURLOPT_URL => "https://api.zeptomail.in/v1.1/email/template",
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 30,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => "POST",
                        CURLOPT_POSTFIELDS => json_encode([
                            "template_key" => $templateId,
                            "bounce_address" => $bounceEmail,  // ✅ Corrected bounce address
                            "from" => ["address" => $fromEmail], // ✅ Verified sender email
                            "to" => [
                                [
                                    "email_address" => ["address" => $recipientEmail, "name" => $recipientName]
                                ]
                            ],
                            "merge_info" => [
                               'name'=>$name,
                        'order_id'=>$mailData->order_unique_id,
                        'order_date'=>$mailData->inserted_date,
                        'order_amount'=>$mailData->amount,
                        'address_name'=>$addressData->address,
                        'pincode'=>$addressData->pincode,
                        'address_city'=>$city->name_of_city,
                        'address_state'=>$state->state_subdivision_name,
                            ]
                        ]),
                        CURLOPT_HTTPHEADER => [
                            "accept: application/json",
                            "authorization: $zeptoApiKey",
                            "cache-control: no-cache",
                            "content-type: application/json",
                        ],
                    ]);

                    $response = curl_exec($curl);
                    $err = curl_error($curl);

                    curl_close($curl);

                    if ($err) {
                        return response()->json(["message" => "cURL Error: $err"], 500);
                    }
                }catch (\Exception $e) {
                    // return response()->json(['message' => $e->getMessage()], 500);
                }
                foreach ($cartData as $cart) {

                    $cartTotal += $cart->price; // Accumulate the total from the cart
                    $product = DB::table('art')
                        ->where('art_id', $cart->art_id)
                        ->first();

                    $artImage = ArtImage::where('art_id', $product->art_id)->first();
                    // $firebase = (new Factory)->withServiceAccount(base_path('artist-3dee9-firebase-adminsdk-3kcvz-0a708fe673.json'));
                    // $messaging = $firebase->createMessaging();
                    // $fcm_tokens = $request->customer_fcm_tokens;

                    // $valid_tokens = array_filter($fcm_tokens, function ($token) {
                    //     return is_string($token) && preg_match('/^[a-zA-Z0-9:_-]+$/', $token);
                    // });

                    // foreach ($valid_tokens as $fcm_token) {
                    //     try {
                    //         $messageData = CloudMessage::withTarget('token', $fcm_token)
                    //             ->withNotification([
                    //                 'title' => 'New Order from ' . $customer->name,
                    //                 'body' => 'Your art is ' . $product->title,
                    //             ]);
                    //         $messaging->send($messageData);
                    //         DB::table('notification')->insert([
                    //             'title' => 'New Order from ' . $customer->name,
                    //             'body' => 'Your art is ' . $product->title,
                    //             'customer_id' =>  $product->customer_id,
                    //             'image' => url($artImage->image) ?? null,
                    //             'inserted_date' => $insertDate,
                    //             'inserted_time' => $insertTime,
                    //         ]);
                    //     } catch (MessagingException $e) {
                    //         Log::error("Failed to send notification to token: $fcm_token", [
                    //             'error' => $e->getMessage(),
                    //         ]);
                    //     }
                    // }

                    // Prepare data for ordered products
                    $data = [
                        'order_id' => $orderId,
                        'art_order_status' => 'Pending',
                        'art_id' => $product->art_id,
                        'seller_id' => $product->customer_id,
                        'category_id' => $product->category_id,
                        'price' => $cart->price,
                        'tax' => $cart->tax,
                        'service_fee' => $cart->service_fee,
                        'buyer_premium' => $cart->buyer_premium,
                        'customer_id' => $order_data->customer_id,
                        'inserted_date' => $insertDate,
                        'inserted_time' => $insertTime,
                    ];

                    // Insert ordered product data
                    DB::table('ordered_arts')->insert($data);


                    $arts = DB::table('art')
                        ->where('art_id', $cart->art_id)

                        ->first();

                    if ($arts) {

                        DB::table('art')
                            ->where('art_id', $arts->art_id)

                            ->update([
                                'status' => 'Sold',
                                'buy_date' => $insertDate
                            ]);
                    }
                }


                $totalAmount = $cartTotal;


                // Update the order with the total amount
                DB::table('orders')->where('order_id', $orderId)->update([
                    'amount' => max($totalAmount, 0), // Ensure it's not negative
                    'order_unique_id' => $order_unique_id,

                ]);

                if ($request->status) {
                    DB::table('order_data')
                        ->where('session_id', $session_id)
                        ->update([
                            'status' => $request->status
                        ]);
                }
                $firebase = (new Factory)->withServiceAccount(base_path('mira-monet-firebase-adminsdk-fbsvc-864230166e.json'));
                    $messaging = $firebase->createMessaging();
                    $fcm_tokens = $request->customer_fcm_tokens;

                    $valid_tokens = array_filter($fcm_tokens, function ($token) {
                        return is_string($token) && preg_match('/^[a-zA-Z0-9:_-]+$/', $token);
                    });

                    foreach ($valid_tokens as $fcm_token) {
                        try {
                            $messageData = CloudMessage::withTarget('token', $fcm_token)
                                ->withNotification([
                                    'title' => 'New Order from ' . $customer->name,
                                    'body' => 'Your art is ' . $product->title,
                                ]);
                            $messaging->send($messageData);
                            DB::table('notification')->insert([
                                'title' => 'New Order from ' . $customer->name,
                                'body' => 'Your art is ' . $product->title,
                                'customer_id' =>  $product->customer_id,
                                'image' => url($artImage->image) ?? null,
                                'inserted_date' => $insertDate,
                                'inserted_time' => $insertTime,
                            ]);
                        } catch (MessagingException $e) {
                            Log::error("Failed to send notification to token: $fcm_token", [
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }


                // Clear the cart
                DB::table('art_cart')->where('customer_id', $order_data->customer_id)->delete();
            }
            // dd($response);
            return response()->json(['status' => true, 'data' => $response, 'order_data' => $order_unique_id], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function order_success_app(Request $request)
    {

        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            // 'customer_unique_id' => 'required|string',
            'payment_id' => 'required|string',
            'payment_method' => 'required',
            'amount' => 'required',
            'customer_delivery_address_id' => 'required',
            'customer_fcm_tokens' => 'required|array',
            'customer_fcm_tokens.*' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }
        $admin = User::find(1);

        if (!$admin || empty($admin->secret_key)) {
            return response()->json([
                'status' => false,
                'message' => 'Stripe API credentials not found in the database',
            ], 500);
        }

        if($admin->payment_accepted=='0'){
            return response()->json([
               'status' => false,
           'message' => 'Payments are currently unavailable. Please check back later or contact support for more details.',

            ]);

        }

        $stripe = new StripeClient($admin->secret_key);
        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->format('Y-m-d');
        $insertTime = $currentDateTime->format('H:i:s');
        $customers = Auth::guard('customer_api')->user();

        // foreach ($fcm_tokens as $fcm_token) {
        //     $messageData = CloudMessage::withTarget('token', $fcm_token)
        //         ->withNotification([
        //             'title' => 'New Order from '. $customer->name,
        //             'body' => 'Your art is ' . $product->title,
        //         ]);
        //         $messaging->send($messageData);
        //     }



        // // Update the order with the total amount
        // DB::table('orders')->where('order_id', $orderId)->update([
        //     'amount' => max($totalAmount, 0), // Ensure it's not negative
        //     'order_unique_id' => $order_unique_id,
        // ]);


        // Finalize the Stripe session
        try {
            $paymentIntent = $stripe->paymentIntents->retrieve($request->payment_id);


            if ($paymentIntent->status == 'succeeded') {
                $customer = Customer::where('customer_unique_id', $customers->customer_unique_id)->first();

                $order_unique_id = $this->generateUniqueId($request->customer_delivery_address_id);
                $orderId = DB::table('orders')->insertGetId([
                    'order_status' => 'Pending',
                    'payment_id' => $request->payment_id,
                    'payment_intent_id'=>$request->payment_id,
                    'customer_id' => $customer->customer_id,
                    'order_unique_id' => $order_unique_id,
                    'payment_method' => $request->payment_method,
                    'amount' => $request->amount,
                    'customer_delivery_address_id' => $request->customer_delivery_address_id,
                    'inserted_date' => $insertDate,
                    'inserted_time' => $insertTime,
                    'payment_status' => true
                ]);


                $cartData = DB::table('art_cart')->where('customer_id', $customer->customer_id)->get();

                if ($cartData->isEmpty()) {
                    return response()->json(['status' => false, 'message' => 'No Art in the cart to place an order.']);
                }
                try{
                    $mailData= DB::table('orders')->where('order_id',$orderId)->first();
                    $addressData=DB::table('customer_delivery_address')
                            ->where('customer_delivery_address_id',$mailData->customer_delivery_address_id)
                            ->first();
                     $country=Country::where('country_id',$addressData->country)->first();
                     $state=State::where('state_subdivision_id',$addressData->state)->first();
                     $city=City::where('cities_id',$addressData->city)->first();
                     $email=$customer->email;
                     $name=$customer->name;
                    $mailSend=[
                        'name'=>$name,
                        'order_id'=>$mailData->order_unique_id,
                        'order_date'=>$mailData->inserted_date,
                        'order_amount'=>$mailData->amount,
                        'address_name'=>$addressData->address,
                        'pincode'=>$addressData->pincode,
                        'address_city'=>$city->name_of_city,
                        'address_state'=>$state->state_subdivision_name,

                    ];
                    $zeptoApiKey = "Zoho-enczapikey PHtE6r1fE+3r2jMvphdS5vG8FcT2Mows9O81JQcWsIoUW/UAGE1dqN56mzW3+Ex+APNLHf6bz4w5sLicu+6NcWfkYGlPCGqyqK3sx/VYSPOZsbq6x00ZuVwaf0DYV47tdd5i3C3SuNraNA==";
                    $templateId = "2518b.45dd43eafd6631e.k1.2c46ec50-0ad6-11f0-bb4e-cabf48e1bf81.195d6535f95";
                    $fromEmail = "noreply@miramonet.com"; // Use a verified sender email
                    $bounceEmail = "donotreply@bounce-zem.miramonet.com"; // Correct bounce address

                    $recipientEmail = $email ?? "abhisheksaini.iimt@gmail.com";
                    $recipientName = $request->name ?? "User";
                    $teamName = "MIRAMONET TEAM";
                    $productName = "MIRAMONET";

                    $curl = curl_init();



                    curl_setopt_array($curl, [
                        CURLOPT_URL => "https://api.zeptomail.in/v1.1/email/template",
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 30,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => "POST",
                        CURLOPT_POSTFIELDS => json_encode([
                            "template_key" => $templateId,
                            "bounce_address" => $bounceEmail,  // ✅ Corrected bounce address
                            "from" => ["address" => $fromEmail], // ✅ Verified sender email
                            "to" => [
                                [
                                    "email_address" => ["address" => $recipientEmail, "name" => $recipientName]
                                ]
                            ],
                            "merge_info" => [
                               'name'=>$name,
                        'order_id'=>$mailData->order_unique_id,
                        'order_date'=>$mailData->inserted_date,
                        'order_amount'=>$mailData->amount,
                        'address_name'=>$addressData->address,
                        'pincode'=>$addressData->pincode,
                        'address_city'=>$city->name_of_city,
                        'address_state'=>$state->state_subdivision_name,
                            ]
                        ]),
                        CURLOPT_HTTPHEADER => [
                            "accept: application/json",
                            "authorization: $zeptoApiKey",
                            "cache-control: no-cache",
                            "content-type: application/json",
                        ],
                    ]);

                    $response = curl_exec($curl);
                    $err = curl_error($curl);

                    curl_close($curl);

                    if ($err) {
                        return response()->json(["message" => "cURL Error: $err"], 500);
                    }
                }catch (\Exception $e) {
                    // return response()->json(['message' => $e->getMessage()], 500);
                }
                $cartTotal = 0;

                foreach ($cartData as $cart) {
                    $cartTotal += $cart->price;
                    $product = DB::table('art')
                        ->where('art_id', $cart->art_id)
                        ->first();

                    $artImage = ArtImage::where('art_id', $product->art_id)->first();
                    $firebase = (new Factory)->withServiceAccount(base_path('mira-monet-firebase-adminsdk-fbsvc-864230166e.json'));
                    $messaging = $firebase->createMessaging();
                    $fcm_tokens = $request->customer_fcm_tokens;

                    $valid_tokens = array_filter($fcm_tokens, function ($token) {
                        return is_string($token) && preg_match('/^[a-zA-Z0-9:_-]+$/', $token);
                    });

                    foreach ($valid_tokens as $fcm_token) {
                        try {
                            $messageData = CloudMessage::withTarget('token', $fcm_token)
                                ->withNotification([
                                    'title' => 'New Order from ' . $customer->name,
                                    'body' => 'Your art is ' . $product->title,
                                ]);
                            $messaging->send($messageData);
                            DB::table('notification')->insert([
                                'title' => 'New Order from ' . $customer->name,
                                'body' => 'Your art is ' . $product->title,
                                'customer_id' =>  $product->customer_id,
                                'image' => url($artImage->image) ?? null,
                                'inserted_date' => $insertDate,
                                'inserted_time' => $insertTime,
                            ]);
                        } catch (MessagingException $e) {
                            Log::error("Failed to send notification to token: $fcm_token", [
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    $data = [
                        'order_id' => $orderId,
                        'art_order_status' => 'Pending',
                        'art_id' => $product->art_id,
                        'category_id' => $product->category_id,
                        'seller_id' => $product->customer_id,
                        'customer_id' => $customer->customer_id,
                        'price' => $cart->price,
                        'tax' => $cart->tax,
                        'buyer_premium' => $cart->buyer_premium,
                        'service_fee' => $cart->service_fee,
                        'inserted_date' => $insertDate,
                        'inserted_time' => $insertTime,
                    ];

                    DB::table('ordered_arts')->insert($data);


                    $arts = DB::table('art')
                        ->where('art_id', $cart->art_id)

                        ->first();


                    if ($arts) {

                        DB::table('art')
                            ->where('art_id', $arts->art_id)

                            ->update([
                                'status' => 'Sold',
                                'buy_date' => $insertDate
                            ]);
                    }
                }

                $totalAmount = $cartTotal;
                DB::table('art_cart')->where('customer_id', $customer->customer_id)->delete();

                return response()->json(['status' => "true", 'message' => 'Ordered Successfully.', 'data' => $paymentIntent], 200);
            } else {
                return response()->json(['status' => "false", 'message' => 'Payment not successful.'], 400);
            }
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function exhRegCheckoutSession(Request $request)
    {

        $validator = Validator::make($request->all(), [
            // 'customer_unique_id' => 'required|string',
            'amount' => 'required',
            'payment_method' => 'required',
            'registration_code' => 'required',
            'success_url' => 'required',
            'cancel_url' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $admin = User::find(1);

        if (!$admin || empty($admin->secret_key)) {
            return response()->json([
                'status' => false,
                'message' => 'Stripe API credentials not found in the database',
            ], 500);
        }

        if($admin->payment_accepted=='0'){
            return response()->json([
               'status' => false,
           'message' => 'Payments are currently unavailable. Please check back later or contact support for more details.',

            ]);

        }

        // Set Stripe API key dynamically using the stored secret key
        $stripe = new StripeClient($admin->secret_key);
        // $customer_delivery_address_id = $request->customer_delivery_address_id;
        $amount = $request->amount;
        $registration_code = $request->registration_code;
        $payment_method = $request->payment_method;
        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();
        $registration = ExhibitionRegistration::where('registration_code', $request->registration_code)->first();
        $totalTax = 0;
        $totalServiceFee = 0;
        $data = DB::table('users')->where('user_id', '1')->first();


        $taxPercentage = $data->tax;
        $serviceFeePercentage = $data->service_fee;

        $taxAmount = ($amount * $taxPercentage) / 100;
        $serviceFeeAmount = ($amount * $serviceFeePercentage) / 100;


        $totalTax += $taxAmount;
        $totalServiceFee += $serviceFeeAmount;


        $totalNewAmount = $amount + $totalTax + $totalServiceFee;
        if (!$registration) {
            return response()->json(['status' => 'false', 'message' => 'Registration not found.']);
        }

        try {
            $session = $stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => 'usd',
                            'product_data' => [
                                'name' => 'Total Items: ' . 'Data',
                            ],
                            // 'unit_amount' => max($totalNewAmount, 0) * 100,
                            // 'unit_amount' => intval(round(max($amount, 0) * 100)),
                            'unit_amount' => max($amount, 0) * 100,

                        ],
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'payment',
                'success_url' => $request->success_url . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $request->cancel_url . '?session_id={CHECKOUT_SESSION_ID}',
            ]);

            ExhibitionRegistration::where('registration_code', $request->registration_code)->update([
                // 'customer_id' => $customer_id,
                'payment_id' => $session->id,
                'payment_type' => $payment_method,
                'amount' => $amount,
                // 'total_amount' => $amount,
                // 'tax' => $totalTax,
                // 'service_fee' => $totalServiceFee,
                'status' => false,
                'inserted_date' => $insertDate,
                'inserted_time' => $insertTime,
            ]);
            return response()->json(['id' => $session->id, 'data' => $session], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'=>false,
                'message' => 'Payment initiation failed, please try again.'

                // 'message' => $e->getMessage()
            ]);
            // return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function exhstripeCheckoutSuccess(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required',
            'status' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }
        $admin = User::find(1);

        if (!$admin || empty($admin->secret_key)) {
            return response()->json([
                'status' => false,
                'message' => 'Stripe API credentials not found in the database',
            ], 500);
        }

        // Set Stripe API key dynamically using the stored secret key
        $stripe = new StripeClient($admin->secret_key);
        $payment_id = $request->session_id;




        try {
            $response = $stripe->checkout->sessions->retrieve($request->session_id);
            $registration = ExhibitionRegistration::where('payment_id', $payment_id)->first();

            if (!$registration) {
                return response()->json(['status' => 'false', 'message' => 'Registration not found.']);
            }

            if ($request->status == true) {
                ExhibitionRegistration::where('payment_id', $request->session_id)
                    ->update([

                        'status' => 'Active',
                        'payment_intent_id'=>$response->payment_intent,
                    ]);
            }


            $exhibition = Exhibition::where('exhibition_id', $registration->exhibition_id)
                ->join('countries', 'countries.country_id', '=', 'exhibitions.country')
                ->join('states', 'states.state_subdivision_id', '=', 'exhibitions.state')
                ->join('cities', 'cities.cities_id', '=', 'exhibitions.city')
                ->first();

            $sponsors = DB::table('exhibition_sponsor')
                ->where('exhibition_id', $registration->exhibition_id)
                ->get();

            $spo = [];
            foreach ($sponsors as $sponsor) {
                $logo = url('/') . '/' . $sponsor->logo;
                $spo[] = [
                    'logo' => $logo,
                ];
            }
            $purpose_booking = $registration->purpose_booking;
            $timeSlot = DB::table('exhibition_time_slot')
                ->where('exhibition_time_slot_id', $registration->exhibition_time_slot_id)
                ->first();

            $baseUrl = url('/');
            $result = [
                'exhibition' => $exhibition,
                'customer_data' => [
                    'name' => $registration->name,
                    'email' => $registration->email,
                    'mobile' => $registration->mobile
                ],
                // 'sponsors' => $spo,
                'logo' => $baseUrl . '/' . $exhibition->logo,
                'registration_code' => $registration->registration_code,
                'purpose_booking' => $registration->purpose_booking,
                'exhibtion_date' => $registration->exhibtion_date,
                'slot_name' => $registration->slot_name,
                'registration' => $registration,
            ];

            return response()->json(['status' => true, 'data' => $response, 'exh_data' => $result], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function exh_success_app(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'customer_unique_id' => 'required|string',
            'payment_id' => 'required|string',
            'payment_type' => 'required',
            'amount' => 'required',
            'registration_code' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }
        $admin = User::find(1);

        if (!$admin || empty($admin->secret_key)) {
            return response()->json([
                'status' => false,
                'message' => 'Stripe API credentials not found in the database',
            ], 500);
        }

        if($admin->payment_accepted=='0'){
            return response()->json([
               'status' => false,
           'message' => 'Payments are currently unavailable. Please check back later or contact support for more details.',

            ]);

        }

        // Set Stripe API key dynamically using the stored secret key
        $stripe = new StripeClient($admin->secret_key);
        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->format('Y-m-d');
        $insertTime = $currentDateTime->format('H:i:s');

        $registrationCode = $request->input('registration_code');

        $registration = ExhibitionRegistration::where('registration_code', $registrationCode)->first();

        if (!$registration) {
            return response()->json(['status' => 'false', 'message' => 'Registration not found.']);
        }

        $exhibition = Exhibition::where('exhibition_id', $registration->exhibition_id)
            ->join('countries', 'countries.country_id', '=', 'exhibitions.country')
            ->join('states', 'states.state_subdivision_id', '=', 'exhibitions.state')
            ->join('cities', 'cities.cities_id', '=', 'exhibitions.city')
            ->first();

        $exhibition->logo = isset($exhibition->logo) ? url($exhibition->logo) : null;
        $amount=$request->amount;
        // $totalTax = 0;
        // $totalServiceFee = 0;
        // $data = DB::table('users')->where('user_id', '1')->first();


        // $taxPercentage = $data->tax;
        // $serviceFeePercentage = $data->service_fee;

        // $taxAmount = ($amount * $taxPercentage) / 100;
        // $serviceFeeAmount = ($amount * $serviceFeePercentage) / 100;


        // $totalTax += $taxAmount;
        // $totalServiceFee += $serviceFeeAmount;


        // $totalNewAmount = $amount + $totalTax + $totalServiceFee;

        $data = ExhibitionRegistration::where('registration_code', $registrationCode)->update([
            'amount' => $amount,
            // 'total_amount' => $amount,
            //     'tax' => $totalTax,
            //     'service_fee' => $totalServiceFee,
            'payment_id' => $request->payment_id,
            'payment_intent_id' => $request->payment_id,



            'payment_type' => $request->payment_type,
            'status' => 'Active',
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ]);

        $sponsors = DB::table('exhibition_sponsor')
            ->where('exhibition_id', $registration->exhibition_id)
            ->get();

        $spo = [];
        foreach ($sponsors as $sponsor) {
            $logo = url('/') . '/' . $sponsor->logo;
            $spo[] = [
                'logo' => $logo,
            ];
        }
        $purpose_booking = $registration->purpose_booking;
        $timeSlot = DB::table('exhibition_time_slot')
            ->where('exhibition_time_slot_id', $registration->exhibition_time_slot_id)
            ->first();

        $baseUrl = url('/');
        $result = [
            'exhibition' => $exhibition,
            'customer_data' => [
                'name' => $registration->name,
                'email' => $registration->email,
                'mobile' => $registration->mobile
            ],
            // 'sponsors' => $spo,
            'logo' => url($exhibition->logo),
            'registration_code' => $registration->registration_code,
            'purpose_booking' => $registration->purpose_booking,
            'exhibtion_date' => $registration->exhibtion_date,
            'slot_name' => $registration->slot_name,
            'registration' => $registration,
        ];

        // $baseUrl = url('/');
        // $result = [
        //     'exhibition' => $exhibition,
        //     'customer_data' => [
        //         'name' => $registration->name,
        //         'email' => $registration->email,
        //         'mobile' => $registration->mobile
        //     ],
        //     'sponsors' => $spo,
        //     'logo' => url($exhibition->logo),
        //     'registration_code' => $registration->registration_code,
        // ];

        try {
            $paymentIntent = $stripe->paymentIntents->retrieve($request->payment_id);

            if ($paymentIntent->status == 'succeeded') {
                return response()->json(['status' => "true", 'message' => 'Registration activated successfully for the exhibition.', 'data' => $result], 200);
            } else {
                return response()->json(['status' => "false", 'message' => 'Payment not successful.'], 400);
            }
        } catch (\Exception $e) {
            return response()->json(['hbhbhbhbh' => $e->getMessage()], 500);
        }
    }

    public function boost_success_app(Request $request)
    {
        $request->validate([
            'payment_id' => 'required',
            'payment_method' => 'required',
            'days' => 'required',
            'views' => 'required',
            'price' => 'required',
            'art_unique_id' => 'required',
            'customer_unique_id' => 'required',
        ]);

        $admin = User::find(1);

        if (!$admin || empty($admin->secret_key)) {
            return response()->json([
                'status' => false,
                'message' => 'Stripe API credentials not found in the database',
            ], 500);
        }
        if($admin->payment_accepted=='0'){
            return response()->json([
               'status' => false,
           'message' => 'Payments are currently unavailable. Please check back later or contact support for more details.',

            ]);

        }

        // Set Stripe API key dynamically using the stored secret key
        $stripe = new StripeClient($admin->secret_key);
        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->format('Y-m-d');
        $insertTime = $currentDateTime->format('H:i:s');
        $art = Art::where('art_unique_id', $request->art_unique_id)->first();
        $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();
        // $totalTax = 0;
        // $totalServiceFee = 0;
        // $data = DB::table('users')->where('user_id', '1')->first();
        // $amount=$request->price;

        // $taxPercentage = $data->artist_tax;
        // $serviceFeePercentage = $data->artist_service_fee;
        // $taxAmount = ($amount * $taxPercentage) / 100;
        // $serviceFeeAmount = ($amount * $serviceFeePercentage) / 100;


        // $totalTax += $taxAmount;
        // $totalServiceFee += $serviceFeeAmount;


        // $totalNewAmount = $amount + $totalTax + $totalServiceFee;



        try {
            $paymentIntent = $stripe->paymentIntents->retrieve($request->payment_id);

            if ($paymentIntent->status == 'succeeded') {

                $result = DB::table('boosted_art')->insert([
                    'payment_id' => $request->payment_id,
                    'payment_intent_id'=>$request->payment_id,

                    'payment_method' => $request->payment_method,
                    'days' => $request->days,
                    'views' => $request->views,
                    'price' => $request->price,
                //     'total_amount' => $request->price,

                // 'tax' => $totalTax,
                // 'service_fee' => $totalServiceFee,
                    'inserted_date' => $insertDate,
                    'inserted_time' => $insertTime,
                    'status' => true,
                    'art_id' => $art->art_id,
                    'customer_id' => $customer->customer_id,
                ]);
                $validUptoDate = Carbon::today()->addDays($request->days);
                $data = Art::where('art_id', $art->art_id)
                    ->update([
                        'is_boost' => true,
                        'boost_valid_upto' => $validUptoDate
                    ]);
                return response()->json(['status' => "true", 'message' => 'Boosted Projcect Successfully, </n> Thank You.', 'data' => $result]);
            } else {
                return response()->json(['status' => "false", 'message' => 'Payment not successful.']);
            }
        } catch (\Exception $e) {
            return response()->json(['hbhbhbhbh' => $e->getMessage()]);
        }
    }

    public function donation_success_app(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            // 'last_name' => 'required|string',
            'amount' => 'required|string',
            'email' => 'required|email',
            'payment_id' => 'required|string',
            'comment' => 'required|string',
            'payment_method' => 'required|string',
            'company_name' => 'nullable|string',
        ]);

        $admin = User::find(1);

        if (!$admin || empty($admin->secret_key)) {
            return response()->json([
                'status' => false,
                'message' => 'Stripe API credentials not found in the database',
            ], 500);
        }

        if($admin->payment_accepted=='0'){
            return response()->json([
               'status' => false,
           'message' => 'Payments are currently unavailable. Please check back later or contact support for more details.',

            ]);

        }
        // Set Stripe API key dynamically using the stored secret key
        $stripe = new StripeClient($admin->secret_key);
        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->format('Y-m-d');
        $insertTime = $currentDateTime->format('H:i:s');

        if ($request->hasFile('donation_logo') && $request->file('donation_logo')->isValid()) {


            $file = $request->file('donation_logo');
            $fileName = time() . '.' . $file->getClientOriginalExtension();
            $filePath = 'donation/donation_logo/' . $fileName;
            $file->move(public_path('donation/donation_logo/'), $fileName);
        }
        try {
            $paymentIntent = $stripe->paymentIntents->retrieve($request->payment_id);

            if ($paymentIntent->status == 'succeeded') {
                $result = DB::table('donations')->insert([
                    'name' => $request->name,
                    'email' => $request->email,
                    'amount' => $request->amount,
                    'donation_logo' => $filePath,
                    'payment_id' => $request->payment_id,
                    'payment_intent_id' => $request->payment_id,


                    'inserted_date' => $insertDate,
                    'inserted_time' => $insertTime,
                    'payment_status' => true,
                    'payment_method' => $request->payment_method,
                    'comment' => $request->comment,
                    'company_name' => $request->company_name,
                ]);

                return response()->json(['status' => "true", 'message' => 'Donation Received, </n> Thank You.', 'data' => $result]);
            } else {
                return response()->json(['status' => "false", 'message' => 'Payment not successful.']);
            }
        } catch (\Exception $e) {
            return response()->json(['hbhbhbhbh' => $e->getMessage()]);
        }
    }

    public function donationCheckoutSession(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'amount' => 'required|string',
            'email' => 'required|email',
            'comment' => 'required|string',
            'donation_logo' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
            'company_name' => 'required',
            'payment_method' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $admin = User::find(1);

        if (!$admin || empty($admin->secret_key)) {
            return response()->json([
                'status' => false,
                'message' => 'Stripe API credentials not found in the database',
            ], 500);
        }
        if($admin->payment_accepted=='0'){
            return response()->json([
               'status' => false,
           'message' => 'Payments are currently unavailable. Please check back later or contact support for more details.',

            ]);

        }

        // Set Stripe API key dynamically using the stored secret key
        $stripe = new StripeClient($admin->secret_key);
        // $customer_delivery_address_id = $request->customer_delivery_address_id;
        $amount = $request->amount;
        $name = $request->name;
        $email = $request->email;
        $comment = $request->comment;
        $payment_method = $request->payment_method;
        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        if ($request->hasFile('donation_logo') && $request->file('donation_logo')->isValid()) {


            $file = $request->file('donation_logo');
            $fileName = time() . '.' . $file->getClientOriginalExtension();
            $filePath = 'donation/donation_logo/' . $fileName;
            $file->move(public_path('donation/donation_logo/'), $fileName);
        }
        try {
            $session = $stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => 'usd',
                            'product_data' => [
                                'name' => 'Total Items: ' . 'Data',
                            ],
                            'unit_amount' => max($amount, 0) * 100,
                        ],
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'payment',
                'success_url' => $request->success_url . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $request->cancel_url . '?session_id={CHECKOUT_SESSION_ID}',
            ]);

            DB::table('donations')->insert([
                'name' => $request->name,
                'email' => $request->email,
                'amount' => $request->amount,
                'donation_logo' => $filePath,
                'payment_id' => $session->id,
                'inserted_date' => $insertDate,
                'inserted_time' => $insertTime,
                'payment_status' => false,
                'payment_method' => $request->payment_method,
                'company_name' => $request->company_name,
                'comment' => $request->comment,
            ]);

            return response()->json(['id' => $session->id, 'data' => $session], 200);
        } catch (\Exception $e) {
            // return response()->json(['error' => $e->getMessage()], 500);
            return response()->json([
                'status'=>false,
                'message' => 'Payment initiation failed, please try again.'

                // 'message' => $e->getMessage()
            ]);
        }
    }

    public function donationCheckoutSuccess(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required',
            'status' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }
        $admin = User::find(1);

        if (!$admin || empty($admin->secret_key)) {
            return response()->json([
                'status' => false,
                'message' => 'Stripe API credentials not found in the database',
            ], 500);
        }

        if($admin->payment_accepted=='0'){
            return response()->json([
               'status' => false,
           'message' => 'Payments are currently unavailable. Please check back later or contact support for more details.',

            ]);

        }
        // Set Stripe API key dynamically using the stored secret key
        $stripe = new StripeClient($admin->secret_key);
        $session_id = $request->session_id;

        try {
            $response = $stripe->checkout->sessions->retrieve($request->session_id);
            $donation = DB::table('donations')->where('payment_id', $session_id)->first();

            if (!$donation) {
                return response()->json(['status' => 'false', 'message' => 'donation not found.']);
            }

            if ($request->status) {
                DB::table('donations')->where('payment_id', $session_id)
                    ->update([
                        'payment_status' => $request->status,
                        'payment_intent_id'=>$response->payment_intent,

                    ]);
            }



            return response()->json(['status' => true, 'data' => $response, 'message' => 'Donation Received, </n> Thank You.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function boost_art_checkout(Request $request)
    {
        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'payment_method' => 'required',
            'days' => 'required',
            'views' => 'required',
            'price' => 'required',
            'art_unique_id' => 'required',
            'customer_unique_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }
        $customer = Auth::guard('customer_api')->user();


        $customer = Auth::guard('customer_api')->user();
        $customer_unique_id = $customer->customer_unique_id;

        $customers = Customer::where('customer_unique_id', $customer_unique_id)->first();

        $admin = User::find(1);

        if (!$admin || empty($admin->secret_key)) {
            return response()->json([
                'status' => false,
                'message' => 'Stripe API credentials not found in the database',
            ], 500);
        }
        if($admin->payment_accepted=='0'){
            return response()->json([
               'status' => false,
           'message' => 'Payments are currently unavailable. Please check back later or contact support for more details.',

            ]);

        }

        // Set Stripe API key dynamically using the stored secret key
        $stripe = new StripeClient($admin->secret_key);

        $art = Art::where('art_unique_id', $request->art_unique_id)->first();
        $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();

        $totalTax = 0;
        $totalServiceFee = 0;
        $data = DB::table('users')->where('user_id', '1')->first();
        $amount=$request->price;

        $taxPercentage = $data->artist_tax;
        $serviceFeePercentage = $data->artist_service_fee;

        $taxAmount = ($amount * $taxPercentage) / 100;
        $serviceFeeAmount = ($amount * $serviceFeePercentage) / 100;


        $totalTax += $taxAmount;
        $totalServiceFee += $serviceFeeAmount;


        $totalNewAmount = $amount + $totalTax + $totalServiceFee;

        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();
        try {
            $session = $stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => 'usd',
                            'product_data' => [
                                'name' => 'name',
                            ],
                            'unit_amount' => max($totalNewAmount, 0) * 100,
                        ],
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'payment',
                'success_url' => $request->success_url . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $request->cancel_url . '?session_id={CHECKOUT_SESSION_ID}',
            ]);
            $result = DB::table('boosted_art')->insert([
                'payment_id' =>   $session->id,
                'payment_method' => $request->payment_method,
                'days' => $request->days,
                'views' => $request->views,
                'price' => $totalNewAmount,
                    'total_amount' => $request->price,

                'tax' => $totalTax,
                'service_fee' => $totalServiceFee,
                'inserted_date' => $insertDate,
                'inserted_time' => $insertTime,
                'status' => false,
                'art_id' => $art->art_id,
                'customer_id' => $customer->customer_id,
            ]);

            return response()->json([
                'status' => true,
                'id' => $session->id,
                'data' => $session
            ], 200);
        } catch (\Exception $e) {
            // return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
            return response()->json([
                'status'=>false,
                'message' => 'Payment initiation failed, please try again.'

                // 'message' => $e->getMessage()
            ]);
        }
    }

    public function boost_art_checkout_success(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'payment_id' => 'required',
            'status' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }
        $admin = User::find(1);

        if (!$admin || empty($admin->secret_key)) {
            return response()->json([
                'status' => false,
                'message' => 'Stripe API credentials not found in the database',
            ], 500);
        }
        if($admin->payment_accepted=='0'){
            return response()->json([
               'status' => false,
           'message' => 'Payments are currently unavailable. Please check back later or contact support for more details.',

            ]);

        }

        // Set Stripe API key dynamically using the stored secret key
        $stripe = new StripeClient($admin->secret_key);
        $session_id = $request->payment_id;
        $art = DB::table('boosted_art')->where('payment_id', $session_id)
            ->first();

        if (!$art) {
            return response()->json([
                'status' => false,
                'message' => 'no Data found'
            ]);
        }
        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->format('Y-m-d');
        $insertTime = $currentDateTime->format('H:i:s');




        try {
            $response = $stripe->checkout->sessions->retrieve($request->payment_id);
            if ($request->status == true) {
                DB::table('boosted_art')->where('payment_id', $session_id)
                    ->update([
                        'payment_intent_id'=>$response->payment_intent,
                        'status' => $request->status,
                        'inserted_date' => $insertDate,
                        'inserted_time' => $insertTime,
                    ]);


                $validUptoDate = Carbon::today()->addDays($art->days);
                $data = Art::where('art_id', $art->art_id)
                    ->update([
                        'is_boost' => true,
                        'boost_valid_upto' => $validUptoDate
                    ]);
            } else {
                return response()->json(['status' => false, 'message' => 'Payment not successful.']);
            }

            // dd($response);
            return response()->json(['status' => true, 'data' => $response, 'art' => $art], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function artist_exh_success_app(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'customer_unique_id' => 'required|string',
            'payment_id' => 'required|string',
            'payment_type' => 'required',
            'amount' => 'required',
            'registration_code' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }
        $admin = User::find(1);

        if (!$admin || empty($admin->secret_key)) {
            return response()->json([
                'status' => false,
                'message' => 'Stripe API credentials not found in the database',
            ], 500);
        }
        if($admin->payment_accepted=='0'){
            return response()->json([
               'status' => false,
           'message' => 'Payments are currently unavailable. Please check back later or contact support for more details.',

            ]);

        }

        // Set Stripe API key dynamically using the stored secret key
        $stripe = new StripeClient($admin->secret_key);
        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->format('Y-m-d');
        $insertTime = $currentDateTime->format('H:i:s');

        $registrationCode = $request->input('registration_code');

        $registration = DB::table('artist_exhibition_registration')->where('registration_code', $registrationCode)->first();

        if (!$registration) {
            return response()->json(['status' => 'false', 'message' => 'Registration not found.']);
        }

        $exhibition = Exhibition::where('exhibition_id', $registration->exhibition_id)
            ->join('countries', 'countries.country_id', '=', 'exhibitions.country')
            ->join('states', 'states.state_subdivision_id', '=', 'exhibitions.state')
            ->join('cities', 'cities.cities_id', '=', 'exhibitions.city')
            ->first();

        $exhibition->logo = isset($exhibition->logo) ? url($exhibition->logo) : null;
        if (!empty($registration->booth_seat_id)) {
            $seat = DB::table('booth_seats')
                ->where('booth_seat_id', $registration->booth_seat_id)
                ->first();
        }
        $exhibition->seat_name = $seat->seat_name ?? null;
        $exhibition->exhibition_booth_id = $seat->exhibition_booth_id ?? null;


        $totalTax = 0;
        $totalServiceFee = 0;
        $data = DB::table('users')->where('user_id', '1')->first();
        $amount=$request->amount;

        // $taxPercentage = $data->artist_tax;
        // $serviceFeePercentage = $data->artist_service_fee;

        // $taxAmount = ($amount * $taxPercentage) / 100;
        // $serviceFeeAmount = ($amount * $serviceFeePercentage) / 100;


        // $totalTax += $taxAmount;
        // $totalServiceFee += $serviceFeeAmount;


        // $totalNewAmount = $amount + $totalTax + $totalServiceFee;



        $data = DB::table('artist_exhibition_registration')->where('registration_code', $registrationCode)->update([

            'amount' => $amount,
                // 'total_amount' => $request->amount,
                // 'tax' => $totalTax,
                // 'service_fee' => $totalServiceFee,
            'payment_id' => $request->payment_id,
            'payment_intent_id' => $request->payment_id,


            'payment_type' => $request->payment_type,
            'status' => 'Active',
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ]);

        $sponsors = DB::table('exhibition_sponsor')
            ->where('exhibition_id', $registration->exhibition_id)
            ->get();

        $spo = [];
        foreach ($sponsors as $sponsor) {
            $logo = url('/') . '/' . $sponsor->logo;
            $spo[] = [
                'logo' => $logo,
            ];
        }



        $baseUrl = url('/');
        $result = [
            'exhibition' => $exhibition,
            'customer_data' => [
                'name' => $registration->name,
                'email' => $registration->email,
                'mobile' => $registration->mobile
            ],
            // 'sponsors' => $spo,
            'logo' => url($exhibition->logo),
            'registration_code' => $registration->registration_code,
            'registration' => $registration,
        ];

        // $baseUrl = url('/');
        // $result = [
        //     'exhibition' => $exhibition,
        //     'customer_data' => [
        //         'name' => $registration->name,
        //         'email' => $registration->email,
        //         'mobile' => $registration->mobile
        //     ],
        //     'sponsors' => $spo,
        //     'logo' => url($exhibition->logo),
        //     'registration_code' => $registration->registration_code,
        // ];

        try {
            $paymentIntent = $stripe->paymentIntents->retrieve($request->payment_id);

            if ($paymentIntent->status == 'succeeded') {
                return response()->json(['status' => "true", 'message' => 'Registration activated successfully for the exhibition.', 'data' => $result], 200);
            } else {
                return response()->json(['status' => "false", 'message' => 'Payment not successful.'], 400);
            }
        } catch (\Exception $e) {
            return response()->json(['hbhbhbhbh' => $e->getMessage()], 500);
        }
    }

    public function artistexhRegCheckoutSession(Request $request)
    {

        $validator = Validator::make($request->all(), [
            // 'customer_unique_id' => 'required|string',
            'amount' => 'required',
            'payment_method' => 'required',
            'registration_code' => 'required',
            'success_url' => 'required',
            'cancel_url' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $admin = User::find(1);

        if (!$admin || empty($admin->secret_key)) {
            return response()->json([
                'status' => false,
                'message' => 'Stripe API credentials not found in the database',
            ], 500);
        }
        if($admin->payment_accepted=='0'){
            return response()->json([
               'status' => false,
           'message' => 'Payments are currently unavailable. Please check back later or contact support for more details.',

            ]);

        }

        // Set Stripe API key dynamically using the stored secret key
        $stripe = new StripeClient($admin->secret_key);
        // $customer_delivery_address_id = $request->customer_delivery_address_id;
        $amount = $request->amount;
        $registration_code = $request->registration_code;
        $payment_method = $request->payment_method;
        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();
        $registration = DB::table('artist_exhibition_registration')->where('registration_code', $request->registration_code)->first();

        $totalTax = 0;
        $totalServiceFee = 0;
        $data = DB::table('users')->where('user_id', '1')->first();


        // $taxPercentage = $data->artist_tax;
        // $serviceFeePercentage = $data->artist_service_fee;

        // $taxAmount = ($amount * $taxPercentage) / 100;
        // $serviceFeeAmount = ($amount * $serviceFeePercentage) / 100;


        // $totalTax += $taxAmount;
        // $totalServiceFee += $serviceFeeAmount;


        $totalNewAmount = $amount + $totalTax + $totalServiceFee;
        if (!$registration) {
            return response()->json(['status' => 'false', 'message' => 'Registration not found.']);
        }

        try {
            $session = $stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => 'usd',
                            'product_data' => [
                                'name' => 'Total Items: ' . 'Data',
                            ],
                            'unit_amount' => max($totalNewAmount, 0) * 100,
                        ],
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'payment',
                'success_url' => $request->success_url . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $request->cancel_url . '?session_id={CHECKOUT_SESSION_ID}',
            ]);

            DB::table('artist_exhibition_registration')->where('registration_code', $request->registration_code)->update([
                // 'customer_id' => $customer_id,
                'payment_id' => $session->id,
                'payment_type' => $payment_method,
               'amount' => $request->amount,
                // 'total_amount' => $request->amount,
                // 'tax' => $totalTax,
                // 'service_fee' => $totalServiceFee,
                'status' => false,
                'inserted_date' => $insertDate,
                'inserted_time' => $insertTime,
            ]);
            return response()->json(['id' => $session->id, 'data' => $session], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'=>false,
                'message' => 'Payment initiation failed, please try again.'

                // 'error' => $e->getMessage()
            ]);
        }
    }

    public function artistexhstripeCheckoutSuccess(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required',
            'status' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }
        $admin = User::find(1);

        if (!$admin || empty($admin->secret_key)) {
            return response()->json([
                'status' => false,
                'message' => 'Stripe API credentials not found in the database',
            ], 500);
        }

        // Set Stripe API key dynamically using the stored secret key
        $stripe = new StripeClient($admin->secret_key);
        $payment_id = $request->session_id;




        try {
            $response = $stripe->checkout->sessions->retrieve($request->session_id);
            $registration = DB::table('artist_exhibition_registration')->where('payment_id', $payment_id)->first();

            if (!$registration) {
                return response()->json(['status' => 'false', 'message' => 'Registration not found.']);
            }

            if ($request->status == true) {
                DB::table('artist_exhibition_registration')->where('payment_id', $request->session_id)
                    ->update([
                        'status' => 'Active',
                        'payment_intent_id'=>$response->payment_intent,

                    ]);
            }


            $exhibition = Exhibition::where('exhibition_id', $registration->exhibition_id)
                ->join('countries', 'countries.country_id', '=', 'exhibitions.country')
                ->join('states', 'states.state_subdivision_id', '=', 'exhibitions.state')
                ->join('cities', 'cities.cities_id', '=', 'exhibitions.city')
                ->first();

            $sponsors = DB::table('exhibition_sponsor')
                ->where('exhibition_id', $registration->exhibition_id)
                ->get();

            $spo = [];
            foreach ($sponsors as $sponsor) {
                $logo = url('/') . '/' . $sponsor->logo;
                $spo[] = [
                    'logo' => $logo,
                ];
            }

            $baseUrl = url('/');
            $result = [
                'exhibition' => $exhibition,
                'customer_data' => [
                    'name' => $registration->name,
                    'email' => $registration->email,
                    'mobile' => $registration->mobile
                ],
                'sponsors' => $spo,
                'logo' => $baseUrl . '/' . $exhibition->logo,
                'registration_code' => $registration->registration_code,
            ];
            return response()->json(['status' => true, 'data' => $response, 'exh_data' => $result], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function createPaymentLink(Request $request)
    {
        // Validate input
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'email' => 'required|email',
            'phone_number' => 'required|string',
            'currency' => 'nullable|string',
        ]);

        try {
            // Fetch admin user (id = 1)
            $admin = User::find(1);

            if (!$admin || empty($admin->secret_key)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Stripe API credentials not found in the database',
                ], 500);
            }

            // Set Stripe API key dynamically
            Stripe::setApiKey($admin->secret_key);

            $amount = $request->amount;
            $customerEmail = $request->email;
            $customerPhone = $request->phone_number;

            // Get customer's IP to detect country
            $ip = $request->ip();
            $geoInfo = Http::get("https://ipapi.co/{$ip}/json/")->json();
            $customerCountry = $geoInfo['country'] ?? 'US';

            // Get customer's native currency
            $exchangeRates = Http::get("https://api.exchangerate-api.com/v4/latest/USD")->json();
            $customerCurrency = $geoInfo['currency'] ?? 'USD';
            $convertedAmount = isset($exchangeRates['rates'][$customerCurrency])
                ? round($amount * $exchangeRates['rates'][$customerCurrency], 2)
                : $amount; // Default if conversion fails

            // Create Stripe Checkout Session
            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower($customerCurrency),
                        'product_data' => [
                            'name' => 'Payment for Services',
                        ],
                        'unit_amount' => $convertedAmount * 100, // Convert to cents
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => url('/payment-success?session_id={CHECKOUT_SESSION_ID}'),
                'cancel_url' => url('/payment-cancel'),
                'customer_email' => $customerEmail,
                'metadata' => [
                    'phone_number' => $customerPhone,
                    'original_amount' => $amount,
                    'original_currency' => 'USD',
                ],
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Payment link generated successfully',
                'payment_link' => $session->url,
                'original_amount' => $amount,
                'converted_amount' => $convertedAmount,
                'currency' => strtoupper($customerCurrency),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create payment link',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function createPaymentLinkNew($amount, $email, $phone_number)
    {
        // Validate input

        try {
            // Fetch admin user (id = 1)
            $admin = User::find(1);

            if (!$admin || empty($admin->secret_key)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Stripe API credentials not found in the database',
                ], 500);
            }

            // Set Stripe API key dynamically
            Stripe::setApiKey($admin->secret_key);

            // $amount = $amount;
            // $customerEmail = $request->email;
            // $customerPhone = $request->phone_number;

            // Get customer's IP to detect country
            // $ip = $request->ip();
            // $geoInfo = Http::get("https://ipapi.co/{$ip}/json/")->json();
            // $customerCountry = $geoInfo['country'] ?? 'US';

            // Get customer's native currency
            $exchangeRates = Http::get("https://api.exchangerate-api.com/v4/latest/USD")->json();
            $customerCurrency = $geoInfo['currency'] ?? 'USD';
            $convertedAmount = isset($exchangeRates['rates'][$customerCurrency])
                ? round($amount * $exchangeRates['rates'][$customerCurrency], 2)
                : $amount; // Default if conversion fails

            // Create Stripe Checkout Session
            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower($customerCurrency),
                        'product_data' => [
                            'name' => 'Payment for Services',
                        ],
                        'unit_amount' => $convertedAmount * 100, // Convert to cents
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => url('/payment-success?session_id={CHECKOUT_SESSION_ID}'),
                'cancel_url' => url('/payment-cancel'),
                'customer_email' => $email,
                'metadata' => [
                    'phone_number' => $phone_number,
                    'original_amount' => $amount,
                    'original_currency' => 'USD',
                ],
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Payment link generated successfully',
                'payment_link' => $session->url,
                'original_amount' => $amount,
                'converted_amount' => $convertedAmount,
                'currency' => strtoupper($customerCurrency),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create payment link',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function genrate_private_link(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ]);
        }
        $validator = Validator::make($request->all(), [
            'private_enquiry_chat_id' => 'required',
            'amount' => 'required',
            'email' => 'required|email',
            'phone_number' => 'required|string',
            'currency' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }
        $user = Auth::guard('api')->user();
        $timezone = $user->timezone ?? "America/Los_Angeles";

        $currentDateTime = now($timezone);
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        $privateChat = DB::table('private_enquiry_chat')
            ->where('private_enquiry_chat_id', $request->private_enquiry_chat_id)
            ->first();

        $customer = Customer::where('customer_id', $privateChat->customer_id)->first();

        $customer_id = $customer->customer_id;
        //   $this->createPaymentLinkNew($customer_id, $request->email,$request->phone_number);
        $paymentResponse = $this->createPaymentLinkNew($request->amount, $customer->email, $customer->phone_number);

        if ($paymentResponse->getStatusCode() !== 200) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to generate payment link',
            ]);
        }
        $paymentData = $paymentResponse->getData();
        DB::table('payment_link')->insert([
            'private_enquiry_chat_id' => $request->private_enquiry_chat_id,
            'customer_id' => $customer->customer_id,
            'payment_link' => $paymentData->payment_link,
            'amount' => $request->amount,
            'mobile' => $request->phone_number,
            'email' => $request->email,
            'currency' => strtoupper($request->currency ?? 'USD'),
            'status' => 'Inactive',
            'isVerify' => '0',
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Payment link generated and saved successfully',
            'payment_link' => $paymentData->payment_link,
        ]);
    }

    public function get_key()
    {

        $data = DB::table('users')
            ->where('user_id', '1')
            ->first();

        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'Data Not Found'
            ]);
        }
        $new = [
            'stripe_key' => $data->stripe_key,
            'secret_key' => $data->secret_key,
        ];
        return response()->json([
            'status' => true,
            'data' => $new
        ]);
    }
    public function get_key_web()
    {
        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ]);
        }
        $data = DB::table('users')
            ->where('user_id', '1')
            ->first();

        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'Data Not Found'
            ]);
        }
        $new = [
            'stripe_key' => $data->stripe_key,
            'secret_key' => $data->secret_key,
        ];
        return response()->json([
            'status' => true,
            'data' => $new
        ]);
    }

    // public function refundPayment($paymentId, $amount,$customerId)
    // {


    //     $user = User::whereNotNull('secret_key')->first();

    //     if (!$user || !$user->secret_key) {
    //         return 0;
    //     }

    //     Stripe::setApiKey($user->secret_key);
    //     $customer=Customer::where('customer_id',$customerId)->fiirst();
    //     try {
    //         $refund = \Stripe\Refund::create([
    //             'payment_intent' => $paymentId,
    //             'amount' => $amount * 100,
    //         ]);
    //         $customerData = [
    //             'customer_name' => $customer->name,
    //             'customer_email' => $customer->email,

    //         ];
    //         try{
    //             Mail::send('emails.return_refund_notification', ['customerData' => $customerData], function ($message) use ($customer) {
    //                 $message->to($customer->email)
    //                     ->subject('Return Response')
    //                     ->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
    //                     ->replyTo(env('MAIL_REPLY_TO_ADDRESS', env('MAIL_FROM_ADDRESS')));
    //             });
    //         }catch (\Exception $e) {
    //             \Log::error('Mail sending failed: ' . $e->getMessage());
    //             // Optionally, return a response or handle the failure gracefully
    //         }


    //         return 1;
    //     } catch (\Stripe\Exception\ApiErrorException $e) {
    //         return 0;
    //     } catch (\Exception $e) {
    //         // Catch general exceptions
    //         return 0;
    //     }
    // }


    public function refundPayment(Request $request)
    {

        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ]);
        }
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required',
            'amount' => 'required',
            'customer_id' => 'required',
            'art_unique_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }


        $user = User::whereNotNull('secret_key')->first();

        if (!$user || !$user->secret_key) {
            return 0;
        }

        $payment_id=$request->payment_id;
        $amount=$request->amount;
        Stripe::setApiKey($user->secret_key);
        $customer=Customer::where('customer_id',$request->customer_id)->first();
        $art=Art::where('art_unique_id',$request->art_unique_id)->first();
        try {
            $refund = \Stripe\Refund::create([
                'payment_intent' => $payment_id,
                'amount' => $amount * 100,
            ]);
            $customerData = [
                'customer_name' => $customer->name,
                'customer_email' => $customer->email,
                'art_unique_id' => $art->art_unique_id,
                'art_name' => $art->title,
                'refund_amount' => $amount,

            ];
            try{
                Mail::send('emails.return_refund_notification', ['customerData' => $customerData], function ($message) use ($customer) {
                    $message->to($customer->email)
                        ->subject('Return Response')
                        ->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
                        ->replyTo(env('MAIL_REPLY_TO_ADDRESS', env('MAIL_FROM_ADDRESS')));
                });
            }catch (\Exception $e) {
                \Log::error('Mail sending failed: ' . $e->getMessage());
                // Optionally, return a response or handle the failure gracefully
            }


            return response()->json([
                'status'=>true,
                'message'=>'Refund Intiates Successfully'
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {

            return response()->json([
                'status'=>false,
                'message'=>'Someting Went Wrong'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'=>false,
                'message'=>'Someting Went Wrong'
            ]);
        }
    }




    public function requestArtistPayment(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 401);
        }

        $user = User::whereNotNull('secret_key')->first();
        if (!$user || !$user->secret_key) {
            return response()->json([
                'status' => false,
                'message' => 'Stripe API credentials not found.',
            ], 500);
        }
        $customer= Customer::where('customer_unique_id', $request->customer_unique_id)->first();
        $amount=$request->amount;
        $bankDetails = BankDetail::where('customer_id', $customer->customer_id)->first();
        if (!$bankDetails) {
            return response()->json([
                'status' => false,
                'message' => 'Bank details not found for this customer.',
            ], 404);
        }

        try {
            $stripe = new StripeClient($user->secret_key);

            if (!empty($bankDetails->digital_payment_id)) {
                $payout = $stripe->payouts->create([
                    'amount' => $amount * 100,
                    'currency' => 'usd',
                    'method' => 'standard',
                    'destination' => $bankDetails->digital_payment_id,
                    'description' => 'Artist payment',
                ]);
            } else {
                if (empty($bankDetails->account_number) || empty($bankDetails->bank_code)) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Bank account details are missing.',
                    ], 400);
                }

                $recipient = $stripe->accounts->create([
                    'type' => 'custom',
                    'country' => 'US',
                    'email' => Auth::guard('api')->user()->email,
                    'capabilities' => [
                        'transfers' => ['requested' => true],
                    ],
                    'external_account' => [
                        'object' => 'bank_account',
                        'country' => 'US',
                        'currency' => 'usd',
                        'account_number' => $bankDetails->account_number,
                        'routing_number' => $bankDetails->bank_code,
                    ],
                ]);
                $payout = $stripe->transfers->create([
                    'amount' => $amount * 100,
                    'currency' => 'usd',
                    'destination' => $recipient->id,
                    'description' => 'Artist payment',
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Payout request successful.',
                'payout_id' => $payout->id
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Payment request failed: ' . $e->getMessage(),
            ], 500);
        }
    }

}
