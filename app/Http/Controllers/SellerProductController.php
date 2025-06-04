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

class SellerProductController extends Controller
{

    public function generateUniqueId($user_id, $category_id)
    {


        $baseUniqueId = Str::uuid()->toString();


        $productCount = DB::table('products')->where('user_id', $user_id)
            ->count();

        if ($productCount) {

            $productCountPadded = str_pad($productCount + 1, 3, '0', STR_PAD_LEFT);
        } else {

            $productCountPadded = '001';
        }


        $uniqueId = $baseUniqueId . $productCountPadded;


        return $uniqueId;
    }
    public function add_product_seller(Request $request)
    {

        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $validator = Validator::make($request->all(), [
            'product_info.product_name' => 'required|string|max:255',
            'product_info.description' => 'required|string',
            'product_info.net_weight' => 'required',
            'product_info.manage_by' => 'required',
            'product_info.stock_status' => 'required',
            'product_info.discount' => 'required|min:0|max:100',
            'product_info.category_id' => 'required',
            'product_info.user_unique_id' => 'required',
            'varient_data' => 'required|array|min:1',
            'varient_data.*.color' => 'required',
            'varient_data.*.price' => 'required|min:0',
            'varient_data.*.var_images' => 'required|array|min:1',
            'varient_data.*.var_images.*.url' => 'required',
            'varient_data.*.sizes' => 'required|array|min:1',
            'varient_data.*.sizes.*.quantity' => 'required|min:0',
            'varient_data.*.sizes.*.size_id' => 'required|exists:sizes,size_id',
            'add_info' => 'nullable|array',
            'add_info.*.field' => 'required_with:add_info',
            'add_info.*.value' => 'required_with:add_info',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);
        }
        $user = Auth::guard('api')->user();

        $productInfo = $request->product_info;
        $user_unique_id = $productInfo['user_unique_id'];

        if ($user->user_unique_id !== $user_unique_id) {
            return response()->json([
                'status' => false,
                'message' => 'unique ID does not match.',
            ], 400);
        }
        $UserId=DB::table('users')
        ->where('user_unique_id',$user_unique_id)
        ->first();
        // $user_id = $users->user_id;


        $currentDateTime = now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();


        // $totalQuantity = 0;
        // foreach ($productInfo['sizes'] as $size) {
        //     $totalQuantity += $size['quantity'];
        // }


        $lastProductId = DB::table('products')->max('product_id');
        // $UserId = DB::table('users')
        //     ->select('unique_id')
        //     ->where('user_id', $productInfo['user_id'])
        //     ->first();

        if ($UserId && $lastProductId) {
            $userInt = preg_replace('/\D/', '', $UserId->unique_id);
            $uniqueId = 'A' . $userInt . 'P' . $lastProductId;
        } else {
            $uniqueId = 'A000000P' . ($lastProductId ? $lastProductId : 1);
        }


        $productData = [
            'product_name' => $productInfo['product_name'],
            'sub_text' => $productInfo['sub_text'] ?? '',
            'description' => $productInfo['description'],
            // 'colour' => $productInfo['colour'],
            'net_weight' => $productInfo['net_weight'],
            'manage_by' => $productInfo['manage_by'],
            'brand_name' => $productInfo['brand_name'] ?? '',
            // 'quantity' => $totalQuantity,
            'stock_status' => $productInfo['stock_status'],
            'unique_id' => $uniqueId,

            'discount' => $productInfo['discount'],
            // 'price' => $productInfo['price'] - ($productInfo['price'] * $productInfo['discount'] / 100),
            // 'total_amount' => $productInfo['price'],

            'category_id' => $productInfo['category_id'],
            'sub_category_1_id' => $productInfo['sub_category_1_id'] ?? null,
            'sub_category_2_id' => $productInfo['sub_category_2_id'] ?? null,
            'sub_category_3_id' => $productInfo['sub_category_3_id'] ?? null,


            'influincer_percentage' => $productInfo['influincer_percentage'] ?? 0,
            'tax_percentage' => $productInfo['tax_percentage'] ?? 0,
            'coupen_code' => $productInfo['coupen_code'] ?? '',
            'coupen_code_discount' => $productInfo['coupen_code_discount'] ?? 0,
            'project_unique_id' => $this->generateUniqueId($UserId->user_id, $request->category_id),

            'user_id' => $UserId->user_id,
            'status' => 'Inactive',
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ];

        $productId = DB::table('products')->insertGetId($productData);

        if (!empty($request->varient_data)) {
            $data = $request->varient_data;
            DB::table('products')->where('product_id', $productId)
            ->update(['product_img' => $data[0]['var_images'][0]['url']]);

            foreach ($request->varient_data as $varientData) {

                $totalQuantity = 0;

                foreach ($varientData['sizes'] as $size) {
                    $totalQuantity += $size['quantity'];
                }

                $variantInsertData = [
                    'product_id' => $productId,
                    'colour' => $varientData['color'],
                    'price' => floor($varientData['price'] - ($varientData['price'] * $productInfo['discount'] / 100)),
                    'total_amount' => $varientData['price'],
                    'quantity' => $totalQuantity,
                    'inserted_date' => $insertDate,
                    'inserted_time' => $insertTime,

                ];

                $lastInsertedId = DB::table('product_varient')->insertGetId($variantInsertData);

                // Insert variant images
                foreach ($varientData['var_images'] as $image) {
                    $imageData = [
                        'images' => $image['url'],
                        'product_varient_id' => $lastInsertedId,
                        'inserted_date' => $insertDate,
                        'inserted_time' => $insertTime,
                    ];
                    DB::table('product_varient_images')->insertGetId($imageData);
                }

                // Insert variant sizes
                foreach ($varientData['sizes'] as $size) {
                    $sizeData = [
                        'stock' => $size['quantity'],
                        'size_id' => $size['size_id'],
                        'product_varient_id' => $lastInsertedId,
                        'inserted_date' => $insertDate,
                        'inserted_time' => $insertTime,
                    ];
                    DB::table('product_varient_size')->insertGetId($sizeData);
                }
            }
        }
        if ($productData['manage_by'] == '0') {
            DB::table('product_delivery')
                ->where('user_id', $request->user_id)
                ->update([
                    'product_id' => $productId
                ]);

        }
        $addInfo = $request->add_info;
        foreach ($addInfo as $info) {

            $heading = $info['field'];
            $value = $info['value'];


            if (!empty($heading) && !empty($value)) {
                $insertedRow = [
                    'product_id' => $productId,
                    'heading' => $heading,
                    'para' => $value,
                    'inserted_date' => $insertDate,
                    'inserted_time' => $insertTime,
                ];


                DB::table('product_about')->insert($insertedRow);
            }
        }
        // Prepare the response
        return response()->json([
            'status' => true,
            'message' => 'Product and Variants Added Successfully',
            'product_id' => $productId,
        ]);
    }

