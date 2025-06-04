<?php

namespace App\Http\Controllers;

use YoutubeDl\YoutubeDl;
use YoutubeDl\YoutubeDlException;
use YoutubeDl\Process\ProcessBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VideoController extends Controller
{
    // public function getVideoLink(Request $request)
    // {
    //     // Validate the URL input
    //     $request->validate([
    //         'url' => 'required|url'
    //     ]);

    //     // Log the received URL for debugging
    //     Log::debug('Received URL: ' . $request->input('url'));

    //     try {
    //         // Initialize YoutubeDl and pass options in the constructor
    //         Log::debug('Initializing YoutubeDl with cookies and options...');
    //         $yt = new YoutubeDl([
    //             'get-url' => true,  // Fetch only the playable URL
    //             'skip-download' => true, // Skip the download
    //             'cookies' => '/var/www/html/artist-backend/cookies.txt', // Path to the cookies.txt file
    //         ]);

    //         // Set the yt-dlp binary path
    //         Log::debug('Setting yt-dlp binary path...');
    //         $yt->setBinPath('/usr/bin/yt-dlp'); // Make sure the binary path is correct

    //         // Process the video link and fetch the playable URL
    //         Log::debug('Fetching video info for the URL...');
    //         $video = $yt->download($request->input('url'));

    //         // Extract the first playable URL
    //         Log::debug('Extracting the playable URL...');
    //         $playableUrl = $video[0]->getUrl();

    //         // Log the fetched playable URL
    //         Log::debug('Playable URL fetched: ' . $playableUrl);

    //         // Return the playable link as a response
    //         return response()->json([
    //             'playable_link' => $playableUrl,
    //         ]);
    //     } catch (YoutubeDlException $e) {
    //         // Handle errors from the yt-dlp process and log the error message
    //         Log::error('YoutubeDlException: ' . $e->getMessage());
    //         return response()->json([
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     } catch (\Exception $e) {
    //         // Handle any other unexpected errors and log the error message
    //         Log::error('General Exception: ' . $e->getMessage());
    //         return response()->json([
    //             'error' => 'An unexpected error occurred: ' . $e->getMessage(),
    //         ], 500);
    //     }
    // }


    public function getVideoLink(Request $request)
    {
        // Validate the URL input
        $request->validate([
            'url' => 'required|url',
        ]);

        // Log the received URL for debugging
        Log::debug('Received URL: ' . $request->input('url'));

        try {
            // Initialize the ProcessBuilder and configure options
            $processBuilder = new ProcessBuilder();
            $processBuilder->setOption('get-url', true); // Fetch only the playable URL
            $processBuilder->setOption('skip-download', true); // Skip download
            $processBuilder->setOption('cookies', '/var/www/html/artist-backend/cookies.txt'); // Path to cookies

            // Initialize YoutubeDl with the configured process builder
            Log::debug('Initializing YoutubeDl...');
            $yt = new YoutubeDl($processBuilder);

            // Set the yt-dlp binary path
            Log::debug('Setting yt-dlp binary path...');
            $yt->setBinPath('/usr/bin/yt-dlp'); // Ensure the correct path to the yt-dlp binary

            // Process the video link and fetch the playable URL
            Log::debug('Fetching video info for the URL...');
            $video = $yt->download($request->input('url'));

            // Extract the first playable URL
            Log::debug('Extracting the playable URL...');
            $playableUrl = $video[0]->getUrl();

            // Log the fetched playable URL
            Log::debug('Playable URL fetched: ' . $playableUrl);

            // Return the playable link as a response
            return response()->json([
                'playable_link' => $playableUrl,
            ]);
        } catch (YoutubeDlException $e) {
            // Handle errors from the yt-dlp process and log the error message
            Log::error('YoutubeDlException: ' . $e->getMessage());
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            // Handle any other unexpected errors and log the error message
            Log::error('General Exception: ' . $e->getMessage());
            return response()->json([
                'error' => 'An unexpected error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }
}
