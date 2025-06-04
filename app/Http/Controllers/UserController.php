<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UserController extends Controller
{

    public function index()
    {
        $users = User::all();
        return response()->json([
            'status' => 200,
            'data' => $users
        ], 200);
    }

    public function incrementApiCallCount($apiName, $customer_id)
    {

        DB::table('api_call_counts')
            ->where('customer_id', $customer_id)
            ->where('api_name', $apiName)
            ->where('inserted_date', '<=', Carbon::now()->subDay()->toDateString())
            ->update(['status'=>'Inactive']);

        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();
        $apiCallCount = DB::table('api_call_counts')
            ->where('api_name', $apiName)
            ->where('status','Active')
            ->where('customer_id', $customer_id)
            ->first();



        if ($apiCallCount) {
            DB::table('api_call_counts')
                ->where('customer_id', $customer_id)
                ->where('status','Active')
                ->where('api_name', $apiName)
                ->update([
                    'call_count' => $apiCallCount->call_count + 1,
                    'inserted_date' => $insertDate,
                    'inserted_time' => $insertTime,
                ]);
        } else {
            DB::table('api_call_counts')->insert([
                'api_name' => $apiName,
                'call_count' => 1,
                'customer_id' => $customer_id,
                'status' => 'Active',
                'inserted_date' => $insertDate,
                'inserted_time' => $insertTime,
            ]);
        }
    }

    public function updateProductApiCallCount($product_id)
    {

        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $productApiCount = DB::table('product_viewcount')
            ->where('product_id', $product_id)
            ->first();

        if ($productApiCount) {

            DB::table('product_viewcount')
                ->where('product_id', $product_id)
                ->update([
                    'count' => $productApiCount->count + 1,
                    'date' => $insertDate,
                ]);
        } else {

            DB::table('product_viewcount')->insert([
                'product_id' => $product_id,
                'count' => 1,
                'date' => $insertDate,
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

    public function viewLimit($apiname, $customer_id)
    {
        $count = DB::table('api_call_counts')
            ->where('customer_id', $customer_id)
            ->where('api_name', $apiname)
            ->pluck('call_count')
            ->first();

            return $count ;
    }

    public function findByName(Request $request)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:191',
        ]);

        if ($validator->fails()) {
            // Return validation errors if validation fails
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Fetch user(s) by name
        $name = $request->input('name');
        $user = User::where('name', 'LIKE', "%{$name}%")->get();

        if ($user->isEmpty()) {
            return response()->json([
                'status' => 404,
                'message' => 'User not found'
            ], 404);
        }


        // Return user(s) data as JSON
        return response()->json([
            'status' => 200,
            'data' => $user
        ], 200);
    }
    public function banners()
    {
        // Fetch data from the banners table
        $banners = DB::table('banners')
            ->select('*')
            ->where('status', 'Active')
            ->orderBy('banners_id', 'DESC')
            ->get();

        // Initialize arrays
        $slider_array = [];
        $discount_array = [];

        foreach ($banners as $row) {
            // Prepare banner data
            $banner_data = [
                'banner_id' => $row->banners_id,
                'banner'=>$row->banners,
                'title' => $row->banner_type,
                'para' => $row->para,
                'banner_link' =>  $row->banner_link,
                'status' => $row->status
            ];

            // Separate data based on type
            if ($row->banner_type == 'slider') {
                $slider_array[] = $banner_data;
            } else {
                $discount_array[] = $banner_data;
            }

        }

        // Prepare response
        $response = [
            'status' => true,
            'message' => 'Banners Found Successfully',
            'slider_array' => $slider_array,
            'discount_array' => $discount_array
        ];

        // Return JSON response
        return response()->json($response);
    }

    public function home_category_with_subcategory_()
    {
        $categories = DB::table('category')
            // ->join('products','category.category_id','=','products.category_id')
            ->select('category.category_id', 'category.category_name')
            ->where('category.home', 1)
            ->limit(10)
            ->get();

        if ($categories->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Sub Categories Not Found ...'
            ]);
        }

        $discount_array = [];
        foreach ($categories as $category) {
            $subCategories = DB::table('sub_category_1')
                ->select('sub_category_1.sub_category_1_id', 'sub_category_1.sub_category_1_name')
                ->join('products', 'sub_category_1.sub_category_1_id', '=', 'products.sub_category_1_id')
                ->where('products.status', 'Active')
                ->where('sub_category_1.status', 'Active')
                ->where('products.category_id', $category->category_id)
                ->groupBy('sub_category_1.sub_category_1_id', 'sub_category_1.sub_category_1_name')
                ->get();

            $discount_array1 = [];

            foreach ($subCategories as $subCategory) {
                $subSubCategories = DB::table('sub_category_2')
                    ->select('sub_category_2.sub_category_2_id', 'sub_category_2.sub_category_2_name')
                    ->join('products', 'sub_category_2.sub_category_2_id', '=', 'products.sub_category_2_id')
                    ->where('products.status', 'Active')
                    ->where('sub_category_2.status', 'Active')
                    ->where('sub_category_2.sub_category_1_id', $subCategory->sub_category_1_id)
                    ->groupBy('sub_category_2.sub_category_2_id', 'sub_category_2.sub_category_2_name')
                    ->get();

                $banner_data = [
                    'sub_category_id' => $subCategory->sub_category_1_id,
                    'sub_category_name' => $subCategory->sub_category_1_name,
                    'sub_category_array' => $subSubCategories->toArray()
                ];

                $discount_array1[] = $banner_data;
            }

            $banner_data = [
                'category_id' => $category->category_id,
                'category_name' => $category->category_name,
                'category_array' => $discount_array1
            ];

            $discount_array[] = $banner_data;
        }

        return response()->json([
            'status' => true,
            'message' => 'Sub Categories Found Successfully',
            'categories_array' => $discount_array
        ]);
    }

    public function home_category_with_subcategory()
    {
        $categories = DB::table('category')
            ->join('products','category.category_id','=','products.category_id')
            ->select('category.category_id', 'category.category_name')
            ->groupBy('category.category_id', 'category.category_name')
            ->where('category.home', 1)
            ->limit(10)
            ->get();

        if ($categories->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Sub Categories Not Found ...'
            ]);
        }

        $discount_array = [];
        foreach ($categories as $category) {
            $subCategories = DB::table('sub_category_1')
                ->select('sub_category_1.sub_category_1_id', 'sub_category_1.sub_category_1_name')
                ->join('products', 'sub_category_1.sub_category_1_id', '=', 'products.sub_category_1_id')
                ->where('products.status', 'Active')
                ->where('sub_category_1.status', 'Active')
                ->where('products.category_id', $category->category_id)
                ->groupBy('sub_category_1.sub_category_1_id', 'sub_category_1.sub_category_1_name')
                ->get();

            $discount_array1 = [];

            foreach ($subCategories as $subCategory) {
                $subSubCategories = DB::table('sub_category_2')
                    ->select('sub_category_2.sub_category_2_id', 'sub_category_2.sub_category_2_name')
                    ->join('products', 'sub_category_2.sub_category_2_id', '=', 'products.sub_category_2_id')
                    ->where('products.status', 'Active')
                    ->where('sub_category_2.status', 'Active')
                    ->where('sub_category_2.sub_category_1_id', $subCategory->sub_category_1_id)
                    ->groupBy('sub_category_2.sub_category_2_id', 'sub_category_2.sub_category_2_name')
                    ->get();

                $banner_data = [
                    'sub_category_id' => $subCategory->sub_category_1_id,
                    'sub_category_name' => $subCategory->sub_category_1_name,
                    'sub_category_array' => $subSubCategories->toArray()
                ];

                $discount_array1[] = $banner_data;
            }

            $banner_data = [
                'category_id' => $category->category_id,
                'category_name' => $category->category_name,
                'category_array' => $discount_array1
            ];

            $discount_array[] = $banner_data;
        }

        return response()->json([
            'status' => true,
            'message' => 'Sub Categories Found Successfully',
            'categories_array' => $discount_array
        ]);
    }

    public function allCategories(Request $request)
    {

        $categories = DB::table('category')
                        //  ->where('category_status', 'Active')
                        ->get();

        if ($categories->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Categories Not Found ...'
            ]);
        }

        $discountArray = [];
        foreach ($categories as $category) {
            $bannerData = [
                'category_id' => $category->category_id,
                'category_name' => $category->category_name,
                'category_image' => $category->category_image,
                'status' => $category->category_status
            ];

            $discountArray[] = $bannerData;
        }

        return response()->json([
            'status' => true,
            'message' => 'Categories Found Successfully',
            'all_categories_array' => $discountArray
        ]);
    }

    public function subCategories(Request $request)
    {
        $categoryId = $request->input('category_id');

        if (empty($categoryId)) {
            return response()->json([
                'status' => false,
                'message' => 'Category ID is required'
            ]);
        }

        $categoryExists = DB::table('category')
                            ->where('category_id', $categoryId)
                            ->exists();

        if (!$categoryExists) {
            return response()->json([
                'status' => false,
                'message' => 'Category ID not found'
            ]);
        }
        $subCategories = DB::table('sub_category_1')
                            ->where('category_id', $categoryId)
                            // ->where('status', 'Active')
                            ->get();

        if ($subCategories->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Sub Categories Not Found ...'
            ]);
        }

        $discountArray = [];
        foreach ($subCategories as $subCategory) {
            $bannerData = [
                'sub_category_1_id' => $subCategory->sub_category_1_id,
                'sub_category_1_name' => $subCategory->sub_category_1_name,
                // 'banner' => 'https://alas.genixbit.com/public/images/' . $subCategory->image,
                // 'para' => $subCategory->para
                'status' => $subCategory->status
            ];

            $discountArray[] = $bannerData;
        }

        return response()->json([
            'status' => true,
            'message' => 'Sub Categories Found Successfully',
            'sub_categories_array' => $discountArray
        ]);
    }

    public function subSubCategories(Request $request)
    {
        $SubcategoryId = $request->input('sub_category_1');

        if (empty($SubcategoryId)) {
            return response()->json([
                'status' => false,
                'message' => 'SubCategory1 ID is required'
            ]);
        }

        // Check if the sub_category_1 exists in the category table
        $SubcategoryExists = DB::table('sub_category_2')
                            ->where('sub_category_1_id', $SubcategoryId)
                            ->exists();

        if (!$SubcategoryExists) {
            return response()->json([
                'status' => false,
                'message' => 'Category ID not found'
            ]);
        }

        // Fetch active sub-subcategories based on sub_category_id
        $subSubCategories = DB::table('sub_category_2')
                              ->where('sub_category_1_id', $SubcategoryId)
                            //   ->where('status', 'Active')
                              ->get();

        if ($subSubCategories->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Sub Sub Categories Not Found ...'
            ]);
        }

        $discountArray = [];  // Initialize discount_array

        foreach ($subSubCategories as $subSubCategory) {
            $bannerData = [
                'sub_category_2_id' => $subSubCategory->sub_category_2_id,
                'sub_category_2_name' => $subSubCategory->sub_category_2_name,
                'status' => $subSubCategory->status
                // 'banner' => 'https://alas.genixbit.com/public/images/' . $subSubCategory->image,
                // 'para' => $subSubCategory->para
            ];

            $discountArray[] = $bannerData;
        }

        return response()->json([
            'status' => true,
            'message' => 'Sub Sub Categories Found Successfully',
            'sub_sub_categories_array' => $discountArray
        ]);
    }

    public function homeCategoriesWithProductCount(Request $request)
    {
        $categories = DB::table('category')
                        ->where('category_status', 'Active')
                        ->groupBy('category_id')
                        ->get();

        if ($categories->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Categories Not Found ...'
            ]);
        }

        $discountArray = [];
        foreach ($categories as $category) {
            $products = DB::table('products')
                            ->where('products.category_id', $category->category_id)
                            ->where('products.status', 'Active')
                            ->groupBy('product_id')
                            ->get();

            // $products = DB::table('products')
            //         ->leftJoin('product_varient', 'products.product_id', '=', 'product_varient.product_id')
            //         ->leftJoin('product_varient_images', 'product_varient.product_varient_id', '=', 'product_varient_images.product_varient_id')
            //         ->where('products.category_id', $category->category_id)
            //         ->where('products.status', 'Active')
            //         // ->whereNotNull('product_varient.product_varient_id')

            //         ->whereNotNull('product_varient_images.images') // Ensure that at least one variant has an image
            //         ->distinct()
            //         ->get();

                            $productCount=count($products);
            // dd($productCount);

            if ($productCount >= 1) {
                $bannerData = [
                    'category_id' => $category->category_id,
                    'category_name' => $category->category_name,
                    'category_img' => !empty($category->category_image) ?  $category->category_image : '',
                    'category_color_code' => $category->category_color_code,
                    'product_count' => $productCount,
                ];
                $discountArray[] = $bannerData;
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Categories Found Successfully',
            'categories_array' => $discountArray
        ]);
    }

    public function getAllCountries()
    {
        $allCountries= DB::table('countries')->orderBy('country_name', 'asc') ->get();
        return response()->json([
         'status' => true,
         'message' => 'All Countries Found Successfully',
         'allCountries' => $allCountries   ]);

    }

    public function getStateofACountry(Request $request)
    {
         $country_id=$request->country_id;

       $states=  DB::table('states')
         ->where('country_id',$country_id)
         ->orderBy('state_subdivision_name', 'ASC')
         ->get();
         return response()->json([
             'status' => true,
             'message' => 'All states Found Successfully',
             'states' => $states
         ]);

    }



    public function register_as_seller_(Request $request)
    {
        date_default_timezone_set('Asia/Kolkata');

        $lastUserId = DB::table('users')->orderBy('user_id', 'DESC')->value('user_id') ?? 0;
        // dd($lastUserId);
        $state = DB::table('states')
            ->select('state_subdivision_id', 'state_subdivision_code')
            ->where('state_subdivision_id', $request->input('state'))
            ->first();
        // dd($state);
        $uniqueId = $state->state_subdivision_code . 'S' . $state->state_subdivision_id . $lastUserId;

        // Prepare seller info array
        $sellerInfo = [
            'name' => $request->name,
            'password' => Hash::make($request->password), // Hash the password
            'address' => $request->address,
            'city' => $request->city,
            'state' => $request->state,
            'country' => $request->country,
            'inserted_date' => date('Y-m-d'),
            'inserted_time' => date('H:i:s'),
            'email' => $request->email,
            'role' => 'seller',
            'mobile' => $request->mobile,
            'status' => 'Active',
            'locality' => $request->locality,
            'unique_id' => $uniqueId,
            'rating' => '0',
            'verify_mobile'=>'No',
            'verify_email'=>'No'
        ];

        $existingSellerEmail = DB::table('users')->where('email', $request->email)->where('role', 'influencer')->first();
        if (!$existingSellerEmail) {
            $existingSellerMobile = DB::table('users')->where('mobile', $request->mobile)->where('role', 'influencer')->first();
            if (!$existingSellerMobile) {
                $userId = DB::table('users')->insertGetId($sellerInfo);
                if ($userId) {
                    $business_info_arr = [
                        'business_name' => $request->business_name,
                        'registration_number' => $request->registration_number,
                        'address1' => $request->address1,
                        'address2' => $request->address2,
                        'postal_code' => $request->postal_code,
                        'city' => $request->city,
                        'state_subdivision_id' => $request->state_subdivision_id,
                        'country_id' => $request->country_id,
                        'inserted_date' => date('Y-m-d'),
                        'inserted_time' => date('H:i:s'),
                        'user_id' => $userId

                    ];

                    DB::table('business_info')->insert($business_info_arr);

                    $bank_details_arr = [
                        'account_holder_name' => $request->account_holder_name,
                        'account_number' => $request->account_number,
                        'ifsc_code' => $request->ifsc_code,
                        'user_id' => $userId
                    ];
                    $bank_detail_user = DB::table('bank_details')->insert($bank_details_arr);


                    $store_info_arr = [
                        'store_name' => $request->store_name,
                        'address1' => $request->address1,
                        'address2' => $request->address2,
                        'postal_code' => $request->postal_code,
                        'city' => $request->city,
                        'state_subdivision_id' => $request->state_subdivision_id,
                        'country_id' => $request->country_id,
                        'user_id' => $userId
                    ];
                    $store_detail_user = DB::table('store_info')->insert($store_info_arr);


                    return response()->json([
                        'status' => true,
                        'message' => 'Seller Added Successfully'
                    ]);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Seller Error ..'
                    ]);
                }
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Mobile Number Already Registered As Seller ..'
                ]);
            }

        } else {
            return response()->json([
                'status' => false,
                'message' => 'Email Id Already Registered As Seller ..'
            ]);
        }

    }



    public function reset_seller_password(Request $request)
    {

        $userId = $request->input('user_id');
        $otp = $request->input('otp');
        $password = $request->input('password');

        // Check if the seller with the given user_id and otp exists
        $seller = DB::table('users')
            ->where('user_id', $userId)
            ->where('mobile_otp', $otp)
            ->where('role', 'seller')
            ->first();

        if (!$seller) {
            // Seller not found or invalid OTP
            return response()->json([
                'status' => false,
                'message' => 'Invalid OTP ...'
            ]);
        }

        // Update the seller's password and verification status
        DB::table('users')
            ->where('user_id', $userId)
            ->update([
                'verify_mobile' => 'Yes',
                'password' => Hash::make($password) // Hash the password
            ]);

        // Retrieve the updated seller data
        $updatedSeller = DB::table('users')
            ->where('user_id', $userId)
            ->first();

        // Prepare success response
        return response()->json([
            'status' => true,
            'user_id' => $updatedSeller->user_id,
            'message' => 'Seller Found ...',
            'seller_data' => $updatedSeller
        ]);
    }













    public function add_product_seller__(Request $request)
    {


        $currentDateTime = now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        $productInfo = $request->product_info[0];

        $productData = [
            'product_name' => $productInfo['product_name'],
            'sub_text' => $productInfo['sub_text'] ?? '',
            'description' => $productInfo['description'],
            'colour' => $productInfo['colour'],
            'net_weight' => $productInfo['net_weight'],
            'manage_by' => $productInfo['manage_by'],
            'brand_name' => $productInfo['brand_name'] ?? '',
            'stock' => $productInfo['quantity'],
            'stock_status' => $productInfo['stock_status'],

            'discount' => $productInfo['discount'],
            'price' => $productInfo['price'] - ($productInfo['price'] * $productInfo['discount'] / 100),
            'total_amount' => $productInfo['price'],

            'category_id' => $productInfo['category_id'],
            'sub_category_1_id' => $productInfo['sub_category_1_id'] ?? null,
            'sub_category_2_id' => $productInfo['sub_category_2_id'] ?? null,
            'sub_category_3_id' => $productInfo['sub_category_3_id'] ?? null,


            'influincer_percentage' => $productInfo['influincer_percentage'] ?? 0,
            'tax_percentage' => $productInfo['tax_percentage'] ?? 0,
            'coupen_code' => $productInfo['coupen_code'] ?? '',
            'coupen_code_discount' => $productInfo['coupen_code_discount'] ?? 0,

            'user_id' => $productInfo['user_id'],
            'status' => 'Inactive',
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ];

        $productId = DB::table('products')->insertGetId($productData);

        foreach ($productInfo['img_url'] as $image) {
            DB::table('product_images')->insert([
                'product_id' => $productId,
                'images' => $image['url'],
                'inserted_date' => $insertDate,
                'inserted_time' => $insertTime,
            ]);
        }

        foreach ($productInfo['sizes'] as $size) {
            DB::table('main_product_sizes')->insert([
                'product_id' => $productId,
                'size_id' => $size['size_id'],
                'stock' => $size['quantity']
            ]);
        }

        DB::table('products')->where('product_id', $productId)
            ->update(['product_img' => $productInfo['img_url'][0]['url']]);


        if (!empty($request->varient_data)) {
            foreach ($request->varient_data as $varientData) {
                $variantInsertData = [
                    'product_id' => $productId,
                    'colour' => $varientData['color'],
                    'price' => $varientData['price'],
                    'inserted_date' => $insertDate,
                    'inserted_time' => $insertTime,
                ];

                $lastInsertedId = DB::table('product_varient')->insertGetId($variantInsertData);

                // Insert variant images
                foreach ($varientData['var_images'] as $image) {
                    $imageData = [
                        'images' => $image['url'],
                        'product_varient_id' => $lastInsertedId,
                        'inserted_date' => $insertDate,
                        'inserted_time' => $insertTime,
                    ];
                    DB::table('product_varient_images')->insertGetId($imageData);
                }

                // Insert variant sizes
                foreach ($varientData['sizes'] as $size) {
                    $sizeData = [
                        'stock' => $size['quantity'],
                        'size_id' => $size['size_id'],
                        'product_varient_id' => $lastInsertedId,
                        'inserted_date' => $insertDate,
                        'inserted_time' => $insertTime,
                    ];
                    DB::table('product_varient_size')->insertGetId($sizeData);
                }
            }
        }
        if ($request->input('manage_by') == '0') {
            DB::table('prodcut_delivery')
                ->where('user_id', $request->user_id)
                ->update([
                    'product_id' => $productId
                ]);

        }

        // Prepare the response
        return response()->json([
            'status' => true,
            'message' => 'Product and Variants Added Successfully',
            'product_id' => $productId,
        ]);
    }

    public function user_login_old(Request $request)
    {
        // Validate incoming request data
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $email = $validated['email'];
        $password = $validated['password'];

        // Fetch user based on email
        $user = DB::table('users')->where('email', $email)->first();

        // Check if user exists
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid Email!',
            ]);
        }

        $currentDateTime = now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();


        // Verify the password
        if (!Hash::check($password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid Password...',
            ]);
        }
        else{

            if ($user->status !== 'Active') {
                return response()->json([
                    'status' => false,
                    'message' => 'Your Account is Deactivated!',
                    'account' => 'Deactivated',
                ]);
            }
        }

        // Log last login details
        DB::table('user_last_login_details')->insert([
            'user_id' => $user->user_id,
            'inserted_date' => $insertDate,
            'inserted_time' =>$insertTime,
            'timestamp' => now()->timestamp * 1000,
        ]);

        // Prepare success response
        return response()->json([
            'status' => true,
            'role' => $user->role,
            'message' => 'User Found...',
            'user_data' => $user,
        ]);
    }







    // public function register_as_seller(Request $request)
    // {
    //     date_default_timezone_set('Asia/Kolkata');

    //     // Define validation rules
    //     $rules = [
    //         'seller_info.firstName' => 'required|string|max:255',
    //         'seller_info.lastName' => 'required|string|max:255',
    //         'seller_info.email' => 'required|email|unique:users,email',
    //         'seller_info.mobile' => 'required|string|unique:users,mobile',

    //     ];

    //     // Validate request data
    //     $validator = Validator::make($request->json()->all(), $rules);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Validation Failed',
    //             'errors' => $validator->errors()
    //         ], 422);
    //     }

    //     $data = $request->json()->all();


    //     $sellerInfo = $data['seller_info'] ?? [];
    //     // $businessInfo = $data['business_info'] ?? [];
    //     $bankDetails = $data['bank_details'] ?? [];
    //     // $storeInfo = $data['store_info'] ?? [];

    //     // Fetch the last user ID for unique ID generation
    //     $lastUserId = DB::table('users')->orderBy('user_id', 'DESC')->value('user_id') ?? 0;

    //     // Fetch state subdivision details
    //     $state = DB::table('states')
    //         ->select('state_subdivision_id', 'state_subdivision_code')
    //         ->where('state_subdivision_id', $sellerInfo['state'] ?? '')
    //         ->first();

    //     if ($state) {
    //         $uniqueId = $state->state_subdivision_code . 'S' . $state->state_subdivision_id . $lastUserId;
    //     } else {
    //         $uniqueId = 'UnknownStateS' . ($lastUserId + 1); // Default or handle missing state
    //     }
    //     $role = $sellerInfo['role'] ?? '';

    //     $currentDateTime = Carbon::now('Asia/Kolkata');
    //     $insertDate = $currentDateTime->toDateString();
    //     $insertTime = $currentDateTime->toTimeString();

    //     $date=DB::table('ads')
    //     ->where('admin_subscription_id','1')
    //     ->select('subscription_period')
    //     ->first();
    //     $subscriptionPeriod = $date->subscription_period;
    //     $validUpto = $currentDateTime->copy()->addDays($subscriptionPeriod)->toDateString();





    //     $sellerData = [
    //         'name' => $sellerInfo['firstName'] . ' ' . $sellerInfo['lastName'] ?? '',
    //         'password' => Hash::make($sellerInfo['password'] ?? ''),
    //         'email' => $sellerInfo['email'] ?? '',
    //         'mobile' => $sellerInfo['mobile'] ?? '',
    //         'unique_id' => $uniqueId,
    //         'rating' => '0',
    //         'role' => $role,
    //         'inserted_date' => $insertDate,
    //         'inserted_time' => $insertTime,
    //         'valid_upto' => $validUpto,
    //         'permission' => '1',
    //         'views' => '0'
    //     ];


    //     $existingSellerEmail = DB::table('users')
    //         ->where('email', $sellerData['email'])
    //         // ->where('role', 'seller')
    //         ->first();

    //     if (!$existingSellerEmail) {

    //         $existingSellerMobile = DB::table('users')
    //             ->where('mobile', $sellerData['mobile'])
    //             // ->where('role', 'seller')
    //             ->first();

    //         if (!$existingSellerMobile) {

    //             $userId = DB::table('users')->insertGetId(array_merge($sellerData, ['status' => 'Active']));

    //             $user_data = DB::table('users')->where('user_id', $userId)
    //                 ->select(
    //                     'user_id',
    //                     'name',
    //                     'address',
    //                     'city',
    //                     'state',
    //                     'country',
    //                     'email',
    //                     'mobile',
    //                     'status',
    //                     'locality',
    //                     'rating',
    //                     'gender',
    //                     'valid_upto'
    //                 )->get();
    //             $role = DB::table('users')->where('user_id', $userId)->select('role')->get();

    //             if ($userId) {
    //                 // Prepare and insert business information
    //                 // $businessData = [
    //                 //     'business_name' => $businessInfo['businessName'] ?? '',
    //                 //     'registration_number' => $businessInfo['companyRegisterNumber'] ?? '',
    //                 //     'city' => $businessInfo['city'] ?? '',
    //                 //     'country_id' => $businessInfo['country_id'] ?? '',
    //                 //     'state_subdivision_id' => $businessInfo['state'] ?? '',
    //                 //     'address1' => $businessInfo['apartment'] ?? '',
    //                 //     'address2' => $businessInfo['address'] ?? '',
    //                 //     'postal_code' => $businessInfo['postal_code'] ?? '',
    //                 //     'user_id' => $userId,
    //                 //     'inserted_date' => $insertDate,  // Add current date
    //                 //     'inserted_time' => $insertTime,
    //                 // ];
    //                 // DB::table('business_info')->insert($businessData);

    //                 // Prepare and insert bank details
    //                 $bankData = [
    //                     'account_holder_name' => $bankDetails['accountHoldername'] ?? '',
    //                     'account_number' => $bankDetails['bankAccountNo'] ?? '',
    //                     'ifsc_code' => $bankDetails['IFSCcode'] ?? '',
    //                     'user_id' => $userId,
    //                     'status' => 'Active',
    //                     'inserted_date' => $insertDate,  // Add current date
    //                     'inserted_time' => $insertTime,

    //                 ];

    //                 DB::table('bank_details')->insert($bankData);

    //                 // // Prepare and insert store information
    //                 // $storeData = [
    //                 //     'store_name' => $storeInfo['storeName'] ?? '',
    //                 //     'country_id' => $storeInfo['storeCountry'] ?? '',
    //                 //     'state_subdivision_id' => $storeInfo['storeState'] ?? '',
    //                 //     'city' => $storeInfo['storeCity'] ?? '',
    //                 //     'address1' => $storeInfo['storeAppartment'] ?? '',
    //                 //     'address2' => $storeInfo['storeAddress'] ?? '',
    //                 //     'postal_code' => $storeInfo['storePostalCode'] ?? '',
    //                 //     'user_id' => $userId,
    //                 //     'inserted_date' => $insertDate,  // Add current date
    //                 //     'inserted_time' => $insertTime,
    //                 // ];
    //                 // DB::table('store_info')->insert($storeData);

    //                 return response()->json([
    //                     'status' => true,
    //                     'message' => 'Seller Added Successfully',
    //                     'user_data' => $user_data[0],
    //                     'role' => $role
    //                 ]);
    //             } else {
    //                 return response()->json([
    //                     'status' => false,
    //                     'message' => 'Seller Error ..'
    //                 ]);
    //             }
    //         } else {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Mobile Number Already Registered As Seller ..'
    //             ]);
    //         }
    //     } else {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Email Id Already Registered As Seller ..'
    //         ]);
    //     }
    // }

    public function register_as_seller__(Request $request)
    {
        date_default_timezone_set('Asia/Kolkata');

        // Define validation rules
        $rules = [
            'seller_info.firstName' => 'required|string|max:255',
            'seller_info.lastName' => 'required|string|max:255',
            'seller_info.email' => 'required|email|unique:users,email',
            'seller_info.mobile' => 'required|string|unique:users,mobile',
            // 'seller_info.password' => 'required|string|min:6',
            // 'business_info.businessName' => 'required|string|max:255',
            // 'business_info.companyRegisterNumber' => 'required|string|max:255',
            // 'business_info.city' => 'required|string|max:255',
            // 'business_info.country_id' => 'required|integer',
            // 'business_info.state' => 'required|integer',
            // 'business_info.apartment' => 'required|string|max:255',
            // 'business_info.address' => 'required|string|max:255',
            // 'business_info.postal_code' => 'required|string|max:10',
            // 'bank_details.accountHoldername' => 'required|string|max:255',
            // 'bank_details.bankAccountNo' => 'required|string|max:20',
            // 'bank_details.IFSCcode' => 'required|string|max:11',
            // 'store_info.storeName' => 'required|string|max:255',
            // 'store_info.storeCountry' => 'required|integer',
            // 'store_info.storeState' => 'required|integer',
            // 'store_info.storeCity' => 'required|string|max:255',
            // 'store_info.storeAppartment' => 'required|string|max:255',
            // 'store_info.storeAddress' => 'required|string|max:255',
            // 'store_info.storePostalCode' => 'required|string|max:10',
        ];

        // Validate request data
        $validator = Validator::make($request->json()->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation Failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->json()->all();


        $sellerInfo = $data['seller_info'] ?? [];
        // $businessInfo = $data['business_info'] ?? [];
        $bankDetails = $data['bank_details'] ?? [];
        // $storeInfo = $data['store_info'] ?? [];

        // Fetch the last user ID for unique ID generation
        $lastUserId = DB::table('users')->orderBy('user_id', 'DESC')->value('user_id') ?? 0;

        // Fetch state subdivision details
        $state = DB::table('states')
            ->select('state_subdivision_id', 'state_subdivision_code')
            ->where('state_subdivision_id', $sellerInfo['state'] ?? '')
            ->first();

        if ($state) {
            $uniqueId = $state->state_subdivision_code . 'S' . $state->state_subdivision_id . $lastUserId;
        } else {
            $uniqueId = 'UnknownStateS' . ($lastUserId + 1); // Default or handle missing state
        }
        $role = $sellerInfo['role'] ?? '';
        // Get current date and time
        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString(); // Current date
        $insertTime = $currentDateTime->toTimeString(); // Current time
        $after20DaysDate = $currentDateTime->copy()->addDays(20)->toDateString();

        // Prepare seller info array
        $sellerData = [
            'name' => $sellerInfo['firstName'] . $sellerInfo['lastName'] ?? '',
            'password' => Hash::make($sellerInfo['password'] ?? ''), // Hash the password
            'email' => $sellerInfo['email'] ?? '',
            'mobile' => $sellerInfo['mobile'] ?? '',
            'unique_id' => $uniqueId,
            'rating' => '0',
            'role' => $role,
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
            'valid_upto'=>$after20DaysDate,
            'permission'=>'1',
            'views'=>'0'
        ];

        // Check for existing seller by email
        $existingSellerEmail = DB::table('users')
            ->where('email', $sellerData['email'])
            // ->where('role', 'seller')
            ->first();

        if (!$existingSellerEmail) {
            // Check for existing seller by mobile number
            $existingSellerMobile = DB::table('users')
                ->where('mobile', $sellerData['mobile'])
                // ->where('role', 'seller')
                ->first();

            if (!$existingSellerMobile) {
                // Insert new seller
                $userId = DB::table('users')->insertGetId(array_merge($sellerData, ['status' => 'Active']));

                $user_data =DB::table('users')->where('user_id',$userId)
                ->select('user_id','name','address','city','state','country','email','role','mobile','status',
                'locality','rating','gender','valid_upto')->get();

                if ($userId) {
                    // Prepare and insert business information
                    // $businessData = [
                    //     'business_name' => $businessInfo['businessName'] ?? '',
                    //     'registration_number' => $businessInfo['companyRegisterNumber'] ?? '',
                    //     'city' => $businessInfo['city'] ?? '',
                    //     'country_id' => $businessInfo['country_id'] ?? '',
                    //     'state_subdivision_id' => $businessInfo['state'] ?? '',
                    //     'address1' => $businessInfo['apartment'] ?? '',
                    //     'address2' => $businessInfo['address'] ?? '',
                    //     'postal_code' => $businessInfo['postal_code'] ?? '',
                    //     'user_id' => $userId,
                    //     'inserted_date' => $insertDate,  // Add current date
                    //     'inserted_time' => $insertTime,
                    // ];
                    // DB::table('business_info')->insert($businessData);

                    // Prepare and insert bank details
                    $bankData = [
                        'account_holder_name' => $bankDetails['accountHoldername'] ?? '',
                        'account_number' => $bankDetails['bankAccountNo'] ?? '',
                        'ifsc_code' => $bankDetails['IFSCcode'] ?? '',
                        'user_id' => $userId,
                        'inserted_date' => $insertDate,  // Add current date
                        'inserted_time' => $insertTime,
                        'status'=>'Active'
                    ];

                    DB::table('bank_details')->insert($bankData);

                    // // Prepare and insert store information
                    // $storeData = [
                    //     'store_name' => $storeInfo['storeName'] ?? '',
                    //     'country_id' => $storeInfo['storeCountry'] ?? '',
                    //     'state_subdivision_id' => $storeInfo['storeState'] ?? '',
                    //     'city' => $storeInfo['storeCity'] ?? '',
                    //     'address1' => $storeInfo['storeAppartment'] ?? '',
                    //     'address2' => $storeInfo['storeAddress'] ?? '',
                    //     'postal_code' => $storeInfo['storePostalCode'] ?? '',
                    //     'user_id' => $userId,
                    //     'inserted_date' => $insertDate,  // Add current date
                    //     'inserted_time' => $insertTime,
                    // ];
                    // DB::table('store_info')->insert($storeData);

                    return response()->json([
                        'status' => true,
                        'message' => 'Seller Added Successfully',
                        'user_data'=>$user_data[0],
                        'role'=> $role
                    ]);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Seller Error ..'
                    ]);
                }
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Mobile Number Already Registered As Seller ..'
                ]);
            }
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Email Id Already Registered As Seller ..'
            ]);
        }
    }









    public function removeAccount(Request $request)
    {
        $data = $request->json()->all();

        $userInfo = $data['user_info'] ?? [];


        $user = DB::table('users')->where('user_id', $userInfo['user_id'])->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found'
            ], 404);
        }

        $updateStatus = [
            'status' => $userInfo['status'],
        ];
        DB::table('users')->where('user_id', $userInfo['user_id'])->update($updateStatus);
        return response()->json([
            'status' => true,
            'message' => 'Profile Status updated successfully'
        ]);


    }



    public function passwordsendotp(Request $request)
    {
        $email = $request->input('email');
        $mobile = $request->input('mobile');
        $type = $request->input('type');


        if ($type == 'user') {
            $finduser = DB::table('users');
        } else if ($type == 'customer') {
            $finduser = DB::table('customers');
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Something Went Wrong'
            ]);
        }

        if ($email) {
            $finduser->where('email', $email);
        } else if ($mobile) {
            $finduser->where('mobile', $mobile);
        } else {
            return response()->json(['status' => false, 'message' => 'No valid method provided'], 400);
        }

        $user = $finduser->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Generate and store OTP
        $otp = rand(1000, 9999);

        if ($type == 'user') {
            $finduserOTP = DB::table('users');
        } else if ($type == 'customer') {
            $finduserOTP = DB::table('customers');
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Something Went Wrong'
            ]);
        }

        if ($email) {
            $finduserOTP->where('email', $email)->update([
                'email_otp' => $otp
            ]);
        } else if ($mobile) {
            $finduserOTP->where('mobile', $mobile)->update([
                'mobile_otp' => $otp
            ]);
        } else {
            return response()->json(['status' => false, 'message' => 'No valid method provided'], 400);
        }

        return response()->json([
            'status' => true,
            'message' => 'otp send successfully',
            'otp' => $otp
        ]);
    }

    public function passwordverifyotp(Request $request)
    {

        $email = $request->input('email');
        $mobile = $request->input('mobile');
        $otp = $request->input('otp');
        $type = $request->input('type');



        if ($type == 'user') {
            $userfind = DB::table('users');
        } else if ($type == 'customer') {
            $userfind = DB::table('customers');
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Something Went Wrong'
            ]);
        }

        if ($email) {
            $userfind->where('email', $email);
        } else if ($mobile) {
            $userfind->where('mobile', $mobile);
        } else {
            return response()->json(['status' => false, 'message' => 'Invalid OTP']);
        }

        $user = $userfind->first();

        if (!$user) {
            return response()->json(['status' => false, 'message' => 'User not found']);
        }

        if ($email) {
            if ($user->email_otp == $otp) {
                return response()->json(['status' => true, 'message' => 'OTP verified successfully']);
            } else {
                return response()->json(['status' => false, 'message' => 'Invalid OTP']);
            }
        } else if ($mobile) {
            if ($user->mobile_otp == $otp) {
                return response()->json(['status' => true, 'message' => 'OTP verified successfully']);
            } else {
                return response()->json(['status' => false, 'message' => 'Invalid OTP']);
            }
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Something Went Wrong'
            ]);
        }
    }

    public function updatePassword(Request $request)
    {
        $email = $request->input('email');
        $mobile = $request->input('mobile');
        $type = $request->input('type');
        $password = $request->input('password');

        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        if ($type == "user") {
            $lastpassuser = DB::table('users');
        } else if ($type == 'customer') {
            $lastpassuser = DB::table('customers');
        } else {
            return response()->json(['status' => false, 'message' => 'No valid method provided'], 400);
        }

        if ($email) {
            $lastpassuser->where('email', $email);
        } else if ($mobile) {
            $lastpassuser->where('mobile', $mobile);
        } else {
            return response()->json(['status' => false, 'message' => 'No valid method provided'], 400);
        }

        $user = $lastpassuser->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'user not found'
            ], 404);
        }

        $oldpassword = $user->password;


        if ($type == 'user') {
            $userId = $user->user_id;
        } else if ($type == 'customer') {
            $userId = $user->customer_id;
        }

        if ($type == 'user') {
            DB::table('user_last_password_details')->insert([
                'user_id' => $userId,
                'password' => $oldpassword,
                'inserted_date' => $insertDate,  // Add current date
                'inserted_time' => $insertTime,
                'timestamp' => Carbon::now()->timestamp * 1000,

            ]);
        } else if ($type == 'customer') {
            DB::table('customer_last_password_details')->insert([
                'customer_id' => $userId,
                'password' => $oldpassword,
                'inserted_date' => $insertDate,  // Add current date
                'inserted_time' => $insertTime,
                'timestamp' => Carbon::now()->timestamp * 1000,

            ]);
        } else {
            return response()->json(['status' => false, 'message' => 'No valid method provided'], 400);
        }

        if ($type == 'user') {
            $singleUser = DB::table('users');
        } else if ($type == 'customer') {
            $singleUser = DB::table('customers');
        } else {
            return response()->json(['status' => false, 'message' => 'No valid method provided'], 400);
        }

        if ($email) {
            $singleUser->where('email', $email);
        } else if ($mobile) {
            $singleUser->where('mobile', $mobile);
        }
        $singleUser->update([
            'password' => Hash::make($password)
        ]);

        if ($singleUser) {
            return response()->json(['status' => true, 'message' => 'Password updated successfully']);
        } else {
            return response()->json(['status' => false, 'message' => 'User not found']);

        }


    }


    public function get_user_products(Request $request)
    {
        $user_id = $request->input('user_id');

        $products = DB::table('products')
            ->leftJoin('category', 'category.category_id', '=', 'products.category_id')
            ->leftJoin('sub_category_1', 'sub_category_1.sub_category_1_id', '=', 'products.sub_category_1_id')
            ->leftJoin('sub_category_2', 'sub_category_2.sub_category_2_id', '=', 'products.sub_category_2_id')
            ->leftJoin('sub_category_3', 'sub_category_3.sub_category_3_id', '=', 'products.sub_category_3_id')
            ->where('products.user_id', $user_id)
            ->get();

        if (!$products->isEmpty()) {
            $finalData = [];

            foreach ($products as $product) {

                $product_images = DB::table('product_images')
                    ->where('product_id', $product->product_id)
                    ->get();

                $varients = [];
                $varient_list = DB::table('product_varient')
                    ->where('product_varient.product_id', $product->product_id)
                    ->get();

                foreach ($varient_list as $var) {
                    $sizes = DB::table('product_varient_size')
                        ->where('product_varient_size.product_varient_id', $var->product_varient_id)
                        ->get();


                    $images = DB::table('product_varient_images')
                        ->where('product_varient_images.product_varient_id', $var->product_varient_id)
                        ->get();


                    $varients[] = [
                        'varient_id' => $var->product_varient_id,
                        'color' => $var->colour,
                        'stock' => $var->stock,
                        'sizes' => $sizes,
                        'varient_images' => $images
                    ];
                }


                $productData = [
                    'product_id' => $product->product_id,
                    'product_base_image' => $product->product_img,
                    'product_all_image' => $product_images,
                    'sub_text' => $product->sub_text,
                    'description' => $product->description,
                    'price' => $product->price,
                    'total_amount' => $product->total_amount,
                    'discount' => $product->discount,
                    'brand_name' => $product->brand_name,
                    'quantity' => $product->quantity,
                    'stock' => $product->product_img,
                    'stock_status' => $product->stock_status,
                    'net_weight' => $product->net_weight,
                    'product_name' => $product->product_name,
                    'carient' => $varients,
                    'category_1' => $product->sub_category_1_name,
                    'category_2' => $product->sub_category_2_name,
                    'category_3' => $product->sub_category_3_name,
                ];

                $finalData[] = $productData;
            }


            return response()->json([
                'status' => true,
                'message' => 'Products Found Successfully',
                'data' => $finalData
            ]);

        } else {

            return response()->json([
                'status' => false,
                'message' => 'No Products Added Yet',
            ]);
        }
    }

    public function product_stock_status(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,product_id',
            'status' => 'required|string|max:50',
        ]);

        $productId = $request->product_id;

        $updated = DB::table('products')
            ->where('product_id', $productId)
            ->update(['stock_status' => $request->status]);

        if ($updated) {
            return response()->json([
                'status' => true,
                'message' => 'Product Stock Status Set To ' . $request->status
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update product stock status.'
            ], 500);
        }
    }

    public function get_main_category(Request $request)
    {
        $category_id= $request->category_id;
        $category= DB::table('category')
            ->where('category_id',$category_id)
            ->get();

        if(!empty($category)){
            return response()->json([
                'status'=>true,
                'category' =>$category
            ]);
        }
        else{
            return response()->json([
                'status'=>false,
                'message'=>'Category Not Found !',
            ]);
        }
    }

    public function update_main_category(Request $request)
    {
        $category_id = $request->category_id;
        $update = DB::table('category')
            ->where('category_id', $category_id)
            ->update
            ([
                'category_name' => $request->category_name,
                'category_image' => $request->category_image,
                'description' => $request->description,
                'home'=> $request->home,
                'category_status'=>$request->status
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Main Categories Update successfully'
            ]);
    }





    public function product_colours()
    {
        $colours= DB::table('colours')->get();
        return response()->json([
         'status'=>true,
         'colurs'=>$colours
        ]);
    }

    public function product_sizes()
    {
        $sizes= DB::table('sizes')->get();
        return response()->json([
         'status'=>true,
         'sizes'=>$sizes
        ]);
    }



    public function get_influincer_list()
    {
        $roles = ['influencer', 'supplier-influencer', 'supplier-seller'];

        $users = DB::table('users')
            ->select('name','user_id','user_image','email','role')
            ->whereIn('role', $roles)
            ->orderBy('user_id', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'users' => $users,
        ]);
    }

    public function get_Customers_Spend_()
    {
        $threeMonthsAgo = Carbon::now()->subMonths(3);
        $customers = DB::table('customers')
            ->leftJoin('orders', 'customers.customer_id', '=', 'orders.customer_id')
            ->select('customers.customer_id','customers.first_name','customers.last_name','countries.country_name', DB::raw('SUM(orders.amount) as total_spend'))
            ->leftjoin('countries', 'customers.country', '=', 'countries.country_id')
            ->where('customers.inserted_date', '>=', $threeMonthsAgo)
            // ->groupBy('customers.customer_id')
            ->get();

        if ($customers->isEmpty()) {
            return response()->json([
                'customers' => [],
                'message' => 'No customers found in the last 3 months.',
                'success' => false
            ]);
        }

        $response = [
            'customers' => $customers,
            'message' => 'Last 3 months customers with total spend',
            'success' => true
        ];

        return response()->json($response);
    }

    public function get_Customers_Spend()
    {
        $threeMonthsAgo = Carbon::now()->subMonths(3);

        $customers = DB::table('customers')
            ->leftJoin('orders', 'customers.customer_id', '=', 'orders.customer_id')
            ->leftJoin('countries', 'customers.country', '=', 'countries.country_id')
            ->select(
                'customers.customer_id',
                'customers.unique_id',
                'customers.first_name',
                'customers.image',
                'customers.status',
                'customers.last_name',
                'countries.country_name',
                DB::raw('SUM(orders.amount) as total_spend')
            )
            ->where('customers.inserted_date', '>=', $threeMonthsAgo)
            ->groupBy('customers.customer_id', 'customers.first_name', 'customers.last_name', 'countries.country_name','customers.image','customers.status','customers.unique_id')
            ->orderBy('customers.customer_id', 'desc')

            ->get();

        if ($customers->isEmpty()) {
            return response()->json([
                'customers' => [],
                'message' => 'No customers found in the last 3 months.',
                'status' => false
            ]);
        }


        $response = [
            'customers' => $customers,
            'message' => 'Last 3 months customers with total spend',
            'status' => true
        ];

        return response()->json($response);
    }

    public function getRecentProductReviews()
    {
        $threeMonthsAgo = Carbon::now()->subMonths(3);

        $reviews = DB::table('product_reviews')
            ->leftJoin('products', 'product_reviews.product_id', '=', 'products.product_id')
            ->leftJoin('customers', 'product_reviews.customer_id', '=', 'customers.customer_id')
            ->select(
                'product_reviews.*',
                'products.product_name',
                'products.product_img',
                'products.description',
                'customers.first_name as customer_first_name',
                'customers.last_name as customer_last_name',
                'customers.email as customer_email',
                'customers.image as customer_image',
                'product_reviews.status as review_status'
            )
            ->where('product_reviews.inserted_date', '>=', $threeMonthsAgo)
            ->orderBy('product_reviews.product_id', 'desc')
            ->get();

        if ($reviews->isEmpty()) {
            return response()->json([
                'product' => [],
                'customer' => [],
                'message' => 'No product reviews found in the last 3 months.',
                'success' => false
            ]);
        }

        $products = [];

        foreach ($reviews as $review) {
            $products[] = [
                'product_id' => $review->product_id,
                'product_name' => $review->product_name,
                'product_img' => $review->product_img,
                'product_description' => $review->description,
                'product_review_id' => $review->product_review_id,
                'review_comment' => $review->comment,
                'review_rating' => $review->rating,
                'customer_id' => $review->customer_id,
                'first_name' => $review->customer_first_name,
                'last_name' => $review->customer_last_name,
                'email' => $review->customer_email,
                'customer_image'=> $review->customer_image,
                'review_status'=> $review->review_status
            ];


        }

        $response = [
            'status' => true,
            'message' => 'Last 3 months product reviews with associated product and customer details',
            'reviews' => $products,
        ];

        return response()->json($response);
    }

    public function getoneProductDiscount(Request $request)
    {
        $discount_per = $request->discount_per;

             $products = DB::table('products')
            ->where('products.discount', $discount_per)
            ->where('products.stock_status', 'Live')
            ->where('products.status', 'Active')
            ->leftJoin('users', 'products.user_id', '=', 'users.user_id')
            ->leftJoin('category', 'products.category_id', '=', 'category.category_id')
            ->select(
                'category.category_name',
                'category.category_id',
                'category.category_image',
                'products.total_amount',
                'products.price',
                'products.status',
                'products.stock_status',
                'products.discount'
            )
            ->get();

        if ($products->isEmpty()) {
            return response()->json([
                'status' => 'false',
                'message' => 'No products found with the specified discount.'
            ]);
        }

        return response()->json([
            'status' => 'true',
            'discount_products' => $products
        ]);
    }

    public function get_all_offers_list()
    {
        $offers = DB::table('products')
            ->select('products.discount')
            ->groupBy('products.discount')
            ->get();

        if ($offers->isEmpty()) {
            return response()->json([
                'message' => 'No Offers On Products Found.',
                'success' => false
            ]);
        } else {
            $response = [
                'offers' => $offers,
                'message' => 'Offers Found',
                'success' => true
            ];
        }
        return response()->json($response);
    }










