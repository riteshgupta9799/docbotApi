<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;
use Cloudinary\Cloudinary;
// Storage::disk('s3')->exists('/');path:

class FileUploadController extends Controller
{
    //     public function uploadFile(Request $request)
    // {
    //     Log::info('Upload API called.');

    //     // Validate request
    //     $request->validate([
    //         'file' => 'required|file',
    //     ]);

    //     $file = $request->file('file');
    //     $fileName = Str::random(10) . '_' . $file->getClientOriginalName();
    //     $filePath = "uploads/$fileName"; // Store all files in 'uploads/' directory

    //     Log::info('File details:', [
    //         'original_name' => $file->getClientOriginalName(),
    //         'mime_type' => $file->getMimeType(),
    //         'size' => $file->getSize(),
    //         'storage_path' => $filePath
    //     ]);

    //     // Check S3 Connection
    //     try {
    //         if (Storage::disk('s3')->exists('uploads/test.txt')) {
    //             Log::info("S3 is accessible.");
    //         } else {
    //             Log::info("S3 is accessible but 'uploads/' folder does not exist.");
    //         }
    //     } catch (\Exception $e) {
    //         Log::error('S3 connection failed:', ['error' => $e->getMessage()]);
    //         return response()->json(['error' => 'S3 connection failed'], 500);
    //     }

    //     // Upload file to S3
    //     try {
    //         $storedPath = Storage::disk('s3')->put($filePath, $file, 'public');
    //         if ($storedPath) {
    //             Log::info('File uploaded successfully to S3.', ['s3_path' => $storedPath]);
    //         } else {
    //             Log::error('File upload failed.');
    //             return response()->json(['error' => 'File upload to S3 failed'], 500);
    //         }
    //     } catch (\Exception $e) {
    //         Log::error('File upload error:', ['error' => $e->getMessage()]);
    //         return response()->json(['error' => 'File upload to S3 failed'], 500);
    //     }

    //     // Get public S3 URL
    //     $fileUrl = Storage::disk('s3')->url($filePath);
    //     Log::info('Generated S3 file URL:', ['file_url' => $fileUrl]);

    //     return response()->json([
    //         'message' => 'File uploaded successfully',
    //         'file_url' => $fileUrl,
    //     ]);
    // }

    public function uploadFile(Request $request)
    {
        Log::info('Upload API called.');

        // Validate request
        $request->validate([
            'file' => 'required|file',
            'folder' => 'required|string',
        ]);

        $file = $request->file('file');
        $folder = trim($request->folder, '/');
        $fileName = Str::random(10) . '_' . $file->getClientOriginalName();
        $filePath = "$folder/$fileName";

        Log::info('File details:', [
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'storage_path' => $filePath
        ]);

        // Check S3 Connection
        try {
            Storage::disk('s3')->exists('/');
            Log::info('S3 connection successful.');
        } catch (\Exception $e) {
            Log::error('S3 connection failed:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'S3 connection failed'], 500);
        }


        // // Check if the folder exists in S3
        // if (!Storage::disk('s3')->exists($folder)) {
        //     Log::warning("Folder '{$folder}' does not exist in S3. Creating folder...");
        //     Storage::disk('s3')->makeDirectory($folder);
        // } else {
        //     Log::info("Folder '{$folder}' exists in S3.");
        // }

        // Upload file to S3
        try {
            $storedPath = Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            if ($storedPath) {
                Log::info('File uploaded successfully to S3.', ['s3_path' => $storedPath]);
            } else {
                Log::error('File upload failed.');
                return response()->json(['error' => 'File upload to S3 failed'], 500);
            }
        } catch (\Exception $e) {
            Log::error('File upload error:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'File upload to S3 failed'], 500);
        }

        // Get public S3 URL
        $fileUrl = Storage::disk('s3')->url($filePath);
        Log::info('Generated S3 file URL:', ['file_url' => $fileUrl]);

        return response()->json([
            'message' => 'File uploaded successfully',
            'file_url' => $fileUrl,
        ]);
    }

    public function test(Request $request)
    {
        if ($request->hasFile('img')) {
            $file = $request->file('img');

            $filename = Str::random(10) . '_' . str_replace(' ', '_', $file->getClientOriginalName());
            $filePath = 'uploads/' . $filename;

            $storedPath = Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');

            if (!$storedPath) {
                return response()->json(['error' => 'File upload failed'], 500);
            }

            $fileUrl = Storage::disk('s3')->url($filePath);

            return response()->json([
                'status' => true,
                'message' => 'File uploaded successfully',
                'file_url' => $fileUrl
            ]);
        }
        return response()->json([
            'status' => false,
            'message' => 'No file uploaded'
        ]);
    }

    public function uploadCloud(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:5120',
        ]);

        $cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => config('services.cloudinary.cloud_name'),
                'api_key'    => config('services.cloudinary.api_key'),
                'api_secret' => config('services.cloudinary.api_secret'),
            ],
        ]);

        $uploadedFile = $cloudinary->uploadApi()->upload(
            $request->file('image')->getRealPath()
        );

        return response()->json([
            'url' => $uploadedFile['secure_url'],
        ]);
    }



    // public function uploadImage(Request $request)
    // {
    //     try {
    //         Log::info('Upload API called.');

    //         // Validate request
    //         $request->validate([
    //             'image' => 'required|image|max:2048',
    //         ]);

    //         $file = $request->file('image');
    //         $fileName = time() . '_' . $file->getClientOriginalName();
    //         $filePath = 'images/' . $fileName;

    //         Log::info('Uploading image:', [
    //             'original_name' => $file->getClientOriginalName(),
    //             'mime_type' => $file->getMimeType(),
    //             'size' => $file->getSize(),
    //             'storage_path' => $filePath
    //         ]);

    //         // Check S3 connection
    //         if (!Storage::disk('s3')->exists('/')) {
    //             Log::error('S3 connection failed: Unable to check bucket existence.');
    //             return response()->json(['error' => 'S3 connection failed'], 500);
    //         }

    //         // Upload file to S3
    //         $storedPath = Storage::disk('s3')->put($filePath, file_get_contents($file));
    //         if (!$storedPath) {
    //             Log::error('File upload failed.');
    //             return response()->json(['error' => 'File upload to S3 failed'], 500);
    //         }

    //         // Make file public (needed for Flysystem v3)
    //         Storage::disk('s3')->setVisibility($filePath, 'public');

    //         // Generate public URL
    //         $url = Storage::disk('s3')->url($filePath);
    //         Log::info('File uploaded successfully.', ['file_url' => $url]);

    //         return response()->json(['message' => 'Image uploaded successfully', 'url' => $url]);

    //     } catch (Exception $e) {
    //         Log::error('File upload error:', ['error' => $e->getMessage()]);
    //         return response()->json(['error' => 'File upload failed', 'details' => $e->getMessage()], 500);
    //     }
    // }
}
