<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
// use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;



class AdminController extends Controller
{
    //



    public function getMachines(Request $request): JsonResponse
    {
        $user = Auth::guard('api')->user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access. Token is missing or invalid.',
            ], 401);
        }

        try {
            $filter = $request->query('filter'); // filter=with_user / without_user / all

            $machineUserData = [];
            $noUserMachineData = [];



            if (!$filter || $filter === 'with_user' || $filter === 'all') {
                $machineUserData = DB::table('machines')
                    ->whereNotNull('machines.customer_id')
                    ->leftJoin('customers', 'machines.machine_id', '=', 'customers.machine_id')
                    ->select(
                        'machines.*',
                        'customers.name as customer_name',
                        'customers.email as customer_email'
                    )
                    ->orderBy('machine_id', 'desc')
                    ->get();
            }

            if (!$filter || $filter === 'without_user' || $filter === 'all') {
                $noUserMachineData = DB::table('machines')
                    ->whereNull('customer_id')
                    ->orderBy('machine_id', 'desc')
                    ->get();
            }

            return response()->json([
                'status' => true,
                'message' => 'Machines retrieved successfully',
                'machineUserData' => $machineUserData,
                'noUserMachineData' => $noUserMachineData,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve machines',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Get single machine
     */
    public function getMachine($id): JsonResponse
    {
        try {
            $machine = DB::table('machines')
                ->where('machine_id', $id)
                ->first();

            if (!$machine) {
                return response()->json([
                    'status' => false,
                    'message' => 'Machine not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Machine retrieved successfully',
                'machines' => $machine
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve machine',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new machine
     */
    public function createMachine(Request $request): JsonResponse
    {
        try {
            // Validation
            $validator = Validator::make($request->all(), [
                'bluetooth_id' => 'required|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first()
                ], 422);
            }

            // Create machine
            $machineId = DB::table('machines')->insertGetId([
                'machine_unique_id' => Str::uuid(),
                'bluetooth_id' => $request->bluetooth_id,

            ]);

            // Get the created machine
            $machine = DB::table('machines')
                ->where('machine_id', $machineId)
                ->first();

            return response()->json([
                'status' => true,
                'message' => 'Machine created successfully',
                'data' => $machine
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create machine',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update machine
     */
    public function updateMachine(Request $request, $id): JsonResponse
    {
        try {
            // Check if machine exists
            $machine = DB::table('machines')
                ->where('machine_id', $id)
                ->first();

            if (!$machine) {
                return response()->json([
                    'success' => false,
                    'message' => 'Machine not found'
                ], 404);
            }

            // Validation
            $validator = Validator::make($request->all(), [
                'machine_unique_id' => 'sometimes|required|string|unique:machines,machine_unique_id,' . $id . ',machine_id|max:255',
                'bluetooth_id' => 'sometimes|required|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Prepare update data
            $updateData = ['updated_at' => now()];

            if ($request->has('machine_unique_id')) {
                $updateData['machine_unique_id'] = $request->machine_unique_id;
            }

            if ($request->has('bluetooth_id')) {
                $updateData['bluetooth_id'] = $request->bluetooth_id;
            }

            // Update machine
            DB::table('machines')
                ->where('machine_id', $id)
                ->update($updateData);

            // Get updated machine
            $updatedMachine = DB::table('machines')
                ->where('machine_id', $id)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Machine updated successfully',
                'data' => $updatedMachine
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update machine',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete machine
     */
    public function deleteMachine($id): JsonResponse
    {
        try {
            // Check if machine exists
            $machine = DB::table('machines')
                ->where('machine_id', $id)
                ->first();

            if (!$machine) {
                return response()->json([
                    'success' => false,
                    'message' => 'Machine not found'
                ], 404);
            }

            // Delete machine
            DB::table('machines')
                ->where('machine_id', $id)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Machine deleted successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete machine',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function machine_to_patient(Request $request){
        
    }



    // user Details for Admin

    public function get_all_customer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'min_date' => 'nullable|date',
            'max_date' => 'nullable|date',
            'filter' => 'nullable|in:all,machineuser,nomachineuser',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $threeMonthsAgo = Carbon::now()->subMonths(3);

        // Base query
        $customers = DB::table('customers')
            ->leftJoin('machines', 'customers.machine_id', '=', 'machines.machine_id')
            ->select('customers.*', 'machines.*');

        // Apply date filters
        if ($request->min_date) {
            $customers->where('customers.inserted_date', '>=', $request->min_date);
        } else {
            // Default to 3 months ago
            $customers->where('customers.inserted_date', '>=', $threeMonthsAgo);
        }

        if ($request->max_date) {
            $customers->where('customers.inserted_date', '<=', $request->max_date);
        }

        $threeMonthsAgo = Carbon::now()->subMonths(3);

        // Filter logic
        if ($request->filter === 'machineuser') {
            $customers->whereNotNull('customers.machine_id');
        } elseif ($request->filter === 'nomachineuser') {
            $customers->whereNull('customers.machine_id');
        } else {
            $customers = $customers->where('inserted_date', '>=', $threeMonthsAgo);
        }

        // Sort
        $customers = $customers->orderBy('customers.customer_id', 'desc')->get();

        if ($customers->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No Customer Found!',
                'customers' => $customers
            ]);
        }

        return response()->json([
            'status' => true,
            'customers' => $customers
        ]);
    }

    public function add_customer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'mobile' => 'required|unique:paitents,paitent_mobile',
            'email' => 'required|email|unique:paitents,paitent_email',
            'username' => 'required',
            'machine_id' => 'required',
            'address' => 'nullable',
            'passwrod' => 'required'
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

        $existing = DB::table('customers')
            ->where('username', $request->username)
            ->first();

        if ($existing) {
            return response()->json([
                'status' => false,
                'message' => 'This User already exists.'
            ], 409);
        }

        // Prepare data
        $commonData = [
            'name'   => ucfirst(strtolower($request->name)),
            'email'  => $request->email,
            'mobile' => $request->mobile,
            'username'         => $request->username,
            'customer_unique_id' => Str::uuid(),
            'password' => Hash::make($request->password),
            'address'        => $request->address,
            'machine_id'        => $request->machine_id,
            'inserted_date'  => $insertDate,
            'inserted_time'  => $insertTime,
        ];
        try {
            $customerId = DB::table('customers')->insertGetId($commonData);
            // $customer = DB::table('customers')->where('customer_id', $customerId)->first();

            // Retrieve Eloquent model for token generation
            $customerModel = \App\Models\Customer::find($customerId);

            // Generate token
            $token = JWTAuth::fromUser($customerModel);


            return response()->json([
                'status' => true,
                // 'role' => $customer->role,
                'message' => 'Customer Registered Successfully',
                'customer' => array_merge($customerModel->toArray(), ['token' => $token]),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    // updateCustomergi
    public function update_customer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required'
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

        $customer = DB::table('customers')
            ->where('customer_unique_id', $request->customer_unique_id)
            ->first();

        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'user Not Found'
            ]);
        }

        // Prepare data
        $commonData = [
            'name'   => ucfirst(strtolower($request->name)) ?? $customer->name,
            'email'  => $request->email ?? $customer->email,
            'mobile' => $request->mobile ?? $customer->mobile,
            'username'         => $request->username ?? $customer->username,
            'customer_unique_id' => Str::uuid(),
            'password' => Hash::make($request->password) ?? $customer->password,
            'address'        => $request->address ?? $customer->address,
            'machine_id'        => $request->machine_id ?? $customer->machine_id,
            'inserted_date'  => $insertDate,
            'inserted_time'  => $insertTime,
        ];
        try {
            $customerId = DB::table('customers')
                ->where('customer_unique_id', $request->customer_unique_id)
                ->update($commonData);



            return response()->json([
                'status' => true,

                'message' => 'Customer Update Successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    // delete customer
    public function delete_customer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required'
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
                'message' => 'user Not Found'
            ]);
        }

        // Prepare data

        try {
            $customer = DB::table('customers')
                ->where('customer_unique_id', $request->customer_unique_id)
                ->delete();



            return response()->json([
                'status' => true,

                'message' => 'Customer Delete Successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }




}