// ======================================================================================= //









    public function update_user_details_(Request $request)
    {
        $data = $request->json()->all();
        $managerInfo = $data['manager_info'] ?? [];
        $bankDetails = $data['bank_details'] ?? [];

        $managerData = [
            'name' => $managerInfo['firstName'] . ' ' . $managerInfo['lastName'] ?? '',
            'country' => $managerInfo['country'] ?? '',
            'state' => $managerInfo['state'] ?? '',
            'city' => $managerInfo['city'] ?? '',
            'address' => $managerInfo['address'] ?? '',
            'locality' => $managerInfo['locality'] ?? '',
            'pin_code' => $managerInfo['pincode'] ?? '',
            'mobile' => $managerInfo['mobile'] ?? '',
            'email' => $managerInfo['email'] ?? '',
        ];

        $updatedRows = DB::table('users')
            ->where('user_id', $request->user_id)
            ->update($managerData);

        $bank_details_arr = [
            'account_holder_name' => $bankDetails['accountHoldername'] ?? '',
            'account_number' => $bankDetails['bankAccountNo'] ?? '',
            'ifsc_code' => $bankDetails['IFSCcode'] ?? ''
        ];

        $updatedBankRows = DB::table('bank_details')
            ->where('user_id', $request->user_id)
            ->update($bank_details_arr);

        if ($updatedRows || $updatedBankRows) {
            return response()->json([
                'status' => true,
                'message' => 'User Profile and Bank Details Updated Successfully'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Failed to Update User Details.'
            ]);
        }
    }

