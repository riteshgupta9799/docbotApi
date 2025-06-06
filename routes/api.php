<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\PaitentController;
use Illuminate\Http\Request;
use App\Http\Controllers\CustomerController;
use Illuminate\Support\Facades\Route;

Route::post('/login_customer', [CustomerController::class, 'login_customer']);
Route::post('/login_user', [CustomerController::class, 'login_user']);

Route::prefix('admin')->middleware(['jwt.auth', 'admin'])->group(function () {
    // Get all machines
    Route::get('/machines', [AdminController::class, 'getMachines']);

    // Get single machine
    Route::get('/machines/{id}', [AdminController::class, 'getMachine']);

    // Create new machine
    Route::post('/machines', [AdminController::class, 'createMachine']);

    // Update machine
    Route::put('/machines/{id}', [AdminController::class, 'updateMachine']);

    // Delete machine
    Route::delete('/machines/{id}', [AdminController::class, 'deleteMachine']);

    // getallUser
    Route::post('/get_all_customer', [AdminController::class, 'get_all_customer']);
});

    // paitent Send Otp
    Route::post('/send_otp', [PaitentController::class, 'send_otp']);
    // verify Otp
    Route::post('/verify_otp', [PaitentController::class, 'verify_otp']);
