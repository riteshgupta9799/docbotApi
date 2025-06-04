<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
class HomeController extends Controller
{
    //
    public function contact_form(Request $request)
    {

        $contactData = [
            'email' => $request->email,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'mobile' => $request->mobile,
            'message' => $request->message,
            'inserted_date' => now()->format('Y-m-d'),
            'inserted_time' => now()->format('H:i:s'),
        ];

        $contactId = DB::table('contact_form')->insertGetId($contactData);

        if ($contactId) {
            return response()->json([
                'status' => true,
                'contact_id' => $contactId,
                'message' => 'Contact Enquiry Raised Successfully...',
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Server Error...',
            ], 500); // 500 Internal Server Error
        }
    }
    public function open_otp_validate(Request $request)
    {
        $mobile = $request->mobile;
        $otp = $request->otp;

        if (empty($mobile) || empty($otp)) {
            return response()->json([
                'status' => false,
                'message' => 'Mobile number and OTP are required.'
            ]);
        }

        $customer = DB::table('open_otp')
                    ->where('mobile', $mobile)
                    ->where('otp', $otp)
                    ->first();

        if (is_null($customer)) {

            return response()->json([
                'status' => false,
                'message' => 'Invalid OTP.'
            ]);
        } else {

            DB::table('open_otp')
            ->where('mobile', $mobile)
            ->delete();

            return response()->json([
                'status' => true,
                'message' => 'OTP verified successfully.'
            ]);
        }
    }
    public function contact()
    {



        $results = DB::table('contact')
            ->where('status', 'Active')
            ->orderBy('contact_id', 'DESC')
            ->get();

        $contactArray = [];
        foreach ($results as $row) {
            $contactArray[] = [
                'contact_id' => $row->contact_id,
                'info' => $row->info,
                'heading' => $row->heading,
                'sub_heading' => $row->sub_heading,
                'image' => $row->image,
                'address' => $row->address,
                'mobile' => $row->mobile,
                'email' => $row->email,
                'phone' => $row->phone,
            ];
        }

        $response = !empty($contactArray) ? [
            'status' => true,
            'message' => 'Contact Content Found ...',
            'contact_array' => $contactArray
        ] : [
            'status' => false,
            'message' => 'Contact Content Not Found',
        ];

        // Send response as JSON
        return response()->json($response);
    }

