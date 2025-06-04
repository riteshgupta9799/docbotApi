<?php

use Illuminate\Http\Request;
use App\Http\Controllers\CustomerController;
use Illuminate\Support\Facades\Route;

Route::post('/login_customer', [CustomerController::class, 'login_customer']);