    public function add_product_about(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $validator = Validator::make($request->all(), [
            'product_unique_id'   => 'required|exists:products,product_unique_id',
            'headings'     => 'required|array|min:1',
            'headings.*'   => 'required|string|max:255',
            'descriptions' => 'required|array|min:1',
            'descriptions.*' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        date_default_timezone_set('Asia/Kolkata');
        $products=DB::table('products')->where('product_unique_id',$request->product_unique_id)->first();
        $productId = $products->product_id;
        $headings = $request->input('headings');
        $descriptions = $request->input('descriptions');


        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString(); // Current date
        $insertTime = $currentDateTime->toTimeString(); // Current time


        foreach ($headings as $index => $heading) {
            $insertedRow = [
                'product_id' => $productId,
                'heading' => $heading,
                'para' => $descriptions[$index],
                'inserted_date' => $insertDate,
                'inserted_time' => $insertTime,
            ];

            DB::table('product_about')->insert($insertedRow);


            $insertedData[] = $insertedRow;
        }

        return response()->json([
            'status' => true,
            'message' => 'Product About Added Successfully',
            'data' => $insertedData

        ]);

    }

    public function add_business_details(Request $request)
    {
        date_default_timezone_set('Asia/Kolkata');
        $currentDateTime = now();
        $insertDate = $currentDateTime->toDateString(); // Current date
        $insertTime = $currentDateTime->toTimeString(); // Current time

        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'businessName' => 'required',
            'registration_number' => 'required',
            'city' => 'required',
            'country_id' => 'required',
            'state' => 'required',
            'apartment' => 'required',
            'address' => 'required',
            'postal_code' => 'required',
            'user_unique_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation Failed',
                'errors' => $validator->errors()
            ], 422);
        }
        $user = Auth::guard('api')->user();

