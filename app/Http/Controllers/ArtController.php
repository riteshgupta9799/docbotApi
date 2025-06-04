<?php

namespace App\Http\Controllers;

use App\Models\Art;
use App\Models\ArtAdditionalDetails;
use App\Models\ArtImage;
use App\Models\ArtistArtStories;
use App\Models\Category;
use App\Models\Customer;
use App\Models\PortalPercentage;
use App\Models\ExhibitionGuest;
use App\Models\ArtistStoryParas;
use App\Models\City;
use App\Models\Country;
use App\Models\ApiCallCount;
use App\Models\ExhibitionRegistration;
use App\Models\ExhibitionGallery;
use App\Models\Exhibition;
use App\Models\State;
use App\Models\ExhibitionBooth;
use Auth;
use Carbon\Carbon;
use GuzzleHttp\Promise\Create;
use Illuminate\Http\Request;
use Validator;
use Illuminate\Support\Facades\DB;
use App\Models\OrderedArt;
use App\Models\WalletRequest;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Str;

class ArtController extends Controller
{
    //

    public function incrementApiCallCount($apiName, $artIds)
    {

        foreach ($artIds as $artId) {
            $currentDateTime = Carbon::now('Asia/Kolkata');
            $insertDate = $currentDateTime->toDateString();
            $insertTime = $currentDateTime->toTimeString();

            $project = Art::where('art_id', $artId)->first();

            $projectInsertedDate = Carbon::parse($project->inserted_date);

            ApiCallCount::where('api_name', $apiName)
                ->where('art_id', $project->project_id)
                ->whereRaw('DATEDIFF(inserted_date, ?) >= 30', [$projectInsertedDate])
                ->delete();

            $apiCallCount = ApiCallCount::where('api_name', $apiName)
                ->where('status', 'Active')
                ->where('art_id', $artId)
                ->first();

            if ($apiCallCount) {
                $apiCallCount->where('api_name', $apiName)
                    ->where('art_id', $artId)
                    ->where('status', 'Active')
                    ->update([
                        'call_count' => $apiCallCount->call_count + 1,
                    ]);
            } else {
                ApiCallCount::create([
                    'api_name' => $apiName,
                    'call_count' => 1,
                    // 'customer_id' => $customer_id,
                    'art_id' => $artId,
                    'status' => 'Active',
                    'inserted_date' => $insertDate,
                    'inserted_time' => $insertTime,
                ]);
            }
        }
    }
    public function generateUniqueId($customer_id, $category_id)
    {

        $customer = Customer::where('customer_id', $customer_id)->first();
        $category = Category::where('category_id', $category_id)->first();

        $categoryName = $category->category_name;
        $words = explode(' ', $categoryName);

        if (count($words) >= 2) {
            $firstLetter = strtoupper(substr($words[0], 0, 1));

            $secondLetter = strtoupper(substr($words[1], 1, 1));

            $result = $firstLetter . $secondLetter;
        } else {
            $result = strtoupper(substr($words[0], 0, 1));
        }

        $formattedCustomerId = str_pad($customer->customer_id, 6, '0', STR_PAD_LEFT);

        $year = date('y');
        $baseUniqueId = $result . $year . $formattedCustomerId;

        $ArtCount = Art::where('customer_id', $customer_id)
            ->count();

        if ($ArtCount) {

            $ArtCountPadded = str_pad($ArtCount + 1, 3, '0', STR_PAD_LEFT);
        } else {

            $ArtCountPadded = '001';
        }
        $rand = Str::uuid();

        $uniqueId = $baseUniqueId.$rand . $ArtCountPadded;


        return $uniqueId;
    }
    public function add_art(Request $request)
    {
        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ]);
        }

        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required|exists:customers,customer_unique_id',
            'title' => 'required',
            // 'artist_name' => 'required',
            'edition' => 'required',
            'since' => 'required',
            'price' => 'required_if:art_type,Online',
            'estimate_price_from' => 'required_if:art_type,Private|required_if:art_type,Exhibition',
            'estimate_price_to' => 'required_if:art_type,Private|required_if:art_type,Exhibition',
            'exhibition_id' => 'required_if:art_type,Exhibition',
            'pickup_address' => 'required',
            'pincode' => 'required',
            'country' => 'required',
            'state' => 'required',
            'city' => 'required',
            'frame' => 'required',
            'paragraph' => 'required',
            'category_id' => 'required',
            'images' => 'required',
            'artAdditinalDetails' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $customer = Auth::guard('customer_api')->user();
        $customer_unique_id = $request->customer_unique_id;

        if ($customer->customer_unique_id !== $customer_unique_id) {
            return response()->json([
                'status' => false,
                'message' => 'Customer unique ID does not match.',
            ], 400);
        }

        $customers = Customer::where('customer_unique_id', $customer_unique_id)->first();

        if (!$customers) {
            return response()->json([
                'status' => false,
                'message' => 'No Customer Found!',
            ]);
        }

        $customer_id = $customers->customer_id;
        $bank=DB::table('bank_details')->where('customer_id',$customer_id)->first();

        if(!$bank){
            return response()->json([
                'status'=>false,
                'message'=>'First Add Your Bank Details in  your profile section'
            ]);
        }

        $existingArt = Art::where('customer_id', $customer_id)
            ->where('title', $request->title)
            ->first();

        if ($existingArt) {
            return response()->json([
                'status' => false,
                'message' => 'An Art with this title already exists for this customer.',
            ]);
        }

        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        $uploadedImages = [];
        $artData = [
            'customer_id' => $customer_id,
            'art_unique_id' =>  $this->generateUniqueId($customer_id, $request->category_id),
            'title' => $request->title,
            'artist_name' => $customers->name,
            'category_id' => $request->category_id,
            'exhibition_id' => $request->exhibition_id,
            'edition' => $request->edition,
            'price' => $request->price,
            'since' => $request->since,
            'pickup_address' => $request->pickup_address,
            'pincode' => $request->pincode,
            'country' => $request->country,
            'state' => $request->state,
            'city' => $request->city,
            'frame' => $request->frame,
            'art_type' => $request->art_type,
            'portal_percentages' => $request->portal_percentages,
            'paragraph' => $request->paragraph,
            'description' => $request->description,
            'status' => 'Pending',
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ];

        $art = Art::create($artData);
        $artId = $art->art_id;

        // dd($artId);

        $existingImages = [];

        $uploadedImages = [];
        $existingImages = []; // To keep track of already uploaded images by file path

        // Validate that images are provided
        if (!$request->has('images') || empty($request->images)) {
            return response()->json([
                'status' => false,
                'message' => 'No images provided.',
            ], 400);
        }

        foreach ($request->images as $image) {
            if (!isset($image['art_image']) || !$image['art_image']->isValid()) {
                continue;
            }

            $file = $image['art_image'];

            $fileName = uniqid('art_', true) . '.' . $file->getClientOriginalExtension();
            $filePath = 'selling/image/' . $fileName;

            if (in_array($filePath, $existingImages)) {
                continue;
            }

            $file->move(public_path('selling/image'), $fileName);

            $uploadedImages[] = [
                'art_id' => $artId,
                'art_type' => $image['type'],
                'image' => $filePath,
                'inserted_date' => $insertDate,
                'inserted_time' => $insertTime,
            ];

            $existingImages[] = $filePath;
        }

        if (!empty($uploadedImages)) {
            DB::table('art_images')->insert($uploadedImages);
        }
        $artAdditinalDetails = $request->artAdditinalDetails;
        // $artAdditinalDetails = json_decode($artAdditinalDetails, true);
        $insertData = [];
        foreach ($artAdditinalDetails as $artAdditinalDetail) {
            $insertData[] = [
                'art_id' => $artId,
                'art_data_id' => $artAdditinalDetail['art_data_id'],
                'description' => $artAdditinalDetail['description'],
                'inserted_date' => $insertDate,
                'inserted_time' => $insertTime,
            ];
        }
        ArtAdditionalDetails::insert($insertData);
        return response()->json([
            'status' => true,
            'message' => 'Art Added Successfully',
            'art_unique_id' => $art->art_unique_id
        ]);
    }

    public function get_images(Request $request)
    {
        $art_id = $request->art_id;
        $data = DB::table('art_images')->where('art_id', $art_id)->first();
        $data->image = url($data->image) ?? null;
        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    public function get_all_art()
    {
        $arts = Art::with([
            'artAdditionalDetails' => function ($query) {
                $query->select('art_additional_details_id', 'art_id', 'art_data_id', 'description')
                    ->with([
                        'artData' => function ($query) {
                            $query->select('art_data_id', 'art_data_title');
                        }
                    ]);
            },
            'artImages' => function ($query) {
                $query->select('art_image_id', 'art_id', 'art_type', 'image');
            },
        ])
            ->with([
                'countries' => function ($query) {
                    $query->select('country_id', 'country_name');
                },
                'states' => function ($query) {
                    $query->select('state_subdivision_id', 'state_subdivision_name');
                },
                'cities' => function ($query) {
                    $query->select('cities_id', 'name_of_city');
                },
                'category' => function ($query) {
                    $query->select('category_id', 'category_name', 'sub_text', 'category_image', 'category_icon')
                        ->with([
                            'SubCategory' => function ($query) {
                                $query->select('sub_category_1_id', 'category_id', 'sub_category_1_name');
                            }
                        ]);
                },
                'customer' => function ($query) {
                    $query->select('customer_id', 'customer_unique_id', 'name', 'customer_profile', 'introduction');
                }
            ])
            ->where('art_type', 'Online')
            ->where('status', 'Approved')
            ->orderBy('art_id', 'desc')
            ->get();

        if ($arts->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No Art Found'
            ]);
        }
        $artIds = $arts->pluck('art_id')->toArray();


        $this->incrementApiCallCount('get_all_art', $artIds);


        $artsData = $arts->map(function ($art) {
            return [

                'artist_unique_id' => $art->customer->customer_unique_id,
                'art_unique_id' => $art->art_unique_id,
                'title' => $art->title,
                'artist_name' => $art->artist_name,
                'art_type' => $art->art_type,
                'category' => [
                    'category_name' => $art->category->category_name,
                    'category_icon' => url($art->category->category_icon),
                    'category_image' => $art->category->category_image,
                    'sub_text' => $art->category->sub_text,
                    'sub_category' => $art->category->SubCategory ? [
                        'sub_category_id' => $art->category->SubCategory->sub_category_1_id,
                        'sub_category_name' => $art->category->SubCategory->sub_category_1_name,
                    ] : null,
                ],
                'edition' => $art->edition,
                'price' => $art->price ?? $art->estimate_price_from . ' - ' . $art->estimate_price_to,
                'since' => $art->since,
                'pickup_address' => $art->pickup_address,
                'pincode' => $art->pincode,
                'country' => $art->country,
                'state' => $art->state,
                'city' => $art->city,
                'frame' => $art->frame,
                'paragraph' => $art->paragraph,
                'status' => $art->status,
                'country' => [
                    'country_id' => $art->countries->country_id,
                    'country_name' => $art->countries->country_name
                ],
                'state' => [
                    'state_id' => $art->states->state_subdivision_id,
                    'state_name' => $art->states->state_subdivision_name
                ],
                'city' => [
                    'city_id' => $art->cities->cities_id,
                    'city_name' => $art->cities->name_of_city
                ],
                'art_additional_details' => $art->artAdditionalDetails->map(function ($detail) {
                    return [
                        'art_data' => $detail->artData ? [
                            'art_data_id' => $detail->artData->art_data_id,
                            'art_data_title' => $detail->artData->art_data_title,
                        ] : null,
                        'description' => $detail->description,
                    ];
                }),
                'artImages' => $art->artImages->map(function ($image) {
                    return [
                        'art_image_id' => $image->art_image_id,
                        'art_type' => $image->art_type,
                        'image' => url($image->image),
                    ];
                }),
            ];
        });
        return response()->json([
            'status' => true,
            'artdata' => $artsData
        ]);
    }


    public function addArtwork(Request $request)
    {
        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ]);
        }
        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required|exists:customers,customer_unique_id',
            'title' => 'required',
            'category_id' => 'required',
            'sub_category_1_id' => 'required',
            'edition' => 'required',
            'art_type' => 'required',
            'artist_name' => 'required',
            'price' => 'required_if:art_type,Online',
            'estimate_price_from' => 'required_if:art_type,Private|required_if:art_type,Exhibition',
            'estimate_price_to' => 'required_if:art_type,Private|required_if:art_type,Exhibition',
            'exhibition_id' => 'required_if:art_type,Exhibition',
            'since' => 'required',
            'pickup_address' => 'required',
            'portal_percentages' => 'required',
            'pincode' => 'required',
            'country' => 'required',
            'state' => 'required',
            'city' => 'required',
            'frame' => 'required',
            'paragraph' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $customer = Auth::guard('customer_api')->user();
        $customer_unique_id = $request->customer_unique_id;

        if ($customer->customer_unique_id != $customer_unique_id) {
            return response()->json([
                'status' => false,
                'message' => 'Customer unique ID does not match.',
            ], 400);
        }
        $customers = Customer::where('customer_unique_id', $customer_unique_id)->first();

        if (!$customers) {
            return response()->json([
                'status' => false,
                'message' => 'No Customer Found!',
            ]);
        }

        $customer_id = $customers->customer_id;

        $bank=DB::table('bank_details')->where('customer_id',$customer_id)->first();

        if(!$bank){
            return response()->json([
                'status'=>false,
                'message'=>'First Add Your Bank Details in  your profile section'
            ]);
        }

        $existingArt = Art::where('customer_id', $customer_id)
            ->where('title', $request->title)
            // ->where('art_unique_id', $this->generateUniqueId($customer_id, $request->category_id))
            ->first();

        if ($existingArt) {
            return response()->json([
                'status' => false,
                'message' => 'An Art with this title already exists for this customer.',
            ]);
        }

        $currentDateTime = Carbon::now('Asia/Kolkata');

        // $exhibition = Exhibition::where('exhibition_unique_id', $request->exhibition_unique_id)->first();
        // dd($exhibition);
        $artData = [
            'customer_id' => $customer_id,
            'art_unique_id' => $this->generateUniqueId($customer_id, $request->category_id),
            'title' => $request->title,
            'artist_name' => $request->artist_name,
            'category_id' => $request->category_id,
            'sub_category_1_id' => $request->sub_category_1_id,
            'edition' => $request->edition,
            'art_type' => $request->art_type,
            'exhibition_id' => $request->exhibition_id ?? null,
            'price' => $request->price,
            'estimate_price_from' => $request->estimate_price_from,
            'estimate_price_to' => $request->estimate_price_to,
            'since' => $request->since,
            'pickup_address' => $request->pickup_address,
            'pincode' => $request->pincode,
            'country' => $request->country,
            'state' => $request->state,
            'city' => $request->city,
            'frame' => $request->frame,
            'paragraph' => $request->paragraph,
            'portal_percentages' => $request->portal_percentages,
            'status' => 'Pending',
            'inserted_date' => $currentDateTime->toDateString(),
            'inserted_time' => $currentDateTime->toTimeString(),
        ];

        try {
            $art = Art::create($artData);
            $artId = $art->id;

            // Use lastInsertId() as a fallback
            if (!$artId) {
                $artId = DB::getPdo()->lastInsertId();
            }
            if ($request->category_id == '1') {
                $category = Category::where('category_id', $request->category_id)->first();
                return response()->json([
                    'status' => true,
                    'message' => 'Artwork added successfully.',
                    'art_unique_id' => $art->art_unique_id,
                    'category_id' => $art->category_id,
                    'category_name' => $category->category_name,
                ]);
            } else {
                return response()->json([
                    'status' => true,
                    'message' => 'Artwork added successfully.',
                    'art_unique_id' => $art->art_unique_id,
                    'category_id' => $art->category_id,
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to add artwork: ' . $e->getMessage(),
            ], 500);
        }
    }



    public function addArtAdditionalDetails(Request $request)
    {
        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ]);
        }
        $validator = Validator::make($request->all(), [
            'art_unique_id' => 'required|exists:art,art_unique_id',
            'art_data_id' => 'required',
            'description' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }
        $customer = Auth::guard('customer_api')->user();

        $art_unique_id = $request->art_unique_id;

        $art = Art::where('art_unique_id', $art_unique_id)->first();

        if (!$art) {
            return response()->json([
                'status' => false,
                'message' => 'No Art Found',
            ]);
        }

        $art_data_id = $request->art_data_id;
        $description = $request->description;
        $existingDetail = ArtAdditionalDetails::where('art_id', $art->art_id)
            ->where('art_data_id', $art_data_id)
            ->where('description', $description)
            ->first();

        if ($existingDetail) {
            return response()->json([
                'status' => false,
                'message' => 'These additional details have already been added.',
            ]);
        }

        $currentDateTime = Carbon::now('Asia/Kolkata');
        ArtAdditionalDetails::create([
            'art_id' => $art->art_id,
            'art_data_id' => $request->art_data_id,
            'description' => $request->description,
            'inserted_date' => $currentDateTime->toDateString(),
            'inserted_time' => $currentDateTime->toTimeString(),
        ]);

        $additionalDetails = ArtAdditionalDetails::with('artData')->where('art_id', $art->art_id)->get();
        $data = [];
        foreach ($additionalDetails as $detail) {
            $data[] = [
                'art_data_title' => $detail->artData->art_data_title,
                'description' => $detail->description,
            ];
        }

        return response()->json([
            'status' => true,
            'message' => 'Additional details added successfully.',
            'additional_details' => $data,
        ]);
    }
    public function addArtAdditionalDetailsnew(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'art_unique_id' => 'required|exists:art,art_unique_id',
            'data' => 'required|array',
            'data.*.art_data_id' => 'required',
            'data.*.description' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $customer = Auth::guard('customer_api')->user();

        $art_unique_id = $request->art_unique_id;

        $art = Art::where('art_unique_id', $art_unique_id)->first();

        if (!$art) {
            return response()->json([
                'status' => false,
                'message' => 'No Art Found',
            ]);
        }

        $currentDateTime = Carbon::now('Asia/Kolkata');
        $addedDetails = [];

        foreach ($request->data as $item) {
            $art_data_id = $item['art_data_id'];
            $description = $item['description'];

            $existingDetail = ArtAdditionalDetails::where('art_id', $art->art_id)
                ->where('art_data_id', $art_data_id)
                ->where('description', $description)
                ->first();

            if ($existingDetail) {
                continue;
            }

            $additionalDetail = ArtAdditionalDetails::create([
                'art_id' => $art->art_id,
                'art_data_id' => $art_data_id,
                'description' => $description,
                'inserted_date' => $currentDateTime->toDateString(),
                'inserted_time' => $currentDateTime->toTimeString(),
            ]);

            $addedDetails[] = [
                'art_data_title' => $additionalDetail->artData->art_data_title,
                'description' => $additionalDetail->description,
            ];
        }

        if (empty($addedDetails)) {
            return response()->json([
                'status' => false,
                'message' => 'No new detail.',
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Additional details added successfully.',
            'additional_details' => $addedDetails,
        ]);
    }



    public function addArtImage(Request $request)
    {
        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ]);
        }
        $validator = Validator::make($request->all(), [
            'art_unique_id' => 'required|exists:art,art_unique_id',
            'art_type' => 'required',
            'image' => 'required|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }
        $art_unique_id = $request->art_unique_id;

        $art = Art::where('art_unique_id', $art_unique_id)->first();

        if (!$art) {
            return response()->json([
                'status' => false,
                'message' => 'No Art Found',
            ]);
        }

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $fileName = time() . '_' . Str::random(5) . '.' . $file->getClientOriginalExtension();
            $filePath = 'selling/image/' . $fileName;
            $file->move(public_path('selling/image'), $fileName);


            $currentDateTime = Carbon::now('Asia/Kolkata');
            $image = ArtImage::create([
                'art_id' => $art->art_id,
                'art_type' => $request->art_type,
                'image' => $filePath,
                'inserted_date' => $currentDateTime->toDateString(),
                'inserted_time' => $currentDateTime->toTimeString(),
            ]);



            return response()->json([
                'status' => true,
                'message' => 'Image added successfully.',
                'image_path' => url($filePath),
                'art_image_id' => $image->id
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'No image file provided.',
            ], 400);
        }
    }

    public function get_portal_percentage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }


        $percentages = DB::table('portal_percentages')
            ->where('role', $request->role)
            ->get();


        if ($percentages->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No portal percentage data found.',
            ], 404);
        }
        $data = [];
        foreach ($percentages as $percentage) {
            $data[] = [
                'portal_percentages_id' => $percentage->portal_percentages_id,
                'percentage' => $percentage->percentage . '%',
            ];
        }
        return response()->json([
            'status' => true,
            'data' => $data,
        ]);
    }


    public function delete_art_image(Request $request)
    {

        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ]);
        }
        $validator = Validator::make($request->all(), [
            "art_image_id" => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $artImage = ArtImage::where('art_image_id', $request->art_image_id)->first();

        if (!$artImage) {
            return response()->json([
                'status' => true,
                'message' => 'No image Found!'
            ]);
        }

        $delete = ArtImage::where('art_image_id', $request->art_image_id)->delete();
        if ($delete) {
            return response()->json([
                'status' => true,
                'message' => 'Image Removed!'
            ]);
        } else {
            return response()->json([
                'status' => true,
                'message' => 'Internal Server Error'
            ]);
        }
    }

    public function cancel_art(Request $request)
    {

        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            "art_unique_id" => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }


        $art = Art::where('art_unique_id', $request->art_unique_id)->first();

        if (!$art) {
            return response()->json([
                'status' => false,
                'message' => 'No Art Found!',
            ], 404);
        }

        DB::beginTransaction();
        try {
            $art_id = $art->art_id;

            ArtImage::where('art_id', $art_id)->delete();
            ArtAdditionalDetails::where('art_id', $art_id)->delete();
            $artistStoryDeleted = DB::table('artist_stories')->where('art_id', $art_id)->delete();

            $delete = Art::where('art_unique_id', $request->art_unique_id)->delete();

            if ($delete) {
                DB::commit();
                return response()->json([
                    'status' => true,
                    'message' => 'Art Cancelled and all related data removed!',
                ], 200);
            } else {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to remove the art.',
                ], 500);
            }
        } catch (\Exception $e) {

            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Internal Server Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function art_detail(Request $request)
    {
        // if (!Auth::guard('customer_api')->check()) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Unauthorized access.',
        //     ]);
        // }
        $validator = Validator::make($request->all(), [
            'art_unique_id' => 'required|exists:art,art_unique_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }
        $art = Art::where('art_unique_id', $request->art_unique_id)->first();
        // dd($art->customer_id);
        $seller = Customer::where('customer_id', $art->customer_id)->first();
        $category = Category::where('category_id', $art->category_id)->first();
        $subCategory = DB::table('sub_category_1')->where('sub_category_1_id', $art->sub_category_1_id)->first();
        $country = Country::where('country_id', $art->country)->first();
        $state = State::where('state_subdivision_id', $art->state)->first();
        $city = City::where('cities_id', $art->city)->first();


        $art->price = $art->price ? $art->price : $art->estimate_price_from . '-' . $art->estimate_price_to;

        $colorCode = DB::table('status_color')
            ->where('status_name', $art->status)
            ->select('status_color_code')
            ->first();
        if (!$art) {
            return response()->json([
                'status' => false,
                'message' => 'No Art Found'
            ]);
        }

        $artImages = ArtImage::where('art_id', $art->art_id)->get();

        $image   = [];
        foreach ($artImages as $artImage) {
            $image[] = [
                'art_type' => $artImage->art_type,
                'image' => isset($artImage->image) ? url($artImage->image) : null,
            ];
        }
        $ArtAdditionalDetails = ArtAdditionalDetails::where('art_id', $art->art_id)->with('artData')->get();
        // dd($ArtAdditionalDetails);
        $details = [];
        foreach ($ArtAdditionalDetails as $additionalDetail) {
            $details[] = [
                'art_data_title' => $additionalDetail->artData->art_data_title,
                'description' => $additionalDetail->description,
            ];
        }
        $newPer=$art->portal_percentages;
        $cleanPercentage = str_replace('%', '', $newPer);


        if (!empty($art->exhibition_id)) {
            $exhibition_booth = DB::table('exhibition_booths')
                ->where('exhibition_id', $art->exhibition_id)
                ->exists(); // Check if any record exists instead of fetching the whole row

            $is_booth = $exhibition_booth ? 1 : 0;
            $art_submission_type = $is_booth ? 'Exhibition Space' : 'Exhibition or Auction';
        } else {
            $art_submission_type = $art->art_type;
            $is_booth = 0;
        }



        $art['colorCode'] = $colorCode->status_color_code??null;
        $art['artist_fcm_token'] = $seller->fcm_token;
        $art['details'] = $details;
        $art['image'] = $image;
        $art['category_name'] = $category->category_name;
        $art['sub_category_1_name'] = $subCategory->sub_category_1_name;
        $art['state_subdivision_name'] = $state->state_subdivision_name;
        $art['country_name'] = $country->country_name;
        $art['name_of_city'] = $city->name_of_city;
        $art['portalpercentages'] = $cleanPercentage;
        $art['art_submission_type'] = $art_submission_type;
        $art['is_booth'] = $is_booth;
        return response()->json([
            'status' => true,
            'artallDetails' => $art
        ]);
    }

    public function get_artist_all_art_deatils(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required|exists:customers,customer_unique_id',
            'role' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }



        $customer_unique_id = $request->customer_unique_id;

        // dd($customer_unique_id);


        $customerData = Customer::where('customer_unique_id', $request->customer_unique_id)->first();

        if (!$customerData) {
            return response()->json([
                'status' => false,
                'message' => 'No Customer Found',
            ], 404);
        }

        if ($request->role == 'customer') {

            $arts = Art::with([
                'artAdditionalDetails' => function ($query) {
                    $query->select('art_additional_details_id', 'art_id', 'art_data_id', 'description')
                        ->with([
                            'artData' => function ($query) {
                                $query->select('art_data_id', 'art_data_title');
                            }
                        ]);
                },
                'artImages' => function ($query) {
                    $query->select('art_image_id', 'art_id', 'art_type', 'image');
                },
            ])
                ->with([
                    'countries' => function ($query) {
                        $query->select('country_id', 'country_name');
                    },
                    'states' => function ($query) {
                        $query->select('state_subdivision_id', 'state_subdivision_name');
                    },
                    'cities' => function ($query) {
                        $query->select('cities_id', 'name_of_city');
                    },
                    'category' => function ($query) {
                        $query->select('category_id', 'category_name', 'sub_text', 'category_image', 'category_icon');
                    }
                ])
                ->where('customer_id', $customerData->customer_id)
                ->whereIn('art_type',  ['Online','Private'])
                ->whereIn('status', ['Approved','Sold'])
                ->orderBy('art_id', 'desc')
                ->get();
        }else{
            $arts = Art::with([
                'artAdditionalDetails' => function ($query) {
                    $query->select('art_additional_details_id', 'art_id', 'art_data_id', 'description')
                        ->with([
                            'artData' => function ($query) {
                                $query->select('art_data_id', 'art_data_title');
                            }
                        ]);
                },
                'artImages' => function ($query) {
                    $query->select('art_image_id', 'art_id', 'art_type', 'image');
                },
            ])
                ->with([
                    'countries' => function ($query) {
                        $query->select('country_id', 'country_name');
                    },
                    'states' => function ($query) {
                        $query->select('state_subdivision_id', 'state_subdivision_name');
                    },
                    'cities' => function ($query) {
                        $query->select('cities_id', 'name_of_city');
                    },
                    'category' => function ($query) {
                        $query->select('category_id', 'category_name', 'sub_text', 'category_image', 'category_icon');
                    }
                ])
                ->where('customer_id', $customerData->customer_id)
                ->orderBy('art_id', 'desc')
                ->get();
        }



        if ($arts->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No Art Found'
            ]);
        }

        $artsData = $arts->map(function ($art) {

            $total_view = ApiCallCount::where('art_id', $art->art_id)->sum('call_count');
            $total_view = strval($total_view);
            $colorCode = DB::table('status_color')
                ->where('status_name', $art->status)
                ->select('status_color_code')
                ->first();
            $today = Carbon::today()->toDateString();
            $data = Art::where('art_id', $art->art_id)->where('status', 'Approved')->where('is_boost', '1')->where('boost_valid_upto', '>=', $today)->first();
            if ($data) {
                $is_boost = true;
            } else {
                $is_boost = false;
            }

            $artist_stories = DB::table('artist_stories')->where('art_id', $art->art_id)
            ->where('customer_id', $art->customer_id)->first();
        if ($artist_stories) {
            $istroy = true;
        } else {
            $istroy = false;
        }


                if($art->exhibition_id){
                    $exhibition_booths = DB::table('exhibition_booths')
                    ->where('exhibition_id', $art->exhibition_id)
                    ->first();

                    $is_booth = $exhibition_booths ? 1 : 0;
                    $art_submission_type = ($is_booth == 1) ? 'Exhibition Space' : 'Exhibition or Auction';

                } else {
                $art_submission_type = $art->art_type;
            }

            return [

                'art_submission_type'=>$art_submission_type,
                'istroy'=>$istroy,
                'reason' => $art->reason,
                'art_unique_id' => $art->art_unique_id,
                'is_boost' => $is_boost,
                'title' => $art->title,
                'artist_name' => $art->artist_name,
                'art_type' => $art->art_type,
                'category' => [
                    'category_name' => $art->category->category_name,
                    'category_icon' => url($art->category->category_icon) ?? null,
                    'category_image' => isset($art->category->category_image) ? url($art->category->category_image) : null,
                    'sub_text' => $art->category->sub_text,
                ],
                'edition' => $art->edition,
                'price' => (string) ($art->price ?? ($art->estimate_price_from . ' - ' . $art->estimate_price_to)),
                // 'estimate_price'=>$art->estimate_price_from . ' - ' . $art->estimate_price_to,
                'since' => $art->since,
                'pickup_address' => $art->pickup_address,
                'pincode' => $art->pincode,
                'country' => $art->country,
                'state' => $art->state,
                'city' => $art->city,
                'frame' => $art->frame,
                'paragraph' => $art->paragraph,
                'status' => $art->status,
                'colorCode' => $colorCode->status_color_code??null,
                'country' => [
                    'country_id' => $art->countries->country_id,
                    'country_name' => $art->countries->country_name
                ],
                'state' => [
                    'state_id' => $art->states->state_subdivision_id,
                    'state_name' => $art->states->state_subdivision_name
                ],
                'city' => $art->cities ? [
                    'city_id' => $art->cities->cities_id,
                    'city_name' => $art->cities->name_of_city
                ] : null,
                'art_additional_details' => $art->artAdditionalDetails->map(function ($detail) {
                    return [
                        'art_data' => $detail->artData ? [
                            'art_data_id' => $detail->artData->art_data_id,
                            'art_data_title' => $detail->artData->art_data_title,
                        ] : null,
                        'description' => $detail->description,
                    ];
                }),
                'artImages' => $art->artImages->map(function ($image) {
                    return [
                        'art_image_id' => $image->art_image_id,
                        'art_type' => $image->art_type,
                        'image' => url($image->image),
                    ];
                }),
            ];
        });
        $customer = [
            'artist_unique_id' => $customerData->customer_unique_id,
            'artist_name' => $customerData->artist_name??$customerData->name,
            'artist_profile' => isset($customerData->customer_profile) ? url($customerData->customer_profile) : null,
            'introduction' => $customerData->introduction,
        ];


        return response()->json([
            'status' => true,
            'artdata' => $artsData,
            'artistData' => $customer
        ]);
    }


    public function add_artist_art_stories(Request $request)
    {
        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ]);
        }
        $validator = Validator::make($request->all(), [
            'art_unique_id' => 'required|exists:art,art_unique_id',
            // 'customer_unique_id' => 'required',
            'paragraph' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }
        $customer = Auth::guard('customer_api')->user();

        $art_unique_id = $request->art_unique_id;
        $customer_unique_id = $customer->customer_unique_id;




        $art = Art::where('art_unique_id', $art_unique_id)->first();
        $customers = Customer::where('customer_unique_id', $customer_unique_id)->first();
        if (!$art) {
            return response()->json([
                'status' => false,
                'message' => 'No Art Found',
            ]);
        }


        $currentDateTime = Carbon::now('Asia/Kolkata');
        $art_story =  ArtistArtStories::create([
            'art_id' => $art->art_id,
            'customer_id' => $customers->customer_id,
            'paragraph' => $request->paragraph,
            'status' => 'Inactive',
            'inserted_date' => $currentDateTime->toDateString(),
            'inserted_time' => $currentDateTime->toTimeString(),
        ]);


        $artistArtStories = ArtistArtStories::where('art_id', $art->art_id)->get();

        return response()->json([
            'status' => true,
            'message' => 'Art Story added successfully.',
            'artistArtStories' => $artistArtStories,
        ]);
    }


    public function get_all_exhibition(Request $request)
    {
        $today = Carbon::today()->format('Y-m-d');
        $exhibitions = Exhibition::with([
            'country' => function ($query) {
                $query->select('country_id', 'country_name');
            },
            'state' => function ($query) {
                $query->select('state_subdivision_id', 'state_subdivision_name');
            },
            'city' => function ($query) {
                $query->select('cities_id', 'name_of_city');
            },
            'category' => function ($query) {
                $query->select('category_id', 'category_name');
            },
            // 'exhibition_art' => function ($query) {
            //     $query->select('exhibition_art_id', 'exhibition_id', 'art_id')
            //         ->with([
            //             'art' => function ($query) {
            //                 $query->select('art_id', 'art_unique_id', 'customer_id', 'title', 'artist_name', 'edition', 'category_id', 'art_type', 'price', 'estimate_price_from', 'estimate_price_to', 'since', 'pickup_address', 'pincode', 'country', 'state', 'city', 'frame', 'paragraph', 'portal_percentages', 'buy_date')
            //                     ->with([
            //                         'artAdditionalDetails' => function ($query) {
            //                             $query->select('art_additional_details_id', 'art_id', 'art_data_id', 'description')
            //                                 ->with([
            //                                     'artData' => function ($query) {
            //                                         $query->select('art_data_id', 'art_data_title');
            //                                     }
            //                                 ]);
            //                         },
            //                         'artImages' => function ($query) {
            //                             $query->select('art_image_id', 'art_id', 'art_type', 'image');
            //                         },
            //                         'countries' => function ($query) {
            //                             $query->select('country_id', 'country_name');
            //                         },
            //                         'states' => function ($query) {
            //                             $query->select('state_subdivision_id', 'state_subdivision_name');
            //                         },
            //                         'cities' => function ($query) {
            //                             $query->select('cities_id', 'name_of_city');
            //                         }
            //                     ]);
            //             }
            //         ]);
            // },
            // 'exhibition_gallery',
            // 'exhibition_guests',
            // 'exhibition_sponsor',
        ])
            // ->where('start_date', '<=', $today)
            ->where('status', 'Active')
            ->get();

        $exhibitions->each(function ($exhibition) {
            $exhibition->logo = url($exhibition->logo);
            // $exhibition->exhibition_gallery->each(function ($gallery) {
            //     $gallery->link = url($gallery->link);
            // });

            // $exhibition->exhibition_guests->each(function ($guest) {
            //     $guest->photo = url($guest->photo);
            // });
            // $exhibition->exhibition_art->each(function ($art) {
            //     $art->art->artImages->each(function ($image) {
            //         $image->image = url($image->image);
            //     });
            // });

            // $exhibition->exhibition_sponsor->each(function ($sponsor) {
            //     $sponsor->logo = url($sponsor->logo);
            // });
        });

        return response()->json([
            'status' => true,
            'exhibition' => $exhibitions

        ]);
    }

    public function get_single_exhibition__(Request $request)
    {

        // if (!Auth::guard('customer_api')->check()) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Unauthorized access.',
        //     ]);
        // }
        $validator = Validator::make($request->all(), [
            'exhibition_unique_id' => 'required|exists:exhibitions,exhibition_unique_id',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $exhibition = Exhibition::where('exhibition_unique_id', $request->exhibition_unique_id)->first();

        if (empty($exhibition)) {
            return response()->json([
                'status' => false,
                'message' => 'No Exhibition Found'
            ]);
        }

        $today = Carbon::today()->format('Y-m-d');
        $exhibitions = Exhibition::with([
            'country' => function ($query) {
                $query->select('country_id', 'country_name');
            },
            'state' => function ($query) {
                $query->select('state_subdivision_id', 'state_subdivision_name');
            },
            'city' => function ($query) {
                $query->select('cities_id', 'name_of_city');
            },
            'exhibition_art' => function ($query) {
                $query->select('exhibition_art_id', 'exhibition_id', 'art_id')
                    ->with([
                        'art' => function ($query) {
                            $query->select('art_id', 'art_unique_id', 'customer_id', 'title', 'artist_name', 'edition', 'category_id', 'art_type', 'price', 'estimate_price_from', 'estimate_price_to', 'since', 'pickup_address', 'pincode', 'country', 'state', 'city', 'frame', 'paragraph', 'portal_percentages', 'buy_date')
                                ->with([
                                    'artAdditionalDetails' => function ($query) {
                                        $query->select('art_additional_details_id', 'art_id', 'art_data_id', 'description')
                                            ->with([
                                                'artData' => function ($query) {
                                                    $query->select('art_data_id', 'art_data_title');
                                                }
                                            ]);
                                    },
                                    'artImages' => function ($query) {
                                        $query->select('art_image_id', 'art_id', 'art_type', 'image');
                                    },
                                    'countries' => function ($query) {
                                        $query->select('country_id', 'country_name');
                                    },
                                    'states' => function ($query) {
                                        $query->select('state_subdivision_id', 'state_subdivision_name');
                                    },
                                    'cities' => function ($query) {
                                        $query->select('cities_id', 'name_of_city');
                                    }
                                ]);
                        }
                    ]);
            },
            'exhibition_gallery',
            'exhibition_guests',
            'exhibition_sponsor',
        ])
            ->where('start_date', '>=', $today)
            ->where('exhibition_unique_id', $request->exhibition_unique_id)
            ->first();

        if ($exhibitions && $exhibitions->logo) {
            $exhibitions->logo = url($exhibitions->logo);
        }

        $exhibitions->exhibition_gallery = null;
        if ($exhibitions->exhibition_gallery) {
            $exhibitions->exhibition_gallery->each(function ($gallery) {
                if ($gallery->link) {
                    $gallery->link = url($gallery->link);
                }
            });
        }


        $exhibitions->exhibition_guests->each(function ($guest) {
            if ($guest->photo) {
                $guest->photo = url($guest->photo);
            }
        });

        $exhibitions->exhibition_sponsor->each(function ($sponsor) {
            $sponsor->logo = url($sponsor->logo);
        });

        $exhibitions->exhibition_art->each(function ($exhibitionArt) {
            $exhibitionArt->art->price =  $exhibitionArt->art->price ?? $exhibitionArt->art->estimate_price_from . ' - ' . $exhibitionArt->art->estimate_price_to;
            $exhibitionArt->art->artImages->each(function ($artImage) {
                if ($artImage->image) {
                    $artImage->image = url($artImage->image);
                }
            });
        });
        // dd($exhibitions);

        $recentExhibition = Exhibition::with([
            'country' => function ($query) {
                $query->select('country_id', 'country_name');
            },
            'state' => function ($query) {
                $query->select('state_subdivision_id', 'state_subdivision_name');
            },
            'city' => function ($query) {
                $query->select('cities_id', 'name_of_city');
            },
        ])
            ->where('end_date', '<=', $today)
            ->get();

        $recentExhibition->each(function ($exhibition) {
            $exhibition->logo = url($exhibition->logo);
            $exhibition->exhibition_gallery->each(function ($gallery) {
                $gallery->link = url($gallery->link);
            });

            $exhibition->exhibition_guests->each(function ($guest) {
                $guest->photo = url($guest->photo);
            });
            $exhibition->exhibition_art->each(function ($art) {
                $art->art->artImages->each(function ($image) {
                    $image->image = url($image->image);
                });
            });

            $exhibition->exhibition_sponsor->each(function ($sponsor) {
                $sponsor->logo = url($sponsor->logo);
            });
        });


        $upcomingExhibitions = Exhibition::with([
            'country' => function ($query) {
                $query->select('country_id', 'country_name');
            },
            'state' => function ($query) {
                $query->select('state_subdivision_id', 'state_subdivision_name');
            },
            'city' => function ($query) {
                $query->select('cities_id', 'name_of_city');
            },

        ])
            ->where('start_date', '>=', $today)
            ->get();

        $upcomingExhibitions->each(function ($exhibition) {
            $exhibition->logo = url($exhibition->logo);
            $exhibition->exhibition_gallery->each(function ($gallery) {
                $gallery->link = url($gallery->link);
            });

            $exhibition->exhibition_guests->each(function ($guest) {
                $guest->photo = url($guest->photo);
            });
            $exhibition->exhibition_art->each(function ($art) {
                $art->art->artImages->each(function ($image) {
                    $image->image = url($image->image);
                });
            });

            $exhibition->exhibition_sponsor->each(function ($sponsor) {
                $sponsor->logo = url($sponsor->logo);
            });
        });

        return response()->json([
            'status' => true,
            'exhibition' => $exhibitions,
            'recentExhibition' => $recentExhibition,
            'upcomingExhibitions' => $upcomingExhibitions,

        ]);
    }

    public function get_single_exhibition(Request $request)
    {
        // Validate the request input
        $validator = Validator::make($request->all(), [
            'exhibition_unique_id' => 'required|exists:exhibitions,exhibition_unique_id',
            'type' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        // Retrieve the exhibition data
        $exhibition = DB::table('exhibitions')->where('exhibition_unique_id', $request->exhibition_unique_id)->first();

        if (empty($exhibition)) {
            return response()->json([
                'status' => false,
                'message' => 'No Exhibition Found'
            ]);
        }

        $today = Carbon::today()->format('Y-m-d');
        $exhibitions = Exhibition::with([
            'country' => function ($query) {
                $query->select('country_id', 'country_name');
            },
            'state' => function ($query) {
                $query->select('state_subdivision_id', 'state_subdivision_name');
            },
            'city' => function ($query) {
                $query->select('cities_id', 'name_of_city');
            },
            'category' => function ($query) {
                $query->select('category_id', 'category_name');
            },
            'exhibition_art' => function ($query) {
                $query->select('exhibition_art_id', 'exhibition_id', 'art_unique_id','title', 'artist_name', 'edition', 'category_id', 'art_type', 'price', 'estimate_price_from', 'estimate_price_to', 'since', 'pickup_address', 'pincode', 'country', 'state', 'city', 'frame', 'paragraph', 'portal_percentages', 'status','bid_start_from','bid_start_to')
                    ->with([
                        'ExhibitionArtImage' => function ($query) {
                            $query->select('exhibition_art_image_id', 'exhibition_art_id', 'art_type', 'image');
                        },
                        'countries' => function ($query) {
                            $query->select('country_id', 'country_name');
                        },
                        'states' => function ($query) {
                            $query->select('state_subdivision_id', 'state_subdivision_name');
                        },
                        'cities' => function ($query) {
                            $query->select('cities_id', 'name_of_city');
                        }
                    ]);
            },
            'exhibition_gallery',
            'exhibition_time_slot' => function ($query) {
                $query->orderBy('date', 'asc')
                    ->orderBy('slot_name', 'asc');
            },
            'exhibition_guests',
            'exhibition_sponsor',
        ])
            // ->where('start_date', '>=', $today)
            ->where('exhibition_unique_id', $exhibition->exhibition_unique_id)
            ->first();

        $totalRegistrations = ExhibitionRegistration::where('exhibition_id', $exhibition->exhibition_id)->count();


        // $isFull=false;
        // if ($request->type == 'customer') {
        //     if ($totalRegistrations >= $exhibition->visitor_count) {
        //         $isFull=true;
        //     }
        // }
        // $exhibitions->isFull=$isFull;
        // Ensure that $exhibitions is not null before accessing its properties
        if (!$exhibitions) {
            return response()->json([
                'status' => false,
                'message' => 'No Exhibition Found'
            ]);
        }


        // Handle logo URL if it exists
        if ($exhibitions->logo) {
            $exhibitions->logo = isset($exhibitions->logo) ? url($exhibitions->logo) : null;
        }

        if ($exhibitions->banner) {
            $exhibitions->banner = isset($exhibitions->banner) ? url($exhibitions->banner) : null;
        }


        if ($exhibitions->exhibition_gallery) {
            $exhibitions->exhibition_gallery->each(function ($gallery) {
                if ($gallery->link) {
                    $gallery->link = url($gallery->link);
                }
            });
        } else {
            // If exhibition_gallery is null, explicitly set it to null
            $exhibitions->exhibition_gallery = null;
        }

        if ($exhibitions->exhibition_guests) {
            $exhibitions->exhibition_guests->each(function ($guest) {
                if ($guest->photo) {
                    $guest->photo = url($guest->photo);
                }
            });
        }

        if ($exhibitions->exhibition_sponsor) {
            $exhibitions->exhibition_sponsor->each(function ($sponsor) {
                if ($sponsor->logo) {
                    $sponsor->logo = url($sponsor->logo);
                }
            });
        }

        // if ($exhibitions->exhibition_art) {
        //     $exhibitions->exhibition_art->each(function ($exhibitionArt) {
        //         // Check if the art exists before accessing its properties
        //         if ($exhibitionArt->art) {
        //             $exhibitionArtist = Customer::where('customer_id', $exhibitionArt->art->customer_id)->first();
        //             $exhibitionArt->art->artist_unique_id = $exhibitionArtist ? $exhibitionArtist->customer_unique_id : null;


        //             $exhibitionArt->art->price = $exhibitionArt->art->price ?? $exhibitionArt->art->estimate_price_from . ' - ' . $exhibitionArt->art->estimate_price_to;

        //             // Check if artImages exist before accessing them
        //             if ($exhibitionArt->art->artImages) {
        //                 $exhibitionArt->art->artImages->each(function ($artImage) {
        //                     if ($artImage->image) {
        //                         $artImage->image = isset($artImage->image) ? url($artImage->image) : null;
        //                     }
        //                 });
        //             }
        //         }
        //     });
        // }

        if ($exhibitions->exhibition_art->isNotEmpty()) {
            $exhibitions->exhibition_art->each(function ($exhibitionArt) {
                // Fetch artist details
                // $exhibitionArtist = Customer::where('customer_id', $exhibitionArt->customer_id)->first();
                // $exhibitionArt->artist_unique_id = $exhibitionArtist ? $exhibitionArtist->customer_unique_id : null;

                // Format price correctly
                $exhibitionArt->price = $exhibitionArt->price ?? "{$exhibitionArt->estimate_price_from} - {$exhibitionArt->estimate_price_to}";

                // Update image URLs
                if ($exhibitionArt->ExhibitionArtImage->isNotEmpty()) {
                    $exhibitionArt->ExhibitionArtImage->each(function ($artImage) {
                        $artImage->image = url($artImage->image);
                    });
                }
            });
        }



        $recentExhibition = Exhibition::with([
            'country' => function ($query) {
                $query->select('country_id', 'country_name');
            },
            'state' => function ($query) {
                $query->select('state_subdivision_id', 'state_subdivision_name');
            },
            'city' => function ($query) {
                $query->select('cities_id', 'name_of_city');
            },
        ])
            ->where('end_date', '<=', $today)
            ->where('status', 'Active')
            ->get();

        $recentExhibition->each(function ($exhibition) {
            if ($exhibition->logo) {
                $exhibition->logo = url($exhibition->logo);
            }
            if ($exhibition->exhibition_gallery) {
                $exhibition->exhibition_gallery->each(function ($gallery) {
                    if ($gallery->link) {
                        $gallery->link = url($gallery->link);
                    }
                });
            }
            if ($exhibition->exhibition_guests) {
                $exhibition->exhibition_guests->each(function ($guest) {
                    if ($guest->photo) {
                        $guest->photo = url($guest->photo);
                    }
                });
            }
            // if ($exhibition->exhibition_art) {
            //     $exhibition->exhibition_art->each(function ($art) {
            //         if ($art->art->artImages) {
            //             $art->art->artImages->each(function ($image) {
            //                 if ($image->image) {
            //                     $image->image = url($image->image);
            //                 }
            //             });
            //         }
            //     });
            // }

            if ($exhibition->exhibition_art) {
                $exhibition->exhibition_art->each(function ($art) {
                    if ($art->ExhibitionArtImage) { // Fix: Use correct relationship name
                        $art->ExhibitionArtImage->each(function ($image) {
                            if ($image->image) {
                                $image->image = url($image->image);
                            }
                        });
                    }
                });
            }
            if ($exhibition->exhibition_sponsor) {
                $exhibition->exhibition_sponsor->each(function ($sponsor) {
                    if ($sponsor->logo) {
                        $sponsor->logo = url($sponsor->logo);
                    }
                });
            }
        });

        $upcomingExhibitions = Exhibition::with([
            'country' => function ($query) {
                $query->select('country_id', 'country_name');
            },
            'state' => function ($query) {
                $query->select('state_subdivision_id', 'state_subdivision_name');
            },
            'city' => function ($query) {
                $query->select('cities_id', 'name_of_city');
            },
        ])
            ->where('start_date', '>=', $today)
            ->where('status', 'Active')

            ->get();

        // Modify upcoming exhibitions
        $upcomingExhibitions->each(function ($exhibition) {
            if ($exhibition->logo) {
                $exhibition->logo = url($exhibition->logo);
            }
            if ($exhibition->exhibition_gallery) {
                $exhibition->exhibition_gallery->each(function ($gallery) {
                    if ($gallery->link) {
                        $gallery->link = url($gallery->link);
                    }
                });
            }
            if ($exhibition->exhibition_guests) {
                $exhibition->exhibition_guests->each(function ($guest) {
                    if ($guest->photo) {
                        $guest->photo = url($guest->photo);
                    }
                });
            }

            if ($exhibition->exhibition_art) {
                $exhibition->exhibition_art->each(function ($exhibitionArt) {
                    if ($exhibitionArt->art) {
                        $exhibitionArtist = Customer::where('customer_id', $exhibitionArt->art->customer_id)->first();
                        $exhibitionArt->art->artist_unique_id = $exhibitionArtist ? $exhibitionArtist->customer_unique_id : null;


                        $exhibitionArt->art->price = $exhibitionArt->art->price ?? $exhibitionArt->art->estimate_price_from . ' - ' . $exhibitionArt->art->estimate_price_to;

                        if ($exhibitionArt->art->artImages) {
                            $exhibitionArt->art->artImages->each(function ($artImage) {
                                if ($artImage->image) {
                                    $artImage->image = isset($artImage->image) ? url($artImage->image) : null;
                                }
                            });
                        }
                    }
                });
            }
            if ($exhibition->exhibition_sponsor) {
                $exhibition->exhibition_sponsor->each(function ($sponsor) {
                    if ($sponsor->logo) {
                        $sponsor->logo = url($sponsor->logo);
                    }
                });
            }
        });

        return response()->json([
            'status' => true,
            'exhibition' => $exhibitions,
            'recentExhibition' => $recentExhibition,
            'upcomingExhibitions' => $upcomingExhibitions,
        ]);
    }


    public function get_all_private_art()
    {
        $arts = Art::with([
            'artAdditionalDetails' => function ($query) {
                $query->select('art_additional_details_id', 'art_id', 'art_data_id', 'description')
                    ->with([
                        'artData' => function ($query) {
                            $query->select('art_data_id', 'art_data_title');
                        }
                    ]);
            },
            'artImages' => function ($query) {
                $query->select('art_image_id', 'art_id', 'art_type', 'image');
            },
        ])
            ->with([
                'countries' => function ($query) {
                    $query->select('country_id', 'country_name');
                },
                'states' => function ($query) {
                    $query->select('state_subdivision_id', 'state_subdivision_name');
                },
                'cities' => function ($query) {
                    $query->select('cities_id', 'name_of_city');
                },
                'category' => function ($query) {
                    $query->select('category_id', 'category_name', 'sub_text', 'category_image', 'category_icon');
                },
                'customer' => function ($query) {
                    $query->select('customer_id', 'customer_unique_id', 'name', 'customer_profile', 'introduction');
                }
            ])
            ->where('art_type', 'Private')
            ->where('status', 'Approved')
            ->orderBy('art_id', 'desc')
            ->get();

        if ($arts->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No Art Found'
            ]);
        }

        $artsData = $arts->map(function ($art) {
            return [

                'artist_unique_id' => $art->customer->customer_unique_id,
                'art_unique_id' => $art->art_unique_id,
                'title' => $art->title,
                'artist_name' => $art->artist_name,
                'art_type' => $art->art_type,
                'category' => [
                    'category_name' => $art->category->category_name,
                    'category_icon' => url($art->category->category_icon),
                    'category_image' => $art->category->category_image,
                    'sub_text' => $art->category->sub_text,
                ],
                'edition' => $art->edition,
                'price' => $art->price ?? $art->estimate_price_from . ' - ' . $art->estimate_price_to,
                'since' => $art->since,
                'pickup_address' => $art->pickup_address,
                'pincode' => $art->pincode,
                'country' => $art->country,
                'state' => $art->state,
                'city' => $art->city,
                'frame' => $art->frame,
                'paragraph' => $art->paragraph,
                'status' => $art->status,
                'country' => [
                    'country_id' => $art->countries->country_id,
                    'country_name' => $art->countries->country_name
                ],
                'state' => [
                    'state_id' => $art->states->state_subdivision_id,
                    'state_name' => $art->states->state_subdivision_name
                ],
                'city' => [
                    'city_id' => $art->cities->cities_id,
                    'city_name' => $art->cities->name_of_city
                ],
                'art_additional_details' => $art->artAdditionalDetails->map(function ($detail) {
                    return [
                        'art_data' => $detail->artData ? [
                            'art_data_id' => $detail->artData->art_data_id,
                            'art_data_title' => $detail->artData->art_data_title,
                        ] : null,
                        'description' => $detail->description,
                    ];
                }),
                'artImages' => $art->artImages->map(function ($image) {
                    return [
                        'art_image_id' => $image->art_image_id,
                        'art_type' => $image->art_type,
                        'image' => url($image->image),
                    ];
                }),
            ];
        });
        return response()->json([
            'status' => true,
            'artdata' => $artsData
        ]);
    }

    // boost


    // public function get_all_arts()
    // {
    //     $today = Carbon::today()->toDateString();

    //     $arts = Art::with([
    //         'artAdditionalDetails' => function ($query) {
    //             $query->select('art_additional_details_id', 'art_id', 'art_data_id', 'description')
    //                 ->with(['artData' => function ($query) {
    //                     $query->select('art_data_id', 'art_data_title');
    //                 }]);
    //         },
    //         'artImages' => function ($query) {
    //             $query->select('art_image_id', 'art_id', 'art_type', 'image');
    //         },
    //     ])
    //         ->with([
    //             'countries' => function ($query) {
    //                 $query->select('country_id', 'country_name');
    //             },
    //             'states' => function ($query) {
    //                 $query->select('state_subdivision_id', 'state_subdivision_name');
    //             },
    //             'cities' => function ($query) {
    //                 $query->select('cities_id', 'name_of_city');
    //             },
    //             'category' => function ($query) {
    //                 $query->select('category_id', 'category_name', 'sub_text', 'category_image', 'category_icon');
    //             },
    //             'customer' => function ($query) {
    //                 $query->select('customer_id', 'customer_unique_id', 'name', 'customer_profile', 'introduction');
    //             }
    //         ])
    //         ->where('art_type', 'Online')
    //         ->where('status', 'Approved')
    //         ->where('is_boost', '1')
    //         ->whereDate('boost_valid_upto', '>=', $today)
    //         ->orderBy('art_id', 'desc')
    //         ->get();

    //     if (!$arts->isEmpty()) {
    //         $artIds = $arts->pluck('art_id')->toArray();
    //         $this->incrementApiCallCount('get_all_art', $artIds);
    //     }

    //     $withoutBoostArt = Art::with([
    //         'artAdditionalDetails' => function ($query) {
    //             $query->select('art_additional_details_id', 'art_id', 'art_data_id', 'description')
    //                 ->with(['artData' => function ($query) {
    //                     $query->select('art_data_id', 'art_data_title');
    //                 }]);
    //         },
    //         'artImages' => function ($query) {
    //             $query->select('art_image_id', 'art_id', 'art_type', 'image');
    //         },
    //     ])
    //         ->with([
    //             'countries' => function ($query) {
    //                 $query->select('country_id', 'country_name');
    //             },
    //             'states' => function ($query) {
    //                 $query->select('state_subdivision_id', 'state_subdivision_name');
    //             },
    //             'cities' => function ($query) {
    //                 $query->select('cities_id', 'name_of_city');
    //             },
    //             'category' => function ($query) {
    //                 $query->select('category_id', 'category_name', 'sub_text', 'category_image', 'category_icon');
    //             },
    //             'customer' => function ($query) {
    //                 $query->select('customer_id', 'customer_unique_id', 'name', 'customer_profile', 'introduction');
    //             }
    //         ])
    //         ->where('art_type', 'Online')
    //         ->where('status', 'Approved')
    //         ->inRandomOrder()
    //         ->take(2)
    //         ->orderBy('art_id', 'desc')
    //         ->get();

    //     if (!$withoutBoostArt->isEmpty()) {
    //         $withoutBoostartIds = $withoutBoostArt->pluck('art_id')->toArray();
    //         $this->incrementApiCallCount('get_all_art', $withoutBoostartIds);
    //     }

    //     $artsData = $arts->map(function ($art) {
    //         $total_view = ApiCallCount::where('art_id', $art->art_id)->sum('call_count');

    //         return [
    //             'artist_unique_id' => $art->customer->customer_unique_id,
    //             'art_unique_id' => $art->art_unique_id,
    //             'title' => $art->title,
    //             'artist_name' => $art->artist_name,
    //             'art_type' => $art->art_type,
    //             'category' => [
    //                 'category_name' => $art->category->category_name,
    //                 'category_icon' => url($art->category->category_icon),
    //                 'category_image' => $art->category->category_image,
    //                 'sub_text' => $art->category->sub_text,
    //             ],
    //             'total_view' => $total_view,
    //             'edition' => $art->edition,
    //             'price' => $art->price ?? $art->estimate_price_from . ' - ' . $art->estimate_price_to,
    //             'since' => $art->since,
    //             'pickup_address' => $art->pickup_address,
    //             'pincode' => $art->pincode,
    //             'country' => $art->country,
    //             'state' => $art->state,
    //             'city' => $art->city,
    //             'frame' => $art->frame,
    //             'paragraph' => $art->paragraph,
    //             'status' => $art->status,
    //             'country' => [
    //                 'country_id' => $art->countries->country_id,
    //                 'country_name' => $art->countries->country_name
    //             ],
    //             'state' => [
    //                 'state_id' => $art->states->state_subdivision_id,
    //                 'state_name' => $art->states->state_subdivision_name
    //             ],
    //             'city' => [
    //                 'city_id' => $art->cities->cities_id,
    //                 'city_name' => $art->cities->name_of_city
    //             ],
    //             'art_additional_details' => $art->artAdditionalDetails->map(function ($detail) {
    //                 return [
    //                     'art_data' => $detail->artData ? [
    //                         'art_data_id' => $detail->artData->art_data_id,
    //                         'art_data_title' => $detail->artData->art_data_title,
    //                     ] : null,
    //                     'description' => $detail->description,
    //                 ];
    //             }),
    //             'artImages' => $art->artImages->map(function ($image) {
    //                 return [
    //                     'art_image_id' => $image->art_image_id,
    //                     'art_type' => $image->art_type,
    //                     'image' => url($image->image),
    //                 ];
    //             }),
    //         ];
    //     });

    //     $withoutBoostArtsData = $withoutBoostArt->map(function ($art) {
    //         $total_view = ApiCallCount::where('art_id', $art->art_id)->sum('call_count');

    //         return [
    //             'artist_unique_id' => $art->customer->customer_unique_id,
    //             'art_unique_id' => $art->art_unique_id,
    //             'title' => $art->title,
    //             'artist_name' => $art->artist_name,
    //             'art_type' => $art->art_type,
    //             'category' => [
    //                 'category_name' => $art->category->category_name,
    //                 'category_icon' => url($art->category->category_icon),
    //                 'category_image' => $art->category->category_image,
    //                 'sub_text' => $art->category->sub_text,
    //             ],
    //             'total_view'=>$total_view,
    //             'edition' => $art->edition,
    //             'price' => $art->price ?? $art->estimate_price_from . ' - ' . $art->estimate_price_to,
    //             'since' => $art->since,
    //             'pickup_address' => $art->pickup_address,
    //             'pincode' => $art->pincode,
    //             'country' => $art->country,
    //             'state' => $art->state,
    //             'city' => $art->city,
    //             'frame' => $art->frame,
    //             'paragraph' => $art->paragraph,
    //             'status' => $art->status,
    //             'country' => [
    //                 'country_id' => $art->countries->country_id,
    //                 'country_name' => $art->countries->country_name
    //             ],
    //             'state' => [
    //                 'state_id' => $art->states->state_subdivision_id,
    //                 'state_name' => $art->states->state_subdivision_name
    //             ],
    //             'city' => [
    //                 'city_id' => $art->cities->cities_id,
    //                 'city_name' => $art->cities->name_of_city
    //             ],
    //             'art_additional_details' => $art->artAdditionalDetails->map(function ($detail) {
    //                 return [
    //                     'art_data' => $detail->artData ? [
    //                         'art_data_id' => $detail->artData->art_data_id,
    //                         'art_data_title' => $detail->artData->art_data_title,
    //                     ] : null,
    //                     'description' => $detail->description,
    //                 ];
    //             }),
    //             'artImages' => $art->artImages->map(function ($image) {
    //                 return [
    //                     'art_image_id' => $image->art_image_id,
    //                     'art_type' => $image->art_type,
    //                     'image' => url($image->image),
    //                 ];
    //             }),
    //         ];
    //     });

    //     $artsData = $arts->isEmpty() ? $withoutBoostArtsData : $artsData->merge($withoutBoostArtsData);

    //     return response()->json([
    //         'status' => true,
    //         'artdata' => $artsData
    //     ]);
    // }

    public function get_all_arts()
    {
        $today = Carbon::today()->toDateString();

        $mapArtData = function ($arts) {
            return $arts->map(function ($art) {
                $total_view = ApiCallCount::where('art_id', $art->art_id)->sum('call_count');

                return [
                    'artist_unique_id' => $art->customer->customer_unique_id,
                    'art_unique_id' => $art->art_unique_id,
                    'title' => $art->title,
                    'artist_name' => $art->artist_name,
                    'art_type' => $art->art_type,
                    'category' => [
                        'category_name' => $art->category->category_name,
                        'category_icon' => url($art->category->category_icon),
                        'category_image' => $art->category->category_image,
                        'sub_text' => $art->category->sub_text,
                        'sub_category' => $art->category->SubCategory ? [
                            'sub_category_id' => $art->category->SubCategory->sub_category_1_id,
                            'sub_category_name' => $art->category->SubCategory->sub_category_1_name,
                        ] : null,
                    ],
                    'total_view' => $total_view,
                    'edition' => $art->edition,
                    'price' => (string) ($art->price ?? ($art->estimate_price_from . ' - ' . $art->estimate_price_to)),
                    'since' => $art->since,
                    'pickup_address' => $art->pickup_address,
                    'pincode' => $art->pincode,
                    'country' => $art->country,
                    'state' => $art->state,
                    'city' => $art->city,
                    'frame' => $art->frame,
                    'paragraph' => $art->paragraph,
                    'status' => $art->status,
                    'country' => [
                        'country_id' => $art->countries->country_id,
                        'country_name' => $art->countries->country_name
                    ],
                    'state' => [
                        'state_id' => $art->states->state_subdivision_id,
                        'state_name' => $art->states->state_subdivision_name
                    ],
                    'city' => [
                        'city_id' => $art->cities->cities_id,
                        'city_name' => $art->cities->name_of_city
                    ],
                    'art_additional_details' => $art->artAdditionalDetails->map(function ($detail) {
                        return [
                            'art_data' => $detail->artData ? [
                                'art_data_id' => $detail->artData->art_data_id,
                                'art_data_title' => $detail->artData->art_data_title,
                            ] : null,
                            'description' => $detail->description,
                        ];
                    }),
                    'artImages' => $art->artImages->map(function ($image) {
                        return [
                            'art_image_id' => $image->art_image_id,
                            'art_type' => $image->art_type,
                            'image' => url($image->image),
                        ];
                    }),
                ];
            });
        };

        $arts = Art::with([
            'artAdditionalDetails.artData',
            'artImages',
            'countries',
            'states',
            'cities',
            'category.SubCategory',
            'customer'
        ])
            ->where('art_type', 'Online')
            ->where('status', 'Approved')
            ->where('is_boost', '1')
            ->whereDate('boost_valid_upto', '>=', $today)
            ->orderBy('art_id', 'desc')
            ->get();

        $withoutBoostArt = Art::with([
            'artAdditionalDetails.artData',
            'artImages',
            'countries',
            'states',
            'cities',
            'category',
            'customer'
        ])
            ->where('art_type', 'Online')
            ->where('status', 'Approved')
            ->inRandomOrder()
            ->take(2)
            ->orderBy('art_id', 'desc')
            ->get();

        if (!$arts->isEmpty()) {
            $artIds = $arts->pluck('art_id')->toArray();
            $this->incrementApiCallCount('get_all_arts', $artIds);
        }

        if (!$withoutBoostArt->isEmpty()) {
            $withoutBoostartIds = $withoutBoostArt->pluck('art_id')->toArray();
            $this->incrementApiCallCount('get_all_arts', $withoutBoostartIds);
        }

        $artsData = $mapArtData($arts);
        $withoutBoostArtsData = $mapArtData($withoutBoostArt);

        $artsData = $arts->isEmpty() ? $withoutBoostArtsData : $artsData->merge($withoutBoostArtsData);

        return response()->json([
            'status' => true,
            'artdata' => $artsData
        ]);
    }
    public function get_all_boosted_arts()
    {
        $today = Carbon::today()->toDateString();

        $mapArtData = function ($arts) {
            return $arts->map(function ($art) {
                $total_view = ApiCallCount::where('art_id', $art->art_id)->sum('call_count');

                return [
                    'artist_unique_id' => $art->customer->customer_unique_id,
                    'art_unique_id' => $art->art_unique_id,
                    'title' => $art->title,
                    'artist_name' => $art->artist_name,
                    'art_type' => $art->art_type,
                    'category' => [
                        'category_name' => $art->category->category_name,
                        'category_icon' => url($art->category->category_icon),
                        'category_image' => $art->category->category_image,
                        'sub_text' => $art->category->sub_text,
                    ],
                    'total_view' => $total_view,
                    'edition' => $art->edition,
                    'price' => $art->price ?? $art->estimate_price_from . ' - ' . $art->estimate_price_to,
                    'since' => $art->since,
                    'pickup_address' => $art->pickup_address,
                    'pincode' => $art->pincode,
                    'country' => $art->country,
                    'state' => $art->state,
                    'city' => $art->city,
                    'frame' => $art->frame,
                    'paragraph' => $art->paragraph,
                    'status' => $art->status,
                    'country' => [
                        'country_id' => $art->countries->country_id,
                        'country_name' => $art->countries->country_name
                    ],
                    'state' => [
                        'state_id' => $art->states->state_subdivision_id,
                        'state_name' => $art->states->state_subdivision_name
                    ],
                    'city' => [
                        'city_id' => $art->cities->cities_id,
                        'city_name' => $art->cities->name_of_city
                    ],
                    'art_additional_details' => $art->artAdditionalDetails->map(function ($detail) {
                        return [
                            'art_data' => $detail->artData ? [
                                'art_data_id' => $detail->artData->art_data_id,
                                'art_data_title' => $detail->artData->art_data_title,
                            ] : null,
                            'description' => $detail->description,
                        ];
                    }),
                    'artImages' => $art->artImages->map(function ($image) {
                        return [
                            'art_image_id' => $image->art_image_id,
                            'art_type' => $image->art_type,
                            'image' => url($image->image),
                        ];
                    }),
                ];
            });
        };

        $arts = Art::with([
            'artAdditionalDetails.artData',
            'artImages',
            'countries',
            'states',
            'cities',
            'category.SubCategory',
            'customer'
        ])
            ->where('art_type', 'Online')
            ->where('status', 'Approved')
            ->where('is_boost', '1')
            ->whereDate('boost_valid_upto', '>=', $today)
            ->inRandomOrder()
            ->take(4)
            ->get();



        if (!$arts->isEmpty()) {
            $artIds = $arts->pluck('art_id')->toArray();
            $this->incrementApiCallCount('get_all_boosted_arts', $artIds);
        }


        $artsData = $mapArtData($arts);


        return response()->json([
            'status' => true,
            'artdata' => $artsData
        ]);
    }



    public function get_all_private_arts()
    {
        $today = Carbon::today()->toDateString();

        $mapArtData = function ($arts) {
            return $arts->map(function ($art) {
                $total_view = ApiCallCount::where('art_id', $art->art_id)->sum('call_count');

                return [
                    'artist_unique_id' => $art->customer->customer_unique_id,
                    'art_unique_id' => $art->art_unique_id,
                    'title' => $art->title,
                    'artist_name' => $art->artist_name,
                    'art_type' => $art->art_type,
                    'category' => [
                        'category_name' => $art->category->category_name,
                        'category_icon' => url($art->category->category_icon),
                        'category_image' => $art->category->category_image,
                        'sub_text' => $art->category->sub_text,
                        'sub_category' => $art->category->SubCategory ? [
                            'sub_category_id' => $art->category->SubCategory->sub_category_1_id,
                            'sub_category_name' => $art->category->SubCategory->sub_category_1_name,
                        ] : null,
                    ],
                    'total_view' => $total_view,
                    'edition' => $art->edition,
                    'price' => $art->price ?? $art->estimate_price_from . ' - ' . $art->estimate_price_to,
                    'since' => $art->since,
                    'pickup_address' => $art->pickup_address,
                    'pincode' => $art->pincode,
                    'country' => [
                        'country_id' => $art->countries->country_id,
                        'country_name' => $art->countries->country_name,
                    ],
                    'state' => [
                        'state_id' => $art->states->state_subdivision_id,
                        'state_name' => $art->states->state_subdivision_name,
                    ],
                    'city' => [
                        'city_id' => $art->cities->cities_id,
                        'city_name' => $art->cities->name_of_city,
                    ],
                    'frame' => $art->frame,
                    'paragraph' => $art->paragraph,
                    'status' => $art->status,
                    'art_additional_details' => $art->artAdditionalDetails->map(function ($detail) {
                        return [
                            'art_data' => $detail->artData ? [
                                'art_data_id' => $detail->artData->art_data_id,
                                'art_data_title' => $detail->artData->art_data_title,
                            ] : null,
                            'description' => $detail->description,
                        ];
                    }),
                    'art_images' => $art->artImages->map(function ($image) {
                        return [
                            'art_image_id' => $image->art_image_id,
                            'art_type' => $image->art_type,
                            'image' => url($image->image),
                        ];
                    }),
                ];
            });
        };

        $arts = Art::with([
            'artAdditionalDetails' => function ($query) {
                $query->select('art_additional_details_id', 'art_id', 'art_data_id', 'description')
                    ->with(['artData' => function ($query) {
                        $query->select('art_data_id', 'art_data_title');
                    }]);
            },
            'artImages' => function ($query) {
                $query->select('art_image_id', 'art_id', 'art_type', 'image');
            },
            'countries' => function ($query) {
                $query->select('country_id', 'country_name');
            },
            'states' => function ($query) {
                $query->select('state_subdivision_id', 'state_subdivision_name');
            },
            'cities' => function ($query) {
                $query->select('cities_id', 'name_of_city');
            },
            'category' => function ($query) {
                    $query->select('category_id', 'category_name', 'sub_text', 'category_image', 'category_icon')
                        ->with([
                            'SubCategory' => function ($query) {
                                $query->select('sub_category_1_id', 'category_id', 'sub_category_1_name');
                            }
                        ]);
                },
            'customer' => function ($query) {
                $query->select('customer_id', 'customer_unique_id', 'name', 'customer_profile', 'introduction');
            }
        ])
            ->where('art_type', 'Private')
            ->where('status', 'Approved')
            ->where('is_boost', '1')
            ->whereDate('boost_valid_upto', '>=', $today)
            ->orderBy('art_id', 'desc')
            ->get();

        $withoutBoostArt = Art::with([
            'artAdditionalDetails.artData',
            'artImages',
            'countries',
            'states',
            'cities',
            'category.SubCategory',
            'customer'
        ])
            ->where('art_type', 'Private')
            ->where('status', 'Approved')
            ->inRandomOrder()
            ->take(2)
            ->orderBy('art_id', 'desc')
            ->get();

        if (!$arts->isEmpty()) {
            $artIds = $arts->pluck('art_id')->toArray();
            $this->incrementApiCallCount('get_all_private_arts', $artIds);
        }

        if (!$withoutBoostArt->isEmpty()) {
            $withoutBoostArtIds = $withoutBoostArt->pluck('art_id')->toArray();
            $this->incrementApiCallCount('get_all_private_arts', $withoutBoostArtIds);
        }

        $artsData = $mapArtData($arts)->unique('art_unique_id')->values();
        $withoutBoostArtsData = $mapArtData($withoutBoostArt)->unique('art_unique_id')->values();

        $mergedData = $artsData->merge($withoutBoostArtsData);

        $uniqueData = $mergedData->unique('art_unique_id')->values();

        return response()->json([
            'status' => true,
            'artdata' => $uniqueData
        ]);
    }

    public function get_all_popular_arts()
    {
        $today = Carbon::today()->toDateString();

        $mapArtData = function ($arts) {
            return $arts->map(function ($art) {
                $total_view = ApiCallCount::where('art_id', $art->art_id)->sum('call_count');

                return [
                    'artist_unique_id' => $art->customer->customer_unique_id,
                    'art_unique_id' => $art->art_unique_id,
                    'title' => $art->title,
                    'artist_name' => $art->artist_name,
                    'art_type' => $art->art_type,
                    'category' => [
                        'category_name' => $art->category->category_name,
                        'category_icon' => url($art->category->category_icon),
                        'category_image' => $art->category->category_image,
                        'sub_text' => $art->category->sub_text,
                        'sub_category' => $art->category->SubCategory ? [
                            'sub_category_id' => $art->category->SubCategory->sub_category_1_id,
                            'sub_category_name' => $art->category->SubCategory->sub_category_1_name,
                        ] : null,
                    ],
                    'total_view' => $total_view,
                    'edition' => $art->edition,
                    'price' => $art->price ?? $art->estimate_price_from . ' - ' . $art->estimate_price_to,
                    'since' => $art->since,
                    'pickup_address' => $art->pickup_address,
                    'pincode' => $art->pincode,
                    'country' => $art->country,
                    'state' => $art->state,
                    'city' => $art->city,
                    'frame' => $art->frame,
                    'paragraph' => $art->paragraph,
                    'status' => $art->status,
                    'country' => [
                        'country_id' => $art->countries->country_id,
                        'country_name' => $art->countries->country_name
                    ],
                    'state' => [
                        'state_id' => $art->states->state_subdivision_id,
                        'state_name' => $art->states->state_subdivision_name
                    ],
                    'city' => [
                        'city_id' => $art->cities->cities_id,
                        'city_name' => $art->cities->name_of_city
                    ],
                    'art_additional_details' => $art->artAdditionalDetails->map(function ($detail) {
                        return [
                            'art_data' => $detail->artData ? [
                                'art_data_id' => $detail->artData->art_data_id,
                                'art_data_title' => $detail->artData->art_data_title,
                            ] : null,
                            'description' => $detail->description,
                        ];
                    }),
                    'artImages' => $art->artImages->map(function ($image) {
                        return [
                            'art_image_id' => $image->art_image_id,
                            'art_type' => $image->art_type,
                            'image' => url($image->image),
                        ];
                    }),
                ];
            });
        };

        $arts = Art::with([
            'artAdditionalDetails.artData',
            'artImages',
            'countries',
            'states',
            'cities',
           'category.SubCategory',
            'customer'
        ])
            ->where('art_type', 'Online')
            ->where('status', 'Approved')
            // ->where('is_boost', '1')
            // ->whereDate('boost_valid_upto', '>=', $today)
            ->inRandomOrder()

            ->withCount('apiCallCount as total_view')->orderBy('total_view', 'desc')
            ->get();



        if (!$arts->isEmpty()) {
            $artIds = $arts->pluck('art_id')->toArray();
            $this->incrementApiCallCount('get_all_boosted_arts', $artIds);
        }


        $artsData = $mapArtData($arts);


        return response()->json([
            'status' => true,
            'artdata' => $artsData
        ]);
    }


    // boost end





    public function get_wallet_deatils(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required|exists:customers,customer_unique_id',
            'role' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }


        $customerData = Customer::where('customer_unique_id', $request->customer_unique_id)->first();

        if (!$customerData) {
            return response()->json([
                'status' => false,
                'message' => 'No Customer Found',
            ]);
        }

        $art = OrderedArt::where('seller_id', $customerData->customer_id)->where('art_order_status', 'Delivered')->orderBy('ordered_art_id', 'desc')->get();
        if (!$art) {
            return response()->json([
                'status' => false,
                'message' => 'No Art Order Found',
            ]);
        }
        $total = 0;
        $total_deductions = 0;
        $result = [];

        $total_ammount = 0;
        foreach ($art as $value) {
            $trans = DB::table('ordered_arts')
                ->where('ordered_art_id', $value->ordered_art_id)
                ->join('art', 'ordered_arts.art_id', '=', 'art.art_id')
                ->join('art_images', 'art.art_id', '=', 'art_images.art_id')
                ->select('ordered_arts.*', 'art.title', 'art.portal_percentages', 'art_images.image', 'art.art_unique_id')
                ->first();

            // dd($trans->portal_percentages);
            if ($value->art_order_status == 'Delivered') {
                $portal_percentage = str_replace('%', '', $trans->portal_percentages);
                $portal_percentage = floatval($portal_percentage) / 100;

                $deduction = $value->price * $portal_percentage;
                $total_deductions = $deduction;
                $total += $value->price - $deduction;
                $portal_percentage = str_replace('%', '', $trans->portal_percentages);
            }

            $transaction = [
                'ordered_art_id' => $trans->ordered_art_id,
                'art_id' => $trans->art_id,
                'art_unique_id' => $trans->art_unique_id,
                'art_name' => $trans->title,
                'image' => isset($trans->image) ? url($trans->image) : null,
                'price' => $value->price,
                'art' => $trans->title,
                'art_order_status' => $trans->art_order_status,
                'portal_percentages' => $trans->portal_percentages,
                'date' => $trans->inserted_date,
                'time' => $trans->inserted_time,
                'platefarm_deduction' => $total_deductions,
                'total_after_deducted' => $value->price - $total_deductions,
            ];

            $total_ammount += $value->price - $total_deductions;

            array_push($result, $transaction);
        }


        $widthrawl = WalletRequest::where('seller_id', $customerData->customer_id)->where('status', 'Approved')->sum('amount');
        $wallet = $total - $widthrawl;
        // echo $total;die;
        $widthrawl_request = WalletRequest::where('seller_id', $customerData->customer_id)->orderBy('widthraw_request_id','desc')->get();
        $walletData = [];
        foreach ($widthrawl_request as $data) {
            $cutomer = Customer::where('customer_id', $data->seller_id)->first();
            $walletData[] = [
                'widthraw_request_id' => $data->widthraw_request_id,
                'seller_id' => $data->seller_id,
                'seller_name' => $cutomer->name,
                'seller_image' => isset($cutomer->customer_profile) ? url($cutomer->customer_profile) : null,
                'amount' => $data->amount,
                'wallet_amount' => $data->wallet_amount,
                'status' => $data->status,
                'payment_id' => $data->payment_id,
                'payment_date' => $data->payment_date,
                'inserted_date' => $data->inserted_date,
                'inserted_time' => $data->inserted_time,
            ];
        }

        $privateData = DB::table('private_ordered_art')
        ->where('seller_id', $customerData->customer_id)
        ->get();
        $privateOrders=[];
        foreach($privateData as $private){
            $art=Art::where('art_id',$private->art_id)->first();
            $artImage=ArtImage::where('art_id',$private->art_id)->first();
            $privateOrders[]=[

                'art_id' => $art->art_id,
                'art_unique_id' => $art->art_unique_id,

                'image' => isset($artImage->image) ? url($artImage->image) : null,
                'art_title' => $art->title,
                'private_ordered_art_id'=>$private->private_ordered_art_id,
                'sold_date'=>$private->sold_date,
                'artist_amount'=>$private->artist_amount,

            ];
        }
        $privateDataAmount = DB::table('private_ordered_art')
        ->where('seller_id', $customerData->customer_id)
        ->sum('artist_amount');
        return response()->json([
            'status' => true,
            'total_ammount' => ($total_ammount + $privateDataAmount) - $widthrawl,
            'widthrawl_amount' => $widthrawl,
            'income_amount' => ($total_ammount + $privateDataAmount),
            'transactions' => $result,
            'widthdraw_request' => $walletData ?? Null,
            'privateData' => $privateOrders ?? Null,
            'message' => 'Wallet Details Found'
        ]);
    }


    public function addWidthrawlRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required|exists:customers,customer_unique_id',
            'role' => 'required',
            'widthrawl_amount' => 'required|string',
            'total_amount' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }


        $customerData = Customer::where('customer_unique_id', $request->customer_unique_id)->first();

        if (!$customerData) {
            return response()->json([
                'status' => false,
                'message' => 'No Customer Found',
            ]);
        }
        if ($request->widthrawl_amount > $request->total_amount) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid Amount Entered.',
            ]);
        }

        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();
        $data = [
            'seller_id' => $customerData->customer_id,
            'amount' => $request->widthrawl_amount,
            'wallet_amount' => $request->total_amount,
            'status' => 'Pending',
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ];
        $result = WalletRequest::create($data);
        if ($result) {
            return response()->json([
                'status' => true,
                'message' => 'Request Sent Successfully',
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Request Failed',
            ]);
        }
    }


    public function getSingleTransactionDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required|exists:customers,customer_unique_id',
            'role' => 'required',
            'ordered_art_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }


        $customerData = Customer::where('customer_unique_id', $request->customer_unique_id)->first();

        if (!$customerData) {
            return response()->json([
                'status' => false,
                'message' => 'No Customer Found',
            ]);
        }

        $transaction = OrderedArt::where('ordered_art_id', $request->ordered_art_id)->first();

        $orderData  = DB::table('orders')->where('order_id', $transaction->order_id)->first();
        $data = [
            'ordered_art_id' => $transaction->ordered_art_id,
            'order_id' => $transaction->order_id,
            'order_unique_id' => $orderData->order_unique_id,
            'seller_id' => $transaction->seller_id,
            'art_id' => $transaction->art_id,
            'art_order_status' => $transaction->art_order_status,
            'price' => $transaction->price,
            'total' => $transaction->total,
            'tracking_id' => $transaction->tracking_id,
            'tracking_link' => $transaction->tracking_link,
            'tracking_status' => $transaction->tracking_status,
            'inserted_date' => $transaction->inserted_date,
            'inserted_time' => $transaction->inserted_time,
            'customer_id' => $transaction->customer_id,
        ];
        if (!$transaction) {
            return response()->json([
                'status' => false,
                'message' => 'Transaction Not Found',
            ]);
        }
        $total_deductions = 0;
        $total = 0;
        $isdelivered = false;
        $art = Art::where('art.art_id', $transaction->art_id)
            ->join('art_images', 'art_images.art_id', '=', 'art.art_id')
            ->first();
        if ($transaction->art_order_status == 'Delivered') {
            $portal_percentage = str_replace('%', '', $art->portal_percentages);
            $portal_percentage = floatval($portal_percentage) / 100;

            $deduction = $transaction->price * $portal_percentage;

            $total += $transaction->price - $deduction;
            $isdelivered = true;
        }
        // $orderdata = DB::table('order_data')->where('order_data_id', $transaction->order_id)->first();
        $delivery = DB::table('customers_delivery_address')
            ->join('countries', 'countries.country_id', '=', 'customers_delivery_address.country')
            ->join('states', 'states.state_subdivision_id', '=', 'customers_delivery_address.state')
            ->join('cities', 'cities.cities_id', '=', 'customers_delivery_address.city')
            ->where('customers_delivery_address_id', $orderData->customer_delivery_address_id)
            ->first();
        $baseUrl = url('/');
        return response()->json([
            'data' => $data,
            'delivery' => $delivery,
            'isdelivered' => $isdelivered,
            'total' => $total,
            'total_deductions' => $deduction,
            'portal_percentage' => $art->portal_percentages,
            'oderdata' => $orderData,
            'art_image' => $baseUrl . '/' . $art->image,
            'delivery_address' => $delivery,
            'status' => 'true',
            'art_data' => $art,
            'message' => 'Transaction details fetched successfully.'
        ]);
    }


    public function homecounts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required|exists:customers,customer_unique_id',
            'role' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }
        $customerData = Customer::where('customer_unique_id', $request->customer_unique_id)->first();

        if (!$customerData) {
            return response()->json([
                'status' => false,
                'message' => 'No Customer Found',
            ]);
        }
        $arts = Art::where('customer_id', $customerData->customer_id)->count();
        $orders = OrderedArt::where('seller_id', $customerData->customer_id)->count();
        $cancel_order = OrderedArt::where('seller_id', $customerData->customer_id)->where('art_order_status', 'Canceled')->count();
        $request = OrderedArt::where('seller_id', $customerData->customer_id)->where('art_order_status', 'Pending')->count();

        return response()->json([
            'status' => true,
            'message' => 'Customer Details',
            'total_art' => $arts,
            'total_order' => $orders,
            'cancel_order' => $cancel_order,
            'booking_request' => $request,
        ]);
    }

    public function add_images_in_Art(Request $request)
    {
        $uploadedImages = [];
        $existingImages = [];
        if (!$request->has('images') || empty($request->images)) {
            return response()->json([
                'status' => false,
                'message' => 'No images provided.',
            ], 400);
        }

        foreach ($request->images as $image) {
            if (!isset($image['art_image']) || !$image['art_image']->isValid()) {
                continue;
            }

            $file = $image['art_image'];

            $fileName = uniqid('art_', true) . '.' . $file->getClientOriginalExtension();
            $filePath = 'selling/image/' . $fileName;

            if (in_array($filePath, $existingImages)) {
                continue;
            }

            $file->move(public_path('selling/image'), $fileName);

            $uploadedImages[] = [
                'art_type' => $image['type'],
                'image' => $filePath,
            ];

            $existingImages[] = $filePath;
        }

        if (!empty($uploadedImages)) {
            DB::table('art_images')->insert($uploadedImages);
            return response()->json([
                'status' => true,
                'message' => 'Images uploaded successfully',
            ], 200);
        }

        return response()->json([
            'status' => false,
            'message' => 'No valid images were uploaded.',
        ], 400);
    }


    // public function add_art_web(Request $request)
    // {
    //     if (!Auth::guard('customer_api')->check()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Unauthorized access.',
    //         ]);
    //     }

    //     $validator = Validator::make($request->all(), [
    //         'customer_unique_id' => 'required|exists:customers,customer_unique_id',
    //         'title' => 'required',
    //         'artist_name' => 'required',
    //         'edition' => 'required',
    //         'since' => 'required',
    //         'price' => 'required_if:art_type,Online',
    //         'estimate_price_from' => 'required_if:art_type,Private|required_if:art_type,Exhibition',
    //         'estimate_price_to' => 'required_if:art_type,Private|required_if:art_type,Exhibition',
    //         'exhibition_id' => 'required_if:art_type,Exhibition',
    //         'pickup_address' => 'required',
    //         'sub_category_1_id'=>'required',
    //         'pincode' => 'required',
    //         'country' => 'required',
    //         'state' => 'required',
    //         'city' => 'required',
    //         'frame' => 'required',
    //         'paragraph' => 'required',
    //         'category_id' => 'required',
    //         'artAdditinalDetails' => 'required',

    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => $validator->errors()->first(),
    //         ], 400);
    //     }

    //     $customer = Auth::guard('customer_api')->user();
    //     $customer_unique_id = $request->customer_unique_id;

    //     if ($customer->customer_unique_id !== $customer_unique_id) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Customer unique ID does not match.',
    //         ], 400);
    //     }

    //     $customers = Customer::where('customer_unique_id', $customer_unique_id)->first();

    //     if (!$customers) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'No Customer Found!',
    //         ]);
    //     }

    //     $customer_id = $customers->customer_id;

    //     $existingArt = Art::where('customer_id', $customer_id)
    //         ->where('title', $request->title)
    //         ->first();

    //     if ($existingArt) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'An Art with this title already exists.',
    //         ]);
    //     }

    //     $currentDateTime = Carbon::now('Asia/Kolkata');
    //     $insertDate = $currentDateTime->toDateString();
    //     $insertTime = $currentDateTime->toTimeString();


    //     $artData = [
    //         'customer_id' => $customer_id,
    //         'art_unique_id' =>  $this->generateUniqueId($customer_id, $request->category_id),
    //         'title' => $request->title,
    //         'category_id' => $request->category_id,
    //         'sub_category_1_id' => $request->sub_category_1_id,
    //         // 'artist_name' => $customers->atist_name ?? $customer->name,
    //         'artist_name' => $request->artist_name,
    //         'exhibition_id' => $request->exhibition_id,
    //         'edition' => $request->edition,
    //         'price' => $request->price,
    //         'estimate_price_from' => $request->estimate_price_from,
    //         'estimate_price_to' => $request->estimate_price_to,
    //         'since' => $request->since,
    //         'pickup_address' => $request->pickup_address,
    //         'pincode' => $request->pincode,
    //         'country' => $request->country,
    //         'state' => $request->state,
    //         'city' => $request->city,
    //         'frame' => $request->frame,
    //         'art_type' => $request->art_type,
    //         'portal_percentages' => $request->portal_percentages,
    //         'paragraph' => $request->paragraph,
    //         'description' => $request->description,
    //         'status' => 'Pending',
    //         'inserted_date' => $insertDate,
    //         'inserted_time' => $insertTime,
    //     ];

    //     $art = Art::create($artData);
    //     $artId = $art->art_id;

    //     // dd($artId);


    //     $artAdditinalDetails = $request->artAdditinalDetails;
    //     // $artAdditinalDetails = json_decode($artAdditinalDetails, true);
    //     $insertData = [];

    //     foreach ($artAdditinalDetails as $item) {
    //         $art_data_id = $item['art_data_id'];
    //         $description = $item['description'];

    //         $existingDetail = ArtAdditionalDetails::where('art_id', $art->art_id)
    //             ->where('art_data_id', $art_data_id)
    //             ->where('description', $description)
    //             ->first();

    //         if ($existingDetail) {
    //             continue;
    //         }

    //         $additionalDetail = ArtAdditionalDetails::create([
    //             'art_id' => $art->art_id,
    //             'art_data_id' => $art_data_id,
    //             'description' => $description,
    //             'inserted_date' => $currentDateTime->toDateString(),
    //             'inserted_time' => $currentDateTime->toTimeString(),
    //         ]);

    //         $insertData[] = [
    //             'art_data_title' => $additionalDetail->artData->art_data_title,
    //             'description' => $additionalDetail->description,
    //         ];
    //     }

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Art Added Successfully',
    //         'art_unique_id' => $art->art_unique_id
    //     ]);
    // }

    public function add_art_web(Request $request)
{
    if (!Auth::guard('customer_api')->check()) {
        return response()->json([
            'status' => false,
            'message' => 'Unauthorized access.',
        ], 401);
    }

    $validator = Validator::make($request->all(), [
        'customer_unique_id' => 'required|exists:customers,customer_unique_id',
        'title' => 'required',
        'artist_name' => 'required',
        'edition' => 'required',
        'since' => 'required',
        'price' => 'required_if:art_type,Online',
        'estimate_price_from' => 'required_if:art_type,Private|required_if:art_type,Exhibition',
        'estimate_price_to' => 'required_if:art_type,Private|required_if:art_type,Exhibition',
        'exhibition_id' => 'required_if:art_type,Exhibition',
        'pickup_address' => 'required',
        'sub_category_1_id'=>'required',
        'pincode' => 'required',
        'country' => 'required',
        'state' => 'required',
        'city' => 'required',
        'frame' => 'required',
        'paragraph' => 'required',
        'category_id' => 'required',
        'artAdditinalDetails' => 'required|array',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'message' => $validator->errors()->first(),
        ], 400);
    }

    $customer = Auth::guard('customer_api')->user();
    if ($customer->customer_unique_id !== $request->customer_unique_id) {
        return response()->json([
            'status' => false,
            'message' => 'Customer unique ID does not match.',
        ], 400);
    }

    $customerData = Customer::where('customer_unique_id', $request->customer_unique_id)->first();
    if (!$customerData) {
        return response()->json([
            'status' => false,
            'message' => 'No Customer Found!',
        ]);
    }

    $customer_id = $customerData->customer_id;
    $currentDateTime = Carbon::now('Asia/Kolkata');

    $art = Art::where('art_unique_id', $request->art_unique_id)
        ->first();

    if ($art) {

        $art->update([
            'title' => $request->title,
            'category_id' => $request->category_id,
            'sub_category_1_id' => $request->sub_category_1_id,
            'artist_name' => $request->artist_name,
            'exhibition_id' => $request->exhibition_id,
            'edition' => $request->edition,
            'price' => $request->price,
            'estimate_price_from' => $request->estimate_price_from,
            'estimate_price_to' => $request->estimate_price_to,
            'since' => $request->since,
            'pickup_address' => $request->pickup_address,
            'pincode' => $request->pincode,
            'country' => $request->country,
            'state' => $request->state,
            'city' => $request->city,
            'frame' => $request->frame,
            'art_type' => $request->art_type,
            'portal_percentages' => $request->portal_percentages,
            'paragraph' => $request->paragraph,
            'description' => $request->description,
            'status' => 'Pending',
        ]);

        $message = 'Art Updated Successfully';
    } else {
              $existingArt = Art::where('customer_id', $customer_id)
            ->where('title', $request->title)
            ->first();

        if ($existingArt) {
            return response()->json([
                'status' => false,
                'message' => 'An Art with this title already exists.',
            ]);
        }

        $art = Art::create([
            'customer_id' => $customer_id,
            'art_unique_id' =>  $this->generateUniqueId($customer_id, $request->category_id),
            'title' => $request->title,
            'category_id' => $request->category_id,
            'sub_category_1_id' => $request->sub_category_1_id,
            'artist_name' => $request->artist_name,
            'exhibition_id' => $request->exhibition_id,
            'edition' => $request->edition,
            'price' => $request->price,
            'estimate_price_from' => $request->estimate_price_from,
            'estimate_price_to' => $request->estimate_price_to,
            'since' => $request->since,
            'pickup_address' => $request->pickup_address,
            'pincode' => $request->pincode,
            'country' => $request->country,
            'state' => $request->state,
            'city' => $request->city,
            'frame' => $request->frame,
            'art_type' => $request->art_type,
            'portal_percentages' => $request->portal_percentages,
            'paragraph' => $request->paragraph,
            'description' => $request->description,
            'status' => 'Pending',
            'inserted_date' => $currentDateTime->toDateString(),
            'inserted_time' => $currentDateTime->toTimeString(),
        ]);

        $message = 'Art Added Successfully';
    }

    if (!empty($request->artAdditinalDetails) && is_array($request->artAdditinalDetails)) {
        foreach ($request->artAdditinalDetails as $item) {
            // Ensure 'art_data_id' and 'description' exist in the item array
            if (!isset($item['art_data_id']) || !isset($item['description'])) {
                continue; // Skip if required keys are missing
            }

            $existingDetail = ArtAdditionalDetails::where('art_id', $art->art_id)
                // ->where('art_data_id', $item['art_data_id']) // Keep this condition to match exact data
                ->delete();

            // if ($existingDetail) {
            //     // Update existing record
            //     $existingDetail->update([
            //         'description' => $item['description'],
            //         'inserted_date' => $currentDateTime->toDateString(),
            //         'inserted_time' => $currentDateTime->toTimeString(),
            //     ]);
            // } else {
                // Insert new record
                ArtAdditionalDetails::create([
                    'art_id' => $art->art_id,
                    'art_data_id' => $item['art_data_id'],
                    'description' => $item['description'],
                    'inserted_date' => $currentDateTime->toDateString(),
                    'inserted_time' => $currentDateTime->toTimeString(),
                ]);
            // }
        }
    }

    return response()->json([
        'status' => true,
        'message' => $message,
        'art_unique_id' => $art->art_unique_id
    ]);
}

    public function get_artist_artdeatils(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required|exists:customers,customer_unique_id',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }



        $customer_unique_id = $request->customer_unique_id;

        // dd($customer_unique_id);


        $customerData = Customer::where('customer_unique_id', $request->customer_unique_id)->first();
        // dd($customerData->customer_id);
        if (!$customerData) {
            return response()->json([
                'status' => false,
                'message' => 'No Customer Found',
            ], 404);
        }


        $arts = Art::with([
            'artAdditionalDetails' => function ($query) {
                $query->select('art_additional_details_id', 'art_id', 'art_data_id', 'description')
                    ->with([
                        'artData' => function ($query) {
                            $query->select('art_data_id', 'art_data_title');
                        }
                    ]);
            },
            'artImages' => function ($query) {
                $query->select('art_image_id', 'art_id', 'art_type', 'image');
            },
        ])
            ->with([
                'countries' => function ($query) {
                    $query->select('country_id', 'country_name');
                },
                'states' => function ($query) {
                    $query->select('state_subdivision_id', 'state_subdivision_name');
                },
                'cities' => function ($query) {
                    $query->select('cities_id', 'name_of_city');
                },
                'category' => function ($query) {
                    $query->select('category_id', 'category_name', 'sub_text', 'category_image', 'category_icon');
                }
            ])
            ->where(function ($query) use ($customerData) {
                $query->where('customer_id', $customerData->customer_id) // Ensuring specific customer
                    ->where(function ($subQuery) {
                        $subQuery->where('art_type', 'Online')
                            ->orWhere('art_type', 'Private');
                    });
            })
            ->orderBy('art_id', 'desc')
            ->get();

        if ($arts->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No Art Found'
            ]);
        }

        $artsData = $arts->map(function ($art) {
            $colorCode = DB::table('status_color')
                ->where('status_name', $art->status)
                ->select('status_color_code')
                ->first();
            $today = Carbon::today()->toDateString();
            $data = Art::where('art_id', $art->art_id)->where('status', 'Approved')->where('is_boost', '1')->where('boost_valid_upto', '>=', $today)->first();
            if ($data) {
                $is_boost = true;
            } else {
                $is_boost = false;
            }
            $artist_stories = DB::table('artist_stories')->where('art_id', $art->art_id)
                ->where('customer_id', $art->customer_id)->first();
            if ($artist_stories) {
                $istroy = true;
            } else {
                $istroy = false;
            }
            return [
                'istroy' => $istroy,
                'art_unique_id' => $art->art_unique_id,
                'is_boost' => $is_boost,
                'title' => $art->title,
                'artist_name' => $art->artist_name,
                'art_type' => $art->art_type,
                'category' => [
                    'category_name' => $art->category->category_name,
                    'category_icon' => url($art->category->category_icon),
                    'category_image' => $art->category->category_image,
                    'sub_text' => $art->category->sub_text,
                ],
                'edition' => $art->edition,
                'price' => (string) ($art->price ?? ($art->estimate_price_from . ' - ' . $art->estimate_price_to)),
                // 'estimate_price'=>$art->estimate_price_from . ' - ' . $art->estimate_price_to,
                'since' => $art->since,
                'pickup_address' => $art->pickup_address,
                'pincode' => $art->pincode,
                'country' => $art->country,
                'state' => $art->state,
                'city' => $art->city,
                'frame' => $art->frame,
                'paragraph' => $art->paragraph,
                'status' => $art->status,
                'colorCode' => $colorCode->status_color_code,
                'country' => [
                    'country_id' => $art->countries->country_id,
                    'country_name' => $art->countries->country_name
                ],
                'state' => [
                    'state_id' => $art->states->state_subdivision_id,
                    'state_name' => $art->states->state_subdivision_name
                ],
                'city' => $art->cities ? [
                    'city_id' => $art->cities->cities_id,
                    'city_name' => $art->cities->name_of_city
                ] : null,
                'art_additional_details' => $art->artAdditionalDetails->map(function ($detail) {
                    return [
                        'art_data' => $detail->artData ? [
                            'art_data_id' => $detail->artData->art_data_id,
                            'art_data_title' => $detail->artData->art_data_title,
                        ] : null,
                        'description' => $detail->description,
                    ];
                }),
                'artImages' => $art->artImages->map(function ($image) {
                    return [
                        'art_image_id' => $image->art_image_id,
                        'art_type' => $image->art_type,
                        'image' => url($image->image),
                    ];
                }),
            ];
        });
        $customer = [
            'artist_unique_id' => $customerData->customer_unique_id,
            // 'artist_name' => $customerData->name,
            'artist_profile' => isset($customerData->customer_profile) ? url($customerData->customer_profile) : null,
            'introduction' => $customerData->introduction,
        ];


        return response()->json([
            'status' => true,
            'artdata' => $artsData,
            'artistData' => $customer
        ]);
    }

    public function get_single_exhibition_seller(Request $request)
    {

        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ]);
        }
        $customer = Auth::guard('customer_api')->user();


        $seller = Customer::where('customer_unique_id', $customer->customer_unique_id)->first();
        // $today = date('Y-m-d');
        $validator = Validator::make($request->all(), [
            'exhibition_unique_id' => 'required|exists:exhibitions,exhibition_unique_id',
            // 'type' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        // Retrieve the exhibition data
        $exhibition = DB::table('exhibitions')->where('exhibition_unique_id', $request->exhibition_unique_id)->first();

        if (empty($exhibition)) {
            return response()->json([
                'status' => false,
                'message' => 'No Exhibition Found'
            ]);
        }

        $today = Carbon::today()->format('Y-m-d');
        $exhibition = Exhibition::with([
            'country' => function ($query) {
                $query->select('country_id', 'country_name');
            },
            'state' => function ($query) {
                $query->select('state_subdivision_id', 'state_subdivision_name');
            },
            'city' => function ($query) {
                $query->select('cities_id', 'name_of_city');
            },
            'category' => function ($query) {
                $query->select('category_id', 'category_name');
            },
            'exhibition_art' => function ($query) {
                $query->select('exhibition_art_id', 'exhibition_id', 'art_unique_id','title', 'artist_name', 'edition', 'category_id', 'art_type', 'price', 'estimate_price_from', 'estimate_price_to', 'since', 'pickup_address', 'pincode', 'country', 'state', 'city', 'frame', 'paragraph', 'portal_percentages', 'status')
                    ->with([
                        'ExhibitionArtImage' => function ($query) {
                            $query->select('exhibition_art_image_id', 'exhibition_art_id', 'art_type', 'image');
                        },
                        'countries' => function ($query) {
                            $query->select('country_id', 'country_name');
                        },
                        'states' => function ($query) {
                            $query->select('state_subdivision_id', 'state_subdivision_name');
                        },
                        'cities' => function ($query) {
                            $query->select('cities_id', 'name_of_city');
                        }
                    ]);
            },
            'exhibition_gallery',
            'exhibition_time_slot',
            'exhibition_guests',
            'exhibition_sponsor',
        ])
            // ->where('start_date', '>=', $today)
            ->where('exhibition_unique_id', $exhibition->exhibition_unique_id)
            ->first();

        // $totalRegistrations = ExhibitionRegistration::where('exhibition_id', $exhibition->exhibition_id)->count();
        $todayCarbon = Carbon::today();
        $todayString = $todayCarbon->toDateString();
        $exhibition->isRegister = $exhibition->art_submit_last_date >= $todayString ? true : false;
        $totalRegistrations = DB::table('artist_exhibition_registration')
            ->where('exhibition_id', $exhibition->exhibition_id)
            ->count();
        $existingArtworksCount = Art::where('customer_id', $seller->customer_id)
            ->where('exhibition_id', $exhibition->exhibition_id)
            ->count();

        $isAdd = true;
        if ($existingArtworksCount >= 3) {

            $isAdd = false;
        }
        $isFull = false;

        if ($totalRegistrations >= $exhibition->artist_count) {
            $isFull = true;
        }

        $exhibition->isFull = $isFull;
        $exhibition->isAdd = $isAdd;


        $exhibition_booths = DB::table('exhibition_booths')->where('exhibition_id', $exhibition->exhibition_id)->first();

        // $exhibitionArt = Art::where('exhibition_id', $exhibition->exhibition_id)
        //     ->where('customer_id', $seller->customer_id)
        //     ->first();

        //     // dd($exhibitionArt);
        // if ($exhibitionArt && $exhibitionArt->status == 'Approved') {
        //     $exhibition->isArtApproved = 1;
        // } else {
        //     $exhibition->isArtApproved = 0;
        // }

        $exhibitionArtApproved = Art::where('exhibition_id', $exhibition->exhibition_id)
    ->where('customer_id', $seller->customer_id)
    ->where('status', 'Approved') // Check if any artwork is approved
    ->exists(); // Returns true if at least one exists

$exhibition->isArtApproved = $exhibitionArtApproved ? 1 : 0;

        $exhibition->is_booth = $exhibition_booths ? 1 : 0;
        $exhibition->logo = isset($exhibition->logo) ? url($exhibition->logo) : null;
        $exhibition->images = ExhibitionGallery::where('exhibition_id', $exhibition->exhibition_id)
            ->get()
            ->map(function ($image) {
                $image->link = isset($image->link) ? url($image->link) : null;
                return $image;
            });



        $termData = DB::table('exhibition_paras')
            ->where('exhibition_id', $exhibition->exhibition_id)
            ->get();


        $exhibition->termData = $termData;



        if (!$exhibition) {
            return response()->json([
                'status' => false,
                'message' => 'No Exhibition Found'
            ]);
        }

        if ($exhibition->logo) {
            $exhibition->logo = url($exhibition->logo) ?? null;
        }
        if ($exhibition->banner) {
            $exhibition->banner = isset($exhibition->banner) ? url($exhibition->banner) : null;
        }

        if ($exhibition->exhibition_gallery) {
            $exhibition->exhibition_gallery->each(function ($gallery) {
                if ($gallery->link) {
                    $gallery->link = url($gallery->link);
                }
            });
        } else {
            $exhibition->exhibition_gallery = null;
        }

        if ($exhibition->exhibition_guests) {
            $exhibition->exhibition_guests->each(function ($guest) {
                if ($guest->photo) {
                    $guest->photo = url($guest->photo);
                }
            });
        }

        if ($exhibition->exhibition_sponsor) {
            $exhibition->exhibition_sponsor->each(function ($sponsor) {
                if ($sponsor->logo) {
                    $sponsor->logo = url($sponsor->logo);
                }
            });
        }

        if ($exhibition->exhibition_art->isNotEmpty()) {
            $exhibition->exhibition_art->each(function ($exhibitionArt) {
                // Fetch artist details
                // $exhibitionArtist = Customer::where('customer_id', $exhibitionArt->customer_id)->first();
                // $exhibitionArt->artist_unique_id = $exhibitionArtist ? $exhibitionArtist->customer_unique_id : null;

                // Format price correctly
                $exhibitionArt->price = $exhibitionArt->price ?? "{$exhibitionArt->estimate_price_from} - {$exhibitionArt->estimate_price_to}";

                // Update image URLs
                if ($exhibitionArt->ExhibitionArtImage->isNotEmpty()) {
                    $exhibitionArt->ExhibitionArtImage->each(function ($artImage) {
                        $artImage->image = url($artImage->image);
                    });
                }
            });
        }
        // if ($exhibition->exhibition_art) {
        //     $exhibition->exhibition_art->each(function ($exhibitionArt) {
        //         $exhibitionArt->art->price = $exhibitionArt->art->price ?? $exhibitionArt->art->estimate_price_from . ' - ' . $exhibitionArt->art->estimate_price_to;
        //         if ($exhibitionArt->art->artImages) {
        //             $exhibitionArt->art->artImages->each(function ($artImage) {
        //                 if ($artImage->image) {
        //                     $artImage->image = url($artImage->image);
        //                 }
        //             });
        //         }
        //     });
        // }
        return response()->json([
            'status' => true,
            'exhibition' => $exhibition,

        ]);
    }


    public function addArtImageS3(Request $request)
    {
        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ]);
        }
        $validator = Validator::make($request->all(), [
            // 'art_unique_id' => 'required|exists:art,art_unique_id',
            'art_type' => 'required',
            'image' => 'required|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }
        // $art_unique_id = $request->art_unique_id;

        // $art = Art::where('art_unique_id', $art_unique_id)->first();

        // if (!$art) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'No Art Found',
        //     ]);
        // }

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $folder = trim('artistsapnabucket/selling/image/', '/'); // Remove leading/trailing slashes
            $fileName = Str::random(10) . '_' . $file->getClientOriginalName();
            $filePath = "$folder/$fileName";

            // Store file in S3
            $storedPath = Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');

            // Generate public URL
            $fileUrl = Storage::disk('s3')->url($filePath);

            $currentDateTime = Carbon::now('Asia/Kolkata');
            $image = ArtImage::create([
                // 'art_id' => $art->art_id,
                'art_type' => $request->art_type,
                'image' => $fileUrl,
                'inserted_date' => $currentDateTime->toDateString(),
                'inserted_time' => $currentDateTime->toTimeString(),
            ]);



            return response()->json([
                'status' => true,
                'message' => 'Image added successfully.',
                'image_path' => $fileUrl,
                'art_image_id' => $image->id
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'No image file provided.',
            ], 400);
        }
    }
    public function uploadFile(Request $request)
    {
        $request->validate([
            'file' => 'required|file',
            'folder' => 'required|string', // Folder location inside S3 bucket
        ]);

        $file = $request->file('file');
        $folder = trim($request->folder, '/'); // Remove leading/trailing slashes
        $fileName = Str::random(10) . '_' . $file->getClientOriginalName();
        $filePath = "$folder/$fileName";

        // Store file in S3 with public visibility
        $storedPath = Storage::disk('s3')->put($filePath, fopen($file, 'r+'), 'public');

        // Generate public URL
        $fileUrl = Storage::disk('s3')->url($filePath);

        return response()->json([
            'message' => 'File uploaded successfully',
            'file_url' => $fileUrl,
        ]);
    }

     public function admin_get_all_exhibition(Request $request)
    {
        $today = Carbon::today()->format('Y-m-d');
        $validator = Validator::make($request->all(), [
            'min_date' => 'nullable|date',
            'max_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $threeMonthsAgo = Carbon::now()->subMonths(3);


        $exhibitions = Exhibition::with([
            'country' => function ($query) {
                $query->select('country_id', 'country_name');
            },
            'state' => function ($query) {
                $query->select('state_subdivision_id', 'state_subdivision_name');
            },
            'city' => function ($query) {
                $query->select('cities_id', 'name_of_city');
            },

        ]);
        // ->where('start_date', '<=', $today)
        if ($request->min_date) {
            $exhibitions = $exhibitions->where('exhibitions.start_date', '>=', $request->min_date);
        }

        if ($request->max_date) {
            $exhibitions = $exhibitions->where('exhibitions.start_date', '<=', $request->max_date);
        }

        else{
            $exhibitions =  $exhibitions->where('exhibitions.start_date', '>=', $threeMonthsAgo);
        }

        $exhibitions = $exhibitions


        ->get();

        // $exhibitions->each(function ($exhibition) {
        //     $exhibition->logo = url($exhibition->logo);
        //     $exhibition->banner = url($exhibition->banner);
        // });


        foreach ($exhibitions as $exhibition) {

            // $exhibition->isRegister = $exhibition->art_submit_last_date >= $todayString ? true : false;
            $totalRegistrations = DB::table('artist_exhibition_registration')
                ->where('exhibition_id', $exhibition->exhibition_id)
                ->count();

            // $existingArtworksCount = Art::where('customer_id', $seller->customer_id)
            //     ->where('exhibition_id', $exhibition->exhibition_id)
            //     ->count();

            // $isAdd = true;
            // if ($existingArtworksCount >= 3) {

            //     $isAdd = false;
            // }

            // $isFull = false;

            // if ($totalRegistrations >= $exhibition->artist_count) {
            //     $isFull = true;
            // }

            // $exhibition->isFull = $isFull;
            // $exhibition->isAdd = $isAdd;

            $exhibition_booths = DB::table('exhibition_booths')->where('exhibition_id', $exhibition->exhibition_id)->first();

            // $exhibitionArt = Art::where('exhibition_id', $exhibition->exhibition_id)
            //     ->where('customer_id', $seller->customer_id)
            //     ->first();

            // // dd($exhibitionArt);
            // if ($exhibitionArt && $exhibitionArt->status == 'Approved') {
            //     $exhibition->isArtApproved = 1;
            // } else {
            //     $exhibition->isArtApproved = 0;
            // }

            $exhibition->is_booth = $exhibition_booths ? 1 : 0;
            $exhibition->logo = isset($exhibition->logo) ? url($exhibition->logo) : null;
            $exhibition->images = ExhibitionGallery::where('exhibition_id', $exhibition->exhibition_id)
                ->get()
                ->map(function ($image) {
                    $image->link = isset($image->link) ? url($image->link) : null;
                    return $image;
                });
        }

        return response()->json([
            'status' => true,
            'exhibition' => $exhibitions

        ]);
    }

    public function get_single_exhibition_admin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'exhibition_unique_id' => 'required|exists:exhibitions,exhibition_unique_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $exhibition = DB::table('exhibitions')->where('exhibition_unique_id', $request->exhibition_unique_id)->first();

        if (empty($exhibition)) {
            return response()->json([
                'status' => false,
                'message' => 'No Exhibition Found'
            ]);
        }

        $today = Carbon::today()->format('Y-m-d');
        $exhibitions = Exhibition::with([
            'country' => function ($query) {
                $query->select('country_id', 'country_name');
            },
            'state' => function ($query) {
                $query->select('state_subdivision_id', 'state_subdivision_name');
            },
            'city' => function ($query) {
                $query->select('cities_id', 'name_of_city');
            },
            'category' => function ($query) {
                $query->select('category_id', 'category_name');
            },
            'exhibition_art' => function ($query) {
                $query->select('exhibition_art_id', 'exhibition_id', 'art_unique_id','title', 'artist_name', 'edition', 'category_id', 'art_type', 'price', 'estimate_price_from', 'estimate_price_to', 'since', 'pickup_address', 'pincode', 'country', 'state', 'city', 'frame', 'paragraph', 'portal_percentages', 'status','bid_start_to','bid_start_from')
                    ->with([
                        'ExhibitionArtImage' => function ($query) {
                            $query->select('exhibition_art_image_id', 'exhibition_art_id', 'art_type', 'image');
                        },
                        'countries' => function ($query) {
                            $query->select('country_id', 'country_name');
                        },
                        'states' => function ($query) {
                            $query->select('state_subdivision_id', 'state_subdivision_name');
                        },
                        'cities' => function ($query) {
                            $query->select('cities_id', 'name_of_city');
                        }
                    ]);
            },
            'exhibition_gallery',
            'exhibition_time_slot' => function ($query) {
                $query->orderBy('date', 'asc')
                    ->orderBy('slot_name', 'asc');
            },
            'exhibition_guests',
            'exhibition_sponsor',
            'exhibition_paras',
            'exhibition_term',
        ])
            ->where('exhibition_unique_id', $exhibition->exhibition_unique_id)
            ->first();




        if (!$exhibitions) {
            return response()->json([
                'status' => false,
                'message' => 'No Exhibition Found'
            ]);
        }


        if ($exhibitions->logo) {
            $exhibitions->logo = isset($exhibitions->logo) ? url($exhibitions->logo) : null;
        }

        if ($exhibitions->banner) {
            $exhibitions->banner = isset($exhibitions->banner) ? url($exhibitions->banner) : null;
        }


        if ($exhibitions->exhibition_gallery) {
            $exhibitions->exhibition_gallery->each(function ($gallery) {
                if ($gallery->link) {
                    $gallery->link = url($gallery->link);
                }
            });
        } else {
            $exhibitions->exhibition_gallery = null;
        }

        if ($exhibitions->exhibition_guests) {
            $exhibitions->exhibition_guests->each(function ($guest) {
                if ($guest->photo) {
                    $guest->photo = url($guest->photo);
                }
            });
        }

        if ($exhibitions->exhibition_sponsor) {
            $exhibitions->exhibition_sponsor->each(function ($sponsor) {
                if ($sponsor->logo) {
                    $sponsor->logo = url($sponsor->logo);
                }
            });
        }

        if ($exhibitions->exhibition_art->isNotEmpty()) {
            $exhibitions->exhibition_art->each(function ($exhibitionArt) {
                // Fetch artist details
                // $exhibitionArtist = Customer::where('customer_id', $exhibitionArt->customer_id)->first();
                // $exhibitionArt->artist_unique_id = $exhibitionArtist ? $exhibitionArtist->customer_unique_id : null;

                // Format price correctly
                $exhibitionArt->price = $exhibitionArt->price ?? "{$exhibitionArt->estimate_price_from} - {$exhibitionArt->estimate_price_to}";

                // Update image URLs
                if ($exhibitionArt->ExhibitionArtImage->isNotEmpty()) {
                    $exhibitionArt->ExhibitionArtImage->each(function ($artImage) {
                        $artImage->image = url($artImage->image);
                    });
                }
            });
        }


        $booths = ExhibitionBooth::where('exhibition_id', $exhibition->exhibition_id)
        ->with(['boothSeats'])
        ->get();

        foreach ($booths as &$booth) { // Use reference to modify array directly
            $booth['art_commision'] = $exhibitions->art_commision;
        }
        $exhibitions->booths = $booths;
        // $exhibitions->booths['art_commision'] = $exhibitions->art_commision;




        return response()->json([
            'status' => true,
            'exhibition' => $exhibitions,

        ]);
    }

    public function get_portal_percentage_admin(Request $request)
    {


        $percentages = DB::table('portal_percentages')
            ->get();


        if ($percentages->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No portal percentage data found.',
            ]);
        }
        $data = [];
        foreach ($percentages as $percentage) {
            $data[] = [
                'portal_percentages_id' => $percentage->portal_percentages_id,
                'percentage' => $percentage->percentage ,
                'role' => $percentage->role,
                'user_id' => $percentage->user_id
            ];
        }
        return response()->json([
            'status' => true,
            'data' => $data,
        ]);
    }

    public function update_exhibition_art(Request $request)
    {


        $validator = Validator::make($request->all(), [

            'art_unique_id' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $art_unique_id = $request->art_unique_id;

        $art = DB::table('exhibition_art')->where('art_unique_id', $art_unique_id)
            ->first();
        $exhibition=DB::table('exhibitions')->where('exhibition_id',$art->exhibition_id)->first();
        if (!$art) {
            return response()->json([
                'status' => false,
                'message' => 'Art not found or you are not authorized to update this art.',
            ]);
        }


        $currentDateTime = Carbon::now('Asia/Kolkata');


        $artData = [
            'title' => $request->title ?? $art->title,
            'artist_name' => $request->artist_name ?? $art->artist_name,
            'category_id' => $request->category_id ?? $art->category_id,
            'sub_category_1_id' => $request->sub_category_1_id ?? $art->sub_category_1_id,
            'edition' => $request->edition ?? $art->edition,
            'art_type' => $request->art_type ?? $art->edition,
            'exhibition_id' => $request->exhibition_id ?? $art->exhibition_id,
            'price' => $request->price ?? $art->price,
            'estimate_price_from' => $request->estimate_price_from ?? $art->estimate_price_from,
            'estimate_price_to' => $request->estimate_price_to ?? $art->estimate_price_to,
            'bid_start_from' => $request->bid_start_from ?? $art->bid_start_from,
            'bid_start_to' => $request->bid_start_to ?? $art->bid_start_to,
            'since' => $request->since ?? $art->since,
            'pickup_address' => $request->pickup_address ?? $art->pickup_address,
            'pincode' => $request->pincode ?? $art->pincode,
            'country' => $request->country ?? $art->country,
            'state' => $request->state ?? $art->state,
            'city' => $request->city ?? $art->city,
            'frame' => $request->frame ?? $art->frame,
            'paragraph' => $request->paragraph ?? $art->paragraph,
            'inserted_date' => $currentDateTime->toDateString(),
            'inserted_time' => $currentDateTime->toTimeString(),
        ];

        DB::table('exhibition_art')->where('art_unique_id', $art_unique_id)
        ->update($artData);

        return response()->json([
            'status' => true,
            'message' => 'Art updated successfully',
            'art_unique_id' => $art->art_unique_id,
            'exhibition_unique_id' => $exhibition->exhibition_unique_id
        ]);
    }





}
