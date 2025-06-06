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

 public function get_all_customer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'min_date' => 'nullable|date',
            'max_date' => 'nullable|date',
            'filter' => 'nullable|in:all,machineUser,nomachineuser',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }


        $threeMonthsAgo = Carbon::now()->subMonths(3);
        $customers = DB::table('customers');

        if ($request->min_date) {
            $customers->where('customers.inserted_date', '>=', $request->min_date);
        }

        if ($request->max_date) {
            $customers->where('customers.inserted_date', '<=', $request->max_date);
        }
        if($request->filter == "nomachineuser"){
             $userData = DB::table('customers')
                ->whereNull('customers.machine_id')
                ->leftJoin('machines', 'customers.machine_id', '=', 'machines.machine_id')
                ->select(
                    'machines.*',
                    'customers.*'
                );

        }
        if($request->filter == "machineuser"){
             $userData = DB::table('customers')
                ->whereNotNull('customers.machine_id')
                ->leftJoin('machines', 'customers.machine_id', '=', 'machines.machine_id')
                ->select(
                    'machines.*',
                    'customers.*'
                )
                ->orderBy('machine_id', 'desc')
                ->get();
        }

        else {

            $customers = $customers->where('inserted_date', '>=', $threeMonthsAgo);
        }


        $customers = $customers->orderBy('customer_id', 'desc')
            ->get();

        $customerProfiles = null;

        foreach ($customers as $customer) {

            $artCount = 0;
            if ($customer->role === 'seller') {
                $artCount = $customer->art->count();
                $art = DB::table('ordered_arts')->where('seller_id', $customer->customer_id)->where('art_order_status', 'Delivered')->orderBy('ordered_art_id', 'desc')->get();
                $total_ammount = 0;
                $total = 0;
                $total_deductions = 0;
                $result = [];
                foreach ($art as $value) {
                    $trans = DB::table('ordered_arts')
                        ->where('ordered_art_id', $value->ordered_art_id)
                        ->join('art', 'ordered_arts.art_id', '=', 'art.art_id')
                        ->join('art_images', 'art.art_id', '=', 'art_images.art_id')
                        ->select('ordered_arts.*', 'art.title', 'art.portal_percentages', 'art_images.image', 'art.art_unique_id')
                        ->first();

                    // dd($trans->portal_percentages);
                    if ($value->art_order_status == 'Delivered') {
                        $portal_percentage = str_replace('%', '', $trans->portal_percentages);
                        $portal_percentage = floatval($portal_percentage) / 100;

                        $deduction = $value->price * $portal_percentage;
                        $total_deductions = $deduction;
                        $total += $value->price - $deduction;
                        $portal_percentage = str_replace('%', '', $trans->portal_percentages);
                    }

                    $transaction = [
                        'ordered_art_id' => $trans->ordered_art_id,
                        'art_id' => $trans->art_id,
                        'art_unique_id' => $trans->art_unique_id,
                        'art_name' => $trans->title,
                        'image' => isset($trans->image) ? url($trans->image) : null,
                        'price' => $value->price,
                        'art' => $trans->title,
                        'art_order_status' => $trans->art_order_status,
                        'portal_percentages' => $trans->portal_percentages,
                        'date' => $trans->inserted_date,
                        'time' => $trans->inserted_time,
                        'platefarm_deduction' => $total_deductions,
                        'total_after_deducted' => $value->price - $total_deductions,
                    ];

                    $total_ammount += $value->price - $total_deductions;

                    array_push($result, $transaction);
                }
                $withdraw = DB::table('wallet_widthraw_request')
                    ->where('seller_id', $customer->customer_id)
                    ->where('status', 'Approved')
                    ->sum(column: 'amount');
            }

            $country = DB::table('customers')
                ->leftJoin('countries', 'customers.country', '=', 'countries.country_id')
                ->where('customers.customer_id', $customer->customer_id)
                ->first();

            $state = DB::table('customers')
                ->leftJoin('states', 'customers.state', '=', 'states.state_subdivision_id')
                ->where('customers.customer_id', $customer->customer_id)
                ->first();

            $city = DB::table('customers')
                ->leftJoin('cities', 'customers.city', '=', 'cities.cities_id')
                ->where('customers.customer_id', $customer->customer_id)
                ->first();;
            $customerProfile = [
                'customer_unique_id' => $customer->customer_unique_id ?? null,
                'name' => $customer->name ?? null,
                'email' => $customer->email ?? null,
                'role' => $customer->role ?? null,
                'status' => $customer->status ?? null,
                'country' => $country ? [
                    'country_id' => $country->country_id ?? null,
                    'country_name' => $country->country_name ?? null
                ] : [],
                'state' => $state ? [
                    'state_id' => $state->state_subdivision_id ?? null,
                    'state_name' => $state->state_subdivision_name ?? null
                ] : [],
                'city' => $city ? [
                    'city_id' => $city->cities_id ?? null,
                    'city_name' => $city->name_of_city ?? null
                ] : [],
                'customer_addres' => $customer->address ?? null,
                'customer_bio' => $customer->introduction ?? null,
                'customer_mobile' => $customer->mobile ?? null,
                'customer_profile' => isset($customer->customer_profile) ? url($customer->customer_profile) : null,
                'artCount' => $artCount,
                'total_ammount' => $total_ammount ?? 0,
                'withdraw' => $withdraw ?? 0,
                'art' => ($customer->art && $customer->art->isNotEmpty()) ? $customer->art->map(function ($art) {
                    $colorCode = DB::table('status_color')
                        ->where('status_name', $art->status)
                        ->select('status_color_code')
                        ->first();
                    return [
                        'colorCode' => $colorCode->status_color_code ?? null,
                        'art_unique_id' => $art->art_unique_id ?? null,
                        'title' => $art->title ?? null,
                        'paragraph' => $art->paragraph ?? null,
                        'artist_name' => $art->artist_name ?? null,
                        'category' => $art->category ? [
                            'category_name' => $art->category->category_name ?? null,
                            'category_icon' => isset($art->category->category_icon) ? url($art->category->category_icon) : null,
                            'category_image' => isset($art->category->category_image) ? $art->category->category_image : null,
                            'sub_text' => $art->category->sub_text ?? null,
                        ] : null,
                        'price' => $art->price ?? $art->estimate_price_from . ' - ' . $art->estimate_price_to,
                        'edition' => $art->edition ?? null,
                        'since' => $art->since ?? null,
                        'pickup_address' => $art->pickup_address ?? null,
                        'pincode' => $art->pincode ?? null,
                        'status' => $art->status ?? null,
                        'country' => $art->country ? [
                            'country_id' => $art->countries->country_id ?? null,
                            'country_name' => $art->countries->country_name ?? null
                        ] : null,
                        'state' => $art->state ? [
                            'state_id' => $art->states->state_subdivision_id ?? null,
                            'state_name' => $art->states->state_subdivision_name ?? null
                        ] : null,
                        'city' => $art->city ? [
                            'city_id' => $art->cities->cities_id ?? null,
                            'city_name' => $art->cities->name_of_city ?? null
                        ] : null,
                        'art_images' => $art->artImages && $art->artImages->isNotEmpty() ? $art->artImages->map(function ($image) {
                            return [
                                'image_id' => $image->art_image_id ?? null,
                                'image_url' => isset($image->image) ? url($image->image) : null,
                            ];
                        }) : [],
                        'art_additional_details' => $art->artAdditionalDetails && $art->artAdditionalDetails->isNotEmpty() ? $art->artAdditionalDetails->map(function ($detail) {
                            return [
                                'description' => $detail->description ?? null,
                                'art_data_title' => $detail->artData ? $detail->artData->art_data_title : null
                            ];
                        }) : []
                    ];
                }) : [],
            ];

            if (array_filter($customerProfile)) {
                $customerProfiles[] = $customerProfile;
            }
        }







        if ($customers->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No Customer Found!'
            ]);
        }
        return response()->json([
            'status' => true,
            'customers' => $customerProfiles
        ]);
    }



}
