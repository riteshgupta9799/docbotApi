<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\PaitentController;
use Illuminate\Http\Request;
use App\Http\Controllers\CustomerController;
use Illuminate\Support\Facades\Route;

Route::post('/login_customer', [CustomerController::class, 'login_customer']);
Route::post('/login_user', [CustomerController::class, 'login_user']);
Route::post('/customer/register', [CustomerController::class, 'register_customer']);


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

    // addUser
    Route::post('/add_customer', [AdminController::class, 'add_customer']);

    // UpdateUser
    Route::post('/update_customer', [AdminController::class, 'update_customer']);

    // Deleteser
    Route::post('/delete_customer', [AdminController::class, 'delete_customer']);

   // Update machine
    Route::get('/machine_to_patient/{id}', [AdminController::class, 'machine_to_patient']);


});



    // paitent Send Otp
    Route::post('/send_otp', [PaitentController::class, 'send_otp']);

    // verify Otp
    Route::post('/verify_otp', [PaitentController::class, 'verify_otp']);

       // register_paitent
   Route::post('/register_paitent', [PaitentController::class, 'register_paitent']);

   Route::post('/get_patient_details', [PaitentController::class, 'get_patient_details']);

   Route::post('/get_patient_Testdetails', [PaitentController::class, 'get_patient_Testdetails']);
   
   Route::post('/last_report_machine', [PaitentController::class, 'last_report_machine_patient']);

   Route::post('/add_test_queue', [PaitentController::class, 'add_test_queue']);

   Route::post('/get_singleTestReport', [PaitentController::class, 'get_singleTestReport']);

       // get_verify_key
    Route::post('/get_verify_key', [CustomerController::class, 'get_verify_key']);

       // token verify key
    Route::post('/verifyToken', [CustomerController::class, 'verifyToken']);

       // logout
    Route::post('/logout', [CustomerController::class, 'logout']);


    // customer Data
    Route::post('/customer_data', [CustomerController::class, 'customer_data']);

    // paitentData Data
    Route::post('/paitentData', [CustomerController::class, 'paitentData']);

    // dlete customer Data
    Route::post('/delete_account', [CustomerController::class, 'delete_account']);

    // get faq Data
    Route::get('/getFaq', [CustomerController::class, 'getFaq']);

    // get about Data
   Route::post('/machine_test_status', [CustomerController::class, 'machine_test_status']);

   Route::post('/getActive_allowedTest', [CustomerController::class, 'getActive_allowedTest']);

   Route::post('/save_deviceDetails', [CustomerController::class, 'save_deviceDetails']);

   Route::post('/getMachineDetails', [CustomerController::class, 'getMachineDetails']);

   Route::post('/getTodaysReportsByMachine', [CustomerController::class, 'getTodaysReportsByMachine']);

   