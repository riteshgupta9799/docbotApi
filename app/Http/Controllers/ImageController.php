<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Http;  // Import the HTTP facade
use App\Models\Art;
use Carbon\Carbon;
use DB;
use Validator;

class ImageController extends Controller

{

    /**

     * Call the external API to upload the painting

     */

    //  public function uploadPainting(Request $request)
    //  {
    //      try {
    //          // Validate the request
    //          $validated = $request->validate([
    //              'painting' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
    //              'customer_id' => 'required',
    //              'art_id' => 'required',
    //          ]);

    //          // Extract request data
    //          $painting = $request->file('painting');
    //          $customerId = $request->input('customer_id');
    //          $artId = $request->input('art_id');

    //          // Prepare the HTTP request to the external API
    //          $response = Http::attach(
    //              'painting',                // Field name
    //              file_get_contents($painting->getRealPath()),  // File contents
    //              $painting->getClientOriginalName()  // Original filename
    //          )->post('https://artistai.genixbit.com/upload-painting', [  // External API URL
    //              'customer_id' => $customerId,
    //              'art_id' => $artId,
    //          ]);

    //          // Check response status
    //          if ($response->successful()) {
    //              return response()->json([
    //                  'status' => true,
    //                  'message' => 'Image uploaded successfully!',
    //                  'data' => $response->json(),
    //              ], 200);
    //          }

    //          return response()->json([
    //              'status' => false,
    //              'message' => 'Image upload failed!',
    //              'error' => $response->body(),
    //          ], $response->status());

    //      } catch (\Illuminate\Validation\ValidationException $e) {
    //          return response()->json([
    //              'status' => false,
    //              'message' => 'Validation failed!',
    //              'errors' => $e->errors(),
    //          ], 422);
    //      } catch (\Exception $e) {
    //          return response()->json([
    //              'status' => false,
    //              'message' => 'Error uploading the image!',
    //              'error' => $e->getMessage(),
    //          ], 500);
    //      }
    //  }


    public function uploadPainting(Request $request)

    {


        $validated = $request->validate([

            'painting' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',

        ]);


        $data = [
            'painting' => $request->file('painting'),
            'customer_id' => $request->customer_id,
            'art_id' => $request->art_id,
        ];

        try {
            $response = Http::attach('painting', file_get_contents($data['painting']->getRealPath()), $data['painting']->getClientOriginalName())
                ->post('https://artistai.genixbit.com/upload-painting/', [
                    'customer_id' => $data['customer_id'],
                    'art_id' => $data['art_id'],
                ]);

            if ($response->successful()) {
                return response()->json([
                    'status' => true,
                    'message' => 'Image uploaded successfully!',
                    'data' => $response->json(),
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Image upload failed!',
                    'error' => $response->body(), // Capture response body as error message
                ], $response->status());
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error calling external API',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function selectpainting(Request $request)
{
    // Validate input
    $validator = Validator::make($request->all(), [
        'urls'    => 'required|array',
        'urls.*'  => 'url',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status'  => false,
            'message' => $validator->errors()->first(),
        ], 400);
    }

    try {
        // Send data to external API
        $response = Http::post('https://artistai.genixbit.com/select-images/', [
            'urls' => $request->urls,
        ]);

        if ($response->successful()) {
            return response()->json([
                'status'  => true,
                'message' => 'Images successfully sent.',
                'data'    => $response->json(), // Ensure JSON response is properly formatted
            ]);
        } else {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to send images to external API.',
                'error'   => $response->body(), // Return API error response for debugging
            ], $response->status());
        }
    } catch (\Exception $e) {
        return response()->json([
            'status'  => false,
            'message' => 'Error calling external API',
            'error'   => $e->getMessage(),
        ], 500);
    }
}



    // public function selectpainting(Request $request)

    // {
    //     $validator = Validator::make($request->all(), [
    //         // 'art_unique_id' => 'required',

    //         'urls' => 'required|array',
    //         'urls.*' => 'url',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => $validator->errors()->first(),
    //         ], 400);
    //     }



    //     $data = [
    //         'urls' => $request->urls,

    //     ];

    //     try {
    //         $response = Http::post('https://artistai.genixbit.com/select-images/', [

    //             'urls' => $data['urls'],
    //         ]);

    //         if ($response->successful()) {
    //             return $response;
    //             // $art = Art::where('art_unique_id', $request->art_unique_id)->first();
    //             // if (!$art) {
    //             //     return response()->json([
    //             //         'status' => false,
    //             //         'message' => 'No Data found for the provided art_unique_id.',
    //             //     ]);
    //             // }
    //             // $urls = $request->urls;
    //             // $new = [];
    //             // $currentDateTime = Carbon::now();
    //             // $insertDate = $currentDateTime->toDateString();
    //             // $insertTime = $currentDateTime->toTimeString();
    //             // foreach ($urls as $index => $image) {
    //             //     if ($index == 0) {
    //             //         $artType = 'Mocup 1';
    //             //     } elseif ($index == 1) {
    //             //         $artType = 'Mocup 2';
    //             //     } else {
    //             //         $artType = 'Mocup 3';
    //             //     }

    //             //     $new[] = [
    //             //         'art_id' => $art->art_id,
    //             //         'art_type' => $artType,
    //             //         'image' => $image,
    //             //         'inserted_date' => $insertDate,
    //             //         'inserted_time' => $insertTime,
    //             //     ];
    //             // }

    //             // $added = DB::table('art_images')->insert($new);

    //             // if ($added) {
    //             //     return response()->json([
    //             //         'status' => true,
    //             //         'message' => 'Images have been successfully uploaded.',
    //             //     ]);
    //             // } else {
    //             //     return response()->json([
    //             //         'status' => false,
    //             //         'message' => 'Internal Server error',
    //             //     ]);
    //             // }

    //         } else {
    //             return $response;
    //         }
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Error calling external API',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }
}
