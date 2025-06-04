<?php

// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Route;
// use App\Http\Controllers\UserController;
// use App\Http\Controllers\ProductController;
// use App\Http\Controllers\CustomerRegisterController;
// use App\Http\Controllers\SellerRegisterController;




// // routes/api.php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\PaymentController;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CustomerRegisterController;
use App\Http\Controllers\AdminProductController;
use App\Http\Controllers\FileUploadController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InfluencerController;
use App\Http\Controllers\SellerProductController;
use App\Http\Controllers\SellerRegisterController;
use App\Http\Controllers\CustomerWebController;

// Route::post('/customer_data_new', [SellerRegisterController::class, 'customer_data_new']);
Route::post('/get_cart', [CustomerRegisterController::class, 'get_cart']);


Route::post('/check', function () {
    return response()->json(['message' => 'POST request received successfully']);
});



// Route::post('/create-payment-intent', [PaymentController::class, 'createPaymentIntent']);
// Route::post('/create-payment-intent', [PaymentController::class, 'createCheckoutSession']);
// Route::post('/create-checkout-response', [PaymentController::class, 'stripeCheckoutSuccess']);
// Route::post('/stripe_success_app', [PaymentController::class, 'stripe_success_app']);

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });
Route::get('/home', [UserController::class, 'index']);

// Route::get( '/', [UserController::class, 'index']);
// Route::post('/home', [UserController::class, 'findByName']);



// Route::post('/get_cart', [CustomerRegisterController::class, 'get_cart']);

// Route::get('/customer_data/{unique_id}',[SellerRegisterController::class,'customer_data']);

Route::post('/customer_data',[CustomerRegisterController::class,'customer_data']);


// Route::POST('/customer_data_new',[SellerRegisterController::class,'customer_data_new']);



Route::get('/home', [UserController::class, 'index']);

Route::get('/banners', [UserController::class, 'banners']);
Route::get('/home_category_with_subcategory', [UserController::class, 'home_category_with_subcategory']);
Route::get('/allCategories', [UserController::class, 'allCategories']);
Route::post('/subCategories', [UserController::class, 'subCategories']);
Route::post('/subSubCategories', [UserController::class, 'subSubCategories']);
Route::get('/homeCategoriesWithProductCount', [UserController::class, 'homeCategoriesWithProductCount']);
Route::get('/getAllCountries', [UserController::class, 'getAllCountries']);
Route::post('/getStateofACountry', [UserController::class, 'getStateofACountry']);


Route::post('/update_password',[UserController::class,'update_password']);
Route::get('/register_as_seller',[UserController::class,'register_as_seller']);
Route::post('/reset_seller_password',[UserController::class,'reset_seller_password']);



Route::post('/email_pass_login',[CustomerRegisterController::class,'email_pass_login']);


Route::post('/register_customer',[CustomerRegisterController::class,'register_customer']);





// Route::post('/removeAccount',[UserController::class,'removeAccount']);

Route::post('/passwordsendotp',[UserController::class,'passwordsendotp']);
Route::post('/passwordverifyotp',[UserController::class,'passwordverifyotp']);
Route::post('/updatePassword',[UserController::class,'updatePassword']);
// Route::post('/get_userusingid',[UserController::class,'get_userusingid']);








// Route::post('/get_user_products',[UserController::class,'get_user_products']);
// Route::post('/product_stock_status',[UserController::class,'product_stock_status']);
// Route::post('/get_main_category',[UserController::class,'get_main_category']);
// Route::post('/update_main_category',[UserController::class,'update_main_category']);




// Route::post('/get_products_list',[UserController::class,'get_products_list']);

Route::get('/product_colours',[UserController::class,'product_colours']);
Route::get('/product_sizes',[UserController::class,'product_sizes']);

Route::get('/get_influincer_list',[UserController::class,'get_influincer_list']);
Route::get('/get_Customers_Spend',[UserController::class,'get_Customers_Spend']);

Route::get('/getRecentProductReviews',[UserController::class,'getRecentProductReviews']);
Route::get('/getalloffers',[UserController::class,'getalloffers']);

Route::post('/get_all_offers_list',[UserController::class,'get_all_offers_list']);
Route::post('/getoneProductDiscount',[UserController::class,'getoneProductDiscount']);
Route::post('/product_reviews_status_update',[AdminController::class,'product_reviews_status_update']);


Route::get('/get_all_manager',[UserController::class,'get_all_manager']);

Route::get('/get_manager_areas',[AdminController::class,'get_manager_areas']);


// // ======================= 26/0/24 ========================= //

