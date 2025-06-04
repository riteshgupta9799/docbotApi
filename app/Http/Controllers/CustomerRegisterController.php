<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class CustomerRegisterController extends Controller
{
    //
    public function generateUniqueId ()
    {
        do
        {
        $uniqueId =
            mt_rand(1000000000, 9999999999);
            $exists = DB::
            table('customers')
            ->where('unique_id', $uniqueId)
            ->exists();
        }
        while
        ($exists);
        return $uniqueId;
    }
    public function register_customer(Request $request)
    {
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:customers,email',
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'mobile' => 'required|string|max:15|unique:customers,mobile',
            'password' => 'required|string|min:6',
        ]);

        // If validation fails, return an error response
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $currentDateTime = Carbon::now();
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();
        $uniqueId = $this->generateUniqueId();
        // Prepare data for insertion
        $customerData = [
            'email' => $request->email,
            'first_name' => $request->firstName,
            'last_name' => $request->lastName,
            'mobile' => $request->mobile,
            'gender' => $request->gender,
            'status' => 'Active',
            'timestamp' => Carbon::now()->timestamp * 1000,
            'password' => Hash::make($request->password),
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
            'unique_id' => $uniqueId,
        ];

        try {
            // Insert into the database
            $customerId = DB::table('customers')->insertGetId($customerData);

            // Retrieve the newly created customer
            $customerData = DB::table('customers')
                // ->select('customer_id', 'first_name', 'last_name', 'mobile', 'email', 'gender','unique_id')
                ->where('customer_id', $customerId)
                ->first();

                $credentials = [
                    'email' => $customerData->email,
                    'password' => $request->password,
                ];

                if (!$token = auth('customer_api')->attempt($credentials)) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Invalid credentials',
                    ], 200);
                }


                if ($customerData->status !== 'Active') {
                    return response()->json([
                        'status' => false,
                        'message' => 'Your Account is Deactivated!',
                        'account' => 'Deactivated',
                    ]);
                }


                $currentDateTime = now('Asia/Kolkata');

                $customerData = (array) $customerData;
                $customerData['token'] = $token;


            // Return success response
            return response()->json([
                'status' => true,
                'customer_id' => $customerId,
                'message' => 'Customer Added Successfully ...',
                'customer_data' => $customerData,
            ]);
        } catch (\Exception $e) {
            // Return error response in case of exception
            return response()->json([
                'status' => false,
                'message' => 'Server Error: ' . $e->getMessage(),
            ], 500);
        }
    }
    public function email_pass_login(Request $request)
    {
        // Validate the incoming request data
        $validated = $request->validate([
            'email' => 'required|string',
            'password' => 'required|string', // Assuming password is required
        ]);

        $email = $validated['email'];
        $password = $validated['password'];

        $credentials = [
            'email' => $validated['email'],
            'password' => $validated['password'],
        ];
        // Find the customer by mobile number
        $customer = DB::table('customers')->where('email', $email)->first();

        // Check if customer exists
        if (!$customer) {
            // If customer does not exist
            return response()->json([
                'status' => false,
                'message' => 'Email Not Exist ...'
            ]);
        }
        if (!$token = auth('customer_api')->attempt($credentials)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid password'
            ]);
        }


        if ($customer->status !== 'Active') {
            return response()->json([
                'status' => false,
                'message' => 'Your account has been deleted!',

            ]);
        }

        // $user->customer_profile = url($user->customer_profile);
        $customer = (array) $customer;
        // $customer->customer_profile=url($customer->customer_profile);
        $customer['token'] = $token;

        // // Check if the password matches
        // if (!Hash::check($password, $customer->password)) {
        //     // If password does not match
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Password Mismatch ...'
        //     ]);
        // }

        // If everything is correct, return customer data
        return response()->json([
            'status' => true,
           'unique_id' => $customer['unique_id'],
        'customer_data' => [
            'customer_id' => $customer['customer_id'],
            'unique_id' => $customer['unique_id'],
            'first_name' => $customer['first_name'],
            'last_name' => $customer['last_name'],
            'mobile' => $customer['mobile'],
            'email' => $customer['email'],
            'gender' => $customer['gender'],
            'token' => $customer['token'],
        ],
            'message' => 'Customer Found ...'
        ]);
    }

    public function getCurrencyInfo($countryCode)
    {
        if(!$countryCode){
            return ;
        }

        $country = DB::table('countries')
            ->where('country_code_char2', $countryCode)
            ->orWhere('country_code_char3', $countryCode)
            ->first();

        if (!$country) {
            return null; // Country not found
        }

        $countryCurrency = DB::table('currencies')
            ->where('country', $country->country_name)
            ->first();

        if (!$countryCurrency) {
            return null; // Currency not found
        }

        // Fetch exchange rates
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://openexchangerates.org/api/latest.json?app_id=388ee726b5924e5b9577140a3c7e0347',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ));

        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            $error_msg = curl_error($curl);
            curl_close($curl);
            return response()->json([
                'status' => false,
                'message' => 'Curl Error: ',
            ]);
        }

        curl_close($curl);
        $currencyData = json_decode($response, true);

        if (isset($currencyData['error'])) {
            return response()->json([
                'status' => false,
                'message' => 'API Error: ' . $currencyData['error'],
            ]);
        }

        $currencyCode = $countryCurrency->code;
        $currencysymbol = $countryCurrency->symbol;

        if (isset($currencyData['rates'][$currencyCode])) {
            $rate = $currencyData['rates'][$currencyCode];
            return ['rate' => $rate, 'symbol' => $currencysymbol];
        }
    }
    public function get_cart(Request $request)
    {

        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ]);
        }

        $validator = Validator::make($request->all(), [
            'unique_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 200);
        }
        $unique_id = $request->unique_id;
        $customerNew = Auth::guard('customer_api')->user();

        $customer=DB::table('customers')
                    ->where('unique_id',$unique_id)

                    ->first();
              $customer_id=$customer->customer_id;
         if($customer->status=='Inactive'){
            $token = JWTAuth::getToken();
            JWTAuth::invalidate($token);
            auth('customer_api')->logout();

            return response()->json([
                'status'=>false,
                 'message' => 'Your account is deleted or deactivated.'
            ]);
         }
                        $countryCode = $request->countryCode;

        if (!$unique_id) {
            return response()->json([
                'status' => false,
                'message' => 'Customer Id not found.'
            ], 404);
        }

        $customerCartProductData = DB::table('product_cart')
            ->where('customer_id', $customer_id)
            ->select(
                'product_cart.*'
            )
            ->get();

        if ($customerCartProductData->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => "Please add products to your cart."
            ]);
        }

        $result = [];
        $totalAmount = 0;
        $currencyInfo = $this->getCurrencyInfo($countryCode);
        $rate = $currencyInfo['rate'] ?? '1';
        $currencysymbol = $currencyInfo['symbol'] ?? '$';

        foreach ($customerCartProductData as $cartData) {

            $productData = DB::table('products')
                            ->where('product_id',$cartData->product_id)
                            ->select('product_name', 'description','category_id')
                            ->first();
            $ar = [];

            $categorydata = DB::table('category')
                            ->where('category_id',$productData->category_id)
                            // ->select('category_name', 'description')
                            ->first();
            // dd($categorydata);

            $ar['product_cart_id'] = $cartData->product_cart_id;
            $ar['product_name'] = $productData->product_name;
            $ar['product_id'] = $cartData->product_id;
            $ar['product_varient_id'] = $cartData->product_varient_id;
            $ar['added_quantity'] = $cartData->quantity;
            $ar['category_name'] = $categorydata->category_name;
                $varientData = DB::table('product_varient')
                    ->where('product_varient_id', $cartData->product_varient_id)
                    ->first();
                // dd($varientData);

                $varientImage = DB::table('product_varient_images')
                    ->where('product_varient_id', $cartData->product_varient_id)
                    ->first();
                // dd($varientImage);

                $sizeData = DB::table('product_varient_size')
                    ->leftJoin('sizes', 'product_varient_size.size_id', '=', 'sizes.size_id')
                    // ->where('product_varient_id', $cartData->product_varient_id)
                    ->where('product_varient_size.size_id', $cartData->size_id)
                    ->select('sizes.size_name', 'product_varient_size.stock', 'product_varient_size.size_id')
                    ->first();
                // dd($sizeData);
                // dd($rate);

                $convertedPrice = sprintf('%.2f', ($varientData->price) * $rate);
                // $convertedproduct_total= sprintf('%.2f', ($cartData->total) * $rate);
                // dd( $convertedPrice);
                $varientData->price = $convertedPrice;
                // $cartData->total=$convertedproduct_total;
                $varientData->symbol = $currencysymbol;



                $ar['images'] = $varientImage->images ?? null;
                $ar['product_size_name'] = $sizeData->size_name ?? null;
                $ar['colour'] = $varientData->colour ?? null;
                $ar['price'] = $varientData->price ?? 0;
                $ar['product_quantity_left'] = $sizeData->stock ?? 0;
                $ar['size_id'] = $sizeData->size_id ?? null;
                $ar['symbol']=$currencysymbol??'';
                // $ar['stock'] = $sizeData->quantity ?? null;
                // $ar['product_total'] = $cartData->total ?? null;

            // $totalAmount = $totalAmount + $cartData->total;
            $totalAmount +=  $ar['price'] * $ar['added_quantity'];
            // dd($totalAmount);
            // $totalAmount = sprintf('%.2f', $totalAmount * $rate);


            // dd($totalAmount);

            $result[] = $ar;
        }

        return response()->json([
            'status' => true,
            'cartAllData' => $result,
            'cartCount' => count($result),
            'total_amount' => $totalAmount,
        ]);
    }

        public function logout()
        {
            auth('customer_api')->logout();

            return response()->json([
                'status' => true,
                'message' => 'Successfully logged out'
            ]);
        }



        public function get_customer_allorder(Request $request)
        {
            if (!Auth::guard('customer_api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                "unique_id" => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ], 400);
            }

            $customer = Auth::guard('customer_api')->user();

            $customer_unique_id = $request->unique_id;
            // $customer_unique_id = $request->unique_id;

            if ($customer->unique_id !== $customer_unique_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Customer unique ID does not match.',
                ], 400);
            }

            $customerData =DB::table('customers')->where('unique_id',$customer_unique_id)->first();
            $customerId = $customerData->customer_id;
            $customerorderData = DB::table('orders')
                ->where('orders.customer_id', $customerId)
                // ->leftjoin('customers_delivery_address', 'orders.customer_delivery_address_id', '=', 'customers_delivery_address.customers_delivery_address_id')
                // ->leftjoin('countries', 'customers_delivery_address.country', '=', 'countries.country_id')
                // ->leftjoin('states', 'customers_delivery_address.state', '=', 'states.state_subdivision_id')
                // ->select(
                //     'orders.order_id',
                //     'orders.amount',
                //     'orders.order_unique_id',
                //     'orders.order_status',
                //     'orders.customer_id',
                //     'orders.payment_id',
                //     'orders.tracking_id',
                //     'orders.tracking_status',
                //     'orders.inserted_date',
                //     'orders.inserted_time',`
                //     'customers_delivery_address.*',
                //     'countries.country_name',
                //     'states.state_subdivision_name'
                // )
                ->orderBy('order_id', 'desc')
                ->get();

            if (!$customerId || $customerorderData->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No Order Made By Customer.'
                ]);
            }

            $result = [];

            foreach ($customerorderData as $orders) {
                // dd($orders);
                $addData = DB::table('customers_delivery_address')
                    ->where('customers_delivery_address_id', $orders->customer_delivery_address_id)
                    ->leftjoin('countries', 'customers_delivery_address.country', '=', 'countries.country_id')
                    ->leftjoin('states', 'customers_delivery_address.state', '=', 'states.state_subdivision_id')
                    ->select('customers_delivery_address.*', 'countries.country_name', 'states.state_subdivision_name')
                    ->first();
                // dd($addData);die;
                $orderData = [
                    'order_id' => $orders->order_id,
                    'order_unique_id' => $orders->order_unique_id,
                    'order_status' => $orders->order_status,
                    'customer_id' => $orders->customer_id,
                    'tracking_id' => $orders->tracking_id,
                    'tracking_status' => $orders->tracking_status,
                    'amount' => $orders->amount,
                    'inserted_date' => $orders->inserted_date,
                ];

                $coupon_data=DB::table('coupon')
                              ->where('coupon_id',$orders->coupon_id)
                              ->select('coupon_name','coupon_image','coupon_code','amount')
                              ->first();


               $customerAdress = [
                    'country_name' => $addData->country_name ?? '',
                    'state_subdivision_name' => $addData->state_subdivision_name ?? '',
                    'city' => $addData->city ?? '',
                    'address_type' => $addData->address_type ?? '',
                    'address1' => $addData->address1 ?? '',
                    'address2' => $addData->address2 ?? '',
                    'locality' => $addData->locality ?? '',
                    'pincode' => $addData->pincode ?? '',
                ];

                $productData = DB::table('ordered_products')
                    ->leftjoin('products', 'ordered_products.product_id', '=', 'products.product_id')
                    ->leftjoin('product_varient', 'ordered_products.product_varient_id', '=', 'product_varient.product_varient_id')
                    ->leftjoin('sizes', 'ordered_products.size_id', '=', 'sizes.size_id')
                    ->select(
                        'ordered_products.order_id',
                        'ordered_products.product_id',
                        'ordered_products.product_order_status',
                        'ordered_products.product_varient_id',
                        'ordered_products.quantity',
                        'ordered_products.price',
                        'ordered_products.total',
                        'ordered_products.tracking_id',
                        'products.product_name',
                        'products.sub_text',
                        'products.brand_name',
                        'products.net_weight',
                        'products.discount',
                        'product_varient.colour',
                        'sizes.size_name'
                    )
                    ->where('order_id', $orders->order_id)
                    ->get();

                // Prepare product details
                $productDetails = [];
                foreach ($productData as $product) {
                    // dd($product);
                    $productImage = DB::table('product_varient_images')
                        ->where('product_varient_id', $product->product_varient_id)
                        ->first();

                       $productVarient=DB::table('product_varient')
                                    ->where('product_varient_id',$product->product_varient_id)
                                    ->first();

                    $productDetails[] = [
                        'product_id' => $product->product_id,
                        'product_name' => $product->product_name,
                        'price' => $productVarient->price,
                        'total_amount' => $productVarient->total_amount,
                        'quantity' => $product->quantity,
                        'sub_text' => $product->sub_text,
                        'product_varient_id' => $product->product_varient_id,
                        'images' => $productImage->images ?? null,
                        'discount' => $product->discount,
                        'net_weight' => $product->net_weight,
                        'colour' => $product->colour,
                        'size_name' => $product->size_name,
                    ];
                }

                // Assemble the final array for each order
                $result[] = [
                    'order_details' => $orderData,
                    'customerAdress' => $customerAdress,
                    'product_details' => $productDetails,
                    'coupon_data' => $coupon_data ??  null,
                ];
            }

            return response()->json([
                'status' => true,
                'OrderAllData' => $result,
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
                "unique_id" => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ], 400);
            }

            $customer = Auth::guard('customer_api')->user();

            $customer_unique_id = $request->unique_id;
            // $customer_unique_id = $request->unique_id;

            if ($customer->unique_id !== $customer_unique_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Customer unique ID does not match.',
                ], 400);
            }


            $customerData=DB::table('customers')
                            ->where('unique_id',$customer_unique_id)
                            ->first();

                            // dd($customerData);

                $customer = DB::table('customers')
                ->leftJoin('countries', 'customers.country', '=', 'countries.country_id')
                ->leftJoin('states', 'customers.state', '=', 'states.state_subdivision_id')
                ->select(
                    'customers.*',
                    'countries.country_name',
                    'states.state_subdivision_name as state_name'
                )
                ->where('customers.customer_id', $customerData->customer_id)
                ->first();


                $my_reviews = DB::table('product_reviews')
                    ->leftJoin('products','product_reviews.product_id','=','products.product_id')
                    ->where('customer_id', $customerData->customer_id)
                    ->select(
                        'product_reviews.product_review_id',
                        'product_reviews.rating',
                        'product_reviews.comment',
                        'products.product_name',
                    )
                    ->get();

                $coupons = DB::table('customer_get_coupon')
                    ->leftjoin('coupon','customer_get_coupon.coupon_id','=','coupon.coupon_id')
                    ->where('customer_id',$customerData->customer_id)
                    ->select('coupon.*')
                    ->get();

                if ($customer) {


                    $arr= [];
                    if($my_reviews){
                        $arr=$my_reviews;
                    }
                    else{
                         $arr = [];
                    }
                    return response()->json([
                        'status' => true,
                        'message' => "Customer Found Successfully",
                        "customer_data" => [
                            'first_name' => $customer->first_name,
                            'last_name' => $customer->last_name,
                            'image' => $customer->image,
                            'mobile' => $customer->mobile,
                            'email' => $customer->email,
                            'gender' => $customer->gender,
                            'country' => $customer->country,
                            'country_name' => $customer->country_name,
                            'state' => $customer->state,
                            'state_name' => $customer->state_name,
                            // 'city' => $customer->city

                        ],
                        "my_reviews" =>$arr,
                        "coupons" => $coupons
                    ]);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => "Customer Not Found"
                    ]);
                }

        }


        public function get_customers_delivery_address(Request $request)
        {
            if (!Auth::guard('customer_api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                "unique_id" => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ], 400);
            }

            $customer = Auth::guard('customer_api')->user();

            $customer_unique_id = $request->unique_id;
            // $customer_unique_id = $request->unique_id;

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
            $deliveryAddress = DB::table('customers_delivery_address')
                ->select(
                    'customers_delivery_address.country',
                    'customers_delivery_address.state',
                    'customers_delivery_address.city',
                    'customers_delivery_address.address_type',
                    'customers_delivery_address.address1',
                    'customers_delivery_address.address2',
                    'customers_delivery_address.locality',
                    'customers_delivery_address.pincode',
                    'countries.country_name',
                    'states.state_subdivision_name',
                    'customers_delivery_address.customers_delivery_address_id',
                )
                ->leftJoin('countries', 'countries.country_id', '=', 'customers_delivery_address.country')

                ->leftJoin('states', 'states.state_subdivision_id', '=', 'customers_delivery_address.state')

                ->where('customer_id', $customer_id)
                ->get();

                $result = $deliveryAddress->toArray();
                if (!empty($result)) {
                    return response()->json([
                        'status' => true,
                        'data' => $result,
                        'message' => 'Customer Delivery Address Found'
                    ]);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Customer Delivery Address Not Found'
                    ]);
                }

        }

        public function add_cart(Request $request)
        {
            if (!Auth::guard('customer_api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                "unique_id" => 'required'
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





            date_default_timezone_set('Asia/Kolkata');
            $currentDateTime = now('Asia/Kolkata');
            $insertDate = $currentDateTime->toDateString();
            $insertTime = $currentDateTime->toTimeString();


            $product_id = $request->product_id;
            // $customer_id = $request->customer_id;
            $product_varient_id = $request->product_varient_id;

            $size_id =$request->size_id;
            if(!$size_id){
               $productVarientSize= DB::table('product_varient_size')
                ->where('product_varient_id',$product_varient_id)
                ->first();

                $size_id = $productVarientSize->size_id;
                // dd($size_id);
            }
            else{
                $size_id = $request->size_id;
            }

            $influencer_id = $request->influencer_id;

            $CartData = DB::table('product_cart')
                ->where('product_id', $product_id)
                ->where('product_varient_id', $product_varient_id)
                ->where('size_id', $size_id)
                ->where('customer_id', $customer_id)
                ->first();

            if (!is_null($CartData)) {
                return response()->json([
                    'status' => true,
                    'message' => 'Product Alredy Added.'
                ]);
            }


            $productData = DB::table('products')->where('product_id', $product_id)->select('product_name', 'description', )->first();


            $product_varient_data = DB::table('product_varient')->where('product_varient_id', $product_varient_id)->select('price', 'colour', 'price', 'total_amount')->first();
            // dd($product_varient_data);
            $price = $product_varient_data->price;
            if (!$productData && !$product_varient_data) {
                return response()->json([
                    'status' => false,
                    'message' => 'Product not found.'
                ], 404);
            }

            $cartData = [
                'quantity' => '1',
                'total' => $price,
                'customer_id' => $customer_id,
                'product_varient_id' => $product_varient_id,
                'size_id' => $size_id,
                'inserted_date' => $insertDate,
                'inserted_time' => $insertTime,
                'product_id' => $product_id,
                'price' => $price,
                'influencer_id' => $influencer_id,
            ];
            $cartId = DB::table('product_cart')->insertGetId($cartData);
            $cartCount = DB::table('product_cart')
                ->where('customer_id', $customer_id)
                ->count();

            return response()->json([
                'status' => true,
                'cartCount' => $cartCount,
                'message' => 'Product Added to Cart Successfully!'
            ]);


        }

        public function updateCustomer(Request $request)
        {
            if (!Auth::guard('customer_api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                "unique_id" => 'required'
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


            $data = [
                'first_name' => $request->first_name??$customerData->first_name,
                'last_name' => $request->last_name??$customerData->last_name,
                'mobile' => $request->mobile??$customerData->mobile,
                'email' => $request->email??$customerData->email,
                'gender' => $request->gender??$customerData->gender,
                'image' => $request->image??$customerData->image,
                'country' => $request->country??$customerData->country,
                'state' => $request->state??$customerData->state,
            ];

          $update=  DB::table('customers')->where('customer_id', $customer_id)->update($data);
            if($update){
                return response()->json([
                    'status' => true,
                    'message' => "Data Updated Successfully"
                ]);
            }else{
                return response()->json([
                    'status' => false,
                    'message' => "Internal server error"
                ]);
            }

        }

        public function check_quantity($request)
        {
            if (!Auth::guard('customer_api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                "unique_id" => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ], 400);
            }

            $customer = Auth::guard('customer_api')->user();

            $customer_unique_id = $request->unique_id;
            // $customer_unique_id = $request->unique_id;

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
                    'message' => 'Customer ID required'
                ]);
            }

            $cartData = DB::table('product_cart')
                ->where('customer_id', $customer_id)
                ->get();

            if ($cartData->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cart Data Not Found'
                ]);
            }
            $allInStock = true;
            $outOfStockProducts = [];
            foreach ($cartData as $cart) {
                // dd($cart);
                $res = DB::table('product_cart')
                    ->where('product_cart_id', $cart->product_cart_id)
                    ->first();

                if (!$res) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Cart item not found.'
                    ], 404);
                }

                $quantity = $res->quantity;
                $size_id = $res->size_id;
                $product_varient_id = $res->product_varient_id;
                $stock = DB::table('product_varient_size')
                    ->where('size_id', $size_id)
                    ->where('product_varient_id', $product_varient_id)
                    ->select('stock')
                    ->first();
                $stockAmount = intval($stock->stock ?? 0);

                if ($stockAmount < $quantity) {
                    $allInStock = false;
                    $product = DB::table('products')
                        ->where('product_id', $res->product_id)
                        ->select('product_name', 'product_id')
                        ->first();

                    $outOfStockProducts[] = [
                        'product_id' => $product->product_id,
                        'product_name' => $product->product_name,
                        // 'requested_quantity' => $quantity,
                        'available_stock' => $stockAmount,
                    ];
                }
            }
            return response()->json([
                'status' => $allInStock,
                'out_of_stock_products' => $outOfStockProducts,
            ]);

        }

        public function customer_add_review(Request $request)
        {
            if (!Auth::guard('customer_api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                "unique_id" => 'required'
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
            $product_id = $request->product_id;

            $revData = DB::table('product_reviews')
                ->where('customer_id', $customer_id)
                ->where('product_id', $product_id)
                ->first();

            if (!is_null($revData)) {
                $content = DB::table('product_review_content')
                    ->where('product_review_id', $revData->product_review_id)
                    ->get();

                $ar = ["review" => $revData, "images" => $content];

                return response()->json([
                    'status' => true,
                    'review_data' => $ar
                ]);
            }

            $deliveredProduct = DB::table('ordered_products')
                ->where('customer_id', $customer_id)
                ->where('product_id', $product_id)
                ->where('product_order_status', 'Delivered')
                ->first();

            if (is_null($deliveredProduct)) {
                return response()->json([
                    'status' => false,
                    'message' => "Haven't purchased this product? Sorry! You are not allowed to review this product since you haven't bought it."
                ]);
            } else {
                return response()->json([
                    'status' => true,
                    'message' => "You are allowed to review this product."
                ]);
            }
        }

        public function customer_add_review_data(Request $request)
        {
            if (!Auth::guard('customer_api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                "unique_id" => 'required',
                "product_unique_id" => 'required',
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

            $product=DB::table('products')
            ->where('product_unique_id',$request->product_unique_id)
            ->first();
            // $customer_id = $request->customer_id;
            $product_id = $product->product_id;
            $rating = $request->review['rating'];
            $comment = $request->review['comment'];
            $images = $request->review['images'] ?? [];

            $validator = Validator::make($request->all(), [
                'customer_id' => 'required',
                'product_id' => 'required',
                'review.rating' => 'required',
                'review.comment' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Some fields are missing.',
                ]);
            }

            $currentDateTime = Carbon::now('Asia/Kolkata');
            $insertDate = $currentDateTime->toDateString();
            $insertTime = $currentDateTime->toTimeString();

            $existingProductReview = DB::table('product_reviews')
                ->where('product_id', $product_id)
                ->where('customer_id', $customer_id)
                ->first();

            if ($existingProductReview) {
                DB::table('product_reviews')
                    ->where('product_review_id', $existingProductReview->product_review_id)
                    ->update([
                        'rating' => $rating,
                        'comment' => $comment,
                        'inserted_date' => $insertDate,
                        'inserted_time' => $insertTime,
                    ]);

                if (is_array($images) && count($images) > 0) {
                    DB::table('product_review_content')
                        ->where('product_review_id', $existingProductReview->product_review_id)
                        ->delete();

                    foreach ($images as $image) {
                        DB::table('product_review_content')
                            ->insert([
                                'product_review_id' => $existingProductReview->product_review_id,
                                'img_video' => $image,
                            ]);
                    }
                }

                return response()->json([
                    'status' => true,
                    'message' => 'Review updated successfully!',
                    'data' => [
                        'product_review_id' => $existingProductReview->product_review_id,
                        'rating' => $rating,
                        'comment' => $comment,
                        'images' => $images,
                    ],
                ]);
            } else {
                $productReviewId = DB::table('product_reviews')
                    ->insertGetId([
                        'customer_id' => $customer_id,
                        'product_id' => $product_id,
                        'rating' => $rating,
                        'comment' => $comment,
                        'status' => 'Active',
                        'inserted_date' => $insertDate,
                        'inserted_time' => $insertTime,
                    ]);

                if (is_array($images) && count($images) > 0) {
                    foreach ($images as $image) {
                        DB::table('product_review_content')
                            ->insert([
                                'product_review_id' => $productReviewId,
                                'img_video' => $image,
                            ]);
                    }
                }

                return response()->json([
                    'status' => true,
                    'message' => 'Review added successfully!',
                    'data' => [
                        'product_review_id' => $productReviewId,
                        'rating' => $rating,
                        'comment' => $comment,
                        'images' => $images,
                    ],
                ]);
            }
        }

        public function add_customer_product_review(Request $request)
        {
            if (!Auth::guard('customer_api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                "unique_id" => 'required',
                "product_unique_id" => 'required',
                'rating' => 'required',
                'comment' => 'nullable',
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

            $product=DB::table('products')
            ->where('product_unique_id',$request->product_unique_id)
            ->first();
            // $request->validate([
            //     'customer_id' => 'required',
            //     'product_id' => 'required',
            //     'rating' => 'required',
            //     'comment' => 'nullable',
            // ]);
            // $customer_id = $request->customer_id;
            $product_id = $product->product_id;


            $currentDateTime = Carbon::now('Asia/Kolkata');
            $insertDate = $currentDateTime->toDateString();
            $insertTime = $currentDateTime->toTimeString();

            $data = [
                'customer_id' => $customer_id,
                'product_id' => $product_id,
                'rating' => $request->rating,
                'comment' => $request->comment,
                'status' => 'Active',
                'inserted_date' => $insertDate,
                'inserted_time' => $insertTime
            ];


            DB::table('product_reviews')
                ->insert($data);
            return response()->json([
                'status' => true,
                'message' => 'Review Added Succesfully',
            ]);
        }



        public function customer_apply_coupon(Request $request)
        {
            if (!Auth::guard('customer_api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                "unique_id" => 'required',
                "coupon_code" => 'required',
                'countryCode' => 'nullable',
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
            $coupon_code = $request->coupon_code;
            $countryCode = $request->countryCode;

            $currencyInfo = $this->getCurrencyInfo($countryCode);

            $rate = $currencyInfo['rate'] ?? '1';
            $currencysymbol = $currencyInfo['symbol'] ?? '$';

            if (!$customer_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Customer Id Required!'
                ]);
            }

            $coupandata = DB::table('coupon')
                ->where('coupon_code', $coupon_code)
                ->first();

            if (!$coupandata) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid Coupon Code.',
                ]);
            }

            $CustomercartData = DB::table('product_cart')
                ->where('customer_id', $customer_id)
                ->get();


            if ($CustomercartData->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Your Cart is Empty Please add product'
                ]);
            }



            $coupon_id = $coupandata->coupon_id;

            // $existingApplied = DB::table('customer_coupon')
            //     ->where('customer_id', $customer_id)
            //     ->where('coupon_id', $coupon_id)
            //     ->where('status', 'Applied')
            //     ->first();

            // if ($existingApplied) {
            //     return response()->json([
            //         'status' => false,
            //         'message' => 'Coupon already applied.',
            //     ]);
            // }

            $currentDate = now()->format('Y-m-d');
            $liveCoupons = DB::table('coupon')
                ->where('start_date', '<=', $currentDate)
                ->where('expire_date', '>=', $currentDate)
                ->pluck('coupon_id')
                ->toArray();

            if (!in_array($coupon_id, $liveCoupons)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid or expired coupon.',
                ]);
            }

            $cartTotal = DB::table('product_cart')
                ->where('customer_id', $customer_id)
                ->sum('total');

                // dd($cartTotal);

            $couponDetails = DB::table('coupon')
                ->where('coupon_id', $coupon_id)
                ->first();

            // $couponDiscount = $couponDetails->amount;
            $couponDiscount = sprintf('%.2f', ($couponDetails->amount) * $rate);
            $cartTotal = sprintf('%.2f', ($cartTotal) * $rate);

            // dd($cartTotal);
    //

            // $coupon_cart_min_amount = $couponDetails->cart_min_amount;
            $coupon_cart_min_amount = sprintf('%.2f', ($couponDetails->cart_min_amount) * $rate);
            // dd($coupon_cart_min_amount);




            if ($cartTotal >= $coupon_cart_min_amount) {
                $DiscountedcartTotal = (float) $cartTotal - (float) $couponDiscount;

                $currentDateTime = Carbon::now('Asia/Kolkata');
                $insertDate = $currentDateTime->toDateString();
                $insertTime = $currentDateTime->toTimeString();

                DB::table('customer_coupon')
                    ->insert([
                        'customer_id' => $customer_id,
                        'coupon_code' => $couponDetails->coupon_code,
                        'coupon_name' => $couponDetails->coupon_name,
                        'amount' => $couponDetails->amount,
                        'status' => 'Applied',
                        'coupon_id' => $coupon_id,
                        'inserted_date' => $insertDate,
                        'inserted_time' => $insertTime,
                    ]);

                return response()->json([
                    'status' => true,
                    'message' => 'Coupon Applied Successfully!',
                    'total_amount' => $cartTotal,
                    'discounted_total' => $DiscountedcartTotal,
                    'coupon_amount' => $couponDiscount,
                    'coupon_id' => $coupon_id,
                    'symbol' => $currencysymbol,
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Cart amount must be at least ' . $coupon_cart_min_amount . ' to apply this coupon.',
                    // 'message' => 'Cart amount must be at least ' . $couponDetails->cart_min_amount . ' to apply this coupon.',
                ]);
            }


        }

        public function customer_add_card_details(Request $request)
        {
            if (!Auth::guard('customer_api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                "unique_id" => 'required',
                'card_name' => 'required',
                'card_number' => 'required',
                'expiry_date' => 'required',
                'cardholder_name' => 'required|string|max:100',

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

            // $rules = [
            //     'card_name' => 'required',
            //     'card_number' => 'required',
            //     'expiry_date' => 'required',
            //     'cardholder_name' => 'required|string|max:100',
            // ];

            // $validator = Validator::make($request->all(), $rules);

            // if ($validator->fails()) {
            //     $firstErrorMessage = $validator->errors()->first();
            //     return response()->json([
            //         'status' => false,
            //         'message' => $firstErrorMessage
            //     ], 422);
            // }

            $validatedData = $validator->validated();
            $currentDateTime = Carbon::now('Asia/Kolkata');
            $insertDate = $currentDateTime->toDateString();
            $insertTime = $currentDateTime->toTimeString();

            $duplicateCard = DB::table('customer_card_details')
                ->where('card_number', $validatedData['card_number'])
                ->first();

            // If card number already exists, return an error
            if ($duplicateCard) {
                return response()->json([
                    'status' => false,
                    'message' => 'This card number already exists in the system.'
                ], 409);
            }

            // Check if customer_card_details_id is provided for update
            if ($request->has('customer_card_details_id')) {
                $customer_card_details_id = $request->customer_card_details_id;

                $updatedRows = DB::table('customer_card_details')
                ->where('customer_card_details_id', $customer_card_details_id)
                ->update([
                    'card_name' => $validatedData['card_name'],
                    'card_number' => $validatedData['card_number'],
                    'expiry_date' => $validatedData['expiry_date'],
                    'cardholder_name' => $validatedData['cardholder_name'],
                    'inserted_date' => $insertDate,
                    'inserted_time' => $insertTime,
                ]);

            if ($updatedRows > 0) {
                return response()->json([
                    'status' => true,
                    'message' => 'Card details updated successfully!'
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'No records updated. Please check the card details ID.'
                ], 404);
            }
            } else {

                $data = [
                    'card_name' => $validatedData['card_name'],
                    'card_number' => $validatedData['card_number'],
                    'expiry_date' => $validatedData['expiry_date'],
                    'cardholder_name' => $validatedData['cardholder_name'],
                    'customer_id' => $customer_id,
                    'inserted_date' => $insertDate,
                    'inserted_time' => $insertTime,
                ];

                DB::table('customer_card_details')->insert($data);

                return response()->json([
                    'status' => true,
                    'message' => 'Card details added successfully!'
                ]);
            }
        }

        public function get_card_details(Request $request)
        {
            if (!Auth::guard('customer_api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                "unique_id" => 'required',
                // "product_id" => 'required',
                // 'rating' => 'required',
                // 'comment' => 'nullable',
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


            $cardDetails = DB::table('customer_card_details')
                ->where('customer_id', $customer_id)
                ->get();

            if (!$cardDetails) {
                return response()->json([
                    'status' => false,
                    'message' => 'No card details found for this customer.'
                ], 404);
            }
            $cardDetailsArray = [];

            foreach ($cardDetails as $cardDetail) {
                $cardDetailsArray[] = [
                    'customer_card_details_id' => $cardDetail->customer_card_details_id,
                    'card_name' => $cardDetail->card_name,
                    'card_number' => '**** **** **** ' . substr($cardDetail->card_number, -4),
                    'expiry_date' => $cardDetail->expiry_date,
                    'cardholder_name' => $cardDetail->cardholder_name,
                ];
            }



            return response()->json([
                'status' => true,
                'data' => $cardDetailsArray
            ]);
        }
        public function checkout(Request $request)
        {

            if (!Auth::guard('customer_api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                "unique_id" => 'required',
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
            $customerId = $customerData->customer_id;

            // $customerId = $request->customer_id;
            $countryCode = $request->countryCode;

            if (!$customerId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Customer ID is required.'
                ], 400);
            }

            $quantityCheckResponse = $this->check_quantity($request);
            if ($quantityCheckResponse->getData()->status === false) {
                return response()->json($quantityCheckResponse->getData());
            }


            $customerCartProductData = DB::table('product_cart')
                ->where('customer_id', $customerId)
                ->where('stock_status', '1')
                ->select('product_cart.*')
                ->get();

            if ($customerCartProductData->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => "Please add products to your cart."
                ]);
            }

            $cartItems = [];
            $totalAmount = 0;

            $currencyInfo = $this->getCurrencyInfo($countryCode);
            $rate = $currencyInfo['rate'] ?? '1';
            $currencysymbol = $currencyInfo['symbol'] ?? '$';


            foreach ($customerCartProductData as $cartData) {

                $productData = DB::table('products')
                                ->where('product_id',$cartData->product_id)
                                ->select('product_name', 'description','category_id')
                                ->first();
                $ar = [];

                $categorydata = DB::table('category')
                                ->where('category_id',$productData->category_id)
                                // ->select('category_name', 'description')
                                ->first();
                // dd($categorydata);

                $ar['product_cart_id'] = $cartData->product_cart_id;
                $ar['product_name'] = $productData->product_name;
                $ar['product_id'] = $cartData->product_id;
                $ar['product_varient_id'] = $cartData->product_varient_id;
                $ar['added_quantity'] = $cartData->quantity;
                $ar['category_name'] = $categorydata->category_name;
                    $varientData = DB::table('product_varient')
                        ->where('product_varient_id', $cartData->product_varient_id)
                        ->first();
                    // dd($varientData);

                    $varientImage = DB::table('product_varient_images')
                        ->where('product_varient_id', $cartData->product_varient_id)
                        ->first();
                    // dd($varientImage);

                    $sizeData = DB::table('product_varient_size')
                        ->leftJoin('sizes', 'product_varient_size.size_id', '=', 'sizes.size_id')
                        // ->where('product_varient_id', $cartData->product_varient_id)
                        ->where('product_varient_size.size_id', $cartData->size_id)
                        ->select('sizes.size_name', 'product_varient_size.stock', 'product_varient_size.size_id')
                        ->first();
                    // dd($sizeData);
                    // dd($rate);

                    $convertedPrice = sprintf('%.2f', ($varientData->price) * $rate);
                    // $convertedproduct_total= sprintf('%.2f', ($cartData->total) * $rate);
                    // dd( $convertedPrice);
                    $varientData->price = $convertedPrice;
                    // $cartData->total=$convertedproduct_total;
                    $varientData->symbol = $currencysymbol;



                    $ar['images'] = $varientImage->images ?? null;
                    $ar['product_size_name'] = $sizeData->size_name ?? null;
                    $ar['colour'] = $varientData->colour ?? null;
                    $ar['price'] = $varientData->price ?? 0;
                    $ar['product_quantity_left'] = $sizeData->stock ?? 0;
                    $ar['size_id'] = $sizeData->size_id ?? null;
                    $ar['symbol']=$currencysymbol??'';
                    // $ar['stock'] = $sizeData->quantity ?? null;
                    // $ar['product_total'] = $cartData->total ?? null;

                // $totalAmount = $totalAmount + $cartData->total;
                $totalAmount +=  $ar['price'] * $ar['added_quantity'];
                // dd($totalAmount);
                // $totalAmount = sprintf('%.2f', $totalAmount * $rate);


                // dd($totalAmount);

                $cartItems[] = $ar;
            }


            $deliveryAddress = DB::table('customers_delivery_address')
                ->leftjoin('countries', 'customers_delivery_address.country', '=', 'countries.country_id')
                ->leftjoin('states', 'customers_delivery_address.state', '=', 'states.state_subdivision_id')
                ->where('customer_id', $customerId)
                ->select(
                    'customers_delivery_address.*',
                    'countries.country_name',
                    'states.state_subdivision_name',
                )
                ->get();



            if (!$deliveryAddress) {
                return response()->json([
                    'status' => false,
                    'message' => 'No Address details found for this customer.'
                ], 404);
            }

            $cardDetails = DB::table('customer_card_details')
                ->where('customer_id', $customerId)
                ->get();

            if (!$cardDetails) {
                return response()->json([
                    'status' => false,
                    'message' => 'No card details found for this customer.'
                ], 404);
            }
            $cardDetailsArray = [];

            foreach ($cardDetails as $cardDetail) {
                $cardDetailsArray[] = [
                    'customer_card_details_id' => $cardDetail->customer_card_details_id,
                    'card_name' => $cardDetail->card_name,
                    'card_number' => '**** **** **** ' . substr($cardDetail->card_number, -4),
                    'expiry_date' => $cardDetail->expiry_date,
                    'cardholder_name' => $cardDetail->cardholder_name,
                ];
            }




            if ($currencyInfo) {

                return response()->json([
                    'status' => true,
                    'cartItems' => $cartItems,
                    'cartCount' => count($cartItems),
                    'total_amount' => $totalAmount,
                    'symbol' => $currencysymbol??"$",
                    'delivery_address' => $deliveryAddress,
                    'card_details' => $cardDetailsArray,
                ]);
            } else {

                return response()->json([
                    'status' => true,
                    'cartItems' => $cartItems,
                    'cartCount' => count($cartItems),
                    'total_amount' => $totalAmount,
                    'delivery_address' => $deliveryAddress,
                    'card_details' => $cardDetailsArray,
                    // 'currency_rates' => $currencyData['rates'],
                ]);

            }
        }

        public function delete_cart_product(Request $request)
        {
            if (!Auth::guard('customer_api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                "unique_id" => 'nullable',
                "product_cart_id" => 'nullable',

            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ], 400);
            }

            $customer = Auth::guard('customer_api')->user();

            $customerUniqueId = $request->unique_id;
            $cartId = $request->product_cart_id;


            // if ($customer->unique_id !== $customer_unique_id) {
            //     return response()->json([
            //         'status' => false,
            //         'message' => 'Customer unique ID does not match.',
            //     ], 400);
            // }
            if (!empty($customerUniqueId)) {
                $customerData = DB::table('customers')
                    ->where('unique_id', $customerUniqueId)
                    ->first();

                if (!$customerData) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Customer not found.'
                    ], 404);
                }

                $deleted = DB::table('product_cart')
                    ->where('customer_id', $customerData->customer_id)
                    ->delete();

                if ($deleted) {
                    return response()->json([
                        'status' => true,
                        'message' => 'All cart items cleared for this customer.'
                    ]);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'No cart data found for this customer.'
                    ]);
                }
            }

            // If only product_cart_id is provided
            if (!empty($cartId)) {
                $deleted = DB::table('product_cart')
                    ->where('product_cart_id', $cartId)
                    ->delete();

                if ($deleted) {
                    return response()->json([
                        'status' => true,
                        'message' => 'Cart item deleted successfully.'
                    ]);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Cart item not found.'
                    ]);
                }
            }
            // $customerData=DB::table('customers')
            // ->where('unique_id',$customer_unique_id)
            // ->first();
            // $customer_id = $customerData->customer_id;

            // $cartId = $request->product_cart_id;
            // // $customer_id = $request->customer_id;

            // if (empty($customer_id) && empty($cartId)) {
            //     return response()->json([
            //         'status' => false,
            //         'message' => 'Customer ID or Cart ID must be provided.'
            //     ], 400);
            // }

            // if (!empty($customer_id)) {
            //     $allDeleted = DB::table('product_cart')
            //         ->where('customer_id', $customer_id)
            //         ->delete();

            //     if ($allDeleted) {
            //         return response()->json([
            //             'status' => true,
            //             'message' => 'All Cart items cleared successfully.'
            //         ]);
            //     } else {
            //         return response()->json([
            //             'status' => false,
            //             'message' => 'No Cart Data found for this customer.'
            //         ], 404);
            //     }
            // }
            // if (!empty($cartId)) {
            //     $delete = DB::table('product_cart')
            //         ->where('product_cart_id', $cartId)
            //         ->delete();

            //     if ($delete) {
            //         return response()->json([
            //             'status' => true,
            //             'message' => 'Product deleted successfully from cart.'
            //         ]);
            //     } else {
            //         return response()->json([
            //             'status' => false,
            //             'message' => 'Product Cart Data not found.'
            //         ], 404);
            //     }
            // }
        }

        public function place_order(Request $request)
        {
            if (!Auth::guard('customer_api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ], 400);
            }



            $validator = Validator::make($request->all(), [
                // 'customer_id' => 'required',
                'unique_id' => 'required',
                'payment_method' => 'nullable', // adjust valid methods
                'customer_delivery_address_id' => 'required',
                'coupon_id' => 'nullable',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first()
                ]);
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
            $payment_method = $request->payment_method;
            $customer_delivery_address_id = $request->customer_delivery_address_id;
            $coupon_id = $request->coupon_id;

            $currentDateTime = Carbon::now('Asia/Kolkata');
            $insertDate = $currentDateTime->format('Y-m-d'); // Ensures the date is in YYYY-MM-DD format
            $insertTime = $currentDateTime->format('H:i:s');

            if (!$customer_id || !$payment_method || !$customer_delivery_address_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Customer ID, payment method, and delivery address are required!'
                ]);
            }
            $order_unique_id = $this->generateUniqueId();
            $orderId = DB::table('orders')
                ->insertGetId([
                    'order_status' => 'Pending',
                    'customer_id' => $customer_id,
                    'order_unique_id' => $order_unique_id,
                    'payment_method' => $payment_method,
                    'coupon_id' => $coupon_id,
                    'customer_delivery_address_id' => $customer_delivery_address_id,
                    'inserted_date' => $insertDate,
                    'inserted_time' => $insertTime,
                ]);


            $cartData = DB::table('product_cart')
                ->where('customer_id', $customer_id)
                ->get();


        if ($cartData->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No products in the cart to place an order.'
            ]);
        }
        $inserted = true;
        $totalAmount = 0;
        $cartTotal = 0;
        $ar =[];
        $product_varient_quantity=0;
        $quantity=0;
            foreach ($cartData as $cart) {


                $cartTotal += $cart->total;

                $products= DB::table('products')
                        ->where('product_id',$cart->product_id)
                        ->first();

                        $user= $products->user_id;
                        // dd($user);
                $data = [
                    'order_id' => $orderId,
                    'product_order_status' => '',
                    'product_varient_id' => $cart->product_varient_id,
                    'product_id' => $cart->product_id,
                    'user_id' => $user,
                    'size_id' => $cart->size_id,
                    'quantity' => $cart->quantity,
                    'price' => $cart->price,
                    'total' => $cart->total,
                    'customer_id' => $cart->customer_id,
                    'inserted_date' => $insertDate,
                    'inserted_time' => $insertTime,
                ];
              $inserted=  DB::table('ordered_products')
                    ->insert($data);

               $product_varient= DB::table('product_varient')
               ->where('product_varient_id',$cart->product_varient_id)
               ->first();
                $product_varient_quantity=$product_varient->quantity;
               $quantity = $product_varient->quantity - $cart->quantity;
                  //    dd($quantity,$producr_varient_quantity);

            DB::table('product_varient')
               ->where('product_varient_id',$cart->product_varient_id)
               ->update([
                'quantity'=>$quantity
               ]);
               $product_varient_size=  DB::table('product_varient_size')
               ->where('product_varient_id',$cart->product_varient_id)
               ->where('size_id',$cart->size_id)
               ->first();

               $product_varient_size_stock = $product_varient_size->stock;
               $size_quantity=$product_varient_size_stock - $cart->quantity;

                     DB::table('product_varient_size')
                    ->where('product_varient_id',$cart->product_varient_id)
                    ->where('size_id',$cart->size_id)
                    ->update([
                    'stock'=>$size_quantity ]);

                    if (!$inserted) {
                        return response()->json([
                            'status' => false,
                            'message' => 'Failed to place order for some products.'
                        ], 500);
                    }
            }


            // dd($ar);

            if ($coupon_id) {
                $coupon= DB::table('coupon')
                    ->where('coupon_id', $coupon_id)

                    ->first();
                    $coupon_amount = $coupon->amount;
                    $totalAmount = $cartTotal - $coupon_amount;
            }
            else{
                $totalAmount += $cartTotal;
            }



            DB::table('orders')
            ->where('order_id',$orderId)
            ->update([
             'amount'=>$totalAmount,
            ]) ;


            DB::table('product_cart')->where('customer_id', $customer_id)->delete();

            return response()->json([
                'status' => true,
                'message' => 'Order placed successfully.',
                'order_id' => $orderId,
                'order_unique_id' => $order_unique_id,
                'total' => $totalAmount,
                'Cart' => $cartTotal,
            ]);

        }


        public function cart_quantity_add(Request $request)
        {
            if (!Auth::guard('customer_api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                "product_cart_id" => 'required',
                  ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ], 400);
            }

            $product_cart_id = $request->product_cart_id;

            if(!$product_cart_id){
                return response()->json([
                    'status'=>false,
                    'message'=>'product cart id required!'
                ]);
            }

            $res = DB::table('product_cart')
                ->where('product_cart_id', $product_cart_id)
                ->first();

            if (!$res) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cart item not found.'
                ], 404);
            }

            $price = $res->price;
            $quantity = $res->quantity;
            $customer_id = $res->customer_id;
            $size_id=$res->size_id;
            $product_varient_id=$res->product_varient_id;

            $stock = DB::table('product_varient_size')
                ->where('size_id',$size_id)
                ->where('product_varient_id',$product_varient_id)
                ->select('stock')
                ->first();

            // dd($stock);
            $stockAmount = intval($stock->stock ?? 0);

            if($stockAmount>$quantity){

                DB::table('product_cart')
                ->where('product_cart_id', $product_cart_id)
                ->update([
                    'quantity' => $quantity + 1,
                    'total' => $price * ($quantity + 1)
                ]);

                $cartTotal = DB::table('product_cart')
                ->where('customer_id', $customer_id)
                ->sum('total');

            return response()->json([
                'status' => true,
                'quantity' => $quantity + 1,
                'product_total' => $price * ($quantity + 1),
                'cart_total' => $cartTotal
            ]);

            }
            else{
                return response()->json([
                    'status' => false,
                    'message' => 'You have reached the maximum quantity of ' . $stockAmount . ' Product  available.'

                ]);
            }

        }

        public function cart_quantity_subtract(Request $request)
        {
            if (!Auth::guard('customer_api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                "product_cart_id" => 'required',
                  ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ], 400);
            }
            $product_cart_id = $request->product_cart_id;

            $res = DB::table('product_cart')
                ->where('product_cart_id', $product_cart_id)
                ->first();

            if (!$res) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cart item not found.'
                ], 404);
            }

            $price = $res->price;
            $quantity = $res->quantity;
            $customer_id = $res->customer_id;

            // Ensure that quantity doesn't go below 1
            if ($quantity <= 1) {
                return response()->json([
                    'status' => false,
                    'message' => 'Quantity cannot be less than 1.'
                ]);
            }

            DB::table('product_cart')
                ->where('product_cart_id', $product_cart_id)
                ->update([
                    'quantity' => $quantity - 1,
                    'total' => $price * ($quantity - 1)
                ]);

            $cartTotal = DB::table('product_cart')
                ->where('customer_id', $customer_id)
                ->sum('total');

            return response()->json([
                'status' => true,
                // 'message' => 'Updated',
                'quantity' => $quantity - 1,
                'product_total' => $price * ($quantity - 1),
                'cart_total' => $cartTotal
            ]);
        }


        public function mobile_otp(Request $request)
        {
            if (!Auth::guard('customer_api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                "mobile" => 'required',
                  ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ], 400);
            }
          $mobile=$request->mobile;

            if (!$mobile) {
                return response()->json([
                    'status' => false,
                    'message' => 'Mobile number is required.'
                ]);
            }

            // Fetch customer record based on mobile number
            $customer = DB::table('customers')
                          ->where('mobile', $mobile)
                          ->first();

            $otp = rand(1000, 9999);  // Generate a random OTP

            if (is_null($customer)) {
                $response = [
                    'status' => false,
                    'message' => 'Customer Not Found ...'
                ];
            } else {
                // Update customer record with OTP
                DB::table('customers')
                  ->where('mobile', $mobile)
                  ->update(['mobile_otp' => $otp]);

                $response = [
                    'status' => true,
                    'otp' => $otp,
                    'customer_id' => $customer->customer_id,
                    'message' => 'Customer Found ...'
                ];
            }

            // Send response as JSON
            return response()->json($response);
        }

        public function email_otp(Request $request)
        {

            if (!Auth::guard('customer_api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                "email" => 'required',
                  ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ], 400);
            }
            $email = $request->email;

            if (!$email) {
                return response()->json([
                    'status' => false,
                    'message' => 'Email address is required.'
                ]);
            }

            // Fetch customer record based on email address
            $customer = DB::table('customers')
                          ->where('email', $email)
                          ->first();

            $otp = rand(1000, 9999);  // Generate a random OTP

            if (is_null($customer)) {
                $response = [
                    'status' => false,
                    'message' => 'Customer Not Found ...'
                ];
            } else {
                // Update customer record with OTP
                DB::table('customers')
                  ->where('email', $email)
                  ->update(['email_otp' => $otp]);

                $response = [
                    'status' => true,
                    'otp' => $otp,
                    'customer_id' => $customer->customer_id,
                    'message' => 'Customer Found ...'
                ];
            }

            // Send response as JSON
            return response()->json($response);
        }

        public function mobile_otp_validate(Request $request)
        {
            if (!Auth::guard('customer_api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                "mobile" => 'required',
                "otp" => 'required',
                  ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ], 400);
            }
            $mobile = $request->mobile;
            $otp = $request->otp;

            if (!$mobile || !$otp) {
                return response()->json([
                    'status' => false,
                    'message' => 'Mobile number and OTP are required.'
                ]);
            }

            // Query the customer table to find a match for the mobile and OTP
            $customer = DB::table('customers')
                          ->select('customer_id', 'first_name', 'last_name', 'mobile', 'email')
                          ->where('mobile', $mobile)
                          ->where('mobile_otp', $otp)
                          ->first();

            if (is_null($customer)) {
                $response = [
                    'status' => false,
                    'message' => 'Invalid OTP ...'
                ];
            } else {
                // Update the customer's verify_mobile status
                DB::table('customers')
                  ->where('mobile', $mobile)
                  ->update(['verify_mobile' => 'Yes']);

                // Fetch updated customer details
                $customer = DB::table('customers')
                              ->select('customer_id', 'first_name', 'last_name', 'mobile', 'email')
                              ->where('customer_id', $customer->customer_id)
                              ->first();

                // Prepare success response
                $response = [
                    'status' => true,
                    'customer_id' => $customer->customer_id,
                    'message' => 'Customer Found ...',
                    'customer_data' => $customer
                ];
            }

            // Send response as JSON
            return response()->json($response);
        }




        public function email_otp_validate(Request $request)
        {

            if (!Auth::guard('customer_api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                "email" => 'required',
                "otp" => 'required',
                  ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ], 400);
            }
            $email = $request->email;
            $otp = $request->otp;

            // Validate input
            if (!$email || !$otp) {
                return response()->json([
                    'status' => false,
                    'message' => 'Email and OTP are required.'
                ]);
            }

            // Query the customer table to find a match for the email and OTP
            $customer = DB::table('customers')
                          ->select('customer_id', 'first_name', 'last_name', 'mobile', 'email')
                          ->where('email', $email)
                          ->where('email_otp', $otp)
                          ->first();

            if (is_null($customer)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid OTP ...'
                ]);
            } else {
                // Update the customer's verify_email status
                DB::table('customers')
                  ->where('email', $email)
                  ->update(['verify_email' => 'Yes']);

                // Fetch updated customer details
                $customer = DB::table('customers')
                              ->select('customer_id', 'first_name', 'last_name', 'mobile', 'email')
                              ->where('customer_id', $customer->customer_id)
                              ->first();

                // Prepare success response
                return response()->json([
                    'status' => true,
                    'customer_id' => $customer->customer_id,
                    'message' => 'Customer Found ...',
                    'customer_data' => $customer
                ]);
            }
        }

        public function mobile_pass_login(Request $request)
        {
            if (!Auth::guard('customer_api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ], 400);
            }
            // Validate the incoming request data
            $validated = $request->validate([
                'mobile' => 'required|string',
                'password' => 'required|string', // Assuming password is required
            ]);


            $mobile = $validated['mobile'];
            $password = $validated['password'];

            // Find the customer by mobile number
            $customer = DB::table('customers')->where('mobile', $mobile)->first();

            // Check if customer exists
            if (!$customer) {
                // If customer does not exist
                return response()->json([
                    'status' => false,
                    'message' => 'Mobile Number Not Exist ...'
                ]);
            }

            // Check if the password matches
            if (!Hash::check($password, $customer->password)) {
                // If password does not match
                return response()->json([
                    'status' => false,
                    'message' => 'Password Mismatch ...'
                ]);
            }

            // If everything is correct, return customer data
            return response()->json([
                'status' => true,
                'customer_id' => $customer->customer_id,
                'customer_data' => [
                    'first_name' => $customer->first_name,
                    'last_name' => $customer->last_name,
                    'mobile' => $customer->mobile,
                    'email' => $customer->email,
                    'gender' => $customer->gender
                ],
                'message' => 'Customer Found ...'
            ]);
        }



        public function add_delivery_address(Request $request)
        {

            if (!Auth::guard('customer_api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                "unique_id" => 'required',
                'customers_delivery_address_id'=>'nullable'

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


            date_default_timezone_set('Asia/Kolkata');
            $currentDateTime = Carbon::now('Asia/Kolkata');
            $insertDate = $currentDateTime->toDateString();
            $insertTime = $currentDateTime->toTimeString();

            $customer_delivery_address_id = $request->customers_delivery_address_id;

            $insertedRow = [
                'customer_id' => $customer_id,
                'country' => $request->input('country_id'),
                'state' => $request->input('state_subdivision_id'),
                'city' => $request->input('city'),
                'address1' => $request->input('address1'),
                'address2' => $request->input('address2'),
                'address_type' => $request->input('address_type'),
                'locality' => $request->input('locality'),
                'pincode' => $request->input('pincode'),
                'inserted_date' => $insertDate,
                'inserted_time' => $insertTime,
                'status' => 'Active'
            ];

            if ($customer_delivery_address_id) {

              $updated=  DB::table('customers_delivery_address')

              ->where('customers_delivery_address_id',$customer_delivery_address_id)
              ->update($insertedRow);

            if($updated){
                return response()->json([
                    'status'=>true,
                    'message'=>'Address Updated Successfully'
                ]);
            }
            else{
                return response()->json([
                    'status'=>false,
                    'message'=>'Faild to update address'
                ]);
            }
            }
            else{
              $add = DB::table('customers_delivery_address')->insertGetId($insertedRow);

                if($add){
                    return response()->json([
                        'status'=>true,
                        'message'=>'Address Added Successfully',
                        'customers_delivery_address_id'=>$add
                    ]);
                }
                else{
                    return response()->json([
                        'status'=>false,
                        'message'=>'Faild to Add address'
                    ]);
                }

            }




        }

        public function update_delivery_address(Request $request)
        {
            if (!Auth::guard('customer_api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ], 400);
            }


            $validator = Validator::make($request->all(), [
                    'customers_delivery_address_id' => 'required|exists:customers_delivery_address,customers_delivery_address_id',
                    'unique_id' => 'required',
                    'country_id' => 'required',
                    'state_subdivison_id' => 'required',
                    'city' => 'required|string|max:255',
                    'address1' => 'required|string|max:500',
                    'address2' => 'nullable|string|max:500',
                    'address_type' => 'required|in:Home,Work,Other',
                    'locality' => 'required|string|max:255',
                    'pincode' => 'required|digits:6',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            date_default_timezone_set('Asia/Kolkata');
            $currentDateTime = now();
            $insertDate = $currentDateTime->toDateString();
            $insertTime = $currentDateTime->toTimeString();
            $customersDeliveryAddressId = $request->input('customers_delivery_address_id');
            if (!$customersDeliveryAddressId) {
                return response()->json([
                    'status' => false,
                    'message' => 'ID Required!'
                ]);
            }
            $customers=DB::table('customers')->where('unique_id',$request->unique_id)->first();
            if(!$customers){
                return response()->json([
                    'status'=>false,
                    'message'=>'No customers Found'
                ]);
            }

            $updateData = [
                'customer_id' => $customers->customer_id,
                'country' => $request->input('country_id'),
                'state' => $request->input('state_subdivison_id'),
                'city' => $request->input('city'),
                'address1' => $request->input('address1'),
                'address2' => $request->input('address2'),
                'address_type' => $request->input('address_type'),
                'locality' => $request->input('locality'),
                'pincode' => $request->input('pincode'),
                'inserted_date' => $insertDate,
                'inserted_time' => $insertTime,
                'status' => 'Active'
            ];

            $affectedRows = DB::table('customers_delivery_address')
                ->where('customers_delivery_address_id', $customersDeliveryAddressId)
                ->update($updateData);

            if ($affectedRows) {
                return response()->json([
                    'status' => true,
                    'message' => 'Address Updated Successfully'
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to Update Address or No Changes Made'
                ]);
            }
        }


        public function delete_product_review(Request $request)
        {
            if (!Auth::guard('customer_api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ], 400);
            }


            $validator = Validator::make($request->all(), [

                'product_review_id' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            $deletedRows = DB::table('product_reviews')
                ->where('product_review_id', $request->input('product_review_id'))
                ->delete();

            if ($deletedRows) {
                return response()->json([
                    'status' => true,
                    'message' => 'Review Deleted Successfully'
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to Delete Review or Review Not Found'
                ]);
            }
        }

        public function update_product_review(Request $request)
        {
            if (!Auth::guard('customer_api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ], 400);
            }


            $validator = Validator::make($request->all(), [
                  'product_review_id' => 'required',

            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data =DB::table('product_reviews')
            ->where('product_review_id', $request->input('product_review_id'))
            ->first();

            if(!$data){
                return response()->json([
                    'status' => false,
                    'message' => 'No Review found',

                ]);
            }

            $updatedRows = DB::table('product_reviews')
                ->where('product_review_id', $request->input('product_review_id'))
                ->update([
                    'rating' => $request->input('rating'),
                    'comment' => $request->input('comment')
                ]);

            if ($updatedRows) {
                return response()->json([
                    'status' => true,
                    'message' => 'Review Updated Successfully'
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to Update Review or Review Not Found'
                ]);
            }
        }

        public function add_product_review(Request $request)
        {
            if (!Auth::guard('customer_api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ], 400);
            }


            $validator = Validator::make($request->all(), [
                  'country_id' => 'required',
                  'state_subdivison_id' => 'required',
                  'city' => 'required',
                  'address1' => 'required',
                  'address2' => 'required',
                  'address_type' => 'required',
                  'pincode' => 'required',
                  'locality' => 'required',
                  'unique_id' => 'required',


            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            date_default_timezone_set('Asia/Kolkata');
            $currentDateTime = now();
            $insertDate = $currentDateTime->toDateString(); // Current date
            $insertTime = $currentDateTime->toTimeString(); // Current time
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
                $insertedRow = [
                    'customer_id' => $customer_id,
                    'country' => $request->input('country_id'),
                    'state' => $request->input('state_subdivison_id'),
                    'city' => $request->input('city'),
                    'address1' => $request->input('address1'),
                    'address2' => $request->input('address2'),
                    'address_type' => $request->input('address_type'),
                    'locality' => $request->input('locality'),
                    'pincode' => $request->input('pincode'),
                    'inserted_date' => $insertDate,
                    'inserted_time' => $insertTime,
                    'status'=>'Active'
                ];

                DB::table('customers_delivery_address')->insert($insertedRow);

            return response()->json([
                'status' => true,
                'message' => 'Address Added Successfully',
                // 'data' => $insertedData

            ]);

        }

        public function get_product_data(Request $request)
        {
            if (!Auth::guard('customer_api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                "unique_id" => 'required',
                "countryCode" => 'nullable',
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

            $countryCode = $request->countryCode;
            // $customer_id = $request->customer_id;
            $threeMonthsAgo = Carbon::now()->subMonths(3);
            $today = Carbon::today()->toDateString();
            $count = $this->viewLimit('get_product_data', $customer_id);
            $this->incrementApiCallCount('get_product_data', $customer_id);

            $limit = 10;
            $offset = ($count - 1) * $limit;

            $productData = DB::table('products')
                ->leftJoin('category', 'products.category_id', '=', 'category.category_id')
                ->leftJoin('sub_category_1', 'sub_category_1.sub_category_1_id', '=', 'products.sub_category_1_id')
                ->leftJoin('sub_category_2', 'sub_category_2.sub_category_2_id', '=', 'products.sub_category_2_id')
                ->leftJoin('sub_category_3', 'sub_category_3.sub_category_3_id', '=', 'products.sub_category_3_id')
                ->where('products.inserted_date', '>=', $threeMonthsAgo)
                ->where('products.status', 'Active')
                ->where('products.valid_date', '>=', $today)
                ->whereNotExists(function($query) use ($today) {
                    $query->select(DB::raw(1))
                        ->from('product_viewcount')
                        ->whereColumn('product_viewcount.product_id', 'products.product_id')
                        ->where('product_viewcount.date', $today)
                        ->whereRaw('CAST(product_viewcount.count AS UNSIGNED) >= CAST(products.view_per_day AS UNSIGNED)');
                })
                ->orderBy('products.product_id', 'desc')
                ->skip($offset)
                ->take($limit)
                ->get();
            $productCount = $productData->count();
            if ($productCount < $limit) {
                $additionalProducts = DB::table('products')
                    ->leftJoin('category', 'products.category_id', '=', 'category.category_id')
                    ->leftJoin('sub_category_1', 'sub_category_1.sub_category_1_id', '=', 'products.sub_category_1_id')
                    ->leftJoin('sub_category_2', 'sub_category_2.sub_category_2_id', '=', 'products.sub_category_2_id')
                    ->leftJoin('sub_category_3', 'sub_category_3.sub_category_3_id', '=', 'products.sub_category_3_id')
                    ->where('products.inserted_date', '>=', $threeMonthsAgo)
                    ->where('products.status', 'Active')
                    ->where('products.valid_date', '>=', $today)
                    ->whereNotExists(function($query) use ($today) {
                        $query->select(DB::raw(1))
                            ->from('product_viewcount')
                            ->whereColumn('product_viewcount.product_id', 'products.product_id')
                            ->where('product_viewcount.date', $today)
                            ->whereRaw('CAST(product_viewcount.count AS UNSIGNED) >= CAST(products.view_per_day AS UNSIGNED)');
                    })
                    ->orderBy('products.product_id', 'desc')
                    ->take($limit - $productCount)
                    ->get();
                $productData = $productData->merge($additionalProducts);
            }

            $MainproductsAllData = [];

            $currencyInfo = $this->getCurrencyInfo($countryCode);

            foreach ($productData as $product) {
                $this->incrementProductViewCount($product->product_id, $today);

                $productReviews = DB::table('product_reviews')
                    ->where('product_id', $product->product_id)
                    ->where('status', 'Active')
                    ->select(
                        DB::raw('AVG(product_reviews.rating) as average_rating'),
                        DB::raw('COUNT(product_reviews.product_review_id) as review_count')
                    )
                    ->groupBy('product_id')
                    ->orderBy('average_rating', 'desc')
                    ->orderBy('review_count', 'desc')
                    ->first();

                $review_count = 0;
                $formatted_Productrating = $productReviews ? number_format($productReviews->average_rating, 1) : "0";
                if ($productReviews) {
                    $review_count = $productReviews->review_count;
                }

                $productVariants = DB::table('product_varient')
                    ->where('product_id', $product->product_id)
                    ->select('product_varient_id', 'colour', 'total_amount', 'quantity')
                    ->get();

                $variantImages = DB::table('product_varient_images')
                    ->whereIn('product_varient_id', $productVariants->pluck('product_varient_id'))
                    ->select('product_varient_id', 'images')
                    ->take(2)
                    ->get();

                $sizes = DB::table('product_varient_size')
                    ->whereIn('product_varient_id', $productVariants->pluck('product_varient_id'))
                    ->leftJoin('sizes', 'product_varient_size.size_id', '=', 'sizes.size_id')
                    ->select('sizes.size_name')
                    ->distinct()
                    ->get()
                    ->pluck('size_name')
                    ->toArray();

                foreach ($productVariants as $variant) {
                    if (!isset($MainproductsAllData[$product->product_id])) {
                        $MainproductsAllData[$product->product_id] = [
                            'product_id' => $product->product_id,
                            'product_varient_id' => $variant->product_varient_id,
                            'product_name' => $product->product_name,
                            'sub_text' => $product->sub_text,
                            'product_img' => $variantImages,
                            'category_name' => $product->category_name,
                            'discount' => $product->discount,
                            'colors' => [],
                            'sizes' => $sizes,
                            'reviews_avg' => $formatted_Productrating,
                            'review_count' => $review_count,
                            'symbol' => $currencyInfo['symbol'] ?? '$'
                        ];
                    }

                    $MainproductsAllData[$product->product_id]['colors'][] = $variant->colour;

                    if (!isset($MainproductsAllData[$product->product_id]['price'])) {
                        $MainproductsAllData[$product->product_id]['price'] = isset($currencyInfo)
                            ? number_format(($variant->total_amount) * $currencyInfo['rate'], 2)
                            : number_format($variant->total_amount, 2);
                    }
                }
            }

            return response()->json([
                'status' => true,
                'MainproductsAllData' => array_values($MainproductsAllData)
            ]);
        }
}

