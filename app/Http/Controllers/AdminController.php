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

class AdminController extends Controller
{
    //
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
            dd($request->email);

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



         public function getMachines(Request $request): JsonResponse
    {
        try {
            $query = DB::table('machines');

            // Optional filtering
            if ($request->has('search')) {
                $search = $request->get('search');
                $query->where(function($q) use ($search) {
                    $q->where('machine_unique_id', 'like', "%{$search}%")
                      ->orWhere('bluetooth_id', 'like', "%{$search}%");
                });
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $page = $request->get('page', 1);

            $total = $query->count();
            $machines = $query->skip(($page - 1) * $perPage)
                            ->take($perPage)
                            ->orderBy('machine_id', 'desc')
                            ->get();

            return response()->json([
                'success' => true,
                'message' => 'Machines retrieved successfully',
                'data' => [
                    'machines' => $machines,
                    'pagination' => [
                        'current_page' => (int)$page,
                        'per_page' => (int)$perPage,
                        'total' => $total,
                        'last_page' => ceil($total / $perPage)
                    ]
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
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
                    'success' => false,
                    'message' => 'Machine not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Machine retrieved successfully',
                'data' => $machine
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
                'machine_unique_id' => 'required|string|unique:machines,machine_unique_id|max:255',
                'bluetooth_id' => 'required|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Create machine
            $machineId = DB::table('machines')->insertGetId([
                'machine_unique_id' => $request->machine_unique_id,
                'bluetooth_id' => $request->bluetooth_id,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Get the created machine
            $machine = DB::table('machines')
                        ->where('machine_id', $machineId)
                        ->first();

            return response()->json([
                'success' => true,
                'message' => 'Machine created successfully',
                'data' => $machine
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
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
}
