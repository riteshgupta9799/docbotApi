<?php

namespace App\Http\Controllers;

use Laravel\Socialite\Facades\Socialite;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use GeoIp2\Database\Reader;
use Illuminate\Routing\Controller;
// use Stevebauman\Location\Facades\Location;
use Tymon\JWTAuth\Facades\JWTAuth;
use Stevebauman\Location\Facades\Location;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class CustomerController extends Controller
{
    public function login_customer(Request $request)
    {

        $validated = $request->validate([
            'username' => 'required|exists:customers,username',
            'password' => 'required|string',
        ]);


        $credentials = [
            'username' => $validated['username'],
            'password' => $validated['password'],
        ];

        $user = auth('customer_api')->user();
        $user = Customer::where('username', $validated['username'])->first();
        $userNew = Customer::where('username', $request->username)->first();


        if (!$user) {

            return response()->json([
                'status' => false,
                'message' => 'Invalid username'
            ]);
        }
        if (!$userNew) {

            return response()->json([
                'status' => false,
                'message' => 'Invalid username'
            ]);
        }

        if (!$token = auth('customer_api')->attempt($credentials)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid password'
            ]);
        }


        $user->customer_profile = ($user->customer_profile);
        $user->update(['token' => $token]);
        $customer = $user->toArray();
        $customer['token'] = $token;

        $machineData = DB::table('machines')
            ->where('machine_id', $customer['machine_id'])
            ->first();




        return response()->json([
            'status' => true,
            'message' => 'Customer Found...',
            'customer' => $customer,
            'machineData' => $machineData ?? null,

        ]);
    }

    // get machine veryify key




    public function login_user(Request $request)
    {

        $validated = $request->validate([
            'email' => 'required|exists:users,email',
            'password' => 'required|string',
        ]);


        $credentials = [
            'email' => $validated['email'],
            'password' => $validated['password'],
        ];
        // dd($request->email);

        $user = auth('api')->user();
        $user = User::where('email', $validated['email'])->first();
        $userNew = User::where('email', $request->email)->first();


        if (!$user) {

            return response()->json([
                'status' => false,
                'message' => 'Invalid username'
            ]);
        }
        if (!$userNew) {

            return response()->json([
                'status' => false,
                'message' => 'Invalid username'
            ]);
        }

        if (!$token = auth('api')->attempt($credentials)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid password'
            ]);
        }


        $user->user_profile = ($user->user_profile);
        $customer = $user->toArray();
        $customer['token'] = $token;



        return response()->json([
            'status' => true,
            'message' => 'User Found...',
            'user' => $customer,

        ]);
    }

    public function verifyToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        // Try to find a customer with the provided token
        $customer = Customer::where('token', $request->token)->first();

        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or expired token.',
            ], 401);
        }

        return response()->json([
            'status' => true,
            'message' => 'Token is valid.',
            'customer' => $customer,
        ], 200);
    }

    public function get_verify_key(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'machine_unique_id' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $machine = DB::table('machines')
            ->where('machine_unique_id', $request->machine_unique_id)
            ->first();

        if (!$machine) {
            return response()->json([
                'status' => false,
                'message' => 'No Machine Found'
            ]);
        }
        return response()->json([
            'status' => true,
            'message' => ' Machine Found',
            'machine_verify_key' => $machine->verification_key
        ]);
    }


    public function logout(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'customer_unique_id' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }
        $customer = DB::table('customers')
            ->where('customer_unique_id', $request->customer_unique_id)
            ->first();

        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'No customer Found'
            ]);
        }


        DB::table('customers')
            ->where('customer_unique_id', $request->customer_unique_id)
            ->update([
                'token' => null
            ]);

        return response()->json([
            'status' => true,
            'message' => ' User Logout Successfully',

        ]);
    }

    public function customer_data(Request $request)
    {
        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            "customer_unique_id" => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $customer = Auth::guard('customer_api')->user();

        $customer_unique_id = $request->customer_unique_id;

        if ($customer->customer_unique_id !== $customer_unique_id) {
            return response()->json([
                'status' => false,
                'message' => 'Customer unique ID does not match.',
            ], 400);
        }

        if ($customer_unique_id) {

            $customer = Customer::where('customer_unique_id', $customer_unique_id)->first();
            $customers = DB::table('customers')->where('customers.customer_id', $customer->customer_id)
                ->leftJoin('machines','customers.machine_id','=','machines.machine_id')
                ->select('customers.customer_unique_id', 'customers.customer_profile', 'customers.name', 'customers.username', 'customers.email', 'customers.mobile', 'machines.machine_unique_id')
                ->orderBy('customers.customer_id', 'desc')
                ->first();

            if (!empty($customers)) {
                // Check if the customer profile image exists and append the full URL
                if ($customers->customer_profile) {
                    $customers->customer_profile = asset($customers->customer_profile);
                }

                return response()->json([
                    'status' => true,
                    'message' => "Customer Found Successfully",
                    "customer_data" => $customers
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => "Customer Not Found"
                ]);
            }
        } else {
            return response()->json([
                'status' => false,
                'message' => "Customer ID Required"
            ]);
        }
    }
}
