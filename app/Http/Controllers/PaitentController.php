<?php

namespace App\Http\Controllers;

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

class PaitentController extends Controller
{
    public function send_otp(Request $request)
    {


        $validator = Validator::make($request->all(), [
            "email" => 'nullable|email',
            "mobile" => 'nullable'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $email = $request->email;
        $mobile = $request->mobile;
        $otp = rand(1000, 9999);
        // dd($email);
        if ($email) {
            $patient = DB::table('patient')
                ->where('patient_email', $email)
                ->first();

            if ($patient) {
                DB::table('patient')
                    ->where('patient_email', $email)
                    ->update([
                        "email_otp" => $otp
                    ]);
            } else {
                DB::table('open_otp')->updateOrInsert(
                    ['email' => $email],
                    ['otp' => $otp]
                );

                // DB::table('open_otp')->insert([
                //     'email'=>$email,
                //     'otp'=>$otp,
                // ]);
            }
        }

        if ($mobile) {
            $patient = DB::table('patient')
                ->where('patient_mobile', $mobile)
                ->first();

            if ($patient) {
                DB::table('patient')
                    ->where('patient_mobile', $mobile)
                    ->update([
                        "mobile_otp" => $otp
                    ]);
            } else {
                DB::table('open_otp')->updateOrInsert(
                    ['mobile' => $mobile],
                    ['otp' => $otp]
                );
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'OTP sent successfully.',
            'otp' => $otp // Optional: for testing or dev purposes
        ]);
    }

    public function verify_otp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "email" => 'nullable|email',
            "mobile" => 'nullable',
            "otp" => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $email = $request->email;
        $mobile = $request->mobile;
        $otp = $request->otp;

        if ($email) {
            // Check in patient table
            $patient = DB::table('patient')
                ->where('patient_email', $email)
                ->where('email_otp', $otp)
                ->first();

            if ($patient) {
                return response()->json([
                    'status' => true,
                    'message' => 'Email OTP verified successfully.',
                    'Existingpaitent'=>true,


                ]);
            }

            // Fallback: check in open_otp
            $open = DB::table('open_otp')
                ->where('email', $email)
                ->where('otp', $otp)
                ->first();

            if ($open) {
                return response()->json([
                    'status' => true,
                    'message' => 'Email OTP verified (open_otp).',
                    'paitent'=>false


                ]);
            }
        }

        if ($mobile) {
            // Check in patient table
            $patient = DB::table('patient')
                ->where('patient_mobile', $mobile)
                ->where('mobile_otp', $otp)
                ->first();

            if ($patient) {
                return response()->json([
                    'status' => true,
                    'message' => 'Mobile OTP verified successfully.',
                    'paitent'=>true

                ]);
            }

            // Fallback: check in open_otp
            $open = DB::table('open_otp')
                ->where('mobile', $mobile)
                ->where('otp', $otp)
                ->first();

            if ($open) {
                return response()->json([
                    'status' => true,
                    'message' => 'Mobile OTP verified (open_otp).',
                    'paitent'=>false

                ]);
            }
        }

        return response()->json([
            'status' => false,
            'message' => 'Invalid OTP or no matching record found.',
        ], 400);
    }
     public function register_paitent(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'patient_name' => 'required',
            'patient_mobile' => 'required',
            'patient_email' => 'required|email|unique:paitent,patient_email',
            'gender' => 'required',
            'dob' => 'required',
            'address' => 'nullable',
        ]);


        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

            $currentDateTime = Carbon::now('Asia/Kolkata');
            $insertDate = $currentDateTime->toDateString();
            $insertTime = $currentDateTime->toTimeString();

        // Add 14 days to the current date


        $existing = DB::table('paitent')
                    ->where('patient_mobile',$request->patient_mobile)
                    ->first();

               if($existing){
                return response()->josn([
                    'status'=>false,
                    'message'=>'Mobile Number Alredy Exists'
                ]);
               }
        $existingEmail = DB::table('customers')
                     ->where('patient_email',$request->patient_email)
                    ->first();

               if($existingEmail){
                return response()->josn([
                    'status'=>false,
                    'message' => 'This email is restricted from creating an account.'
                ]);
               }
        $currentDateTime = Carbon::now('Asia/Kolkata');


        $commonData = [
            'patient_name' => ucfirst(strtolower($request->name)),
            'patient_email' => $request->patient_email,
            'patient_mobile' => $request->patient_mobile,
            'dob' => $request->dob,

            'inserted_date' => $insertDate,

            'inserted_time' => $insertTime,

            'address'=>$request->address
        ];

        try {

           $patientId = DB::table('paitent')->insertGetId($commonData);
            $patient = DB::table('paitent')->where('paitent_id', $patientId)->first();


            $credentials = [
                'email' => $request->patient_email,
            ];

            if (!$token = auth('paitent_api')->attempt($credentials)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid credentials',
                ], 200);
            }



            $currentDateTime = now('Asia/Kolkata');

            $paitentResponse = $patient->toArray();
            $paitentResponse['token'] = $token;

            return response()->json([
                'status' => true,

                'message' => 'Customer Registered Successfully',
                'paitent' => $paitentResponse,
            ]);
        } catch (\Exception $e) {



            return response()->json([
                'status' => false,
                'message' => 'An error occurred during registration: ' . $e->getMessage(),
            ], 500);
        }
    }
}