    public function about()
    {
        // Fetch about information from the database
        $results = DB::table('about')
            ->where('status', 'Active')
            ->get();

        // Initialize an empty array for about data
        $aboutArray = [];

        // Transform results to the desired format
        foreach ($results as $row) {
            $aboutArray[] = [
                'about_id' => $row->about_id,
                'title' => $row->title,
                'heading' => $row->heading,
                'sub_heading' => $row->sub_heading,
                'about' => $row->about,
                'para' => $row->para
            ];
        }

        // Prepare response
        $response = !empty($aboutArray) ? [
            'status' => true,
            'message' => 'About Content Found ...',
            'about_array' => $aboutArray
        ] : [
            'status' => false,
            'message' => 'About Content Not Found',
        ];

        // Send response as JSON
        return response()->json($response);
    }
    public function faqs(Request $request)
    {
        // Fetch FAQs from the database where status is 'Active'
        $faqs = DB::table('faq')->where('status', 'Active')->get();

        $faqArray = [];

        // Transform results to the desired format
        foreach ($faqs as $row) {
            $faqArray[] = [
                'faq_id' => $row->faq_id,
                'title' => $row->title,
                // 'heading' => $row->heading,
                // 'sub_heading' => $row->sub_heading,
                // 'about' => 'http://127.0.0.1:8000/about/' . $row->about,
                'para' => $row->para
            ];
        }

        $response = !empty($faqArray) ? [
            'status' => true,
            'message' => 'fAQ Content Found ...',
            'faq_array' => $faqArray
        ] : [
            'status' => false,
            'message' => 'fAQ Content Not Found',
        ];

        // Send response as JSON
        return response()->json($response);
    }
    public function subSubSubCategories(Request $request)
    {
        $SubcategoryId = $request->input('sub_category_2');
        // dd($SubcategoryId);

        // Validate input
        if (empty($SubcategoryId)) {
            return response()->json([
                'status' => false,
                'message' => 'SubCategory1 ID is required'
            ]);
        }


        // Fetch active sub-subcategories based on sub_category_id
        $subSubCategories = DB::table('sub_category_3')
            ->where('sub_category_2_id', $SubcategoryId)
            // ->where('status', 'Active')
            ->get();

        if ($subSubCategories->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Sub Sub Categories Not Found ...'
            ]);
        }

        $discountArray = [];  // Initialize discount_array

        foreach ($subSubCategories as $subSubCategory) {
            $SubcategoryData = [
                'sub_category_3_id' => $subSubCategory->sub_category_2_id,
                'sub_category_3_name' => $subSubCategory->sub_category_3_name,
                'status'=>$subSubCategory->status
                // 'banner' => 'https://alas.genixbit.com/public/images/' . $subSubCategory->image,
                // 'para' => $subSubCategory->para
            ];

            $discountArray[] = $SubcategoryData;
        }

        return response()->json([
            'status' => true,
            'message' => 'Sub Sub Categories Found Successfully',
            'sub_sub_sub_categories_array' => $discountArray
        ]);
    }
    public function live_influincers_list(Request $request)
    {
        $influencerLive = DB::table('influencer_live')
            ->select('influencer_live.*','users.user_unique_id', 'users.name', 'users.user_image', 'users.email', 'users.mobile', 'users.address', 'users.city')
            ->join('users', 'users.user_id', '=', 'influencer_live.user_id')
            ->where('influencer_live.status', 'Live')
            ->get();

        if($influencerLive){
            return response()->json([
                'status' => true,
                'message' => 'Live Influencer Fetched Successfully.',
                'influencer' => $influencerLive,
            ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => 'No Influencer Live.',
            ]);
        }
    }

    public function getCurrencyInfo($countryCode)
    {
        if(!$countryCode){
            return ;
        }

        $country = DB::table('countries')
            ->where('country_code_char2', $countryCode)
            ->orWhere('country_code_char3', $countryCode)
            ->first();

        if (!$country) {
            return null; // Country not found
        }

        $countryCurrency = DB::table('currencies')
            ->where('country', $country->country_name)
            ->first();

        if (!$countryCurrency) {
            return null; // Currency not found
        }

        // Fetch exchange rates
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://openexchangerates.org/api/latest.json?app_id=388ee726b5924e5b9577140a3c7e0347',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ));

        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            $error_msg = curl_error($curl);
            curl_close($curl);
            return response()->json([
                'status' => false,
                'message' => 'Curl Error: ',
            ]);
        }

        curl_close($curl);
        $currencyData = json_decode($response, true);

        if (isset($currencyData['error'])) {
            return response()->json([
                'status' => false,
                'message' => 'API Error: ' . $currencyData['error'],
            ]);
        }

        $currencyCode = $countryCurrency->code;
        $currencysymbol = $countryCurrency->symbol;

        if (isset($currencyData['rates'][$currencyCode])) {
            $rate = $currencyData['rates'][$currencyCode];
            return ['rate' => $rate, 'symbol' => $currencysymbol];
        }
    }
    public function influencer_bag_data(Request $request)
    {
        $bagItems = DB::table('influencer_bag')
            ->where('influencer_bag.status', 'Active')
            ->get();

            $countryCode = $request->countryCode;

        if ($bagItems->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => "No Bag Found."
            ]);
        }

        $latestBags = [];


        foreach ($bagItems as $bagItem) {
            $user_id = $bagItem->user_id;
            if (
                !isset($latestBags[$user_id]) ||
                (new \DateTime($latestBags[$user_id]->inserted_date . ' ' . $latestBags[$user_id]->inserted_time) <
                    new \DateTime($bagItem->inserted_date . ' ' . $bagItem->inserted_time))
            ) {
                $latestBags[$user_id] = $bagItem;
            }
        }

        $result = [];
        foreach ($latestBags as $latestBagItem) {
            $user_id = $latestBagItem->user_id;


            $influencerData = DB::table('users')
                ->where('user_id', $user_id)
                ->select('name', 'user_image')
                ->first();

            $bagId = $latestBagItem->influencer_bag_id;


            $productBagData = DB::table('product_to_bag')
                ->where('influencer_bag_id', $bagId)
                ->orderBy('product_to_bag_id', 'desc')
                ->get();


            if (!$productBagData->isEmpty()) {
                $products = [];
                foreach ($productBagData as $productBag) {


                     $currencyInfo = $this->getCurrencyInfo($countryCode);

                    $productData = DB::table('products')
                        ->where('product_id', $productBag->product_id)
                        ->select('product_name', 'sub_text', 'description', 'discount', 'influincer_percentage', 'net_weight')
                        ->first();

                    // dd($product);
                    $productReviews = DB::table('product_reviews')
                        ->where('product_id', $productBag->product_id)
                        ->where('status', 'Active')
                        ->select(
                            DB::raw('AVG(product_reviews.rating) as average_rating'),
                            DB::raw('COUNT(product_reviews.product_review_id) as review_count')
                        )
                        ->groupBy('product_id')
                        ->first();

                    $formatted_Productrating = $productReviews ? number_format($productReviews->average_rating, 1) : '0';
                    $review_count = $productReviews->review_count ?? 0;

                    $rate = $currencyInfo['rate'] ?? '1';
                    $currencysymbol = $currencyInfo['symbol'] ?? '$';

                    $productVariantData = DB::table('product_varient')
                        ->where('product_varient_id', $productBag->product_varient_id)
                        ->join('products', 'product_varient.product_id', '=', 'products.product_id')
                        ->select(
                            'product_varient.product_varient_id',
                            'product_varient.product_id',
                            'product_varient.colour',
                            'product_varient.quantity',
                            'product_varient.price',
                            'product_varient.total_amount',
                            'products.product_name',
                            'products.product_img'
                        )
                        ->first();

                        $convertedTotalAmount = sprintf('%.2f', ($productVariantData->total_amount) * $rate);
                        $convertedPrice = sprintf('%.2f', ($productVariantData->price) * $rate);
                        $productVariantData->total_amount = $convertedTotalAmount;
                        $productVariantData->price = $convertedPrice;
                        $productVariantData->symbol = $currencysymbol;



                    $size = DB::table('product_varient_size')
                        ->leftJoin('sizes', 'product_varient_size.size_id', '=', 'sizes.size_id')
                        ->where('product_varient_size.size_id', $productBag->size_id)
                        ->select('sizes.size_id', 'sizes.size_name')
                        ->first();

                    if ($productVariantData) {
                        $products[] = [
                            'user_id' => $user_id,
                            'product_to_bag_id' => $productBag->product_to_bag_id,
                            'productData' => $productData,
                            'product_variant' => $productVariantData,
                            'size' => $size,
                            'review_avg' => $formatted_Productrating,
                            'review_count' => $review_count,
                        ];
                    }
                }


                $result[] = [
                    'influencerData' => $influencerData,
                    'bag_id' => $bagId,
                    'bag_name' => $latestBagItem->bag_name,
                    'products' => $products,
                ];
            }
        }

        return response()->json([
            'status' => true,
            'bags' => $result,
            'message' => count($result) ? "Latest bags and products fetched successfully." : "No products in any bags."
        ]);
    }
    public function customer_influencer_bag(Request $request)
    {
        $countryCode = $request->countryCode;

        $influencer_live = DB::table('influencer_live')
            ->where('status', 'Live')
            ->get();

        $result = [];

        foreach ($influencer_live as $influencer) {
            $join_id = $influencer->join_id;
            $user_id = $influencer->user_id;

            $influencer_data = DB::table('users')
                ->where('user_id', $user_id)
                ->select('user_image', 'name', 'user_id')
                ->first();

            $productBagData = DB::table('product_to_bag')
                // ->where('join_id', $join_id)
                ->orderBy('product_to_bag_id', 'desc')
                ->get();

            if (!$productBagData->isEmpty()) {
                $products = [];
                foreach ($productBagData as $productBag) {
                    $currencyInfo = $this->getCurrencyInfo($countryCode);

                    $productData = DB::table('products')
                        ->where('product_id', $productBag->product_id)
                        ->select('product_name', 'sub_text', 'description', 'discount', 'influincer_percentage', 'net_weight')
                        ->first();

                    $productReviews = DB::table('product_reviews')
                        ->where('product_id', $productBag->product_id)
                        ->where('status', 'Active')
                        ->select(
                            DB::raw('AVG(product_reviews.rating) as average_rating'),
                            DB::raw('COUNT(product_reviews.product_review_id) as review_count')
                        )
                        ->groupBy('product_id')
                        ->first();

                    $formatted_Productrating = $productReviews ? number_format($productReviews->average_rating, 1) : '0';
                    $review_count = $productReviews->review_count ?? 0;

                    $rate = $currencyInfo['rate'] ?? '1';
                    $currencysymbol = $currencyInfo['symbol'] ?? '$';

                    $productVariantData = DB::table('product_varient')
                        ->where('product_varient_id', $productBag->product_varient_id)
                        ->join('products', 'product_varient.product_id', '=', 'products.product_id')
                        ->select(
                            'product_varient.product_varient_id',
                            'product_varient.product_id',
                            'product_varient.colour',
                            'product_varient.quantity',
                            'product_varient.price',
                            'product_varient.total_amount',
                            'products.product_name',
                            'products.product_img'
                        )
                        ->first();

                    if ($productVariantData) {
                        $convertedTotalAmount = sprintf('%.2f', ($productVariantData->total_amount) * $rate);
                        $convertedPrice = sprintf('%.2f', ($productVariantData->price) * $rate);
                        $productVariantData->total_amount = $convertedTotalAmount;
                        $productVariantData->price = $convertedPrice;
                        $productVariantData->symbol = $currencysymbol;

                        $size = DB::table('product_varient_size')
                            ->leftJoin('sizes', 'product_varient_size.size_id', '=', 'sizes.size_id')
                            ->where('product_varient_size.size_id', $productBag->size_id)
                            ->select('sizes.size_id', 'sizes.size_name')
                            ->first();

                        $products[] = [
                            'user_id' => $user_id,
                            'product_to_bag_id' => $productBag->product_to_bag_id,
                            'productData' => $productData,
                            'product_variant' => $productVariantData,
                            'size' => $size,
                            'review_avg' => $formatted_Productrating,
                            'review_count' => $review_count,
                        ];
                    }
                }

                $result[] = [
                    'join_id' => $join_id,
                    'products' => $products,
                    'influencer_data' => $influencer_data
                ];
            }
        }

        return response()->json([
            'status' => true,
            'bag_data' => $result
        ]);
    }

    public function get_brands()
    {
        $brands = DB::table('brands')
            ->orderBy('brand_id', 'desc')
            ->get();
        if ($brands->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No Brands found'
            ]);
        } else {
            return response()->json([
                'status' => true,
                'brands' => $brands
            ]);
        }
    }

    public function open_otp(Request $request)
    {

        // if (!Auth::guard('customer_api')->check()) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Unauthorized access.',
        //     ], 400);
        // }

        $validator = Validator::make($request->all(), [
            "mobile" => 'required',
              ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $otp = rand(1000, 9999);

        $mobile = $request->input('mobile');

        $customer = DB::table('customers')
            ->where('mobile', $mobile)
            ->first();


        if (empty($customer)) {

            $otp_data = DB::table('open_otp')
                ->where('mobile', $mobile)
                ->value('otp');

            if (empty($otp_data)) {

                $data = [
                    'mobile' => $mobile,
                    'otp' => $otp
                ];

                DB::table('open_otp')->insert($data);

                $response = [
                    'status' => true,
                    'otp' => $otp,
                    'message' => 'New Customer, OTP sent ...'
                ];

            } else {

                $data = [
                    'otp' => $otp
                ];
                DB::table('open_otp')->where('mobile', $mobile)->update($data);

                $response = [
                    'status' => true,
                    'otp' => $otp,
                    'message' => 'Resending OTP ...'
                ];

            }

        } else {
            $response = [
                'status' => false,
                'message' => 'Customer Already Exists ...'
            ];
        }
        return response()->json($response);

    }

    public function colors(Request $request){

        $colors=$request->colors;

        foreach($colors as $color){
            $data =[
                'colour_name'=>$color['name'],
                'hexa' =>'#'.$color['hex'],
                'rgb'=>$color['rgb']
            ];

            DB::table('colours')
            ->insert($data);

        }

    }
    public function add_subscriber(Request $request){

        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        $email=$request->email;

        $existingSubscriber = DB::table('subscriber')
            ->where('email',$email)
            ->first();

        if($existingSubscriber){
            return response()->json([
            'status'=>false,
            'message'=>'Alredy Subscribed!'
            ]);
        }

        if(!$email){
            return response()->json([
                'status'=>false,
                'message'=>'Email Id Required!'
            ]);
        }
        $data = [
            'email'=>$email,
            'status'=>'Active',
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ];


        DB::table('subscriber')
            ->insert($data);

        return response()->json([
            'status'=>true,
            'message'=>'Subscriber Added Successfully!'
        ]);

    }
    public function get_newsletter()
    {
        $newsletters = DB::table('newsletter')
            ->select('newsletter_id', 'heading', 'image1', 'status')
            // ->where('status', 'Active')
            ->orderBy('newsletter_id', 'DESC')
            ->get();
        $result = null;
        foreach ($newsletters as $newsletter) {
            $content_data = DB::table('newsletter_content')
                ->select('sub_heading', 'paragraph')
                ->where('newsletter_id', $newsletter->newsletter_id)
                ->get();

                // dd($content_data);
            $ar = [
                'newsletter_id' => $newsletter->newsletter_id,
                'image' => $newsletter->image1,
                'title' => $newsletter->heading,
                'status' => $newsletter->status,
            ];
            if ($content_data->isNotEmpty()) {
                $ar['sub_title'] = $content_data[0]->sub_heading;
                $ar['description'] = $content_data[0]->paragraph;
            } else {
                $ar['sub_title'] = null;
                $ar['description'] = null;
            }
            $result[] = $ar;
        }
        return response()->json([
            'status' => true,
            'news' => $result
        ]);
    }
}
