<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class InfluencerController extends Controller
{
    //
    private function generateUniqueId()
    {
        do {
            $uniqueId = mt_rand(1000000000, 9999999999  );
        } while (DB::table('users')->where('user_unique_id', $uniqueId)->exists());

        return $uniqueId;
    }

    public function register_as_influencer(Request $request)
    {
        date_default_timezone_set('Asia/Kolkata');
        $lastUserId = DB::table('users')->orderBy('user_id', 'DESC')->value('user_id') ?? 0;
        // dd($lastUserId);
        $state = DB::table('states')
            ->select('state_subdivision_id', 'state_subdivision_code')
            ->where('state_subdivision_id', $request->input('state'))
            ->first();
        // dd($state);
        if (!$state) {
            $uniqueId = "12345678987654sdfgvcxsdfgvas";
        } else {
            $uniqueId = $state->state_subdivision_code . 'S' . $state->state_subdivision_id . $lastUserId;
        }
        // Prepare seller info array
        $influencerInfo = [
            'name' => $request->name,
            'password' => Hash::make($request->password), // Hash the password
            'address' => $request->address,
            'city' => $request->city,
            'state' => $request->state,
            'country' => $request->country,
            'inserted_date' => date('Y-m-d'),
            'inserted_time' => date('H:i:s'),
            'email' => $request->email,
            'role' => 'influencer',
            'mobile' => $request->mobile,
            'verify_mobile' => 'No',
            'verify_email' => 'No',
            'status' => 'Active',
            'locality' => $request->locality,
            'user_unique_id' => $this->generateUniqueId(),
            'rating' => '0'
        ];

        $existingInfluencerEmail = DB::table('users')->where('email', $request->email)->where('role', 'influencer')->first();
        if (!$existingInfluencerEmail) {
            $existingInfluencerMobile = DB::table('users')->where('mobile', $request->mobile)->where('role', 'influencer')->first();
            if (!$existingInfluencerMobile) {
                $userId = DB::table('users')->insertGetId($influencerInfo);
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

                    $usersData =DB::table('users')->where('user_id',$userId)->first();
                    $credentials = [
                        'email' =>$request->email,
                        'password' =>$request->password,
                    ];

                    if (!$token = auth('api')->attempt($credentials)) {
                        return response()->json([
                            'status' => false,
                            'message' => 'Invalid credentials',
                        ], 200);
                    }
                    $usersData->token = $token;



                    return response()->json([
                        'status' => true,
                        'message' => 'Influencer Added Successfully',
                        'user'=>$usersData
                    ]);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Influencer Error ..'
                    ]);
                }
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Mobile Number Already Registered As Influencer ..'
                ]);
            }

        } else {
            return response()->json([
                'status' => false,
                'message' => 'Email Id Already Registered As Influencer ..'
            ]);
        }

    }
    public function reset_influencer_password(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $validator = Validator::make($request->all(), [
            // 'customer_id' => 'required',
            'user_unique_id' => 'required',
            'otp' => 'required',
            'password' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);
        }

        $user = Auth::guard('api')->user();

        $user_unique_id = $request->user_unique_id;
        // $customer_unique_id = $request->unique_id;

        if ($user->user_unique_id !== $user_unique_id) {
            return response()->json([
                'status' => false,
                'message' => 'Customer unique ID does not match.',
            ], 400);
        }


        $userData=DB::table('users')
                        ->where('user_unique_id',$user_unique_id)
                        ->first();

        $userId = $userData->user_id;



        // $userId = $request->input('user_id');
        $otp = $request->input('otp');
        $password = $request->input('password');

        // Check if the influencer with the given user_id and otp exists
        $influencer = DB::table('users')
            ->where('user_id', $userId)
            ->where('mobile_otp', $otp)
            ->where('role', 'influencer')
            ->first();
        // dd($influencer);

        if (!$influencer) {
            // Influencer not found or invalid OTP
            return response()->json([
                'status' => false,
                'message' => 'Invalid OTP ...'
            ]);
        }

        // Update the influencer's password and verification status
        DB::table('users')
            ->where('user_id', $userId)
            ->update([
                'verify_mobile' => 'Yes',
                'password' => Hash::make($password) // Hash the password
            ]);

        // Retrieve the updated influencer data
        $updatedInfluencer = DB::table('users')
            ->where('user_id', $userId)
            ->first();

        // Prepare success response
        return response()->json([
            'status' => true,
            'user_id' => $updatedInfluencer->user_id,
            'message' => 'Influencer Found ...',
            'influencer_data' => $updatedInfluencer
        ]);
    }

    public function mobile_otp_influencer(Request $request)
    {

        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $validator = Validator::make($request->all(), [
            // 'customer_id' => 'required',
            'mobile' => 'required',
            // 'password' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);
        }

        // $user = Auth::guard('api')->user();

        // $user_unique_id = $request->user_unique_id;
        // // $customer_unique_id = $request->unique_id;

        // if ($user->user_unique_id !== $user_unique_id) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Customer unique ID does not match.',
        //     ], 400);
        // }


        // $userData=DB::table('users')
        //                 ->where('user_unique_id',$user_unique_id)
        //                 ->first();

        // $userId = $userData->user_id;


        $seller = DB::table('users')
            ->where('mobile', $request->input('mobile'))
            ->where('role', 'influencer')
            ->where('status', 'Active')
            ->first();

        if (!$seller) {
            return response()->json([
                'status' => false,
                'message' => 'Seller Not Found ...'
            ]);
        }

        $otp = rand(1000, 9999);

        DB::table('users')
            ->where('user_id', $seller->user_id)
            ->update(['mobile_otp' => $otp]);

        return response()->json([
            'status' => true,
            'otp' => $otp,
            'user_id' => $seller->user_id,
            'message' => 'Seller Found ...'
        ]);
    }

    public function get_bag_product_list(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $validator = Validator::make($request->all(), [
            'user_unique_id' => 'required',


        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);
        }

        $user = Auth::guard('api')->user();

        $user_unique_id = $request->user_unique_id;
        // $customer_unique_id = $request->unique_id;

        if ($user->user_unique_id !== $user_unique_id) {
            return response()->json([
                'status' => false,
                'message' => 'Customer unique ID does not match.',
            ], 400);
        }


        $userData=DB::table('users')
                        ->where('user_unique_id',$user_unique_id)
                        ->first();

        $user_id = $userData->user_id;

        // $user_id = $request->user_id;


        $productBagData = DB::table('product_to_bag')
            ->where('user_id', $user_id)
            ->get();

        $result = [];


        if (!$productBagData->isEmpty()) {
            foreach ($productBagData as $productBag) {
                $products = [];
                // $bagName=DB::table('influencer_bag')
                // ->where('influencer_bag_id',$productBag->influencer_bag_id)
                // ->select('bag_name')
                // ->first();
                // dd($bagName);

                $productVariantData = DB::table('product_varient')
                    ->where('product_varient.product_varient_id', $productBag->product_varient_id)
                    ->join('products', 'product_varient.product_id', '=', 'products.product_id')
                    ->join('product_varient_size', 'product_varient.product_varient_id', '=', 'product_varient_size.product_varient_id')
                    ->select(
                        'product_varient.product_varient_id',
                        'product_varient.product_id',
                        'product_varient.colour',
                        'product_varient.quantity',
                        'product_varient.price',
                        'products.product_name',
                        'products.product_img',
                        'product_varient_size.size_id',
                        'product_varient_size.stock'
                    )
                    ->get();


                foreach ($productVariantData as $variant) {
                    $products[] = $variant;
                }


                $result[] = [
                    'product_to_bag_id' => $productBag->product_to_bag_id,
                    // 'bag_name' => $bagName->bag_name,
                    'products' => $products,
                ];
            }
        }

        return response()->json([
            'status' => true,
            'bags' => $result,
            'message' => count($result) ? "Bags and products fetched successfully." : "No products in any bags."
        ]);
    }
    public function influencer_add_product_bag(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $validator = Validator::make($request->all(), [
            'influencer_unique_id' => 'required',
            'product_unique_id' => 'required',


        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);
        }

        $user = Auth::guard('api')->user();

        $user_unique_id = $request->influencer_unique_id;
        // $customer_unique_id = $request->unique_id;

        if ($user->user_unique_id !== $user_unique_id) {
            return response()->json([
                'status' => false,
                'message' => 'Customer unique ID does not match.',
            ], 400);
        }


        $userData=DB::table('users')
                        ->where('user_unique_id',$user_unique_id)
                        ->first();

        if(!$userData){
            return response()->json([
                'status'=>false,
                'message'=>'No user Found'
            ]);
        }
        $productData=DB::table('products')
                        ->where('product_unique_id',$request->product_unique_id)
                        ->first();
                        if(!$productData){
                            return response()->json([
                                'status'=>false,
                                'message'=>'No product Found'
                            ]);
                        }
        $influencer_id = $userData->user_id;

        // $influencer_id = $request->influencer_id;
        $product_id = $productData->product_id;
        // $lastbag = DB::table('influencer_bag')
        //     ->where('user_id', $influencer_id)
        //     ->where('status', 'Active')
        //     // ->orderBy('influencer_bag_id', 'DESC')
        //     ->first();

        $productexist = DB::table('product_to_bag')
            ->where('user_id', $influencer_id)
            ->where('product_id', $product_id)
            ->where('status', 'Active')
            // ->orderBy('influencer_bag_id', 'DESC')
            ->first();
        if($productexist){
            return response()->json
            (
                [
                    'status' => false,
                    'message' => 'Product Already Exist',
                ]
            );
        }


        $varient = DB::table('product_varient')
            ->join('products', 'product_varient.product_id', '=', 'products.product_id')
            ->join('product_varient_size', 'product_varient.product_varient_id', '=', 'product_varient_size.product_varient_id')
            ->where('product_varient.product_id', $product_id)
            ->first();

        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        $data = [
            // 'influencer_bag_id' => $lastbag->influencer_bag_id,
            'product_id' => $product_id,
            // 'main_product_size_id' => $varient->size_id,
            // 'product_images_id' => $varient->product_img,
            'product_varient_id' => $varient->product_varient_id,
            'size_id' => $varient->size_id,
            // 'product_varient_image_id' => '',
            // 'stock' =>$varient->stock,
            'status'=>'Active',
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
            'user_id'=>$influencer_id
        ];

        if (empty($data)) {
            return response()->json([
                'status' => false,
                'message' => 'Missing required data.',
            ], 400);
         }
        $product_to_bag_id=  DB::table('product_to_bag')
                ->insertGetId($data);

            return response()->json([
                'status' => true,
                'message' => 'Product successfully added to bag.',
                // 'product_to_bag_id'=>$product_to_bag_id
            ]);
    }

    public function influencer_product_remove_bag(Request $request)
    {

        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $validator = Validator::make($request->all(), [
            'product_to_bag_id' => 'required',



        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);
        }
        $product_to_bag_id = $request->product_to_bag_id;

        if (!$product_to_bag_id) {
            return response()->json([
                'status' => true,
                'message' => 'Product to bag Id Required'
            ]);
        }

        $deleted=DB::table('product_to_bag')
        ->where('product_to_bag_id',$product_to_bag_id)
        ->delete();

        if($deleted){
            return response()->json([
                'status' => true,
                'message' => 'Product Remove Succesfully',

            ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => 'Product Not found',

            ]);
        }
    }
    public function save_join_id(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $validator = Validator::make($request->all(), [
            'user_unique_id' => 'required',
            'join_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);
        }
        $user = Auth::guard('api')->user();

        $user_unique_id = $request->user_unique_id;
        // $customer_unique_id = $request->unique_id;

        if ($user->user_unique_id !== $user_unique_id) {
            return response()->json([
                'status' => false,
                'message' => 'Customer unique ID does not match.',
            ], 400);
        }


        $userData=DB::table('users')
                        ->where('user_unique_id',$user_unique_id)
                        ->first();

        if(!$userData){
            return response()->json([
                'status'=>false,
                'message'=>'No user Found'
            ]);
        }
        $user_id = $userData->user_id;
        $join_id = $request->join_id;

        $currentDateTime = Carbon::now('Asia/Kolkata');
        $streamingDate = $currentDateTime->toDateString();
        $streamingTime = $currentDateTime->toTimeString();
        // $new=DB::table('influencer_bag')
        //         ->where('user_id',$user_id)
        //         ->orderBy('influencer_bag_id','desc')
        //         ->first();

        $data = [
            'user_id' => $user_id,
            'join_id' => $join_id,
            'streaming_date' => $streamingDate,
            'streaming_time' => $streamingTime,
            'status' => 'Live',
            // 'bag_id' => $new->influencer_bag_id,
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

    public function influencer_live_end(Request $request)
    {
        $influencer_live_id = $request->influencer_live_id;
        $currentDateTime = Carbon::now('Asia/Kolkata');

        $insertTime = $currentDateTime->toTimeString();

        if (!$influencer_live_id) {
            return response()->json([
                'status' => false,
                'message' => 'Influencer Live ID is required!'
            ]);
        }

        $influencerLive = DB::table('influencer_live')
            ->where('influencer_live_id', $influencer_live_id)
            ->first();

        if (!$influencerLive) {
            return response()->json([
                'status' => false,
                'message' => 'Influencer Live session not found!'
            ]);
        }

        if ($influencerLive->status === 'End') {
            return response()->json([
                'status' => false,
                'message' => 'Influencer Live session is already ended!'
            ]);
        }

        $update= DB::table('influencer_live')
            ->where('influencer_live_id', $influencer_live_id)
            ->update([
                'status' => 'End',
                'streaming_end_time'=>$insertTime
            ]);
        if($update){
                return response()->json([
                    'status' => true,
                    'message' => 'Influencer Live session ended successfully.'
                ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => 'Internal Server Error'
            ]);
        }
    }
    public function get_bag_product_list_app(Request $request)
    {
        // if (!Auth::guard('api')->check()) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Unauthorized access.',
        //     ], 400);
        // }
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',


        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);
        }




        $userData=DB::table('users')
                        ->where('user_id',$request->user_id)
                        ->first();

        $user_id = $userData->user_id;

        // $user_id = $request->user_id;


        $productBagData = DB::table('product_to_bag')
            ->where('user_id', $user_id)
            ->get();

        $result = [];


        if (!$productBagData->isEmpty()) {
            foreach ($productBagData as $productBag) {
                $products = [];
                // $bagName=DB::table('influencer_bag')
                // ->where('influencer_bag_id',$productBag->influencer_bag_id)
                // ->select('bag_name')
                // ->first();
                // dd($bagName);

                $productVariantData = DB::table('product_varient')
                    ->where('product_varient.product_varient_id', $productBag->product_varient_id)
                    ->join('products', 'product_varient.product_id', '=', 'products.product_id')
                    ->join('product_varient_size', 'product_varient.product_varient_id', '=', 'product_varient_size.product_varient_id')
                    ->select(
                        'product_varient.product_varient_id',
                        'product_varient.product_id',
                        'product_varient.colour',
                        'product_varient.quantity',
                        'product_varient.price',
                        'products.product_name',
                        'products.product_img',
                        'product_varient_size.size_id',
                        'product_varient_size.stock'
                    )
                    ->get();


                foreach ($productVariantData as $variant) {
                    $products[] = $variant;
                }


                $result[] = [
                    'product_to_bag_id' => $productBag->product_to_bag_id,
                    // 'bag_name' => $bagName->bag_name,
                    'products' => $products,
                ];
            }
        }

        return response()->json([
            'status' => true,
            'bags' => $result,
            'message' => count($result) ? "Bags and products fetched successfully." : "No products in any bags."
        ]);
    }

    public function influencer_live_bags(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $validator = Validator::make($request->all(), [
            'user_unique_id' => 'required',
            'countryCode' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);
        }
        $user = Auth::guard('api')->user();

        $user_unique_id = $request->user_unique_id;
        // $customer_unique_id = $request->unique_id;

        if ($user->user_unique_id !== $user_unique_id) {
            return response()->json([
                'status' => false,
                'message' => 'Customer unique ID does not match.',
            ], 400);
        }


        $userData=DB::table('users')
                        ->where('user_unique_id',$user_unique_id)
                        ->first();

        if(!$userData){
            return response()->json([
                'status'=>false,
                'message'=>'No user Found'
            ]);
        }
        $user_id = $userData->user_id;
        // $user_id = $request->user_id;
        $countryCode = $request->countryCode;

        $bagItems = DB::table('influencer_bag')
            ->where('status', 'Active')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('product_to_bag')
                    ->whereRaw('product_to_bag.influencer_bag_id = influencer_bag.influencer_bag_id');
            })
            ->orderBy('inserted_date', 'desc')
            ->orderBy('inserted_time', 'desc')
            ->get();

        if ($bagItems->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => "No Bag Found."
            ]);
        }

        $latestBags = [];

        foreach ($bagItems as $bagItem) {
            $user_id = $bagItem->user_id;

            if (!isset($latestBags[$user_id])) {
                $latestBags[$user_id] = [];
            }

            if (count($latestBags[$user_id]) < 5) {
                $latestBags[$user_id][] = $bagItem;
            }
        }

        $result = [];

        foreach ($latestBags as $userBags) {
            foreach ($userBags as $latestBagItem) {
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
                    $totalProducts = $productBagData->count();

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
                                DB::raw('AVG(rating) as average_rating'),
                                DB::raw('COUNT(product_review_id) as review_count')
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
                                // 'productData' => $productData,
                                'product_variant' => $productVariantData,
                                // 'size' => $size,
                                // 'review_avg' => $formatted_Productrating,
                                // 'review_count' => $review_count,
                            ];
                        }
                    }

                    $result[] = [
                        // 'influencerData' => $influencerData,
                        'bag_id' => $bagId,
                        'bag_name' => $latestBagItem->bag_name,
                        'total_products' => $totalProducts,
                        'products' => $products,
                    ];
                }
            }
        }

        return response()->json([
            'status' => true,
            'bags' => $result,
            'message' => count($result) ? "Latest bags and products fetched successfully." : "No products in any bags."
        ]);
    }
    public function influencer_live_streaming(Request $request)
    {

        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $validator = Validator::make($request->all(), [
            'user_unique_id' => 'required',
            'join_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);
        }
        $user = Auth::guard('api')->user();

        $user_unique_id = $request->user_unique_id;
        // $customer_unique_id = $request->unique_id;

        if ($user->user_unique_id !== $user_unique_id) {
            return response()->json([
                'status' => false,
                'message' => 'Customer unique ID does not match.',
            ], 400);
        }


        $userData=DB::table('users')
                        ->where('user_unique_id',$user_unique_id)
                        ->first();

        if(!$userData){
            return response()->json([
                'status'=>false,
                'message'=>'No user Found'
            ]);
        }
        $user_id = $userData->user_id;

        // $user_id = $request->user_id;
        $join_id = $request->join_id;

        if (!$user_id) {
            return response()->json([
                'status' => false,
                'message' => 'Influencer Id Required'
            ]);
        }

        $currentDateTime = Carbon::now('Asia/Kolkata');
        $streamingDate = $currentDateTime->toDateString();
        $streamingTime = $currentDateTime->toTimeString();

        $data = [
            'user_id' => $user_id,
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
            'join_id' => $join_id
        ]);
    }

     public function influencer_create_bag(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $validator = Validator::make($request->all(), [
            'user_unique_id' => 'required',
            'bag_name' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);
        }
        $user = Auth::guard('api')->user();

        $user_unique_id = $request->user_unique_id;
        // $customer_unique_id = $request->unique_id;

        if ($user->user_unique_id !== $user_unique_id) {
            return response()->json([
                'status' => false,
                'message' => 'Customer unique ID does not match.',
            ], 400);
        }


        $userData=DB::table('users')
                        ->where('user_unique_id',$user_unique_id)
                        ->first();

        if(!$userData){
            return response()->json([
                'status'=>false,
                'message'=>'No user Found'
            ]);
        }
        $user_id = $userData->user_id;

        // $user_id = $request->user_id;
        $bag_name = $request->bag_name;
        if (!$user_id && !$bag_name) {
            return response()->json([
                'status' => true,
                'message' => 'User Id Required'
            ]);
        }
        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        $existingBag=DB::table('influencer_bag')
                    ->where('user_id',$user_id)
                    ->where('bag_name',$bag_name)
                    ->first();

        if($existingBag){
            return response()->json([
                'status' => false,
                'message'=>'Bag Name Alredy Added'
            ]);
        }


        $influencer_bag_id = DB::table('influencer_bag')
            ->insertGetId([
                'user_id' => $user_id,
                'bag_name' => $bag_name,
                'inserted_date' => $insertDate,
                'inserted_time' => $insertTime,
                'status'=>'Active'
            ]);

        return response()->json([
            'status' => true,
            'influencer_bag_id' => $influencer_bag_id,
            'user_id' => $user_id,
            'bag_name' => $bag_name,
        ]);

    }




    public function influencer_get_product_bag(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $validator = Validator::make($request->all(), [
            'user_unique_id' => 'required',
            // 'join_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);
        }
        $user = Auth::guard('api')->user();

        $user_unique_id = $request->user_unique_id;
        // $customer_unique_id = $request->unique_id;

        if ($user->user_unique_id !== $user_unique_id) {
            return response()->json([
                'status' => false,
                'message' => 'Customer unique ID does not match.',
            ], 400);
        }


        $userData=DB::table('users')
                        ->where('user_unique_id',$user_unique_id)
                        ->first();

        if(!$userData){
            return response()->json([
                'status'=>false,
                'message'=>'No user Found'
            ]);
        }
        $user_id = $userData->user_id;

        // $user_id = $request->user_id;

        $bagItems = DB::table('influencer_bag')
            ->where('user_id', $user_id)
            ->where('status', 'Active')
            ->orderBy('influencer_bag_id', 'desc')
            ->get();

        if ($bagItems->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => "No Bag Found."
            ]);
        } else {
            $result = [];

            foreach ($bagItems as $bagItem) {
                $bagId = $bagItem->influencer_bag_id;
                $productBagData = DB::table('product_to_bag')
                    ->where('influencer_bag_id', $bagId)
                    ->get();

                $products = [];
                if (!$productBagData->isEmpty()) {
                    foreach ($productBagData as $productBag) {
                        $productVariantData = DB::table('product_varient')
                            ->where('product_varient_id', $productBag->product_varient_id)
                            ->join('products', 'product_varient.product_id', '=', 'products.product_id')
                            ->select(
                                'product_varient.product_varient_id',
                                'product_varient.product_id',
                                'product_varient.colour',
                                'product_varient.quantity',
                                'product_varient.price',
                                'products.product_name',
                                'products.product_img'
                            )
                            ->first();

                        if ($productVariantData) {
                            $products[] = [
                                'product_to_bag_id' => $productBag->product_to_bag_id, // Add product_to_bag_id here
                                'product_variant' => $productVariantData,
                            ];
                        }
                    }
                }

                $result[] = [
                    'bag_id' => $bagId,
                    'bag_name' => $bagItem->bag_name,
                    'products' => $products,
                ];
            }

            return response()->json([
                'status' => true,
                'bags' => $result,
                'message' => count($result) ? "Bags and products fetched successfully." : "No products in any bags."
            ]);
        }
    }

}