Route::get('/get_wharehouse_list',[AdminController::class,'get_wharehouse_list']);


Route::post('/update_userdata',[UserController::class,'update_userdata']);
Route::post('/update_user_details',[UserController::class,'update_user_details']);

Route::post('/reset_user_password', [AdminController::class, 'reset_user_password']);



Route::post('/update_product', [SellerProductController::class, 'update_product']);
Route::post('/update_user_image', [AdminController::class, 'update_user_image']);
Route::post('/add_banners', [AdminController::class, 'add_banners']);



Route::post('/update_banners', [AdminController::class, 'update_banners']);
Route::post('/get_idwise_products_review_avg', [AdminController::class, 'get_idwise_products_review_avg']);
Route::post('/get_idwise_all_reviews', [AdminController::class, 'get_idwise_all_reviews']);
Route::post('/add_about', [AdminController::class, 'add_about']);
Route::post('/update_about', [AdminController::class, 'update_about']);


Route::get('/get_customer_reviews', [UserController::class, 'get_customer_reviews']);
Route::post('/customer_update_product_review', [CustomerWebController::class, 'customer_update_product_review']);
Route::post('/delete_customer_poduct_review', [CustomerWebController::class, 'delete_customer_poduct_review']);

Route::post('/influencer_create_bag', [InfluencerController::class, 'influencer_create_bag']);
Route::post('/influencer_get_product_bag', [InfluencerController::class, 'influencer_get_product_bag']);
Route::post('/user_product_stock_status_update', [SellerProductController::class, 'user_product_stock_status_update']);
Route::post('/get_cusotmer_databyid', [AdminController::class, 'get_cusotmer_databyid']);
Route::post('/customer_category_data', [AdminController::class, 'customer_category_data']);
Route::get('/get_transaction', [AdminController::class, 'get_transaction']);
Route::get('/get_coupon', [AdminController::class, 'get_coupon']);



Route::post('/update_coupon', [AdminController::class, 'update_coupon']);
Route::get('/get_categories', [UserController::class, 'get_categories']);
// Route::post('/get_single_review', [UserController::class, 'get_single_review']);
// Route::post('/update_single_review', [UserController::class, 'update_single_review']);

Route::get('/get_brands', [HomeController::class, 'get_brands']);
route::post('/colors', [HomeController::class, 'colors']);
route::post('/add_subscriber', [HomeController::class, 'add_subscriber']);


Route::middleware('throttle:10,1')->group(function () {
    // Route::get('/public-data', [PublicController::class, 'index']);
    Route::get('/get_newsletter', [HomeController::class, 'get_newsletter']);
});


Route::post('/customer_deactive_account', [CustomerWebController::class, 'customer_deactive_account']);

Route::post('/ads_subscription', [AdminController::class, 'ads_subscription']);
Route::post('/customer_delete_card_detail', [CustomerWebController::class, 'customer_delete_card_detail']);



Route::post('/get_customer_allorder', [CustomerRegisterController::class, 'get_customer_allorder']);
Route::post('/get_customers_delivery_address',[CustomerRegisterController::class,'get_customers_delivery_address']);
Route::post('/add_cart', [CustomerRegisterController::class, 'add_cart']);
Route::post('/updateCustomer',[CustomerRegisterController::class,'updateCustomer']);
Route::post('/check_quantity', [CustomerRegisterController::class, 'check_quantity']);
Route::post('/add_customer_product_review', [CustomerRegisterController::class, 'add_customer_product_review']);
Route::post('/customer_apply_coupon', [CustomerRegisterController::class, 'customer_apply_coupon']);
Route::post('/customer_add_review', [CustomerRegisterController::class, 'customer_add_review']);
Route::post('/customer_add_review_data', [CustomerRegisterController::class, 'customer_add_review_data']);
Route::post('/customer_add_card_details', [CustomerRegisterController::class, 'customer_add_card_details']);
Route::post('/get_card_details', [CustomerRegisterController::class, 'get_card_details']);
Route::post('/checkout', [CustomerRegisterController::class, 'checkout']);
Route::post('/delete_cart_product', [CustomerRegisterController::class, 'delete_cart_product']);
Route::post('/place_order', [CustomerRegisterController::class, 'place_order']);
Route::post('/cart_quantity_add', [CustomerRegisterController::class, 'cart_quantity_add']);
Route::post('/cart_quantity_subtract', [CustomerRegisterController::class, 'cart_quantity_subtract']);
Route::post('/mobile_otp', [CustomerRegisterController::class, 'mobile_otp']);
Route::post('/email_otp', [CustomerRegisterController::class, 'email_otp']);
Route::post('/email_otp_validate', [CustomerRegisterController::class, 'email_otp_validate']);
Route::post('/mobile_otp_validate', [CustomerRegisterController::class, 'mobile_otp_validate']);
Route::post('/mobile_pass_login',[CustomerRegisterController::class,'mobile_pass_login']);
Route::post('/add_delivery_address',[CustomerRegisterController::class,'add_delivery_address']);
Route::post('/update_delivery_address',[CustomerRegisterController::class,'update_delivery_address']);
Route::post('/delete_product_review',[CustomerRegisterController::class,'delete_product_review']);
Route::post('/update_product_review',[CustomerRegisterController::class,'update_product_review']);





