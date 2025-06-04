<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
class AdminController extends Controller
{
    public function get_stripe_credentials_app(Request $request)
    {

        $stripecredentials = DB::table('stripecredentials')
                    ->where('stripe_credentials_id','1')
                    ->where('status','Active')
                    ->first();
        if ($stripecredentials) {
            $data =[
                'public_key' => $stripecredentials->stripe_public_key,
                'secret_key' => $stripecredentials->stripe_secret_key,
            ];

            return response()->json([
                'status' => true,
                'message' => 'Details fetched successfully.',
                'data'=>$data

            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Details not found.',
            ], 404);
        }
    }

    public function addFaqs(Request $request)
    {

        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $validator = Validator::make($request->all(), [
            // 'customer_id' => 'required',
            'title' => 'required',
            'para' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);
        }




          // Get current date and time
          $currentDateTime = Carbon::now();
          $insertDate = $currentDateTime->toDateString(); // Current date
          $insertTime = $currentDateTime->toTimeString(); // Current time
        $data = [
            'title' => $request->title,
            'para' => $request->para,
            'status' => "Active",
            'inserted_date' => $insertDate,  // Add current date
            'inserted_time' => $insertTime,

        ];
        $insertedfaq = DB::table('faq')->insert($data);
        return response()->json([
            'status' => true,
            'message' => 'FAQ created successfully',
            'faq_data' => $data
        ]);
    }

    public function deletefaqs($id)
    {

        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }


        // Find the FAQ by ID
        $faq = DB::table('faq')->where('faq_id', $id)->first();

        if (!$faq) {
            return response()->json([
                'status' => false,
                'message' => 'FAQ not found',
            ], 404);
        }

        // Delete the FAQ
        DB::table('faq')->where('faq_id', $id)->delete();

