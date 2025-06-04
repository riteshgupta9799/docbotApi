<?php

namespace App\Http\Controllers;

use Laravel\Socialite\Facades\Socialite;
use App\Models\AddressDetail;
use App\Models\AgentLead;
use App\Models\City;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerLastLoginDetail;
use App\Models\CustomerLastPasswordDetail;
use App\Models\Customers;
use App\Models\OpenOtp;
use App\Models\State;
use App\Models\User;
use App\Models\UserLastPasswordDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use GeoIp2\Database\Reader;
use Validator;
use Illuminate\Support\Facades\DB;
// use Stevebauman\Location\Facades\Location;
use Tymon\JWTAuth\Facades\JWTAuth;
use Stevebauman\Location\Facades\Location;
use Illuminate\Support\Facades\Http;

class RegisterController extends Controller
{

    private function encryptData($data)
    {
        $encrypt = true;
        if ($encrypt) {
            $new = rand(00000000, 99999999);
            $new2 = rand(000000000, 999999999);

            $key = base64_encode($new . env('APP_KEY') . $new2);

            $jsonData = json_encode($data);
            $encryptedData = base64_encode($jsonData . $key);
            // return $encryptedData;
            return [
                'status' => true,
                'data' => $encryptedData,
                'cm' => $key
            ];
        } else {
            return [
                'status' => true,
                'data' => $data,
            ];
        }
    }


    private function generateUniqueId()
    {
        do {
            $uniqueId = mt_rand(1000000000, 9999999999);
        } while (Customer::where('customer_unique_id', $uniqueId)->exists());

        return $uniqueId;
    }

