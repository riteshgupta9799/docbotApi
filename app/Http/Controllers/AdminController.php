<?php

namespace App\Http\Controllers;

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


    // user Details for Admin

public function get_all_customer(Request $request): JsonResponse
{
    $validator = Validator::make($request->all(), [
        'min_date' => 'nullable|date',
        'max_date' => 'nullable|date',
        'filter' => 'nullable|in:all,machine_customer,no_machine_customer',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'message' => $validator->errors()->first(),
        ], 400);
    }

    try {
        $filter = $request->query('filter', 'all'); // default to 'all'
        $threeMonthsAgo = Carbon::now()->subMonths(3);

        $customersQuery = DB::table('customers');

        if ($request->min_date) {
            $customersQuery->where('inserted_date', '>=', $request->min_date);
        }

        if ($request->max_date) {
            $customersQuery->where('inserted_date', '<=', $request->max_date);
        } else {
            $customersQuery->where('inserted_date', '>=', $threeMonthsAgo);
        }

        $customers = $customersQuery->get();

        if ($customers->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No Customer Found!'
            ]);
        }

        $customerProfiles = $customers->map(function ($customer) {
            $machines = DB::table('machines')
                ->where('customer_id', $customer->customer_id)
                ->select('machine_id', 'machine_unique_id', 'bluetooth_id')
                ->get();

            $customer->machines = $machines;
            return $customer;
        });

        // Filter by customer with machines
        if ($filter === 'machine_customer') {
            $customerProfiles = $customerProfiles->filter(function ($c) {
                return $c->machines->isNotEmpty();
            })->values();
        }

        // Filter by customer WITHOUT machines
        if ($filter === 'no_machine_customer') {
            $customerProfiles = $customerProfiles->filter(function ($c) {
                return $c->machines->isEmpty();
            })->values();
        }
        if ($customerProfiles->isEmpty()) {
    return response()->json([
        'status' => false,
        'message' => 'No Customer Found!',
        'customers' => []
    ]);
}

        return response()->json([
            'status' => true,
            'customers' => $customerProfiles
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Error occurred while fetching customer data',
            'error' => $e->getMessage()
        ], 500);
    }
}


}
