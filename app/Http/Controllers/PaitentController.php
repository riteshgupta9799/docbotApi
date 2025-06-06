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
                // DB::table('open_otp')->updateOrInsert(
                //     ['email' => $email],
                //     ['otp' => $otp]
                // );

                DB::table('open_otp')->insert([
                    'email'=>$email,
                    'otp'=>$otp,
                ]);
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
                ->where('email', $email)
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
                ->where('mobile', $mobile)
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
}