    public function register_customer(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'password' => 'required',
            'email' => 'required|email|unique:customers,email',
            'mobile' => 'required',
            'role' => 'required',
            'ip' => 'nullable',
        ]);


        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }
        $ip = $request->input('ip', $request->ip());
        $insertDate = null;
        $insertTime = null;
        if ($ip) {
            $response = Http::get("http://ip-api.com/json/{$ip}");

            if ($response->successful()) {
                $location = $response->json();

                if ($location && isset($location['lat']) && isset($location['lon'])) {
                    $latitude = $location['lat'];
                    $longitude = $location['lon'];
                    $timezone = $location['timezone'] ?? 'Asia/Kolkata';
                }
            }

            $currentDateTime = Carbon::now($timezone);
            $insertDate = $currentDateTime->toDateString();
            $insertTime = $currentDateTime->toTimeString();
        } else {
            $currentDateTime = Carbon::now('Asia/Kolkata');
            $insertDate = $currentDateTime->toDateString();
            $insertTime = $currentDateTime->toTimeString();
        }
        // Add 14 days to the current date

        $newDate = $currentDateTime->addDays(14)->toDateString();

        $existing = DB::table('customers')
                    ->where('mobile',$request->mobile)
                    ->where('email',$request->email)
                    ->first();

               if($existing){
                return response()->josn([
                    'status'=>false,
                    'message'=>'Mobile Number Alredy Exists'
                ]);
               }
        $existingEmail = DB::table('customers')
                    ->where('email',$request->email)
                    ->where('status','Inactive')
                    ->first();

               if($existingEmail){
                return response()->josn([
                    'status'=>false,
                    'message' => 'This email is restricted from creating an account.'
                ]);
               }
        $currentDateTime = Carbon::now('Asia/Kolkata');


        $commonData = [
          'name' => ucfirst(strtolower($request->name)),
            'password' => Hash::make($request->password),
            'email' => $request->email,
            'artist_name' => $request->artist_name,
            'mobile' => $request->mobile,
            'role' => $request->role,
            'status' => 'Active',
            'fcm_token' => $request->fcm_token??null,
            'inserted_date' => $insertDate,
            'updated_date' => $newDate,
            'inserted_time' => $insertTime,

            'customer_unique_id' => $this->generateUniqueId(),
            'ip' => $ip,

            'latitude' => $latitude ?? 0.0,

            'longitude' => $longitude ?? 0.0,
            'timezone' => $timezone ?? '',
        ];

        try {

            $customer = Customer::create($commonData);


            $credentials = [
                'email' => $customer->email,
                'password' => $request->password,
            ];

            if (!$token = auth('customer_api')->attempt($credentials)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid credentials',
                ], 200);
            }


            if ($customer->status !== 'Active') {
                return response()->json([
                    'status' => false,
                    'message' => 'Your Account is Deactivated!',
                    'account' => 'Deactivated',
                ]);
            }


            $currentDateTime = now('Asia/Kolkata');
            CustomerLastLoginDetail::create([
                'customer_id' => $customer->customer_id,
                'inserted_date' => $currentDateTime->toDateString(),
                'inserted_time' => $currentDateTime->toTimeString(),
                'timestamp' => $currentDateTime->timestamp * 1000,
            ]);

            $customerResponse = $customer->toArray();
            $customerResponse['token'] = $token;

            return response()->json([
                'status' => true,
                'role' => $customer->role,
                'message' => 'Customer Registered Successfully',
                'customer' => $customerResponse,
            ]);
        } catch (\Exception $e) {

            \Log::error('Customer registration error: ' . $e->getMessage());


            return response()->json([
                'status' => false,
                'message' => 'An error occurred during registration: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function send_otp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contact' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'Contact field is required']);
        }

        $contact = $request->input('contact');

        $isEmail = filter_var($contact, FILTER_VALIDATE_EMAIL);
        $isMobile = preg_match('/^\+?[1-9]\d{1,14}$/', $contact);

        if (!$isEmail && !$isMobile) {
            return response()->json(['status' => false, 'message' => 'Invalid contact format']);
        }

        $otp = rand(1000, 9999);

        $contactData = OpenOtp::where('contact', $contact)->first();

        if ($contactData) {
            OpenOtp::where('contact', $contactData->contact)->update([
                'otp' => $otp,
            ]);
        } else {
            OpenOtp::create([
                'contact' => $contact,
                'otp' => $otp,
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'OTP generated successfully',
            'otp' => $otp,
        ]);
    }
    public function verify_otp(Request $request)
    {
        $request->validate([
            'contact' => 'required',
            'otp' => 'required|integer',
        ]);

        $contact = $request->contact;
        $otp = $request->otp;

        // Check OTP valid or not
        $otpData = OpenOtp::where('contact', $contact)->first();

        if (empty($otpData)) {
            return response()->json([
                'status' => false,
                'message' => 'No Record Found'
            ]);
        }

        if ($contact && $otpData->otp != $otp) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid OTP'
            ], 400);
        }

        return response()->json([
            'status' => true,
            'message' => 'OTP verified successfully'
        ]);
    }



    public function login_customer(Request $request)
    {

        $validated = $request->validate([
            'email' => 'required|email|exists:customers,email',
            'password' => 'required|string',
        ]);


        $credentials = [
            'email' => $validated['email'],
            'password' => $validated['password'],
        ];

        $user = auth('customer_api')->user();
        $user = Customer::where('email', $validated['email'])->first();
        $ip = $request->input('ip', $user->ip);
        $insertDate = null;
        $insertTime = null;
        if ($ip) {
            $response = Http::get("http://ip-api.com/json/{$ip}");

            if ($response->successful()) {
                $location = $response->json();

                if ($location && isset($location['lat']) && isset($location['lon'])) {
                    $latitude = $location['lat'];
                    $longitude = $location['lon'];
                    $timezone = $location['timezone'] ?? 'Asia/Kolkata';
                }
            }

            $currentDateTime = Carbon::now($timezone);
            $insertDate = $currentDateTime->toDateString();
            $insertTime = $currentDateTime->toTimeString();
        } else {
            $currentDateTime = Carbon::now('Asia/Kolkata');
            $insertDate = $currentDateTime->toDateString();
            $insertTime = $currentDateTime->toTimeString();
        }
        $data = Customer::where('customer_unique_id', $user->customer_unique_id)->update([
            'fcm_token' => $request->fcm_token,
            'latitude'=>$latitude??0.0,
            'longitude'=>$longitude??0.0,
        ]);

        if (!$user) {

            return response()->json([
                'status' => false,
                'message' => 'Invalid email address'
            ]);
        }

        if (!$token = auth('customer_api')->attempt($credentials)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid password'
            ]);
        }


        if ($user->status !== 'Active') {
            return response()->json([
                'status' => false,
                'message' => 'Your account has been deleted!',

            ]);
        }

        $user->customer_profile = url($user->customer_profile);
        $customer = $user->toArray();
        // $customer->customer_profile=url($customer->customer_profile);
        $customer['token'] = $token;

        $currentDateTime = now('Asia/Kolkata');
        CustomerLastLoginDetail::create([
            'customer_id' => $user->customer_id,
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
            'timestamp' => $currentDateTime->timestamp * 1000, // Milliseconds
        ]);

        // Return response with user data and token
        return response()->json([
            'status' => true,
            'role' => $user->role,
            'message' => 'Customer Found...',
            'customer' => $customer,

        ]);
    }

    // public function login_customer(Request $request)
    // {

    //     $validated = $request->validate([
    //         'email' => 'required|email',
    //         'password' => 'required|string',
    //     ]);


    //     $credentials = [
    //         'email' => $validated['email'],
    //         'password' => $validated['password'],
    //     ];
    //     $user = Customer::where('email', $validated['email'])->first();

    //     if (!$user) {

    //         return response()->json([
    //             'status'=>false,
    //             'message' => 'Invalid email address'
    //         ]);
    //     }

    //     if (!$token = auth('customer_api')->attempt($credentials)) {
    //         return response()->json([
    //             'status'=>false,
    //             'message' => 'Invalid password'
    //         ]);
    //     }

    //     $user = auth('customer_api')->user();

    //     if ($user->status !== 'Active') {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Your Account is Deactivated!',
    //             'account' => 'Deactivated',
    //         ]);
    //     }

    //     $currentDateTime = now('Asia/Kolkata');
    //     CustomerLastLoginDetail::create([
    //         'customer_id' => $user->customer_id,
    //         'inserted_date' => $currentDateTime->toDateString(),
    //         'inserted_time' => $currentDateTime->toTimeString(),
    //         'timestamp' => $currentDateTime->timestamp * 1000, // Milliseconds
    //     ]);

    //     $selectedUserData = $user->only(['customer_unique_id', 'customer_profile_image', 'full_name', 'mobile', 'email','school_university','student_identity','lead_count','student_id_file']);

    //     return response()->json([
    //         'status' => true,
    //         'role' => $user->role,
    //         'message' => 'Customer Found...',
    //         'customer' => $selectedUserData,
    //         'token' => $token,
    //     ]);
    // }




    // public function register_customer(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'full_name' => 'required',
    //         'password' => 'required',
    //         'email' => 'required|email',
    //         'mobile' => 'required',
    //         'role' => 'required',
    //         'house_no' => 'required',
    //         'address' => 'required',
    //         'country' => 'required',
    //         'state' => 'required',
    //         'zip_code' => 'required',
    //         'school_university' => 'required_if:student_entrepreneur,Yes',
    //         'student_identity' => 'required_if:student_entrepreneur,Yes',
    //         'student_id_file' => 'required_if:student_entrepreneur,Yes',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => $validator->errors()->first(),
    //         ], 400);
    //     }

    //     if (Customer::where('email', $request->email)->exists()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Email is already taken.',
    //         ], 409);
    //     }

    //     if (Customer::where('mobile', $request->mobile)->exists()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Mobile number is already taken.',
    //         ], 409);
    //     }

    //     $currentDateTime = Carbon::now('Asia/Kolkata');
    //     $insertDate = $currentDateTime->toDateString();
    //     $insertTime = $currentDateTime->toTimeString();

    //     $commonData = [
    //         'full_name' => $request->full_name,
    //         'password' => Hash::make($request->password),
    //         'role' => $request->role,
    //         'email' => $request->email,
    //         'mobile' => $request->mobile,
    //         'status' => 'Active',
    //         'inserted_date' => $insertDate,
    //         'inserted_time' => $insertTime,
    //         'unique_id' => $this->generateUniqueId(),
    //     ];

    //     if ($request->student_entrepreneur === 'Yes') {
    //         $commonData = array_merge($commonData, [
    //             'school_university' => $request->school_university,
    //             'student_identity' => $request->student_identity,
    //             'student_id_file' => $request->student_id_file,
    //         ]);
    //     }

    //     try {
    //         $customer = Customer::create($commonData);
    //         $customerId = $customer->customer_id;

    //         $addressData = [
    //             'customer_id' => $customerId,
    //             'address' => $request->house_no . ',' . $request->address,
    //             'country' => $request->country,
    //             'state' => $request->state,
    //             'zip_code' => $request->zip_code,
    //             'inserted_date' => $insertDate,
    //             'inserted_time' => $insertTime,
    //         ];
    //         AddressDetail::create($addressData);

    //         if ($customer->role == 'Agent') {
    //             $leads = [
    //                 'customer_id' => $customerId,
    //                 'client_name' => 'Client ',
    //                 'service' => 'Service ',
    //                 'description' => 'Description for lead ',
    //                 'status' => 'New',
    //                 'priority' => 'Normal',
    //                 'lead_count' => 5,
    //                 'expired_date' => now()->addDays(30),
    //                 'inserted_date' => $insertDate,
    //                 'inserted_time' => $insertTime,
    //             ];

    //             AgentLead::create($leads);

    //             $message = 'Customer registered successfully with 5 leads.';
    //         } else {
    //             $message = $request->student_entrepreneur === 'Yes'
    //                 ? 'Student Customer registered successfully.'
    //                 : 'Customer registered successfully.';
    //         }

    //         return response()->json([
    //             'status' => true,
    //             'message' => $message,
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'An error occurred: ' . $e->getMessage(),
    //         ], 500);
    //     }
    // }


    public function passwordsendotp(Request $request)
    {
        $email = $request->input('email');
        $mobile = $request->input('mobile');
        $type = $request->input('type');


        if ($type == 'user') {
            $model = User::class;
        } else if ($type == 'customer') {
            $model = Customer::class;
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Something Went Wrong'
            ]);
        }

        $finduser = $model::query();

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

        $otp = rand(1000, 9999);

        if ($type == 'user') {
            $modelOTP = User::class;
        } else if ($type == 'customer') {
            $modelOTP = Customer::class;
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Something Went Wrong'
            ]);
        }

        $finduserOTP = $modelOTP::query();

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
            $model = User::class;
        } else if ($type == 'customer') {
            $model = Customer::class;
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Something Went Wrong'
            ]);
        }

        $userfind = $model::query();

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
            $model = User::class;
        } else if ($type == 'customer') {
            $model = Customer::class;
        } else {
            return response()->json(['status' => false, 'message' => 'No valid method provided'], 400);
        }

        $lastpassuser = $model::query();

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
            UserLastPasswordDetail::create([
                'user_id' => $userId,
                'password' => $oldpassword,
                'inserted_date' => $insertDate,
                'inserted_time' => $insertTime,
                'timestamp' => Carbon::now()->timestamp * 1000,
            ]);
        } else if ($type == 'customer') {
            CustomerLastPasswordDetail::create([
                'customer_id' => $userId,
                'password' => $oldpassword,
                'inserted_date' => $insertDate,
                'inserted_time' => $insertTime,
                'timestamp' => Carbon::now()->timestamp * 1000,
            ]);
        } else {
            return response()->json(['status' => false, 'message' => 'No valid method provided'], 400);
        }

        if ($type == 'user') {
            $singleUserModel = User::class;
        } else if ($type == 'customer') {
            $singleUserModel = Customer::class;
        } else {
            return response()->json(['status' => false, 'message' => 'No valid method provided'], 400);
        }

        $singleUser = $singleUserModel::query();

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




    // public function getAllCountries()
    // {
    //     $allCountries = Country::select('*')
    //         ->whereNotIn('country_id', [704, 240])
    //         ->orderByRaw("
    //             CASE
    //                 WHEN country_id = '105' THEN 1 -- India
    //                 WHEN country_id = '48' THEN 2 -- China
    //                 ELSE 3 -- All other countries
    //             END
    //         ")
    //         ->orderBy('country_name', 'asc') // Alphabetical order for the rest
    //         ->get();

    //     // Prepend the virtual row at the beginning
    //     $allCountries->prepend([
    //         'country_id' => 704,
    //         'country_name' => 'United States',
    //         'zone' => null,
    //         'un_region' => 'America',
    //     ]);

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'All Countries Found Successfully',
    //         'allCountries' => $allCountries
    //     ]);
    // }

    // public function getAllCountries()
    // {
    //     $excludedCountryIds = State::pluck('country_id')->toArray();
    //     $allCountries = Country::select('*')
    //         ->whereNotIn('country_id', [236, 240])
    //         ->whereIn('country_id', $excludedCountryIds)
    //         ->orderByRaw("
    //             CASE
    //                 WHEN country_id = '235' THEN 1 -- United Kingdom
    //                 WHEN country_id = '102' THEN 2 -- India
    //                 ELSE 3 -- All other countries
    //             END
    //         ")
    //         ->orderBy('country_name', 'asc') // Alphabetical order for the rest
    //         ->get();

    //     // Prepend the virtual row at the beginning
    //     // $allCountries->prepend([
    //     //     'country_id' => 704,
    //     //     'country_name' => 'United States',
    //     //     'zone' => null,
    //     //     'un_region' => 'America',
    //     // ]);


    //     $allCountries->prepend([
    //         'country_id' => 236,
    //         'country_name' => 'United States',
    //         'zone' => null,
    //         'un_region' => 'America',
    //     ]);

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'All Countries Found Successfully',
    //         'allCountries' => $allCountries
    //     ]);
    // }


    // public function getState($country_id)
    // {
    //     // Check if the requested country_id is 704
    //     if ($country_id == 704) {
    //         // Fetch states where sub_region is 'America'
    //         $states = State::where('sub_region', 'America')
    //         ->whereNotNull('state_subdivision_name')
    //         ->orderBy('state_subdivision_name', 'ASC')
    //         ->get();
    //     } else {
    //         // Fetch states normally based on country_id
    //         $states = State::where('country_id', $country_id)
    //         ->whereNotNull('state_subdivision_name')
    //         ->orderBy('state_subdivision_name', 'ASC')
    //         ->get();
    //     }

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'All states Found Successfully',
    //         'states' => $states
    //     ]);
    // }



    // public function getState($country_id)
    // {
    //     // Get state_ids that have at least one associated city using a subquery
    //     $stateIdsWithCities = City::select('state_id')->distinct();

    //     if ($country_id == 236) {
    //         // Fetch states where sub_region is 'America' and exists in the cities table
    //         // $states = State::where('sub_region', 'America')
    //         //     ->whereNotNull('state_subdivision_name')
    //         //     ->whereIn('state_subdivision_id', $stateIdsWithCities) // Fixed: Uses a subquery
    //         //     ->orderBy('state_subdivision_name', 'ASC')
    //         //     ->get();

    //         $states = State::whereNotNull('state_subdivision_name')
    //             ->where('country_id', 236) // Fixed: Uses a subquery
    //             ->orderBy('state_subdivision_name', 'ASC')
    //             ->get();
    //     } else {
    //         // Fetch states normally based on country_id and exists in the cities table
    //         $states = State::where('country_id', $country_id)
    //             ->whereNotNull('state_subdivision_name')
    //             ->whereIn('state_subdivision_id', $stateIdsWithCities) // Fixed: Uses a subquery
    //             ->orderBy('state_subdivision_name', 'ASC')
    //             ->get();
    //     }

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'All states Found Successfully',
    //         'states' => $states
    //     ]);
    // }
    // public function getCity($state_subdivision_id)
    // {
    //     $states = City::where('state_id', $state_subdivision_id)
    //         ->whereNotNull('name_of_city')
    //         ->orderBy('name_of_city', 'ASC')
    //         ->get();
    //     return response()->json([
    //         'status' => true,
    //         'message' => 'All Cities Found Successfully',
    //         'cities' => $states
    //     ]);
    // }
    public function getCityOld($state_subdivision_id)
    {
        $states = DB::table('cities_old')->where('state_id', $state_subdivision_id)
            ->whereNotNull('name_of_city')
            ->orderBy('name_of_city', 'ASC')
            ->get();
        return response()->json([
            'status' => true,
            'message' => 'All Cities Found Successfully',
            'cities' => $states
        ]);
    }




  public function register_artist(Request $request)
  {


        // Current Date and Time
        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        // Loop through each artist data and create an entry
        try {
            foreach ($request->data as $value) {
                $customerData = [
                    'name' => $value['name'],
                    'email' => $value['email'],
                    'mobile' => $value['mobile'],
                    'role' => $value['role'],
                    //   'latitude' => $value['latitude'],
                    //   'longitude' => $value['longitude'],
                    'status' => 'Active',
                    'inserted_date' => $insertDate,
                    'inserted_time' => $insertTime,
                    'customer_unique_id' => $this->generateUniqueId(),
                    'password' => Hash::make($value['password']), // Or set a default password
                ];

                // Create customer record in the database
                Customer::create($customerData);
            }

            return response()->json([
                'status' => true,
                'message' => 'Customer(s) registered successfully!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function google_login()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function google_login_call_back()
    {
        try {
            // Check if the 'code' parameter is present
            $code = request()->get('code');
            if (!$code) {
                return response()->json(['error' => 'Missing authorization code'], 400);
            }

            // Attempt to exchange the code for an access token
            $accessToken = Socialite::driver('google')->stateless()->getAccessTokenResponse($code);

            // Log access token for debugging
            \Log::info('Google Access Token Response', $accessToken);

            // Retrieve the user details from Google
            $googleUser = Socialite::driver('google')->stateless()->userFromToken($accessToken['access_token']);

            // Log user details for debugging
            \Log::info('Google User Info', [
                'email' => $googleUser->getEmail(),
                'name' => $googleUser->getName(),
                'avatar' => $googleUser->getAvatar(),
            ]);

            // Find or create the user
            $user = User::firstOrCreate(
                ['email' => $googleUser->getEmail()],
                ['name' => $googleUser->getName(), 'customer_profile' => $googleUser->getAvatar()]
            );

            // Generate a JWT token
            $token = JWTAuth::fromUser($user);

            return response()->json([
                'token' => $token,
                'user' => $user
            ], 200);
        } catch (\Exception $e) {
            // Log the exception for better debugging
            \Log::error('Google Login Error', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Google login failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    // public function google_login_call_back()
    // {
    //      try {
    //         $googleUser = Socialite::driver('google')->user();

    //         $user = User::where('email', $googleUser->getEmail())->first();

    //         if (!$user) {
    //             $user = User::create([
    //                 'name' => $googleUser->getName(),
    //                 'email' => $googleUser->getEmail(),
    //                 'customer_profile' => $googleUser->getAvatar(),
    //             ]);
    //         }

    //         $token = JWTAuth::fromUser($user);

    //         return response()->json([
    //             'token' => $token,
    //             'user' => $user
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json(['error' => 'Google login failed', 'message' => $e->getMessage()], 500);
    //     }
    // }

    public function update_fcm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required',
            'fcm_token' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();

        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'No Customer Found'
            ]);
        }

        $update = Customer::where('customer_id', $customer->customer_id)->update([
            'fcm_token' => $request->fcm_token,
        ]);


        return response()->json([
            'status' => true,
            'message' => 'Token Updated Successfully'
        ]);
    }





public function getLocation(Request $request)
{
    // Get IP from request or use user's IP
    $ip = $request->input('ip', $request->ip());

    try {
        // Load MaxMind GeoIP2 database (Ensure the correct path)
        $reader = new Reader(storage_path('geoip/GeoLite2-City.mmdb'));

        // Get geo data for the IP
        $record = $reader->city($ip);

        return response()->json([
            'ip' => $ip,
            'country' => $record->country->name ?? 'Unknown',
            'region' => $record->mostSpecificSubdivision->name ?? 'Unknown',
            'city' => $record->city->name ?? 'Unknown',
            'latitude' => $record->location->latitude ?? null,
            'longitude' => $record->location->longitude ?? null,
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Unable to retrieve location: ' . $e->getMessage()], 500);
    }
}

public function getCountryName($country_id)
{


    $city = Country::where('country_id', $country_id)
        ->whereNotNull('country_name')
        ->value('country_name');
    return response()->json([
        'status' => true,
        'message' => 'Country found successfully',
        'country_name' => $city
    ]);
}

public function getStateName($state_subdivision_id)
{
    $state = State::where('state_subdivision_id', $state_subdivision_id)
        ->select('state_subdivision_name')
        ->first();

    if ($state) {
        return response()->json([
            'status' => true,
            'message' => 'State found successfully',
            'state_subdivision_name' => $state->state_subdivision_name
        ]);
    } else {
        return response()->json([
            'status' => false,
            'message' => 'State not found'
        ]);
    }
}

public function getCityName($cities_id)
{


    $cities = City::where('cities_id', $cities_id)
        ->whereNotNull('name_of_city')
        ->first();
        if ($cities) {
            return response()->json([
                'status' => true,
                'message' => 'City found successfully',
                'name_of_city' => $cities->name_of_city
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'City not found'
            ]);
        }

}

}
