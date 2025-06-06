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
            $paitent = DB::table('paitent')
                ->where('paitent_email', $email)
                ->first();

            if ($paitent) {
                DB::table('paitent')
                    ->where('paitent_email', $email)
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
            $paitent = DB::table('paitent')
                ->where('paitent_mobile', $mobile)
                ->first();

            if ($paitent) {
                DB::table('paitent')
                    ->where('paitent_mobile', $mobile)
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
            // Check in paitent table
            $paitent = DB::table('paitent')
                ->where('paitent_email', $email)
                ->where('email_otp', $otp)
                ->first();

            if ($paitent) {
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
            // Check in paitent table
            $paitent = DB::table('paitent')
                ->where('paitent_mobile', $mobile)
                ->where('mobile_otp', $otp)
                ->first();

            if ($paitent) {
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
            'paitent_name' => 'required',
            'paitent_mobile' => 'required',
            'paitent_email' => 'required|email|unique:paitent,paitent_email',
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
                    ->where('paitent_mobile',$request->paitent_mobile)
                    ->first();

               if($existing){
                return response()->josn([
                    'status'=>false,
                    'message'=>'Mobile Number Alredy Exists'
                ]);
               }
        $existingEmail = DB::table('customers')
                     ->where('paitent_email',$request->paitent_email)
                    ->first();

               if($existingEmail){
                return response()->josn([
                    'status'=>false,
                    'message' => 'This email is restricted from creating an account.'
                ]);
               }
        $currentDateTime = Carbon::now('Asia/Kolkata');


        $commonData = [
            'paitent_name' => ucfirst(strtolower($request->name)),
            'paitent_email' => $request->paitent_email,
            'paitent_mobile' => $request->paitent_mobile,
            'dob' => $request->dob,

            'inserted_date' => $insertDate,

            'inserted_time' => $insertTime,

            'address'=>$request->address
        ];

        try {

           $paitentId = DB::table('paitent')->insertGetId($commonData);
            $paitent = DB::table('paitent')->where('paitent_id', $paitentId)->first();


            $credentials = [
                'email' => $request->paitent_email,
            ];

            if (!$token = auth('paitent_api')->attempt($credentials)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid credentials',
                ], 200);
            }



            $currentDateTime = now('Asia/Kolkata');

            $paitentResponse = $paitent->toArray();
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