// ===================================================================================== //





// ====================================================================== //





    public function get_product_data_(Request $request)
    {
        $countryCode=$request->countryCode;
        $customer_id= $request->customer_id;
        $threeMonthsAgo = Carbon::now()->subMonths(3);

        $count = $this->viewLimit('get_product_data', $customer_id);
        // dd($count);
        $this->incrementApiCallCount('get_product_data', $customer_id);
        $limit = 10;
        $offset = ($count - 1) * 10;

        $productData = DB::table('products')
            ->leftJoin('category', 'products.category_id', '=', 'category.category_id')
            ->leftJoin('sub_category_1', 'sub_category_1.sub_category_1_id', '=', 'products.sub_category_1_id')
            ->leftJoin('sub_category_2', 'sub_category_2.sub_category_2_id', '=', 'products.sub_category_2_id')
            ->leftJoin('sub_category_3', 'sub_category_3.sub_category_3_id', '=', 'products.sub_category_3_id')
            ->where('products.inserted_date', '>=', $threeMonthsAgo)
            ->where('products.status', 'Active')
            // ->where('products.stock_status', 'Live')
            ->orderBy('products.product_id', 'desc')
            ->select(
                'products.*',
                'category.category_name',
                'sub_category_1.sub_category_1_name',
                'sub_category_2.sub_category_2_name',
                'sub_category_3.sub_category_3_name'
            )
            ->skip($offset)
            ->take($limit)
            ->get();

        $MainproductsAllData = [];

        $currencyInfo = $this->getCurrencyInfo($countryCode);


        foreach ($productData as $product) {
            $this->updateProductApiCallCount($product->product_id);

            $productReviews = DB::table('product_reviews')
                ->where('product_id', $product->product_id)
                ->where('status', 'Active')
                ->select(
                    DB::raw('AVG(product_reviews.rating) as average_rating'),
                    DB::raw('COUNT(product_reviews.product_review_id) as review_count')
                )
                ->groupBy('product_id')
                ->orderBy('average_rating', 'desc')
                ->orderBy('review_count', 'desc')
                ->first();

            // dd($productReviews);
            $review_count = 0;

            if ($productReviews) {
                $formatted_Productrating = number_format($productReviews->average_rating, 1);
                $review_count = $productReviews->review_count;
            } else {
                $formatted_Productrating = "0";
            }

            if ($currencyInfo) {
                $rate = $currencyInfo['rate'] ?? '1';
                $currencysymbol = $currencyInfo['symbol'] ?? '$';
            }




            $productVariants = DB::table('product_varient')
                ->where('product_id', $product->product_id)
                ->select('product_varient_id', 'colour', 'total_amount', 'quantity')
                ->get();

            $variantImages = DB::table('product_varient_images')
                ->whereIn('product_varient_id', $productVariants->pluck('product_varient_id'))
                ->select('product_varient_id', 'images')
                ->take(2)
                ->get();

            $sizes = DB::table('product_varient_size')
                ->whereIn('product_varient_id', $productVariants->pluck('product_varient_id'))
                ->leftJoin('sizes', 'product_varient_size.size_id', '=', 'sizes.size_id')
                ->select('sizes.size_name')
                ->distinct()
                ->get()
                ->pluck('size_name')
                ->toArray();

            foreach ($productVariants as $variant) {
                if (!isset($MainproductsAllData[$product->product_id])) {
                    $MainproductsAllData[$product->product_id] = [
                        'product_id' => $product->product_id,
                        'product_varient_id' => $variant->product_varient_id,
                        'product_name' => $product->product_name,
                        'sub_text' => $product->sub_text,
                        'product_img' => $variantImages,
                        'category_name' => $product->category_name,
                        'discount' => $product->discount,
                        'colors' => [],
                        'sizes' => $sizes,
                        'reviews_avg' => $formatted_Productrating,
                        'review_count' => $review_count,
                        'symbol' => $currencysymbol

                    ];


                }

                $MainproductsAllData[$product->product_id]['colors'][] = $variant->colour;

                if (!isset($MainproductsAllData[$product->product_id]['price'])) {
                    if (isset($currencyInfo)) {
                        $MainproductsAllData[$product->product_id]['price'] = number_format(($variant->total_amount) * $rate, 2);
                    } else {
                        $MainproductsAllData[$product->product_id]['price'] = number_format($variant->total_amount, 2);
                    }

                }
            }
        }

        return response()->json([
            'status' => true,
            'MainproductsAllData' => array_values($MainproductsAllData)
        ]);
    }

    public function get_product_data__(Request $request)
    {
        $countryCode = $request->countryCode;
        $customer_id = $request->customer_id;
        $threeMonthsAgo = Carbon::now()->subMonths(3);

        $count = $this->viewLimit('get_product_data', $customer_id);
        $this->incrementApiCallCount('get_product_data', $customer_id);

        $limit = 10;
        $offset = ($count - 1) * $limit;

        // Get initial product data
        $productData = DB::table('products')
            ->leftJoin('category', 'products.category_id', '=', 'category.category_id')
            ->leftJoin('sub_category_1', 'sub_category_1.sub_category_1_id', '=', 'products.sub_category_1_id')
            ->leftJoin('sub_category_2', 'sub_category_2.sub_category_2_id', '=', 'products.sub_category_2_id')
            ->leftJoin('sub_category_3', 'sub_category_3.sub_category_3_id', '=', 'products.sub_category_3_id')
            ->where('products.inserted_date', '>=', $threeMonthsAgo)
            ->where('products.status', 'Active')
            ->orderBy('products.product_id', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        // If less than 10 products are found, fetch the remaining from the start
        $productCount = $productData->count();
        if ($productCount < $limit) {
            $additionalProducts = DB::table('products')
                ->leftJoin('category', 'products.category_id', '=', 'category.category_id')
                ->leftJoin('sub_category_1', 'sub_category_1.sub_category_1_id', '=', 'products.sub_category_1_id')
                ->leftJoin('sub_category_2', 'sub_category_2.sub_category_2_id', '=', 'products.sub_category_2_id')
                ->leftJoin('sub_category_3', 'sub_category_3.sub_category_3_id', '=', 'products.sub_category_3_id')
                ->where('products.inserted_date', '>=', $threeMonthsAgo)
                ->where('products.status', 'Active')
                ->orderBy('products.product_id', 'desc')
                ->take($limit - $productCount)  // Fetch only the remaining number of products
                ->get();

            // Append additional products to the original product data
            $productData = $productData->merge($additionalProducts);
        }

        $MainproductsAllData = [];

        $currencyInfo = $this->getCurrencyInfo($countryCode);

        foreach ($productData as $product) {
            $this->updateProductApiCallCount($product->product_id);

            // Get product reviews
            $productReviews = DB::table('product_reviews')
                ->where('product_id', $product->product_id)
                ->where('status', 'Active')
                ->select(
                    DB::raw('AVG(product_reviews.rating) as average_rating'),
                    DB::raw('COUNT(product_reviews.product_review_id) as review_count')
                )
                ->groupBy('product_id')
                ->orderBy('average_rating', 'desc')
                ->orderBy('review_count', 'desc')
                ->first();

            $review_count = 0;
            $formatted_Productrating = $productReviews ? number_format($productReviews->average_rating, 1) : "0";
            if ($productReviews) {
                $review_count = $productReviews->review_count;
            }

            // Get product variants
            $productVariants = DB::table('product_varient')
                ->where('product_id', $product->product_id)
                ->select('product_varient_id', 'colour', 'total_amount', 'quantity')
                ->get();

            $variantImages = DB::table('product_varient_images')
                ->whereIn('product_varient_id', $productVariants->pluck('product_varient_id'))
                ->select('product_varient_id', 'images')
                ->take(2)
                ->get();

            $sizes = DB::table('product_varient_size')
                ->whereIn('product_varient_id', $productVariants->pluck('product_varient_id'))
                ->leftJoin('sizes', 'product_varient_size.size_id', '=', 'sizes.size_id')
                ->select('sizes.size_name')
                ->distinct()
                ->get()
                ->pluck('size_name')
                ->toArray();

            foreach ($productVariants as $variant) {
                if (!isset($MainproductsAllData[$product->product_id])) {
                    $MainproductsAllData[$product->product_id] = [
                        'product_id' => $product->product_id,
                        'product_varient_id' => $variant->product_varient_id,
                        'product_name' => $product->product_name,
                        'sub_text' => $product->sub_text,
                        'product_img' => $variantImages,
                        'category_name' => $product->category_name,
                        'discount' => $product->discount,
                        'colors' => [],
                        'sizes' => $sizes,
                        'reviews_avg' => $formatted_Productrating,
                        'review_count' => $review_count,
                        'symbol' => $currencyInfo['symbol'] ?? '$'
                    ];
                }

                $MainproductsAllData[$product->product_id]['colors'][] = $variant->colour;

                if (!isset($MainproductsAllData[$product->product_id]['price'])) {
                    $MainproductsAllData[$product->product_id]['price'] = isset($currencyInfo)
                        ? number_format(($variant->total_amount) * $currencyInfo['rate'], 2)
                        : number_format($variant->total_amount, 2);
                }
            }
        }

        return response()->json([
            'status' => true,
            'MainproductsAllData' => array_values($MainproductsAllData)
        ]);
    }







    public function get_products_list(Request $request)
    {
        $user_id = $request->user_id;
        $role = $request->role;

        $products_query = DB::table('products')->orderBy('product_id', 'desc');

        if ($role == 'manager') {
            $getManagerStateId = DB::table('state_to_manager')
                ->where('user_id', $user_id)
                ->pluck('state_subdivision_id')
                ->toArray();

            $getUsersUnderManager = DB::table('users')
                ->whereIn('state', $getManagerStateId)
                ->pluck('user_id')
                ->toArray();

            $products_query->whereIn('user_id', $getUsersUnderManager);
        } else if (in_array($role, ['supplier-seller', 'supplier', 'supplier-influencer'])) {
            $products_query->where('user_id', $user_id);
        } else if ($role == 'supervisor') {
            if ($user_id == 2) {
                $country = DB::table('countries')
                    ->where('zone', '1')
                    ->pluck('country_id')
                    ->toArray();
                $underSupervisorUser = DB::table('users')
                    ->whereIn('country', $country)
                    ->pluck('user_id')
                    ->toArray();
                $products_query->whereIn('user_id', $underSupervisorUser);
            } else if ($user_id == 3) {
                $country = DB::table('countries')
                    ->where('zone', '2')
                    ->pluck('country_id')
                    ->toArray();
                $underSupervisorUser = DB::table('users')
                    ->whereIn('country', $country)
                    ->pluck('user_id')
                    ->toArray();
                $products_query->whereIn('user_id', $underSupervisorUser);
            } else if ($user_id == 4) {
                $country = DB::table('countries')
                    ->where('zone', '3')
                    ->pluck('country_id')
                    ->toArray();
                $underSupervisorUser = DB::table('users')
                    ->whereIn('country', $country)
                    ->pluck('user_id')
                    ->toArray();
                $products_query->whereIn('user_id', $underSupervisorUser);
            }
        }

        $products_data = $products_query->get();
        $finalData = [];

        foreach($products_data as $product) {
            $currentDateTime = Carbon::now('Asia/Kolkata');
            $todayDate = $currentDateTime->toDateString();

            $productDate = Carbon::parse($product->inserted_date);

            $days = DB::table('ads')
                ->where('admin_subscription_id', '1')
                ->first();

            $subscriptionPeriod = (int) $days->subscription_period;

            $afterDays = $productDate->copy()->addDays($subscriptionPeriod);

            //     dd($afterDays->toDateString()
            // );
            $validDate = $afterDays->toDateString();

            $views=DB::table('product_viewcount')
                    ->where('product_id',$product->product_id)
                    ->where('date',$todayDate)
                    ->select('count')
                    ->first();
                    // dd($views);



            // dd($todayDate);

            $boost = $productDate < $validDate ? true : false;

            $category = DB::table('category')
                ->leftJoin('products', 'category.category_id', '=', 'products.category_id')
                ->where('products.product_id', $product->product_id)
                ->select('products.*', 'category.category_name')
                ->first();

            $varientData = DB::table('product_varient')
                ->where('product_id', $product->product_id)
                ->first();

            // Check if $varientData is null before accessing its properties
            if ($varientData) {
                $ar = [
                    'category_id' => $category->category_id ?? null,
                    'category_name' => $category->category_name ?? null,
                    'price' => $varientData->price,
                    'total_amount' => $varientData->total_amount,
                    'quantity' => $varientData->quantity,
                    'product_id' => $product->product_id,
                    'product_name' => $product->product_name,
                    'stock_status' => $product->stock_status,
                    'product_img' => $product->product_img,
                    'boost' => $boost,
                    'status' => $product->status,
                    'views' => (string) ($views->count ?? 0),
                    'todayDate' => $todayDate,
                ];

                $finalData[] = $ar;
            }
        }

        if (!empty($finalData)) {
            return response()->json([
                'status' => true,
                'message' => 'Products Found Successfully',
                'products' => $finalData,
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'No Products Added Yet',
            ]);
        }
    }



    public function get_one_product_(Request $request)
    {
        $product_id = $request->product_id;

        $products = DB::table('products')
            ->where('products.product_id', $product_id)
            ->select('product_name', 'sub_text', 'description', 'product_id', 'discount','category_id','sub_category_1_id','sub_category_2_id','sub_category_3_id')
            ->first();

        $varientData = DB::table('product_varient')
            ->where('product_id', $product_id)
            ->select('product_varient.colour', 'product_varient.price', 'product_varient.total_amount', 'product_varient.product_varient_id')
            ->get();

        $finalVariants = null;

        foreach ($varientData as $variant) {
            $images = DB::table('product_varient_images')
                ->where('product_varient_id', $variant->product_varient_id)
                ->pluck('images');

            $variant->images = $images;

            $sizeStock = DB::table('product_varient_size')
                ->where('product_varient_id', $variant->product_varient_id)
                ->leftJoin('sizes', 'product_varient_size.size_id', '=', 'sizes.size_id')
                ->select('sizes.size_name', 'product_varient_size.stock', 'product_varient_size.size_id')
                ->get();

            $variant->sizeStock = $sizeStock;

            $finalVariants[] = $variant;
        }

        $finalVariants = collect($finalVariants)->unique('product_varient_id')->values();

        $reviews = DB::table('product_reviews')
            ->leftJoin('customers', 'product_reviews.customer_id', '=', 'customers.customer_id')
            ->leftJoin('product_review_content', 'product_reviews.product_review_id', '=', 'product_review_content.product_review_id')
            ->where('product_reviews.product_id', $product_id)
            ->select(
                'product_reviews.*',
                // 'product_reviews.comment',
                'customers.first_name',
                'customers.last_name',
                'customers.image',
                'customers.customer_id',
                'product_reviews.product_review_id'
            )
            ->get();

        $averageRating = DB::table('product_reviews')
            ->where('product_id', $product_id)
            ->avg('rating');

        $formattedAverageRating = number_format($averageRating, 1);

        $reviewCount = DB::table('product_reviews')
            ->where('product_id', $product_id)
            ->count('product_review_id');

        $reviewData = [];
        foreach ($reviews as $review) {
            // dd($review);
            if (!isset($reviewData[$review->product_review_id])) {

                $reviewImages = DB::table('product_review_content')
                    ->where('product_review_id', $review->product_review_id)
                    ->get();
                if (isset($review->customer_id)) {
                    $ar = [
                        'rating' => $review->rating,
                        'comment' => $review->comment,
                        'review_time' => $review->inserted_time,
                        'review_date' => $review->inserted_date,
                        'firstName' => $review->first_name,
                        'lastName' => $review->last_name,
                        'customer_image' => $review->image,
                        'customerId' => $review->customer_id,
                        'images' => $reviewImages,
                    ];
                    $reviewData[$review->product_review_id] = $ar;
                }


            }
        }

        $reviewData = array_values($reviewData);
        // $reviewData[] = [
        //     'reviews_avg' => $formattedAverageRating,
        //     'reviewCount' => $reviewCount,
        // ];


        // Return or process $finalResult as needed


        $productAbout = DB::table('product_about')
            ->where('product_id', $product_id)
            ->select('heading', 'para', 'product_about_id')
            ->get();


        $resultArray = null;
        foreach ($productAbout as $item) {
            $resultArray[] = [
                'product_about_id' => $item->product_about_id,
                'field' => $item->heading,
                'value' => $item->para,
            ];
        }



        return response()->json([
            'status' => true,
            'products' => $products,
            'variants' => $finalVariants,
            'reviews' => $reviewData,
            'reviews_avg' => $formattedAverageRating,
            'reviewCount' => $reviewCount,
            'about' => $resultArray,
        ]);
    }

    public function get_customer_reviews(Request $request)
    {

        $reviews = DB::table('product_reviews')
            ->join('products', 'product_reviews.product_id', '=', 'products.product_id')
            ->join('customers', 'product_reviews.customer_id', '=', 'customers.customer_id')
            ->select('product_reviews.*', 'products.product_name', 'customers.first_name','customers.last_name')
            ->where('product_reviews.customer_id', $request->customer_id)
            ->orderBy('product_reviews.product_review_id', 'desc')
            ->get();
        if ($reviews->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No Reviews Found.'
            ]);
        }else {

            return response()->json([
                'status' => true,
                'reviews' => $reviews,
                'message' => 'Reviews Found.'
            ]);
        }

    }



    public function delete_customer_poduct_review_(Request $request){
        $customer_id = $request->customer_id;
        $product_id = $request->product_id;


        if(!$customer_id && !$product_id){
            return response()->json([
                'status' => true,
                'massage' => 'Customers ID and Product ID Required',
            ]);
        }
      $deleted=  DB::table('product_reviews')
        ->where('customer_id',$customer_id)
        ->where('product_id',$product_id)
        ->delete();

        if ($deleted) {
            return response()->json([
                'status' => true,
                'massage' => 'Review Deleted Successfully',
            ]);
        } else {
            return response()->json([
                'status' => true,
                'massage' => 'Review Data Not Found',
            ]);
        }


    }



    public function get_cart_(Request $request)
    {
        $customerId = $request->customer_id;

        if (!$customerId) {
            return response()->json([
                'status' => false,
                'message' => 'Customer Id not found.'
            ], 404);
        }

        $customerCartProductData = DB::table('product_cart')
            ->where('customer_id', $customerId)
            ->select(
                'product_cart.*'
            )
            ->get();

        if ($customerCartProductData->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => "Please add products to your cart."
            ]);
        }

        $result = [];
        $totalAmount = 0;

        foreach ($customerCartProductData as $cartData) {
            // dd($cartData);

            $productData = DB::table('products')
                            ->where('product_id',$cartData->product_id)
                            ->select('product_name', 'description','category_id')
                            ->first();
            $ar = [];

            $categorydata = DB::table('category')
                            ->where('category_id',$productData->category_id)
                            // ->select('category_name', 'description')
                            ->first();
            // dd($categorydata);

            $ar['product_cart_id'] = $cartData->product_cart_id;
            $ar['product_name'] = $productData->product_name;
            $ar['product_id'] = $cartData->product_id;
            $ar['product_varient_id'] = $cartData->product_varient_id;
            $ar['added_quantity'] = $cartData->quantity;
            $ar['category_name'] = $categorydata->category_name;
                $varientData = DB::table('product_varient')
                    ->where('product_varient_id', $cartData->product_varient_id)
                    ->first();
                // dd($varientData);

                $varientImage = DB::table('product_varient_images')
                    ->where('product_varient_id', $cartData->product_varient_id)
                    ->first();
                // dd($varientImage);

                $sizeData = DB::table('product_varient_size')
                    ->leftJoin('sizes', 'product_varient_size.size_id', '=', 'sizes.size_id')
                    // ->where('product_varient_id', $cartData->product_varient_id)
                    ->where('product_varient_size.size_id', $cartData->size_id)
                    ->select('sizes.size_name', 'product_varient_size.stock', 'product_varient_size.size_id')
                    ->first();
                // dd($sizeData);

                $ar['images'] = $varientImage->images ?? null;
                $ar['product_size_name'] = $sizeData->size_name ?? null;
                $ar['colour'] = $varientData->colour ?? null;
                $ar['price'] = $varientData->price ?? 0;
                $ar['product_quantity_left'] = $sizeData->stock ?? 0;
                $ar['size_id'] = $sizeData->size_id ?? null;
                // $ar['stock'] = $sizeData->quantity ?? null;
                $ar['product_total'] = $cartData->total ?? null;

            $totalAmount = $totalAmount + $cartData->total;
            $result[] = $ar;
        }

        return response()->json([
            'status' => true,
            'cartAllData' => $result,
            'cartCount' => count($result),
            'total_amount' => $totalAmount,
        ]);
    }



    public function update_product_(Request $request)
    {

        $currentDateTime = now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();


        $productdata = $request->product_info;
        $productVariants = $request->varient_data;

        // $lastProductId = DB::table('products')->max('product_id');
        // $UserId = DB::table('users')
        //     ->select('unique_id')
        //     ->where('user_id', $productdata['user_id'])
        //     ->first();

        // if ($UserId && $lastProductId) {
        //     $userInt = preg_replace('/\D/', '', $UserId->unique_id);
        //     $uniqueId = 'A' . $userInt . 'P' . $lastProductId;
        // } else {
        //     $uniqueId = 'A000000P' . ($lastProductId ? $lastProductId : 1);
        // }

        $update_data = [
            'product_name' => $productdata['product_name'],
            'sub_text' => $productdata['sub_text'],
            'description' => $productdata['description'],
            'category_id' => $productdata['category_id'],
            'sub_category_1_id' => $productdata['sub_category_1_id'],
            'sub_category_2_id' => $productdata['sub_category_2_id'],
            'sub_category_3_id' => $productdata['sub_category_3_id'],
            'manage_by' => $productdata['manage_by'],

            'discount' => $productdata['discount'],
            'influincer_percentage' => $productdata['influincer_percentage'],
            'tax_percentage' => $productdata['tax_percentage'],
            'coupen_code' => $productdata['coupen_code'],
            'coupen_code_discount' => $productdata['coupen_code_discount'],

            'brand_name' => $productdata['brand_name'],
            'stock_status' => $productdata['stock_status'],

            'net_weight' => $productdata['net_weight'],
            'user_id' => $productdata['user_id'],
            // 'unique_id' => $uniqueId,

        ];

        $updated = DB::table('products')->where('product_id', $productdata['product_id'])->update($update_data);


        if ($updated) {

            if (!empty($productVariants)) {

                DB::table('products')->where('product_id', $productdata['product_id'])
                    ->update(['product_img' => $productVariants[0]['var_images'][0]['url']]);


                foreach ($productVariants as $variantData) {

                    $totalQuantity = 0;

                    foreach ($variantData['sizes'] as $size) {
                        $totalQuantity += $size['quantity'];
                    }
                    if ($variantData['product_varient_id']) {

                        DB::table('product_varient')->where('product_varient_id', $variantData['product_varient_id'])->where('product_id',$productdata['product_id'])->update([
                            'product_id' => $productdata['product_id'],
                            'colour' => $variantData['color'],
                            'price' => floor($variantData['price'] - ($variantData['price'] * $productdata['discount'] / 100)),

                            'total_amount' => $variantData['price'],
                            'quantity' => $totalQuantity,
                            'inserted_date' => $insertDate,
                            'inserted_time' => $insertTime,
                        ]);

                        $lastInsertedId = $variantData['product_varient_id'];
                    } else {
                        $lastInsertedId = DB::table('product_varient')->insertGetId([
                            'product_id' => $productdata['product_id'],
                            'colour' => $variantData['color'],
                            'price' => floor($variantData['price'] - ($variantData['price'] * $productdata['discount'] / 100)),

                            'total_amount' => $variantData['price'],
                            'quantity' => $totalQuantity,
                            'inserted_date' => $insertDate,
                            'inserted_time' => $insertTime,
                        ]);

                    }

                    foreach ($variantData['var_images'] as $image) {

                        if ($image['product_varient_image_id']) {
                            DB::table('product_varient_images')->where('product_varient_image_id', $image['product_varient_image_id'])->update([
                                'images' => $image['url'],
                                'product_varient_id' => $lastInsertedId,
                                'inserted_date' => $insertDate,
                                'inserted_time' => $insertTime,
                            ]);
                        } else {
                            DB::table('product_varient_images')->insert([
                                'images' => $image['url'],
                                'product_varient_id' => $lastInsertedId,
                                'inserted_date' => $insertDate,
                                'inserted_time' => $insertTime,
                            ]);
                        }
                    }
                    foreach ($variantData['sizes'] as $sizes) {

                        if ($sizes['product_varient_size_id']) {
                            DB::table('product_varient_size')->where('product_varient_size_id', $sizes['product_varient_size_id'])->update([
                                'stock' => $sizes['quantity'],
                                'size_id' => $sizes['size_id'],
                                'product_varient_id' => $lastInsertedId,
                                'inserted_date' => $insertDate,
                                'inserted_time' => $insertTime,
                            ]);
                        } else {
                            DB::table('product_varient_size')->insert([
                                'stock' => $sizes['quantity'],
                                'size_id' => $sizes['size_id'],
                                'product_varient_id' => $lastInsertedId,
                                'inserted_date' => $insertDate,
                                'inserted_time' => $insertTime,
                            ]);
                        }
                    }
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Product Status Updated Successfully.',
                'data' => $updated
            ]);

        } else if (!$updated) {

            if (!empty($productVariants)) {


                foreach ($productVariants as $variantData) {

                    $totalQuantity = 0;

                    foreach ($variantData['sizes'] as $size) {
                        $totalQuantity += $size['quantity'];
                    }

                    if ($variantData['product_varient_id']) {

                        DB::table('product_varient')->where('product_varient_id', $variantData['product_varient_id'])->where('product_id',$productdata['product_id'])->update([
                            'product_id' => $productdata['product_id'],
                            'colour' => $variantData['color'],
                            'price' => floor($variantData['price'] - ($variantData['price'] * $productdata['discount'] / 100)),

                            'total_amount' => $variantData['price'],
                            'quantity' => $totalQuantity,
                            'inserted_date' => $insertDate,
                            'inserted_time' => $insertTime,
                        ]);

                        $lastInsertedId = $variantData['product_varient_id'];
                    } else {
                        $lastInsertedId = DB::table('product_varient')->insertGetId([
                            'product_id' => $productdata['product_id'],
                            'colour' => $variantData['color'],
                            'price' => floor($variantData['price'] - ($variantData['price'] * $productdata['discount'] / 100)),

                            'total_amount' => $variantData['price'],
                            'quantity' => $totalQuantity,
                            'inserted_date' => $insertDate,
                            'inserted_time' => $insertTime,
                        ]);

                    }

                    foreach ($variantData['var_images'] as $image) {

                        if ($image['product_varient_image_id']) {
                            DB::table('product_varient_images')->where('product_varient_image_id', $image['product_varient_image_id'])->update([
                                'images' => $image['url'],
                                'product_varient_id' => $lastInsertedId,
                                'inserted_date' => $insertDate,
                                'inserted_time' => $insertTime,
                            ]);
                        } else {
                            DB::table('product_varient_images')->insert([
                                'images' => $image['url'],
                                'product_varient_id' => $lastInsertedId,
                                'inserted_date' => $insertDate,
                                'inserted_time' => $insertTime,
                            ]);
                        }
                    }
                    foreach ($variantData['sizes'] as $sizes) {

                        if ($sizes['product_varient_size_id']) {
                            DB::table('product_varient_size')->where('product_varient_size_id', $sizes['product_varient_size_id'])->update([
                                'stock' => $sizes['quantity'],
                                'size_id' => $sizes['size_id'],
                                'product_varient_id' => $lastInsertedId,
                                'inserted_date' => $insertDate,
                                'inserted_time' => $insertTime,
                            ]);
                        } else {
                            DB::table('product_varient_size')->insert([
                                'stock' => $sizes['quantity'],
                                'size_id' => $sizes['size_id'],
                                'product_varient_id' => $lastInsertedId,
                                'inserted_date' => $insertDate,
                                'inserted_time' => $insertTime,
                            ]);
                        }
                    }

                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Product Status Updated Successfully.',
                'data' => $updated
            ]);

        } else {
            return response()->json([
                'status' => false,
                'message' => 'Product Id Required.',
            ]);
        }

    }


