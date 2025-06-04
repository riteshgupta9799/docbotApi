<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
// use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProductController extends Controller
{

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
    public function get_all_product_data(Request $request)
    {


        $threeMonthsAgo = Carbon::now()->subMonths(3);

        $countryCode=$request->countryCode;
            $productData=DB::table('products')
                ->leftJoin('category', 'products.category_id', '=', 'category.category_id')
                ->leftJoin('sub_category_1', 'sub_category_1.sub_category_1_id', '=', 'products.sub_category_1_id')
                ->leftJoin('sub_category_2', 'sub_category_2.sub_category_2_id', '=', 'products.sub_category_2_id')
                ->leftJoin('sub_category_3', 'sub_category_3.sub_category_3_id', '=', 'products.sub_category_3_id')
                ->where('products.inserted_date', '>=', $threeMonthsAgo)
                ->where('products.status', 'Active')
                // ->where('products.stock_status', 'Live')
                ->orderBy('products.product_id', 'desc')
                ->select(
                    'products.*',
                    'category.category_name',
                    'sub_category_1.sub_category_1_name',
                    'sub_category_2.sub_category_2_name',
                    'sub_category_3.sub_category_3_name'
                )
                // ->skip($offset)
                // ->take($limit)
                ->get();



        $MainproductsAllData = [];

        $currencyInfo = $this->getCurrencyInfo($countryCode);


        foreach ($productData as $product) {


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

            // dd($productReviews);
            $review_count = 0;

            if ($productReviews) {
                $formatted_Productrating = number_format($productReviews->average_rating, 1);
                $review_count = $productReviews->review_count;
            } else {
                $formatted_Productrating = "0";
            }

            if ($currencyInfo) {
                $rate = $currencyInfo['rate'] ?? '1';
                $currencysymbol = $currencyInfo['symbol'] ?? '$';
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
                        'product_unique_id' => $product->product_unique_id,
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
                        'symbol' => $currencysymbol ?? "$"

                    ];


                }

                $MainproductsAllData[$product->product_id]['colors'][] = $variant->colour;

                if (!isset($MainproductsAllData[$product->product_id]['price'])) {
                    if (isset($currencyInfo)) {
                        $MainproductsAllData[$product->product_id]['price'] = sprintf('%.2f', ($variant->total_amount) * $rate);
                    } else {
                        $MainproductsAllData[$product->product_id]['price'] = sprintf('%.2f', $variant->total_amount);
                    }

                }
            }
        }
        // header("Access-Control-Allow-Origin: *");
        // header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        // header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        // header("Access-Control-Expose-Headers: Content-Disposition");

        return response()->json([
            'status' => true,
            'MainproductsAllData' => array_values($MainproductsAllData)
        ]);
    }
    public function get_one_product(Request $request)
    {
        $product_id = $request->product_id;
        $countryCode = $request->countryCode;


        $products = DB::table('products')
            ->where('product_id', $product_id)
            ->select('product_name', 'sub_text', 'description','product_unique_id', 'product_id', 'discount', 'category_id', 'sub_category_1_id', 'sub_category_2_id', 'sub_category_3_id')
            ->first();


        $variantData = DB::table('product_varient')
            ->where('product_id', $product_id)
            ->select('product_varient.colour', 'product_varient.price', 'product_varient.total_amount', 'product_varient.product_varient_id')
            ->get();

        $finalVariants = [];
        $currencyInfo = $this->getCurrencyInfo($countryCode);
        $rate = $currencyInfo['rate'] ?? 1;
        $currencysymbol = $currencyInfo['symbol'] ?? '$';

        foreach ($variantData as $variant) {

            $convertedTotalAmount = sprintf('%.2f', ($variant->total_amount) * $rate);
            $variant->total_amount = $convertedTotalAmount;

            $convertedPrice = sprintf('%.2f', ($variant->price) * $rate);
            $variant->price = $convertedPrice;
            $variant->symbol = $currencysymbol;


            $images = DB::table('product_varient_images')
                ->where('product_varient_id', $variant->product_varient_id)
                ->pluck('images');
            $variant->images = $images;

            $sizeStock = DB::table('product_varient_size')
                ->where('product_varient_id', $variant->product_varient_id)
                ->leftJoin('sizes', 'product_varient_size.size_id', '=', 'sizes.size_id')
                ->select('sizes.size_name', 'product_varient_size.stock', 'product_varient_size.size_id')
                ->get();
            $variant->sizeStock = $sizeStock;

            $finalVariants[] = $variant;
        }

        $finalVariants = collect($finalVariants)->unique('product_varient_id')->values();


        $reviews = DB::table('product_reviews')
            ->leftJoin('customers', 'product_reviews.customer_id', '=', 'customers.customer_id')
            ->leftJoin('product_review_content', 'product_reviews.product_review_id', '=', 'product_review_content.product_review_id')
            ->where('product_reviews.product_id', $product_id)
            ->whereNotNull('product_reviews.customer_id')
            ->select(
                'product_reviews.*',
                'customers.first_name',
                'customers.last_name',
                'customers.image',
                'customers.customer_id',
                'product_reviews.product_review_id'
            )
            ->get();

        $averageRating = DB::table('product_reviews')->where('product_id', $product_id)->avg('rating');
        $formattedAverageRating = number_format($averageRating, 1);
        $reviewCount = DB::table('product_reviews')->where('product_id', $product_id)->count('product_review_id');

        $reviewData = [];
        foreach ($reviews as $review) {
            if (!isset($reviewData[$review->product_review_id])) {
                $reviewImages = DB::table('product_review_content')
                    ->where('product_review_id', $review->product_review_id)
                    ->get();

                $reviewData[$review->product_review_id] = [
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'review_time' => $review->inserted_time,
                    'review_date' => $review->inserted_date,
                    'firstName' => $review->first_name,
                    'lastName' => $review->last_name,
                    'customer_image' => $review->image,
                    'customerId' => $review->customer_id,
                    'images' => $reviewImages,
                ];
            }
        }
        $reviewData = array_values($reviewData);


        $productAbout = DB::table('product_about')
            ->where('product_id', $product_id)
            ->select('heading', 'para', 'product_about_id')
            ->get();

        $resultArray = [];
        foreach ($productAbout as $item) {
            $resultArray[] = [
                'product_about_id' => $item->product_about_id,
                'field' => $item->heading,
                'value' => $item->para,
            ];
        }

        return response()->json([
            'status' => true,
            'products' => $products,
            'variants' => $finalVariants,
            'reviews' => $reviewData,
            'reviews_avg' => $formattedAverageRating,
            'reviewCount' => $reviewCount,
            'about' => $resultArray,
        ]);
    }


    public function get_search_suggestion($query)
    {
        $query = str_replace('-', ' ', $query);
        if (empty($query)) {
            return response()->json([]);
        }

        $productData = DB::table('products')
            ->leftJoin('category', 'products.category_id', '=', 'category.category_id')
            ->where(function ($queryBuilder) use ($query) {
                $queryBuilder->where('products.product_name', 'LIKE', '%' . $query . '%');
            })
            ->select(
                'products.product_name',
                'products.product_id',
                'products.category_id'
            )
            ->where('products.status', 'Active')
            ->groupBy(
                'products.product_name',
                'products.product_id',
                'products.category_id'
            )
            ->orderBy('products.product_name', 'asc')
            ->limit(10)
            ->get();

        $result = [];
        foreach ($productData as $product) {
            $limitedProductName = implode(' ', array_slice(explode(' ', $product->product_name), 0, 4));

            $result[] = [
                'product_name' => $limitedProductName,
                'product_id' => $product->product_id,
                'category_id' => $product->category_id
            ];
        }

        return response()->json($result);
    }
    public function get_search_product_data_($query)
    {
        if (empty($query)) {
            return response()->json([]);
        }


        $categoryProducts = DB::table('products')
            ->leftJoin('category', 'products.category_id', '=', 'category.category_id')
            ->leftJoin('sub_category_1', 'sub_category_1.sub_category_1_id', '=', 'products.sub_category_1_id')
            ->leftJoin('sub_category_2', 'sub_category_2.sub_category_2_id', '=', 'products.sub_category_2_id')
            ->leftJoin('sub_category_3', 'sub_category_3.sub_category_3_id', '=', 'products.sub_category_3_id')
            ->where('category.category_name', 'LIKE', $query . '%')
            ->where('products.status', 'Active')
            ->select(
                'products.*',
                'category.category_name',
                'sub_category_1.sub_category_1_name',
                'sub_category_2.sub_category_2_name',
                'sub_category_3.sub_category_3_name'
            )
            ->orderBy('products.product_id', 'desc')
            ->get();

        $categoryProductData = [];

        foreach ($categoryProducts as $product) {
            $averageRating = DB::table('product_reviews')
                ->where('product_id', $product->product_id)
                ->avg('rating');

            $formattedAverageRating = number_format($averageRating ?: 0, 1);

            $reviewCount = DB::table('product_reviews')
                ->where('product_id', $product->product_id)
                ->count('product_review_id');

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
                ->pluck('size_name')
                ->toArray();


            if (!isset($categoryProductData[$product->product_id])) {
                $categoryProductData[$product->product_id] = [
                    'product_id' => $product->product_id,
                    'product_name' => $product->product_name,
                    'sub_text' => $product->sub_text,
                    'product_img' => $variantImages,
                    'category_name' => $product->category_name,
                    'discount' => $product->discount,
                    'colors' => [],
                    'sizes' => $sizes,
                    'reviews_avg' => $formattedAverageRating,
                    'reviewCount' => $reviewCount,
                    'price' => $productVariants->first()->total_amount ?? null,
                ];
            }

            foreach ($productVariants as $variant) {
                if (!isset($MainproductsAllData[$product->product_id])) {
                    $MainproductsAllData[$product->product_id] = [
                        'product_id' => $product->product_id,
                        'product_name' => $product->product_name,
                        'sub_text' => $product->sub_text,
                        'product_img' => $variantImages,
                        'category_name' => $product->category_name,
                        'discount' => $product->discount,
                        'colors' => [],
                        'sizes' => $sizes,
                        'reviews_avg' => $formattedAverageRating,
                        'reviewCount' => $reviewCount
                    ];
                }

                $MainproductsAllData[$product->product_id]['colors'][] = $variant->colour;

                if (!isset($MainproductsAllData[$product->product_id]['price'])) {
                    $MainproductsAllData[$product->product_id]['price'] = $variant->total_amount;
                }
            }

            $categoryProductData[$product->product_id]['colors'][] = $variant->colour;
        }

        // Search for main product data
        $productData = DB::table('products')
            ->leftJoin('category', 'products.category_id', '=', 'category.category_id')
            ->leftJoin('sub_category_1', 'sub_category_1.sub_category_1_id', '=', 'products.sub_category_1_id')
            ->leftJoin('sub_category_2', 'sub_category_2.sub_category_2_id', '=', 'products.sub_category_2_id')
            ->leftJoin('sub_category_3', 'sub_category_3.sub_category_3_id', '=', 'products.sub_category_3_id')
            ->where(function ($queryBuilder) use ($query) {
                $queryBuilder->where('products.product_name', 'LIKE', $query . '%')
                    ->orWhere('products.brand_name', 'LIKE', $query . '%');
            })
            ->where('products.status', 'Active')
            ->orderBy('products.product_id', 'desc')
            ->select(
                'products.*',
                'category.category_name',
                'sub_category_1.sub_category_1_name',
                'sub_category_2.sub_category_2_name',
                'sub_category_3.sub_category_3_name'
            )
            ->get();

        $mainProductsAllData = [];

        foreach ($productData as $product) {
            $productReviews = DB::table('product_reviews')
                ->where('product_id', $product->product_id)
                ->where('status', 'Active')
                ->select(
                    DB::raw('AVG(product_reviews.rating) as average_rating'),
                    DB::raw('COUNT(product_reviews.product_review_id) as review_count')
                )
                ->groupBy('product_id')
                ->first();

            $formatted_Productrating = $productReviews ? number_format($productReviews->average_rating, 1) : 0;
            $review_count = $productReviews->review_count ?? 0;

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
                ->pluck('size_name')
                ->toArray();

            foreach ($productVariants as $variant) {
                if (!isset($mainProductsAllData[$product->product_id])) {
                    $mainProductsAllData[$product->product_id] = [
                        'product_id' => $product->product_id,
                        'product_varient_id' => $variant->product_varient_id,
                        'product_name' => $product->product_name,
                        // 'product_name' => implode(' ', array_slice(explode(' ', $product->product_name), 0, 3)),
                        'sub_text' => $product->sub_text,
                        'product_img' => $variantImages,
                        'brand_name' => $product->brand_name,
                        'category_name' => $product->category_name,
                        'discount' => $product->discount,
                        'colors' => [],
                        'sizes' => $sizes,
                        'reviews_avg' => $formatted_Productrating,
                        'review_count' => $review_count,
                        'price' => $variant->total_amount,
                    ];
                }

                $mainProductsAllData[$product->product_id]['colors'][] = $variant->colour;
            }
        }

        return response()->json([
            'status' => true,
            'MainproductsAllData' => array_values($mainProductsAllData),
            'categoryProductData' => array_values($categoryProductData)
        ]);
    }
    public function get_search_product_data(Request $request)
    {
        $search = $request->search;
        $search = str_replace('_', ' ', $search);
        if (empty($search)) {
            return response()->json([]);
        }

        $productData = DB::table('products')
            ->leftJoin('category', 'products.category_id', '=', 'category.category_id')
            ->where(function ($queryBuilder) use ($search) {
                $queryBuilder->where('products.product_name', 'LIKE', '%' . $search .'%' );
            })
            ->where('products.status', 'Active')
            ->orderBy('products.product_id', 'desc')
            ->select(
                'products.*',
                'category.category_name',
            )
            ->get();



        $mainProductsAllData = [];

        foreach ($productData as $product) {

            // dd($product);
            $productReviews = DB::table('product_reviews')
                ->where('product_id', $product->product_id)
                ->where('status', 'Active')
                ->select(
                    DB::raw('AVG(product_reviews.rating) as average_rating'),
                    DB::raw('COUNT(product_reviews.product_review_id) as review_count')
                )
                ->groupBy('product_id')
                ->first();

            $formatted_Productrating = $productReviews ? number_format($productReviews->average_rating, 1) : 0;
            $review_count = $productReviews->review_count ?? 0;

            $productVariants = DB::table('product_varient')
                ->where('product_id', $product->product_id)
                ->select('product_varient_id', 'colour', 'total_amount', 'quantity')
                ->get();
                // dd($productVariants);

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
                ->pluck('size_name')
                ->toArray();

            foreach ($productVariants as $variant) {
                if (!isset($mainProductsAllData[$product->product_id])) {
                    $mainProductsAllData[$product->product_id] = [
                        'product_id' => $product->product_id,
                        'product_unique_id' => $product->product_unique_id,
                        'product_varient_id' => $variant->product_varient_id,
                        'product_name' => $product->product_name,
                        'sub_text' => $product->sub_text,
                        'product_img' => $variantImages,
                        'brand_name' => $product->brand_name,
                        'category_name' => $product->category_name,
                        'discount' => $product->discount,
                        'category_id' => $product->category_id,
                        'sub_category_3_id' => $product->sub_category_3_id,
                        'colors' => [],
                        'sizes' => $sizes,
                        'reviews_avg' => $formatted_Productrating,
                        'review_count' => $review_count,
                        'price' => $variant->total_amount,
                    ];
                }

                $mainProductsAllData[$product->product_id]['colors'][] = $variant->colour;
            }
        }

        if(count($mainProductsAllData) < 4){
            $categorydata =[];
            foreach($mainProductsAllData as $products){
                $categoryProducts = DB::table('products')
                ->leftJoin('category', 'products.category_id', '=', 'category.category_id')
                ->where('products.sub_category_3_id', $products['sub_category_3_id'])
                ->where('products.status', 'Active')
                ->select(
                    'products.*',
                    'category.category_name',
                    'category.category_id'
                )
                ->get();

                foreach ($categoryProducts as $product) {

                    // dd($product);
                    $productReviews = DB::table('product_reviews')
                        ->where('product_id', $product->product_id)
                        ->where('status', 'Active')
                        ->select(
                            DB::raw('AVG(product_reviews.rating) as average_rating'),
                            DB::raw('COUNT(product_reviews.product_review_id) as review_count')
                        )
                        ->groupBy('product_id')
                        ->first();

                    $formatted_Productrating = $productReviews ? number_format($productReviews->average_rating, 1) : 0;
                    $review_count = $productReviews->review_count ?? 0;

                    $productVariants = DB::table('product_varient')
                        ->where('product_id', $product->product_id)
                        ->select('product_varient_id', 'colour', 'total_amount', 'quantity')
                        ->get();
                        // dd($productVariants);

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
                        ->pluck('size_name')
                        ->toArray();

                    foreach ($productVariants as $variant) {
                        if (!isset($categorydata[$product->product_id])) {
                            $categorydata[$product->product_id] = [
                                'product_id' => $product->product_id,
                                'product_varient_id' => $variant->product_varient_id,
                                'product_name' => $product->product_name,
                                'sub_text' => $product->sub_text,
                                'product_img' => $variantImages,
                                'brand_name' => $product->brand_name,
                                'category_name' => $product->category_name,
                                'category_id' => $product->category_id,
                                'discount' => $product->discount,
                                'colors' => [],
                                'sizes' => $sizes,
                                'reviews_avg' => $formatted_Productrating,
                                'review_count' => $review_count,
                                'price' => $variant->total_amount,
                            ];
                        }

                        $categorydata[$product->product_id]['colors'][] = $variant->colour;
                    }
                }

            }
            return response()->json([
                'status' => true,
                'MainproductsAllData' => array_values($categorydata)

            ]);

        }
        else{
            return response()->json([
                'status' => true,
                'MainproductsAllData' => array_values($mainProductsAllData)

            ]);

        }


    }
    public function get_category_products(Request $request)
    {
        $category_id = $request->category_id;
        // $sub_category_id = $request->sub_category_id;
        $countryCode = $request->countryCode;

        $categoryProducts = DB::table('products')
            ->leftJoin('category', 'products.category_id', '=', 'category.category_id')
            ->leftJoin('sub_category_1', 'sub_category_1.sub_category_1_id', '=', 'products.sub_category_1_id')
            ->leftJoin('sub_category_2', 'sub_category_2.sub_category_2_id', '=', 'products.sub_category_2_id')
            ->leftJoin('sub_category_3', 'sub_category_3.sub_category_3_id', '=', 'products.sub_category_3_id')
            ->where('products.category_id', $category_id)
            // ->where('products.sub_category_1_id', $sub_category_id)
            ->where('products.status', 'Active')
            // ->where('products.stock_status', 'Live')
            ->select(
                'products.*',
                'category.category_name',
                'sub_category_1.sub_category_1_name',
                'sub_category_2.sub_category_2_name',
                'sub_category_3.sub_category_3_name'
            )
            ->orderBy('products.product_id', 'desc')
            ->get();

        if ($categoryProducts->isEmpty()) {
            return response()->json([
                'status' => true,
                'MainproductsAllData' => []
            ]);
        } else {
            $MainproductsAllData = [];

            $currencyInfo = $this->getCurrencyInfo($countryCode);

            foreach ($categoryProducts as $product) {


                $averageRating = DB::table('product_reviews')
                    ->where('product_id', $product->product_id)
                    ->avg('rating');

                $formattedAverageRating = number_format($averageRating, 1);

                $reviewCount = DB::table('product_reviews')
                    ->where('product_id', $product->product_id)
                    ->count('product_review_id');

                $productVariants = DB::table('product_varient')
                    ->where('product_id', $product->product_id)
                    ->select('product_varient_id', 'colour', 'total_amount', 'quantity')
                    ->get();


                $variantImages = DB::table('product_varient_images')
                    ->whereIn('product_varient_id', $productVariants->pluck('product_varient_id'))
                    ->select('product_varient_id', 'images')
                    ->take(2) // Limit to 2 images
                    ->get();

                // Collect sizes
                $sizes = DB::table('product_varient_size')
                    ->whereIn('product_varient_id', $productVariants->pluck('product_varient_id'))
                    ->leftJoin('sizes', 'product_varient_size.size_id', '=', 'sizes.size_id')
                    ->select('sizes.size_name')
                    ->distinct()
                    ->get()
                    ->pluck('size_name')
                    ->toArray();

                    $productImage[]=$product->product_img;
                    $rate = $currencyInfo['rate'] ?? '1';
                    $currencysymbol = $currencyInfo['symbol'] ?? '$';
                    if (!isset($MainproductsAllData[$product->product_id])) {

                        $MainproductsAllData[$product->product_id]=[
                            'product_id' => $product->product_id,
                            'product_unique_id' => $product->product_unique_id,
                            'product_name' => $product->product_name,
                            'product_img' => $variantImages??$productImage,

                            'sub_text' => $product->sub_text,
                            'category_name' => $product->category_name,
                            'discount' => $product->discount,
                            'colors' => [],
                            'sizes' => $sizes,
                            'reviews_avg' => $formattedAverageRating,
                            'review_count' => $reviewCount,
                            'symbol' => $currencysymbol,
                        ];
                    }

                foreach ($productVariants as $variant) {
                    if (!isset($MainproductsAllData[$product->product_id])) {
                        $MainproductsAllData[$product->product_id] = [
                            // 'product_id' => $product->product_id,
                            // 'product_unique_id' => $product->product_unique_id,
                            'product_varient_id' => $variant->product_varient_id,
                            // 'product_name' => $product->product_name,
                            // 'sub_text' => $product->sub_text,
                            // 'product_img' => $variantImages,
                            // 'category_name' => $product->category_name,
                            // 'discount' => $product->discount,
                            // 'colors' => [],
                            // 'sizes' => $sizes,
                            // 'reviews_avg' => $formattedAverageRating,
                            // 'review_count' => $reviewCount,
                            // 'symbol' => $currencysymbol,
                        ];
                    }

                    $MainproductsAllData[$product->product_id]['colors'][] = $variant->colour;

                    if (!isset($MainproductsAllData[$product->product_id]['price'])) {
                        if (isset($currencyInfo)) {
                            $MainproductsAllData[$product->product_id]['price'] = sprintf('%.2f', ($variant->total_amount) * $rate);
                        } else {
                            $MainproductsAllData[$product->product_id]['price'] = sprintf('%.2f', $variant->total_amount);
                        }
                    }
                }
            }
            // dd($categoryProducts);

            return response()->json([
                'status' => true,
                'MainproductsAllData' => array_values($MainproductsAllData)
            ]);
        }
    }
    public function getaboutData(Request $request)
    {
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
        $data = DB::table('product_about')->where('product_id', $product_id)->get();
        if($data->isEmpty()){
            return response()->json([
                'status'=>false,
                'message'=>'No Product about Found'
            ]);
        }
        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    public function get_idwise_productdata(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'product_unique_id'=>'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['status'=>false,'message' => $validator->errors()]);
        }
        $productData=DB::table('products')->where('product_unique_id',$request->product_unique_id)->firt();
        // Fetch product data
        $product = DB::table('products')
            ->leftjoin('category', 'products.category_id', '=', 'category.category_id')
            ->leftjoin('sub_category_1', 'sub_category_1.sub_category_1_id', '=', 'products.sub_category_1_id')
            ->leftjoin('sub_category_2', 'sub_category_2.sub_category_2_id', '=', 'products.sub_category_2_id')
            ->leftjoin('sub_category_3', 'sub_category_3.sub_category_3_id', '=', 'products.sub_category_3_id')
            ->select(
                'products.*',
                // 'category.category_id',
                'category.category_name',
                // 'sub_category_1.sub_category_1_id',
                'sub_category_1.sub_category_1_name',
                // 'sub_category_2.sub_category_2_id',
                'sub_category_2.sub_category_2_name',
                // 'sub_category_3.sub_category_3_id',
                'sub_category_3.sub_category_3_name'
            )
            ->where('product_id', $productData->product_id)
            ->first();

        // Fetch product images
        // $product_images = DB::table('product_images')
        //     ->where('product_id', $request->product_id)
        //     ->get();

        // Fetch product sizes
        // $product_sizes = DB::table('main_product_sizes')
        //     ->leftjoin('sizes', 'main_product_sizes.size_id', '=', 'sizes.size_id')
        //     ->where('product_id', $request->product_id)
        //     ->get();


        $productReviews = DB::table('product_reviews')
            ->where('product_id', $productData->product_id)
            ->where('status', 'Active')
            ->select(
                DB::raw('AVG(product_reviews.rating) as average_rating'),
                DB::raw('COUNT(product_reviews.product_review_id) as review_count')
            )
            ->groupBy('product_id')
            ->orderBy('average_rating', 'desc')
            ->orderBy('review_count', 'desc')
            ->first();


        // if (is_null($productReviews) || $productReviews->review_count === 0) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'No reviews found for this product.',
        //     ]);
        // }


        $allReviews = DB::table('product_reviews')
            ->where('product_id', $productData->product_id)
            ->where('status', 'Active')
            ->get();
        // if ($allReviews->isEmpty()) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'No reviews found for this product.',
        //     ]);
        // }



        // Prepare product data array
        // $productdata = [
        //     'product' => $product,
            // 'product_sizes' => $product_sizes,
            // 'product_images' => $product_images
        // ];

        // Fetch product variants
        $varient = DB::table('product_varient')
            ->where('product_id', $productData->product_id)
            ->get();

        $varients = [];

        // If variants are available, fetch their images and sizes
        if (!$varient->isEmpty()) {
            $var = DB::table('product_varient')
                // ->leftjoin('category', 'products.category_id', '=', 'category.category_id')

                ->where('product_varient.product_id', $productData->product_id)
                ->get();

            // Iterate over variants to fetch their images and sizes
            foreach ($var as $v) {
                $varient_images = DB::table('product_varient_images')
                    ->where('product_varient_id', $v->product_varient_id)
                    ->get();

                $product_varient_size = DB::table('product_varient_size')
                    ->leftjoin('sizes', 'product_varient_size.size_id', '=', 'sizes.size_id')
                    ->where('product_varient_id', $v->product_varient_id)
                    ->get();

                // Prepare array for each variant
                $ar = [
                    'varient' => $v,
                    'varient_images' => $varient_images,
                    'product_varient_size' => $product_varient_size
                ];

                $varients[] = $ar;
            }
        }

        // Check if product data is empty (use count() for arrays)
        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found.',
            ]);
        }

        $productAbout = DB::table('product_about')
            ->where('product_id', $productData->product_id)
            ->select('heading', 'para', 'product_about_id')
            ->get();


        $resultArray = null;
        foreach ($productAbout as $item) {
            $resultArray[] = [
                'product_about_id' => $item->product_about_id,
                'field' => $item->heading,
                'value' => $item->para,
            ];
        }

        // Return response with product and variant data
        return response()->json([
            'status' => true,
            'product' => $product,
            'varientsdata' => $varients,
            'productReviewsAvg' => $productReviews??null,
            'allReviews' => $allReviews->isEmpty() ? null :  $allReviews,
            'about'=>$resultArray
        ]);
    }

    public function get_discounted_products(Request $request)
    {

        $maxDiscount1 = DB::table('products')
            ->max('discount');
        $maxDiscount2 = $maxDiscount1-10;
        $maxDiscount3 = $maxDiscount2-10;
        $maxDiscount4 = $maxDiscount3-10;

        $discountValues = [$maxDiscount1, $maxDiscount2, $maxDiscount3, $maxDiscount4];


        $discountedProducts = DB::table('products')
            ->leftjoin('category', 'products.category_id', '=', 'category.category_id')
            ->leftjoin('sub_category_1', 'sub_category_1.sub_category_1_id', '=', 'products.sub_category_1_id')
            ->leftjoin('sub_category_2', 'sub_category_2.sub_category_2_id', '=', 'products.sub_category_2_id')
            ->leftjoin('sub_category_3', 'sub_category_3.sub_category_3_id', '=', 'products.sub_category_3_id')
            ->select(
                'products.*',
                'category.category_name',
                'sub_category_1.sub_category_1_name',
                'sub_category_2.sub_category_2_name',
                'sub_category_3.sub_category_3_name'
            )
            ->whereIn('discount', $discountValues)
            ->orderBy('discount','desc')
            ->get();


        if ($discountedProducts->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No discounted products found.',
            ]);
        }

        return response()->json([
            'status' => true,
            'products' => $discountedProducts,
        ]);

    }

}