Route::post('/get_product_data', [CustomerRegisterController::class, 'get_product_data']);
Route::post('/uploadCloud', [FileUploadController::class, 'uploadCloud']);












Route::post('/get_category_products',[ProductController::class,'get_category_products']);
Route::post('/get_all_product_data', [ProductController::class, 'get_all_product_data']);
Route::post('/get_one_product', [ProductController::class, 'get_one_product']);
Route::get('/get_search_suggestion/{query}', [ProductController::class, 'get_search_suggestion']);
Route::post('/get_search_product_data', [ProductController::class, 'get_search_product_data']);
Route::post('/getaboutData',[ProductController::class,'getaboutData']);


Route::post('/user_data',[SellerRegisterController::class,'user_data']);
Route::post('/user_login',[SellerRegisterController::class,'user_login']);
Route::post('/register_as_seller',[SellerRegisterController::class,'register_as_seller']);
Route::post('/mobile_otp_seller',[SellerRegisterController::class,'mobile_otp_seller']);
Route::post('/save_password',[SellerRegisterController::class,'save_password']);
Route::post('/updateProfile',[SellerRegisterController::class,'updateProfile']);
Route::post('/getStoreInfo',[SellerRegisterController::class,'getStoreInfo']);
Route::post('/update_store_details',[SellerRegisterController::class,'update_store_details']);
Route::post('/add_store_details',[SellerRegisterController::class,'add_store_details']);





Route::post('/register_as_influencer',[InfluencerController::class,'register_as_influencer']);
Route::post('/reset_influencer_password',[InfluencerController::class,'reset_influencer_password']);
Route::post('/mobile_otp_influencer',[InfluencerController::class,'mobile_otp_influencer']);
Route::post('/get_bag_product_list', [InfluencerController::class, 'get_bag_product_list']);
Route::post('/get_bag_product_list_app', [InfluencerController::class, 'get_bag_product_list_app']);
Route::post('/influencer_add_product_bag', [InfluencerController::class, 'influencer_add_product_bag']);
Route::post('/influencer_product_remove_bag', [InfluencerController::class, 'influencer_product_remove_bag']);
Route::post('/save_join_id', [InfluencerController::class, 'save_join_id']);
Route::post('/influencer_live_end', [InfluencerController::class, 'influencer_live_end']);












Route::post('/getCustomer',[AdminController::class,'getCustomer']);
Route::get('/get_stripe_credentials_app',[AdminController::class,'get_stripe_credentials_app']);
Route::post('/addFaqs',[AdminController::class,'addFaqs']);
Route::post('/deletefaqs/{id}',[AdminController::class,'deletefaqs']);
Route::post('/updatefaqs/{id}',[AdminController::class,'updatefaqs']);
Route::get('/get_user_list',[AdminController::class,'get_user_list']);



Route::post('/get_user_profile',[AdminController::class,'get_user_profile']);
Route::post('/addAlaspartner',[AdminController::class,'addAlaspartner']);
Route::get('/getContactFormData',[AdminController::class,'getContactFormData']);
Route::post('/category_status_update',[AdminController::class,'category_status_update']);
Route::post('/add_main_category',[AdminController::class,'add_main_category']);
Route::post('/add_sub_categories',[AdminController::class,'add_sub_categories']);
Route::post('/add_sub2_categories',[AdminController::class,'add_sub2_categories']);
Route::post('/add_sub3_categories',[AdminController::class,'add_sub3_categories']);
Route::post('/sub_categories_status_update',[AdminController::class,'sub_categories_status_update']);
Route::post('/sub2_categories_status_update',[AdminController::class,'sub2_categories_status_update']);
Route::post('/sub3_categories_status_update',[AdminController::class,'sub3_categories_status_update']);
Route::post('/add_update_newsletter', [AdminController::class, 'add_update_newsletter']);
Route::post('/change_newsletter_status', [AdminController::class, 'change_newsletter_status']);
Route::get('/get_app_credentials_app',[AdminController::class,'get_app_credentials_app']);




