<?php

namespace App\Http\Controllers;

use Laravel\Socialite\Facades\Socialite;

use Carbon\Carbon;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use GeoIp2\Database\Reader;
use Illuminate\Routing\Controller;
use Validator;
use Illuminate\Support\Facades\DB;
// use Stevebauman\Location\Facades\Location;
use Tymon\JWTAuth\Facades\JWTAuth;
use Stevebauman\Location\Facades\Location;
use Illuminate\Support\Facades\Http;

class CustomerController extends Controller{
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
            $customer = $user->toArray();
            $customer['token'] = $token;

            $machineData = DB::table('machines')
                            ->where('machine_id',$customer['machine_id'])
                            ->first();


            return response()->json([
                'status' => true,
                'message' => 'Customer Found...',
                'customer' => $customer,
                'machineData' => $machineData ?? null,

            ]);
        }

}
