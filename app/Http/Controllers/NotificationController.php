<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Illuminate\Http\Request;
use Validator;


class NotificationController extends Controller
{
    public function sendNotification(Request $request)
    {
        $firebaseCredentials = (new Factory)->withServiceAccount(base_path('mira-monet-firebase-adminsdk-fbsvc-3d1bf73d07.json'));

        $messaging = $firebaseCredentials->createMessaging();

        $deviceToken = $request->input('token');

        $message = CloudMessage::withTarget('token', $deviceToken)
            ->withNotification([
                'title' => 'Hell0',
                'body' => 'Notification mila kya',
                'image'=>'https://images.unsplash.com/photo-1575936123452-b67c3203c357?q=80&w=2070&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D'
            ]);

        try {
            $messaging->send($message);
            return response()->json([
                'status' => true,
                'message' => 'Notification sent successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error sending notification: ' . $e->getMessage()
            ]);
        }
    }
}