        $user_unique_id = $request->user_unique_id;
        // $customer_unique_id = $request->unique_id;

        if ($user->user_unique_id !== $user_unique_id) {
            return response()->json([
                'status' => false,
                'message' => 'Customer unique ID does not match.',
            ], 400);
        }
        $users=DB::table('users')->where('user_unique_id',$request->user_unique_id)->first();
        $businessData = [
                        'business_name' => $request->businessName ?? '',
                        'registration_number' => $request->companyRegisterNumber ?? '',
                        'city' => $request->city ?? '',
                        'country_id' => $request->country_id ?? '',
                        'state_subdivision_id' => $request->state ?? '',
                        'address1' => $request->apartment ?? '',
                        'address2' => $request->address ?? '',
                        'postal_code' => $request->postal_code ?? '',
                        'user_id' => $users->user_id,
                        'inserted_date' => $insertDate,
                        'inserted_time' => $insertTime,
                        'status'=>'Active'
                    ];
        DB::table('business_info')->insert($businessData);

        return response()->json([
            'status' => true,
            'message' => 'Business Added Successfully',
            // 'data' => $insertedData

        ]);

    }

    public function update_business_details(Request $request)
    {
        date_default_timezone_set('Asia/Kolkata');
        $currentDateTime = now();
        $updateDate = $currentDateTime->toDateString(); // Current date
        $updateTime = $currentDateTime->toTimeString(); // Current time


        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [

            'business_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation Failed',
                'errors' => $validator->errors()
            ]);
        }
        // $user = Auth::guard('api')->user();

        // $user_unique_id = $request->user_unique_id;
        // // $customer_unique_id = $request->unique_id;

        // if ($user->user_unique_id !== $user_unique_id) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Customer unique ID does not match.',
        //     ], 400);
        // }
        // $users=DB::table('users')->where('user_unique_id',$request->user_unique_id)->first();

        $businessData = [
            'business_name' => $request->businessName ?? '',
            'registration_number' => $request->companyRegisterNumber ?? '',
            'city' => $request->city ?? '',
            'country_id' => $request->country_id ?? '',
            'state_subdivision_id' => $request->state ?? '',
            'address1' => $request->apartment ?? '',
            'address2' => $request->address ?? '',
            'postal_code' => $request->postal_code ?? '',
            'user_id' => $request->user_id,
            'inserted_date' => $updateDate,
            'inserted_time' => $updateTime,
            'status' => 'Active'
        ];


        $updated = DB::table('business_info')
            ->where('business_id', $request->business_id)
            ->update($businessData);


        if ($updated) {
            return response()->json([
                'status' => true,
                'message' => 'Business Updated Successfully',
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Business Update Failed',
            ]);
        }
    }

    public function seller_order(Request $request) {


        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'role'=>'required',
            'user_unique_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation Failed',
                'errors' => $validator->errors()
            ], 422);
        }
        $user = Auth::guard('api')->user();

        $user_unique_id = $request->user_unique_id;
        // $customer_unique_id = $request->unique_id;

        if ($user->user_unique_id !== $user_unique_id) {
            return response()->json([
                'status' => false,
                'message' => 'Customer unique ID does not match.',
            ], 400);
        }
        $users=DB::table('users')->where('user_unique_id',$request->user_unique_id)->first();

        $user_id = $users->user_id;
        $role = $request->role;

        if (!$user_id) {
            return response()->json([
                'status' => false,
                'message' => 'User ID required'
            ]);
        }


        $orderedProductsQuery = DB::table('ordered_products');

        if ($role == 'supplier' || $role == 'supplier-influencer' || $role == 'supplier-seller' || $role == 'influencer' ) {
            $orderedProductsQuery->where('user_id', $user_id);
        }
        $orderedProducts = $orderedProductsQuery->orderBy('order_id', 'desc')->get();
        // $orderedProducts = $orderedProductsQuery->get();


        if ($orderedProducts->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No orders found for this user.'
            ]);
        }

        $products = [];

        foreach ($orderedProducts as $orders) {
            $orderID = DB::table('orders')
                ->where('order_id', $orders->order_id)
                ->first();

            $product = DB::table('products')
                ->where('product_id', $orders->product_id)
                ->first();

            $product_varient = DB::table('product_varient')
                ->where('product_varient_id', $orders->product_varient_id)
                ->first();

            $product_size = DB::table('sizes')
                ->where('size_id', $orders->size_id)
                ->first();

            if ($product) {
                $products[] = [
                    'order_id' => $orderID->order_unique_id,
                    'order_status' => $orderID->order_status,
                    'product_name' => $product->product_name,
                    'product_id' => $product->product_id,
                    'product_img' => $product->product_img,
                    'net_weight' => $product->net_weight,
                    'price' => $product_varient->price,
                    'colour' => $product_varient->colour,
                    'total_amount' => $product_varient->total_amount,
                    'size' => $product_size->size_name,
                    'quantity' => $orders->quantity,
                    'order_date' => $orderID->inserted_date,
                ];
            }
        }

        return response()->json([
            'status' => true,
            'products' => $products
        ]);
    }



    public function seller_confirms_order(Request $request)
    {

        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [

            'order_unique_id' => 'required',
            'type' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation Failed',
                'errors' => $validator->errors()
            ], 422);
        }
        $user = Auth::guard('api')->user();

        // $user_unique_id = $request->user_unique_id;
        // // $customer_unique_id = $request->unique_id;

        // if ($user->user_unique_id !== $user_unique_id) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Customer unique ID does not match.',
        //     ], 400);
        // }
        // $users=DB::table('users')->where('user_unique_id',$request->user_unique_id)->first();

        $order_unique_id = $request->order_unique_id;
        $type = $request->type;
        if (!$order_unique_id) {
            return response()->json([
                'status' => false,
                'message' => 'Order Unique ID Required!'
            ]);
        }
        if ($type == 'Cancel') {
            $orders = DB::table('orders')
                ->where('order_unique_id', $order_unique_id)
                ->first();
            // dd($orders->order_id);
            $order_data = DB::table('ordered_products')
                ->where('order_id', $orders->order_id)
                ->get();

            if ($order_data->isEmpty()) {
                return response()
                    ->json([
                        'status' => false,
                        'message' => 'Order Data Not Found!'
                    ]);
            }

            foreach ($order_data as $order) {
                // dd($order);
                $product_varient = DB::table('product_varient')
                    ->where('product_varient_id', $order->product_varient_id)
                    ->first();
                $product_varient_quantity = $product_varient->quantity;
                $quantity = $product_varient_quantity + $order->quantity;
                DB::table('product_varient')
                    ->where('product_varient_id', $order->product_varient_id)
                    ->update([
                        'quantity' => $quantity
                    ]);
                $product_varient_size = DB::table('product_varient_size')
                    ->where('product_varient_id', $order->product_varient_id)
                    ->where('size_id', $order->size_id)
                    ->first();

                $product_varient_size_stock = $product_varient_size->stock;
                $size_quantity = $product_varient_size_stock + $order->quantity;

                DB::table('product_varient_size')
                    ->where('product_varient_id', $order->product_varient_id)
                    ->where('size_id', $order->size_id)
                    ->update(['stock' => $size_quantity]);
            }


        }

        $update = DB::table('orders')
            ->where('order_unique_id', $order_unique_id)
            ->update([
                'order_status' => $type
            ]);


        if ($update) {
            return response()->json([
                'status' => true,
                'message' => 'Order Status changes to ' . $type
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update order status.'
            ]);
        }


    }

    public function remove_business_info(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $data = $request->json()->all();

        DB::table('business_info')->where('business_id', $data['business_id'])->update([
            'status' => 'Inactive'
        ]);

        return response()->json([
            'status' => true,
            'message' => "Business Removed Successfully",

        ]);
    }

    public function remove_store_info(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $data = $request->json()->all();

        DB::table('store_info')->where('store_id', $data['store_id'])->update([
            'status' => 'Inactive'
        ]);

        return response()->json([
            'status' => true,
            'message' => "Store Removed Successfully",

        ]);
    }

    public function add_varient_details(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [

            'product_unique_id' => 'required',
            'color' => 'required|string|max:100',
            'images' => 'required|array',
            'sizes' => 'required|array',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);

        }
        date_default_timezone_set('Asia/Kolkata');
        $currentDateTime = now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        $productData = DB::table('products')->where('product_unique_id'.$request->product_unique_id)->first();
        $varientData = [
            'product_id' => $productData->product_id ?? '',
            'colour' => $request->color ?? '',
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ];

        $lastInsertedId = DB::table('product_varient')->insertGetId($varientData);
        foreach ($request['images'] as $image) {
            $varientData = [
                'images' => $image['image_url'],
                'product_varient_id' => $lastInsertedId,
                'inserted_date' => $insertDate,
                'inserted_time' => $insertTime,
            ];
            DB::table('product_varient_images')->insertGetId($varientData);
        }



        foreach ($request['sizes'] as $size) {
            $varientData = [
                'stock' => $size[0],
                'size_id' => $size[1],
                'product_varient_id' => $lastInsertedId,
                'inserted_date' => $insertDate,
                'inserted_time' => $insertTime,
            ];
            DB::table('product_varient_size')->insertGetId($varientData);
        }

        return response()->json([
            'status' => true,
            'message' => 'Product Varient Added Successfully'
        ]);


    }



    public function add_product_delivery(Request $request)
    {

        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [

            'user_unique_id' => 'required',
            'counrty_id' => 'required',
            'state_subdivision_id' => 'required',


        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);

        }
        $userData=DB::table('users')->where('user_unique_id',$request->user_unique_id)->first();
        $userId = $userData->user_id;
        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        $data = [
            'user_id' => $userId,
            'counrty_id' => $request->counrty_id,
            'state_id' => $request->state_subdivision_id,
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime
        ];

        DB::table('prodcut_delivery')->insert($data);


        return response()->json([
            'status' => true,
            'message' => 'Product Devlivery Added Successfully'
        ]);

    }
    public function getBusinessInfo(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [

            'user_unique_id' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);

        }
        $userData=DB::table('users')->where('user_unique_id',$request->user_unique_id)->first();

        $userId= $request->user_id;
        $user_storeInfo= DB::table('business_info')
            ->where('user_id',$userId)
            ->where('status','Active')
            ->get();

       $result = $user_storeInfo->toArray();
       if(!empty($result)){
        return response()->json([
            'status'=>true,
            'message'=>'Business Info Found!',
            'store_info' =>$result
        ]);
       }
       else{
        return response()->json([
            'status'=>false,
            'message'=>'Business Info Not Found!',

        ]);
       }
    }

    public function update_product(Request $request)
    {

        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $currentDateTime = now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();


        $productdata = $request->product_info;
        $productVariants = $request->varient_data;
        $productAbout = $request->add_info;


        $update_data = [
            'product_name' => $productdata['product_name'],
            'sub_text' => $productdata['sub_text'],
            'description' => $productdata['description'],
            'category_id' => $productdata['category_id'],
            'sub_category_1_id' => $productdata['sub_category_1_id'],
            'sub_category_2_id' => $productdata['sub_category_2_id'],
            'sub_category_3_id' => $productdata['sub_category_3_id'],
            'manage_by' => $productdata['manage_by'],

            'discount' => $productdata['discount'],
            'influincer_percentage' => $productdata['influincer_percentage'],
            'tax_percentage' => $productdata['tax_percentage'],
            'coupen_code' => $productdata['coupen_code'],
            'coupen_code_discount' => $productdata['coupen_code_discount'],

            'brand_name' => $productdata['brand_name'],
            'stock_status' => $productdata['stock_status'],

            'net_weight' => $productdata['net_weight'],
            'user_id' => $productdata['user_id'],

        ];

         $status=$update_data['stock_status']==='live'? 1:0;

        DB::table('product_cart')->where('product_id', $productdata['product_id'])->update([
            'stock_status'=>$status
        ]);



        $updated = DB::table('products')->where('product_id', $productdata['product_id'])->update($update_data);


        if ($updated) {

            if (!empty($productVariants)) {

                DB::table('products')->where('product_id', $productdata['product_id'])
                    ->update(['product_img' => $productVariants[0]['var_images'][0]['url']]);


                foreach ($productVariants as $variantData) {

                    $totalQuantity = 0;

                    foreach ($variantData['sizes'] as $size) {
                        $totalQuantity += $size['quantity'];
                    }
                    if ($variantData['product_varient_id']) {

                        DB::table('product_varient')->where('product_varient_id', $variantData['product_varient_id'])->where('product_id', $productdata['product_id'])->update([
                            'product_id' => $productdata['product_id'],
                            'colour' => $variantData['color'],
                            'price' => floor($variantData['price'] - ($variantData['price'] * $productdata['discount'] / 100)),

                            'total_amount' => $variantData['price'],
                            'quantity' => $totalQuantity,
                            'inserted_date' => $insertDate,
                            'inserted_time' => $insertTime,
                        ]);

                        $lastInsertedId = $variantData['product_varient_id'];
                    } else {
                        $lastInsertedId = DB::table('product_varient')->insertGetId([
                            'product_id' => $productdata['product_id'],
                            'colour' => $variantData['color'],
                            'price' => floor($variantData['price'] - ($variantData['price'] * $productdata['discount'] / 100)),

                            'total_amount' => $variantData['price'],
                            'quantity' => $totalQuantity,
                            'inserted_date' => $insertDate,
                            'inserted_time' => $insertTime,
                        ]);

                    }

                    foreach ($variantData['var_images'] as $image) {

                        if ($image['product_varient_image_id']) {
                            DB::table('product_varient_images')->where('product_varient_image_id', $image['product_varient_image_id'])->update([
                                'images' => $image['url'],
                                'product_varient_id' => $lastInsertedId,
                                'inserted_date' => $insertDate,
                                'inserted_time' => $insertTime,
                            ]);
                        } else {
                            DB::table('product_varient_images')->insert([
                                'images' => $image['url'],
                                'product_varient_id' => $lastInsertedId,
                                'inserted_date' => $insertDate,
                                'inserted_time' => $insertTime,
                            ]);
                        }
                    }
                    foreach ($variantData['sizes'] as $sizes) {

                        if ($sizes['product_varient_size_id']) {
                            DB::table('product_varient_size')->where('product_varient_size_id', $sizes['product_varient_size_id'])->update([
                                'stock' => $sizes['quantity'],
                                'size_id' => $sizes['size_id'],
                                'product_varient_id' => $lastInsertedId,
                                'inserted_date' => $insertDate,
                                'inserted_time' => $insertTime,
                            ]);
                        } else {
                            DB::table('product_varient_size')->insert([
                                'stock' => $sizes['quantity'],
                                'size_id' => $sizes['size_id'],
                                'product_varient_id' => $lastInsertedId,
                                'inserted_date' => $insertDate,
                                'inserted_time' => $insertTime,
                            ]);
                        }
                    }
                }
            }

            if(!empty($productAbout)){
                foreach($productAbout as $about){
                    if($about['product_about_id']!=null){
                      DB::table('product_about')
                      ->where('product_about_id', $about['product_about_id'])
                      ->update([
                        'heading' => $about['field'],
                        'para' => $about['value'],
                      ]);
                    }
                    else{
                        DB::table('product_about')
                        ->insert([
                            'product_id' => $productdata['product_id'],
                          'heading' => $about['field'],
                          'para' => $about['value'],
                          'inserted_date' => $insertDate,
                          'inserted_time' => $insertTime,
                        ]);
                    }
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Product Status Updated Successfully.',
                'data' => $updated
            ]);

        } else if (!$updated) {

            if (!empty($productVariants)) {


                foreach ($productVariants as $variantData) {

                    $totalQuantity = 0;

                    foreach ($variantData['sizes'] as $size) {
                        $totalQuantity += $size['quantity'];
                    }

                    if ($variantData['product_varient_id']) {

                        DB::table('product_varient')->where('product_varient_id', $variantData['product_varient_id'])->where('product_id', $productdata['product_id'])->update([
                            'product_id' => $productdata['product_id'],
                            'colour' => $variantData['color'],
                            'price' => floor($variantData['price'] - ($variantData['price'] * $productdata['discount'] / 100)),

                            'total_amount' => $variantData['price'],
                            'quantity' => $totalQuantity,
                            'inserted_date' => $insertDate,
                            'inserted_time' => $insertTime,
                        ]);

                        $lastInsertedId = $variantData['product_varient_id'];
                    } else {
                        $lastInsertedId = DB::table('product_varient')->insertGetId([
                            'product_id' => $productdata['product_id'],
                            'colour' => $variantData['color'],
                            'price' => floor($variantData['price'] - ($variantData['price'] * $productdata['discount'] / 100)),

                            'total_amount' => $variantData['price'],
                            'quantity' => $totalQuantity,
                            'inserted_date' => $insertDate,
                            'inserted_time' => $insertTime,
                        ]);

                    }

                    foreach ($variantData['var_images'] as $image) {

                        if ($image['product_varient_image_id']) {
                            DB::table('product_varient_images')->where('product_varient_image_id', $image['product_varient_image_id'])->update([
                                'images' => $image['url'],
                                'product_varient_id' => $lastInsertedId,
                                'inserted_date' => $insertDate,
                                'inserted_time' => $insertTime,
                            ]);
                        } else {
                            DB::table('product_varient_images')->insert([
                                'images' => $image['url'],
                                'product_varient_id' => $lastInsertedId,
                                'inserted_date' => $insertDate,
                                'inserted_time' => $insertTime,
                            ]);
                        }
                    }
                    foreach ($variantData['sizes'] as $sizes) {

                        if ($sizes['product_varient_size_id']) {
                            DB::table('product_varient_size')->where('product_varient_size_id', $sizes['product_varient_size_id'])->update([
                                'stock' => $sizes['quantity'],
                                'size_id' => $sizes['size_id'],
                                'product_varient_id' => $lastInsertedId,
                                'inserted_date' => $insertDate,
                                'inserted_time' => $insertTime,
                            ]);
                        } else {
                            DB::table('product_varient_size')->insert([
                                'stock' => $sizes['quantity'],
                                'size_id' => $sizes['size_id'],
                                'product_varient_id' => $lastInsertedId,
                                'inserted_date' => $insertDate,
                                'inserted_time' => $insertTime,
                            ]);
                        }
                    }

                }
            }

            if(!empty($productAbout)){
                foreach($productAbout as $about){
                    if($about['product_about_id']!=null){
                      DB::table('product_about')
                      ->where('product_about_id', $about['product_about_id'])
                      ->update([
                        'heading' => $about['field'],
                        'para' => $about['value'],
                      ]);
                    }
                    else{
                        DB::table('product_about')
                        ->insert([
                            'product_id' => $productdata['product_id'],
                          'heading' => $about['field'],
                          'para' => $about['value'],
                          'inserted_date' => $insertDate,
                          'inserted_time' => $insertTime,
                        ]);
                    }
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Product Status Updated Successfully.',
                'data' => $updated
            ]);

        } else {
            return response()->json([
                'status' => false,
                'message' => 'Product Id Required.',
            ]);
        }

    }

    public function user_product_stock_status_update(Request $request){

        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [

            'product_unique_id' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);

        }
        $productData = DB::table('products')->where('product_unique_id'.$request->product_unique_id)->first();

        $product_id = $productData->product_id;



        if (!$product_id) {
            return response()->json([
                'status' => false,
                'message' => 'Product Id not found'
            ]);
        }

        $updated = DB::table('products')
            ->where('product_id', $product_id)
            ->update([
                'stock_status'=>$request->stock_status
            ]);


            return response()->json([
                'status' => true,
                'message' => 'Stock status Change to '.$request->stock_status
            ]);

    }
}
