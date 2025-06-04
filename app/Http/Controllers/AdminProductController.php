<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AdminProductController extends Controller
{
    //
    public function getall_productwise_review(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $validator = Validator::make($request->all(), [
            // 'customer_id' => 'required',
            'product_unique_id' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);
        }
        $product=DB::table('products')->where('product_unique_id',$request->product_unique_id)->first();
        if(!$product){
            return response()->json([
                'status'=>false,
                'message'=>'No Product Found'
            ]);
        }
        $product_id = $product->product_id;
        $data = DB::table('product_reviews')
            ->where('product_id', $product_id)
            ->where('status', 'Active')->get();
        // dd($data);
        $result = $data->toArray();

        if (!empty($result)) {
            return response()->json([
                'status' => true,
                'data' => $result,
                'message' => 'Reviews Found'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Reviews Not Found'
            ]);
        }
    }

    public function get_customer_product_review(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $validator = Validator::make($request->all(), [
            // 'customer_id' => 'required',
            'unique_id' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);
        }
        $customers=DB::table('customers')->where('unique_id',$request->unique_id)->first();
        if(!$customers){
            return response()->json([
                'status'=>false,
                'message'=>'No customers Found'
            ]);
        }
        $data = DB::table('product_reviews')
            // ->where('product_id', $request->product_id)
            ->where('customer_id', $customers->customer_id)
            ->where('status', 'Active')->get();
        if(!$data->isEmpty()){
            return response()->json([ 'status'=>true, 'data'=>$data,'message'=>'Review Found' ]);
        }else{
            return response()->json([ 'status'=>false, 'message'=>'Review Not Found' ]);
        }
    }

    public function get_recent_purchases(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $validator = Validator::make($request->all(), [
            // 'customer_id' => 'required',
            'unique_id' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);
        }
        $customers=DB::table('customers')->where('unique_id',$request->unique_id)->first();
        if(!$customers){
            return response()->json([
                'status'=>false,
                'message'=>'No customers Found'
            ]);
        }
        $customer_id = $customers->customer_id;


        if (!$customer_id) {
            return response()->json([
                'status' => false,
                'message' => 'Customer ID is required.'
            ], 400);
        }
        $thiryday= Carbon::now()->subDays(30)->format('Y-m-d');


        $orders = DB::table('orders')
            ->where('customer_id', $customer_id)
            ->where('inserted_date', '>=',$thiryday)
            ->orderByDesc('order_id')
            ->get();


        if ($orders->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No recent purchases found in last 30 days.'
            ]);
        }


        $orderedProductsDetails = [];
        $totalOrderCount = 0;
        $totalOrderedProductCount = 0;

        foreach ($orders as $order) {
            $totalOrderCount++;
            $orderedProducts = DB::table('ordered_products')
                ->join('products', 'ordered_products.product_id', '=', 'products.product_id')
                ->join('sizes', 'ordered_products.size_id', '=', 'sizes.size_id')
                ->where('ordered_products.order_id', $order->order_id)
                ->get([
                    'ordered_products.product_id',
                    'ordered_products.ordered_product_id',
                    'ordered_products.product_varient_id',
                    'ordered_products.quantity',
                    'ordered_products.price',
                    'ordered_products.total',
                    'ordered_products.size_id',
                    'sizes.size_name',
                    'products.product_name',
                    'products.product_img'
                ]);


            foreach ($orderedProducts as $product) {
                $orderedProductsDetails[] = [
                    'ordered_product_id' => $product->ordered_product_id,
                    'product_id' => $product->product_id,
                    'product_varient_id' => $product->product_varient_id,
                    'quantity' => $product->quantity,
                    'price' => $product->price,
                    'total' => $product->total,
                    'size_id' => $product->size_id,
                    'size_name' => $product->size_name,
                    'product_name' => $product->product_name,
                    'product_img' => $product->product_img,
                    'order_status' => $order->order_status,
                ];
                $totalOrderedProductCount += $product->quantity;
            }
        }


        return response()->json([
            'status' => true,
            'message' => 'Ordered products fetched successfully.',
            'ordered_products' => $orderedProductsDetails,
            'total_order_count' => $totalOrderCount,
            'total_ordered_product_count' => $totalOrderedProductCount
        ]);
    }
}
