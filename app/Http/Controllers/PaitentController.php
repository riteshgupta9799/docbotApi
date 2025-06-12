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


        if ($mobile) {
            $paitent = DB::table('paitents')
                ->where('paitent_mobile', $mobile)
                ->first();

            if ($paitent) {
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
            'existingPaitent' => 'required|in:true,false'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $mobile = $request->mobile;
        $otp = $request->otp;



        if ($request->existingPaitent == true) {
            $paitent = DB::table('paitents')
                ->where('paitent_mobile', $mobile)
                ->where('mobile_otp', $otp)
                ->first();

            if ($paitent) {
                $paitents = \App\Models\Paitents::find($paitent->paitent_id); // ✅ Corrected

                $token = JWTAuth::fromUser($paitents);
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
        } else {
            // Fallback: check in open_otp
            $open = DB::table('open_otp')
                ->where('mobile', $mobile)
                ->where('otp', $otp)
                ->first();

            if ($open) {
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
    public function register_paitent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'paitent_name' => 'required',
            'paitent_mobile' => 'required|unique:paitents,paitent_mobile',
            'paitent_email' => 'required|email|unique:paitents,paitent_email',
            'gender' => 'required',
            'dob' => 'required|date',
            'address' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
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
}