        return response()->json([
            'status' => true,
            'message' => 'FAQ deleted successfully',
        ]);
    }

    public function updatefaqs(Request $request, $id)
    {

        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }


        $faq = DB::table('faq')->where('faq_id', $id)->first();

        if (!$faq) {
            return response()->json([
                'status' => false,
                'message' => 'FAQ not found',
            ], 404);
        }

        DB::table('faq')
            ->where('faq_id', $id)
            ->update([
                'title' => $request->input('title'),
                'para' => $request->input('para'),
            ]);

        return response()->json([
            'status' => true,
            'message' => 'FAQ updated successfully',
        ]);
    }
    public function get_app_credentials_app(Request $request)
    {

        $appcredentials = DB::table('applivecredentials')
                    ->where('applivecredentialsid','1')
                    ->where('status','Active')
                    ->first();
        if ($appcredentials) {
            $data =[
                'appid' => $appcredentials->appid,
                'serversecret' => $appcredentials->serversecret,
                'appsign' => $appcredentials->appsign,
            ];

            return response()->json([
                'status' => true,
                'message' => 'Details fetched successfully.',
                'data'=>$data

            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Details not found.',
            ], 404);
        }
    }


    public function addAlaspartner(Request $request)
    {

        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        date_default_timezone_set('Asia/Kolkata');

        // Retrieve JSON data from the request
        $data = $request->json()->all();

        // Extract influencer information from the JSON data
        $alaspartnerInfo = $data['alasPartner_info'] ?? [];
        $bankDetails = $data['bank_details'] ?? [];

        // Fetch the last user ID for unique ID generation
        $lastUserId = DB::table('users')->orderBy('user_id', 'DESC')->value('user_id') ?? 0;

        // Fetch state subdivision details
        $state = DB::table('states')->select('state_subdivision_code', 'state_subdivision_id')
            ->where('state_subdivision_id', $influencerInfo['state_subdivision_id'] ?? '')
            ->first();

        if ($state) {
            $uniqueId = $state->state_subdivision_code . 'F' . $state->state_subdivision_id . $lastUserId;
        } else {
            $uniqueId = 'UnknownStateF' . ($lastUserId + 1); // Default or handle missing state
        }
        // Get current date and time
        $currentDateTime = Carbon::now();
        $insertDate = $currentDateTime->toDateString(); // Current date
        $insertTime = $currentDateTime->toTimeString(); // Current time

        // Prepare influencer info array
        $alaspartnerData = [
            'name' => (($alaspartnerInfo['firstName'] ?? '') . ' ' . ($alaspartnerInfo['lastName'] ?? '')),
            'password' => Hash::make($alaspartnerInfo['password'] ?? ''), // Hash the password

            'email' => $alaspartnerInfo['email'] ?? '',
            'mobile' => $alaspartnerInfo['mobile'] ?? '',
            'unique_id' => $uniqueId,
            'rating' => '0',
            'inserted_date' => $insertDate,  // Add current date
            'inserted_time' => $insertTime,

        ];

        // Check for existing influencer by email
        $existingalaspartnerDataEmail = DB::table('users')->where('email', $alaspartnerData['email'])
            ->where('role', 'alaspartner')
            ->first();

        if (!$existingalaspartnerDataEmail) {
            // Check for existing influencer by mobile number
            $existingalaspartnerDataMobile = DB::table('users')->where('mobile', $alaspartnerData['mobile'])
                ->where('role', 'alaspartner')
                ->first();

            if (!$existingalaspartnerDataMobile) {
                // Insert new influencer
                $userId = DB::table('users')->insertGetId(array_merge($alaspartnerData, ['role' => 'alaspartner', 'status' => 'Active']));

                if ($userId) {
                    // Prepare and insert bank details
                    $bankData = [
                        'account_holder_name' => $bankDetails['accountHoldername'] ?? '',
                        'account_number' => $bankDetails['bankAccountNo'] ?? '',
                        'ifsc_code' => $bankDetails['IFSCcode'] ?? '',
                        'user_id' => $userId,
                        'inserted_date' => $insertDate,  // Add current date
                        'inserted_time' => $insertTime,

                    ];
                    DB::table('bank_details')->insert($bankData);

                    return response()->json([
                        'status' => true,
                        'message' => 'Alas Partner user Added Successfully'
                    ]);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Error Adding Alas Partner user'
                    ]);
                }
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Mobile Number Already Registered As Alas Partner user'
                ]);
            }
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Email Id Already Registered As Alas Partner user'
            ]);
        }
    }

    public function getCustomer(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $customers = DB::table('customers')->get();
        $customers = DB::table('customers') ->select('customers.first_name', 'customers.last_name', 'customers.mobile', 'customers.email', 'customers.gender', 'customers.country', 'customers.state', 'countries.country_name', 'states.state_subdivision_name','customers.unique_id',)
            ->where('customer_id', $request->customer_id)
            ->leftJoin('countries', 'countries.country_id', '=', 'customers.country')
            ->leftJoin('states', 'states.state_subdivision_id', '=', 'customers.state') ->get();
            $result = $customers->toArray();
        return response()->json(['status' => true, 'customers' => $result]);
    }

    public function getContactFormData(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $data = DB::table("contact_form")->get();
        if($data->isEmpty()){
            return response()->json([
                'status'=>false,
                'message'=>'No Contact form found'
            ]);
        }
        return response()->json([
            'status' => true,
             'data' => $data
            ]);
    }

    public function get_user_list()
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        // $roles = ['supplier', 'supplier-influencer', 'supplier-seller'];

       $users = DB::table('users')
            ->leftJoin('products', 'users.user_id', '=', 'products.user_id')
            ->select(
                'users.name',
                'users.user_id',
                'users.user_image',
                'users.email',
                'users.role',
                'users.unique_id',
                'users.status',
                DB::raw('COUNT(products.product_id) as product_count')
            )
            ->groupBy(
                'users.user_id',
                'users.name',
                'users.user_image',
                'users.email',
                'users.role',
                'users.unique_id',
                'users.status'
            )
            ->orderBy('users.user_id', 'desc')
            ->get();


        if ($users->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No users found!',
            ]);
        }

        return response()->json([
            'status' => true,
            'users' => $users,
        ]);
    }

    public function get_user_profile(Request $request)
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

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);
        }
        $users=DB::table('users')->where('user_unique_id',$request->user_unique_id)->first();
        $userId=$users->user_id;
        $user = DB::table('users')
        ->where('user_id', $userId)
        ->orderBy('user_id', 'desc')
        ->select('name','user_id','user_image','email','mobile','address','state','country','city','locality','pin_code')
        ->first();

        $userSubscription = DB::table('users')
        ->where('users.user_id',$userId)
        ->join('subscription','subscription.user_id','=','users.user_id')
        ->orderBy('user_id', 'desc')
        ->select('users.name','users.email','users.mobile','users.address','subscription.*')
        ->get();



        return response()->json([
            'status' => true,
            'user' => $user,
            'userSubscription' =>$userSubscription
        ]);
    }

    public function category_status_update(Request $request)
    {


        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $validator = Validator::make($request->all(), [
            // 'customer_id' => 'required',
            'category_id' => 'required|integer',
            'status' => 'required|string'

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);
        }


        // $request->validate([
        //     'category_id' => 'required|integer',
        //     'status' => 'required|string'
        // ]);
        DB::table('category')
            ->where('category_id', $request->category_id)
            ->update(['category_status' => $request->status]);

        $sub_category_1_ids = DB::table('sub_category_1')
            ->where('category_id', $request->category_id)
            ->pluck('sub_category_1_id');

        DB::table('sub_category_1')
            ->whereIn('sub_category_1_id', $sub_category_1_ids)
            ->update(['status' => $request->status]);

        $sub_category_2_ids = DB::table('sub_category_2')
            ->whereIn('sub_category_1_id', $sub_category_1_ids)
            ->pluck('sub_category_2_id');

        DB::table('sub_category_2')
            ->whereIn('sub_category_2_id', $sub_category_2_ids)
            ->update(['status' => $request->status]);

        $sub_category_3_ids = DB::table('sub_category_3')
            ->whereIn('sub_category_2_id', $sub_category_2_ids)
            ->pluck('sub_category_3_id');

        DB::table('sub_category_3')
            ->whereIn('sub_category_3_id', $sub_category_3_ids)
            ->update(['status' => $request->status]);

        $response = [
            'status' => true,
            'message' => 'Category Status Set To ' . $request->status
        ];

        return response()->json($response);
    }

    public function add_main_category(Request $request)
    {
        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();



        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $validator = Validator::make($request->all(), [
            // 'customer_id' => 'required',
            'category_name' => 'required',
            'category_image' => 'required',
            'description' => 'required',
            'home' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);
        }


            DB::table('category')->insert([
                'category_name' => $request->category_name,
                'category_image' => $request->category_image,
                'description' => $request->description,
                'home'=>$request->home,
                'category_status' => 'Active',
                'inserted_date' => $insertDate,
                'inserted_time' => $insertTime
            ]);



        return response()->json([
            'status' => true,
            'message' => 'Categories added successfully'
        ]);
    }

    public function add_sub_categories(Request $request)
    {
        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'category_id' => 'required',
            'sub_categories' => 'required|array|min:1',
            'sub_categories.*.title' => 'required|string|max:255',
        ]);

        // Handle validation failure
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $category_id = $request->input('category_id');
        $sub_categories = $request->input('sub_categories');

        foreach ($sub_categories as $sub_category_name) {
            DB::table('sub_category_1')->insert([
                'category_id' => $category_id,
                'sub_category_1_name' => $sub_category_name['title'],
                'status' => 'Active',
                'inserted_date' => $insertDate,
                'inserted_time' => $insertTime
            ]);
        }

        $sub_category_count = DB::table('sub_category_1')
            ->where('category_id', $category_id)
            ->count();

        return response()->json([
            'status' => true,
            'message' => 'Sub-categories added successfully. Total sub-categories: ' . $sub_category_count,
        ]);
    }

    public function add_sub2_categories(Request $request)
    {
        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'sub_category_1_id' => 'required',
            'sub_categories' => 'required|array|min:1',
            'sub_categories.*.title' => 'required|string|max:255',
        ]);

        // Handle validation failure
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $category_id = $request->input('sub_category_1_id');
        $sub_categories = $request->input('sub_categories');

        foreach ($sub_categories as $sub_category_name) {
            DB::table('sub_category_2')->insert([
                'sub_category_1_id' => $category_id,
                'sub_category_2_name' => $sub_category_name['title'],
                'status' => 'Active',
                'inserted_date' => $insertDate,
                'inserted_time' => $insertTime
            ]);
        }

        $sub_category_count = DB::table('sub_category_2')
            ->where('sub_category_1_id', $category_id)
            ->count();

        return response()->json([
            'status' => true,
            'message' => 'Sub-categories added successfully. Total sub-categories: ' . $sub_category_count,
        ]);
    }

    public function add_sub3_categories(Request $request)
    {
        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'sub_category_2_id' => 'required',
            'sub_categories' => 'required|array|min:1',
            'sub_categories.*.title' => 'required|string|max:255',
        ]);

        // Handle validation failure
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $category_id = $request->input('sub_category_2_id');
        $sub_categories = $request->input('sub_categories');

        foreach ($sub_categories as $sub_category_name) {
            DB::table('sub_category_3')->insert([
                'sub_category_2_id' => $category_id,
                'sub_category_3_name' => $sub_category_name['title'],
                'status' => 'Active',
                'inserted_date' => $insertDate,
                'inserted_time' => $insertTime
            ]);
        }

        $sub_category_count = DB::table('sub_category_3')
            ->where('sub_category_2_id', $category_id)
            ->count();

        return response()->json([
            'status' => true,
            'message' => 'Sub-categories added successfully. Total sub-categories: ' . $sub_category_count,
        ]);
    }


    public function sub_categories_status_update(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'sub_category_1_id' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = DB::table('sub_category_1')
        ->where('sub_category_1_id', $request->sub_category_1_id)
       ->first();

       if(!$data){
        return response()->json([
            'status'=>false,
            'message'=>'No Data Found'
        ]);
       }
         DB::table('sub_category_1')
            ->where('sub_category_1_id', $request->sub_category_1_id)
            ->update(['status' => $request->status]);

        $response = [
            'status' => true,
            'message' => 'Category Status Set To ' . $request->status
        ];

        return response()->json($response);
    }

    public function sub2_categories_status_update(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'sub_category_2_id' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = DB::table('sub_category_2')
        ->where('sub_category_2_id', $request->sub_category_2_id)
       ->first();

       if(!$data){
        return response()->json([
            'status'=>false,
            'message'=>'No Data Found'
        ]);
       }
         DB::table('sub_category_2')
            ->where('sub_category_2_id', $request->sub_category_2_id)
            ->update(['status' => $request->status]);

        $response = [
            'status' => true,
            'message' => 'Category Status Set To ' . $request->status
        ];

        return response()->json($response);
    }

    public function sub3_categories_status_update(Request $request)
    {
        // echo $request->status;
        // echo $request->sub_category_3_id;die;
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'sub_category_3_id' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = DB::table('sub_category_3')
        ->where('sub_category_3_id', $request->sub_category_3_id)
         ->first();

       if(!$data){
        return response()->json([
            'status'=>false,
            'message'=>'No Data Found'
        ]);
       }
        DB::table('sub_category_3')
            ->where('sub_category_3_id', $request->sub_category_3_id)
            ->update(['status' => $request->status]);

        $response = [
            'status' => true,
            'message' => 'Category Status Set To ' . $request->status
        ];

        return response()->json($response);
    }

    public function add_update_newsletter(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'image' => 'nullable|string',
            'sub_title' => 'nullable|string',
            'description' => 'required|string',
            'status' => 'required|string',
            'newsletter_id' => 'nullable|integer'
        ]);

        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }


        // Prepare the newsletter data
        $newsletterData = [
            'heading' => $validated['title'],
            'image1' => $validated['image'],
            'status' => $validated['status'],
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ];

        if (isset($validated['newsletter_id'])) {
            // Update existing newsletter
            DB::table('newsletter')
                ->where('newsletter_id', $validated['newsletter_id'])
                ->update($newsletterData);

            // Remove existing content and insert new content
            DB::table('newsletter_content')->where('newsletter_id', $validated['newsletter_id'])->delete();

            if (isset($validated['description'])) {
                DB::table('newsletter_content')->insert([
                    'newsletter_id' => $validated['newsletter_id'],
                    'sub_heading' => $validated['sub_title'],
                    'paragraph' => $validated['description'],
                    'inserted_date' => $insertDate,
                    'inserted_time' => $insertTime,
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Newsletter updated successfully'
            ]);
        } else {
            // Insert new newsletter
            $newsletter_id = DB::table('newsletter')->insertGetId($newsletterData);

            if (isset($validated['description'])) {
                DB::table('newsletter_content')->insert([
                    'newsletter_id' => $newsletter_id,
                    'sub_heading' => $validated['sub_title'],
                    'paragraph' => $validated['description'],
                    'inserted_date' => $insertDate,
                    'inserted_time' => $insertTime,
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Newsletter added successfully'
            ]);
        }
    }

    public function change_newsletter_status(Request $request) {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $validated = $request->validate([
            'newsletter_id' => 'required|integer',
            'status' => 'required|string'
        ]);




        DB::table('newsletter')
            ->where('newsletter_id', $validated['newsletter_id'])
            ->update([
                'status' => $validated['status']
            ]);

        return response()->json([
            'status' => true,
            'message' => 'Newsletter status updated successfully'
        ]);
    }

    public function add_brand(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'brand_name' => 'required',
            'brand_image' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();
        $brandData = [
            'brand_name' => $request->brand_name,
            'brand_image' => $request->brand_image,
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ];

        $existingBrand = DB::table('brands')
            ->where('brand_name', $brandData['brand_name'])
            ->first();


        if ($existingBrand) {
            return response()->json([
                'status' => false,
                'message' => 'Brand already exists.',
            ]);
        }
        // Prepare brand data

        // Check if required fields are empty
        if (empty($brandData['brand_name']) || empty($brandData['brand_image'])) {
            return response()->json([
                'status' => false,
                'message' => 'Brand name and image are required.',
            ]);
        }

        // Insert the brand data into the database
        DB::table('brands')->insert($brandData);

        return response()->json([
            'status' => true,
            'message' => 'Added Successfully!',
        ]);
    }

    public function delete_brand($id)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }


        $brand = DB::table('brands')->where('brand_id', $id)->first();

        if (!$brand) {
            return response()->json([
                'status' => false,
                'message' => 'Brand not found',
            ], 404);
        }

        DB::table('brands')->where('brand_id', $id)
        ->delete();

        return response()->json([
            'status' => true,
            'message' => 'Brand deleted successfully',
        ]);
    }

    public function update_brand(Request $request)
    {

        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'brand_id' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $brand_id=$request->brand_id;

        $brand = DB::table('brands')->where('brand_id', $brand_id)->first();

        if (!$brand) {
            return response()->json([
                'status' => false,
                'message' => 'Brand not found',
            ]);
        }
        $updateData = [];

        if ($request->has('brand_name')) {
            $updateData['brand_name'] = $request->brand_name;
        }

        if ($request->has('brand_image')) {
            $updateData['brand_image'] = $request->brand_image;
        }
            if (!empty($updateData)) {
          $updateData=  DB::table('brands')
                ->where('brand_id', $brand_id)
                ->update($updateData);
                if(!$updateData){
                    return response()->json([
                        'status' => false,
                        'message' => 'Please Update the  fields first',
                    ], 400);
                }
        } else {
            return response()->json([
                'status' => false,
                'message' => 'No fields to update',
            ], 400);
        }

        return response()->json([
            'status' => true,
            'message' => 'Brand updated successfully',
        ]);
    }

    public function add_new_wharehouse(Request $request)
    {

        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'mobile' => 'required',
            'email' => 'required',
            'telephone' => 'required',
            'country' => 'required',
            'country_id' => 'required',
            'state' => 'required',
            'city' => 'required',
            'managerName' => 'required',
            'managerMobile' => 'required',
            'managerEmail' => 'required',
            'pincode' => 'required',
            'locality' => 'required',
            'address' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();
        $wharehouse_data = [
            'name' => $request->name,
            'mobile' => $request->mobile,
            'email' => $request->email,
            'telephone' => $request->telephone,
            'country_id' => $request->country,
            'state_subdivision_id' => $request->state,
            'status' => 'Active',
            'city' => $request->city,
            'manager' => $request->managerName,
            'manager_mobile' => $request->managerMobile,
            'manager_email' => $request->managerEmail,
            'pin_code' => $request->pincode,
            'locality' => $request->locality,
            'address1' => $request->address,
            // 'address2' => $request->address2,
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime
        ];

        DB::table('wharehouse')->insert($wharehouse_data);

        return response()->json([
            'status' => true,
            'message' => 'Wharehouse Added Successfully'
        ]);
    }

    public function update_wharehouse_status(Request $request){
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required',
            'wharehouse_id' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $wharehouse_data = [
            'status' =>$request->status,
        ];
        DB::table('wharehouse')->where('wharehouse_id' ,$request->wharehouse_id)->update($wharehouse_data);
        return response()->json([
            'status'=>true,
            'message'=> 'Wharehouse Status '.$request->status.' Successfully.'
            ]);
    }

    public function update_wharehouse_data(Request $request){
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'wharehouse_id'=>'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        if($request->wharehouse_id){
            $wharehouse_data = [
                'name' =>$request->name,
                'mobile' =>$request->mobile,
                'email' => $request->email,
                'telephone' =>$request->telephone,
                'country_id' =>$request->country,
                'state_subdivision_id' =>$request->state,
                'city'=> $request->city,
                'manager'=> $request->managerName,
                'pin_code'=>$request->pincode,
                'locality'=>$request->locality,
                'address1'=> $request->address,
                // 'address2' =>$request->address2,
                'manager_mobile' =>$request->managerMobile,
                'manager_email' =>$request->managerEmail
            ];

            DB::table('wharehouse')->where('wharehouse_id' ,$request->wharehouse_id)->update($wharehouse_data);
            return response()->json([
                'status'=>true,
                'message'=> 'Wharehouse Details Updated Successfully.'
                ]);
        }else{
            return response()->json([
                'status'=>false,
                'message'=> 'Wharehouse ID Not Found.'
                ]);
        }
    }

    public function update_sub_category1(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'sub_category_1_id' => 'required',
            'status' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $sub_category_1_id = $request->sub_category_1_id;
        $update = DB::table('sub_category_1')
            ->where('sub_category_1_id', $sub_category_1_id)
            ->update([
                'sub_category_1_name' => $request->sub_category_1_name,
                'status' => $request->status,
            ]);

            if($update){
                return response()->json([
                    'status' => true,
                    'message' => 'Sub Categories Update successfully'
                ]);
            }
            return response()->json([
                'status' => false,
                'message' => 'Internal Server Error'
            ]);



    }

    public function update_sub_category2(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'sub_category_2_id' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }


        $sub_category_2_id = $request->sub_category_2_id;
        $update = DB::table('sub_category_2')
            ->where('sub_category_2_id', $sub_category_2_id)
            ->update([
                'sub_category_2_name' => $request->sub_category_2_name,
                'status' => $request->status,
            ]);


            if($update){
                return response()->json([
                    'status' => true,
                    'message' => 'Sub Categories 2 Update successfully'
                ]);
            }
            return response()->json([
                'status' => false,
                'message' => 'Internal Server Error'
            ]);


    }

    public function update_sub_category3(Request $request)
    {

        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'sub_category_3_id' => 'required',
            'status' => 'required',
            'sub_category_3_name'=> 'required',

        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // echo $request->sub_category_3_id;
        // echo $request->status;
        // echo $request->sub_category_3_name;
        // die;

        $sub_category_3_id = $request->sub_category_3_id;
        $update = DB::table('sub_category_3')
            ->where('sub_category_3_id', $sub_category_3_id)
            ->update([
                'status' => $request->status,
                'sub_category_3_name' => $request->sub_category_3_name,
            ]);

        if($update){
            return response()->json([
                'status' => true,
                'message' => 'Sub Categories 3 Update to '.$request->status.' successfully.'
            ]);
        }
        else{
            return response()->json([
                'status' => true,
                'message' => 'Same data Updated.'
            ]);
        }
    }

    public function add_coupon(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'coupon_name' => 'required',
            'coupon_image_url' => 'required',
            'coupon_code'=> 'required',
            'amount'=> 'required',
            'start_date'=> 'required',
            'expire_date'=> 'required',
            'status'=> 'required',
            'cart_min_amount'=> 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();
        $coupon_data = [
            'coupon_name' =>$request->coupon_name,
            'coupon_image' =>$request->coupon_image_url,
            'coupon_code' => strtoupper($request->coupon_code),
            'amount' =>$request->amount,
            'start_date' =>$request->start_date,
            'expire_date' =>$request->expire_date,
            'status' =>$request->status,
            'inserted_date' =>$insertDate,
            'inserted_time' =>$insertTime,
            'cart_min_amount'=>$request->cart_min_amount
        ];

        DB::table('coupon')->insert($coupon_data);

        return response()->json([
            'status'=>true,
            'message' =>'Coupon Added Successfully...!'
        ]);
    }

    public function get_offer_list_customer(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $maxDiscount1 = DB::table('products')

            ->max('discount');
        $maxDiscount2 = $maxDiscount1 - 10;
        $maxDiscount3 = $maxDiscount2 - 10;
        $maxDiscount4 = $maxDiscount3 - 10;

        $discountValues = [$maxDiscount1, $maxDiscount2, $maxDiscount3, $maxDiscount4];


        $productData = DB::table('products')
            ->leftJoin('category', 'products.category_id', '=', 'category.category_id')
            ->leftJoin('sub_category_1', 'sub_category_1.sub_category_1_id', '=', 'products.sub_category_1_id')
            ->leftJoin('sub_category_2', 'sub_category_2.sub_category_2_id', '=', 'products.sub_category_2_id')
            ->leftJoin('sub_category_3', 'sub_category_3.sub_category_3_id', '=', 'products.sub_category_3_id')
            ->whereIn('products.discount', $discountValues)
            ->select(
                // 'products.product_name',
                'products.discount',
                'products.product_id',
                'category.category_id',
                'category.category_name',
                'sub_category_1.sub_category_1_name',
                'sub_category_2.sub_category_2_name',
                'sub_category_3.sub_category_3_name'
            )
            // ->groupBy(
            //     'products.product_name',
            //     'products.brand_name',
            //     'products.product_id',
            //     'category.category_id',
            //     'category.category_name'
            // )
            ->orderBy('discount', 'desc')

            ->get();

        return response()->json($productData);
    }

    public function get_wharehousebyid_list(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $validator = Validator::make($request->all(), [
            'wharehouse_id' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $wharehouse = DB::table('wharehouse')
            ->where('wharehouse_id', $request->wharehouse_id)
            ->first();

        if (is_null($wharehouse)) {
            return response()->json([
                'status' => false,
                'message' => 'Warehouse not found.',
            ]);
        }

        return response()->json([
            'status' => true,
            'wharehouse' => $wharehouse,
        ]);
    }
    public function update_user_image(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $validator = Validator::make($request->all(), [
            'user_unique_id'=>'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        date_default_timezone_set('Asia/Kolkata');
        $currentDateTime = now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        $user = Auth::guard('api')->user();

        $user_unique_id = $request->user_unique_id;
        // $customer_unique_id = $request->unique_id;

        if ($user->user_unique_id !== $user_unique_id) {
            return response()->json([
                'status' => false,
                'message' => 'Customer unique ID does not match.',
            ], 400);
        }
        $users=DB::table('users')->where('user_unique_id',$request->user_unique_id)->first();

        $userId = $users->user_id;
        $user_image_update = DB::table('users')->where('user_id', $userId)->update([
            'user_image' => $request->image,
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ]);

        if ($user_image_update) {
            return response()->json([
                'status' => true,
                'massage' => 'Image Update Successfully',
            ]);
        } else {
            return response()->json([
                'status' => false,
                'massage' => 'user ID Required',
            ]);
        }

    }

    public function add_banners(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $validator = Validator::make($request->all(), [
           'bannerdata.banner' => 'required',
        'bannerdata.type' => 'required',
        'bannerdata.banner_category' => 'required',
        'bannerdata.banner_discount' => 'nullable',
        'bannerdata.status' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $dbanners = $request->bannerdata;

        DB::table('banners')
            ->insert([
                'banners' => $dbanners['banner'] ?? null,
                'banner_type' => $dbanners['type'] ?? null,
                'banner_category' => $dbanners['banner_category'] ?? null,
                'banner_discount' => $dbanners['banner_discount'] ?? null,
                'status' => $dbanners['status'] ?? null,
            ]);


        return response()->json([
            'status' => true,
            'message'=>'Banner Added Successfully'
        ]);

    }


    public function get_orderedProduct_ByOrderId(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $orderId = $request->order_id;

        $orderProductData = DB::table('ordered_products')
            ->where('order_id', $orderId)
            ->leftJoin('products', 'ordered_products.product_id', '=', 'products.product_id')
            ->select('ordered_products.*', 'products.product_name', 'products.product_img')
            ->get();

        if ($orderProductData->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No products found for the specified order.'
            ], 404);
        }


        $customerId = $orderProductData[0]->customer_id;

        if (!$customerId) {
            return response()->json([
                'status' => false,
                'message' => 'Customer ID not found in ordered products.'
            ], 404);
        }

        $customerData = DB::table('customers')
            ->where('customer_id', $customerId)
            ->select('customers.first_name','customers.last_name','customers.customer_id',
            'customers.mobile',
            'customers.email',
            'customers.country',
            'customers.customer_image')
            ->first();


        if (!$customerData) {
            return response()->json([
                'status' => false,
                'message' => 'Customer not found for the specified order.'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'order_products' => $orderProductData,
            'customer_data' => $customerData
        ]);
    }

    public function get_all_supervisor(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $supervisors=DB::table('users')
        ->select('users.*','bank_details.account_number','bank_details.ifsc_code','bank_details.account_holder_name')
        ->join('bank_details','users.user_id','=','bank_details.user_id')
        ->where('role','supervisor')
        // ->select('name','user_image','address','email','mobile','user_id')
        ->get();
        if ($supervisors->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Supervisors not found.',
            ]);
        }

        return response()->json([
            'status' => true,
            'supervisors' => $supervisors,
        ]);
    }

    public function get_all_manager(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $managers = DB::table('users')
            ->select(
                'users.user_id',
                'users.user_image',
                'users.name',
                'users.role',
                'users.email',
                'users.mobile',
                'users.status',
                'users.unique_id',
                'users.rating',
                'users.gender',
                DB::raw('MAX(state_to_manager.state_subdivision_id) as state_subdivision_id'),
                DB::raw('MAX(state_to_manager.state_to_manager_id) as state_to_manager_id'),
                DB::raw('MAX(countries.country_id) as country_id'),
                DB::raw('MAX(countries.country_name) as country_name')
            )
            ->leftJoin('bank_details', 'users.user_id', '=', 'bank_details.user_id')
            ->leftJoin('state_to_manager', 'users.user_id', '=', 'state_to_manager.user_id')
            ->leftJoin('states', 'state_to_manager.state_subdivision_id', '=', 'states.state_subdivision_id')
            ->leftJoin('countries', 'states.country_id', '=', 'countries.country_id')
            ->where('users.role', 'manager')
            ->groupBy('users.user_id')
            ->get();

        if ($managers->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Managers not found.',
            ]);
        }

        return response()->json([
            'status' => true,
            'managers' => $managers,
        ]);
    }

    public function register_new_manager(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }


        $data = $request->json()->all();

        $sellerInfo = $data['manager_info'] ?? [];
        $bankDetails = $data['bank_details'] ?? [];
        $workingArea = $data['working_area'] ?? [];

        // Fetch the last user ID for unique ID generation
        $lastUserId = DB::table('users')->orderBy('user_id', 'DESC')->value('user_id') ?? 0;

        // Fetch state subdivision details
        $state = DB::table('states')
            ->select('state_subdivision_id', 'state_subdivision_code')
            ->where('state_subdivision_id', $sellerInfo['state'] ?? '')
            ->first();

        if ($state) {
            $uniqueId = $state->state_subdivision_code . 'M' . $state->state_subdivision_id . $lastUserId;
        } else {
            $uniqueId = 'UnknownStateS' . ($lastUserId + 1);
        }
        $role = 'manager';

        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();
        $after20DaysDate = $currentDateTime->copy()->addDays(20)->toDateString();


        $sellerData = [
            'name' => ($sellerInfo['firstName'] ?? '') . ' ' . ($sellerInfo['lastName'] ?? ''),
            'password' => Hash::make($sellerInfo['password'] ?? ''),
            'email' => $sellerInfo['email'] ?? '',
            'mobile' => $sellerInfo['mobile'] ?? '',
            'unique_id' => $uniqueId,
            'rating' => '0',
            'role' => $role,
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
            // 'valid_upto'=>$after20DaysDate,
            'permission' => '1',
            'views' => '0',
            'country' => $sellerInfo['country'],
            'state' => $sellerInfo['state'],
            'address' => $sellerInfo['address'],
            'city' => $sellerInfo['city'],
            'pin_code' => $sellerInfo['pincode'],
            'locality' => $sellerInfo['pincode'],
            'status' => 'Active'
        ];

        $existingSellerEmail = DB::table('users')
            ->where('email', $sellerData['email'])
            ->first();

        if (!$existingSellerEmail) {
            $existingSellerMobile = DB::table('users')
                ->where('mobile', $sellerData['mobile'])
                ->first();

            if (!$existingSellerMobile) {
                $userId = DB::table('users')->insertGetId(array_merge($sellerData, ['status' => 'Active']));

                $user_data = DB::table('users')->where('user_id', $userId)
                    ->select(
                        'user_id',
                        'name',
                        'address',
                        'city',
                        'state',
                        'country',
                        'email',
                        'role',
                        'mobile',
                        'status',
                        'locality',
                        'rating',
                        'gender',
                        'valid_upto'
                    )->get();

                if ($userId) {

                    $bankData = [
                        'account_holder_name' => $bankDetails['accountHoldername'] ?? '',
                        'account_number' => $bankDetails['bankAccountNo'] ?? '',
                        'ifsc_code' => $bankDetails['IFSCcode'] ?? '',
                        'user_id' => $userId,
                        'inserted_date' => $insertDate,
                        'inserted_time' => $insertTime,
                        'status' => 'Active'
                    ];

                    DB::table('bank_details')->insert($bankData);

                    $workingData = [
                        // 'account_number' => $workingArea['country'] ?? '',
                        'state_subdivision_id' => $workingArea['state'] ?? '',
                        'user_id' => $userId,
                        'status' =>'Active',
                        'inserted_date' => $insertDate,
                        'inserted_time' => $insertTime,
                    ];

                    foreach ($workingArea as $area) {
                        $workingData = [
                            'state_subdivision_id' => $area['state'] ?? '',
                            'user_id' => $userId,
                            'status' => 'Active',
                            'inserted_date' => $insertDate,
                            'inserted_time' => $insertTime,
                        ];

                        DB::table('state_to_manager')->insert($workingData);
                    }

                    DB::table('state_to_manager')->insert($workingData);



                    return response()->json([
                        'status' => true,
                        'message' => 'Manager Added Successfully',
                        'user_data' => $user_data[0],
                        'role' => $role
                    ]);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Server Error ..'
                    ]);
                }
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Mobile Number Already Registered As Manager ..'
                ]);
            }
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Email Id Already Registered As Manager ..'
            ]);
        }
    }

    public function register_new_supervisor(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $data = $request->json()->all();

        $sellerInfo = $data['supervisor_info'] ?? [];
        $bankDetails = $data['bank_details'] ?? [];

        // Fetch the last user ID for unique ID generation
        $lastUserId = DB::table('users')->orderBy('user_id', 'DESC')->value('user_id') ?? 0;

        // Fetch state subdivision details
        $state = DB::table('states')
            ->select('state_subdivision_id', 'state_subdivision_code')
            ->where('state_subdivision_id', $sellerInfo['state'] ?? '')
            ->first();

        if ($state) {
            $uniqueId = $state->state_subdivision_code . 'SP' . $state->state_subdivision_id . $lastUserId;
        } else {
            $uniqueId = 'UnknownStateS' . ($lastUserId + 1);
        }
        // $role = $sellerInfo['role'] ?? '';
        $role='supervisor';
        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();
        $after20DaysDate = $currentDateTime->copy()->addDays(20)->toDateString();


        $sellerData = [
            'name' => ($sellerInfo['firstName'] ?? '') . ' ' . ($sellerInfo['lastName'] ?? ''),
            'password' => Hash::make($sellerInfo['password'] ?? ''),
            'email' => $sellerInfo['email'] ?? '',
            'mobile' => $sellerInfo['mobile'] ?? '',
            'unique_id' => $uniqueId,
            'rating' => '0',
            'role' => $role,
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
            'valid_upto'=>$after20DaysDate,
            'permission'=>'1',
            'views'=>'0',
            'country'=>$sellerInfo['country'] ,
            'state'=>$sellerInfo['state'],
            'address'=>$sellerInfo['address'],
            'city'=>$sellerInfo['city'],
            'pin_code'=>$sellerInfo['pincode'],
            'status'=>'Active',
            'locality'=>$sellerInfo['locality'] ??'',
        ];

        $existingSellerEmail = DB::table('users')
            ->where('email', $sellerData['email'])
            ->first();

        if (!$existingSellerEmail) {
            $existingSellerMobile = DB::table('users')
                ->where('mobile', $sellerData['mobile'])
                ->first();

            if (!$existingSellerMobile) {
                $userId = DB::table('users')->insertGetId(array_merge($sellerData, ['status' => 'Active']));

                $user_data =DB::table('users')->where('user_id',$userId)
                ->select('user_id','name','address','city','state','country','email','role','mobile','status',
                'locality','rating','gender','valid_upto')->get();

                if ($userId) {

                    $bankData = [
                        'account_holder_name' => $bankDetails['accountHoldername'] ?? '',
                        'account_number' => $bankDetails['bankAccountNo'] ?? '',
                        'ifsc_code' => $bankDetails['IFSCcode'] ?? '',
                        'user_id' => $userId,
                        'inserted_date' => $insertDate,
                        'inserted_time' => $insertTime,
                        'status'=>'Active'
                    ];

                    DB::table('bank_details')->insert($bankData);

                    return response()->json([
                        'status' => true,
                        'message' => 'Supervisor Added Successfully',
                        'user_data'=>$user_data[0],
                        'role'=> $role
                    ]);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Server Error ..'
                    ]);
                }
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Mobile Number Already Registered As Supervisor ..'
                ]);
            }
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Email Id Already Registered As Supervisor ..'
            ]);
        }
    }

    public function update_product_status(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $validator = Validator::make($request->all(), [
            'product_unique_id'=>'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['status'=>false,'message' => $validator->errors()]);
        }
        $productDataNew=DB::table('products')->where('product_unique_id',$request->product_unique_id)->firt();

        $product = DB::table('products')
            ->select('product_id')
            ->where('product_id', $productDataNew->product_id)
            ->first();

        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found.',
            ]);
        }


        if (empty($request->status)) {
            return response()->json([
                'status' => false,
                'message' => 'Status cannot be empty.',
            ]);
        }

        $currentDateTime = Carbon::now('Asia/Kolkata');
        $todayDate = $currentDateTime->toDateString();


        $days = DB::table('ads')
            ->where('admin_subscription_id', '1')
            ->first();

        $subscriptionPeriod = (int) $days->subscription_period;

        // dd($subscriptionPeriod);

        $afterDays = Carbon::parse($todayDate)->addDays($subscriptionPeriod)->toDateString();


        $productData = [
            'status' => $request->status];
        if ($request->status == 'Active') {
            $productData['valid_date'] = $afterDays;
            $productData['view_per_day'] = $days->views;
        }



        $updatedRows = DB::table('products')
            ->where('product_id', $productDataNew->product_id)
            ->update($productData);

        if ($updatedRows) {
            return response()->json([
                'status' => true,
                'message' => 'Product Status Updated Successfully.',
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Failed to Update Product Status.',
            ]);
        }

    }

    public function update_user_status(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'user_unique_id'=>'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['status'=>false,'message' => $validator->errors()]);
        }
        $type = $request->type;

        if($type=="customer"){
        $CustomerData=DB::table('customers')->where('unique_id',$request->user_unique_id)->first();
        }else if ($type === "user") {
            $userData = DB::table('users')->where('user_unique_id', $request->user_unique_id)->first();
        }


        if ($type === "customer") {
            $user = DB::table('customers')->where('customer_id', $CustomerData->customer_id);
        } else if ($type === "user") {
            $user = DB::table('users')->where('user_id', $userData->user_id);
        }

        $user->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found.',
            ]);
        }

        if (empty($request->status)) {
            return response()->json([
                'status' => false,
                'message' => 'Status cannot be empty.',
            ]);
        }
        $userData = ['status' => $request->status];

        if ($type === "customer") {
            $updatedRows = DB::table('customers')->where('customer_id', $request->user_id)->update($userData);
        } else if ($type === "user") {
            $updatedRows = DB::table('users')->where('user_id', $request->user_id)->update($userData);
        }

        if ($updatedRows) {
            return response()->json([
                'status' => true,
                'message' => 'User Status Updated Successfully.',
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Failed to Update Status.',
            ]);
        }
    }

    public function get_seller_list()
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }


        $roles = ['supplier'];

        $users = DB::table('users')
            ->whereIn('role', $roles)
            ->orderBy('user_id', 'desc')
            ->select('name', 'user_id', 'user_image', 'email', 'role')
            ->get();

            if(!is_null($users)){
                return response()->json([
                    'status' => true,
                    'users' => $users,
                ]);
            }
            else{
                return response()->json([
                    'status' => false,
                    'message' => 'No supplier found!',
                ]);
            }
    }

    public function get_supplier_influencer_list()
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $roles = ['supplier-influencer'];

        $users = DB::table('users')
            ->whereIn('role', $roles)
            ->orderBy('user_id', 'desc')
            ->select('name', 'user_id', 'user_image', 'email', 'role')
            ->get();
            if(!is_null($users)){
                return response()->json([
                    'status' => true,
                    'users' => $users,
                ]);
            }
            else{
                return response()->json([
                    'status' => false,
                    'message' => 'No supplier Influencer found!',
                ]);
            }

    }

    public function get_supplier_seller_list()
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $roles = ['supplier-seller'];

        $users = DB::table('users')
            ->whereIn('role', $roles)
            ->orderBy('user_id', 'desc')
            ->select('name', 'user_id', 'user_image', 'email', 'role')
            ->get();
            if(!is_null($users)){
                return response()->json([
                    'status' => true,
                    'users' => $users,
                ]);
            }
            else{
                return response()->json([
                    'status' => false,
                    'message' => 'No supplier seller found!',
                ]);
            }
    }

    public function contact_form_delete(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'contact_form_id'=>'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['status'=>false,'message' => $validator->errors()]);
        }
        // $productData=DB::table('products')->where('product_unique_id',$request->product_unique_id)->firt();

        $contact_form_id = $request->contact_form_id;
        if(!$contact_form_id){
            return response()->json([
                'status' => false,
                'message' => 'Inquiries Id not Found....',
            ]);
        }

       $deleted= DB::table('contact_form')->where('contact_form_id',$contact_form_id)->delete();
       if($deleted){
        return response()->json([
            'status' => true,
            'message' => 'Inquiries Deleted Successfully.',
        ]);
       }
       else{
        return response()->json([
            'status' => false,
            'message' => 'Failed to Delete Inquiries.',
        ]);
       }

    }

    public function contact_form_update(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $contact_form_id = $request->contact_form_id;
        if(!$contact_form_id){
            return response()->json([
                'status'=> false,
                'message'=> 'Inquiries Id not Found.',
                ]);
        }else{
            $contact_status= DB::table('contact_form')->where('contact_form_id',$contact_form_id)->update([
                'status'=> $request->status,
                ]);
                if($contact_status){
                    return response()->json([
                        'status'=> true,
                        'message'=> 'Inquiries Status Updated Successfully.',
                        ]);
                }else{
                    return response()->json([
                        'status'=> false,
                        'message'=> 'Failed to Update Inquiries Status.',
                        ]);
                }
        }
    }

    public function get_all_banners()
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        // Fetch data from the banners table
        $banners = DB::table('banners')
            ->select('*')
            // ->where('status', 'Active')
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
                'status' => $row->status,
                'banner_category' => $row->banner_category,
                'banner_discount' => $row->banner_discount,
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

