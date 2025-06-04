<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;

class SocialMediaController extends Controller
{
    

public function downloadVideo(Request $request)
{
    $url = $request->input('url');

    if (!$url) {
        return response()->json(['error' => 'URL is required'], 400);
    }

    try {
        $client = new Client();
        $response = $client->get($url);

        // Use regular expressions or HTML parsing to extract the video URL
        preg_match('/"url":"(https:\/\/[^"]+\.mp4)"/', $response->getBody(), $matches);

        if (!isset($matches[1])) {
            return response()->json(['error' => 'Video URL not found'], 500);
        }

        return response()->json([
            'success' => true,
            'data' => ['playable_link' => $matches[1]],
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Failed to retrieve video URL'], 500);
    }
}

    public function downloadVideo_(Request $request)
{
    $url = $request->input('url');

    if (!$url) {
        return response()->json(['error' => 'URL is required'], 400);
    }

    $maxRetries = 3;  // Maximum number of retries
    $retryDelay = 5;  // Delay between retries in seconds

    try {
        $attempt = 0;
        $data = null;

        while ($attempt < $maxRetries) {
            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => "https://facebook-instagram-tiktok-downloader.p.rapidapi.com/all",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => "url=" . urlencode($url),
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/x-www-form-urlencoded",
                    "x-rapidapi-host: facebook-instagram-tiktok-downloader.p.rapidapi.com",
                    "x-rapidapi-key: d70350515dmshe71f5886436d5a4p12b338jsn1bba2f2afce1"
                ],
            ]);

            $response = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);

            if ($err) {
                return response()->json(['error' => "cURL Error #: $err"], 500);
            }

            // Log raw response for debugging
            Log::info('Raw API Response: ' . $response);

            $data = json_decode($response, true);

            // Check if the API responded with valid data
            if ($data) {
                if (isset($data['error'])) {
                    return response()->json(['error' => $data['error']], 500);
                }

                // Return the successful response if data is valid
                return response()->json([
                    'success' => true,
                    'data' => $data,
                ]);
            }

            // If the data is null or not valid, increment attempt and retry
            $attempt++;

            // Delay before retrying
            if ($attempt < $maxRetries) {
                sleep($retryDelay);
            }
        }

        // After retries, if it still fails, return an error
        return response()->json(['error' => 'Unable to fetch data after multiple attempts. Please try again later.'], 500);

    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}



}
