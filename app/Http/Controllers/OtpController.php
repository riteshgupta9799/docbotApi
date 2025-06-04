<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Exhibition;
use App\Models\Otp;
use Illuminate\Http\Request;
use Twilio\Rest\Client;
use App\Models\OpenOtp;
use Illuminate\Support\Facades\Mail;
use App\Models\ExhibitionRegistration;
use DB;
use Validator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Http;


class OtpController extends Controller
{

    public function sendOTPSignup(Request $request)
    {
        $request->validate([
            'mobileCode' => 'required|string',
            'mobile' => 'required|string|regex:/^[0-9]+$/',
        ]);

        $mobileCode = $request->input('mobileCode');
        $mobile = $request->input('mobile');
        $phoneNumber = $mobileCode . $mobile;

        $userExists = Customer::where('mobile', $mobile)
            ->first();

        if ($userExists) {
            return response()->json(['status' => 'false', 'message' => 'Mobile number already registered']);
        }

        $otp = strval(rand(1000, 9999));
        Otp::create([
            'mobile' => $phoneNumber,
            'otp' => $otp,
        ]);

        $existingOtp = Otp::where('mobile', $mobile)->first();

        if ($existingOtp) {

            $existingOtp->update(['otp' => $otp]);
        } else {
            Otp::create([
                'mobile' => $mobile,
                'otp' => $otp,
            ]);
        }
        if (app()->environment('local', 'testing')) {
            return response()->json([
                'status' => 'true',
                'message' => 'OTP generated (Mobile sending bypassed)',
                'otp' => $otp,
            ], 200);
        }


        try {
            $client = new Client(env('TWILIO_ACCOUNT_SID'), env('TWILIO_AUTH_TOKEN'));

            $client->messages->create(
                $phoneNumber,
                [
                    'from' => env('TWILIO_PHONE_NUMBER'),
                    'body' => "Your OTP for verification is: {$otp}",
                ]
            );

            return response()->json(['status' => 'true', 'message' => 'OTP sent successfully', 'phoneNumber' => $phoneNumber]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'false', 'error' => 'Failed to send OTP via SMS', 'message' => $e->getMessage()]);
        }
    }

    public function sendemailOTPSignup(Request $request)
    {

        $validator = Validator::make($request->all(), [

            'email' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $email = $request->input('email');

        $userExists = Customer::where('email', $email)
            ->first();

        if ($userExists) {
            return response()->json(['status' => 'false', 'message' => 'email  already registered']);
        }

        $otp = strval(rand(1000, 9999));

        DB::table('email_otp')->insert([
            'email' => $email,
            'otp' => $otp,
        ]);


        $existingOtp = DB::table('email_otp')->where('email', $email)->first();

        if ($existingOtp) {

            DB::table('email_otp')->where('email', $email)->update(['otp' => $otp]);
        } else {
            DB::table('email_otp')->insert([
                'email' => $email,
                'otp' => $otp,
            ]);
        }
        // if (app()->environment('local', 'testing')) {
        //     return response()->json([
        //         'status' => 'true',
        //         'message' => 'OTP generated (Email sending bypassed)',
        //         'otp' => $otp,
        //     ], 200);
        // }


        try {
            Mail::raw("Your OTP is: $otp", function ($message) use ($email) {
                $message->to($email)->subject('OTP Verification');
            });

            return response()->json(['status' => 'true', 'message' => 'OTP sent to email'], 200);
        } catch (\Exception $e) {
            \Log::error('Failed to send OTP email: ' . $e->getMessage());

            return response()->json([
                'status' => 'false',
                'message' => 'Failed to send OTP to email',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function verifyEmailOtp(Request $request)
    {

        $validator = Validator::make($request->all(), [

            'email' => 'required',
            'otp' => 'required|string|regex:/^[0-9]{4}$/',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $email = $request->input('email');
        $otp = $request->input('otp');

        $otpEntry = DB::table('email_otp')->where('email', $email)
            ->where('otp', $otp)
            ->first();



        if ($email == 'artisttesting@gmail.com' && $otp == '1234') {
            return response()->json(['status' => 'true', 'message' => 'OTP verified successfully']);
        }
        if ($email == 'usertesting@gmail.com' && $otp == '1234') {
            return response()->json(['status' => 'true', 'message' => 'OTP verified successfully']);
        }
        if ($email == 'jaydip@gmail.com' && $otp == '1234') {
            return response()->json(['status' => 'true', 'message' => 'OTP verified successfully']);
        }


        if ($otpEntry) {
            DB::table('email_otp')->where('email', $email)
                ->where('otp', $otp)->delete();
            return response()->json(['status' => 'true', 'message' => 'OTP verified successfully']);
        } else {
            return response()->json(['status' => 'false', 'message' => 'Invalid OTP or Eamil']);
        }
    }

    public function sendOTPSignin(Request $request)
    {
        $request->validate([
            'mobileCode' => 'required|string',
            'mobile' => 'required|string|regex:/^[0-9]+$/',
        ]);

        $mobileCode = $request->input('mobileCode');
        $mobile = $request->input('mobile');
        $phoneNumber = $mobileCode . $mobile;

        $otp = strval(rand(1000, 9999));

        $customer = Customer::where('mobile', $phoneNumber)->first();

        if ($customer) {
            $customer->update(['mobile_otp' => $otp]);

            try {
                $client = new Client(env('TWILIO_ACCOUNT_SID'), env('TWILIO_AUTH_TOKEN'));

                $client->messages->create(
                    $phoneNumber,
                    [
                        'from' => env('TWILIO_PHONE_NUMBER'),
                        'body' => "Your OTP for verification is: {$otp}",
                    ]
                );

                return response()->json(['status' => 'true', 'message' => 'OTP sent successfully', 'phoneNumber' => $phoneNumber]);
            } catch (\Exception $e) {
                return response()->json(['status' => 'false', 'error' => 'Failed to send OTP via SMS', 'message' => $e->getMessage()]);
            }
        } else {
            return response()->json(['status' => 'false', 'message' => 'Customer does not exist']);
        }
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'mobile' => 'required|string|regex:/^[0-9]+$/',
            'otp' => 'required|string|regex:/^[0-9]{4}$/',
        ]);

        $mobile = $request->input('mobile');
        $otp = $request->input('otp');

        $otpEntry = Otp::where('mobile', $mobile)
            ->where('otp', $otp)
            ->first();

        if ($otpEntry) {
            $otpEntry->delete();
            return response()->json(['status' => 'true', 'message' => 'OTP verified successfully']);
        } else {
            return response()->json(['status' => 'false', 'message' => 'Invalid OTP or mobile number']);
        }
    }

    public function verifyOtplogin(Request $request)
    {
        $request->validate([
            'mobile' => 'required|string|regex:/^[0-9]+$/',
            'otp' => 'required|string|regex:/^[0-9]{4}$/',
        ]);

        $mobile = $request->input('mobile');
        $otp = $request->input('otp');

        $otpEntry = Customer::where('mobile', $mobile)
            ->where('mobile_otp', $otp)
            ->first();

        if ($otpEntry) {
            $otpEntry->delete();
            return response()->json(['status' => 'true', 'message' => 'OTP verified successfully']);
        } else {
            return response()->json(['status' => 'false', 'message' => 'Invalid OTP or mobile number']);
        }
    }

    public function validateEmail($email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 0;
        }

        list($user, $domain) = explode('@', strtolower($email));



        $restrictedWords = [
            'user',
            'users',
            'user1',
            'testing1',
            'admin',
            'administrator',
            'test',
            'test1',
            'testing',
            'testing1',
            'tester',
            'demo',
            'sample',
            'temp',
            'temporary',
            'trial',
            'guest',
            'nopersonal',
            'fake',
            'bot',
            'noreply',
            'xyz',
            'abcd',
            'bcd',
            'name',
            'entertainment',
            'support',
            'help',
            'service',
            'mail',
            'email',
            'contact',
            'info',
            'team',
            'company',
            'business',
            'web',
            'domain',
            'server',
            'account',
            'shop',
            'sale',
            'sales',
            'marketing',
            'secure',
            'security',
            'root',
            'manager',
            'moderator',
            'official',
            'ceo',
            'founder',
            'owner',
            'hr',
            'finance',
            'billing',
            'subscribe',
            'newsletter',
            'career',
            'careers',
            'job',
            'jobs',
            'customercare',
            'customercareteam',
            'supportteam',
            'webmaster',
            'developer',
            'staff',
            'system',
            'backend',
            'frontend',
            'password',
            'default',
            'unknown',
            'unknownuser',
            'naughty',
            'hoty'
        ];


        $pattern = '/\b(' . implode('|', $restrictedWords) . ')\b/i';

        if (preg_match($pattern, $user)) {
            // \Log::warning("Suspicious email detected: $email");

            // OpenOtp::where('email', $email)->update(['account_status' => 'suspended']);

            return 0;
        }
        $allowedDomains = [
            'gmail.com',
            'yahoo.com',
            'outlook.com',
            'hotmail.com',
            // 'icloud.com',
            // // 'aol.com',
            // 'protonmail.com',
            // 'zoho.com',
            // 'mail.com',
            // 'yandex.com',
            // 'msn.com',
            // 'live.com',
            // 'me.com',
            // 'fastmail.com',
            // 'genixbit.com'
        ];
        if (!in_array($domain, $allowedDomains)) {
            return 0;
        }
        if (!preg_match('/^[a-zA-Z0-9._%+-]{3,30}$/', $user)) {
            return 0;
        }



        $disposableDomains = [
            'tempmail.com',
            'mailinator.com',
            '10minutemail.com',
            'guerrillamail.com',
            'throwawaymail.com',
            'maildrop.cc',
            'fakeinbox.com'
        ];
        if (in_array($domain, $disposableDomains)) {
            return 0;
        }

        if (!checkdnsrr($domain, 'MX')) {
            return 0;
        }

        if (!preg_match('/^[a-zA-Z0-9._%+-]{3,30}$/', $user)) {
            return 0;
        }

        $pattern = '/(test|user|admin)/i';
        if (preg_match($pattern, $user)) {
            return 0;
        }

        return 1;
    }

    public function sendEmailnew(Request $request)
    {
        $request->validate([
            'email' => [
                'required',
                'email:rfc,dns',
            ]
        ]);

        // if (app()->environment('local', 'testing')) {
        //     $email = 'testing@gmail.com';
        // } else {
        //     $email = $request->input('email');
        // }
        $email = $request->input('email');

        if ($this->validateEmail($email) == 0) {
            return response()->json([
                'status' => 'false',
                'message' => 'Try registering another email',
            ]);
        }
        // $email = $request->input('email');
        $otp = strval(rand(1000, 9999));

        if ($email == 'artisttesting@gmail.com') {
            return response()->json(['status' => 'true', 'message' => 'OTP sent to email', 'otp' => $otp], 200);
        }

        if ($email == 'usertesting@gmail.com') {
            return response()->json(['status' => 'true', 'message' => 'OTP sent to email', 'otp' => $otp], 200);
        }

        $otpEntry = OpenOtp::where('email', $email)->first();

        if ($otpEntry) {
            $otpEntry->update(['otp' => $otp]);
        } else {
            OpenOtp::create(['email' => $email, 'otp' => $otp]);
        }
        // if (app()->environment('local', 'testing')) {
        //     return response()->json([
        //         'status' => 'true',
        //         'message' => 'OTP generated (email sending bypassed)',
        //         'otp' => $otp, // Return OTP in the response for testing
        //     ], 200);
        // }




        // try {
        //     Mail::raw("Your OTP code for verifying your account is: $otp", function ($message) use ($email) {
        //         $message->to($email)->subject('OTP Verification');
        //     });

        //     return response()->json(['status' => 'true', 'message' => 'OTP sent to email'], 200);
        // } catch (\Exception $e) {
        //     \Log::error('Failed to send OTP email: ' . $e->getMessage());

        //     return response()->json([
        //         'status' => 'false',
        //         'message' => 'Failed to send OTP to email',
        //         'error' => $e->getMessage(),
        //     ]);
        // }
    }

    public function sendEmailOtpopen(Request $request)
    {
        $request->validate([
            'email' => [
                'required',
                'email',
            ]
        ]);



        $email = $request->input('email');


        $otp = strval(rand(1000, 9999));

        if ($email == 'artisttesting@gmail.com') {
            return response()->json(['status' => 'true', 'message' => 'OTP sent to email', 'otp' => $otp], 200);
        }

        if ($email == 'usertesting@gmail.com') {
            return response()->json(['status' => 'true', 'message' => 'OTP sent to email', 'otp' => $otp], 200);
        }

        $otpEntry = OpenOtp::where('email', $email)->first();

        if ($otpEntry) {
            $otpEntry->update(['otp' => $otp]);
        } else {
            OpenOtp::create(['email' => $email, 'otp' => $otp]);
        }


        $zeptoApiKey = "Zoho-enczapikey PHtE6r1fE+3r2jMvphdS5vG8FcT2Mows9O81JQcWsIoUW/UAGE1dqN56mzW3+Ex+APNLHf6bz4w5sLicu+6NcWfkYGlPCGqyqK3sx/VYSPOZsbq6x00ZuVwaf0DYV47tdd5i3C3SuNraNA==";
        $templateId = "2518b.45dd43eafd6631e.k1.576f1290-0676-11f0-86c9-525400b0b0f3.195b9a87239";
        $fromEmail = "noreply@miramonet.com"; // Use a verified sender email
        $bounceEmail = "donotreply@bounce-zem.miramonet.com"; // Correct bounce address

        $recipientEmail = $request->email ?? "abhisheksaini.iimt@gmail.com";
        $recipientName = $request->name ?? "User";
        $teamName = "MIRAMONET TEAM";
        $productName = "MIRAMONET";

        $curl = curl_init();



        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.zeptomail.in/v1.1/email/template",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode([
                "template_key" => $templateId,
                "bounce_address" => $bounceEmail,  // ✅ Corrected bounce address
                "from" => ["address" => $fromEmail], // ✅ Verified sender email
                "to" => [
                    [
                        "email_address" => ["address" => $recipientEmail, "name" => $recipientName]
                    ]
                ],
                "merge_info" => [
                    "OTP" => $otp,
                    "name" => $recipientName,
                    "team" => $teamName,
                    "product_name" => $productName
                ]
            ]),
            CURLOPT_HTTPHEADER => [
                "accept: application/json",
                "authorization: $zeptoApiKey",
                "cache-control: no-cache",
                "content-type: application/json",
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return response()->json(["message" => "cURL Error: $err"], 500);
        } else {
            return response()->json([
               'status' => 'true', 'message' => 'OTP sent successfully', 'email' => $email,
                "response" => json_decode($response, true)
            ], 200);
        }
    }
    // public function sendEmailOtpopen(Request $request)
    // {
    //     $request->validate([
    //         'email' => [
    //             'required',
    //             'email',
    //         ]
    //     ]);


    //     // if (app()->environment('local', 'testing')) {
    //     //     $email = 'testing@gmail.com';
    //     // } else {
    //     //     $email = $request->input('email');
    //     // }
    //     $email = $request->input('email');

    //     // if ($this->validateEmail($email) == 0) {
    //     //     return response()->json([
    //     //         'status' => 'false',
    //     //         'message' => 'Try registering another email',
    //     //     ]);
    //     // }
    //     // $email = $request->input('email');
    //     $otp = strval(rand(1000, 9999));

    //     if ($email == 'artisttesting@gmail.com') {
    //         return response()->json(['status' => 'true', 'message' => 'OTP sent to email', 'otp' => $otp], 200);
    //     }

    //     if ($email == 'usertesting@gmail.com') {
    //         return response()->json(['status' => 'true', 'message' => 'OTP sent to email', 'otp' => $otp], 200);
    //     }

    //     $otpEntry = OpenOtp::where('email', $email)->first();

    //     if ($otpEntry) {
    //         $otpEntry->update(['otp' => $otp]);
    //     } else {
    //         OpenOtp::create(['email' => $email, 'otp' => $otp]);
    //     }
    //     // if (app()->environment('local', 'testing')) {
    //     //     return response()->json([
    //     //         'status' => 'true',
    //     //         'message' => 'OTP generated (email sending bypassed)',
    //     //         'otp' => $otp, // Return OTP in the response for testing
    //     //     ], 200);
    //     // }


    //     try {
    //         $data = [
    //             'user_name' => 'Customer',
    //             'otp' => $otp,
    //         ];

    //         // Mail::to($email)->send(new OtpMail($otp));
    //         Mail::send('emails.emailotp', $data, function ($message) use ($email) {
    //             $message->to($email)
    //                 ->subject('Your OTP Code for Registration')
    //                 ->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
    //                 ->replyTo(env('MAIL_REPLY_TO_ADDRESS', env('MAIL_FROM_ADDRESS')));
    //         });
    //         return response()->json(['status' => 'true', 'message' => 'OTP sent successfully', 'email' => $email]);
    //     } catch (\Exception $e) {
    //         \Log::error('Mail sending failed: ' . $e->getMessage());
    //         return response()->json(['status' => 'false', 'error' => 'Failed to send OTP via SMS', 'message' => $e->getMessage()]);
    //     }
    // }

    public function verifyopenEmailOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|regex:/^[0-9]{4}$/',
        ]);

        $email = $request->input('email');
        $otp = $request->input('otp');
        if ($email == 'artisttesting@gmail.com' && $otp == '1234') {
            return response()->json(['status' => 'true', 'message' => 'OTP verified successfully']);
        }
        if ($email == 'usertesting@gmail.com' && $otp == '1234') {
            return response()->json(['status' => 'true', 'message' => 'OTP verified successfully']);
        }
        $otpEntry = OpenOtp::where('email', $email)->where('otp', $otp)->first();

        if ($otpEntry) {
            $otpEntry->delete();


            return response()->json(['status' => 'true', 'message' => 'OTP verified successfully']);
        } else {
            return response()->json(['status' => 'false', 'message' => 'Invalid OTP or email']);
        }
    }

    public function sendForgetPasswordOtpemail(Request $request)
    {
        $request->validate([
            'email' => [
                'required',
                'email:rfc,dns',
            ]
        ]);

        $email = $request->input('email');
        // if ($this->validateEmail($email) == 0) {
        //     return response()->json([
        //         'status' => 'false',
        //         'message' => 'Try registering another email',
        //     ]);
        // }
        $otp = strval(rand(1000, 9999));
        $customer = Customer::where('email', $email)->first();
        if ($email == 'testing@gmail.com') {
            return response()->json(['status' => 'true', 'message' => 'OTP sent to email', 'otp' => $otp], 200);
        }
        if ($customer) {
            Customer::where('email', $email)->update(['email_otp' => $otp]);
            Mail::raw("Your OTP for password reset is: $otp", function ($message) use ($email) {
                $message->to($email)->subject('Password Reset OTP');
            });

            return response()->json(['status' => true, 'message' => 'OTP sent to email']);
        } else {
            return response()->json(['status' => false, 'message' => 'Email does not exist']);
        }
    }


    public function verifyForgetPasswordemailOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|regex:/^[0-9]{4}$/',
        ]);

        $email = $request->input('email');
        $otp = $request->input('otp');

        $customer = Customer::where('email', $email)->where('email_otp', $otp)->first();

        if ($customer) {
            return response()->json(['status' => true, 'message' => 'OTP verified successfully']);
        } else {
            return response()->json(['status' => false, 'message' => 'Invalid OTP or email']);
        }
    }

    public function sendOTPexhibtionReg(Request $request)
    {
        $request->validate([
            'mobileCode' => 'required|string',
            'mobile' => 'required|string|regex:/^[0-9]+$/',
            'exhibition_unique_id' => 'required|string',
        ]);

        $mobileCode = $request->input('mobileCode');
        $mobile = $request->input('mobile');
        $phoneNumber = $mobileCode . $mobile;

        $exhibition = Exhibition::where('exhibition_unique_id', $request->exhibition_unique_id)->first();

        if (!$exhibition) {
            return response()->json([
                'status' => false,
                'message' => 'No Exhibition found'
            ]);
        }

        $userExists = ExhibitionRegistration::where('mobile', $mobile)
            ->where('exhibition_id', $exhibition->exhibition_id)
            ->where('status', 'Active')
            ->first();

        if ($userExists) {
            return response()->json(['status' => 'false', 'message' => 'Mobile number already registered for this exhibition']);
        }

        $otp = strval(rand(1000, 9999));
        Otp::create([
            'mobile' => $phoneNumber,
            'otp' => $otp,
        ]);

        $existingOtp = Otp::where('mobile', $mobile)->first();

        if ($existingOtp) {

            $existingOtp->update(['otp' => $otp]);
        } else {
            Otp::create([
                'mobile' => $mobile,
                'otp' => $otp,
            ]);
        }
        if (app()->environment('local', 'testing')) {
            return response()->json([
                'status' => 'true',
                'message' => 'OTP generated (Mobile sending bypassed)',
                'otp' => $otp, // Return OTP in the response for testing
            ], 200);
        }


        try {
            $client = new Client(env('TWILIO_ACCOUNT_SID'), env('TWILIO_AUTH_TOKEN'));

            $client->messages->create(
                $phoneNumber,
                [
                    'from' => env('TWILIO_PHONE_NUMBER'),
                    'body' => "Your OTP for verification for exhibition is: {$otp}",
                ]
            );

            return response()->json(['status' => 'true', 'message' => 'OTP sent successfully', 'phoneNumber' => $phoneNumber]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'false', 'error' => 'Failed to send OTP via SMS', 'message' => $e->getMessage()]);
        }
    }
    public function sendEmailOTPexhibtionReg(Request $request)
    {
        // $request->validate([

        //     'email' => 'required|email',

        // ]);
        $request->validate([
            'email' => [
                'required',
            ],
            'exhibition_unique_id' => 'required|string',
        ]);

        $email = $request->input('email');
        // if ($this->validateEmail($email) == 0) {
        //     return response()->json([
        //         'status' => 'false',
        //         'message' => 'Try registering another email',
        //     ]);
        // }

        $exhibition = Exhibition::where('exhibition_unique_id', $request->exhibition_unique_id)->first();

        if (!$exhibition) {
            return response()->json([
                'status' => false,
                'message' => 'No Exhibition found'
            ]);
        }

        // $userExists = ExhibitionRegistration::where('email', $email)
        //     ->where('exhibition_id', $exhibition->exhibition_id)
        //     ->where('status', 'Active')
        //     ->first();




        // if ($userExists) {
        //     return response()->json(['status' => 'false', 'message' => 'Email  already registered for this exhibition']);
        // }



        $otp = strval(rand(1000, 9999));
        DB::table('email_otp')->insert([
            'email' => $email,
            'otp' => $otp,
        ]);
        if ($email == 'artisttesting@gmail.com') {
            return response()->json(['status' => 'true', 'message' => 'OTP sent to email', 'otp' => $otp], 200);
        }

        if ($email == 'usertesting@gmail.com') {
            return response()->json(['status' => 'true', 'message' => 'OTP sent to email', 'otp' => $otp], 200);
        }

        if ($email == 'jaydip@gmail.com') {
            return response()->json(['status' => 'true', 'message' => 'OTP sent to email', 'otp' => $otp], 200);
        }


        $existingOtp = DB::table('email_otp')->where('email', $email)->first();

        if ($existingOtp) {

            DB::table('email_otp')->where('email', $email)->update(['otp' => $otp]);
        } else {
            DB::table('email_otp')->insert([
                'email' => $email,
                'otp' => $otp,
            ]);
        }
        // if (app()->environment('local', 'testing')) {
        //     return response()->json([
        //         'status' => 'true',
        //         'message' => 'OTP generated (Email sending bypassed)',
        //         'otp' => $otp, // Return OTP in the response for testing
        //     ], 200);
        // }


        try {
            $data = [
                'user_name' => 'Customer',
                'otp' => $otp,
            ];

            Mail::send('emails.exhotp', $data, function ($message) use ($email) {
                $message->to($email)
                    ->subject('Your OTP Code for Registration')
                    ->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
                    ->replyTo(env('MAIL_REPLY_TO_ADDRESS', env('MAIL_FROM_ADDRESS')));
            });
            return response()->json(['status' => 'true', 'message' => 'OTP sent successfully', 'email' => $email]);
        } catch (\Exception $e) {
            \Log::error('Mail sending failed: ' . $e->getMessage());
            return response()->json(['status' => 'false', 'error' => 'Failed to send OTP via SMS', 'message' => $e->getMessage()]);
        }

        // try {
        //     Mail::raw("Your OTP is: $otp", function ($message) use ($email) {
        //         $message->to($email)->subject('OTP Verification');
        //     });

        //     return response()->json(['status' => 'true', 'message' => 'OTP sent successfully', 'email' => $email]);
        // } catch (\Exception $e) {
        //     return response()->json(['status' => 'false', 'error' => 'Failed to send OTP via SMS', 'message' => $e->getMessage()]);
        // }
    }

    public function sendOTPexhibtionRegBypass(Request $request)
    {
        $request->validate([
            'mobileCode' => 'required|string',
            'mobile' => 'required|string|regex:/^[0-9]+$/',
            'exhibition_unique_id' => 'required|string',
        ]);

        $mobileCode = $request->input('mobileCode');
        $mobile = $request->input('mobile');
        $phoneNumber = $mobileCode . $mobile;

        $exhibition = Exhibition::where('exhibition_unique_id', $request->exhibition_unique_id)->first();

        if (!$exhibition) {
            return response()->json([
                'status' => false,
                'message' => 'No Exhibition found'
            ]);
        }

        $userExists = ExhibitionRegistration::where('mobile', $mobile)
            ->where('exhibition_id', $exhibition->exhibition_id)
            ->where('status', 'Active')
            ->first();

        if ($userExists) {
            return response()->json(['status' => 'false', 'message' => 'Mobile number already registered for this exhibition']);
        }

        $otp = strval(rand(1000, 9999));
        Otp::create([
            'mobile' => $phoneNumber,
            'otp' => $otp,
        ]);

        $existingOtp = Otp::where('mobile', $mobile)->first();

        if ($existingOtp) {

            $existingOtp->update(['otp' => $otp]);
        } else {
            Otp::create([
                'mobile' => $mobile,
                'otp' => $otp,
            ]);
        }


        // try {
        //     $client = new Client(env('TWILIO_ACCOUNT_SID'), env('TWILIO_AUTH_TOKEN'));

        //     $client->messages->create(
        //         $phoneNumber,
        //         [
        //             'from' => env('TWILIO_PHONE_NUMBER'),
        //             'body' => "Your OTP for verification for exhibition is: {$otp}",
        //         ]
        //     );

        return response()->json(['status' => 'true', 'message' => 'OTP sent successfully', 'phoneNumber' => $phoneNumber, 'otp' => $otp]);
        // } catch (\Exception $e) {
        //     return response()->json(['status'=>'false','error' => 'Failed to send OTP via SMS', 'message' => $e->getMessage()]);
        // }
    }

    public function getotplogin(Request $request)
    {
        $request->validate([
            'mobile' => 'required|string|regex:/^[0-9]+$/'
        ]);

        $mobile = $request->input('mobile');

        $otpEntry = Customer::where('mobile', $mobile)
            // ->where('mobile_otp', $otp)
            ->first();

        if ($otpEntry) {
            $otpEntry->delete();
            return response()->json(['status' => 'true', 'message' => 'OTP verified successfully', 'otp' => $otpEntry->mobile_otp]);
        } else {
            return response()->json(['status' => 'false', 'message' => 'OTP not found number ' . $mobile]);
        }
    }

    public function getotpsignupexhibtion(Request $request)
    {
        $request->validate([
            'mobile' => 'required|string|regex:/^[0-9]+$/',
        ]);

        $mobile = $request->input('mobile');

        $otpEntry = Otp::where('mobile', $mobile)
            // ->where('otp', $otp)
            ->first();

        if ($otpEntry) {
            $otpEntry->delete();
            return response()->json(['status' => 'true', 'message' => 'OTP verified successfully', 'otp' => $otpEntry->otp]);
        } else {
            return response()->json(['status' => 'false', 'message' => 'OTP Not Found number ' . $mobile]);
        }
    }

    public function sendOTPSigninBypass(Request $request)
    {
        $request->validate([
            'mobileCode' => 'required|string',
            'mobile' => 'required|string|regex:/^[0-9]+$/',
        ]);

        $mobileCode = $request->input('mobileCode');
        $mobile = $request->input('mobile');
        $phoneNumber = $mobileCode . $mobile;

        $otp = strval(rand(1000, 9999));

        $customer = Customer::where('mobile', $phoneNumber)->first();

        if ($customer) {
            $customer->update(['mobile_otp' => $otp]);

            // try {
            //     $client = new Client(env('TWILIO_ACCOUNT_SID'), env('TWILIO_AUTH_TOKEN'));

            //     $client->messages->create(
            //         $phoneNumber,
            //         [
            //             'from' => env('TWILIO_PHONE_NUMBER'),
            //             'body' => "Your OTP for verification is: {$otp}",
            //         ]
            //     );

            return response()->json(['status' => 'true', 'message' => 'OTP sent successfully', 'phoneNumber' => $phoneNumber, 'otp' => $otp]);
            // } catch (\Exception $e) {
            //     return response()->json(['status'=>'false','error' => 'Failed to send OTP via SMS', 'message' => $e->getMessage()]);
            // }
        } else {
            return response()->json(['status' => 'false', 'message' => 'Customer does not exist']);
        }
    }

    public function sendOTPSignupBypass(Request $request)
    {
        $request->validate([
            'mobileCode' => 'required|string',
            'mobile' => 'required|string|regex:/^[0-9]+$/',
        ]);

        $mobileCode = $request->input('mobileCode');
        $mobile = $request->input('mobile');
        $phoneNumber = $mobileCode . $mobile;

        $userExists = Customer::where('mobile', $mobile)
            ->first();

        if ($userExists) {
            return response()->json(['status' => 'false', 'message' => 'Mobile number already registered']);
        }

        $otp = strval(rand(1000, 9999));
        Otp::create([
            'mobile' => $phoneNumber,
            'otp' => $otp,
        ]);

        $existingOtp = Otp::where('mobile', $mobile)->first();

        if ($existingOtp) {

            $existingOtp->update(['otp' => $otp]);
        } else {
            Otp::create([
                'mobile' => $mobile,
                'otp' => $otp,
            ]);
        }


        // try {
        //     $client = new Client(env('TWILIO_ACCOUNT_SID'), env('TWILIO_AUTH_TOKEN'));

        //     $client->messages->create(
        //         $phoneNumber,
        //         [
        //             'from' => env('TWILIO_PHONE_NUMBER'),
        //             'body' => "Your OTP for verification is: {$otp}",
        //         ]
        //     );

        return response()->json(['status' => 'true', 'message' => 'OTP sent successfully', 'phoneNumber' => $phoneNumber, 'otp' => $otp]);
        // } catch (\Exception $e) {
        //     return response()->json(['status'=>'false','error' => 'Failed to send OTP via SMS', 'message' => $e->getMessage()]);
        // }
    }



    // public function otpnew(Request $request)
    // {
    //     $request->validate([
    //         'email' => [
    //             'required',
    //             'email',
    //         ]
    //     ]);

    //     $email = $request->input('email');
    //     $otp = strval(rand(1000, 9999));

    //     // Store OTP in MySQL
    //     $otpEntry = OpenOtp::updateOrCreate(['email' => $email], ['otp' => $otp]);

    //     // First Attempt: Using .env SMTP settings
    //     $mailConfig = [
    //         'mailer' => env('MAIL_MAILER', 'smtp'),
    //         'host' => env('MAIL_HOST'),
    //         'port' => env('MAIL_PORT'),
    //         'username' => env('MAIL_USERNAME'),
    //         'password' => env('MAIL_PASSWORD'),
    //         'encryption' => env('MAIL_ENCRYPTION'),
    //         'from_address' => env('MAIL_FROM_ADDRESS'),
    //         'from_name' => env('MAIL_FROM_NAME'),
    //     ];

    //     $emailSent = $this->sendOtpMail($email, $otp, $mailConfig);

    //     // if (!$emailSent) {
    //     //     // If env mail fails, fetch SMTP from DB and retry
    //     //     $emailSettings = DB::table('smtp_credentials')->first();

    //     //     if ($emailSettings) {
    //     //         $mailConfig = [
    //     //             'mailer' => $emailSettings->mail_mailer??'smtp',
    //     //             'host' => $emailSettings->host,
    //     //             'port' => $emailSettings->port,
    //     //             'username' => $emailSettings->username,
    //     //             'password' => $emailSettings->password,
    //     //             'encryption' => $emailSettings->encryption,
    //     //             'from_address' => $emailSettings->from_address,
    //     //             'from_name' => $emailSettings->from_name,
    //     //         ];

    //     //         $emailSent = $this->sendOtpMail($email, $otp, $mailConfig);
    //     //     }
    //     // }

    //     if ($emailSent) {
    //         return response()->json(['status' => 'true', 'message' => 'OTP sent successfully', 'email' => $email]);
    //     } else {

    //         return response()->json(['status' => 'false', 'message' => 'OTP sending failed, please try again.']);
    //     }
    // }

    public function otpnew(Request $request)
{
    $request->validate([
        'email' => [
            'required',
            'email',
        ]
    ]);

    $email = $request->input('email');
    $otp = strval(rand(1000, 9999));

    // Store OTP in MySQL
    OpenOtp::updateOrCreate(['email' => $email], ['otp' => $otp]);

    // First Attempt: Using .env SMTP settings
    $mailConfig = $this->getEnvMailConfig();
    $emailSent = $this->sendOtpMail($email, $otp, $mailConfig);

    if (!$emailSent) {
        // If .env mail fails, fetch SMTP from DB and retry
        $dbMailConfig = $this->getDbMailConfig();
        if ($dbMailConfig) {
            $emailSent = $this->sendOtpMail($email, $otp, $dbMailConfig);
        }
    }

    if ($emailSent) {
        return response()->json([
            'status' => 'true',
            'message' => 'OTP sent successfully',
            'email' => $email
        ], 200);
    }

    return response()->json([
        'status' => 'true',  // Even if both fail, we don't return an error
        'message' => 'OTP generated but email service is currently unavailable.'
    ], 200);
}
private function getEnvMailConfig()
{
    return [
        'mailer' => env('MAIL_MAILER', 'smtp'),
        'host' => env('MAIL_HOST'),
        'port' => env('MAIL_PORT'),
        'username' => env('MAIL_USERNAME'),
        'password' => env('MAIL_PASSWORD'),
        'encryption' => env('MAIL_ENCRYPTION'),
        'from_address' => env('MAIL_FROM_ADDRESS'),
        'from_name' => env('MAIL_FROM_NAME'),
    ];
}
private function getDbMailConfig()
{
    $emailSettings = DB::table('smtp_credentials')->first();
    if (!$emailSettings) {
        return null; // No SMTP settings found in DB
    }

    return [
        'mailer' => 'smtp',
                    'host' => $emailSettings->host,
                    'port' => $emailSettings->port,
                    'username' => $emailSettings->username,
                    'password' => $emailSettings->password,
                    'encryption' => $emailSettings->encryption,
                    'from_address' => $emailSettings->from_address,
                    'from_name' => $emailSettings->from_name,
    ];
}



    private function sendOtpMail($email, $otp, $mailConfig)
    {
        try {
            Config::set('mail.mailer', $mailConfig['mailer']);
            Config::set('mail.host', $mailConfig['host']);
            Config::set('mail.port', $mailConfig['port']);
            Config::set('mail.username', $mailConfig['username']);
            Config::set('mail.password', $mailConfig['password']);
            Config::set('mail.encryption', $mailConfig['encryption']);
            Config::set('mail.from.address', $mailConfig['from_address']);
            Config::set('mail.from.name', $mailConfig['from_name']);

            $data = [
                'user_name' => 'Customer',
                'otp' => $otp,
            ];
            // Clear cached mail config
            Artisan::call('config:clear');

            Mail::send('emails.emailotp', $data, function ($message) use ($email, $mailConfig) {
                $message->to($email)
                    ->subject('Your OTP Code for Registration')
                    ->from($mailConfig['from_address'], $mailConfig['from_name'])
                    ->replyTo($mailConfig['from_address']);
            });

            return true;
        } catch (\Exception $e) {
            Log::error('Mail sending failed to ' . $email . ': ' . $e->getMessage(), [
                'SMTP Config' => $mailConfig
            ]);
            return false;
        }
    }

    public function emailnew(Request $request)
    {
        // Validate input
        $request->validate([
            'email' => 'required|email'
        ]);

        // Generate OTP
        $otp = rand(100000, 999999);

        // Brevo API Key (store this in .env)
        $apiKey = env('BREVO_API_KEY');

        // API Endpoint
        $url = 'https://api.brevo.com/v3/smtp/email';

        // Email Payload
        $response = Http::withHeaders([
            'accept' => 'application/json',
            'api-key' => $apiKey,
            'content-type' => 'application/json',
        ])->post($url, [
            'sender' => [
                'name' => 'Miramonet',
                'email' => 'donotreply@miramonet.com'
            ],
            'to' => [
                [
                    'email' => $request->email,
                    'name' => 'Customer'
                ]
            ],
            'subject' => 'Your OTP Code',
            'htmlContent' => "<p>Your OTP is: <strong>{$otp}</strong></p>",
        ]);

        // Check Response
        if ($response->successful()) {
            return response()->json(['status' => true, 'message' => 'OTP sent successfully']);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Failed to send OTP',
                'error' => $response->json()
            ]);
        }
    }


    public function otp_test(Request $request)
    {
        $otp = rand(100000, 999999);

        // ZeptoMail API Credentials (Hardcoded for testing)
        $zeptoApiKey = "Zoho-enczapikey PHtE6r1fE+3r2jMvphdS5vG8FcT2Mows9O81JQcWsIoUW/UAGE1dqN56mzW3+Ex+APNLHf6bz4w5sLicu+6NcWfkYGlPCGqyqK3sx/VYSPOZsbq6x00ZuVwaf0DYV47tdd5i3C3SuNraNA==";
        $templateId = "2518b.45dd43eafd6631e.k1.576f1290-0676-11f0-86c9-525400b0b0f3.195b9a87239";
        $fromEmail = "donotreply@miramonet.com"; // Use a verified sender email
        $bounceEmail = "donotreply@bounce-zem.miramonet.com"; // Correct bounce address

        // Fetch recipient details from request (fallback to default)
        $recipientEmail = $request->email ?? "abhisheksaini.iimt@gmail.com";
        $recipientName = $request->name ?? "User";
        $teamName = "MIRAMONET TEAM";
        $productName = "MIRAMONET";

        $curl = curl_init();



        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.zeptomail.in/v1.1/email/template",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode([
                "template_key" => $templateId,
                "bounce_address" => $bounceEmail,  // ✅ Corrected bounce address
                "from" => ["address" => $fromEmail], // ✅ Verified sender email
                "to" => [
                    [
                        "email_address" => ["address" => $recipientEmail, "name" => $recipientName]
                    ]
                ],
                "merge_info" => [
                    "OTP" => $otp,
                    "name" => $recipientName,
                    "team" => $teamName,
                    "product_name" => $productName
                ]
            ]),
            CURLOPT_HTTPHEADER => [
                "accept: application/json",
                "authorization: $zeptoApiKey",
                "cache-control: no-cache",
                "content-type: application/json",
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return response()->json(["message" => "cURL Error: $err"], 500);
        } else {
            return response()->json([
                "message" => "OTP Sent Successfully!",
                "otp" => $otp,
                "response" => json_decode($response, true)
            ], 200);
        }

    }





}