Route::post('/influencer_live_bags', [InfluencerController::class, 'influencer_live_bags']);
Route::post('/influencer_live_streaming', [InfluencerController::class, 'influencer_live_streaming']);
Route::post('/get_recent_purchases', [AdminProductController::class, 'get_recent_purchases']);
Route::post('/customer_influencer_bag', [HomeController::class, 'customer_influencer_bag']);
Route::post('/add_brand', [AdminController::class, 'add_brand']);
Route::post('/update_brand', [AdminController::class, 'update_brand']);
Route::get('/delete_brand/{id}', [AdminController::class, 'delete_brand']);
Route::get('/faqs',[HomeController::class,'faqs']);
Route::post('/subSubSubCategories',[HomeController::class,'subSubSubCategories']);
Route::post('/add_business_details',[SellerProductController::class,'add_business_details']);
Route::post('/update_business_details',[SellerProductController::class,'update_business_details']);
Route::post('/contact_form',[HomeController::class,'contact_form']);
Route::post('/open_otp_validate',[HomeController::class,'open_otp_validate']);
Route::post('/add_new_wharehouse',[AdminController::class,'add_new_wharehouse']);
Route::post('/update_wharehouse_status',[AdminController::class,'update_wharehouse_status']);
Route::post('/update_wharehouse_data',[AdminController::class,'update_wharehouse_data']);
Route::get('/live_influincers_list', [HomeController::class, 'live_influincers_list']);
Route::post('/seller_order', [SellerProductController::class, 'seller_order']);




Route::post('/open_otp',[HomeController::class,'open_otp']);
Route::get('/contact',[HomeController::class,'contact']);
Route::get('/about',[HomeController::class,'about']);
Route::post('/influencer_bag_data', [HomeController::class, 'influencer_bag_data']);
Route::post('/add_coupon',[AdminController::class,'add_coupon']);










Route::post('/getall_productwise_review',[AdminProductController::class,'getall_productwise_review']);
Route::post('/get_customer_product_review',[AdminProductController::class,'get_customer_product_review']);


Route::post('/update_sub_category1',[AdminController::class,'update_sub_category1']);
Route::post('/update_sub_category2',[AdminController::class,'update_sub_category2']);
Route::post('/update_sub_category3',[AdminController::class,'update_sub_category3']);







Route::post('/seller_confirms_order', [SellerProductController::class, 'seller_confirms_order']);
Route::get('/get_offer_list_customer', [AdminController::class, 'get_offer_list_customer']);
Route::post('/get_wharehousebyid_list', [AdminController::class, 'get_wharehousebyid_list']);
Route::post('/update_user_image', [AdminController::class, 'update_user_image']);
Route::post('/add_banners', [AdminController::class, 'add_banners']);
Route::post('/get_orderedProduct_ByOrderId',[AdminController::class,'get_orderedProduct_ByOrderId']);

Route::get('/get_all_supervisor',[AdminController::class,'get_all_supervisor']);
Route::get('/get_all_manager',[AdminController::class,'get_all_manager']);

Route::post('/get_idwise_productdata',[ProductController::class,'get_idwise_productdata']);
Route::post('/register_new_manager',[AdminController::class,'register_new_manager']);
Route::post('/register_new_supervisor',[AdminController::class,'register_new_supervisor']);

Route::post('/update_product_status', [AdminController::class, 'update_product_status']);
Route::post('/update_user_status', [AdminController::class, 'update_user_status']);



Route::post('/contact_form_delete', [AdminController::class, 'contact_form_delete']);
Route::post('/contact_form_update', [AdminController::class, 'contact_form_update']);
Route::get('/get_seller_list',[AdminController::class,'get_seller_list']);
Route::get('/get_supplier_influencer_list',[AdminController::class,'get_supplier_influencer_list']);
Route::get('/get_supplier_seller_list',[AdminController::class,'get_supplier_seller_list']);
Route::get('/get_all_banners', [AdminController::class, 'get_all_banners']);
Route::get('/getBanners', [AdminController::class, 'getBanners']);

Route::post('/remove_store_info',[SellerProductController::class,'remove_store_info']);
Route::post('/remove_business_info',[SellerProductController::class,'remove_business_info']);
Route::post('/add_product_delivery',[SellerProductController::class,'add_product_delivery']);
Route::post('/add_varient_details',[SellerProductController::class,'add_varient_details']);
Route::post('/update_manager_area_status',[AdminController::class,'update_manager_area_status']);
Route::post('/assign_area_to_manager',[AdminController::class,'assign_area_to_manager']);