// ===================================================================== //

    public function getBanners()
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $banners = DB::table('banners')
            ->select('*')
            // ->where('status', 'Active')
            ->orderBy('banners_id', 'DESC')
            ->get();

        $banners_array = [];

        foreach ($banners as $row) {
            $banner_data = [
                'banner_id' => $row->banners_id,
                'banner' => $row->banners,
                'type' => $row->banner_type,
                'banner_link' => $row->banner_link,
                'status' => $row->status
            ];
            $banners_array[] = $banner_data;
        }

        $response = [
            'status' => true,
            'message' => 'Banners Found Successfully',
            'banners' => $banners_array,
        ];

        return response()->json($response);
    }

    public function product_reviews_status_update(Request $request)
    {
        $product_review_id = $request->product_review_id;
        DB::table('product_reviews')
            ->where('product_review_id', $product_review_id)
            ->update([
                'status' => $request->status
            ]);

        return response()->json([
            'status' => true,
            'message' => 'Review Status Changes to '.$request->status
        ]);

    }

    public function get_manager_areas(Request $request)
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
        $userData=DB::table('users')->where('user_unique_id',$request->user_unique_id)->first();
        $user_id = $userData->user_id;
        $areas = DB::table('state_to_manager')
            ->where('user_id', $user_id)
            ->leftJoin('states', 'states.state_subdivision_id', '=', 'state_to_manager.state_subdivision_id')
            ->leftJoin('countries', 'countries.country_id', '=', 'states.country_id')
            ->orderBy('states.state_subdivision_id','desc')
            ->select('countries.country_name','states.state_subdivision_name','state_to_manager.state_to_manager_id')
            ->get();
        if ($areas->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Areas not found.',
            ]);
        }

        return response()->json([
            'status' => true,
            'areas' => $areas,
        ]);

    }

    public function update_manager_area_status(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [

            'state_to_manager_id' => 'required',


        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);

        }
        // $userData=DB::table('users')->where('user_unique_id',$request->user_unique_id)->first();
        $state_to_manager_id = $request->state_to_manager_id;

        DB::table('state_to_manager')
            ->where('state_to_manager_id', $state_to_manager_id)
            ->update([
                'status' => $request->status
            ]);

        return response()->json([
            'status' => true,
            'message' => 'Manager Status Changes to ' . $request->status
        ]);

    }

    public function assign_area_to_manager(Request $request)
    {
        // $country_id=$request->country_id;

        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();


        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [

            'user_unique_id' => 'required',
            'state_subdivision_id' => 'required',
               ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);

        }
        $userData=DB::table('users')->where('user_unique_id',$request->user_unique_id)->first();
        $user_id = $userData->user_id;
        $state_subdivision_id=$request->state_subdivision_id;
        // $user_id=$request->user_id;

        $data=[
            'state_subdivision_id'=>$state_subdivision_id,
            'user_id'=>$user_id,
            'status'=>'Active',
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime

        ];
        DB::table('state_to_manager')->insert($data);


        return response()->json([
            'status' => true,
            'message' => 'Area Assigned Successfully'
        ]);

    }

    public function get_wharehouse_list(Request $request)
    {

        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $wharehouse = DB::table('wharehouse')
            ->get();
        if ($wharehouse->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Wharehouse not found.',
            ]);
        }
        return response()->json([
            'status' => true,
            'wharehouse' => $wharehouse,
        ]);
    }



    public function reset_user_password(Request $request) {

        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [

            'user_unique_id' => 'required',
            'password' => 'required',
               ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);

        }
        $userData=DB::table('users')->where('user_unique_id',$request->user_unique_id)->first();
        $user_id = $userData->user_id;

        $user = DB::table('users')
            ->select('user_unique_id')
            ->where('user_id', $userData->user_id)
            ->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found.',
            ]);
        }
        if (empty($request->password)) {
            return response()->json([
                'status' => false,
                'message' => 'password cannot be empty.',
            ]);
        }
        $usersData = [
            'password' => Hash::make($request->password)
        ];

        $updatedRows = DB::table('users')
            ->where('user_id', $userData->user_id)
            ->update($usersData);

        if ($updatedRows) {
            return response()->json([
                'status' => true,
                'message' => 'User Password Updated Successfully'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Failed to Update User Password.'
            ]);
        }
    }

    public function update_banners(Request $request)
    {

        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $dbanners = $request->bannerdata;

         DB::table('banners')
            ->where('banners_id', $dbanners['banner_id'])
            ->update([
                'banners' => $dbanners['banner'] ?? null,
                'banner_type' => $dbanners['type'] ?? null,
                'banner_category' => $dbanners['banner_category'] ?? null,
                'banner_discount' => $dbanners['banner_discount'] ?? null,
                // 'banner_title' => $dbanners['banner_title'] ?? null,
                'status' => $dbanners['status'] ?? null,
            ]);
        $updatedBanner = DB::table('banners')->where('banners_id', $dbanners['banner_id'])->first();

        return response()->json([
            'status' => true,
            'banner_data' => $updatedBanner
        ]);
    }
    public function get_idwise_products_review_avg(Request $request)
    {

        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [

            'product_unique_id' => 'required',


        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);

        }
        $productData=DB::table('products')->where('product_unique_id',$request->product_unique_id)->first();

        $productID = $productData->product_id;

        if (!$productID) {
            return response()->json([
                'status' => false,
                'message' => 'Product ID not found.',
            ]);
        }

        $productReviews = DB::table('product_reviews')
            ->where('product_id', $productID)
            ->where('status', 'Active')
            ->select(
                DB::raw('AVG(product_reviews.rating) as average_rating'),
                DB::raw('COUNT(product_reviews.product_review_id) as review_count')
            )
            ->groupBy('product_id')
            ->orderBy('average_rating', 'desc')
            ->orderBy('review_count', 'desc')
            ->first();

        if (is_null($productReviews) || $productReviews->review_count === 0) {
            return response()->json([
                'status' => false,
                'message' => 'No reviews found for this product.',
            ]);
        }

        return response()->json([
            'status' => true,
            'product_reviews' => $productReviews,
        ]);
    }

    public function get_cusotmer_databyid(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [

            'unique_id' => 'required',


        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);

        }
        $userData=DB::table('customers')->where('unique_id',$request->unique_id)->first();

        $customer_id = $userData->customer_id;
        $userdata = DB::table('customers')

            // ->leftJoin('countries', 'countries.country_id', '=', 'users.country')
            // ->leftJoin('states', 'states.state_subdivision_id', '=', 'users.state')
            ->where('customer_id', $customer_id)
            ->get();

            // $product_data = null;
        $ordered_products = DB::table('ordered_products')
            ->leftJoin('orders', 'orders.order_id', '=', 'ordered_products.order_id')
            ->where('ordered_products.customer_id', $customer_id)
            ->orderBy('ordered_products.ordered_product_id', 'DESC')
            ->get();


        return response()->json([
            'status' => true,
            'data' => $userdata,
            'products' => $ordered_products,

        ]);

    }

    public function customer_category_data(Request $request)
    {

        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [

            'sub_category_2_id' => 'required',
            'countryCode' => 'required',



        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);

        }
        $sub_category_2_id=$request->sub_category_2_id;
        $countryCode = $request->countryCode;
        $productData = DB::table('products')
            ->leftJoin('category', 'products.category_id', '=', 'category.category_id')
            ->leftJoin('sub_category_1', 'sub_category_1.sub_category_1_id', '=', 'products.sub_category_1_id')
            ->leftJoin('sub_category_2', 'sub_category_2.sub_category_2_id', '=', 'products.sub_category_2_id')
            ->leftJoin('sub_category_3', 'sub_category_3.sub_category_3_id', '=', 'products.sub_category_3_id')
            ->select(
                'products.*',
                'category.category_name',
                'sub_category_1.sub_category_1_name',
                'sub_category_2.sub_category_2_name',
                'sub_category_3.sub_category_3_name'
            )
            ->where('products.sub_category_2_id', $sub_category_2_id)
            ->where('products.status', 'Active')
            // ->where('products.stock_status', 'Live')
            ->orderBy('products.product_id', 'desc')
            ->get();

        $MainproductsAllData = [];

        $currencyInfo = $this->getCurrencyInfo($countryCode);
        $rate = $currencyInfo['rate'] ?? '1';
        $currencysymbol = $currencyInfo['symbol'] ?? '$';
        foreach ($productData as $product) {

            $productVariants = DB::table('product_varient')
                ->where('product_id', $product->product_id)
                ->select('product_varient_id', 'colour', 'total_amount', 'quantity')
                ->get();

            $variantImages = DB::table('product_varient_images')
                ->whereIn('product_varient_id', $productVariants->pluck('product_varient_id'))
                ->select('product_varient_id', 'images')
                ->take(2)
                ->get();

            // Collect sizes
            $sizes = DB::table('product_varient_size')
                ->whereIn('product_varient_id', $productVariants->pluck('product_varient_id'))
                ->leftJoin('sizes', 'product_varient_size.size_id', '=', 'sizes.size_id')
                ->select('sizes.size_name')
                ->distinct()
                ->get()
                ->pluck('size_name')
                ->toArray();

            foreach ($productVariants as $variant) {

                $variant->symbol = $currencysymbol;

                if (!isset($MainproductsAllData[$product->product_id])) {
                    $MainproductsAllData[$product->product_id] = [
                        'product_id' => $product->product_id,
                        'product_name' => $product->product_name,
                        'sub_text' => $product->sub_text,
                        'product_img' => $variantImages,
                        'category_name' => $product->category_name,
                        'discount' => $product->discount,
                        'colors' => [],
                        'sizes' => $sizes,
                        'symbol'=>$currencysymbol
                    ];
                }

                $MainproductsAllData[$product->product_id]['colors'][] = $variant->colour;

                if (!isset($MainproductsAllData[$product->product_id]['price'])) {
                    if (isset($currencyInfo)) {
                        $MainproductsAllData[$product->product_id]['price'] = sprintf('%.2f', ($variant->total_amount) * $rate);
                    } else {
                        $MainproductsAllData[$product->product_id]['price'] = sprintf('%.2f', $variant->total_amount);
                    }
                }
            }
        }

        return response()->json([
            'status' => true,
            'MainproductsAllData' => array_values($MainproductsAllData)
        ]);
    }

    public function get_transaction(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }


        $threeMonthsAgo = Carbon::now()->subMonths(3);
        $transactionData = DB::table('orders')
            ->where('orders.inserted_date', '>=', $threeMonthsAgo)
            ->orderBy('order_id', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'transactionData' => $transactionData
        ]);



    }
    public function get_coupon(){
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }


        $coupons = DB::table('coupon')
        ->orderBy('coupon_id','desc')
        ->get();
        if($coupons->isEmpty()){
            return response()->json([
                'status'=>false,
                'message'=>'No Coupons found'
            ]);
        }
        else{
            return response()->json([
                'status'=>true,
                'coupons'=>$coupons
            ]);
        }
    }

    public function update_coupon(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [

            'coupon_id' => 'required',
            'coupon_name' => 'required|string|max:255',
            'coupon_image_url' => 'nullable|string', // or 'url' if it's always a valid URL
            'coupon_code' => 'required|string|max:100',
            'status' => 'required', // adjust if you use numeric status like 1/0
            'amount' => 'required',
            'start_date' => 'required',
            'expire_date' => 'required|after_or_equal:start_date',
            'cart_min_amount' => 'required|min:0',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);

        }
        $coupon = DB::table('coupon')
            ->where('coupon_id', $request->coupon_id)
            ->first();

        if (!$coupon) {
            return response()->json([
                'status' => false,
                'message' => 'Coupon not found.',
            ], 404);
        }

        $updated_coupon = [
            'coupon_name' => $request->coupon_name,
            'coupon_image' => $request->coupon_image_url,
            'coupon_code' => $request->coupon_code,
            'status'=>$request->status,
            'amount' => $request->amount,
            'start_date' => $request->start_date,
            'expire_date' => $request->expire_date,
            'cart_min_amount' => $request->cart_min_amount,
        ];

        $updatedRows = DB::table('coupon')
            ->where('coupon_id', $request->coupon_id)
            ->update($updated_coupon);

        if ($updatedRows) {

            return response()->json([
                'status' => true,
                'message' => 'Coupon Updated Successfully.',
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Failed to Updated Coupon.',
            ]);
        }

    }

    public function ads_subscription(Request $request){

        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $data = DB::table('ads')->get();

        if($data->isNotEmpty()){

            return response()->json([
                'status'=>true,
                'data'=>$data
            ]);
        }
        else{
            return response()->json([
                'status'=>true,
                'message'=>'No Advertise Subscription Data Found!'
            ]);
        }
    }

    public function get_idwise_all_reviews(Request $request){

        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [

            'product_unique_id' => 'required',


        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);

        }
        $productData=DB::table('products')->where('product_unique_id',$request->product_unique_id)->first();

        $productID = $productData->product_id;

        // $productID = $request->product_id;

        if (!$productID) {
            return response()->json([
                'status' => false,
                'message' => 'Product ID not found.',
            ]);
        }
        $allReviews=DB::table('product_reviews')
        ->where('product_id', $productID)
        ->where('status', 'Active')
        ->get();
        if($allReviews->isEmpty()){
            return response()->json([
                'status' => false,
                'message' => 'No reviews found for this product.',
            ]);
        }

        return response()->json([
            'status' => true,
            'allReviews' => $allReviews,
        ]);
    }

    public function add_about(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $addedAbout = [];
        $about = $request->about;

        foreach ($about as $data) {
            DB::table('about')->insert([
                'about' => $data['image'] ?? null,
                'title' => $data['title'] ?? null,
                'heading' => $data['heading'] ?? null,
                'sub_heading' => $data['sub_heading'] ?? null,
                'para' => $data['para'] ?? null,
                'status' =>'Active'
            ]);

            $addedAbout[] = $data;
        }

        return response()->json([
            'status' => 'true',
            'about' => $addedAbout
        ]);
    }

    public function update_about(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $aboutId = $request->about_id;


        if (!$aboutId) {
            return response()->json([
                'status' => false,
                'message' => 'About Id not found'
            ]);
        }

        $updated = DB::table('about')
            ->where('about_id', $aboutId)
            ->update([
                'about' => $request->image,
                'title' => $request->title,
                'heading' => $request->heading,
                'sub_heading' => $request->sub_heading,
                'para' => $request->para,
                'status' => $request->status,
            ]);

        if ($updated) {
            return response()->json([
                'status' => true,
                'message' => 'About information updated successfully'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Update failed, about not found or no changes made'
            ]);
        }
    }
   }
