<?php

use App\Http\Controllers\RegisterController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
Route::get('/demo', action: function () {
    return view('demo');
});

// echo "hello";die;

// Route::get('/home', [UserController::class, 'index']);


// Route::get('/users', [UserController::class, 'index']);
// Route::post('/users/find', [UserController::class, 'findByName']);


// Route::get('/banners', [UserController::class, 'banners']);

// Route::get('/home_category_with_subcategory', [UserController::class, 'home_category_with_subcategory']);


