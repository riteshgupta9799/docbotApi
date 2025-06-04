<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class CustomerWebController extends Controller
{
    public function customer_update_product_review(Request $request)
    {

        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            "unique_id" => 'required',
            "product_unique_id" => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $customer = Auth::guard('customer_api')->user();

        $customer_unique_id = $request->unique_id;

        if ($customer->unique_id !== $customer_unique_id) {
            return response()->json([
                'status' => false,
                'message' => 'Customer unique ID does not match.',
            ], 400);
        }
        $customerData=DB::table('customers')
        ->where('unique_id',$customer_unique_id)
        ->first();
        $productData=DB::table('product')
        ->where('product_unique_id',$request->product_unique_id)
        ->first();
        $customer_id = $customerData->customer_id;


        // $customer_id = $request->customer_id;
        $product_id = $productData->product_id;
        $currentDateTime = now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        if(!$customer_id && !$product_id){
            return response()->json([
                'status' => true,
                'massage' => 'Customers ID and Product ID Required',
            ]);
        }


        $updatedRow = [
            'rating' => $request->rating,
            'comment' => $request->comment,
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ];


        $updated = DB::table('product_reviews')
            ->where('customer_id', $customer_id)
            ->update($updatedRow);
        if ($updated) {
            return response()->json([
                'status' => true,
                'massage' => 'Review Update Successfully',
            ]);
        } else {
            return response()->json([
                'status' => true,
                'massage' => 'Customers ID Required',
            ]);
        }
    }

    public function delete_customer_poduct_review(Request $request) {

        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            "unique_id" => 'required',
            "product_unique_id" => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $customer = Auth::guard('customer_api')->user();

        $customer_unique_id = $request->unique_id;

        if ($customer->unique_id !== $customer_unique_id) {
            return response()->json([
                'status' => false,
                'message' => 'Customer unique ID does not match.',
            ], 400);
        }
        $customerData=DB::table('customers')
        ->where('unique_id',$customer_unique_id)
        ->first();
        $productData=DB::table('product')
        ->where('product_unique_id',$request->product_unique_id)
        ->first();
        $customer_id = $customerData->customer_id;


        // $customer_id = $request->customer_id;
        $product_id = $productData->product_id;


        if (!$customer_id || !$product_id) {
            return response()->json([
                'status' => false,
                'message' => 'Customer ID and Product ID are required',
            ]);
        }

        $review = DB::table('product_reviews')
                    ->where('customer_id', $customer_id)
                    ->where('product_id', $product_id)
                    ->first();
        // dd($review);die;
        if (!$review) {
            return response()->json([
                'status' => false,
                'message' => 'Review Data Not Found',
            ]);
        }

        $images = DB::table('product_review_content')
                    ->where('product_review_id', $review->product_review_id)
                    ->get();

        if ($images) {
            DB::table('product_review_content')
                ->where('product_review_id', $review->product_review_id)
                ->delete();

            foreach ($images as $image) {
                $imagePath = public_path('path_to_images_directory/' . $image->img_video);
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
        }
        $deleted = DB::table('product_reviews')
                     ->where('customer_id', $customer_id)
                     ->where('product_id', $product_id)
                     ->delete();

        if ($deleted) {
            return response()->json([
                'status' => true,
                'message' => 'Review and associated images deleted successfully',
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Review deletion failed',
            ]);
        }
    }

    public function customer_deactive_account(Request $request)
    {
        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            "unique_id" => 'required',
            // "product_unique_id" => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $customer = Auth::guard('customer_api')->user();

        $customer_unique_id = $request->unique_id;

        if ($customer->unique_id !== $customer_unique_id) {
            return response()->json([
                'status' => false,
                'message' => 'Customer unique ID does not match.',
            ], 400);
        }
        $customerData=DB::table('customers')
        ->where('unique_id',$customer_unique_id)
        ->first();

        $customer_id = $customerData->customer_id;

        // $customer_id = $request->customer_id;

        if (!$customer_id) {
            return response()->json([
                'status' => false,
                'message' => 'Customer ID is required.'
            ]);
        }

        $customer = DB::table('customers')->where('customer_id', $customer_id)->first();

        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'Customer not found.'
            ]);
        }

        if ($customer->status === 'Inactive') {
            return response()->json([
                'status' => false,
                'message' => 'Account is already Inactive.'
            ]);
        }


            DB::table('customers')
                ->where('customer_id', $customer_id)
                ->update(['status' => 'Inactive']);

            return response()->json([
                'status' => true,
                'message' => 'Account removed successfully!'
            ]);

    }


    public function customer_delete_card_detail(Request $request)
    {

        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            "customer_card_details_id" => 'required',
            // "product_unique_id" => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }
        $customer_card_details_id = $request->customer_card_details_id;
        if (!$customer_card_details_id) {
            return response()->json([
                'status' => true,
                'message' => 'Card Detail Id required'
            ]);
        }

        $cardDetail = DB::table('customer_card_details')
            ->where('customer_card_details_id', $customer_card_details_id)
            ->first();

        if (!$cardDetail) {
            return response()->json([
                'status' => true,
                'message' => 'No Card Data Found'
            ]);
        }

        DB::table('customer_card_details')
            ->where('customer_card_details_id', $customer_card_details_id)
            ->delete();

        return response()->json([
            'status' => true,
            'message' => 'Card Detail Deleted Succesfully!'
        ]);



    }
}
