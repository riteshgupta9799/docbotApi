<?php

namespace App\Http\Controllers;

use App\Models\Paitents;
use Laravel\Socialite\Facades\Socialite;

use Carbon\Carbon;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use GeoIp2\Database\Reader;
use Illuminate\Routing\Controller;

// use Stevebauman\Location\Facades\Location;
use Tymon\JWTAuth\Facades\JWTAuth;
use Stevebauman\Location\Facades\Location;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Services\TwilioService;

use App\Models\TestQueue;
use App\Models\TestToQueue;
use App\Models\PatientReport;

use App\Models\Patient;
use App\Models\PatintReport;


class PaitentController extends Controller
{
    public function send_otp(Request $request,TwilioService $twilio)
    {


        $validator = Validator::make($request->all(), [

            "mobile" => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }


        $mobile = $request->mobile;
        $otp = rand(1000, 9999);
        // dd($email);
        try {
            // Send OTP using Twilio WhatsApp Template
            $twilio->sendOtpUsingSmsTemplate($mobile, $otp);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to send OTP: ' . $e->getMessage()
            ], 500);
        }

        if ($mobile) {
            $paitent = DB::table('paitents')
                ->where('paitent_mobile', $mobile)
                ->first();

            if ($paitent) {
                //  $twilio->sendOtpUsingTemplate($mobile,  $otp);


                DB::table('paitents')
                    ->where('paitent_mobile', $mobile)
                    ->update([
                        "mobile_otp" => $otp
                    ]);
                return response()->json([
                    'status' => true,
                    'message' => 'OTP sent successfully.',
                    'otp' => $otp,
                    'existingPaitent' => true
                ]);
            } else {
                DB::table('open_otp')->updateOrInsert(
                    ['mobile' => $mobile],
                    ['otp' => $otp]
                );
                return response()->json([
                    'status' => true,
                    'message' => 'OTP sent successfully.',
                    'otp' => $otp,
                    'existingPaitent' => false
                ]);
            }
        }
    }

    public function verify_otp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "mobile" => 'nullable',
            "otp" => 'required',
            "existingPaitent" => 'required',
            "customer_unique_id" => 'required'
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
                'message' => 'No Customer Found!'
            ]);
        }


        $mobile = $request->mobile;
        $otp = $request->otp;

        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        if ($request->existingPaitent == true) {
            $paitent = DB::table('paitents')
                ->where('paitent_mobile', $mobile)
                ->where('mobile_otp', $otp)
                ->first();

            if ($paitent) {

                $paitents = \App\Models\Paitents::find($paitent->paitent_id); // ✅ Corrected

                $token = JWTAuth::fromUser($paitents);
                

                    $last_machine_patient = DB::table('last_machine_patient')->insertGetId([
                        'machine_id'        => $customer->machine_id,
                        'inserted_date'     => $insertDate,
                        'inserted_time'     => $insertTime,
                        'patient_id'        => $paitent->paitent_id
                    ]);


                return response()->json([
                    'status' => true,
                    'message' => 'Mobile OTP verified successfully.',
                    'paitent' => array_merge(
                        $paitents->only([
                            'paitent_id',
                            'paitent_name',
                            'paitent_email',
                            'paitent_mobile',
                            'gender',
                            'dob',
                            'address',
                            'inserted_date',
                            'inserted_time',
                        ]),

                        ['token' => $token]

                    ),

                ]);
            }
        }

        if($request->existingPaitent == false){
            // Fallback: check in open_otp
            $open = DB::table('open_otp')
                ->where('mobile', $mobile)
                ->where('otp', $otp)
                ->first();

            if ($open) {
                $last_machine_patient = DB::table('last_machine_patient')->insertGetId([
                        'machine_id'        => $customer->machine_id,
                        'inserted_date'     => $insertDate,
                        'inserted_time'     => $insertTime,
                        'patient_id'        => $paitent->paitent_id
                    ]);

                return response()->json([
                    'status' => true,
                    'message' => 'Mobile OTP verified.'
                ]);
            }
        }

        return response()->json([
            'status' => false,
            'message' => 'Invalid OTP or no matching record found.',
        ], 400);
    }

    public function register_paitent_(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'paitent_name' => 'required',
            'paitent_mobile' => 'required|unique:paitents,paitent_mobile',
            'paitent_email' => 'required|email|unique:paitents,paitent_email',
            'gender' => 'required',
            'dob' => 'required|date',
            'address' => 'nullable',
            'customer_unique_id' => 'required',
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
                'message' => 'Invalid or customer unique id.',
            ], 401);
        }


        // Set current time in Asia/Kolkata timezone
        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        // Check if mobile already exists
        $existing = DB::table('paitents')
            ->where('paitent_mobile', $request->paitent_mobile)
            ->first();

        if ($existing) {
            return response()->json([
                'status' => false,
                'message' => 'Mobile number already exists.'
            ], 409);
        }

        // Optional: Check if email is blocked in another table
        $existingEmail = DB::table('paitents')
            ->where('paitent_email', $request->paitent_email)
            ->first();

        if ($existingEmail) {
            return response()->json([
                'status' => false,
                'message' => 'This email is restricted from creating an account.'
            ], 403);
        }

        // Prepare data
        $commonData = [
            'paitent_name'   => ucfirst(strtolower($request->paitent_name)),
            'paitent_email'  => $request->paitent_email,
            'paitent_mobile' => $request->paitent_mobile,
            'gender'         => $request->gender,
            'dob'            => $request->dob,
            'address'        => $request->address,
            'inserted_date'  => $insertDate,
            'inserted_time'  => $insertTime,
            'customer_id'    => $customer->customer_id,
        ];

        try {

            $paitentId = DB::table('paitents')->insertGetId($commonData);
            // $paitent = DB::table('paitents')->where('paitent_id', $paitentId)->first();

            // Generate auth token (requires password-based auth; adjust if you're not storing passwords)
            // $credentials = [
            //     'paitent_email' => $request->paitent_email,
            //     // 'password' => $request->password, // Uncomment and use if password is stored
            // ];
            $paitent = \App\Models\Paitents::find($paitentId); // ✅ Corrected

            $token = JWTAuth::fromUser($paitent); // ✅ Will now work

            // if (!$token = auth('paitent_api')->attempt($credentials)) {
            //     return response()->json([
            //         'status' => false,
            //         'message' => 'Invalid credentials',
            //     ], 401);
            // }

            return response()->json([
                'status'  => true,
                'message' => 'Patient registered successfully',
                'paitent' => array_merge(
                    $paitent->only([
                        'paitent_id',
                        'paitent_name',
                        'paitent_email',
                        'paitent_mobile',
                        'gender',
                        'dob',
                        'address',
                        'inserted_date',
                        'inserted_time',
                    ]),
                    ['token' => $token]
                ),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function register_paitent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'paitent_name' => 'required',
            'paitent_mobile' => 'required|unique:paitents,paitent_mobile',
            'paitent_email' => 'required|email|unique:paitents,paitent_email',
            'gender' => 'required',
            'dob' => 'required|date',
            'address' => 'nullable',
            'customer_unique_id' => 'required',
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
                'message' => 'Invalid or missing customer unique ID.',
            ], 401);
        }

        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        // Generate unique patient ID (e.g., 6-character random + timestamp)
        $patient_unique_id = strtoupper(Str::random(6)) . '@' . now()->timestamp;

        // Calculate age
        $dob = Carbon::parse($request->dob);
        $age = $dob->diffInYears(Carbon::now());


        // echo $patient_unique_id.$age;die;

        try {
            $paitentId = DB::table('paitents')->insertGetId([
                'paitent_unique_id' => $patient_unique_id,
                'paitent_name'      => ucfirst(strtolower($request->paitent_name)),
                'paitent_email'     => $request->paitent_email,
                'paitent_mobile'    => $request->paitent_mobile,
                'gender'            => $request->gender,
                'dob'               => $request->dob,
                'address'           => $request->address,
                'age'               => $age,
                'inserted_date'     => $insertDate,
                'inserted_time'     => $insertTime,
                'customer_id'       => $customer->customer_id,
            ]);

            $paitent = \App\Models\Paitents::find($paitentId);
            $token = JWTAuth::fromUser($paitent);

            return response()->json([
                'status'  => true,
                'message' => 'Patient registered successfully',
                'paitent' => array_merge(
                    $paitent->only([
                        'paitent_id',
                        'paitent_unique_id',
                        'paitent_name',
                        'paitent_email',
                        'paitent_mobile',
                        'gender',
                        'dob',
                        'age',
                        'address',
                        'inserted_date',
                        'inserted_time',
                        'customer_id'
                    ]),
                    ['token' => $token]
                ),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }


    public function paitentData(Request $request){

        if (!Auth::guard('paitent_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
         $validator = Validator::make($request->all(), [
           'paitent_unique_id'=>'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $paitent= DB::table('paitents')
                    ->where('paitent_unique_id',$request->paitent_unique_id)
                    ->first();

             if($paitent){
                return response()->json([
                    'status'=>true,
                    'message'=>'Paitent Found',
                    'paitent'=>$paitent
                ]);
             }
                return response()->json([
                    'status'=>false,
                    'message'=>'Paitent  not Found',

                ]);
    }


    public function add_test_queue(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "customer_unique_id" => 'required',
            "paitent_unique_id" => 'required',
            "tests" => 'required|array|min:1',
            "tests.*.test_name" => 'required|string',
            "tests.*.test_key" => 'required|string',
            "tests.*.test_value" => 'required|string',
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
                'message' => 'No Customer Found!',
            ]);
        }


        $patient = Paitents::where('paitent_unique_id', $request->paitent_unique_id)->first();
        if (!$patient) {
            return response()->json([
                'status' => false,
                'message' => 'No Patient Found!',
            ]);
        }

        // Insert into test_queue
        $testQueue = new TestQueue();
        $testQueue->machine_id = $customer->machine_id;
        $testQueue->patient_id = $patient->paitent_id;
        $testQueue->inserted_time = now()->format('H:i:s');
        $testQueue->inserted_date = now()->format('Y-m-d');

        // Prevent Laravel from auto-adding timestamps
        $testQueue->timestamps = false;
        $testQueue->save();

        $queue_id = $testQueue->id;

        // Insert each test into test_to_queue
        foreach ($request->tests as $test) {
            $testToQueue = new TestToQueue();
            $testToQueue->queue_id = $queue_id;
            $testToQueue->test_name = $test['test_name'];
            $testToQueue->test_key = $test['test_key'];
            $testToQueue->test_value = $test['test_value'];
            $testToQueue->inserted_time = now()->format('H:i:s');
            $testToQueue->inserted_date = now()->format('Y-m-d');

            $testToQueue->timestamps = false;
            $testToQueue->save();
        }

        return response()->json([
            'status' => true,
            'message' => 'Tests inserted successfully.',
            'queue_id' => $queue_id,
        ]);
    }


    public function last_report_machine_patient(Request $request)
    {
            $validator = Validator::make($request->all(), [
                'customer_unique_id' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first()
                ], 400);
            }

            // Fetch latest patient_id (replace logic based on your DB)
            $latestPatientReport = PatientReport::where('patient_id', $request->customer_unique_id)
                ->orderBy('inserted_date', 'desc')
                ->orderBy('inserted_time', 'desc')
                ->first();

            if (!$latestPatientReport) {
                return response()->json([
                    'status' => false,
                    'message' => 'No patient report found.'
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Latest patient report fetched.',
                'data' => $latestPatientReport
            ]);
        }


    public function get_patient_details(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "paitent_unique_id" => 'required'
        ]);

        if ($validator->fails   ()) {
            return response()->json(
                [
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ]
            );
        }

        $patient = Patient::where('paitent_unique_id', $request->paitent_unique_id)->first();

        if (!$patient) {
            return response()->json(

                [
                    'status' => false,
                    'message' => 'Patient not found.',
                ]
            );
        }

        return response()->json(
            [
                'status' => true,
                'message' => 'Patient details fetched.',
                'data' => $patient,
            ]
        );
    }

    
    public function get_patient_Testdetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "paitent_unique_id" => 'required',
            'customer_unique_id' => 'required'
        ]);

        if ($validator->fails   ()) {
            return response()->json(
                [
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ]
            );
        }

        $patient = Patient::where('paitent_unique_id', $request->paitent_unique_id)->first();

        if (!$patient) {
            return response()->json(

                [
                    'status' => false,
                    'message' => 'Patient not found.',
                ]
            );
        }

        $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();

        if (!$customer) {
            return response()->json(

                [
                    'status' => false,
                    'message' => 'Invalid Customer ID.',
                ]
            );
        }

        $machine_id = $customer->machine_id;

        
        $reports = PatientReport::where('paitent_id', $patient->paitent_id)
            ->orderByDesc('inserted_date')
            ->orderByDesc('inserted_time')
            ->limit(3)
            ->get()
            ->map(function ($report) use ($machine_id) {
                return [
                    'report_id' => $report->report_id,
                    'machine_id' => $report->machine_id,
                    'patient_id' => $report->paitent_id,
                    'inserted_time' => $report->inserted_time,
                    'inserted_date' => $report->inserted_date,
                    'result_key' => $report->result_key,
                    'result_value' => $report->result_value,
                    'test_name' => $report->test_name,
                    'que_id' => $report->que_id,
                    'result_array' => json_decode($report->result_array, true),
                    'color' => '#00bcd4',
                    'this_machine' => $report->machine_id == $machine_id ? 1 : 0
                ];
            });

        return response()->json([
            'status' => true,
            'message' => 'Patient details and reports fetched.',
            'data' => [
                'patient' => $patient,
                'reports' => $reports
            ]
        ]);
        
    }


}
