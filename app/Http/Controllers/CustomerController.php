<?php

namespace App\Http\Controllers;

use Laravel\Socialite\Facades\Socialite;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use GeoIp2\Database\Reader;
use Illuminate\Routing\Controller;
// use Stevebauman\Location\Facades\Location;
use Tymon\JWTAuth\Facades\JWTAuth;
use Stevebauman\Location\Facades\Location;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use App\Models\Test;
use App\Models\MachinesTest;
use App\Models\Patient;

class CustomerController extends Controller
{
    public function login_customer(Request $request)
    {


        $validated = $request->validate([
            'username' => 'required|exists:customers,username',
            'password' => 'required|string',
        ]);


        $credentials = [
            'username' => $validated['username'],
            'password' => $validated['password'],
        ];

        $user = auth('customer_api')->user();
        $user = Customer::where('username', $validated['username'])->first();
        $userNew = Customer::where('username', $request->username)->first();

        if($userNew->status=='Inactive'){
             return response()->json([
                'status' => false,
                'message' => 'Your Account Has Been Deleted'
            ]);
        }

        if($userNew->token !== null){
            return response()->json([
                'status'=>false,
                'message'=>'First logout in another devices'
            ]);
        }



        if (!$user) {

            return response()->json([
                'status' => false,
                'message' => 'Invalid username'
            ]);
        }
        if (!$userNew) {

            return response()->json([
                'status' => false,
                'message' => 'Invalid username'
            ]);
        }

        if (!$token = auth('customer_api')->attempt($credentials)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid password'
            ]);
        }


        $user->customer_profile = ($user->customer_profile);
        $user->update(['token' => $token]);
        $customer = $user->toArray();
        $customer['token'] = $token;

        $machineData = DB::table('machines')
            ->where('machine_id', $customer['machine_id'])
            ->first();




        return response()->json([
            'status' => true,
            'message' => 'Customer Found...',
            'customer' => $customer,
            'machineData' => $machineData ?? null,

        ]);
    }

    // get machine veryify key

    public function register_customer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => 'required|string|unique:customers,username',
            'password' => 'required|string|min:6',
            'email' => 'nullable|email|unique:customers,email',
            'mobile' => 'required|string|min:10|max:15|unique:customers,mobile',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Clean mobile number: keep last 10 digits
            $mobile = preg_replace('/\D/', '', $request->mobile);
            $mobile = substr($mobile, -10);

            $customer = new Customer();
            // $customer->customer_id = Str::uuid();
            $customer->token = null;
            $customer->name = trim($request->name);
            $customer->status = 'Active';
            $customer->username = trim($request->username);
            $customer->password = Hash::make($request->password);
            $customer->email = trim($request->email);
            $customer->mobile = $mobile;
            $customer->machine_id = $request->machine_id ?? 1;
            $customer->address = $request->address ?? null;
            $customer->inserted_date = now()->toDateString();
            $customer->inserted_time = now()->toTimeString();
            $customer->save();

            return response()->json([
                'status' => true,
                'message' => 'Customer registered successfully',
                'customer' => $customer
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function machine_test_status(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "customer_unique_id" => 'required|string',
            "bloodPressureModule" => 'required|string',
            "cholesterolUricAcidModule" => 'required|string',
            "glucometerModule" => 'required|string',
            "hemoglobinModule" => 'required|string',
            "pulseOximetryModule" => 'required|string',
            "rdtModule" => 'required|string',
            "ecgModule" => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();

        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid User.',
            ], 401);
        }

        $machineId = $customer->machine_id;

        $modules = [
            'bloodPressureModule',
            'cholesterolUricAcidModule',
            'glucometerModule',
            'hemoglobinModule',
            'pulseOximetryModule',
            'rdtModule',
            'ecgModule'
        ];

        $updatedModules = [];

        foreach ($modules as $moduleKey) {
            $status = $request->$moduleKey;

            if ($status === "1") {
                $test = Test::where('module_name', $moduleKey)->first();

                if ($test) {
                    // Update the machines_tests entry
                    MachinesTest::where('machine_id', $machineId)
                        ->where('test_id', $test->id)
                        ->update([
                            'test_name'=>$test->name,
                            'active_status' => 1,
                            'inserted_time' => Carbon::now()->format('H:i:s'),
                            'inserted_date' => Carbon::now()->format('Y-m-d'),
                        ]);

                    $updatedModules[] = $moduleKey;
                }
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Test module statuses updated successfully.',
            'updated_modules' => $updatedModules,
        ], 200);
    }   

    public function getActive_allowedTest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "customer_unique_id" => 'required|string'   
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $customer_unique_id = $request->customer_unique_id;

        $customer = Customer::where('customer_unique_id', $customer_unique_id)->first();

        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'Customer not found.',
            ], 404);
        }

        $machine_id = $customer->machine_id;

        // $active_tests = Test::where('machine_id', $machine_id)->where('status', 'active')->get();

        $active_tests = DB::table('machines_tests')
            ->join('tests', 'machines_tests.test_id', '=', 'tests.id')
            ->where('machines_tests.machine_id', $machine_id)
            ->where('machines_tests.active_status', 1)
            ->where('tests.status', 1)
            ->select('tests.*', 'machines_tests.active_status', 'machines_tests.machine_id')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Active tests retrieved successfully.',
            'active_tests' => $active_tests,
        ], 200);

    }

    public function save_deviceDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "customer_unique_id" => 'required|string',
            "serial_number"=>'required|string',
            "firmware_version"=>'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $customer_unique_id = $request->customer_unique_id;

        $customer = Customer::where('customer_unique_id', $customer_unique_id)->first();

        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'Customer not found.',
            ], 404);
        }

        $machine_id = $customer->machine_id;
        $serial_number = $request->serial_number;

        $machie= DB::table('machines')->where('machine_id',$machine_id)->first();    

        if(!$machie){
            return response()->json([
                'status' => false,
                'message' => 'Machine Not exists.',
            ], 400);
        }

        DB::table('machines')
            ->where('machine_id', $machine_id)
            ->update([
                'serial_number' => $serial_number,
                'firmware_version' => $request->firmware_version
            ]);

        $machie= DB::table('machines')->where('machine_id',$machine_id)->first();    

        return response()->json([
            'status' => true,
            'message' => 'Machine Details Saved Successfully',
            'data' => $machie
        ]);
    }

    public function getMachineDetails(Request $request)
    {
        $validated = $request->validate([
                'customer_unique_id' => 'required|string|max:255',
            ]);

        if(!$validated){
            return response()->json([
                'status' => false,
                'message' => 'Validation Failed',
            ], 400);
        }

        $customer = DB::table('customers')->where('customer_unique_id',$request->customer_unique_id)->first();    

        if(!$customer){
            return response()->json([
                'status' => false,
                'message' => 'Customer Not found.',
            ], 404);
        }


        $data = DB::table('customers')
            ->join('machines', 'machines.machine_id', '=', 'customers.machine_id')
            ->where('customers.customer_unique_id', $request->customer_unique_id)
            ->select('customers.*', 'machines.*')
            ->first();

        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'Customer or machine not found.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Machine Details',
            'data' => $data
        ]);
    }

    public function login_user(Request $request)
    {

        $validated = $request->validate([
            'email' => 'required|exists:users,email',
            'password' => 'required|string',
        ]);


        $credentials = [
            'email' => $validated['email'],
            'password' => $validated['password'],
        ];
        // dd($request->email);

        $user = auth('api')->user();
        $user = User::where('email', $validated['email'])->first();
        $userNew = User::where('email', $request->email)->first();



        if (!$user) {

            return response()->json([
                'status' => false,
                'message' => 'Invalid username'
            ]);
        }
        if (!$userNew) {

            return response()->json([
                'status' => false,
                'message' => 'Invalid username'
            ]);
        }

        if (!$token = auth('api')->attempt($credentials)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid password'
            ]);
        }


        $user->user_profile = ($user->user_profile);
        $customer = $user->toArray();
        $customer['token'] = $token;



        return response()->json([
            'status' => true,
            'message' => 'User Found...',
            'user' => $customer,

        ]);
    }

    public function verifyToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        // Try to find a customer with the provided token
        $customer = Customer::where('token', $request->token)->first();

        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or expired token.',
            ], 401);
        }

        return response()->json([
            'status' => true,
            'message' => 'Token is valid.',
            'customer' => $customer,
        ], 200);
    }

    public function get_verify_key(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'machine_unique_id' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $machine = DB::table('machines')
            ->where('machine_unique_id', $request->machine_unique_id)
            ->first();

        if (!$machine) {
            return response()->json([
                'status' => false,
                'message' => 'No Machine Found'
            ]);
        }
        return response()->json([
            'status' => true,
            'message' => ' Machine Found',
            'machine_verify_key' => $machine->verification_key
        ]);
    }


    public function logout(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'customer_unique_id' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }
        $customer = DB::table('customers')
            ->where('customer_unique_id', $request->customer_unique_id)
            ->first();

        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'No customer Found'
            ]);
        }


        DB::table('customers')
            ->where('customer_unique_id', $request->customer_unique_id)
            ->update([
                'token' => null
            ]);

        return response()->json([
            'status' => true,
            'message' => ' User Logout Successfully',

        ]);
    }

    public function customer_data(Request $request)
    {

        $validator = Validator::make($request->all(), [
            "customer_unique_id" => 'required',
             'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

         // Try to find a customer with the provided token
        $customer = Customer::where('token', $request->token)->first();

        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or expired token.',
            ], 401);
        }


        $customer_unique_id = $request->customer_unique_id;

        if ($customer->customer_unique_id !== $customer_unique_id) {
            return response()->json([
                'status' => false,
                'message' => 'Customer unique ID does not match.',
            ], 400);
        }

        if ($customer_unique_id) {

            $customer = Customer::where('customer_unique_id', $customer_unique_id)->first();
            $customers = DB::table('customers')->where('customers.customer_id', $customer->customer_id)
                ->leftJoin('machines','customers.machine_id','=','machines.machine_id')
                ->select('customers.customer_unique_id', 'customers.customer_profile', 'customers.name', 'customers.username', 'customers.email', 'customers.mobile', 'machines.machine_unique_id')
                ->orderBy('customers.customer_id', 'desc')
                ->first();

            if (!empty($customers)) {
                // Check if the customer profile image exists and append the full URL
                if ($customers->customer_profile) {
                    $customers->customer_profile = asset($customers->customer_profile);
                }

                return response()->json([
                    'status' => true,
                    'message' => "Customer Found Successfully",
                    "customer_data" => $customers
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => "Customer Not Found"
                ]);
            }
        } else {
            return response()->json([
                'status' => false,
                'message' => "Customer ID Required"
            ]);
        }
    }

    public function delete_account(Request $request) // customer side
    {

        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required',
            'token'=>'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }
           // Try to find a customer with the provided token

        $customer = Customer::where('token', $request->token)->first();

        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or expired token.',
            ], 401);
        }


        $customers = Customer::where('customer_unique_id', $customer->customer_unique_id)->first();
        if (!$customers) {
            return response()->json([
                'status' => false,
                'message' => 'No Customer Found!'
            ]);
        }

        
        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();
        $existing = Customer::where('customer_id', $customers->customer_id)->where('status', 'Inactive')->first();
        if ($existing) {
            return response()->json([
                'status' => false,
                'message' => 'Account is Alredy Deleted'
            ]);
        }
        $delete = Customer::where('customer_id', $customers->customer_id)->update([
            'status' => 'Inactive',
            'token' => null,

        ]);






        return response()->json([
            'status' => true,
            'message' => 'Account  Has Been Deleted   successfully'
        ]);
    }

    public function getFaq(){
        $data = DB::table('faqs')->where('status','Active')->orderBy('faq_id','desc')->get();
        if($data->isEmpty()){
            return response()->json([
                'status'=>false,
                'message'=>'No faq Found'
            ]);
        }
        return response()->json([
                'status'=>true,
                'message'=>'Faq Data Found',
                'faq'=>$data
            ]);
    }


    public function getTodaysReportsByMachine(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        // Get customer and machine_id
        $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();
        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid Customer ID.',
            ]);
        }

        $machine_id = $customer->machine_id;
        $today = date('Y-m-d');

        // Get all today’s reports for this machine, grouped by patient_id
        $groupedReports = DB::table('patient_reports')
            ->select('patient_id')
            ->where('machine_id', $machine_id)
            ->where('inserted_date', $today)
            ->groupBy('patient_id')
            ->get();

        $queueColors = [
            1 => '0xFFFFC107', // amber
            2 => '0xFF4CAF50', // green
            3 => '0xFF2196F3', // blue
            4 => '0xFFFF5722', // orange
            5 => '0xFF9C27B0', // purple
        ];

        $finalData = [];

        foreach ($groupedReports as $row) {
            $patient = Patient::find($row->patient_id);

            if (!$patient) continue;

            // Get all today’s reports for this patient with test data
            $reports = DB::table('patient_reports')
                ->join('tests', 'patient_reports.test_id', '=', 'tests.id')
                ->join('paitents', 'patient_reports.patient_id', '=', 'paitents.paitent_id')
                    ->where('patient_reports.patient_id', $row->patient_id)
                    ->where('patient_reports.inserted_date', $today)
                    ->where('patient_reports.machine_id', $machine_id)
                    ->orderBy('patient_reports.que_id')
                    ->get()
                    ->map(function ($report) use ($machine_id, $queueColors) {
                        return [
                            'report_id'     => $report->report_id,
                            'machine_id'    => $report->machine_id,
                            'patient_unique_id'    => $report->paitent_unique_id,
                            'inserted_time' => $report->inserted_time,
                            'inserted_date' => $report->inserted_date,
                            'result_key'    => $report->result_key,
                            'result_value'  => $report->result_value,
                            'test_name'     => $report->name,
                            'module_name'   => $report->module_name,
                            'que_id'        => $report->que_id,
                            'result_array'  => json_decode($report->result_array, true),
                            'queue_color'   => $queueColors[$report->que_id] ?? '0xFF607D8B', // default: grey
                            'this_machine'  => $report->machine_id == $machine_id ? 1 : 0
                        ];
                    });

            $finalData[] = [
                'patient_id' => $patient->paitent_id,
                'patient_name' => $patient->paitent_name,
                'reports' => $reports
            ];
        }

        return response()->json([
            'status' => true,
            'message' => 'Today’s patient reports grouped by patient.',
            'data' => $finalData
        ]);
    }


    

}