// =================================================================================  //





// =========================================================================================== //







    public function update_user_details(Request $request)
    {
        $data = $request->json()->all();
        $managerInfo = $data['manager_info'] ?? [];
        $bankDetails = $data['bank_details'] ?? [];
        $workingArea = $data['working_area'] ?? [];



        foreach($workingArea as $area){
            if(is_null($area['state_to_manager_id'])){
              $updatedArea=  DB::table('state_to_manager')

                ->insert([
                    'state_subdivision_id'=>$area['state'],
                    'user_id' =>$request->user_id
                ]);
            }else{
              $updatedArea=  DB::table('state_to_manager')
                ->where('state_to_manager_id',$area['state_to_manager_id'])
                ->update([
                    'state_subdivision_id'=>$area['state'],
                ]);
            }
        }


        $managerData = [
            'name' => $managerInfo['firstName'] . ' ' . $managerInfo['lastName'] ?? '',
            'country' => $managerInfo['country'] ?? '',
            'state' => $managerInfo['state'] ?? '',
            'city' => $managerInfo['city'] ?? '',
            'address' => $managerInfo['address'] ?? '',
            'locality' => $managerInfo['locality'] ?? '',
            'pin_code' => $managerInfo['pincode'] ?? '',
            'mobile' => $managerInfo['mobile'] ?? '',
            'email' => $managerInfo['email'] ?? '',
        ];

        $updatedRows = DB::table('users')
            ->where('user_id', $request->user_id)
            ->update($managerData);

        $bank_details_arr = [
            'account_holder_name' => $bankDetails['accountHoldername'] ?? '',
            'account_number' => $bankDetails['bankAccountNo'] ?? '',
            'ifsc_code' => $bankDetails['IFSCcode'] ?? ''
        ];

        $updatedBankRows = DB::table('bank_details')
            ->where('user_id', $request->user_id)
            ->update($bank_details_arr);

        if ($updatedRows || $updatedBankRows  || $updatedArea) {
            return response()->json([
                'status' => true,
                'message' => 'User Profile and Bank Details Updated Successfully'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Failed to Update User Details.'
            ]);
        }
    }







    public function customer_apply_coupon_(Request $request)
    {
        $customer_id = $request->customer_id;
        $coupon_code = $request->coupon_code;

        if(!$customer_id){
            return response()->json([
                'status'=>false,
                'message'=>'Customer Id Required!'
            ]);
        }

        $coupandata = DB::table('coupon')
            ->where('coupon_code', $coupon_code)
            ->first();

        if (!$coupandata) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid Coupon Code.',
            ]);
        }

        $CustomercartData = DB::table('product_cart')
                            ->where('customer_id',$customer_id)
                            ->get();


                               if($CustomercartData->isEmpty()){
            return response()->json([
                'status'=>false,
                'message'=>'Your Cart is Empty Please add product'
            ]);
        }



        $coupon_id = $coupandata->coupon_id;

        // $existingApplied = DB::table('customer_coupon')
        //     ->where('customer_id', $customer_id)
        //     ->where('coupon_id', $coupon_id)
        //     ->where('status', 'Applied')
        //     ->first();

        // if ($existingApplied) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Coupon already applied.',
        //     ]);
        // }

        $currentDate = now()->format('Y-m-d');
        $liveCoupons = DB::table('coupon')
            ->where('start_date', '<=', $currentDate)
            ->where('expire_date', '>=', $currentDate)
            ->pluck('coupon_id')
            ->toArray();

        if (!in_array($coupon_id, $liveCoupons)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or expired coupon.',
            ]);
        }

        $cartTotal = DB::table('product_cart')
            ->where('customer_id', $customer_id)
            ->sum('total');

        $couponDetails = DB::table('coupon')
            ->where('coupon_id', $coupon_id)
            ->first();

        $couponDiscount = $couponDetails->amount;


       $coupon_cart_min_amount = $couponDetails->cart_min_amount;



       if($cartTotal>=$coupon_cart_min_amount){
        $DiscountedcartTotal = (float)$cartTotal - (float)$couponDiscount;

        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        DB::table('customer_coupon')
            ->insert([
                'customer_id' => $customer_id,
                'coupon_code' => $couponDetails->coupon_code,
                'coupon_name' => $couponDetails->coupon_name,
                'amount' => $couponDetails->amount,
                'status' => 'Applied',
                'coupon_id' => $coupon_id,
                'inserted_date' => $insertDate,
                'inserted_time' => $insertTime,
            ]);

        return response()->json([
            'status' => true,
            'message' => 'Coupon Applied Successfully!',
            'total_amount' => $cartTotal,
            'discounted_total' => $DiscountedcartTotal,
            'coupon_amount' => $couponDiscount,
            'coupon_id' => $coupon_id,
            'coupon_code'=>$request->coupon_code
        ]);
       }
       else{
        return response()->json([
            'status'=>false,
            'message' => 'Cart amount must be at least ' . $couponDetails->cart_min_amount . ' to apply this coupon.',
        ]);
       }


    }





    public function get_customer_allorder_(Request $request)
    {
        $customerId = $request->customer_id;
        $customerorderData = DB::table('orders')
            ->where('orders.customer_id', $customerId)
            ->leftjoin('customers_delivery_address','orders.customer_delivery_address_id','=','customers_delivery_address.customers_delivery_address_id')
            ->leftjoin('countries','customers_delivery_address.country','=','countries.country_id')
            ->leftjoin('states','customers_delivery_address.state','=','states.state_subdivision_id')
            ->select('orders.amount','orders.order_unique_id','orders.order_status','orders.customer_id as customer_id','orders.payment_id','orders.tracking_id','orders.tracking_status','orders.inserted_date','orders.inserted_time','customers_delivery_address.*','countries.country_name','states.state_subdivision_name')
            ->orderBy('order_id', 'desc')
            ->get();

        if (!$customerId || !$customerorderData) {
            return response()->json([
                'status' => false,
                'message' => 'No Order Made By Customer.'
            ]);
        }

        $result = [];
        // dd($customerorderData);die;
        foreach ($customerorderData as $orders) {
            // dd($orders);die;
            $orderData=[
                'order_id' =>$orders->order_id,
                'order_unique_id' =>$orders->order_unique_id,
                'order_status' =>$orders->order_status,
                'customer_id' =>$customerId,
                'tracking_id' =>$orders->tracking_id,
                'tracking_status' =>$orders->tracking_status,
                'amount' =>$orders->amount,
                'inserted_date' =>$orders->inserted_date,
            ];

            $customerAdress = [
                // 'customer_delivery_address_id' =>$orders->customer_delivery_address_id,
                'country_name' =>$orders->country_name,
                'state_subdivision_name' =>$orders->state_subdivision_name,
                'city' =>$orders->city,
                'address_type' =>$orders->address_type,
                'address1' =>$orders->address1,
                'address2' =>$orders->address2,
                'locality' =>$orders->locality,
                'pincode' =>$orders->pincode,
            ];

            $productData = DB::table('ordered_products')
                ->leftjoin('products', 'ordered_products.product_id', '=', 'products.product_id')
                ->leftjoin('product_varient', 'ordered_products.product_varient_id', '=', 'product_varient.product_varient_id')
                ->leftjoin('sizes', 'ordered_products.size_id', '=', 'sizes.size_id')
                ->select(
                    'ordered_products.order_id',
                    'ordered_products.product_id',
                    'ordered_products.product_order_status',
                    'ordered_products.product_varient_id',
                    'ordered_products.quantity',
                    'ordered_products.price',
                    'ordered_products.total',
                    'ordered_products.tracking_id',
                    'products.product_name',
                    'products.sub_text',
                    'products.brand_name',
                    'products.net_weight',
                    'products.discount',
                    'product_varient.colour',
                    'sizes.size_name',

                )
                ->where('order_id', $orders->order_id)
                ->get();
            // dd($productData);

            $aa = [];
            foreach ($productData as $product) {
                // dd($product);
                $productImage = DB::table('product_varient_images')
                ->where('product_varient_id', $product->product_varient_id)
                ->first();
                // dd($productImage);
                $orderData['quantity'] =    $product->quantity;
                $productDetails= [
                'product_id'=>$product->product_id,
                'product_name'=>$product->product_name,
                'sub_text'=>$product->sub_text,
                'product_varient_id'=>$product->product_varient_id,
                'images'=>$productImage->images,
                // 'price'=>$product->price,
                // 'total'=>$product->total,
                'discount'=>$product->discount,
                'net_weight'=>$product->net_weight,
                'colour'=>$product->colour,
                'size_name'=>$product->size_name,
                ];


                // $aa = [
                //     'product_details' => $productDetails,
                //     'product_images' => $productImage
                // ];
            }

            $ar = [
                'order_details' => $orderData,
                'customerAdress' => $customerAdress,
                'product_details' => $productDetails,
            ];

            $result[] = $ar;
        }

        return response()->json([
            'status' => true,
            'OrderAllData' => $result,
        ]);

    }



    public function get_categories(Request $request)
    {

        $categoryProducts = DB::table('category')
            ->leftJoin('products', 'products.category_id', '=', 'category.category_id')
            ->leftJoin('product_varient', 'product_varient.product_id', '=', 'products.product_id')
            ->select(
                'category.category_id',
                'category.category_name',
                'category.category_image',
                'category.category_status as status',
                DB::raw('COUNT(products.product_id) as total_products'),
                DB::raw('SUM(product_varient.quantity) as total_quantity'),
                DB::raw('FLOOR(MIN(product_varient.price)) as min_amount'),
                DB::raw('FLOOR(MAX(product_varient.price)) as max_amount')
            )
            ->groupBy('category.category_id', 'category.category_name', 'category.category_status','category.category_image')
            ->get();

        if (!$categoryProducts) {
            return response()->json([
                'status' => false,
                'message' => 'Not Found'

            ]);
        } else {
            return response()->json([
                'status' => true,
                'category' => $categoryProducts

            ]);
        }
    }


    public function get_single_review(Request $request)
    {
        $product_review_id = $request->product_review_id;
        $review = DB::table('product_reviews')
            ->where('product_review_id', $product_review_id)
            ->first();

        $images = DB::table('product_review_content')
            ->where('product_review_id', $product_review_id)
            ->get();

        if (!$review) {
            return response()->json([
                'status' => false,
                'message' => 'Review not found.'
            ], 404);
        } else {
            return response()->json([
                'status' => true,
                'review' => $review,
                'images' => $images
            ]);
        }
    }

    public function update_single_review(Request $request)
    {
        $product_id = $request->product_id;
        $customer_id = $request->customer_id;
        $rating = $request->review['rating'] ?? null;
        $comment = $request->review['comment'] ?? null;
        $images = $request->review['images'] ?? [];

        $validator = Validator::make($request->all(), [
            'review.rating' => 'required',
            'review.comment' => 'required',
            'product_id'=>'required',
            'customer_id'=>'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Some fields are missing.',
                'errors' => $validator->errors()
            ], 422);
        }

        $existingReview = DB::table('product_reviews')
            ->where('product_id', $product_id)
            ->where('customer_id', $customer_id)
            ->first();

        if (!$existingReview) {
            return response()->json([
                'status' => false,
                'message' => 'Review not found.',
            ], 404);
        }

        DB::table('product_reviews')
            ->where('product_review_id', $existingReview->product_review_id)
            ->update([
                'rating' => $rating,
                'comment' => $comment,
                'updated_at' => Carbon::now('Asia/Kolkata'),
            ]);

        if (is_array($images) && count($images) > 0) {
            DB::table('product_review_content')
                ->where('product_review_id', $existingReview->product_review_id)
                ->delete();

            foreach ($images as $image) {
                DB::table('product_review_content')
                    ->insert([
                        'product_review_id' => $existingReview->product_review_id,
                        'img_video' => $image,
                    ]);
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Review updated successfully!',
        ]);
    }















    public function get_search_suggestion_($query)
    {

        if (empty($query)) {
            return response()->json([]);
        }

        $productData = DB::table('products')
            ->leftJoin('category', 'products.category_id', '=', 'category.category_id')
            // ->leftJoin('sub_category_1', 'sub_category_1.sub_category_1_id', '=', 'products.sub_category_1_id')
            // ->leftJoin('sub_category_2', 'sub_category_2.sub_category_2_id', '=', 'products.sub_category_2_id')
            // ->leftJoin('sub_category_3', 'sub_category_3.sub_category_3_id', '=', 'products.sub_category_3_id')
            ->where(function ($queryBuilder) use ($query) {
                $queryBuilder->where('products.product_name', 'LIKE', '%' . $query . '%')
                    ->orWhere('products.brand_name', 'LIKE', '%' . $query . '%');
            })
            ->select(
                'products.product_name',
                'products.brand_name',
                'products.product_id',
                'category.category_id',
                // 'category.category_name',
                // 'sub_category_1.sub_category_1_name',
                // 'sub_category_2.sub_category_2_name',
                // 'sub_category_3.sub_category_3_name'
            )
            ->groupBy('products.product_name', 'products.brand_name','products.product_id',
            'category.category_id',)
            ->orderBy('products.product_name', 'asc')
            ->limit(10)
            ->get();

        return response()->json($productData);
    }








    public function influencer_live_streaming__(Request $request)
    {

        $user_id = $request->user_id;
        $join_id = $request->join_id;
        $bag_id = $request->bag_id;
        if (!$user_id) {
            return response()->json([
                'status' => false,
                'message' => 'Influnecer Id Required'
            ]);
        }
        $currentDateTime = Carbon::now('Asia/Kolkata');
        $streamingDate = $currentDateTime->toDateString();
        $streamingTime = $currentDateTime->toTimeString();


        $data = [
            'user_id' => $user_id,
            'bag_id' => $bag_id,
            'join_id' => $join_id,
            'streaming_date' => $streamingDate,
            'streaming_time' => $streamingTime,
            'status' => 'Live'
        ];


        $influencerLiveId = DB::table('influencer_live')
            ->insertGetId($data);

        return response()->json([
            'status' => true,
            'message' => 'Influencer Live Started!',
            'influencer_live_id' => $influencerLiveId,
            'join_id'=>$join_id
        ]);
    }












    public function incrementProductViewCount($productId, $date)
    {
        DB::table('product_viewcount')
            ->updateOrInsert(
                ['product_id' => $productId, 'date' => $date],
                ['count' => DB::raw('CAST(count AS UNSIGNED) + 1')]
            );
    }





    public function seller_order_(Request $request) {
        $user_id = $request->user_id;
        $role = $request->role;

        if (!$user_id) {
            return response()->json([
                'status' => false,
                'message' => 'User ID required'
            ]);
        }


        $orderedProductsQuery = DB::table('ordered_products');

        if ($role == 'supplier' || $role == 'supplier-influencer' || $role == 'supplier-seller' || $role == 'influencer' ) {
            $orderedProductsQuery->where('user_id', $user_id);
        }
        else if($role=='superadmin'){

            $orderedProducts = $orderedProductsQuery->get();
        }


        if ($orderedProducts->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No orders found for this user.'
            ]);
        }

        $products = [];

        foreach ($orderedProducts as $orders) {
            $orderID = DB::table('orders')
                ->where('order_id', $orders->order_id)
                ->first();

            $product = DB::table('products')
                ->where('product_id', $orders->product_id)
                ->first();

            $product_varient = DB::table('product_varient')
                ->where('product_varient_id', $orders->product_varient_id)
                ->first();

            $product_size = DB::table('sizes')
                ->where('size_id', $orders->size_id)
                ->first();

            if ($product) {
                $products[] = [
                    'order_id' => $orderID->order_unique_id,
                    'order_status' => $orderID->order_status,
                    'product_name' => $product->product_name,
                    'product_id' => $product->product_id,
                    'product_img' => $product->product_img,
                    'net_weight' => $product->net_weight,
                    'price' => $product_varient->price,
                    'colour' => $product_varient->colour,
                    'total_amount' => $product_varient->total_amount,
                    'size' => $product_size->size_name,
                    'quantity' => $orders->quantity,
                    'order_date' => $orderID->inserted_date,
                ];
            }
        }

        return response()->json([
            'status' => true,
            'products' => $products
        ]);
    }



    public function customer_influencer_bag___(Request $request) {
        $countryCode = $request->countryCode;

        $influencer_live = DB::table('influencer_live')
            ->where('status', 'Live')
            ->get();

        $result = [];

        foreach ($influencer_live as $influencer) {
            $bag_id = $influencer->bag_id;
            $join_id = $influencer->join_id;
            $user_id = $influencer->user_id;

            $bag = DB::table('influencer_bag')
                ->where('influencer_bag_id', $bag_id)
                ->first();

            $influencer_data = DB::table('users')
                            ->where('user_id',$user_id)
                            ->select('user_image','name','user_id')
                            ->first();

            $productBagData = DB::table('product_to_bag')
                ->where('influencer_bag_id', $bag_id)
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
                    'bag_id' => $bag_id,
                    'bag_name' => $bag->bag_name,
                    'products' => $products,
                    'influencer_data'=>$influencer_data
                ];
            }
        }

        return response()->json([
            'status' => true,
            'bag_data' => $result
        ]);
    }












    public function update_password(Request $request){
        $email=$request->email;
        DB::table('users')
        ->where('email',$request->email)
        ->update([
            'password'=>Hash::make($request->password)
        ]);
    }
}


