<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class SellerRegisterController extends Controller
{
    //
    public function register_as_seller(Request $request)
    {
        date_default_timezone_set('Asia/Kolkata');

        // Define validation rules
        $rules = [
            'seller_info.firstName' => 'required|string|max:255',
            'seller_info.lastName' => 'required|string|max:255',
            'seller_info.email' => 'required|email|unique:users,email',
            'seller_info.mobile' => 'required|string|unique:users,mobile',

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

        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        $date=DB::table('ads')
        ->where('admin_subscription_id','1')
        ->select('subscription_period')
        ->first();
        $subscriptionPeriod = $date->subscription_period;
        $validUpto = $currentDateTime->copy()->addDays($subscriptionPeriod)->toDateString();





        $sellerData = [
            'name' => $sellerInfo['firstName'] . ' ' . $sellerInfo['lastName'] ?? '',
            'password' => Hash::make($sellerInfo['password'] ?? ''),
            'email' => $sellerInfo['email'] ?? '',
            'mobile' => $sellerInfo['mobile'] ?? '',
            'unique_id' => $uniqueId,
            'rating' => '0',
            'role' => $role,
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
            'valid_upto' => $validUpto,
            'permission' => '1',
            'views' => '0'
        ];


        $existingSellerEmail = DB::table('users')
            ->where('email', $sellerData['email'])
            // ->where('role', 'seller')
            ->first();

        if (!$existingSellerEmail) {

            $existingSellerMobile = DB::table('users')
                ->where('mobile', $sellerData['mobile'])
                // ->where('role', 'seller')
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
                        'mobile',
                        'status',
                        'locality',
                        'rating',
                        'gender',
                        'valid_upto'
                    )->get();
                $role = DB::table('users')->where('user_id', $userId)->select('role')->get();

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
                        'status' => 'Active',
                        'inserted_date' => $insertDate,  // Add current date
                        'inserted_time' => $insertTime,

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

                    $credentials = [
                        'email' =>$sellerInfo['email'],
                        'password' =>$sellerInfo['password'],
                    ];

                    if (!$token = auth('customer_api')->attempt($credentials)) {
                        return response()->json([
                            'status' => false,
                            'message' => 'Invalid credentials',
                        ], 200);
                    }
                    $user_data['token'] = $token;



                    return response()->json([
                        'status' => true,
                        'message' => 'Seller Added Successfully',
                        'user_data' => $user_data[0],
                        'role' => $role
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

    public function user_login(Request $request)
    {

        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);


        // $credentials = [
        //     'email' => $validated['email'],
        //     'password' => $validated['password'],
        // ];


        // if (!$token = auth('api')->attempt($credentials)) {
        //     return response()->json(['error' => 'Unauthorized'], 401);
        // }


        // $user = auth('api')->user();
        // if (!$user) {

        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Invalid email address'
        //     ]);
        // }

        // if (!$token = auth('customer_api')->attempt($credentials)) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Invalid password'
        //     ]);
        // }




        // if ($user->status !== 'Active') {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Your Account is Deactivated!',
        //         'account' => 'Deactivated',
        //     ]);
        // }
        // $user['token'] = $token;
        $credentials = [
            'email' => $validated['email'],
            'password' => $validated['password'],
        ];

        $user = auth('api')->user();
        $user = DB::table('users')->where('email', $validated['email'])->first();


        if (!$user) {

            return response()->json([
                'status' => false,
                'message' => 'Invalid email address'
            ]);
        }

        if (!$token = auth('api')->attempt($credentials)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid password'
            ]);
        }


        if ($user->status !== 'Active') {
            return response()->json([
                'status' => false,
                'message' => 'Your Account is Deactivated!',
                'account' => 'Deactivated',
            ]);
        }

        $user->user_profile = isset($user->user_profile) ? url($user->user_profile) : null;

        $user = (array) $user;
        $user['token'] = $token;

        $currentDateTime = now('Asia/Kolkata');

        // Log last login details
        DB::table('user_last_login_details')->insert([
            'user_id' => $user['user_id'], // Use array access here
            'inserted_date' => $currentDateTime->toDateString(),
            'inserted_time' => $currentDateTime->toTimeString(),
            'timestamp' => now()->timestamp * 1000,
        ]);

        return response()->json([
            'status' => true,
            'role' => $user['role'], // Use array access here
            'message' => 'User Found...',
            'user_data' => $user,
        ]);
    }



    public function user_data(Request $request)
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
        $user = Auth::guard('api')->user();

        $user_unique_id = $request->user_unique_id;

        if ($user->user_unique_id !== $user_unique_id) {
            return response()->json([
                'status' => false,
                'message' => 'unique ID does not match.',
            ], 400);
        }
        $users=DB::table('users')
        ->where('user_unique_id',$user_unique_id)
        ->first();
        $user_id = $users->user_id;

        // $user_id = $request->user_id;

        $userdata = DB::table('users')
            ->select(
                'users.name',
                'users.gender',
                'users.email',
                'users.mobile',
                'users.address',
                'users.country',
                'users.city',
                'users.state',
                'users.locality',
                'countries.country_name',
                'states.state_subdivision_name',
                'users.pin_code',
                'users.status',
                'users.user_unique_id',
                'users.user_image',
                'users.role'
            )
            ->leftJoin('countries', 'countries.country_id', '=', 'users.country')
            ->leftJoin('states', 'states.state_subdivision_id', '=', 'users.state')
            ->where('users.user_id', $user_id)
            ->get();

        $product_data = null;

        if ($userdata[0]->role == 'supplier' || $userdata[0]->role == 'supplier-seller' || $userdata[0]->role == 'supervisor') {

            $products_data = DB::table('products')->where('user_id', $user_id)->get();

            $product_data = $products_data->count() > 0 ? $products_data : null;
        }

        if($userdata[0]->role =='manager'){
            $assignedArea = DB::table('state_to_manager')
            ->where('user_id',$user_id)
            ->leftjoin('states','state_to_manager.state_subdivision_id','=','states.state_subdivision_id')
            ->leftjoin('countries', 'states.country_id', '=', 'countries.country_id')
            ->select('states.state_subdivision_id','countries.country_id','state_to_manager.state_to_manager_id')
            ->get();
        }

        $bankdata = DB::table('bank_details')
            ->select('*')
            ->where('bank_details.user_id', $user_id)
            ->first();

        if (is_null($userdata)) {
            return response()->json([
                'status' => false,
                'message' => 'No data found'
            ]);
        } else {
            if($userdata[0]->role =='manager'){

                return response()->json([
                    'status' => true,
                    'data' => $userdata,
                    'bankdetails' => $bankdata,
                    'products' => $product_data,
                    'assignedArea' => $assignedArea,
                ]);
            }else{
                return response()->json([
                    'status' => true,
                    'data' => $userdata,
                    'bankdetails' => $bankdata,
                    'products' => $product_data,
                    // 'assignedArea' => $assignedArea,
                ]);
            }
        }
    }

    public function mobile_otp_seller(Request $request)
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

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);
        }


        $seller = DB::table('users')
            ->where('mobile', $request->input('mobile'))
            ->where('role', 'seller')
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

    public function save_password(Request $request)
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
        $password = $request->input('password');

        // Hash the new password
        $hashedPassword = Hash::make($password);

        // Update the password
        $updated = DB::table('users')
            ->where('user_id', $userId)
            ->update(['password' => $hashedPassword]);

        if ($updated) {
            $response = [
                'status' => true,
                'message' => 'Password Updated Successfully ...'
            ];
        } else {
            $response = [
                'status' => false,
                'message' => 'No changes, password is same as prior.'
            ];
        }

        // Send response as JSON
        return response()->json($response);
    }
    public function updateProfile(Request $request)
    {

        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        // $validator = Validator::make($request->all(), [
        //     // 'customer_id' => 'required',
        //     'user_unique_id' => 'required',
        //     'password' => 'required',

        // ]);

        // if ($validator->fails()) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => $validator->errors()->first()
        //     ]);
        // }

        // $user = Auth::guard('api')->user();

        // $user_unique_id = $request->user_unique_id;
        // // $customer_unique_id = $request->unique_id;

        // if ($user->user_unique_id !== $user_unique_id) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Customer unique ID does not match.',
        //     ], 400);
        // }
        $data = $request->json()->all();

        $userInfo = $data['user_info'] ?? [];


        $user = DB::table('users')->where('user_id', $userInfo['user_id'])->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Prepare user update data
        $updateData = [
            'name' => ($userInfo['firstName']) . ' ' . ($userInfo['lastName']) ?? $user->name,
            'gender' => $userInfo['gender'] ?? $user->gender,
            'email' => $userInfo['email'] ?? $user->email,
            'mobile' => $userInfo['mobile'] ?? $user->mobile,
            'address' => $userInfo['address'] ?? $user->address,
            'country' => $userInfo['country'] ?? $user->country,
            'state' => $userInfo['state'] ?? $user->state,
            'city' => $userInfo['city'] ?? $user->city,
            'locality' => $userInfo['locality'] ?? $user->locality,
        ];


        DB::table('users')->where('user_id', $userInfo['user_id'])->update($updateData);

        return response()->json([
            'status' => true,
            'message' => 'Profile updated successfully'
        ]);
    }

    public function getStoreInfo(Request $request)
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

        $userId = $userData->user_id;

        // $userId= $request->user_id;
        $user_storeInfo= DB::table('store_info')
        ->where('user_id',$userId)
        ->where('status','Active')
        ->get();

        $result = $user_storeInfo->toArray();
        if(!empty($result)){
            return response()->json([
                'status'=>true,
                'message'=>'Store Info Found!',
                'store_info' =>$result
            ]);
        }
        else{
            return response()->json([
                'status'=>false,
                'message'=>'Store Info Not Found!',

            ]);
        }
    }

    public function add_store_details(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'storeName' => 'required|string|max:255',
            'storeCountry' => 'required|integer|exists:countries,country_id',
            'storeState' => 'required|integer|exists:states,state_subdivision_id',
            'storeCity' => 'required|string|max:255',
            'storeAppartment' => 'nullable|string|max:255',
            'storeAddress' => 'required|string|max:500',
            'storePostalCode' => 'required|string|max:20',
            'user_unique_id' => 'required',
            'business_id' => 'required|integer|exists:business_info,business_id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation Failed',
                'errors' => $validator->errors()
            ], 422);
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
        $users=DB::table('users')->where('user_unique_id',$request->user_unique_id)->first();
        date_default_timezone_set('Asia/Kolkata');
        $currentDateTime = now();
        $insertDate = $currentDateTime->toDateString(); // Current date
        $insertTime = $currentDateTime->toTimeString(); // Current time
            $storeData = [
                        'store_name' => $request->storeName ?? '',
                        'country_id' => $request->storeCountry ?? '',
                        'state_subdivision_id' => $request->storeState ?? '',
                        'city' => $request->storeCity ?? '',
                        'address1' => $request->storeAppartment ?? '',
                        'address2' => $request->storeAddress ?? '',
                        'postal_code' => $request->storePostalCode ?? '',
                        'user_id' => $users->user_id,
                        'inserted_date' => $insertDate,  // Add current date
                        'inserted_time' => $insertTime,
                        'business_id' => $request->business_id,
                        'status'=>'Active'
                    ];
                    DB::table('store_info')->insert($storeData);

        return response()->json([
            'status' => true,
            'message' => 'Store Added Successfully',
            // 'data' => $insertedData

        ]);

    }
    public function update_store_details(Request $request)
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
            'store_id' => 'required|integer',
            'storeName' => 'sometimes|string',
            'storeCountry' => 'sometimes|string',
            'storeState' => 'sometimes|string',
            'storeCity' => 'sometimes|string',
            'storeAppartment' => 'sometimes|string',
            'storeAddress' => 'sometimes|string',
            'storePostalCode' => 'sometimes|string',


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
        // $request->validate([
        //     'store_id' => 'required|integer',
        //     'storeName' => 'sometimes|string',
        //     'storeCountry' => 'sometimes|string',
        //     'storeState' => 'sometimes|string',
        //     'storeCity' => 'sometimes|string',
        //     'storeAppartment' => 'sometimes|string',
        //     'storeAddress' => 'sometimes|string',
        //     'storePostalCode' => 'sometimes|string',
        //     'user_id' => 'required|integer'
        // ]);


        date_default_timezone_set('Asia/Kolkata');
        $currentDateTime = now();
        $updateDate = $currentDateTime->toDateString();
        $updateTime = $currentDateTime->toTimeString();

        $storeData = [
            'store_name' => $request->storeName ?? '',
            'country_id' => $request->storeCountry ?? '',
            'state_subdivision_id' => $request->storeState ?? '',
            'city' => $request->storeCity ?? '',
            'address1' => $request->storeAppartment ?? '',
            'address2' => $request->storeAddress ?? '',
            'postal_code' => $request->storePostalCode ?? '',
            'updated_date' => $updateDate,
            'updated_time' => $updateTime
        ];

        $updatedRows = DB::table('store_info')
            ->where('store_id', $request->store_id)
            ->update($storeData);

        if ($updatedRows) {
            return response()->json([
                'status' => true,
                'message' => 'Store Updated Successfully'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Failed to Update Store or Store Not Found'
            ]);
        }
    }





}
