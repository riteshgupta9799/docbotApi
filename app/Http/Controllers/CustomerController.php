<?php

namespace App\Http\Controllers;

use App\Models\About;
use App\Models\AddressDetail;
use App\Models\Art;
use App\Models\ArtCart;
use App\Models\ArtData;
use App\Models\ArtImage;
use App\Models\ArtistArtStories;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerDeliveryAddress;
use App\Models\Wishlist;
use App\Models\BankDetail;
use App\Models\User;
use App\Models\Ads;
use App\Models\Country;
use App\Models\State;
use App\Models\City;
use App\Models\Wishlists;
use App\Models\ExhibitionArt;
use App\Models\ExhibitionArtImage;
use App\Models\ReturnOrder;
use App\Models\ReturnOrderImage;
use App\Models\ArtEnquiryChat;
use App\Models\MiramonetChat;
use App\Models\MiramonetChatMessage;
use App\Models\ArtEnquiryChatMessage;
use App\Models\PrivateEnquiryChat;
use App\Models\PrivateEnquiryChatMessage;
use App\Models\AdminPrivateEnquiryChatMessage;
use App\Models\HelpCenterChat;
use App\Models\HelpCenterChatMessage;
use App\Models\HelpCenterChatImages;
use App\Models\ReturnOrderMessage;
use App\Models\EnquiryCategory;
use App\Models\Exhibition;
use Auth;
use Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\Models\ArtEnquiry;
use App\Models\PrivateSaleEnquiry;
use Illuminate\Support\Facades\DB;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
// use Log;
class CustomerController extends Controller
{

    public function get_category()
    {
        $category = Category::select('category_id', 'category_name', 'category_image', 'category_icon', 'sub_text')->where('status', 'Active')->get();
        $data = [];
        foreach ($category as $cat) {
            $data[] = [
                'category_id' => $cat->category_id,
                'category_name' => $cat->category_name,
                'category_image' => isset($cat->category_image) ? url($cat->category_image) : null,
                'category_icon' => $cat->category_icon ? url($cat->category_icon) : null,
                'sub_text' => $cat->sub_text
            ];
        }
        if (empty($data)) {
            return response()->json([
                'status' => false,
                'message' => 'No Category data found!'
            ]);
        } else {
            return response()->json([
                'status' => true,
                'category' => $data
            ]);
        }
    }

    // public function about()
    // {

    //     $results = About::where('status', 'Active')->get();


    //     $aboutArray = [];


    //     foreach ($results as $row) {
    //         $aboutArray[] = [
    //             'about_id' => $row->about_id,
    //             'title' => $row->title,
    //             'heading' => $row->heading,
    //             'sub_heading' => $row->sub_heading,
    //             'about_image' => $row->about_image,
    //             'para' => $row->para
    //         ];
    //     }


    //     $response = !empty($aboutArray) ? [
    //         'status' => true,
    //         'message' => 'About Content Found ...',
    //         'about_array' => $aboutArray
    //     ] : [
    //         'status' => false,
    //         'message' => 'About Content Not Found',
    //     ];


    //     return response()->json($response);
    // }

    public function getBanners()
    {

        $banners = Banner::where('status', 'Active')
            ->orderBy('banners_id', 'DESC')
            ->get();

        $banners_array = [];


        foreach ($banners as $row) {
            $banner_data = [
                'banners_id' => $row->banners_id,
                'banner_image' => $row->banner_image,
                'heading' => $row->heading,

                'sub_heading' => $row->sub_heading,
                'banner_title' => $row->banner_title
            ];
            $banners_array[] = $banner_data;
        }


        if (empty($banners_array)) {
            return response()->json([
                'status' => false,
                'message' => 'No Data Found!',
            ]);
        }


        return response()->json([
            'status' => true,
            'message' => 'Banners Found Successfully',
            'banners' => $banners_array,
        ]);
    }

    public function get_art()
    {

        $art = Art::where('status', 'Active')
            ->with(['artImages', 'artDetail'])
            ->get();


        if ($art->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No Art Found!',
            ]);
        } else {
            return response()->json([
                'status' => true,
                'art' => $art,
            ]);
        }
    }


    public function add_private_sale_enquiry(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'mobile' => 'required|string|max:15',
            'message' => 'required|string',
            'art_unique_id' => 'required|string',
            'customer_unique_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'false',
                'message' => $validator->errors()->first(),
            ]);
        }

        $customer = Customer::where('customer_unique_id', $request->input('customer_unique_id'))->first();
        if (!$customer) {
            return response()->json(['status' => 'false', 'message' => 'Customer not found.']);
        }
        $art = Art::where('art_unique_id', $request->input('art_unique_id'))->first();
        if (!$art) {
            return response()->json(['status' => 'false', 'message' => 'Art not found.']);
        }

        $existingEnquiry = PrivateSaleEnquiry::where('customer_id', $customer->customer_id)
            ->where('art_id', $art->art_id)
            ->exists();

        $type = $existingEnquiry ? 'reply' : 'first';

        $currentDateTime = Carbon::now();
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        $data = [
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'mobile' => $request->input('mobile'),
            'message' => $request->input('message'),
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
            'art_id' => $art->art_id,
            'customer_id' => $customer->customer_id,
            'type' => $type,
            'role' => 'customer'
        ];

        PrivateSaleEnquiry::create($data);

        return response()->json([
            'status' => 'true',
            'message' => 'Private Sale Enquiry Raised Successfully!',
        ]);
    }

    public function add_art_enquiry(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'mobile' => 'required|string|max:15',
            'message' => 'required|string',
            'art_unique_id' => 'required|string',
            'customer_unique_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'false',
                'message' => $validator->errors()->first(),
            ]);
        }

        $customer = Customer::where('customer_unique_id', $request->input('customer_unique_id'))->first();
        if (!$customer) {
            return response()->json(['status' => 'false', 'message' => 'Customer not found.']);
        }
        $art = Art::where('art_unique_id', $request->input('art_unique_id'))->first();
        if (!$art) {
            return response()->json(['status' => 'false', 'message' => 'Art not found.']);
        }

        $existingEnquiry = ArtEnquiry::where('customer_id', $customer->customer_id)
            ->where('art_id', $art->art_id)
            ->exists();

        $type = $existingEnquiry ? 'reply' : 'first';

        $currentDateTime = Carbon::now();
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        $data = [
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'mobile' => $request->input('mobile'),
            'message' => $request->input('message'),
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
            'art_id' => $art->art_id,
            'customer_id' => $customer->customer_id,
            'type' => $type,
            'role' => 'customer'
        ];

        ArtEnquiry::create($data);

        return response()->json([
            'status' => 'true',
            'message' => 'Art Enquiry Raised Successfully!',
        ]);
    }

    public function customer_data(Request $request)
    {
        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            "customer_unique_id" => 'required'
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

        if ($customer_unique_id) {

            $customer = Customer::where('customer_unique_id', $customer_unique_id)->first();
            $customers = Customer::where('customer_id', $customer->customer_id)
                ->select('customer_unique_id', 'customer_profile', 'name', 'role', 'email', 'mobile', 'country', 'state', 'city', 'address', 'zip_code', 'introduction', 'artist_name')
                // ->withCount(['wishlist', 'cart'])
                ->with([
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
                ->orderBy('customer_id', 'desc')
                ->first();

            if (!empty($customers)) {
                // Check if the customer profile image exists and append the full URL
                if ($customers->customer_profile) {
                    $customers->customer_profile = asset($customers->customer_profile);
                }

                $isMiramonet= DB::table('miramonet_chat')
                        ->where('customer_id',$customer->customer_id)
                        ->exists();
                $Miramonet= DB::table('miramonet_chat')
                        ->where('customer_id',$customer->customer_id)
                        ->first();
                $isUpdate = false;

                if ($customers->role == 'seller') {
                    $today = Carbon::today()->toDateString();
                    $updatedDate = Carbon::parse($customers->updated_date)->toDateString();
                    // dd($today);
                    if ($updatedDate == $today) {
                        $isUpdate = true;
                    }
                }
                $wishlist_count=DB::table('wishlist')->where('customer_id',$customer->customer_id)->where('status','Active')->count();
                $cart_count=DB::table('art_cart')->where('customer_id',$customer->customer_id)->where('status','Active')->count();
                $customers->isUpdate = $isUpdate;
                $customers->wishlist_count = $wishlist_count;
                $customers->cart_count = $cart_count;
                $customers->isMiramonet = $isMiramonet;
                $customers->miramonet_chat_id = $Miramonet->miramonet_chat_id??null;

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


    public function get_artist()
    {
        $artist = Customer::where('status', 'Active')->where('role', 'seller')
            ->select('customer_unique_id', 'customer_profile','artist_name', 'name', 'email', 'mobile', 'country', 'state', 'city', 'address')
            ->get();
        if ($artist->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No Artist Found!'
            ]);
        } else {
            $data =[];
            foreach ($artist as $value) {
                $data[]=[
                    'customer_unique_id'=>$value->customer_unique_id,
                    'customer_profile'=>isset($value->customer_profile) ? url($value->customer_profile) : null,
                    'name'=>$value->artist_name??$value->name,
                    'email'=>$value->email,
                    'mobile'=>$value->mobile,
                    'country'=>$value->country,
                    'state'=>$value->state,
                    'city'=>$value->city,
                    'address'=>$value->address,
                ];
                // $value->customer_profile = isset($value->customer_profile) ? url($value->customer_profile) : null;
            }

            return response()->json([
                'status' => true,
                'artist' => $data
            ]);
        }
    }

    public function edit_customer_profile(Request $request)
    {

        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 401);
        }
        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required',
            // 'customer_profile' => 'required',
            // 'name' => 'required',
            // 'city' => 'required',
            // 'address' => 'required',
            // 'country' => 'required',
            // 'state' => 'required',
            // 'zip_code' => 'required',
            // 'longitude' => 'required',
            // 'latitude' => 'required',
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
                'status' => true,
                'message' => 'No Customer Found'
            ]);
        }
        if ($request->hasFile('customer_profile') && $request->file('customer_profile')->isValid()) {
            $file = $request->file('customer_profile');

            $fileName = time() . '.' . $file->getClientOriginalExtension();

            $file->move(public_path('customer_profile'), $fileName);

            $filePath = 'customer_profile/' . $fileName;
        } else {
            $filePath = $customers->customer_profile;
        }


        $customerId = $customers->customer_id;

        $updatedRows = Customer::where('customer_id', $customerId)
            ->update([
                'customer_profile' => $filePath ?? $customers->customer_profile,
                'name' => $request->name ?? $customers->name,
                'address' => $request->address ?? $customers->address,
                'country' => $request->country ?? $customers->country,
                'state' => $request->state ?? $customers->state,
                'city' => $request->city ?? $customers->city,
                'zip_code' => $request->zip_code ?? $customers->zip_code,
                'introduction' => $request->introduction ?? $customers->introduction,
                'longitude' => $request->longitude,
                'latitude' => $request->latitude,
            ]);

        $data = [
            'customer_profile' => url($filePath),
            'name' => $request->name ?? $customers->name,
            'address' => $request->address ?? $customers->address,
            'country' => $request->country ?? $customers->country,
            'state' => $request->state ?? $customers->state,
            'city' => $request->city ?? $customers->city,
            'zip_code' => $request->zip_code ?? $customers->zip_code,
            'introduction' => $request->introduction ?? $customers->introduction,
            'longitude' => $request->longitude,
            'latitude' => $request->latitude,
        ];



        if ($updatedRows > 0) {
            return response()->json([
                'status' => true,
                'message' => 'Customer Profile updated successfully!',
                'data' => $data
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'No records updated.'
            ]);
        }
    }

    public function get_art_data(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $category = Category::where('category_id', $request->category_id)->first();
        if (!$category) {
            return response()->json([
                'status' => false,
                'message' => 'No Data Found'
            ]);
        }

        $artData = ArtData::where('category_id', $request->category_id)->select('art_data_id', 'art_data_title', 'required','placeholder')->where('status', 'Active')->get();
        if ($artData->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No Art data found!'
            ]);
        } else {
            return response()->json([
                'status' => true,
                'artData' => $artData
            ]);
        }
    }

    public function add_category_icon(Request $request)
    {
        if ($request->hasFile('category_icon') && $request->file('category_icon')->isValid()) {
            $file = $request->file('category_icon');

            $fileName = time() . '.' . $file->getClientOriginalExtension();

            $file->move(public_path('category_icon'), $fileName);

            $filePath = 'category_icon/' . $fileName;
        } else {
            $filePath = $request->category_icon;
        }

        Category::where('category_id', $request->category_id)->update([
            'category_icon' => $filePath,
        ]);
    }

    // public function get_artist_art_story()
    // {


    //     $data = ArtistArtStories::with([
    //         'art' => function ($query) {
    //             $query->select('art_id', 'art_unique_id', 'customer_id', 'title', 'artist_name', 'edition', 'category_id', 'art_type', 'price', 'estimate_price_from', 'estimate_price_to', 'since', 'pickup_address', 'pincode', 'country', 'state', 'city', 'frame', 'paragraph', 'portal_percentages', 'buy_date')
    //                 ->with([
    //                     'artAdditionalDetails' => function ($query) {
    //                         $query->select('art_additional_details_id', 'art_id', 'art_data_id', 'description')
    //                             ->with([
    //                                 'artData' => function ($query) {
    //                                     $query->select('art_data_id', 'art_data_title');
    //                                 }
    //                             ]);
    //                     },
    //                     'artImages' => function ($query) {
    //                         $query->select('art_image_id', 'art_id', 'art_type', 'image');
    //                     }
    //                 ]);
    //         },
    //         'customer' => function ($query) {
    //             $query->select('customer_unique_id', 'customer_id', 'customer_profile', 'name', 'role', 'email', 'mobile');
    //         }
    //     ])->get();

    //     if ($data->isEmpty()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'No Stories Found'

    //         ]);
    //     }
    //     // $data->each(function ($project) {

    //     //     $project = $project->first();

    //     //     $project->customer->customer_profile = url($project->customer->customer_profile);

    //     //     $project->art->artImages->each(function ($images) {
    //     //         $images->image = url($images->image);
    //     //     });
    //     // });

    //     $news = [];

    //     foreach ($data as $value) {
    //         $new = [];
    //         $para = ArtistArtStories::where('art_id', $value->art_id)->get();
    //         $image = ArtImage::where('art_id', $value->art_id)->get();
    //         $customer = Customer::where('customer_id', $value->customer_id)->select('customer_unique_id', 'customer_profile', 'name', 'role', 'introduction', 'country', 'state', 'city', 'address', 'zip_code', 'longitude', 'latitude')->first();

    //         $customer->customer_profile = isset($customer->customer_profile) ? url($customer->customer_profile) : null;
    //         $art = Art::where('art_id', $value->art_id)->first();
    //         $art->price =  $art->price ?? ($art->estimate_price_from . ' - ' . $art->estimate_price_to);
    //         $imageUrls = $image->map(function ($img) {
    //             $img->image = url($img->image); // Generate the full URL for the image
    //             return $img;
    //         });
    //         $new['customer'] = $customer ?? null;
    //         $new['art'] = $art ?? null;
    //         $new['paragraph'] = $para ?? null;
    //         $new['image'] = $image ?? null;

    //         $news[] = $new;
    //     }








    //     if ($data->isEmpty()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'No Stories Found'
    //         ]);
    //     }


    //     return response()->json([
    //         'status' => true,
    //         'stories' => $news
    //     ]);
    // }

    public function get_artist_art_story()
    {
        $data = ArtistArtStories::with([
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
                        }
                    ]);
            },
            'customer' => function ($query) {
                $query->select('customer_unique_id', 'customer_id', 'customer_profile', 'name', 'role', 'email', 'mobile');
            }
        ])
        ->where('status','Active')
        ->orderBy('artist_stories_id','desc')
        ->get();

        if ($data->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No Stories Found'
            ]);
        }

        $news = [];

        foreach ($data as $value) {
            $new = [];
            $para = ArtistArtStories::where('art_id', $value->art_id)->get();
            $image = ArtImage::where('art_id', $value->art_id)->get();
            $customer = Customer::where('customer_id', $value->customer_id)
                ->select('customer_unique_id', 'customer_profile', 'name', 'role', 'introduction', 'country', 'state', 'city', 'address', 'zip_code', 'longitude', 'latitude')
                ->first();

            $customer->customer_profile = isset($customer->customer_profile) ? url($customer->customer_profile) : null;

            $art = Art::where('art_id', $value->art_id)->first();

            if ($art) {
                $art->price = $art->price ?? ($art->estimate_price_from . ' - ' . $art->estimate_price_to);
            }

            $imageUrls = $image->map(function ($img) {
                $img->image = url($img->image); // Generate the full URL for the image
                return $img;
            });

            $new['customer'] = $customer ?? null;
            $new['art'] = $art ?? null;
            $new['paragraph'] = $para ?? null;
            $new['image'] = $image ?? null;

            $news[] = $new;
        }

        return response()->json([
            'status' => true,
            'stories' => $news
        ]);
    }


    public function get_single_artist(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }
        $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();

        $customer_id = $customer->customer_id;

        $CustomerData = Customer::where('customer_id', $customer_id)->first();

        $customerDetail = [
            'name' => $CustomerData->name,
            'customer_profile' => url($CustomerData->customer_profile),
            'description' => $CustomerData->description,
        ];

        $arts = Art::with([
            'artAdditionalDetails' => function ($query) {
                $query->select('art_additional_details_id', 'art_id', 'art_data_id', 'description', 'inserted_date', 'inserted_time')
                    ->with([
                        'artData' => function ($query) {
                            $query->select('art_data_id', 'art_data_title');
                        }
                    ]);
            },
        ])->with([
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
            ->where('customer_id', $customer_id)
            ->orderBy('art_id', 'desc')
            ->get();

        $artsData = $arts->map(function ($art) {
            return [
                'art_unique_id' => $art->art_unique_id,
                'title' => $art->title,
                'artist_name' => $art->artist_name,
                'category' => [
                    'category_name' => $art->category->category_name,
                    'category_icon' => url($art->category->category_icon),
                    'category_image' => $art->category->category_image,
                    'sub_text' => $art->category->sub_text,
                ],
                'edition' => $art->edition,
                'price' => $art->price,
                'since' => $art->since,
                'pickup_address' => $art->pickup_address,
                'pincode' => $art->pincode,
                'country' => $art->country,
                'state' => $art->state,
                'city' => $art->city,
                'frame' => $art->frame,
                'paragraph' => $art->paragraph,
                'with_background_image' => $art->with_background_image ? url($art->with_background_image) : null,
                'front_side_image' => $art->front_side_image ? url($art->front_side_image) : null,
                'back_side_image' => $art->back_side_image ? url($art->back_side_image) : null,
                'left_angle_image' => $art->left_angle_image ? url($art->left_angle_image) : null,
                'right_angle_image' => $art->right_angle_image ? url($art->right_angle_image) : null,
                'full_image_with_background' => $art->full_image_with_background ? url($art->full_image_with_background) : null,
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
            ];
        });

        if ($artsData->isEmpty()) {
            $customerDetail['art_details'] = null;
        } else {
            $customerDetail['art_details'] = $artsData;
        }


        return response()->json([
            'status' => true,
            'customerDetails' => $customerDetail
        ]);
    }

    public function get_single_art__(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'art_unique_id' => 'required|exists:art,art_unique_id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $art = Art::with([
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
            ->where('art_unique_id', $request->art_unique_id)
            ->first();
        if (! $art) {
            return response()->json([
                'status' => false,
                'message' => 'No Art Found!'
            ]);
        }
        $customer = Customer::where('customer_id', $art->customer_id)->first();

        $arts = [
            'artist_unique_id' => $customer->customer_unique_id,
            'art_unique_id' => $art->art_unique_id,
            'title' => $art->title,
            'artist_name' => $art->artist_name,
            'art_type' => $art->art_type,
            'category' => [
                'category_id' => $art->category->category_id,
                'category_name' => $art->category->category_name,
                'category_icon' => url($art->category->category_icon),
                'category_image' => $art->category->category_image,
                'sub_text' => $art->category->sub_text,
            ],
            'edition' => $art->edition,
            'price' => $art->price ?? $art->estimate_price_from . ' - ' . $art->estimate_price_to,
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





        return response()->json([
            'status' => true,
            'art' => $arts,
        ]);
    }

    public function add_cart(Request $request)
    {
        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ]);
        }

        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required',
            'art_unique_id' => 'required',

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
                'message' => 'Customer not found.',
            ], 404);
        }
        $art = Art::where('art_unique_id', $request->art_unique_id)->first();
        if (!$art) {
            return response()->json([
                'status' => false,
                'message' => 'Art not found.',
            ], 404);
        }

        if ($art->status == 'Sold') {
            return response()->json([
                'status' => false,
                'message' => 'Art is not available for purchase.',
            ], 400);
        }
        $customer_id = $customer->customer_id;
        $art_id = $art->art_id;
        $existingCart = ArtCart::where('customer_id', $customer->customer_id)
            ->where('art_id', $art->art_id)
            ->where('status', 'Active')
            ->first();

        if ($existingCart) {
            return response()->json([
                'status' => false,
                'message' => 'This art is already in your cart.',
            ]);
        }

        $timezone = $customer->timezone ?? 'Asia/Kolkata';
        $currentDateTime = now($timezone);
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        $totalTax = 0;
        $totalServiceFee = 0;
        $data = DB::table('users')->where('user_id', '1')->first();
        $price = $art->price;

        $taxPercentage = $data->tax;
        $buyer_premium = $data->customer_buyer_premium;
        $serviceFeePercentage = $data->service_fee;

        $taxAmount = ($price * $taxPercentage) / 100;
        $buyer_premiumAmount = ($price * $buyer_premium) / 100;
        $serviceFeeAmount = ($price * $serviceFeePercentage) / 100;


        $totalTax += $taxAmount;
        $totalServiceFee += $serviceFeeAmount;
        $cartData = [
            'art_id' => $art->art_id,
            'price' => $art->price,
            'customer_id' => $customer_id,
            'status' => 'Active',
            'tax' => $totalTax,
            'service_fee' => $serviceFeeAmount,
            'buyer_premium' => $buyer_premiumAmount,
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ];

        try {
            $cart = ArtCart::create($cartData);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to add art to cart. Please try again.',
            ], 500);
        }

        $cartCount = ArtCart::where('customer_id', $customer->customer_id)
            ->where('status', 'Active')
            ->count();
        return response()->json([
            'status' => true,
            'cartCount' => $cartCount,
            'message' => 'Art Added to Cart Successfully!'
        ]);
    }
    public function get_cart(Request $request)
    {

        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ]);
        }

        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required',
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
                'message' => 'Customer not found.',
            ]);
        }

        $cartItems = ArtCart::with([
            'art' => function ($query) {
                $query->select('art_id', 'art_unique_id', 'customer_id', 'title', 'artist_name', 'edition', 'category_id', 'art_type', 'price', 'estimate_price_from', 'estimate_price_to', 'since', 'pickup_address', 'pincode', 'country', 'state', 'city', 'frame', 'paragraph', 'portal_percentages', 'buy_date', 'status')
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
                        'category' => function ($query) {
                            $query->select('category_id', 'category_name', 'category_icon', 'category_image', 'sub_text');
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
        ])
            ->where('customer_id', $customer->customer_id)
            ->get();


        $total = ArtCart::where('customer_id', $customer->customer_id)->sum('price');
        $totalString = (string) $total;
        $data = DB::table('users')->where('user_id', '1')->first();


        $artsData = $cartItems->map(function ($cartItem) use (&$totalTax, &$totalServiceFee, &$buyer_premiumAmountFee) {
            $art = $cartItem->art;
            // dd($art);
            $data = DB::table('users')->where('user_id', '1')->first();

            if ($art && isset($art->price)) {
                $price = $art->price;

                $taxPercentage = $data->tax;
                $serviceFeePercentage = $data->service_fee;
                $buyer_premium = $data->customer_buyer_premium;

                $taxAmount = ($price * $taxPercentage) / 100;
                $buyer_premiumAmount = ($price * $buyer_premium) / 100;
                $serviceFeeAmount = ($price * $serviceFeePercentage) / 100;


                $totalTax += round($taxAmount);
                $totalServiceFee += round($serviceFeeAmount);
                $buyer_premiumAmountFee += round($buyer_premiumAmount);
            }

            $customer = Customer::where('customer_id', $art->customer_id)->first();

            return [
                'art_cart_id' => $cartItem->art_cart_id,
                'artist_unique_id' => $customer->customer_unique_id,
                'artist_fcm_token' => $customer->fcm_token,
                'art_unique_id' => $art->art_unique_id,
                'title' => $art->title,
                'artist_name' => $art->artist_name,
                'art_type' => $art->art_type,
                'edition' => $art->edition,
                'price' => $art->price ?? ($art->estimate_price_from . ' - ' . $art->estimate_price_to),
                'since' => $art->since,
                'pickup_address' => $art->pickup_address,
                'pincode' => $art->pincode,
                'country' => $art->country,
                'state' => $art->state,
                'city' => $art->city,
                'frame' => $art->frame,
                'paragraph' => $art->paragraph,
                'status' => $art->status,
                'category' => [
                    'category_id' => $art->category->category_id,
                    'category_name' => $art->category->category_name,
                    'category_icon' => url($art->category->category_icon),
                    'category_image' => url($art->category->category_image),
                    'sub_text' => $art->category->sub_text,
                ],
                'country_details' => [
                    'country_id' => $art->countries->country_id,
                    'country_name' => $art->countries->country_name
                ],
                'state_details' => [
                    'state_id' => $art->states->state_subdivision_id,
                    'state_name' => $art->states->state_subdivision_name
                ],
                'city_details' => [
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

        $payableamout = $totalString + $totalTax + $totalServiceFee + $buyer_premiumAmountFee;



        if ($cartItems->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No items in cart.',
            ], 200);
        }
        return response()->json([
            'status' => true,
            'data' => $artsData,
            'total' => $totalString,
            'payableamout' => $payableamout,
            'totalTax' => $totalTax,
            'totalServiceFee' => $totalServiceFee,
            'buyer_premiumAmountFee' => $buyer_premiumAmountFee,
            'tax_per' => $data->tax,
            'service_per' => $data->service_fee,
            'buyer_premium' => $data->customer_buyer_premium,
        ]);
    }
    //     public function get_cart(Request $request)
    // {
    //     if (!Auth::guard('customer_api')->check()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Unauthorized access.',
    //         ]);
    //     }

    //     $validator = Validator::make($request->all(), [
    //         'customer_unique_id' => 'required',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => $validator->errors()->first(),
    //         ], 400);
    //     }

    //     $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();
    //     if (!$customer) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Customer not found.',
    //         ]);
    //     }

    //     $cartItems = ArtCart::with([
    //         'art' => function ($query) {
    //             $query->select('art_id', 'art_unique_id', 'customer_id', 'title', 'artist_name', 'edition', 'category_id', 'art_type', 'price', 'estimate_price_from', 'estimate_price_to', 'since', 'pickup_address', 'pincode', 'country', 'state', 'city', 'frame', 'paragraph', 'portal_percentages', 'buy_date', 'status')
    //                 ->with([
    //                     'artAdditionalDetails' => function ($query) {
    //                         $query->select('art_additional_details_id', 'art_id', 'art_data_id', 'description')
    //                             ->with([
    //                                 'artData' => function ($query) {
    //                                     $query->select('art_data_id', 'art_data_title');
    //                                 }
    //                             ]);
    //                     },
    //                     'artImages' => function ($query) {
    //                         $query->select('art_image_id', 'art_id', 'art_type', 'image');
    //                     },
    //                     'category' => function ($query) {
    //                         $query->select('category_id', 'category_name', 'category_icon', 'category_image', 'sub_text');
    //                     },
    //                     'countries' => function ($query) {
    //                         $query->select('country_id', 'country_name');
    //                     },
    //                     'states' => function ($query) {
    //                         $query->select('state_subdivision_id', 'state_subdivision_name');
    //                     },
    //                     'cities' => function ($query) {
    //                         $query->select('cities_id', 'name_of_city');
    //                     }
    //                 ]);
    //         }
    //     ])
    //     ->where('customer_id', $customer->customer_id)
    //     ->get();

    //     $total = ArtCart::where('customer_id', $customer->customer_id)->sum('price');
    //     $totalString = (string) $total;
    //     $data = DB::table('users')->where('user_id', '1')->first();

    //     // Initialize tax and service fee variables
    //     $totalTax = 0;
    //     $totalServiceFee = 0;

    //     $artsData = $cartItems->map(function ($cartItem) use (&$totalTax, &$totalServiceFee) {
    //         $art = $cartItem->art;

    //         if (!$art) {
    //             return null; // Skip this iteration if there's no art data
    //         }

    //         $data = DB::table('users')->where('user_id', '1')->first();

    //         if ($art && isset($art->price)) {
    //             $price = $art->price;

    //             $taxPercentage = $data->tax;
    //             $serviceFeePercentage = $data->service_fee;

    //             $taxAmount = ($price * $taxPercentage) / 100;
    //             $serviceFeeAmount = ($price * $serviceFeePercentage) / 100;

    //             $totalTax += round($taxAmount);
    //             $totalServiceFee += round($serviceFeeAmount);
    //         }

    //         $customer = Customer::where('customer_id', $art->customer_id)->first();

    //         return [
    //             'art_cart_id' => $cartItem->art_cart_id,
    //             'artist_unique_id' => $customer->customer_unique_id,
    //             'artist_fcm_token' => $customer->fcm_token,
    //             'art_unique_id' => $art->art_unique_id,
    //             'title' => $art->title,
    //             'artist_name' => $art->artist_name,
    //             'art_type' => $art->art_type,
    //             'edition' => $art->edition,
    //             'price' => $art->price ?? ($art->estimate_price_from . ' - ' . $art->estimate_price_to),
    //             'since' => $art->since,
    //             'pickup_address' => $art->pickup_address,
    //             'pincode' => $art->pincode,
    //             'country' => $art->country,
    //             'state' => $art->state,
    //             'city' => $art->city,
    //             'frame' => $art->frame,
    //             'paragraph' => $art->paragraph,
    //             'status' => $art->status,
    //             'category' => [
    //                 'category_id' => $art->category->category_id,
    //                 'category_name' => $art->category->category_name,
    //                 'category_icon' => url($art->category->category_icon),
    //                 'category_image' => url($art->category->category_image),
    //                 'sub_text' => $art->category->sub_text,
    //             ],
    //             'country_details' => [
    //                 'country_id' => $art->countries->country_id,
    //                 'country_name' => $art->countries->country_name
    //             ],
    //             'state_details' => [
    //                 'state_id' => $art->states->state_subdivision_id,
    //                 'state_name' => $art->states->state_subdivision_name
    //             ],
    //             'city_details' => [
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

    //     $payableamout = $totalString + $totalTax + $totalServiceFee;

    //     if ($cartItems->isEmpty()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'No items in cart.',
    //         ], 200);
    //     }

    //     return response()->json([
    //         'status' => true,
    //         'data' => $artsData,
    //         'total' => $totalString,
    //         'payableamout' => $payableamout,
    //         'totalTax' => $totalTax,
    //         'totalServiceFee' => $totalServiceFee,
    //         'tax_per' => $data->tax,
    //         'service_per' => $data->service_fee,
    //     ]);
    // }


    public function add_wishlist(Request $request)
    {
        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ]);
        }

        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required',
            'art_unique_id' => 'required',

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
                'message' => 'Customer not found.',
            ], 404);
        }
        $art = Art::where('art_unique_id', $request->art_unique_id)->first();
        if (!$art) {
            return response()->json([
                'status' => false,
                'message' => 'Art not found.',
            ], 404);
        }

        // if ($art->status == 'Sold') {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Art is not available for purchase.',
        //     ], 400);
        // }
        $customer_id = $customer->customer_id;
        $art_id = $art->art_id;

        $existingCart = Wishlists::where('customer_id', $customer->customer_id)
            ->where('art_id', $art->art_id)
            ->where('status', 'Active')
            ->first();

        if ($existingCart) {
            return response()->json([
                'status' => false,
                'message' => 'This art is already in your wishlist.',
            ]);
        }

        $timezone = $customer->timezone ?? 'Asia/Kolkata';
        $currentDateTime = now($timezone);
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();
        $wishllistData = [
            'art_id' => $art->art_id,
            'price' => $art->price,
            'customer_id' => $customer_id,
            'status' => 'Active',
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ];

        try {
            $wishlist = Wishlists::create($wishllistData);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to add art to cart. Please try again.',
            ], 500);
        }

        $wishlistCount = Wishlists::where('customer_id', $customer->customer_id)
            ->where('status', 'Active')
            ->count();
        return response()->json([
            'status' => true,
            'wishlistCount' => $wishlistCount,
            'message' => 'Art Added to wishlist Successfully!'
        ]);
    }

    public function get_wishlist(Request $request)
    {

        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ]);
        }

        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required',
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
                'message' => 'Customer not found.',
            ], 404);
        }



        $wishlistItems = Wishlists::with([
            'art' => function ($query) {
                $query->select('art_id', 'art_unique_id', 'customer_id', 'title', 'artist_name', 'edition', 'category_id', 'art_type', 'price', 'estimate_price_from', 'estimate_price_to', 'since', 'pickup_address', 'pincode', 'country', 'state', 'city', 'frame', 'paragraph', 'portal_percentages', 'buy_date', 'status')
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
                        'category' => function ($query) {
                            $query->select('category_id', 'category_name', 'category_icon', 'category_image', 'sub_text');
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
        ])
            ->where('customer_id', $customer->customer_id)
            ->where('status', 'Active')
            ->get();

        if ($wishlistItems->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No Data Found in Wishlist!'
            ]);
        }

        // $wishlistItems->each(function ($wishlistItem) {
        //     if ($wishlistItem->art && $wishlistItem->art->artImages) {
        //         $wishlistItem->art->artImages->map(function ($img) {
        //             $img->image = url($img->image);
        //             return $img;
        //         });
        //     }
        // });

        $artsData = $wishlistItems->map(function ($wishlistItem) {
            $art = $wishlistItem->art;

            $customer = Customer::where('customer_id', $art->customer_id)->first();

            return [
                'wishlist_id' => $wishlistItem->wishlist_id,
                'artist_unique_id' => $customer->customer_unique_id,
                'art_unique_id' => $art->art_unique_id,
                'title' => $art->title,
                'artist_name' => $art->artist_name,
                'art_type' => $art->art_type,
                'edition' => $art->edition,
                'price' => $art->price ?? ($art->estimate_price_from . ' - ' . $art->estimate_price_to),
                'since' => $art->since,
                'pickup_address' => $art->pickup_address,
                'pincode' => $art->pincode,
                'country' => $art->country,
                'state' => $art->state,
                'city' => $art->city,
                'frame' => $art->frame,
                'paragraph' => $art->paragraph,
                'status' => $art->status,
                'category' => [
                    'category_id' => $art->category->category_id,
                    'category_name' => $art->category->category_name,
                    'category_icon' => url($art->category->category_icon),
                    'category_image' => url($art->category->category_image),
                    'sub_text' => $art->category->sub_text,
                ],
                'country_details' => [
                    'country_id' => $art->countries->country_id,
                    'country_name' => $art->countries->country_name
                ],
                'state_details' => [
                    'state_id' => $art->states->state_subdivision_id,
                    'state_name' => $art->states->state_subdivision_name
                ],
                'city_details' => [
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
            'wishlist_items' => $artsData,
        ]);
    }

    public function delete_wishlist(Request $request)
    {
        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ]);
        }

        $validator = Validator::make($request->all(), [
            'wishlist_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $wishlist = Wishlists::where('wishlist_id', $request->wishlist_id)->first();

        if (!$wishlist) {
            return response()->json([
                'status' => false,
                'message' => 'No Art found in Wishlist!',
            ]);
        }
        $delete = Wishlists::where('wishlist_id', $request->wishlist_id)->delete();
        if ($delete) {
            return response()->json([
                'status' => true,
                'message' => 'Art Removed From Wishlist',
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'No Art  Found!',
            ]);
        }
    }

    public function get_single_art(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'art_unique_id' => 'required|exists:art,art_unique_id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $customerData = null;
        if ($request->has('customer_unique_id')) {
            $customerData = Customer::where('customer_unique_id', $request->customer_unique_id)->first();
        }
        // $customerData =Customer::where('customer_unique_id',$request->customer_unique_id)->first();


        $art = Art::with([
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
                $query->select('category_id', 'category_name', 'sub_text', 'category_image', 'category_icon');
            }
        ])
            ->where('art_unique_id', $request->art_unique_id)
            ->first();

        if (!$art) {
            return response()->json([
                'status' => false,
                'message' => 'No Art Found!',
            ], 404);
        }



        $customer = Customer::find($art->customer_id);

        $artDetails = $this->formatArtDetails($art, $customerData ? $customerData->customer_id : null);

        $artsFromArtist = $this->fetchArtsFromArtist($customer->customer_id);

        $categoryArtData = null;

        $categoryArtData = $this->fetchArtsByCategory($art->category_id);

        return response()->json([
            'status' => true,
            'art' => $artDetails,
            'from_artist' => $artsFromArtist,
            'categoryArtData' => $categoryArtData,
        ]);
    }

    private function formatArtDetails($art, $customer_id = null)
    {
        $customer = Customer::where('customer_id', $art->customer_id)->first();
        $isWishlist = false;

        if ($customer_id) {
            $wishlist = Wishlists::where('customer_id', $customer_id)->where('art_id', $art->art_id)->first();
            if ($wishlist) {
                $isWishlist = true;
            }
        }

        return [
            'art_unique_id' => $art->art_unique_id,
            'isWishlist' => $isWishlist ?? null,
            'title' => $art->title,
            'artist_name' => $art->artist_name,
            'artist_unique_id' => $customer->customer_unique_id,
            'artist_fcm_token' => $customer->fcm_token,
            'art_type' => $art->art_type,
            'category' => [
                'category_id' => $art->category->category_id,
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
            'country' => $art->countries->country_name,
            'state' => $art->states->state_subdivision_name,
            'city' => $art->cities->name_of_city,
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
            'artImages' => $art->artImages->map(function ($image) {
                return [
                    'art_image_id' => $image->art_image_id,
                    'art_type' => $image->art_type,
                    'image' => url($image->image),
                ];
            }),
        ];
    }

    private function fetchArtsFromArtist($customerId)
    {
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
                $query->select('category_id', 'category_name', 'sub_text', 'category_image', 'category_icon');
            }
        ])
            ->where('customer_id', $customerId)
            ->where('art_type', 'Online')
            ->Where('status', 'Approved')
            ->orderBy('art_id', 'desc')
            ->get();

        return $arts->map(function ($art) {
            return $this->formatArtDetails($art);
        });
    }

    private function fetchArtsByCategory($categoryId)
    {
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
                $query->select('category_id', 'category_name', 'sub_text', 'category_image', 'category_icon');
            }
        ])
            ->where('category_id', $categoryId)
            ->where('art_type', 'Online')
            ->orderBy('art_id', 'desc')
            ->get();

        return $arts->map(function ($art) {
            return $this->formatArtDetails($art);
        });
    }

    public function get_artist_single_art_story(Request $request)
    {
        $art_unique_id = $request->art_unique_id;

        $art = Art::where('art_unique_id', $art_unique_id)->first();

        if (!$art) {
            return response()->json([
                'status' => false,
                'message' => 'Art not found'
            ]);
        }

        $data = ArtistArtStories::with([
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
                        }
                    ]);
            },
            'customer' => function ($query) {
                $query->select('customer_unique_id', 'customer_id', 'customer_profile', 'name', 'role', 'email', 'mobile');
            }
        ])
            ->where('art_id', $art->art_id)
            ->first();

        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'No Stories Found'
            ]);
        }

        $storyData = $this->transformStoryData($data);

        $artsFromArtist = $this->fetchArtStoryFromArtist($art->customer_id);

        return response()->json([
            'status' => true,
            'stories' => $storyData,
            'from_artist' => $artsFromArtist
        ]);
    }

    private function transformStoryData($story)
    {
        $storyData = [];

        if ($story->art) {
            $storyData['art'] = $story->art;

            $storyData['art']->category_id = $story->art->category_id ?? null;

            $storyData['art']->price = $story->art->price ?? ($story->art->estimate_price_from . ' - ' . $story->art->estimate_price_to);
        }

        $storyData['paragraph'] = ArtistArtStories::where('art_id', $story->art_id)->get();

        $storyData['image'] = $story->art->artImages->map(function ($img) {
            if ($img->image) {
                // Prepend the base URL to the image path
                $img->image = "https://artist.genixbit.com/" . $img->image;
            }
            return $img;
        });

        $customer = $story->customer;
        if ($customer) {
            $customer->customer_profile = isset($customer->customer_profile) ? "https://artist.genixbit.com/" . $customer->customer_profile : null;
            $storyData['customer'] = $customer;
        }

        return $storyData;
    }

    private function fetchArtStoryFromArtist($customerId)
    {
        $data = ArtistArtStories::with([
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
                        }
                    ]);
            },
            'customer' => function ($query) {
                $query->select('customer_unique_id', 'customer_id', 'customer_profile', 'name', 'role', 'email', 'mobile');
            }
        ])
            ->where('customer_id', $customerId)
            ->get();

        return $data->map(function ($art) {
            return $this->transformStoryData($art);
        });
    }

    public function delete_cart(Request $request)
    {
        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ]);
        }

        $validator = Validator::make($request->all(), [
            'art_cart_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $cart = ArtCart::where('art_cart_id', $request->art_cart_id)->first();

        // dd($cart);

        if (!$cart) {
            return response()->json([
                'status' => false,
                'message' => 'No Art found in cart!',
            ]);
        }
        $delete = ArtCart::where('art_cart_id', $request->art_cart_id)->delete();
        if ($delete) {
            return response()->json([
                'status' => true,
                'message' => 'Art Removed From Cart',
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'No Art  Found!',
            ]);
        }
    }

    public function get_single_private_art(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'art_unique_id' => 'required|exists:art,art_unique_id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $customerData = null;
        if ($request->has('customer_unique_id')) {
            $customerData = Customer::where('customer_unique_id', $request->customer_unique_id)->first();
        }
        // $customerData =Customer::where('customer_unique_id',$request->customer_unique_id)->first();


        $art = Art::with([
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
                $query->select('category_id', 'category_name', 'sub_text', 'category_image', 'category_icon');
            }
        ])
            ->where('art_unique_id', $request->art_unique_id)
            ->first();

        if (!$art) {
            return response()->json([
                'status' => false,
                'message' => 'No Art Found!',
            ], 404);
        }



        $customer = Customer::find($art->customer_id);

        $artDetails = $this->formatPrivateArtDetails($art, $customerData ? $customerData->customer_id : null);

        $artsFromArtist = $this->fetchPrivateArtsFromArtist($customer->customer_id);

        $categoryArtData = null;

        $categoryArtData = $this->fetchPrivateArtsByCategory($art->category_id);

        return response()->json([
            'status' => true,
            'art' => $artDetails,
            'from_artist' => $artsFromArtist,
            'categoryArtData' => $categoryArtData,
        ]);
    }

    private function formatPrivateArtDetails($art, $customer_id = null)
    {
        $customer = Customer::where('customer_id', $art->customer_id)->first();
        $isWishlist = false;

        if ($customer_id) {
            $wishlist = Wishlists::where('customer_id', $customer_id)->where('art_id', $art->art_id)->first();
            if ($wishlist) {
                $isWishlist = true;
            }
        }

        return [
            'art_unique_id' => $art->art_unique_id,
            'isWishlist' => $isWishlist ?? null,
            'title' => $art->title,
            'artist_name' => $art->artist_name,
            'artist_unique_id' => $customer->customer_unique_id,
            'artist_fcm_token' => $customer->fcm_token,
            'art_type' => $art->art_type,
            'category' => [
                'category_id' => $art->category->category_id,
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
            'country' => $art->countries->country_name,
            'state' => $art->states->state_subdivision_name,
            'city' => $art->cities->name_of_city,
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
            'artImages' => $art->artImages->map(function ($image) {
                return [
                    'art_image_id' => $image->art_image_id,
                    'art_type' => $image->art_type,
                    'image' => url($image->image),
                ];
            }),
        ];
    }

    private function fetchPrivateArtsFromArtist($customerId)
    {
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
                $query->select('category_id', 'category_name', 'sub_text', 'category_image', 'category_icon');
            }
        ])
            ->where('customer_id', $customerId)
            ->where('art_type', 'Private')
            ->Where('status', 'Approved')
            ->orderBy('art_id', 'desc')
            ->get();

        return $arts->map(function ($art) {
            return $this->formatPrivateArtDetails($art);
        });
    }

    private function fetchPrivateArtsByCategory($categoryId)
    {
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
                $query->select('category_id', 'category_name', 'sub_text', 'category_image', 'category_icon');
            }
        ])
            ->where('category_id', $categoryId)
            ->where('art_type', 'Private')
            ->orderBy('art_id', 'desc')
            ->get();

        return $arts->map(function ($art) {
            return $this->formatPrivateArtDetails($art);
        });
    }



    public function add_delivery_address(Request $request)
    {
        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ]);
        }

        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required|string',
            'full_name' => 'required|string',
            'mobile' => 'required',
            'country' => 'required',
            'state' => 'required',
            'city' => 'required',
            // 'house_no' => 'required|string',
            'address' => 'required',
            'pincode' => 'required',
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
                'message' => 'Customer not found.',
            ], 404);
        }
        $customer_id = $customer->customer_id;

        $timezone = $customer->timezone ?? 'Asia/Kolkata';
        $currentDateTime = now($timezone);
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();
        $addressData = [
            'customer_id' => $customer_id,
            'full_name' => $request->full_name,
            'mobile' => $request->mobile,
            'country' => $request->country,
            'state' => $request->state,
            'city' => $request->city,
            // 'house_no' => $request->house_no,
            'address' => $request->address,
            'pincode' => $request->pincode,
            'status' => 'Active',
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ];

        try {
            // $existingAddress = CustomerDeliveryAddress::where('customer_id', $customer_id)->first();

            // if ($existingAddress) {
            //     $existingAddress->update($addressData);
            //     return response()->json([
            //         'status' => true,
            //         'message' => 'Delivery Address Updated Successfully!'
            //     ]);
            // } else {
            //     $address = CustomerDeliveryAddress::create($addressData);
            //     return response()->json([
            //         'status' => true,
            //         'message' => 'Delivery Address Added Successfully!'
            //     ]);
            // }
            $address = CustomerDeliveryAddress::create($addressData);
            return response()->json([
                'status' => true,
                'message' => 'Delivery Address Added Successfully!',
                'customers_delivery_address_id' => $address->customers_delivery_address_id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => ' Please try again.',
            ], 200);
        }
    }


    public function check_quantity(Request $request)
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
        $customer_unique_id = $request->customer_unique_id;

        $customer = Customer::where('customer_unique_id', $customer_unique_id)->first();


        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'Customer Not Found'
            ]);
        }

        $cartData = ArtCart::where('customer_id', $customer->customer_id)->get();

        if ($cartData->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Cart Data Not Found'
            ]);
        }
        $allInStock = true;
        $outOfStockProducts = [];
        foreach ($cartData as $cart) {
            // dd($cart);
            $res = ArtCart::where('art_cart_id', $cart->art_cart_id)->first();

            if (!$res) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cart item not found.'
                ], 404);
            }

            $art = Art::where('art_id', $res->art_id)->first();

            if ($art->status == 'Sold') {
                $allInStock = false;
                $outOfStockProducts[] = $res;
            }
        }
        return response()->json([
            'status' => $allInStock,
            'out_of_stock_products' => $outOfStockProducts,
        ]);
    }
    public function checkout(Request $request)
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
        $customer_unique_id = $request->customer_unique_id;

        $customer = Customer::where('customer_unique_id', $customer_unique_id)->first();


        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'Customer Not Found'
            ]);
        }

        $customerId = $customer->customer_id;



        $quantityCheckResponse = $this->check_quantity($request);
        if ($quantityCheckResponse->getData()->status === false) {
            return response()->json($quantityCheckResponse->getData());
        }


        $customerCartArtData = ArtCart::where('customer_id', $customerId)
            ->get();

        if ($customerCartArtData->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => "Please add Art to your cart."
            ]);
        }

        $cartItems = [];
        $totalAmount = 0;




        foreach ($customerCartArtData as $cartData) {

            $ArtData = Art::where('art_id', $cartData->art_id)
                ->first();

            $artImages = ArtImage::where('art_id', $cartData->art_id)->first();
            $ar = [];


            $ar['art_cart_id'] = $cartData->art_cart_id;
            $ar['title'] = $ArtData->title;
            $ar['art_unique_id'] = $ArtData->art_unique_id;
            $ar['images'] = isset($artImages->image) ? url($artImages->image) : null;
            $ar['price'] = $cartData->price ?? 0;

            $totalAmount +=  $ar['price'];

            $cartItems[] = $ar;
        }
        return response()->json([
            'status' => true,
            'cartItems' => $cartItems,
            'cartCount' => count($cartItems),
            'total_amount' => $totalAmount,

        ]);
    }

    // public function seller_seat_booking(Request $request){

    //     $validator = Validator::make($request->all(), [
    //         'customer_unique_id' => 'required',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => $validator->errors()->first(),
    //         ], 400);
    //     }
    //     $customer_unique_id = $request->customer_unique_id;

    //     $customer = Customer::where('customer_unique_id', $customer_unique_id)->first();


    //     if (!$customer) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Customer Not Found'
    //         ]);
    //     }

    // }

    public function get_ads()
    {
        $ads = Ads::select('ads_id', 'plan_name', 'plan_image', 'days', 'views', 'price')->where('status', 'Active')->get();
        foreach ($ads as $ad) {
            $ad->plan_image = url($ad->plan_image) ?? null;
        }
        if ($ads->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No Advertise data  found!'
            ]);
        } else {
            return response()->json([
                'status' => true,
                'ads' => $ads
            ]);
        }
    }

    public function moveSingleWishlistToCart(Request $request)
    {
        $request->validate([
            'customer_unique_id' => 'required|string',
            'art_unique_id' => 'required|string',
        ]);

        $customerUniqueId = $request->input('customer_unique_id');
        $artUniqueId = $request->input('art_unique_id');
        $customer = Customer::where('customer_unique_id', $customerUniqueId)->first();
        if (!$customer) {
            return response()->json([
                'status' => 'false',
                'message' => 'Customer not found.',
            ]);
        }
        $customerId = $customer->customer_id;

        $art = Art::where('art_unique_id', $artUniqueId)->first();
        if (!$art) {
            return response()->json([
                'status' => 'false',
                'message' => 'Art not found.',
            ]);
        }
        $artId = $art->art_id;

        $wishlistItem = Wishlists::where('customer_id', $customerId)
            ->where('art_id', $artId)
            ->where('status', 'Active')
            ->first();

        if (!$wishlistItem) {
            return response()->json([
                'status' => 'false',
                'message' => 'Wishlist item not found.',
            ]);
        }

        $soldItem = DB::table('art')
            ->where('art_id', $artId)
            ->where('status', 'Sold')
            ->first();
        if ($soldItem) {
            return response()->json([
                'status' => 'false',
                'message' => 'Item Alredy Sold.',
            ]);
        }

        $artItem = ArtCart::where('customer_id', $customerId)
            ->where('art_id', $artId)
            ->first();

        if ($artItem) {
            return response()->json([
                'status' => 'false',
                'message' => 'Item alreay in cart.',
            ]);
        }
        // $timezone=$customer->timezone?? 'Asia/Kolkata';
        // $currentDateTime = now($timezone);

        // ArtCart::create([
        //     'art_id' => $artId,
        //     'customer_id' => $customerId,
        //     'price' => $wishlistItem->price,
        //     'status' => 'Active',

        //     'inserted_date' => now()->toDateString(),
        //     'inserted_time' => now()->toTimeString(),
        // ]);


        Wishlists::where('wishlist_id', $wishlistItem->wishlist_id)->update(['status' => 'Inactive']);

        return response()->json([
            'status' => 'true',
            'message' => 'Wishlist item moved to cart successfully.',
        ]);
    }



    public function seller_order(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required',
            'role' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->where('role', $request->role)->first();
        $seller_id = $customer->customer_id;
        $role = $request->role;

        if (!$seller_id) {
            return response()->json([
                'status' => false,
                'message' => 'seller ID required'
            ]);
        }


        $orderedArtQuery = DB::table('ordered_arts')->where('art_order_status', 'Pending');

        $orderedArts = $orderedArtQuery->where('seller_id', $seller_id)->orderBy('order_id', 'desc')->get();



        if ($orderedArts->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No orders found for this user.'
            ], 200);
        }

        $products = [];

        foreach ($orderedArts as $orders) {
            $orderID = DB::table('orders')
                ->where('order_id', $orders->order_id)
                ->first();

            $isTrack = DB::table('ordered_arts')
                ->where('art_id', $orders->art_id)
                ->whereNull('tracking_id')  // Check if tracking_id is null
                ->exists();  // This returns true if any record is found, false if not



            $customer = Customer::where('customer_id', $orders->customer_id)->first();

            $product = DB::table('art')
                ->where('art_id', $orders->art_id)
                ->first();

            $productImage = DB::table('art_images')
                ->where('art_id', $product->art_id)
                ->first();

            if ($product) {
                $products[] = [
                    'order_id' => $orderID->order_id,
                    'order_unique_id' => $orderID->order_unique_id,
                    'art_order_status' => $orders->art_order_status,
                    'customer_name' => $customer->name,
                    'customer_fcm_token' => $customer->fcm_token,
                    'title' => $product->title,
                    'art_unique_id' => $product->art_unique_id,
                    'art_image' => isset($productImage->image) ? url($productImage->image) : null,
                    'price' => $product->price,
                    'order_date' => $orderID->inserted_date,
                    'isTrack' => $isTrack
                ];
            }
        }

        return response()->json([
            'status' => true,
            'arts' => $products
        ]);
    }

    public function add_tracking_system(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_unique_id' => 'required',
            'art_unique_id' => 'required',
            'tracking_id' => 'required',
            'tracking_link' => 'required',
            'company_name' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $order = DB::table('orders')->where('order_unique_id', $request->order_unique_id)->first();

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found.',
            ], 404);
        }

        $art = DB::table('art')->where('art_unique_id', $request->art_unique_id)->first();
        $artImage = DB::table('art_images')->where('art_id', $art->art_id)->first();

        if (!$art) {
            return response()->json([
                'status' => false,
                'message' => 'Art not found.',
            ], 404);
        }

        $orderedArt = DB::table('ordered_arts')
            ->where('order_id', $order->order_id)
            ->where('art_id', $art->art_id)
            ->first();

        if (!$orderedArt) {
            return response()->json([
                'status' => false,
                'message' => 'No matching ordered art found for this order and art.',
            ], 404);
        }

        DB::table('ordered_arts')
            ->where('order_id', $order->order_id)
            ->where('art_id', $art->art_id)
            ->update([
                'tracking_id' => $request->tracking_id,
                'tracking_link' => $request->tracking_link,
                'company_name' => $request->company_name,
                'tracking_status' => 'Order-Qued'
            ]);


        if (!empty($request->fcm_token)) {
            try {
                $firebase = (new Factory)->withServiceAccount(base_path('mira-monet-firebase-adminsdk-fbsvc-864230166e.json'));
                $messaging = $firebase->createMessaging();
                $fcm_token = $request->fcm_token;

                $messageData = CloudMessage::withTarget('token', $fcm_token)
                    ->withNotification([
                        'title' => 'Your Artwork  ' . $art->title  . 'Is Being Crafted!',
                        'body' => 'Thank you for your order! Your Order tracking status is Order-Qued',
                        'image' => isset($artImage->image) ? url($artImage->image) : null,
                    ]);

                $messaging->send($messageData);
            } catch (\Exception $e) {
                \Log::error("FCM Notification Error: " . $e->getMessage());
            }
        }


        return response()->json([
            'status' => true,
            'message' => 'Tracking system added successfully.',
        ]);
    }

    // public function seller_confirms_order(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'order_unique_id' => 'required',
    //         'type' => 'required',

    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => $validator->errors()->first(),
    //         ], 400);
    //     }
    //     $order_unique_id = $request->order_unique_id;
    //     $type = $request->type;
    //     if (!$order_unique_id) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Order Unique ID Required!'
    //         ]);
    //     }
    //     if ($type == 'Declined') {
    //         $orders = DB::table('orders')
    //             ->where('order_unique_id', $order_unique_id)
    //             ->first();
    //         // dd($orders->order_id);
    //         $order_data = DB::table('ordered_arts')
    //             ->where('order_id', $orders->order_id)
    //             ->get();

    //         if ($order_data->isEmpty()) {
    //             return response()
    //                 ->json([
    //                     'status' => false,
    //                     'message' => 'Order Data Not Found!'
    //                 ]);
    //         }

    //         foreach ($order_data as $order) {
    //             // dd($order);
    //             $product_varient = DB::table('art')
    //                 ->where('art_id', $order->art_id)
    //                 ->first();

    //             $order_arts = DB::table('ordered_arts')
    //                 ->where('art_id', $order->art_id)
    //                 ->update([
    //                     'art_order_status' => $type
    //                 ]);

    //             DB::table('art')
    //                 ->where('art_id',  $order->art_id)
    //                 ->update([
    //                     'status' => 'Approved',
    //                     'buy_date' => ''
    //                 ]);
    //         }
    //     }

    //     $orders = DB::table('orders')
    //     ->where('order_unique_id', $order_unique_id)
    //     ->first();

    //     $order_data = DB::table('ordered_arts')
    //     ->where('order_id', $orders->order_id)
    //     ->get();

    //     foreach ($order_data as $order) {
    //         // dd($order);
    //         $product_varient = DB::table('art')
    //             ->where('art_id', $order->art_id)
    //             ->first();

    //         $order_arts = DB::table('ordered_arts')
    //             ->where('art_id', $order->art_id)
    //             ->update([
    //                 'art_order_status' => $type
    //             ]);
    //     }


    //     $update = DB::table('orders')
    //         ->where('order_unique_id', $order_unique_id)
    //         ->update([
    //             'order_status' => $type
    //         ]);




    //     if ($update) {
    //         return response()->json([
    //             'status' => true,
    //             'message' => 'Order Status changes to ' . $type
    //         ]);
    //     } else {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Failed to update order status.'
    //         ]);
    //     }
    // }

    public function seller_confirms_order(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'order_unique_id' => 'required',
            'type' => 'required|in:Confirmed,Declined',
            'art_unique_id' => 'required',
            'customer_unique_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $customer_unique_id = $request->customer_unique_id;
        $type = $request->type;

        // $order = DB::table('orders')->where('order_unique_id', $order_unique_id)->first();
        // if (!$order) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Order not found!',
        //     ], 404);
        // }

        $art = DB::table('art')->where('art_unique_id', $request->art_unique_id)->first();
        $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();
        $artImage = DB::table('art_images')->where('art_id', $art->art_id)->first();
        if (!$art) {
            return response()->json([
                'status' => false,
                'message' => 'Art not found!',
            ], 404);
        }

        $order_data = DB::table('ordered_arts')
            ->where('seller_id', $customer->customer_id)
            ->where('art_id', $art->art_id)
            ->first();

        if (!$order_data) {
            return response()->json([
                'status' => false,
                'message' => 'No ordered items found for this art in the specified order!',
            ], 404);
        }
        $timezone = $customer->timezone ?? 'Asia/Kolkata';
        $currentDateTime = now($timezone);



        // $currentDateTime = now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        $imageUrl = url($artImage->image);

        DB::beginTransaction();
        $firebase = (new Factory)->withServiceAccount(base_path('mira-monet-firebase-adminsdk-fbsvc-864230166e.json'));
        $messaging = $firebase->createMessaging();
        $fcm_token = $request->customer_fcm_token;
        $messageData = CloudMessage::withTarget('token', $fcm_token)
            ->withNotification([
                'title' => $art->title,
                'body' => 'Your art is ' . $type . ' by the Artist',
                'image' => $imageUrl
            ]);
        try {

            $messaging->send($messageData);
            DB::table('notification')->insert([
                'title' =>  $art->title,
                'body' => 'Your art is ' . $type . ' by the Artist',
                'customer_id' =>  $order_data->customer_id,
                'image' => url($artImage->image) ?? null,
                'inserted_date' => $insertDate,
                'inserted_time' => $insertTime,
            ]);
            $deliveryDate = Carbon::today()->addDays(7);

            DB::table('ordered_arts')
                ->where('seller_id',  $customer->customer_id)
                ->where('art_id', $order_data->art_id)
                ->update([
                    'art_order_status' => $type,
                    'delivered_date' => $deliveryDate
                    // 'tracking_id' => null,
                    // 'tracking_link' => null
                ]);

            $user = Customer::where('customer_id', $order_data->customer_id)->first();
            if ($type === 'Declined') {
                DB::table('art')
                    ->where('art_id', $order_data->art_id)
                    ->update([
                        'status' => 'Declined',
                        // 'buy_date' => null,
                    ]);

                $admin = DB::table('users')
                    ->where('user_id', '1')
                    ->first();

                $firebase = (new Factory)->withServiceAccount(base_path('mira-monet-firebase-adminsdk-fbsvc-864230166e.json'));
                $messaging = $firebase->createMessaging();
                $fcm_token = $admin->fcm_token;
                $messageData = CloudMessage::withTarget('token', $fcm_token)
                    ->withNotification([
                        'title' => $art->title,
                        'body' => 'The art is ' . $type . ' by the Artist',
                        'image' => $imageUrl
                    ]);


                $adminMailData = [
                    'admin_name' => $admin->name,
                    'art_name' => $art->title,
                    'art_unique_id' => $art->art_unique_id,
                    'status' => $art->status,
                    'artist_name' => $customer->name,
                    'artist_email' => $customer->email,
                    'customer_name' => $user->name,
                    'customer_email' => $user->email,

                ];

                Mail::send('emails.admin_art_declined', ['adminMailData' => $adminMailData], function ($message) use ($admin) {
                    $message->to($admin->admin_mail)
                        ->subject('Art Declined')
                        ->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
                        ->replyTo(env('MAIL_REPLY_TO_ADDRESS', env('MAIL_FROM_ADDRESS')));
                });


                DB::table('ordered_arts')
                    ->where('seller_id',  $customer->customer_id)
                    ->where('art_id', $order_data->art_id)
                    ->update([
                        'art_order_status' => $type,
                        'tracking_id' => null,
                        'tracking_link' => null
                    ]);
            }

            // DB::table('orders')
            //     ->where('order_unique_id', $order_unique_id)
            //     ->update(['order_status' => $type]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Order status changed to ' . $type,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Failed to update order status: ' . $e->getMessage(),
            ], 500);
        }
    }
    public function seller_confirms_order_web(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'order_unique_id' => 'required',
            'type' => 'required|in:Confirmed,Declined',
            'art_unique_id' => 'required',
            'customer_unique_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $customer_unique_id = $request->customer_unique_id;
        $type = $request->type;

        // $order = DB::table('orders')->where('order_unique_id', $order_unique_id)->first();
        // if (!$order) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Order not found!',
        //     ], 404);
        // }

        $art = DB::table('art')->where('art_unique_id', $request->art_unique_id)->first();
        $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();
        $artImage = DB::table('art_images')->where('art_id', $art->art_id)->first();
        if (!$art) {
            return response()->json([
                'status' => false,
                'message' => 'Art not found!',
            ], 404);
        }

        $order_data = DB::table('ordered_arts')
            ->where('seller_id', $customer->customer_id)
            ->where('art_id', $art->art_id)
            ->first();

        if (!$order_data) {
            return response()->json([
                'status' => false,
                'message' => 'No ordered items found for this art in the specified order!',
            ], 404);
        }
        $timezone = $customer->timezone ?? 'Asia/Kolkata';
        $currentDateTime = now($timezone);
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        $imageUrl = url($artImage->image);

        DB::beginTransaction();

        try {


            DB::table('ordered_arts')
                ->where('seller_id',  $customer->customer_id)
                ->where('art_id', $order_data->art_id)
                ->update([
                    'art_order_status' => $type,
                    // 'tracking_status'=>''
                    // 'tracking_id' => null,
                    // 'tracking_link' => null
                ]);

            if ($type === 'Declined') {
                DB::table('art')
                    ->where('art_id', $order_data->art_id)
                    ->update([
                        'status' => 'Approved',
                        'buy_date' => null,
                    ]);
                DB::table('ordered_arts')
                    ->where('seller_id',  $customer->customer_id)
                    ->where('art_id', $order_data->art_id)
                    ->update([
                        'art_order_status' => $type,
                        'tracking_id' => null,
                        'tracking_link' => null
                    ]);
            }

            // DB::table('orders')
            //     ->where('order_unique_id', $order_unique_id)
            //     ->update(['order_status' => $type]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Order status changed to ' . $type,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Failed to update order status: ' . $e->getMessage(),
            ], 500);
        }
    }




    public function get_customer_allorder(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required',
            'role' => 'required'
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

        $customerId = $customer->customer_id;

        $customerOrders = DB::table('orders')
            ->where('orders.customer_id', $customerId)
            ->orderBy('order_id', 'desc')
            ->get();

        if ($customerOrders->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No Order Made By Customer.',
            ], 404);
        }

        $result = [];

        foreach ($customerOrders as $order) {
            $addressData = DB::table('customers_delivery_address')
                ->where('customers_delivery_address_id', $order->customer_delivery_address_id)
                ->leftJoin('countries', 'customers_delivery_address.country', '=', 'countries.country_id')
                ->leftJoin('states', 'customers_delivery_address.state', '=', 'states.state_subdivision_id')
                ->select('customers_delivery_address.*', 'countries.country_name', 'states.state_subdivision_name')
                ->first();

            if (!$addressData) {
                $addressData = (object) [
                    'country_name' => null,
                    'state_subdivision_name' => null,
                    'city' => null,
                    'full_name' => null,
                    'mobile' => null,
                    'address' => null,
                    'pincode' => null,
                ];
            }

            $orderData = [
                'order_id' => $order->order_id,
                'order_unique_id' => $order->order_unique_id,
                'order_status' => $order->order_status,
                'customer_id' => $order->customer_id,
                'tracking_id' => $order->tracking_id,
                'tracking_status' => $order->tracking_status,
                'amount' => $order->amount,
                'inserted_date' => $order->inserted_date,
            ];

            $customerAddress = [
                'country_name' => $addressData->country_name,
                'state_subdivision_name' => $addressData->state_subdivision_name,
                'city' => $addressData->city,
                'full_name' => $addressData->full_name,
                'mobile' => $addressData->mobile,
                'address' => $addressData->address,
                'pincode' => $addressData->pincode,
            ];

            $productData = DB::table('ordered_arts')
                ->leftJoin('art', 'ordered_arts.art_id', '=', 'art.art_id')
                ->leftJoin('customers', 'ordered_arts.seller_id', '=', 'customers.customer_id')
                ->select(
                    'ordered_arts.order_id',
                    'ordered_arts.art_id',
                    'ordered_arts.art_order_status',
                    'ordered_arts.price',
                    'ordered_arts.tracking_id',
                    'ordered_arts.tracking_status',
                    'ordered_arts.return_tracking_id',
                    'ordered_arts.return_tracking_status',
                    'ordered_arts.delivered_date',
                    'art.title',
                    'art.art_unique_id',
                    'art.artist_name',
                    'art.edition',
                    'art.art_type',
                    'art.price',
                    'art.frame',
                    'customers.customer_unique_id',
                    'customers.name',
                    'customers.fcm_token',
                )
                ->where('ordered_arts.order_id', $order->order_id)
                ->get();

            // $productDetails = [];
            //     foreach ($productData as $product) {
            //         $productImage = DB::table('art_images')
            //             ->where('art_id', $product->art_id)
            //             ->first();
            //             $isReturn = false;

            //             if ($product->art_order_status == 'Delivered') {
            //                 $validUptoDate = Carbon::today()->addDays(3);

            //                 if (isset($product->delivered_date)) {
            //                     $deliveredDate = Carbon::parse($product->delivered_date);
            //                 } else {
            //                     $deliveredDate = null;
            //                 }

            //                 if ($deliveredDate && $deliveredDate->isSameDay($validUptoDate)) {
            //                     $isReturn = false;
            //                 }
            //             }


            //         $productDetails[] = [
            //             'isReturn'=>$isReturn,
            //             'title' => $product->title,
            //             'art_unique_id' => $product->art_unique_id,
            //             'price' => $product->price,
            //             'art_order_status' => $product->art_order_status,
            //             'tracking_status' => $product->tracking_status,
            //             'images' => $productImage->image ? url($productImage->image) : null,
            //             'frame' => $product->frame,
            //             'edition' => $product->edition,
            //             'artist_name' => $product->artist_name,
            //             'artist_unique_id' => $product->customer_unique_id,
            //         ];
            //     }

            // $productDetails = [];

            // foreach ($productData as $product) {
            //     $productImage = DB::table('art_images')
            //         ->where('art_id', $product->art_id)
            //         ->first();
            //     if ($product->art_order_status == 'Delivered') {
            //         $productImage = DB::table('art_images')
            //             ->where('art_id', $product->art_id)
            //             ->first();

            //         $isReturn = true;

            //         $validUptoDate = Carbon::today()->addDays(3);

            //         if (isset($product->delivered_date)) {
            //             $deliveredDate = Carbon::parse($product->delivered_date);
            //         } else {
            //             $deliveredDate = null;
            //         }

            //         if ($deliveredDate && $deliveredDate->isSameDay($validUptoDate)) {
            //             $isReturn = false;
            //         }

            //         $productDetails[] = [
            //             'isReturn' => $isReturn,
            //             'title' => $product->title,
            //             'art_unique_id' => $product->art_unique_id,
            //             'price' => $product->price,
            //             'art_order_status' => $product->art_order_status,
            //             'tracking_status' => $product->tracking_status,
            //             'images' => $productImage->image ? url($productImage->image) : null,
            //             'frame' => $product->frame,
            //             'edition' => $product->edition,
            //             'artist_name' => $product->artist_name,
            //             'artist_unique_id' => $product->customer_unique_id,
            //         ];
            //     }
            //     $productDetails[] = [
            //         'isReturn' => false,
            //         'title' => $product->title,
            //         'art_unique_id' => $product->art_unique_id,
            //         'price' => $product->price,
            //         'art_order_status' => $product->art_order_status,
            //         'tracking_status' => $product->tracking_status,
            //         'images' => $productImage->image ? url($productImage->image) : null,
            //         'frame' => $product->frame,
            //         'edition' => $product->edition,
            //         'artist_name' => $product->artist_name,
            //         'artist_unique_id' => $product->customer_unique_id,
            //     ];
            // }
            $productDetails = [];

            foreach ($productData as $product) {

                $colorCode = DB::table('status_color')
                    ->where('status_name', $product->art_order_status)
                    ->select('status_color_code')
                    ->first();
                $productImage = DB::table('art_images')
                    ->where('art_id', $product->art_id)
                    ->first();
                // dd($productImage);
                $productImageUrl = $productImage ? url($productImage->image) : null;

                // $seller=Customer::where('customer_id',$product->seller_id)->first();
                if ($product->art_order_status == 'Delivered') {

                    $isReturn = true;
                    $isFeedback = true;

                    if (isset($product->delivered_date)) {
                        $deliveredDate = Carbon::parse($product->delivered_date)->addDays(3);
                    } else {
                        $deliveredDate = null;
                    }
                    if ($deliveredDate && $deliveredDate->isBefore(Carbon::today())) {
                        $isReturn = false;
                        $isFeedback = true;
                    }

                    $productDetails[] = [

                        'colorCode' => $colorCode->status_color_code,
                        'isReturn' => $isReturn,
                        'isFeedback' => $isFeedback,
                        'deliveredDate' => $product->delivered_date,
                        'title' => $product->title,
                        'art_unique_id' => $product->art_unique_id,
                        'price' => $product->price,
                        'art_order_status' => $product->art_order_status,
                        'tracking_status' => $product->tracking_status,
                        'images' => $productImageUrl ?? null,
                        'frame' => $product->frame,
                        'edition' => $product->edition,
                        'artist_name' => $product->artist_name,
                        'artist_unique_id' => $product->customer_unique_id,
                        'artist_fcm_token' => $product->fcm_token,
                    ];
                } else if ($product->art_order_status == 'Return') {
                    // For products that are not 'Delivered', we still need to return the product info
                    $productDetails[] = [
                        'colorCode' => $colorCode->status_color_code,
                        'isReturn' => false,
                        'isFeedback' => false,
                        'title' => $product->title,
                        'art_unique_id' => $product->art_unique_id,
                        'price' => $product->price,
                        'art_order_status' => $product->art_order_status,
                        'tracking_status' => $product->return_tracking_status,
                        'images' => $productImageUrl ?? null,
                        'frame' => $product->frame,
                        'edition' => $product->edition,
                        'artist_name' => $product->artist_name,
                        'artist_unique_id' => $product->customer_unique_id,
                        'artist_fcm_token' => $product->fcm_token,
                    ];
                } else {
                    $productDetails[] = [
                        'colorCode' => $colorCode->status_color_code,
                        'isReturn' => false,
                        'isFeedback' => false,
                        'title' => $product->title,
                        'art_unique_id' => $product->art_unique_id,
                        'price' => $product->price,
                        'art_order_status' => $product->art_order_status,
                        'tracking_status' => $product->tracking_status,
                        'images' => $productImageUrl ?? null,
                        'frame' => $product->frame,
                        'edition' => $product->edition,
                        'artist_name' => $product->artist_name,
                        'artist_unique_id' => $product->customer_unique_id,
                        'artist_fcm_token' => $product->fcm_token,
                    ];
                }
            }





            $result[] = [
                'order_details' => $orderData,
                'art_details' => $productDetails,
            ];
        }

        return response()->json([
            'status' => true,
            'OrderAllData' => $result,
        ]);
    }

    public function get_seller_allorders__(Request $request)
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

        $customer_unique_id = $request->customer_unique_id;

        // Check if the seller exists
        $customer = Customer::where('customer_unique_id', $customer_unique_id)
            ->where('role', 'seller')
            ->first();

        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'Customer not found.',
            ], 404);
        }

        $customerId = $customer->customer_id;

        // Fetch all the orders for the seller
        $productData = DB::table('ordered_arts')
            ->leftJoin('art', 'ordered_arts.art_id', '=', 'art.art_id')
            ->select(
                'ordered_arts.order_id',
                'ordered_arts.art_id',
                'ordered_arts.art_order_status',
                'ordered_arts.price',
                'ordered_arts.tracking_id',
                'ordered_arts.tracking_status',
                'art.title',
                'art.art_unique_id',
                'art.artist_name',
                'art.edition',
                'art.art_type',
                'art.price',
                'art.frame',
            )
            ->where('ordered_arts.seller_id', $customerId)
            ->orderBy('ordered_arts.order_id', 'desc')
            ->get();

        if ($productData->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No orders for the seller.',
            ],);
        }

        $result = [];
        $orders = [];

        // Group product data by order_id
        foreach ($productData as $product) {
            $productImage = DB::table('art_images')
                ->where('art_id', $product->art_id)
                ->first();

            $colorCode = DB::table('status_color')
                ->where('status_name', $product->art_order_status)
                ->select('status_color_code')
                ->first();
            $artDetails = [
                'title' => $product->title,
                'art_unique_id' => $product->art_unique_id,
                'price' => $product->price,
                'art_order_status' => $product->art_order_status,
                'colorCode' => $colorCode->status_color_code,
                'tracking_status' => $product->tracking_status,
                'images' => $productImage && $productImage->image ? url($productImage->image) : null,
                'frame' => $product->frame,
                'edition' => $product->edition,
                'artist_name' => $product->artist_name,
            ];

            // Group the arts by order_id
            if (!isset($orders[$product->order_id])) {
                $orders[$product->order_id] = [
                    'art_details' => [],
                ];
            }

            $orders[$product->order_id]['art_details'][] = $artDetails;
        }

        // Prepare the final result
        foreach ($orders as $order) {
            $result[] = $order;
        }

        return response()->json([
            'status' => true,
            'OrderAllData' => $result,
        ]);
    }
    public function get_seller_allorders(Request $request)
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

        $customer_unique_id = $request->customer_unique_id;

        // Check if the seller exists
        $customer = Customer::where('customer_unique_id', $customer_unique_id)
            ->where('role', 'seller')
            ->first();

        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'Customer not found.',
            ], 404);
        }

        $customerId = $customer->customer_id;

        // Fetch all the orders for the seller
        $productData = DB::table('ordered_arts')
            ->leftJoin('art', 'ordered_arts.art_id', '=', 'art.art_id')
            ->leftJoin('customers', 'ordered_arts.customer_id', '=', 'customers.customer_id')
            ->select(
                'ordered_arts.order_id',
                'ordered_arts.art_id',
                'ordered_arts.art_order_status',
                'ordered_arts.price',
                'ordered_arts.tracking_id',
                'ordered_arts.tracking_status',
                'ordered_arts.return_tracking_id',
                'ordered_arts.return_tracking_status',
                'ordered_arts.delivered_date',
                'art.title',
                'art.art_unique_id',
                'art.artist_name',
                'art.edition',
                'art.art_type',
                'art.price',
                'art.frame',
                'customers.name',
                'customers.customer_unique_id',
                'customers.fcm_token',
            )
            ->where('ordered_arts.seller_id', $customerId)
            ->orderBy('ordered_arts.order_id', 'desc')
            ->get();

        if ($productData->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No orders for the seller.',
            ],);
        }

        $result = [];
        $orders = [];

        foreach ($productData as $product) {
            $isTrack = DB::table('ordered_arts')
                ->where('art_id', $product->art_id)
                ->whereNull('tracking_id')
                ->exists();

            $order_unique_id = DB::table('orders')->where('order_id', $product->order_id)->value('order_unique_id');
            $productImage = DB::table('art_images')
                ->where('art_id', $product->art_id)
                ->first();

            $colorCode = DB::table('status_color')
                ->where('status_name', $product->art_order_status)
                ->select('status_color_code')
                ->first();


            if ($product->art_order_status == 'Delivered') {

                $isReturn = true;
                $isFeedback = true;

                if (isset($product->delivered_date)) {
                    $deliveredDate = Carbon::parse($product->delivered_date)->addDays(3);
                } else {
                    $deliveredDate = null;
                }
                if ($deliveredDate && $deliveredDate->isBefore(Carbon::today())) {
                    $isReturn = false;
                    $isFeedback = true;
                }

                $artDetails = [
                    'order_unique_id' => $order_unique_id,
                    'title' => $product->title,
                    'art_unique_id' => $product->art_unique_id,
                    'price' => $product->price,
                    'art_order_status' => $product->art_order_status,
                    'colorCode' => $colorCode->status_color_code,
                    'tracking_status' => $product->tracking_status,
                    'images' => $productImage && $productImage->image ? url($productImage->image) : null,
                    'frame' => $product->frame,
                    'edition' => $product->edition,
                    'artist_name' => $product->artist_name,
                    'customer_unique_id' => $product->customer_unique_id,
                    'fcm_token' => $product->fcm_token,
                    'isTrack' => $isTrack,
                ];
            } else if ($product->art_order_status == 'Return') {
                $artDetails = [
                    'order_unique_id' => $order_unique_id,
                    'title' => $product->title,
                    'art_unique_id' => $product->art_unique_id,
                    'price' => $product->price,
                    'art_order_status' => $product->art_order_status,
                    'colorCode' => $colorCode->status_color_code,
                    'tracking_status' => $product->return_tracking_status,
                    'images' => $productImage && $productImage->image ? url($productImage->image) : null,
                    'frame' => $product->frame,
                    'edition' => $product->edition,
                    'artist_name' => $product->artist_name,
                    'isTrack' => $isTrack,
                    'customer_unique_id' => $product->customer_unique_id,
                    'fcm_token' => $product->fcm_token,

                ];
            } else {
                $artDetails = [
                    'order_unique_id' => $order_unique_id,
                    'title' => $product->title,
                    'art_unique_id' => $product->art_unique_id,
                    'price' => $product->price,
                    'art_order_status' => $product->art_order_status,
                    'colorCode' => $colorCode->status_color_code,
                    'tracking_status' => $product->tracking_status,
                    'images' => $productImage && $productImage->image ? url($productImage->image) : null,
                    'frame' => $product->frame,
                    'edition' => $product->edition,
                    'artist_name' => $product->artist_name,
                    'isTrack' => $isTrack,
                    'customer_unique_id' => $product->customer_unique_id,
                    'fcm_token' => $product->fcm_token,

                ];
            }


            // Group the arts by order_id
            if (!isset($orders[$product->order_id])) {
                $orders[$product->order_id] = [
                    'art_details' => [],
                ];
            }

            $orders[$product->order_id]['art_details'][] = $artDetails;
        }

        // Prepare the final result
        foreach ($orders as $order) {
            $result[] = $order;
        }

        return response()->json([
            'status' => true,
            'OrderAllData' => $result,
        ]);
    }


    public function seller_order_web(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required',
            'role' => 'required',
            'min_date' => 'nullable|date',
            'max_date' => 'nullable|date',
            'status' => 'nullable|in:Return,Return Pending,Delivered,Confirmed,Pending,Declined'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->where('role', $request->role)->first();
        $seller_id = $customer->customer_id;
        $role = $request->role;

        if (!$seller_id) {
            return response()->json([
                'status' => false,
                'message' => 'seller ID required'
            ]);
        }
        $threeMonthsAgo = Carbon::now()->subMonths(3);


        $orderedArtQuery = DB::table('ordered_arts');

        if ($request->min_date) {
            $orderedArtQuery->where('ordered_arts.inserted_date', '>=', $request->min_date);
        }

        if ($request->max_date) {
            $orderedArtQuery->where('ordered_arts.inserted_date', '<=', $request->max_date);
        }

        if ($request->status) {
            if ($request->status === 'Return') {
                $orderedArtQuery->whereIn('ordered_arts.art_order_status', ['Return', 'Return Pending']);
            } else {
                $orderedArtQuery->where('ordered_arts.art_order_status', $request->status);
            }
            // $orderedArtQuery->where('ordered_arts.art_order_status', $request->status);
        }



        $orderedArts = $orderedArtQuery->where('seller_id', $seller_id)->orderBy('order_id', 'desc')
        ->where('ordered_arts.inserted_date', '>=', $threeMonthsAgo)->get();



        if ($orderedArts->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No orders found for this user.'
            ], 200);
        }

        $products = [];

        foreach ($orderedArts as $orders) {
            $orderID = DB::table('orders')
                ->where('order_id', $orders->order_id)
                ->first();

                if($orders->art_order_status == 'Confirmed'){
                    $isTrack = DB::table('ordered_arts')
                    ->where('art_id', $orders->art_id)
                    ->where('art_order_status','Confirmed')
                    ->whereNotNull('tracking_id')
                    ->exists();
                }elseif($orders->art_order_status == 'Delivered'){
                    $isTrack = DB::table('ordered_arts')
                    ->where('art_id', $orders->art_id)
                    ->where('art_order_status','Delivered')
                    ->whereNotNull('tracking_id')
                    ->exists();
                }elseif (in_array($orders->art_order_status, ['Return', 'Return Pending'])) {
                    $isTrack = DB::table('ordered_arts')
                        ->where('art_id', $orders->art_id)
                        ->whereIn('art_order_status', ['Return', 'Return Pending'])
                        ->whereNotNull('return_tracking_id')
                        ->exists();
                } elseif(in_array($orders->art_order_status, ['Pending', 'Decliend'])) {
                    $isTrack = true;
                }
                 else {
                    $isTrack = false;
                }







            $customer = Customer::where('customer_id', $orderID->customer_id)->first();
            $colorCode = DB::table('status_color')
                ->where('status_name', $orders->art_order_status)
                ->select('status_color_code')
                ->first();
            $data = [];
            $product = DB::table('art')
                ->where('art_id', $orders->art_id)
                ->first();

            $productImage = DB::table('art_images')
                ->where('art_id', $product->art_id)
                ->first();

            if ($product) {
                $products[] = [
                    'isTrack' => $isTrack,
                    'order_id' => $orderID->order_id,
                    'order_unique_id' => $orderID->order_unique_id,
                    'art_order_status' => $orders->art_order_status,
                    'colorCode' => $colorCode->status_color_code,
                    'payment_id' => $orderID->payment_id,
                    'customer_name' => $customer->name,
                    'title' => $product->title,
                    'art_unique_id' => $product->art_unique_id,

                    // 'art_unique_id' => $product->art_unique_id,
                    'art_image' => isset($productImage->image) ? url($productImage->image) : null,
                    'price' => $orders->price,
                    'order_date' => $orderID->inserted_date,
                ];
            }
        }

        return response()->json([
            'status' => true,
            'orders' => $products
        ]);
    }


    public function customer_getting_seller_details__(Request $request)
    {

        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $validator = Validator::make($request->all(), [
            'art_unique_id' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $customer = Auth::guard('customer_api')->user();

        $buyer = Customer::where('customer_unique_id', $customer->customer_unique_id)->first();

        $art = Art::where('art_unique_id', $request->art_unique_id)->first();



        $orderData = DB::table('ordered_arts')->where('art_id', $art->art_id)->first();
        $orders = DB::table('orders')->where('order_id', $orderData->order_id)->first();
        $artImage = DB::table('art_images')->where('art_id', operator: $art->art_id)->first();
        if (!$art) {
            return response()->json([
                'status' => false,
                'message' => 'No Art Found'
            ]);
        }

        $sellerData = Customer::where('customer_id', $art->customer_id)->first();

        $trackingData = DB::table(table: 'ordered_arts')
            ->where('seller_id', $sellerData->customer_id)
            ->where('art_id', $art->art_id)
            ->first();

        $colorCode = DB::table('status_color')
            ->where('status_name', $orderData->art_order_status)
            ->select('status_color_code')
            ->first();

        if ($trackingData->art_order_status == 'Return') {
            $response = [
                'colorCode' => $colorCode->status_color_code,
                'tracking_id' => $trackingData->return_tracking_id,
                'tracking_link' => $trackingData->return_tracking_link,
                'company_name' => $trackingData->return_tracking_link,
                'tracking_status' => $trackingData->return_tracking_status,
                'customer_profile' => isset($sellerData->customer_profile) ? url($sellerData->customer_profile) : null,
                'customer_unique_id' => $sellerData->customer_unique_id,
                'artist_name' => $sellerData->name,
                'mobile' => $sellerData->mobile,
                'art_name' => $art->title,
                'art_order_status' => $orderData->art_order_status,
                'art_unique_id' => $art->art_unique_id,
                'art_image' => isset($artImage->image) ? url($artImage->image) : null,
                'frame' => $art->frame,
                'price' => $art->price,
                'order_id' => $orders->order_id,
                'order_unique_id' => $orders->order_unique_id,
            ];
        }
        $response = [
            'colorCode' => $colorCode->status_color_code,
            'tracking_id' => $trackingData->tracking_id,
            'tracking_link' => $trackingData->tracking_link,
            'company_name' => $trackingData->company_name,
            'tracking_status' => $trackingData->tracking_status,
            'customer_profile' => isset($sellerData->customer_profile) ? url($sellerData->customer_profile) : null,
            'customer_unique_id' => $sellerData->customer_unique_id,
            'artist_name' => $sellerData->name,
            'mobile' => $sellerData->mobile,
            'art_name' => $art->title,
            'art_order_status' => $orderData->art_order_status,
            'art_unique_id' => $art->art_unique_id,
            'art_image' => isset($artImage->image) ? url($artImage->image) : null,
            'frame' => $art->frame,
            'price' => $art->price,
            'order_id' => $orders->order_id,
            'order_unique_id' => $orders->order_unique_id,
        ];

        return response()->json([
            'status' => true,
            'data' => $response
        ]);
    }

    public function customer_getting_seller_details(Request $request)
    {
        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'art_unique_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $customer = Auth::guard('customer_api')->user();
        $buyer = Customer::where('customer_unique_id', $customer->customer_unique_id)->first();
        $art = Art::where('art_unique_id', $request->art_unique_id)->first();

        if (!$art) {
            return response()->json([
                'status' => false,
                'message' => 'No Art Found'
            ]);
        }

        $orderData = DB::table('ordered_arts')->where('art_id', $art->art_id)->first();
        $orders = DB::table('orders')->where('order_id', $orderData->order_id)->first();
        $artImage = DB::table('art_images')->where('art_id', $art->art_id)->first();
        $sellerData = Customer::where('customer_id', $art->customer_id)->first();

        $trackingData = DB::table('ordered_arts')
            ->where('seller_id', $sellerData->customer_id)
            ->where('art_id', $art->art_id)
            ->first();

        $colorCode = DB::table('status_color')
            ->where('status_name', $orderData->art_order_status)
            ->select('status_color_code')
            ->first();

        $response = [
            'colorCode' => $colorCode->status_color_code,
            'tracking_id' => $trackingData->tracking_id,
            'tracking_link' => $trackingData->tracking_link,
            'company_name' => $trackingData->company_name,
            'tracking_status' => $trackingData->tracking_status,
            'customer_profile' => isset($sellerData->customer_profile) ? url($sellerData->customer_profile) : null,
            'customer_unique_id' => $sellerData->customer_unique_id,
            'artist_name' => $sellerData->name,
            'mobile' => $sellerData->mobile,
            'art_name' => $art->title,
            'art_order_status' => $orderData->art_order_status,
            'art_unique_id' => $art->art_unique_id,
            'art_image' => isset($artImage->image) ? url($artImage->image) : null,
            'frame' => $art->frame,
            'price' => $art->price,
            'order_id' => $orders->order_id,
            'order_unique_id' => $orders->order_unique_id,
        ];

        if ($orderData->art_order_status == 'Return') {
            $response['tracking_id'] = $trackingData->return_tracking_id;
            $response['tracking_link'] = $trackingData->return_tracking_link;
            $response['company_name'] = $trackingData->return_tracking_link;
            $response['tracking_status'] = $trackingData->return_tracking_status;
        }

        return response()->json([
            'status' => true,
            'data' => $response
        ]);
    }
    public function seller_getting_customer__details(Request $request)
    {

        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $validator = Validator::make($request->all(), [
            'art_unique_id' => 'required',

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
                'message' => 'No Art Found'
            ]);
        }
        $orderedArt = DB::table('ordered_arts')->where('art_id', $art->art_id)->first();
        if (!$orderedArt) {
            return response()->json([
                'status' => false,
                'message' => 'No Art Found'
            ]);
        }

        $orderData = DB::table('orders')->where('order_id', $orderedArt->order_id)->first();

        $customerData = Customer::where('customer_id', $orderData->customer_id)->first();

        $trackingData = DB::table('ordered_arts')
            ->where('art_id', $art->art_id)
            ->first();

        $addressData = DB::table('customers_delivery_address')
            ->where('customers_delivery_address_id', $orderData->customer_delivery_address_id)
            ->leftJoin('countries', 'customers_delivery_address.country', '=', 'countries.country_id')
            ->leftJoin('states', 'customers_delivery_address.state', '=', 'states.state_subdivision_id')
            ->leftJoin('cities', 'customers_delivery_address.city', '=', 'cities.cities_id')
            ->select('customers_delivery_address.*', 'countries.country_name', 'states.state_subdivision_name', 'cities.name_of_city')
            ->first();


        $response = [
            'addressData' => $addressData,
            'customer_profile' => isset($customerData->customer_profile) ? url($customerData->customer_profile) : null,
            'customer_unique_id' => $customerData->customer_unique_id,
            'customer_name' => $customerData->name,
            'customer_email' => $customerData->email,
            'mobile' => $customerData->mobile,
        ];

        $trackdetail = [
            'tracking_id' => $trackingData->tracking_id,
            'tracking_link' => $trackingData->tracking_link,
            'company_name' => $trackingData->company_name,
        ];

        $data = [
            'customerdetail' => $response,
            'trackdetail' => $trackdetail
        ];


        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    // public function get_search_suggestion($query)
    // {
    //     $query = str_replace('-', ' ', $query);
    //     if (empty($query)) {
    //         return response()->json([]);
    //     }

    //     $productData = DB::table('art')
    //         ->leftJoin('category', 'art.category_id', '=', 'category.category_id')
    //         ->leftJoin('customers', 'art.customer_id', '=', 'customers.customer_id')
    //         ->where(function ($queryBuilder) use ($query) {
    //             $queryBuilder->where('art.title', 'LIKE',  $query . '%');
    //                 // ->orWhere('art.artist_name', 'LIKE',  $query . '%');
    //         })
    //         ->select(
    //             'art.title',
    //             'art.art_unique_id',
    //             'art.art_id',
    //         )
    //         ->where('art.status', 'Approved')
    //         ->where('art.art_type', operator: 'Online')
    //         ->orWhere('art.art_type', operator: 'Private')
    //         ->groupBy(
    //             'art.title',
    //             'art.art_unique_id',
    //             'art.art_id',

    //         )
    //         ->orderBy('art.title', 'asc')
    //         ->limit(10)
    //         ->get();

    //     $result = [];
    //     foreach ($productData as $product) {
    //         $limitedProductName = implode(' ', array_slice(explode(' ', $product->title), 0, 4));

    //         $result[] = [
    //             'title' => $limitedProductName,
    //             'art_id' => $product->art_id,
    //             'art__unique_id' => $product->art_unique_id,
    //         ];
    //     }

    //     return response()->json($result);
    // }

    public function get_search_suggestion__($query)
    {
        $query = str_replace('-', ' ', $query);

        if (empty($query)) {
            return response()->json([]);
        }

        $productData = DB::table('art')
            ->leftJoin('category', 'art.category_id', '=', 'category.category_id')
            ->leftJoin('customers', 'art.customer_id', '=', 'customers.customer_id')
            ->where('art.title', 'LIKE', $query . '%')
            // Search for the title starting with the query
            ->where('art.status', 'Approved')  // Ensure only 'Approved' artworks are fetched
            ->whereIn('art.art_type', ['Online', 'Private']) // Handle both 'Online' and 'Private' art types
            ->select(
                'art.title',
                'art.art_unique_id',
                'art.art_id'
            )
            ->groupBy(
                'art.title',
                'art.art_unique_id',
                'art.art_id'
            )
            ->orderBy('art.title', 'asc')
            ->limit(10)
            ->get();

        $result = [];
        foreach ($productData as $product) {
            // Limit the product name to 4 words
            $limitedProductName = implode(' ', array_slice(explode(' ', $product->title), 0, 4));

            $result[] = [
                'title' => $limitedProductName,
                'art_id' => $product->art_id,
                'art_unique_id' => $product->art_unique_id, // Fixed typo in the key name
            ];
        }

        return response()->json($result);
    }


    public function get_search_suggestion($query)
    {
        $query = str_replace('-', ' ', $query);

        if (empty($query)) {
            return response()->json([]);
        }

        $productData = DB::table('art')
            ->leftJoin('category', 'art.category_id', '=', 'category.category_id')
            ->leftJoin('customers', 'art.customer_id', '=', 'customers.customer_id')
            ->where(function ($queryBuilder) use ($query) {
                $queryBuilder->where('art.title', 'LIKE', '%' . $query . '%')
                    ->orWhere('art.artist_name', 'LIKE', '%' . $query . '%')
                    ->orWhere('category.category_name', 'LIKE', '%' . $query . '%');
            })
            ->where('art.status', 'Approved')
            ->whereIn('art.art_type', ['Online', 'Private'])
            ->select(
                'art.title',
                'art.art_unique_id',
                'art.art_id',
                'art.artist_name',
                'category.category_name',
                'category.category_id',
                'customers.customer_unique_id'
            )
            ->groupBy(
                'art.title',
                'art.art_unique_id',
                'art.art_id',
                'art.artist_name',
                'category.category_name',
                'category.category_id',
                'customers.customer_unique_id'
            )
            ->orderBy('art.title', 'asc')
            ->limit(10)
            ->get();

        $result = [];
        foreach ($productData as $product) {
            if (stripos($product->artist_name, $query) !== false) {
                $result[] = [
                    'artist_name' => $product->artist_name,
                    'artist_unique_id' => $product->customer_unique_id,
                ];
            } elseif (stripos($product->category_name, $query) !== false) {
                // Ensure unique categories
                $isExistingCategory = false;
                foreach ($result as $item) {
                    if (isset($item['category_name']) && $item['category_name'] == $product->category_name) {
                        $isExistingCategory = true;
                        break;
                    }
                }

                if (!$isExistingCategory) {
                    $result[] = [
                        'category_name' => $product->category_name,
                        'category_id' => $product->category_id,
                    ];
                }
            } else {
                // Limit product title to 4 words if it matches the query
                $limitedProductName = implode(' ', array_slice(explode(' ', $product->title), 0, 4));
                $result[] = [
                    'title' => $limitedProductName,
                    'art_id' => $product->art_id,
                    'art_unique_id' => $product->art_unique_id,
                ];
            }
        }

        return response()->json($result);
    }




    public function get_search_art_data__(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'search' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }
        $searchTerm = $request->search;
        $searchTerm = str_replace('_', ' ', $searchTerm);

        if (empty($searchTerm)) {
            return response()->json([]);
        }

        $artData = DB::table('art')
            ->leftJoin('category', 'art.category_id', '=', 'category.category_id')
            ->where(function ($query) use ($searchTerm) {
                $query->where('art.title', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('art.artist_name', 'LIKE', '%' . $searchTerm . '%');
            })
            ->where('art.status', 'Approved')
            ->where('art.art_type', 'Online')
            ->orderBy('art.art_id', 'desc')
            ->select('art.*', 'category.category_name', 'category.category_id')
            ->get();

        if ($artData->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No art Found'
            ]);
        }

        $artIds = $artData->pluck('art_id');
        $artImages = DB::table('art_images')
            ->whereIn('art_id', $artIds)
            ->get()
            ->keyBy('art_id');
        $mainArtData = [];

        foreach ($artData as $art) {
            $image = isset($artImages[$art->art_id]) ? url($artImages[$art->art_id]->image) : null;

            $mainArtData[$art->art_id] = [
                'art_id' => $art->art_id,
                'art_unique_id' => $art->art_unique_id,
                'title' => $art->title,
                'artist_name' => $art->artist_name,
                'image' => $image,
                'category_name' => $art->category_name,
                'category_id' => $art->category_id,
                'price' => $art->price,
            ];
        }

        if (count($mainArtData) < 4) {
            $relatedArtData = [];

            foreach ($mainArtData as $art) {
                $relatedArt = DB::table('art')
                    ->leftJoin('category', 'art.category_id', '=', 'category.category_id')
                    ->where('art.status', 'Approved')
                    ->where('art.art_type', 'Online')
                    ->where('art.category_id', $art['category_id'])
                    ->select('art.*', 'category.category_name', 'category.category_id')
                    ->get();

                $relatedArtIds = $relatedArt->pluck('art_id');
                $relatedArtImages = DB::table('art_images')
                    ->whereIn('art_id', $relatedArtIds)
                    ->get()
                    ->keyBy('art_id');

                foreach ($relatedArt as $related) {
                    if (!isset($relatedArtData[$related->art_id])) {
                        $image = isset($relatedArtImages[$related->art_id]) ? url($relatedArtImages[$related->art_id]->image) : null;

                        $relatedArtData[$related->art_id] = [
                            'art_id' => $related->art_id,
                            'art_unique_id' => $related->art_unique_id,
                            'title' => $related->title,
                            'artist_name' => $related->artist_name,
                            'image' => $image,
                            'category_name' => $related->category_name,
                            'category_id' => $related->category_id,
                            'price' => $related->price,
                        ];
                    }
                }
            }

            return response()->json([
                'status' => true,
                'MainproductsAllData' => array_values($relatedArtData),
            ]);
        }

        return response()->json([
            'status' => true,
            'MainproductsAllData' => array_values($mainArtData),
        ]);
    }

    public function get_search_art_data(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'search' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $searchTerm = $request->search;
        $searchTerm = str_replace('_', ' ', $searchTerm);

        if (empty($searchTerm)) {
            return response()->json([]);
        }

        // Query to fetch the art based on title, artist name, or category name
        $artData = DB::table('art')
            ->leftJoin('category', 'art.category_id', '=', 'category.category_id')
            ->leftJoin('customers', 'art.customer_id', '=', 'customers.customer_id')
            ->where(function ($query) use ($searchTerm) {
                $query->where('art.title', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('art.artist_name', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('category.category_name', 'LIKE', '%' . $searchTerm . '%');
            })
            ->where('art.status', 'Approved')
            ->whereIn('art.art_type', ['Online', 'Private'])
            ->orderBy('art.art_id', 'desc')
            ->select('art.*', 'category.category_name', 'category.category_id', 'customers.customer_unique_id')
            ->get();

        if ($artData->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No art found'
            ]);
        }

        // Extract art ids and fetch art images
        $artIds = $artData->pluck('art_id');
        $artImages = DB::table('art_images')
            ->whereIn('art_id', $artIds)
            ->get()
            ->keyBy('art_id');

        $mainArtData = [];
        foreach ($artData as $art) {
            $image = isset($artImages[$art->art_id]) ? url($artImages[$art->art_id]->image) : null;

            $mainArtData[$art->art_id] = [
                'art_id' => $art->art_id,
                'art_unique_id' => $art->art_unique_id,
                'title' => $art->title,
                'artist_name' => $art->artist_name,
                'image' => $image,
                'category_name' => $art->category_name,
                'category_id' => $art->category_id,
                'price' => $art->price,
                'artist_unique_id' => $art->customer_unique_id, // Adding artist unique ID
            ];
        }


        if (count($mainArtData) < 4) {
            $relatedArtData = [];
            foreach ($mainArtData as $art) {
                $relatedArt = DB::table('art')
                    ->leftJoin('category', 'art.category_id', '=', 'category.category_id')
                    ->where('art.status', 'Approved')
                    ->whereIn('art.art_type', ['Online', 'Private'])
                    ->where('art.category_id', $art['category_id'])
                    ->select('art.*', 'category.category_name', 'category.category_id')
                    ->get();

                $relatedArtIds = $relatedArt->pluck('art_id');
                $relatedArtImages = DB::table('art_images')
                    ->whereIn('art_id', $relatedArtIds)
                    ->get()
                    ->keyBy('art_id');

                foreach ($relatedArt as $related) {
                    if (!isset($relatedArtData[$related->art_id])) {
                        $image = isset($relatedArtImages[$related->art_id]) ? url($relatedArtImages[$related->art_id]->image) : null;

                        $relatedArtData[$related->art_id] = [
                            'art_id' => $related->art_id,
                            'art_unique_id' => $related->art_unique_id,
                            'title' => $related->title,
                            'artist_name' => $related->artist_name,
                            'image' => $image,
                            'category_name' => $related->category_name,
                            'category_id' => $related->category_id,
                            'price' => $related->price,
                        ];
                    }
                }
            }

            return response()->json([
                'status' => true,
                'MainproductsAllData' => array_values($relatedArtData),
            ]);
        }

        return response()->json([
            'status' => true,
            'MainproductsAllData' => array_values($mainArtData),
        ]);
    }


    public function get_notification_data(Request $request)
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

        $customer_unique_id = $request->customer_unique_id;

        $customer = Customer::where('customer_unique_id', $customer_unique_id)->first();

        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'Customer not found.',
            ], 404);
        }

        $customerId = $customer->customer_id;

        $data = DB::table('notification')
            ->where('customer_id', $customer->customer_id)
            ->orderBy('inserted_date', 'desc')
            ->orderBy('inserted_time', 'desc')
            ->limit(10)
            ->get();


        if ($data->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No Data Found'
            ]);
        }
        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    public function add_volunteers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'volunteers_name' => 'required',
            'volunteers_image' => 'required',
            'fb_link' => 'required',
            'insta_link' => 'required',
            'twitter_link' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }
        $currentDateTime = Carbon::now();
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        if ($request->hasFile('volunteers_image')) {
            $file = $request->file('volunteers_image');
            $fileName = time() . '_' . Str::random(5) . '.' . $file->getClientOriginalExtension();
            $filePath = 'volunteers_image/' . $fileName;
            $file->move(public_path('volunteers_image/'), $fileName);
        }
        $data = [
            'volunteers_name' => $request->input('volunteers_name'),
            'fb_link' => $request->input('fb_link'),
            'insta_link' => $request->input('insta_link'),
            'twitter_link' => $request->input('twitter_link'),
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
            'status' => 'Active',
            'volunteers_image' => $filePath ?? null,
        ];

        DB::table('volunteers')->insert($data);

        return response()->json([
            'status' => true,
            'message' => 'volunteers add successfully'
        ]);
    }

    public function seller_update_status(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'art_unique_id' => 'required',
            'tracking_status' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }
        $art = Art::where('art_unique_id', $request->art_unique_id)->first();
        $artImage = ArtImage::where('art_id', $art->art_id)->first();

        $orderArt = DB::table('ordered_arts')->where('art_id', $art->art_id)->where('art_order_status', 'Confirmed')->first();
        if (!$orderArt) {
            return response()->json([
                'status' => false,
                'message' => 'No confirmed order found for this art',
            ]);
        }
        $updateData = [
            'tracking_status' => $request->tracking_status
        ];

        $currentDateTime = Carbon::now();
        $insertDate = $currentDateTime->toDateString();
        if ($request->tracking_status === 'Delivered') {
            $updateData['art_order_status'] = 'Delivered';
            $updateData['delivered_date'] = $insertDate;
        }

        $updateResult = DB::table('ordered_arts')
            ->where('art_id', $art->art_id)
            ->update($updateData);

        if ($updateResult) {
            if (!empty($request->fcm_token)) {
                try {
                    $firebase = (new Factory)->withServiceAccount(base_path('mira-monet-firebase-adminsdk-fbsvc-864230166e.json'));
                    $messaging = $firebase->createMessaging();
                    $fcm_token = $request->fcm_token;

                    $messageData = CloudMessage::withTarget('token', $fcm_token)
                        ->withNotification([
                            'title' => 'Your Artwork  ' . $art->title  . 'Is Being Crafted!',
                            'body' => 'Thank you for your order! Your Order tracking status is ' . $request->tracking_status,
                            'image' => isset($artImage->image) ? url($artImage->image) : null,
                        ]);

                    $messaging->send($messageData);
                } catch (\Exception $e) {
                    \Log::error("FCM Notification Error: " . $e->getMessage());
                }
            }
            return response()->json([
                'status' => true,
                'message' => 'Ordered Art Tracking status successfully updated to ' . $request->tracking_status,
            ]);
        }
        return response()->json([
            'status' => false,
            'message' => 'Failed to update Ordered Art status',
        ]);
    }

    public function tracking_status(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'type' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }


        $data = DB::table('tracking_status')
            ->where('status', 'Active')
            ->where('type', $request->type)
            ->select('tracking_status')->get();
        if ($data->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No Data Found'
            ]);
        }
        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    // not
    public function add_subscriber(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'email' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        $email = $request->email;

        if (!$email) {
            return response()->json([
                'status' => false,
                'message' => 'Email Id Required!'
            ]);
        }

        $existingSubscriber = DB::table('subscriber')
            ->where('email', $email)
            ->first();
        if ($existingSubscriber) {
            return response()->json([
                'status' => false,
                'message' => 'Alredy Subscribed!'
            ]);
        }

        $data = [
            'email' => $email,
            'status' => 'Active',
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ];


        DB::table('subscriber')
            ->insert($data);

            $mailData = [
                'email' => $email,

            ];
            try {
                Mail::send('emails.thank_you_subscription', ['mailData' => $mailData], function ($message) use ($request) {
                    $message->to($request->email)
                        ->subject('Thank You for Subscribing')
                        ->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
                        ->replyTo(env('MAIL_REPLY_TO_ADDRESS', env('MAIL_FROM_ADDRESS')));
                });
            } catch (\Exception $e) {
                \Log::error('Mail sending failed: ' . $e->getMessage());
                // Optionally, return a response or handle the failure gracefully
            }

        return response()->json([
            'status' => true,
            'message' => 'Subscriber Added Successfully!'
        ]);
    }
    // notend
    public function art_enquiry_chat(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required',
            'artist_unique_id' => 'required',
            'art_unique_id' => 'required',
            'name' => 'required',
            'email' => 'required',
            'message' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }


        $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();
        $seller = Customer::where('customer_unique_id', $request->artist_unique_id)->first();
        $art = Art::where('art_unique_id', $request->art_unique_id)->first();
        $timezone = $customer->timezone ?? 'Asia/Kolkata';
        $currentDateTime = now($timezone);
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();
        // dd($art);

        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'No Customer Found'
            ]);
        }
        if (!$seller) {
            return response()->json([
                'status' => false,
                'message' => 'No Artist Found'
            ]);
        }
        if (!$art) {
            return response()->json([
                'status' => false,
                'message' => 'No Art Found'
            ]);
        }

        $chatData = [
            'customer_id' => $customer->customer_id,
            'seller_id' => $seller->customer_id,
            'art_id' => $art->art_id,
            'name' => $request->name,
            'email' => $request->email,
            'status' => 'Active',
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ];

        $existingChat = DB::table('art_enquiry_chat')->where('art_id', $art->art_id)
            ->where('customer_id', $customer->customer_id)->first();

        if ($existingChat) {
            return response()->json([
                'status' => false,
                'message' => 'Your Enquiry Alredy Exists',
                'art_enquiry_chat_id' => $existingChat->art_enquiry_chat_id,

            ]);
        }

        $artImage = ArtImage::where('art_id', $art->art_id)->first();

        $chatDataId =  DB::table('art_enquiry_chat')->insertGetId($chatData);

        $messageDataId = DB::table('art_enquiry_chat_message')->insertGetId([
            'sender_id' => $customer->customer_id,
            'receiver_id' => $seller->customer_id,
            'art_enquiry_chat_id' => $chatDataId,
            'message' => $request->message,
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ]);

        if (!empty($request->artist_fcm_token)) {
            try {
                $firebase = (new Factory)->withServiceAccount(base_path('mira-monet-firebase-adminsdk-fbsvc-864230166e.json'));
                $messaging = $firebase->createMessaging();
                $fcm_token = $request->artist_fcm_token;

                $messageData = CloudMessage::withTarget('token', $fcm_token)
                    ->withNotification([
                        'title' => $art->title . ' Enquiry',
                        'body' => $request->message,
                        'image' => isset($artImage->image) ? url($artImage->image) : null,
                    ]);

                $messaging->send($messageData);
            } catch (\Exception $e) {
                \Log::error("FCM Notification Error: " . $e->getMessage());
            }
        }

        $messageData = DB::table('art_enquiry_chat_message')->where('art_enquiry_chat_message_id', $messageDataId)->first();

        $notif = DB::table('notification')
            ->insert([
                'title' => $art->title . 'Inquiry',
                'body' =>  $request->message,
                'image' => url($artImage->image),
                'customer_id' => $seller->customer_id
            ]);

        // $mailData = [
        //     'name' => $customer->name,
        //     'message' => $request->message,
        // ];

        // Mail::send('emails.art_enquiry', ['mailData' => $mailData], function ($message) use ($request) {
        //     $message->to($request->email)
        //         ->subject('Art Inquiry')
        //         ->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
        //         ->replyTo(env('MAIL_REPLY_TO_ADDRESS', env('MAIL_FROM_ADDRESS')));
        // });

        $artistmailData = [
            'customer_name' => $customer->name,
            'name' => $seller->name,
            'message' => $request->message,
        ];
        try {
            Mail::send('emails.artist_art_enquiry', ['artistmailData' => $artistmailData], function ($message) use ($seller) {
                $message->to($seller->email)
                    ->subject('Art Inquiry')
                    ->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
                    ->replyTo(env('MAIL_REPLY_TO_ADDRESS', env('MAIL_FROM_ADDRESS')));
            });
        } catch (\Exception $e) {
            \Log::error('Mail sending failed: ' . $e->getMessage());
            // Optionally, return a response or handle the failure gracefully
        }

        return response()->json([
            'status' => true,
            'art_title' => $art->title,
            'art_enquiry_chat_id' => $chatDataId,
            'art_image' => isset($artImage->image) ? url($artImage->image) : null,
            'message' => $messageData->message,
            'reciver_unique_id' => $request->artist_unique_id,
        ]);
    }

    public function sendMessage(Request $request)
    {

        // if (!Auth::guard('customer_api')->check()) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Unauthorized access.',
        //     ], 400);
        // }
        $validator = Validator::make($request->all(), [
            'art_enquiry_chat_id' => 'required|exists:art_enquiry_chat,art_enquiry_chat_id',
            'message' => 'nullable|string',
            'sender_unique_id' => 'required|string',
            'receiver_unique_id' => 'required|string',
            'image' => 'nullable|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 200);
        }

        $sender = Customer::where('customer_unique_id', $request->sender_unique_id)->first();
        $chatInitiate = ArtEnquiryChat::where('art_enquiry_chat_id', $request->art_enquiry_chat_id)->first();
        $receiverData = Customer::where('customer_unique_id', $request->receiver_unique_id)->first();

        $filePath = null;

        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $file = $request->file('image');

            $fileName = time() . '.' . $file->getClientOriginalExtension();

            $file->move(public_path('artEnquiry/image'), $fileName);

            $filePath = 'artEnquiry/image/' . $fileName;
        }

        // dd($filePath);

        $timezone = $sender->timezone ?? 'Asia/Kolkata';
        $currentDateTime = now($timezone);

        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();


        $message = ArtEnquiryChatMessage::create([
            'art_enquiry_chat_id' => $request->art_enquiry_chat_id,
            'sender_id' => $sender->customer_id,
            'receiver_id' => $receiverData->customer_id,
            'message' => $request->message,
            'images' => $filePath,
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ]);

        $data = [
            'art_enquiry_chat_id' => $request->art_enquiry_chat_id,
            'sender_id' => $sender->customer_id,
            'sender_unique_id' => $request->sender_unique_id,
            'receiver_id' => $receiverData->customer_id,
            'receiver_unique_id' => $request->receiver_unique_id,
            'message' => $request->message,
            'images' => url($filePath),
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ];

        // $firebase = (new Factory)->withServiceAccount(base_path('artist-3dee9-firebase-adminsdk-3kcvz-0a708fe673.json'));
        // $messaging = $firebase->createMessaging();
        // $fcm_token = $request->reciver_fcm_token;
        // $messageData = CloudMessage::withTarget('token', $fcm_token)
        //     ->withNotification([
        //         'title' => $sender->name,
        //         'body' =>  $request->message,
        //         'image' => url($filePath) ?? null
        //     ]);
        // $messaging->send($messageData);

        if (!empty($request->reciver_fcm_token)) {
            try {
                $firebase = (new Factory)->withServiceAccount(base_path('mira-monet-firebase-adminsdk-fbsvc-864230166e.json'));
                $messaging = $firebase->createMessaging();
                $fcm_token = $request->reciver_fcm_token;
                $imageUrl = !empty($filePath) ? (string) $filePath : null;

                $messageData = CloudMessage::withTarget('token', $fcm_token)
                    ->withNotification([
                        'title' => $sender->name,
                        'body' =>  $request->message??'sent a file',
                        'image' => $imageUrl ?? null
                    ]);

                $messaging->send($messageData);
            } catch (\Exception $e) {
                // Log Firebase errors but do not return an error response
                \Log::error('FCM Error: ' . $e->getMessage());
            }
        }

        return response()->json([
            'status' => true,
            'message' => $data
        ]);
    }

    // public function get_customer_all_art_enquiry__(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'customer_unique_id' => 'required',

    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => $validator->errors()->first(),
    //         ]);
    //     }

    //     $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();


    //     $chats = ArtEnquiryChat::with(['ArtEnquiryChatMessage' => function ($query) {
    //         $query->select('art_enquiry_chat_message_id', 'art_enquiry_chat_id', 'message', 'images', 'status', 'inserted_date', 'inserted_time')
    //             ->orderByDesc('inserted_date')
    //             ->orderByDesc('inserted_time');
    //     }])
    //         ->Where('customer_id', $customer->customer_id)
    //         ->orderByDesc('inserted_date')
    //         ->orderByDesc('inserted_time')
    //         ->get();

    //     $ArtData = [];
    //     foreach ($chats as $chat) {
    //         // Get the other participant in the chat

    //         $art = Art::where('art_id', $chat->art_id)->first();

    //         $artImage = ArtImage::where('art_id', $art->art_id)->first();
    //         $seller = Customer::where('customer_id', $chat->seller_id)->first();

    //         $lastMessagae = DB::table('art_enquiry_chat_message')
    //             ->where('art_enquiry_chat_id', $chat->art_enquiry_chat_id)
    //             ->orderBy('inserted_date', 'desc')
    //             ->orderBy('inserted_time', 'desc')
    //             ->first();
    //         $ArtData[] = [
    //             'art_unique_id' => $art->art_unique_id,
    //             'title' => $art->title,
    //             'image' => isset($artImage->image) ? url($artImage->image) : null,
    //             'reciver_unique_id' => $seller->customer_unique_id,
    //             'art_enquiry_chat_id' => $chat->art_enquiry_chat_id,
    //             'last_message' => $lastMessagae,
    //         ];
    //     }
    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Chat users fetched successfully.',
    //         'art_enuqiry_list' => $ArtData,
    //     ]);
    // }

    public function get_customer_all_art_enquiry(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();

        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'Customer not found.',
            ]);
        }

        // $chats = ArtEnquiryChat::with(['ArtEnquiryChatMessage' => function ($query) {
        //     $query->select('art_enquiry_chat_message_id', 'art_enquiry_chat_id', 'message', 'images', 'status', 'inserted_date', 'inserted_time')
        //         ->orderByDesc('inserted_date')
        //         ->orderByDesc('inserted_time');
        // }])
        //     ->Where('customer_id', $customer->customer_id)
        //     ->orderByDesc('inserted_date')
        //     ->orderByDesc('inserted_time')
        //     ->get();


        $Artchats = ArtEnquiryChat::with([
            'ArtEnquiryChatMessage' => function ($query) {
                $query->select(
                    'art_enquiry_chat_message_id',
                    'art_enquiry_chat_id',
                    'message',
                    'images',
                    'status',
                    'inserted_date',
                    'inserted_time'
                )
                    ->orderByDesc('inserted_date')
                    ->orderByDesc('inserted_time');
            }
        ])
            ->where('customer_id', $customer->customer_id)
            ->addSelect([
                'latest_message_date' => ArtEnquiryChatMessage::select('inserted_date')
                    ->whereColumn('art_enquiry_chat_id', 'art_enquiry_chat.art_enquiry_chat_id')
                    ->orderByDesc('inserted_date')
                    ->orderByDesc('inserted_time')
                    ->limit(1),

                'latest_message_time' => ArtEnquiryChatMessage::select('inserted_time')
                    ->whereColumn('art_enquiry_chat_id', 'art_enquiry_chat.art_enquiry_chat_id')
                    ->orderByDesc('inserted_date')
                    ->orderByDesc('inserted_time')
                    ->limit(1)
            ])
            ->orderByDesc('latest_message_date')
            ->orderByDesc('latest_message_time')
            ->get();


        $ArtData = [];
        foreach ($Artchats as $chat) {
            // Get the other participant in the chat

            $art = Art::where('art_id', $chat->art_id)->first();

            $lastMessagae = DB::table('art_enquiry_chat_message')
                ->where('art_enquiry_chat_id', $chat->art_enquiry_chat_id)
                ->orderByDesc('inserted_date')
                ->orderByDesc('inserted_time')
                ->first();
            $artImage = ArtImage::where('art_id', $art->art_id)->first();
            $seller = Customer::where('customer_id', $chat->seller_id)->first();
            $ArtData[] = [
                'art_unique_id' => $art->art_unique_id,
                'title' => $art->title,
                'art_type' => $art->art_type,
                'image' => isset($artImage->image) ? url($artImage->image) : null,
                'reciver_unique_id' => $seller->customer_unique_id,
                'art_enquiry_chat_id' => $chat->art_enquiry_chat_id,
                'last_message' => $lastMessagae,
            ];
        }

        return response()->json([
            'status' => true,
            'message' => 'Chat users fetched successfully.',
            'art_enquiry_list' => $ArtData,
        ]);
    }


    public function get_single_enquiry_chat(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'art_enquiry_chat_id' => 'required|exists:art_enquiry_chat,art_enquiry_chat_id',
            'reciver_unique_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $chatInitiate = ArtEnquiryChat::with('ArtEnquiryChatMessage')
            ->where('art_enquiry_chat_id', $request->art_enquiry_chat_id)
            ->first();

        if (!$chatInitiate) {
            return response()->json([
                'status' => false,
                'message' => 'Chat not initiated or not found.',
            ], 404);
        }

        $art = Art::where('art_id', $chatInitiate->art_id)->first();
        $artImage = ArtImage::where('art_id', $chatInitiate->art_id)->first();

        $artData = [
            'title' => $art->title,
            'image' => isset($artImage->image) ? url($artImage->image) : null,
        ];

        $customer_with_chat = Customer::where('customer_unique_id', $request->reciver_unique_id)->first();


        $reciver_data = [
            'reciver_unique_id' => $customer_with_chat->customer_unique_id,
            'reciver_profile_image' => isset($customer_with_chat->customer_profile_image) ? url($customer_with_chat->customer_profile_image) : null,
            'reciver_name' => $customer_with_chat->full_name,
            'reciver_email' => $customer_with_chat->email,
            'reciver_mobile' => $customer_with_chat->mobile,
            'reciver_fcm_token' => $customer_with_chat->fcm_token,
            'reciver_latitude' => $customer_with_chat->latitude,
            'reciver_longitude' => $customer_with_chat->longitude,
            'reciver_role' => $customer_with_chat->role,

        ];

        $chatDetails = $chatInitiate->ArtEnquiryChatMessage->map(function ($message) {
            $sender_unique_id = Customer::where('customer_id', $message->sender_id)->value('customer_unique_id');
            // dd($sender_unique_id);
            $receiver_unique_id = Customer::where('customer_id', $message->receiver_id)->value('customer_unique_id');
            $reciver_fcm_token = Customer::where('customer_id', $message->receiver_id)->value('fcm_token');




            return [
                'sender_id' => $message->sender_id,
                'receiver_id' => $message->receiver_id,
                'sender_unique_id' => $sender_unique_id,
                'receiver_unique_id' => $receiver_unique_id,
                'reciver_fcm_token' => $reciver_fcm_token,
                'inserted_date' => $message->inserted_date,
                'inserted_time' => $message->inserted_time,
                'message' => $message->message ?? null,
                'image' => $message->images ? url($message->images) : null,
            ];
        });

        return response()->json([
            'status' => true,
            'art_enquiry_chat_id' => $request->art_enquiry_chat_id,
            'chatDetails' => $chatDetails,
            'artData' => $artData,
        ]);
    }





    public function get_enquiry_category(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        $data = DB::table('enquiry_category')
            ->where('role', $request->role)
            ->get();

        if ($data->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No Data Found'
            ]);
        }
        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }
    public function seller_getting_return_order(Request $request) {}

    public function get_exhibition_date(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'exhibition_unique_id' => 'required|exists:exhibitions,exhibition_unique_id',
            'type' => 'required|in:visitor,private,auction',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        $type = $request->type;
        $allowColumn = '';

        switch ($type) {
            case 'visitor':
                $allowColumn = 'visitor_allow';
                break;
            case 'private':
                $allowColumn = 'private_visitor_allow';
                break;
            case 'auction':
                $allowColumn = 'auction_visitor_allow';
                break;
            default:
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid type provided',
                ]);
        }
        $exhibition = DB::table('exhibitions')->where('exhibition_unique_id', $request->exhibition_unique_id)->first();

        if (!$exhibition) {
            return response()->json([
                'status' => false,
                'message' => 'No Exhibition Found'
            ]);
        }

        $data = DB::table('exhibition_time_slot')
            ->where('exhibition_id', $exhibition->exhibition_id)
            ->where($allowColumn, 'Yes')
            ->where('date', '>=', Carbon::today()->toDateString())
            ->select('date')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();
        // ->map(function ($item) {
        //     $item->date = Carbon::parse($item->date)->format('m/d/Y');
        //     return $item;
        // });

        if ($data->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No Data Found',
            ]);
        }

        return response()->json([
            'status' => true,
            'data' => $data,
        ]);
    }

    public function get_exhibition_slot(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'exhibition_unique_id' => 'required',
            'date' => 'required',
            'type' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        $type = $request->type;
        $allowColumn = '';

        switch ($type) {
            case 'visitor':
                $allowColumn = 'visitor_price';
                break;
            case 'private':
                $allowColumn = 'private_visitor_price';
                break;
            case 'auction':
                $allowColumn = 'auction_visitor_price';
                break;
            default:
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid type provided',
                ]);
        }

        $exhibition = DB::table('exhibitions')->where('exhibition_unique_id', $request->exhibition_unique_id)->first();
        $price=$exhibition->amount;
        if (!$exhibition) {
            return response()->json([
                'status' => false,
                'message' => 'No Exhibition Found'
            ]);
        }
        $data = DB::table('exhibition_time_slot')
            ->where('exhibition_id', $exhibition->exhibition_id)
            ->where('date', $request->date)
            ->where($allowColumn, '!=', null)
            // ->select('slot_name', 'exhibition_time_slot_id', $allowColumn)
            ->select(
                'slot_name',
                'exhibition_time_slot_id',
                $allowColumn,
                DB::raw("CASE WHEN $allowColumn IS NULL OR $allowColumn = 0 THEN false ELSE true END AS isPaid")
            )
            ->groupBy('slot_name', 'exhibition_time_slot_id', $allowColumn)
            ->get();

        $news = [];

        foreach ($data as $value) {
            $news[] = [
                'slot_name' => $value->slot_name,
                'exhibition_time_slot_id' => $value->exhibition_time_slot_id,
              'amount' => $value->{$allowColumn},
            //   'amount' => $value->{$allowColumn} + $exhibition->amount,

                'isPaid' => $value->isPaid,
            ];
        }




        if ($data->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No Data Found'
            ]);
        }


        return response()->json([
            'status' => true,
            'data' => $news
        ]);
    }


    public function private_enquiry_chat(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required',
            'art_unique_id' => 'required',
            'artist_unique_id' => 'required',
            'name' => 'required',
            'email' => 'required',
            'mobile' => 'required',
            'message' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();
        $seller = Customer::where('customer_unique_id', $request->artist_unique_id)->first();
        $user = User::where('user_unique_id', '987654321')->first();
        $art = Art::where('art_unique_id', $request->art_unique_id)->first();


        $timezone = $customer->timezone ?? 'Asia/Kolkata';
        $currentDateTime = now($timezone);
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        // dd($art);

        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'No Customer Found'
            ]);
        }
        if (!$seller) {
            return response()->json([
                'status' => false,
                'message' => 'No Artist Found'
            ]);
        }
        if (!$art) {
            return response()->json([
                'status' => false,
                'message' => 'No Art Found'
            ]);
        }

        $chatData = [
            'customer_id' => $customer->customer_id,
            'seller_id' => $seller->customer_id,
            'art_id' => $art->art_id,
            'role' => $customer->role,
            'user_id' => $user->user_id,
            'name' => $request->name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'status' => 'Active',
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ];

        $existingChat = DB::table('private_enquiry_chat')->where('art_id', $art->art_id)->where('customer_id',  $customer->customer_id)->first();

        if ($existingChat) {
            return response()->json([
                'status' => false,
                'message' => 'Your Enquiry Alredy Exists',
                'private_enquiry_chat_id' => $existingChat->private_enquiry_chat_id,

            ]);
        }

        $artImage = ArtImage::where('art_id', $art->art_id)->first();

        $chatDataId =  DB::table('private_enquiry_chat')->insertGetId($chatData);

        $messageDataId = DB::table('private_enquiry_chat_message')->insertGetId([
            'sender_id' => $customer->customer_id,
            'receiver_id' => $user->user_id,
            'role' => $customer->role,
            'private_enquiry_chat_id' => $chatDataId,
            'message' => $request->message,
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ]);

        $messageData = DB::table('private_enquiry_chat_message')->where('private_enquiry_chat_message_id', $messageDataId)->first();
        if (!empty($request->artist_fcm_token)) {
            try {
                $firebase = (new Factory)->withServiceAccount(base_path('mira-monet-firebase-adminsdk-fbsvc-864230166e.json'));
                $messaging = $firebase->createMessaging();
                $fcm_token = $user->fcm_token;

                $messageData = CloudMessage::withTarget('token', $fcm_token)
                    ->withNotification([
                        'title' => $art->title . 'Private Inquiry',
                        'body' => $request->message,
                        'image' => isset($artImage->image) ? url($artImage->image) : null,
                    ]);

                $messaging->send($messageData);
            } catch (\Exception $e) {
                \Log::error("FCM Notification Error: " . $e->getMessage());
            }
        }

        $notif = DB::table(table: 'admin_notification')
            ->insert([
                'title' => $art->title . 'Private Inquiry',
                'body' => $request->message,
                'image' => isset($artImage->image) ? url($artImage->image) : null,
                'user_id' => '1',
                'inserted_date' => $insertDate,
                'inserted_time' => $insertTime,
            ]);

        $admin = DB::table('users')
            ->where('user_id', '1')
            ->first();


        $adminMailData = [
            'admin_name' => $admin->name,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'message' => $request->message,
        ];
        try {
            Mail::send('emails.private_art_enquiry', ['adminMailData' => $adminMailData], function ($message) use ($admin) {
                $message->to($admin->admin_mail)
                    ->subject('Private Art Inquiry')
                    ->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
                    ->replyTo(env('MAIL_REPLY_TO_ADDRESS', env('MAIL_FROM_ADDRESS')));
            });
        } catch (\Exception $e) {
            \Log::error('Mail sending failed: ' . $e->getMessage());
            // Optionally, return a response or handle the failure gracefully
        }

        return response()->json([
            'status' => true,
            'art_title' => $art->title,
            'private_enquiry_chat_id' => $chatDataId,
            'art_image' => isset($artImage->image) ? url($artImage->image) : null,
            'message' => $messageData->message,
            'reciver_unique_id' => $user->user_unique_id,
        ]);
    }


    public function get_single_private_enquiry_chat(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'private_enquiry_chat_id' => 'required|exists:private_enquiry_chat,private_enquiry_chat_id',
            'reciver_unique_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $chatInitiate = PrivateEnquiryChat::with('PrivateEnquiryChatMessage')
            ->where('private_enquiry_chat_id', $request->private_enquiry_chat_id)
            ->first();

        if (!$chatInitiate) {
            return response()->json([
                'status' => false,
                'message' => 'Chat not initiated or not found.',
            ], 404);
        }

        $art = Art::where('art_id', $chatInitiate->art_id)->first();
        $artImage = ArtImage::where('art_id', $chatInitiate->art_id)->first();


        $customer_with_chat = User::where('user_unique_id', $request->reciver_unique_id)->first();
        $isSold=DB::table('private_ordered_art')
        ->where('art_id',$chatInitiate->art_id)
        ->where('customer_id',$chatInitiate->customer_id)
        ->first();
        $isSell=false;
        if($isSold){
            $isSell=true;
        }
        $artData = [
            'title' => $art->title,
            'art_unique_id' => $art->art_unique_id,
            'image' => isset($artImage->image) ? url($artImage->image) : null,
            'isSell' => $isSell,
        ];


        $reciver_data = [
            'reciver_unique_id' => $customer_with_chat->customer_unique_id,
            'reciver_profile_image' => isset($customer_with_chat->customer_profile_image) ? url($customer_with_chat->customer_profile_image) : null,
            'reciver_name' => $customer_with_chat->full_name,
            'reciver_email' => $customer_with_chat->email,
            'reciver_mobile' => $customer_with_chat->mobile,
            'reciver_fcm_token' => $customer_with_chat->fcm_token,
            'reciver_latitude' => $customer_with_chat->latitude,
            'reciver_longitude' => $customer_with_chat->longitude,
            'reciver_role' => $customer_with_chat->role,

        ];

        $chatDetails = $chatInitiate->PrivateEnquiryChatMessage->map(function ($message) {

            if ($message->role == 'customer') {

                $sender_unique_id = Customer::where('customer_id', $message->sender_id)->value('customer_unique_id');
                $receiver_unique_id = User::where('user_id', $message->receiver_id)->value('user_unique_id');
            } else {
                $sender_unique_id = User::where('user_id', $message->sender_id)->value('user_unique_id');
                $receiver_unique_id = Customer::where('customer_id', $message->receiver_id)->value('customer_unique_id');
            }
            // dd($sender_unique_id);
            // $reciver_fcm_token = Customer::where('customer_id', $message->receiver_id)->value('fcm_token');

            // $sender_unique_id = Customer::where('customer_id', $message->sender_id)->value('customer_unique_id');
            // $receiver_unique_id = User::where('user_id', $message->receiver_id)->value('user_unique_id');


            // if ($message->sender_id != $message->receiver_id) {
            //     $sender_unique_id = User::where('user_id', $message->sender_id)->value('user_unique_id');
            //     $receiver_unique_id = Customer::where('customer_id', $message->receiver_id)->value('customer_unique_id');


            // }

            return [
                'sender_id' => $message->sender_id,
                'receiver_id' => $message->receiver_id,
                'sender_unique_id' => $sender_unique_id,
                'receiver_unique_id' => $receiver_unique_id,
                // 'reciver_fcm_token' => $reciver_fcm_token,
                'inserted_date' => $message->inserted_date,
                'inserted_time' => $message->inserted_time,
                'message' => $message->message ?? null,
                'image' => $message->images ? url($message->images) : null,
            ];
        });

        return response()->json([
            'status' => true,
            'art_enquiry_chat_id' => $request->art_enquiry_chat_id,
            'chatDetails' => $chatDetails,
            'artData' => $artData,

        ]);
    }

    public function get_customer_all_private_art_enquiry(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();


        $chats = PrivateEnquiryChat::with(['PrivateEnquiryChatMessage' => function ($query) {
            $query->select('private_enquiry_chat_message_id', 'private_enquiry_chat_id', 'message', 'images', 'status', 'inserted_date', 'inserted_time')
                ->orderByDesc('inserted_date')
                ->orderByDesc('inserted_time');
        }])
            ->Where('customer_id', $customer->customer_id)
            ->addSelect([
                'latest_message_date' => PrivateEnquiryChatMessage::select('inserted_date')
                    ->whereColumn('private_enquiry_chat_id', 'private_enquiry_chat.private_enquiry_chat_id')
                    ->orderByDesc('inserted_date')
                    ->orderByDesc('inserted_time')
                    ->limit(1),

                'latest_message_time' => PrivateEnquiryChatMessage::select('inserted_time')
                    ->whereColumn('private_enquiry_chat_id', 'private_enquiry_chat.private_enquiry_chat_id')
                    ->orderByDesc('inserted_date')
                    ->orderByDesc('inserted_time')
                    ->limit(1)
            ])
            ->orderByDesc('latest_message_date')
            ->orderByDesc('latest_message_time')

            ->get();

        $ArtData = [];
        foreach ($chats as $chat) {

            $art = Art::where('art_id', $chat->art_id)->first();
            $lastMessagae = DB::table('private_enquiry_chat_message')
                ->where('private_enquiry_chat_id', $chat->private_enquiry_chat_id)
                ->orderBy('inserted_date', 'desc')
                ->orderBy('inserted_time', 'desc')
                ->first();
            $artImage = ArtImage::where('art_id', $art->art_id)->first();
            $user = User::where('user_id', $chat->user_id)->first();
            $ArtData[] = [
                'art_unique_id' => $art->art_unique_id,
                'title' => $art->title,
                'image' => isset($artImage->image) ? url($artImage->image) : null,
                'reciver_unique_id' => $user->user_unique_id,
                'private_enquiry_chat_id' => $chat->private_enquiry_chat_id,
                'last_message' => $lastMessagae,
            ];
        }
        return response()->json([
            'status' => true,
            'message' => 'Chat users fetched successfully.',
            'private_enuqiry_list' => $ArtData,
        ]);
    }

    public function sendPrivateMessage(Request $request)
    {

        // if (!Auth::guard('customer_api')->check()) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Unauthorized access.',
        //     ], 400);
        // }

        $validator = Validator::make($request->all(), [
            'private_enquiry_chat_id' => 'required|exists:private_enquiry_chat,private_enquiry_chat_id',
            'message' => 'nullable|string',
            'sender_unique_id' => 'required|string',
            'receiver_unique_id' => 'required|string',
            // 'images' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
            'images' => 'nullable|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $sender = Customer::where('customer_unique_id', $request->sender_unique_id)->first();
        $chatInitiate = PrivateEnquiryChat::where('private_enquiry_chat_id', $request->private_enquiry_chat_id)->first();
        $receiverData = User::where('user_unique_id', $request->receiver_unique_id)->first();


        $filePath = null;

        if ($request->hasFile('images') && $request->file('images')->isValid()) {
            $file = $request->file('images');

            $fileName = time() . '.' . $file->getClientOriginalExtension();

            $file->move(public_path('privateArtEnquiry/images'), $fileName);

            $filePath = 'privateArtEnquiry/images/' . $fileName;
        }


        $timezone = $sender->timezone ?? 'Asia/Kolkata';
        $currentDateTime = now($timezone);

        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();


        $message = PrivateEnquiryChatMessage::create([
            'private_enquiry_chat_id' => $request->private_enquiry_chat_id,
            'sender_id' => $sender->customer_id,
            'receiver_id' => $receiverData->user_id,
            'message' => $request->message,
            'role' => $sender->role,
            'images' => $filePath,
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ]);

        $data = [
            'private_enquiry_chat_id' => $request->private_enquiry_chat_id,
            'sender_id' => $sender->customer_id,
            'sender_unique_id' => $request->sender_unique_id,
            'receiver_id' => $receiverData->user_id,
            'receiver_unique_id' => $request->receiver_unique_id,
            'message' => $request->message,
            'image' => url($filePath),
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ];
        if (!empty($request->reciver_fcm_token)) {
            try {
                $firebase = (new Factory)->withServiceAccount(base_path('mira-monet-firebase-adminsdk-fbsvc-864230166e.json'));
                $messaging = $firebase->createMessaging();
                $fcm_token = $request->reciver_fcm_token;

                $messageData = CloudMessage::withTarget('token', $fcm_token)
                    ->withNotification([
                        'title' => $sender->name,
                        'body' => $request->message,
                        'image' => isset($filePath) ? url($filePath) : null,
                    ]);

                $messaging->send($messageData);
            } catch (\Exception $e) {
                \Log::error("FCM Notification Error: " . $e->getMessage());
            }
        }


        return response()->json([
            'status' => true,
            'message' => $data
        ]);
    }

    public function help_center_chat(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required',
            'name' => 'required',
            'email' => 'required',
            'mobile' => 'required',
            'art_unique_id' => 'nullable',
            'enquiry_category_id' => 'required',
            'issue' => 'required',
            'images' => 'required|array',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }





        $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();
        $user = User::where('user_unique_id', '987654321')->first();
        $art = Art::where('art_unique_id', $request->art_unique_id)->first();

        // dd($art);
        $timezone = $customer->timezone ?? 'Asia/Kolkata';
        $currentDateTime = now($timezone);
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();


        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'No Customer Found'
            ]);
        }
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'No User Found'
            ]);
        }
        if (!$art) {
            return response()->json([
                'status' => false,
                'message' => 'No Art Found'
            ]);
        }

        $chatData = [
            'customer_id' => $customer->customer_id,
            'art_id' => $art->art_id,
            'user_id' => $user->user_id,
            'full_name' => $request->name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'enquiry_category_id' => $request->enquiry_category_id,
            'issue' => $request->issue,
            'role' => $customer->role,
            'status' => 'Active',
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ];

        $existingChat = DB::table('help_center_chat')
        ->where('art_id', $art->art_id)
        ->where('customer_id',  $customer->customer_id)
        ->where('enquiry_category_id', $request->enquiry_category_id)
        ->first();

        if ($existingChat) {
            table:
            return response()->json([
                'status' => false,
                'message' => 'Your Enquiry Alredy Exists',
                'help_center_chat_id' => $existingChat->help_center_chat_id,

            ]);
        }

        $enquiryCategory = DB::table('enquiry_category')->where('enquiry_category_id', $request->enquiry_category_id)->select('enquiry_category_name')->first();

        $artImage = ArtImage::where('art_id', $art->art_id)->first();
        // $seller = Customer::where('customer_id', $art->art_id)->first();

        $chatDataId =  DB::table('help_center_chat')->insertGetId($chatData);

        $existingImages = [];
        $uploadedImages = [];

        foreach ($request->file('images') as $image) {
            if (!$image->isValid()) {
                continue;
            }

            $fileName = uniqid('art_', true) . '.' . $image->getClientOriginalExtension();
            $filePath = 'helpCenter/image/' . $fileName;

            if (in_array($filePath, $existingImages)) {
                continue;
            }

            $image->move(public_path('helpCenter/image'), $fileName);

            $uploadedImages[] = [
                'help_center_chat_id' => $chatDataId,
                'image' => $filePath
            ];

            $existingImages[] = $filePath;
        }
        if (!empty($uploadedImages)) {
            DB::table('help_center_images')->insert($uploadedImages);
        }

        $issueImages = DB::table('help_center_images')
            ->where('help_center_chat_id', $chatDataId)
            ->get()
            ->map(function ($issueImage) {
                $issueImage->image = isset($issueImage->image) ? url($issueImage->image) : null;
                return $issueImage;
            });
        if (!empty($user->fcm_token)) {
            try {
                $firebase = (new Factory)->withServiceAccount(base_path('mira-monet-firebase-adminsdk-fbsvc-864230166e.json'));
                $messaging = $firebase->createMessaging();
                $fcm_token = $user->fcm_token;

                $messageData = CloudMessage::withTarget('token', $fcm_token)
                    ->withNotification([
                        'title' => $enquiryCategory->enquiry_category_name .  'Help  Enquiry',
                        'body' =>  $request->issue,
                        'image' => isset($artImage->image) ? url($artImage->image) : null,
                    ]);

                $messaging->send($messageData);
                $notif = DB::table(table: 'admin_notification')
                    ->insert([
                        'title' => $enquiryCategory->enquiry_category_name .  'Help  Enquiry',
                        'body' =>  $request->issue,
                        'image' => isset($artImage->image) ? url($artImage->image) : null,
                        'user_id' => '1',
                        'inserted_date' => $insertDate,
                        'inserted_time' => $insertTime,
                    ]);
            } catch (\Exception $e) {
                \Log::error("FCM Notification Error: " . $e->getMessage());
            }
        }

        $admin = DB::table('users')
            ->where('user_id', '1')
            ->first();


        $adminMailData = [
            'admin_name' => $admin->name,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'message' => $request->message,
            'enquiryCategory' => $enquiryCategory->enquiry_category_name
        ];

        try {
            Mail::send('emails.help_enquiey', ['adminMailData' => $adminMailData], function ($message) use ($admin) {
                $message->to($admin->admin_mail)
                    ->subject('Art Inquiry')
                    ->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'));
                // ->replyTo(env('MAIL_REPLY_TO_ADDRESS', env('MAIL_FROM_ADDRESS')));
            });
        } catch (\Exception $e) {
            \Log::error('Mail sending failed: ' . $e->getMessage());
            // Optionally, return a response or handle the failure gracefully
        }


        // $issueimages=DB::table('help_center_images')->where('help_center_image_id',$help_center_image_id)->select('help_center_chat_id','image')->get();
        return response()->json([
            'status' => true,
            'message' => 'Help Enquiry Raised Successfully',
            'art_title' => $art->title,
            'help_center_chat_id' => $chatDataId,
            'art_image' => isset($artImage->image) ? url($artImage->image) : null,
            'reciver_unique_id' => $user->user_unique_id,
            'images' => $issueImages,
            'enquiryCategory' => $enquiryCategory->enquiry_category_name
        ]);
    }


    public function get_customer_all_help_enquiry(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        // Find the customer
        $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();

        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'Customer not found.',
            ]);
        }


        $chats = HelpCenterChat::with([
            'EnquiryCategory' => function ($query) {
                $query->select('enquiry_category_id', 'enquiry_category_name');
            },
            'HelpCenterChatImages' => function ($query) {
                $query->select('help_center_image_id', 'help_center_chat_id', 'image');
            },
            'HelpCenterChatMessage' => function ($query) {
                $query->select('help_center_chat_message_id', 'help_center_chat_id', 'image', 'message', 'inserted_date', 'inserted_time')
                    ->orderByDesc('inserted_date')
                    ->orderByDesc('inserted_time');
            },
        ])
            ->where('customer_id', $customer->customer_id)
            ->orderByDesc('inserted_date')
            ->orderByDesc('inserted_time')
            ->get();

        $ArtData = [];

        foreach ($chats as $chat) {
            $art = Art::where('art_id', $chat->art_id)->first();

            if (!$art) {
                continue;
            }

            $artImage = ArtImage::where('art_id', $art->art_id)->first();
            $user = User::where('user_id', $chat->user_id)->first();


            $lastMessagae = DB::table('help_center_chat_message')
                ->where('help_center_chat_id', $chat->help_center_chat_id)
                ->orderBy('inserted_date', 'desc')
                ->orderBy('inserted_time', 'desc')
                ->first();

            $help_center_chat = DB::table('help_center_chat')
                ->where('help_center_chat_id', $chat->help_center_chat_id)
                ->first();


            $ArtData[] = [
                'art_unique_id' => $art->art_unique_id,
                'title' => $art->title,
                'image' => isset($artImage->image) ? url($artImage->image) : null,
                'reciver_unique_id' => $user->user_unique_id,
                'reciver_fcm_token' => $user->fcm_token,
                'help_center_chat_id' => $chat->help_center_chat_id,
                'enquiry_category_name' => $chat->EnquiryCategory->enquiry_category_name,
                'last_message' => $lastMessagae ?? $help_center_chat,
            ];
        }

        return response()->json([
            'status' => true,
            'message' => 'Chat users fetched successfully.',
            'help_enuqiry_list' => $ArtData,
        ]);
    }

    public function get_single_help_enquiry_chat(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'help_center_chat_id' => 'required|exists:help_center_chat,help_center_chat_id',
            'reciver_unique_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $chatInitiate = HelpCenterChat::with(['HelpCenterChatMessage', 'EnquiryCategory' => function ($query) {
            $query->select('enquiry_category_id', 'enquiry_category_name');
        }])
            ->where('help_center_chat_id', $request->help_center_chat_id)
            ->first();
        $issue = $chatInitiate->issue;

        $enquiryCategoryName = $chatInitiate->EnquiryCategory ? $chatInitiate->EnquiryCategory->enquiry_category_name : null;


        if (!$chatInitiate) {
            return response()->json([
                'status' => false,
                'message' => 'Chat not initiated or not found.',
            ], 404);
        }

        $art = Art::where('art_id', $chatInitiate->art_id)->first();
        $artImage = ArtImage::where('art_id', $chatInitiate->art_id)->first();

        $artData = [
            'title' => $art->title,
            'image' => isset($artImage->image) ? url($artImage->image) : null,
        ];

        $customer_with_chat = User::where('user_unique_id', $request->reciver_unique_id)->first();


        $reciver_data = [
            'reciver_unique_id' => $customer_with_chat->customer_unique_id,
            'reciver_profile_image' => isset($customer_with_chat->customer_profile_image) ? url($customer_with_chat->customer_profile_image) : null,
            'reciver_name' => $customer_with_chat->full_name,
            'reciver_email' => $customer_with_chat->email,
            'reciver_mobile' => $customer_with_chat->mobile,
            'reciver_fcm_token' => $customer_with_chat->fcm_token,
            'reciver_latitude' => $customer_with_chat->latitude,
            'reciver_longitude' => $customer_with_chat->longitude,
            'reciver_role' => $customer_with_chat->role,

        ];
        $issueImages = DB::table('help_center_images')
            ->where('help_center_chat_id', $chatInitiate->help_center_chat_id)
            ->get()
            ->map(function ($issueImage) {
                $issueImage->image = isset($issueImage->image) ? url($issueImage->image) : null;
                return $issueImage;
            });

        $chatDetails = $chatInitiate->HelpCenterChatMessage->map(function ($message) {
            $sender_unique_id = Customer::where('customer_id', $message->sender_id)->value('customer_unique_id');
            $receiver_unique_id = User::where('user_id', $message->receiver_id)->value('user_unique_id');




            return [
                'sender_id' => $message->sender_id,
                'receiver_id' => $message->receiver_id,
                'sender_unique_id' => $sender_unique_id,
                'receiver_unique_id' => $receiver_unique_id,
                // 'reciver_fcm_token' => $reciver_fcm_token,
                'inserted_date' => $message->inserted_date,
                'inserted_time' => $message->inserted_time,
                'message' => $message->message ?? null,
                'image' => $message->image ? url($message->image) : null,
            ];
        });

        return response()->json([
            'status' => true,
            'help_center_chat_id' => $request->help_center_chat_id,
            'chatDetails' => $chatDetails,
            'artData' => $artData,
            'enquiryCategoryName' => $enquiryCategoryName,
            'issue' => $issue,
            'issueImages' => $issueImages,

        ]);
    }

    public function sendHelpMessage(Request $request)
    {

        // if (!Auth::guard('customer_api')->check()) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Unauthorized access.',
        //     ], 400);
        // }

        $validator = Validator::make($request->all(), [
            'help_center_chat_id' => 'required|exists:help_center_chat,help_center_chat_id',
            'message' => 'nullable|string',
            'sender_unique_id' => 'required|string',
            'receiver_unique_id' => 'required|string',
            'images' => 'nullable|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $sender = Customer::where('customer_unique_id', $request->sender_unique_id)->first();
        $chatInitiate = HelpCenterChat::where('help_center_chat_id', $request->help_center_chat_id)->first();
        $receiverData = User::where('user_unique_id', $request->receiver_unique_id)->first();

        $image = null;
        if ($request->hasFile('images') && $request->file('images')->isValid()) {
            $file = $request->file('images');
            $fileName = time() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('helpCenter/image/'), $fileName);

            $ChatImagePath = 'helpCenter/image/' . $fileName;
        } else {
            $ChatImagePath = null;
        }

        $timezone = $sender->timezone ?? 'Asia/Kolkata';
        $currentDateTime = now($timezone);

        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();


        $message = HelpCenterChatMessage::create([
            'help_center_chat_id' => $request->help_center_chat_id,
            'sender_id' => $sender->customer_id,
            'receiver_id' => $receiverData->user_id,
            'role' => $sender->role,
            'message' => $request->message,
            'image' => $ChatImagePath,
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ]);

        $data = [
            'help_center_chat_id' => $request->help_center_chat_id,
            'sender_id' => $sender->customer_id,
            'sender_unique_id' => $request->sender_unique_id,
            'receiver_id' => $receiverData->user_id,
            'receiver_unique_id' => $request->receiver_unique_id,
            'message' => $request->message,
            'image' => $ChatImagePath ? url($ChatImagePath) : null,
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ];


        return response()->json([
            'status' => true,
            'message' => $data
        ]);
    }


    // public function get_customer_all_enquiry(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'customer_unique_id' => 'required',

    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => $validator->errors()->first(),
    //         ]);
    //     }

    //     $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();


    //     // $Artchats = ArtEnquiryChat::with(['ArtEnquiryChatMessage' => function ($query) {
    //     //     $query->select('art_enquiry_chat_message_id', 'art_enquiry_chat_id', 'message', 'images', 'status', 'inserted_date', 'inserted_time')
    //     //         // ->orderByDesc('inserted_date')
    //     //         ->orderByDesc('inserted_time');
    //     // }])
    //     //     ->Where('customer_id', $customer->customer_id)
    //     //     ->orderByDesc('inserted_date')
    //     //     ->orderBy('inserted_time', 'asc')
    //     //     ->get();

    //     $Artchats = ArtEnquiryChat::with(['ArtEnquiryChatMessage' => function ($query) {
    //         $query->select(
    //             'art_enquiry_chat_message_id',
    //             'art_enquiry_chat_id',
    //             'message',
    //             'images',
    //             'status',
    //             'inserted_date',
    //             'inserted_time'
    //         )
    //         ->orderByDesc('inserted_date')
    //         ->orderByDesc('inserted_time');
    //     }])
    //     ->where('customer_id', $customer->customer_id)
    //     // ->orderByDesc('inserted_date')

    //     ->get();
    //     $ArtData = [];
    //     foreach ($Artchats as $chat) {
    //         // Get the other participant in the chat

    //         $art = Art::where('art_id', $chat->art_id)->first();

    //         $lastMessagae = DB::table('art_enquiry_chat_message')
    //             ->where('art_enquiry_chat_id', $chat->art_enquiry_chat_id)
    //             ->orderByDesc('inserted_date')
    //             ->orderByDesc('inserted_time')
    //             ->first();
    //         $artImage = ArtImage::where('art_id', $art->art_id)->first();
    //         $seller = Customer::where('customer_id', $chat->seller_id)->first();
    //         $ArtData[] = [
    //             'art_unique_id' => $art->art_unique_id,
    //             'title' => $art->title,
    //             'art_type' => $art->art_type,
    //             'image' => isset($artImage->image) ? url($artImage->image) : null,
    //             'reciver_unique_id' => $seller->customer_unique_id,
    //             'art_enquiry_chat_id' => $chat->art_enquiry_chat_id,
    //             'last_message' => $lastMessagae,
    //         ];
    //     }


    //     $Privatechats = PrivateEnquiryChat::with(['PrivateEnquiryChatMessage' => function ($query) {
    //         $query->select('private_enquiry_chat_message_id', 'private_enquiry_chat_id', 'message', 'images', 'status', 'inserted_date', 'inserted_time')
    //             ->orderByDesc('inserted_date')

    //             ->orderBy('inserted_time', 'asc');
    //     }])
    //         ->Where('customer_id', $customer->customer_id)

    //         ->get();

    //     $PrivateArtData = [];
    //     foreach ($Privatechats as $chat) {

    //         $art = Art::where('art_id', $chat->art_id)->first();
    //         $lastMessagae = DB::table('private_enquiry_chat_message')
    //             ->where('private_enquiry_chat_id', $chat->private_enquiry_chat_id)
    //             // ->orderBy('inserted_date', 'desc')
    //             ->orderBy('inserted_time', 'desc')
    //             ->first();
    //         $artImage = ArtImage::where('art_id', $art->art_id)->first();
    //         $user = User::where('user_id', $chat->user_id)->first();
    //         $PrivateArtData[] = [
    //             'art_unique_id' => $art->art_unique_id,
    //             'title' => $art->title,
    //             'art_type' => $art->art_type,
    //             'image' => isset($artImage->image) ? url($artImage->image) : null,
    //             'reciver_unique_id' => $user->user_unique_id,
    //             'private_enquiry_chat_id' => $chat->private_enquiry_chat_id,
    //             'last_message' => $lastMessagae,
    //         ];
    //     }

    //     $data = [
    //         'art_enuqiry_list' => $ArtData,
    //         'private_enuqiry_list' => $PrivateArtData
    //     ];
    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Chat users fetched successfully.',
    //         'data' => $data,
    //     ]);
    // }



    public function return_order(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required',
            'art_unique_id' => 'required',
            'artist_unique_id' => 'required',
            'reason' => 'required',
            'images' => 'required|array',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:2048'

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }







        $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();
        $seller = Customer::where('customer_unique_id', $request->artist_unique_id)->first();
        $user = User::where('user_unique_id', '987654321')->first();
        $art = Art::where('art_unique_id', $request->art_unique_id)->first();

        $timezone = $customer->timezone ?? 'Asia/Kolkata';
        $currentDateTime = now($timezone);
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();
        // dd($art);

        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'No Customer Found'
            ]);
        }
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'No User Found'
            ]);
        }
        if (!$art) {
            return response()->json([
                'status' => false,
                'message' => 'No Art Found'
            ]);
        }

        $retrunData = [
            'customer_id' => $customer->customer_id,
            'art_id' => $art->art_id,
            'seller_id' => $seller->customer_id,
            'reason' => $request->reason,
            'status' => 'Pending',
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ];

        $existingReturn = DB::table('return_order')->where('art_id', $art->art_id)->where('customer_id',  $customer->customer_id)->first();

        if ($existingReturn) {
            table:
            return response()->json([
                'status' => false,
                'message' => 'Your Return Alredy Exists',
                'return_order_id' => $existingReturn->return_order_id,

            ]);
        }


        $artImage = ArtImage::where('art_id', $art->art_id)->first();

        $return_order_id =  DB::table('return_order')->insertGetId($retrunData);

        $orderData = DB::table('ordered_arts')->where('art_id', $art->art_id)->update([
            'art_order_status' => 'Return Pending'
        ]);
        $existingImages = [];
        $uploadedImages = [];

        foreach ($request->file('images') as $image) {
            if (!$image->isValid()) {
                continue;
            }

            $fileName = uniqid('art_', true) . '.' . $image->getClientOriginalExtension();
            $filePath = 'returnOrder/image/' . $fileName;

            if (in_array($filePath, $existingImages)) {
                continue;
            }

            $image->move(public_path('returnOrder/image'), $fileName);

            $uploadedImages[] = [
                'return_order_id' => $return_order_id,
                'image' => $filePath,
                'inserted_date' => $insertDate,
                'inserted_time' => $insertTime,
            ];

            $existingImages[] = $filePath;
        }
        if (!empty($uploadedImages)) {
            DB::table('return_order_images')->insert($uploadedImages);
        }

        $issueImages = DB::table('return_order_images')
            ->where('return_order_id', $return_order_id)
            ->get()
            ->map(function ($issueImage) {
                $issueImage->image = isset($issueImage->image) ? url($issueImage->image) : null;
                return $issueImage;
            });

        if (!empty($request->artist_fcm_token)) {
            try {
                $firebase = (new Factory)->withServiceAccount(base_path('mira-monet-firebase-adminsdk-fbsvc-864230166e.json'));
                $messaging = $firebase->createMessaging();
                $fcm_token = $request->artist_fcm_token;

                $messageData = CloudMessage::withTarget('token', $fcm_token)
                    ->withNotification([
                        'title' => $art->title . 'Return Enquiry',
                        'body' => $request->reason,
                        'image' => isset($artImage->image) ? url($artImage->image) : null,
                    ]);

                $messaging->send($messageData);
            } catch (\Exception $e) {
                \Log::error("FCM Notification Error: " . $e->getMessage());
            }
        }
        $notif = DB::table('notification')
            ->insert([
                'title' => $art->title . ' Return Inquiry',
                'body' =>  $request->reason,
                'image' => url($artImage->image),
                'customer_id' => $seller->customer_id
            ]);
        $artistmailData = [
            'customer_name' => $customer->name,
            'name' => $seller->name,
            'message' => $request->reason,
        ];
        try {
            Mail::send('emails.artist_art_enquiry', ['artistmailData' => $artistmailData], function ($message) use ($seller) {
                $message->to($seller->email)
                    ->subject('Return Inquiry')
                    ->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
                    ->replyTo(env('MAIL_REPLY_TO_ADDRESS', env('MAIL_FROM_ADDRESS')));
            });
        } catch (\Exception $e) {
            \Log::error('Mail sending failed: ' . $e->getMessage());
            // Optionally, return a response or handle the failure gracefully
        }





        $return_order = DB::table('return_order')
            ->where('return_order_id', $return_order_id)
            ->first();

        // $issueimages=DB::table('help_center_images')->where('help_center_image_id',$help_center_image_id)->select('help_center_chat_id','image')->get();
        return response()->json([
            'status' => true,
            'art_title' => $art->title,
            'return_status' => $return_order->status,
            'return_order_id' => $return_order_id,
            'art_image' => isset($artImage->image) ? url($artImage->image) : null,
            'reciver_unique_id' => $seller->customer_unique_id,
            'images' => $issueImages,
            // 'enquiryCategory' => $enquiryCategory->enquiry_category_name
        ]);
    }


    public function get_customer_all_return_enquiry(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        // Find the customer
        $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();

        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'Customer not found.',
            ]);
        }


        $chats = ReturnOrder::with([
            'ReturnOrderImage' => function ($query) {
                $query->select('return_order_images_id', 'return_order_id', 'image');
            },
            'ReturnOrderMessage' => function ($query) {
                $query->select('return_order_message_id', 'return_order_id', 'image', 'message', 'inserted_date', 'inserted_time')
                    ->orderByDesc('inserted_date')
                    ->orderByDesc('inserted_time');
            },
        ])
            ->where('customer_id', $customer->customer_id)
            ->orderByDesc('inserted_date')
            ->orderByDesc('inserted_time')
            ->get();

        $ArtData = [];

        foreach ($chats as $chat) {
            $art = Art::where('art_id', $chat->art_id)->first();

            if (!$art) {
                continue;
            }

            $artImage = ArtImage::where('art_id', $art->art_id)->first();
            $seller = Customer::where('customer_id', $chat->seller_id)->first();

            $returns = ReturnOrder::where('return_order_id', $chat->return_order_id)->first();

            $lastMessagae = DB::table('return_order_message')
                ->where('return_order_id', $chat->return_order_id)
                ->orderBy('inserted_date', 'desc')
                ->orderBy('inserted_time', 'desc')
                ->first();
            $ArtData[] = [
                'art_unique_id' => $art->art_unique_id,
                'title' => $art->title,
                'image' => isset($artImage->image) ? url($artImage->image) : null,
                'reciver_unique_id' => $seller->customer_unique_id,
                'reciver_fcm_token' => $seller->fcm_token ?? null,
                'return_order_id' => $chat->return_order_id,
                'last_message' => $lastMessagae ?? $returns,
            ];
        }

        return response()->json([
            'status' => true,
            'message' => 'Chat users fetched successfully.',
            'return_enuqiry_list' => $ArtData,
        ]);
    }


    public function get_single_return_enquiry_chat(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'return_order_id' => 'required|exists:return_order,return_order_id',
            'reciver_unique_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $chatInitiate = ReturnOrder::with(['ReturnOrderMessage'])
            ->where('return_order_id', $request->return_order_id)
            ->first();




        if (!$chatInitiate) {
            return response()->json([
                'status' => false,
                'message' => 'Chat not initiated or not found.',
            ], 404);
        }

        $art = Art::where('art_id', $chatInitiate->art_id)->first();
        $artImage = ArtImage::where('art_id', $chatInitiate->art_id)->first();

        $artData = [
            'title' => $art->title,
            'image' => isset($artImage->image) ? url($artImage->image) : null,
        ];

        $customer_with_chat = Customer::where('customer_unique_id', operator: $request->reciver_unique_id)->first();


        $reciver_data = [
            'reciver_unique_id' => $customer_with_chat->customer_unique_id,
            'reciver_profile_image' => isset($customer_with_chat->customer_profile_image) ? url($customer_with_chat->customer_profile_image) : null,
            'reciver_name' => $customer_with_chat->full_name,
            'reciver_email' => $customer_with_chat->email,
            'reciver_mobile' => $customer_with_chat->mobile,
            'reciver_fcm_token' => $customer_with_chat->fcm_token,
            'reciver_latitude' => $customer_with_chat->latitude,
            'reciver_longitude' => $customer_with_chat->longitude,
            'reciver_role' => $customer_with_chat->role,

        ];
        $issueImages = DB::table('return_order_images')
            ->where('return_order_id', $chatInitiate->return_order_id)
            ->get()
            ->map(function ($issueImage) {
                $issueImage->image = isset($issueImage->image) ? url($issueImage->image) : null;
                return $issueImage;
            });

        $chatDetails = $chatInitiate->ReturnOrderMessage->map(function ($message) {
            $sender_unique_id = Customer::where('customer_id', $message->sender_id)->value('customer_unique_id');
            $receiver_unique_id = Customer::where('customer_id', $message->receiver_id)->value('customer_unique_id');




            return [
                'sender_id' => $message->sender_id,
                'receiver_id' => $message->receiver_id,
                'sender_unique_id' => $sender_unique_id,
                'receiver_unique_id' => $receiver_unique_id,
                // 'reciver_fcm_token' => $reciver_fcm_token,
                'inserted_date' => $message->inserted_date,
                'inserted_time' => $message->inserted_time,
                'message' => $message->message ?? null,
                'image' => $message->image ? url($message->image) : null,
            ];
        });

        return response()->json([
            'status' => true,
            'return_order_id' => $request->return_order_id,
            'chatDetails' => $chatDetails,
            'artData' => $artData,
            'reason' => $chatInitiate->reason,
            'issueImages' => $issueImages,
            'return_status' => $chatInitiate->status

        ]);
    }


    public function sendReturnMessage(Request $request)
    {

        // if (!Auth::guard('customer_api')->check()) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Unauthorized access.',
        //     ], 400);
        // }
        $validator = Validator::make($request->all(), [
            'return_order_id' => 'required|exists:return_order,return_order_id',
            'message' => 'nullable|string',
            'sender_unique_id' => 'required|string',
            'receiver_unique_id' => 'required|string',
            'image' => 'nullable|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $sender = Customer::where('customer_unique_id', $request->sender_unique_id)->first();
        $chatInitiate = ReturnOrder::where('return_order_id', $request->return_order_id)->first();
        $receiverData = Customer::where('customer_unique_id', $request->receiver_unique_id)->first();

        $filePath = null;

        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $file = $request->file('image');

            $fileName = time() . '.' . $file->getClientOriginalExtension();

            $file->move(public_path('ReturnOrder/image'), $fileName);

            $filePath = 'ReturnOrder/image/' . $fileName;
        }


        $timezone = $sender->timezone ?? 'Asia/Kolkata';
        $currentDateTime = now($timezone);
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();


        $message = ReturnOrderMessage::create([
            'return_order_id' => $request->return_order_id,
            'sender_id' => $sender->customer_id,
            'receiver_id' => $receiverData->customer_id,
            'message' => $request->message,
            'role' => $sender->role,
            'image' => $filePath,
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ]);

        $data = [
            'return_order_id' => $request->return_order_id,
            'sender_id' => $sender->customer_id,
            'sender_unique_id' => $request->sender_unique_id,
            'receiver_id' => $receiverData->customer_id,
            'receiver_unique_id' => $request->receiver_unique_id,
            'message' => $request->message,
            'images' => url($filePath),
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ];
        $notificationData = [
            'title' => 'New Message from ' . $sender->full_name,
            'body' => $request->message,
            'sender_name' => $sender->full_name,
            'receiver_unique_id' => $request->reciver_unique_id,
            'message' => $request->message,
            'sender_unique_id' => $request->sender_unique_id,
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ];
        if (!empty($request->reciver_fcm_token)) {
            try {
                $firebase = (new Factory)->withServiceAccount(base_path('mira-monet-firebase-adminsdk-fbsvc-864230166e.json'));
                $messaging = $firebase->createMessaging();
                $fcm_token = $request->reciver_fcm_token;

                $messageData = CloudMessage::withTarget('token', $fcm_token)
                    ->withNotification([
                        'title' => 'New Message from ' . $sender->name,
                        'body' => $request->message,
                        'image' => isset($filePath) ? url($filePath) : null,
                    ]);

                $messaging->send($messageData);
            } catch (\Exception $e) {
                \Log::error("FCM Notification Error: " . $e->getMessage());
            }
        }

        return response()->json([
            'status' => true,
            'message' => $data
        ]);
    }

    public function artFeedBack(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'art_name' => 'required|string',
            'artist_name' => 'required|string',
            'art_unique_id' => 'required',
            'customer_unique_id' => 'required',
            'rating' => 'required',
            'comment' => 'required',
            'image' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }


        $art = Art::where('art_unique_id', $request->art_unique_id)->first();
        $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();
        $existingArtReview = DB::table('art_feedback')
            ->where('art_id', $art->art_id)
            ->where('customer_id', $customer->customer_id)
            ->first();

        $timezone = $customer->timezone ?? 'Asia/Kolkata';
        $currentDateTime = now($timezone);
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        if ($existingArtReview) {
            DB::table('art_feedback')
                ->where('art_feedback_id', $existingArtReview->art_feedback_id)
                ->update([
                    'art_id' => $art->art_id,
                    'art_name' => $art->title,
                    'customer_id' => $customer->customer_id,
                    'artist_name' => $request->artist_name,
                    'rating' => $request->rating,
                    'comment' => $request->comment,
                    'inserted_date' => $insertDate,
                    'inserted_time' => $insertTime,
                ]);


            return response()->json([
                'status' => true,
                'message' => 'Review updated successfully!',
                'data' => [
                    'art_feedback_id' => $existingArtReview->art_feedback_id,
                    'art_id' => $art->art_id,
                    'customer' => $customer->customer_id,
                    'artist_name' => $request->artist_name,
                    'rating' => $request->rating,
                    'comment' => $request->comment,
                ],
            ]);
        } else {
            $artReviewId = DB::table('art_feedback')
                ->insertGetId([
                    'art_id' => $art->art_id,
                    'customer_id' => $customer->customer_id,
                    'artist_name' => $request->artist_name,
                    'art_name' => $request->art_name,
                    'rating' => $request->rating,
                    'comment' => $request->comment,
                    'inserted_date' => $insertDate,
                    'inserted_time' => $insertTime,
                ]);



            return response()->json([
                'status' => true,
                'message' => 'Review added successfully!',
                'data' => [
                    'art_feedback_id' => $artReviewId,
                    'art_id' => $art->art_id,
                    'customer' => $customer->customer_id,
                    'artist_name' => $request->artist_name,
                    'rating' => $request->rating,
                    'comment' => $request->comment,
                ],
            ]);
        }
    }


    public function get_single_help_enquiry_chat_app(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'help_center_chat_id' => 'required|exists:help_center_chat,help_center_chat_id',
            'reciver_unique_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $chatInitiate = HelpCenterChat::with(['HelpCenterChatMessage', 'EnquiryCategory' => function ($query) {
            $query->select('enquiry_category_id', 'enquiry_category_name');
        }])
            ->where('help_center_chat_id', $request->help_center_chat_id)
            ->first();

        $issue = $chatInitiate->issue;

        $enquiryCategoryName = $chatInitiate->EnquiryCategory ? $chatInitiate->EnquiryCategory->enquiry_category_name : null;


        if (!$chatInitiate) {
            return response()->json([
                'status' => false,
                'message' => 'Chat not initiated or not found.',
            ], 404);
        }

        $art = Art::where('art_id', $chatInitiate->art_id)->first();
        $artImage = ArtImage::where('art_id', $chatInitiate->art_id)->first();

        $artData = [
            'title' => $art->title,
            'image' => isset($artImage->image) ? url($artImage->image) : null,
        ];

        $customer_with_chat = User::where('user_unique_id', $request->reciver_unique_id)->first();


        $reciver_data = [
            'reciver_unique_id' => $customer_with_chat->customer_unique_id,
            'reciver_profile_image' => isset($customer_with_chat->customer_profile_image) ? url($customer_with_chat->customer_profile_image) : null,
            'reciver_name' => $customer_with_chat->full_name,
            'reciver_email' => $customer_with_chat->email,
            'reciver_mobile' => $customer_with_chat->mobile,
            'reciver_fcm_token' => $customer_with_chat->fcm_token,
            'reciver_latitude' => $customer_with_chat->latitude,
            'reciver_longitude' => $customer_with_chat->longitude,
            'reciver_role' => $customer_with_chat->role,

        ];
        $issueImages = DB::table('help_center_images')
            ->where('help_center_chat_id', $chatInitiate->help_center_chat_id)
            ->get()
            ->map(function ($issueImage) {
                $issueImage->image = isset($issueImage->image) ? url($issueImage->image) : null;
                return $issueImage;
            });

        $chatDetails = $chatInitiate->HelpCenterChatMessage->map(function ($message) {
            $sender_unique_id = Customer::where('customer_id', $message->sender_id)->value('customer_unique_id');
            $receiver_unique_id = User::where('user_id', $message->receiver_id)->value('user_unique_id');




            return [
                'sender_id' => $message->sender_id,
                'receiver_id' => $message->receiver_id,
                'sender_unique_id' => $sender_unique_id,
                'receiver_unique_id' => $receiver_unique_id,
                // 'reciver_fcm_token' => $reciver_fcm_token,
                'inserted_date' => $message->inserted_date,
                'inserted_time' => $message->inserted_time,
                'message' => $message->message ?? null,
                'image' => $message->image ? url($message->image) : null,
            ];
        });

        $data = [
            'help_center_chat_id' => $request->help_center_chat_id,
            'chatDetails' => $chatDetails,
            'artData' => $artData,
            'enquiryCategoryName' => $enquiryCategoryName,
            'issue' => $issue,
            'issueImages' => $issueImages,
        ];

        return response()->json([
            'status' => true,
            'data' => $data

        ]);
    }


    public function get_single_return_enquiry_chat_app(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'return_order_id' => 'required|exists:return_order,return_order_id',
            'reciver_unique_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $chatInitiate = ReturnOrder::with(['ReturnOrderMessage'])
            ->where('return_order_id', $request->return_order_id)
            ->first();




        if (!$chatInitiate) {
            return response()->json([
                'status' => false,
                'message' => 'Chat not initiated or not found.',
            ], 404);
        }

        $art = Art::where('art_id', $chatInitiate->art_id)->first();
        $artImage = ArtImage::where('art_id', $chatInitiate->art_id)->first();

        $artData = [
            'title' => $art->title,
            'image' => isset($artImage->image) ? url($artImage->image) : null,
        ];

        $customer_with_chat = Customer::where('customer_unique_id', operator: $request->reciver_unique_id)->first();


        $reciver_data = [
            'reciver_unique_id' => $customer_with_chat->customer_unique_id,
            'reciver_profile_image' => isset($customer_with_chat->customer_profile_image) ? url($customer_with_chat->customer_profile_image) : null,
            'reciver_name' => $customer_with_chat->full_name,
            'reciver_email' => $customer_with_chat->email,
            'reciver_mobile' => $customer_with_chat->mobile,
            'reciver_fcm_token' => $customer_with_chat->fcm_token,
            'reciver_latitude' => $customer_with_chat->latitude,
            'reciver_longitude' => $customer_with_chat->longitude,
            'reciver_role' => $customer_with_chat->role,

        ];
        $issueImages = DB::table('return_order_images')
            ->where('return_order_id', $chatInitiate->return_order_id)
            ->get()
            ->map(function ($issueImage) {
                $issueImage->image = isset($issueImage->image) ? url($issueImage->image) : null;
                return $issueImage;
            });

        $chatDetails = $chatInitiate->ReturnOrderMessage->map(function ($message) {


            $sender_unique_id = Customer::where('customer_id', $message->sender_id)->value('customer_unique_id');
            $receiver_unique_id = Customer::where('customer_id', $message->receiver_id)->value('customer_unique_id');




            return [
                'sender_id' => $message->sender_id,
                'receiver_id' => $message->receiver_id,
                'sender_unique_id' => $sender_unique_id,
                'receiver_unique_id' => $receiver_unique_id,
                // 'reciver_fcm_token' => $reciver_fcm_token,
                'inserted_date' => $message->inserted_date,
                'inserted_time' => $message->inserted_time,
                'message' => $message->message ?? null,
                'image' => $message->image ? url($message->image) : null,
            ];
        });

        $data = [
            'return_order_id' => $request->return_order_id,
            'chatDetails' => $chatDetails,
            'artData' => $artData,
            'reason' => $chatInitiate->reason,
            'issueImages' => $issueImages,
            'return_status' => $chatInitiate->status
        ];

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    public function seller_getting_return_form(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();

        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'Customer not found.',
            ]);
        }


        $chats = ReturnOrder::with([
            'ReturnOrderImage' => function ($query) {
                $query->select('return_order_images_id', 'return_order_id', 'image');
            },
            'ReturnOrderMessage' => function ($query) {
                $query->select('return_order_message_id', 'return_order_id', 'image', 'message', 'inserted_date', 'inserted_time')
                    ->orderByDesc('inserted_date')
                    ->orderByDesc('inserted_time');
            },
        ])
            ->where('seller_id', $customer->customer_id)
            ->orderByDesc('inserted_date')
            ->orderByDesc('inserted_time')
            ->get();

        $ArtData = [];

        foreach ($chats as $chat) {
            $art = Art::where('art_id', $chat->art_id)->first();

            if (!$art) {
                continue;
            }

            $artImage = ArtImage::where('art_id', $art->art_id)->first();
            $customerReturn = Customer::where('customer_id', $chat->customer_id)->first();

            $returns = ReturnOrder::where('return_order_id', $chat->return_order_id)->first();

            $isReturnApproved = true;
            if ($returns->status == 'Pending') {
                $isReturnApproved = false;
            }

            $lastMessagae = DB::table('return_order_message')
                ->where('return_order_id', $chat->return_order_id)
                ->orderBy('inserted_date', 'desc')
                ->orderBy('inserted_time', 'desc')
                ->first();

            //  $lastMessage=

            // $lastMessagae->image = isset($lastMessagae->image) ? url($lastMessagae->image) : null;
            $ArtData[] = [
                'art_unique_id' => $art->art_unique_id,
                'isReturnApproved' => $isReturnApproved,
                'reciver_fcm_token' => $customerReturn->fcm_token,
                'reason' => $returns->reason,
                'title' => $art->title,
                'image' => isset($artImage->image) ? url($artImage->image) : null,
                'reciver_unique_id' => $customerReturn->customer_unique_id,
                'return_order_id' => $chat->return_order_id,
                'last_message' => $lastMessagae ?? $returns,
            ];
        }

        return response()->json([
            'status' => true,
            'message' => 'Chat users fetched successfully.',
            'return_enuqiry_list' => $ArtData,
        ]);
    }


    public function seller_get_single_return_enquiry_chat_app(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'return_order_id' => 'required|exists:return_order,return_order_id',
            'reciver_unique_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $chatInitiate = ReturnOrder::with(['ReturnOrderMessage'])
            ->where('return_order_id', $request->return_order_id)
            ->first();




        if (!$chatInitiate) {
            return response()->json([
                'status' => false,
                'message' => 'Chat not initiated or not found.',
            ], 404);
        }

        $art = Art::where('art_id', $chatInitiate->art_id)->first();
        $artImage = ArtImage::where('art_id', $chatInitiate->art_id)->first();

        $artData = [
            'title' => $art->title,
            'art_unique_id' => $art->art_unique_id,
            'image' => isset($artImage->image) ? url($artImage->image) : null,
        ];

        $customer_with_chat = Customer::where('customer_unique_id', operator: $request->reciver_unique_id)->first();




        $reciver_data = [
            'reciver_unique_id' => $customer_with_chat->customer_unique_id,
            'reciver_profile_image' => isset($customer_with_chat->customer_profile_image) ? url($customer_with_chat->customer_profile_image) : null,
            'reciver_name' => $customer_with_chat->full_name,
            'reciver_email' => $customer_with_chat->email,
            'reciver_mobile' => $customer_with_chat->mobile,
            'reciver_fcm_token' => $customer_with_chat->fcm_token,
            'reciver_latitude' => $customer_with_chat->latitude,
            'reciver_longitude' => $customer_with_chat->longitude,
            'reciver_role' => $customer_with_chat->role,

        ];
        $issueImages = DB::table('return_order_images')
            ->where('return_order_id', $chatInitiate->return_order_id)
            ->get()
            ->map(function ($issueImage) {
                $issueImage->image = isset($issueImage->image) ? url($issueImage->image) : null;
                return $issueImage;
            });

        $returns = ReturnOrder::where('return_order_id', $request->return_order_id)->first();

        $isReturnApproved = true;
        if ($returns->status == 'Pending') {
            $isReturnApproved = false;
        }

        $chatDetails = $chatInitiate->ReturnOrderMessage->map(function ($message) {
            $sender_unique_id = Customer::where('customer_id', $message->sender_id)->value('customer_unique_id');
            $receiver_unique_id = Customer::where('customer_id', $message->receiver_id)->value('customer_unique_id');




            return [
                'sender_id' => $message->sender_id,
                'receiver_id' => $message->receiver_id,
                'sender_unique_id' => $sender_unique_id,
                'receiver_unique_id' => $receiver_unique_id,
                // 'reciver_fcm_token' => $reciver_fcm_token,
                'inserted_date' => $message->inserted_date,
                'inserted_time' => $message->inserted_time,
                'message' => $message->message ?? null,
                'image' => $message->image ? url($message->image) : null,
            ];
        });

        $data = [
            'reciver_fcm_token' => $customer_with_chat->fcm_token,
            'isReturnApproved' => $isReturnApproved,
            'return_order_id' => $request->return_order_id,
            'chatDetails' => $chatDetails,
            'artData' => $artData,
            'reason' => $chatInitiate->reason,
            'issueImages' => $issueImages,
            'return_status' => $chatInitiate->status
        ];

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }
    public function seller_confirm_return_enquiry(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'return_order_id' => 'required|exists:return_order,return_order_id',
            'customer_unique_id' => 'required|exists:customers,customer_unique_id',
            'return_tracking_id' => 'required|string',
            'return_tracking_link' => 'required|url',
            'return_company_name' => 'required|string',
            // 'customer_fcm_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $seller = DB::table('customers')
            ->where('customer_unique_id', $request->customer_unique_id)
            ->first();

        $returnData = ReturnOrder::where('return_order_id', $request->return_order_id)->first();
        $art = Art::where('art_id', $returnData->art_id)->first();
        $artImage = ArtImage::where('art_id', $returnData->art_id)->first();

        if (!$returnData) {
            return response()->json([
                'status' => false,
                'message' => 'No Return Order found'
            ]);
        }


        if (!empty($request->customer_fcm_token)) {
            try {
                $firebase = (new Factory)->withServiceAccount(base_path('mira-monet-firebase-adminsdk-fbsvc-864230166e.json'));
                $messaging = $firebase->createMessaging();
                $fcm_token = $request->customer_fcm_token;

                $messageData = CloudMessage::withTarget('token', $fcm_token)
                    ->withNotification([
                        'title' => $art->title,
                        'body' => 'Your  Return Request for Art is Approved by the Artist',
                        'image' => isset($artImage->image) ? url($artImage->image) : null
                    ]);

                $messaging->send($messageData);
            } catch (\Exception $e) {
                \Log::error("FCM Notification Error: " . $e->getMessage());
            }
        }

        // $firebase = (new Factory)->withServiceAccount(base_path('artist-3dee9-firebase-adminsdk-3kcvz-0a708fe673.json'));
        // $messaging = $firebase->createMessaging();
        // $fcm_token = $request->customer_fcm_token;
        // $messageData = CloudMessage::withTarget('token', $fcm_token)
        //     ->withNotification([
        //         'title' => $art->title,
        //         'body' => 'Your  Return Request for Art is Approved by the Artist',
        //         'image' => isset($artImage->image) ? url($artImage->image) : null
        //     ]);

        // $messaging->send($messageData);



        $returnOrderUpdated = ReturnOrder::where('return_order_id', $request->return_order_id)
            ->where('seller_id', $seller->customer_id)
            ->update([
                'status' => 'Approved'
            ]);

        if (!$returnOrderUpdated) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update return order status.',
            ]);
        }


        $orderDataUpdated = DB::table('ordered_arts')
            ->where('art_id', $returnData->art_id)
            ->where('seller_id', $seller->customer_id)
            ->update([
                'return_tracking_id' => $request->return_tracking_id,
                'return_tracking_link' => $request->return_tracking_link,
                'return_company_name' => $request->return_company_name,
                'return_tracking_status' => 'Return-Accepted',
                'art_order_status' => 'Return',
            ]);

        if (!$orderDataUpdated) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update order data.',
            ]);
        }




        return response()->json([
            'status' => true,
            'message' => 'Return Approved Successfully'
        ]);
    }

    public function artist_name_update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required|exists:customers,customer_unique_id',
            'artist_name' => 'required|string|max:255'

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
                'message' => 'No Customer Found'
            ]);
        }
        $timezone = $customer->timezone ?? 'Asia/Kolkata';
        $currentDateTime = now($timezone);


        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();
        $newDate = $currentDateTime->addDays(14)->toDateString();
        if ($customer->updated_date == $insertDate) {
            $update =  DB::table('customers')
                ->where('customer_unique_id', $request->customer_unique_id)
                ->update([
                    'artist_name' => $request->artist_name,
                    'updated_date' => $newDate,
                ]);
            return response()->json([
                'status' => true,
                'message' => 'Artist name updated successfully',
            ], 200);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'You cannot change the artist name for 14 days after your last update.',
            ]);
        }
    }

    public function get_seller_all_art_enquiry(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();


        $chats = ArtEnquiryChat::with(['ArtEnquiryChatMessage' => function ($query) {
            $query->select('art_enquiry_chat_message_id', 'art_enquiry_chat_id', 'message', 'images', 'status', 'inserted_date', 'inserted_time')
                ->orderByDesc('inserted_date')
                ->orderByDesc('inserted_time');
        }])
            ->Where('seller_id', $customer->customer_id)
            ->orderByDesc('inserted_date')
            ->orderByDesc('inserted_time')
            ->get();



        $ArtData = [];
        foreach ($chats as $chat) {
            // Get the other participant in the chat
            $lastMessagae = DB::table('art_enquiry_chat_message')
                ->where('art_enquiry_chat_id', $chat->art_enquiry_chat_id)
                ->orderBy('inserted_date', 'desc')
                ->orderBy('inserted_time', 'desc')
                ->first();
            $artEnquiry = DB::table('art_enquiry_chat')->where('art_enquiry_chat_id', $chat->art_enquiry_chat_id)->first();

            $art = Art::where('art_id', $chat->art_id)->first();

            if ($art) {
                $artImage = ArtImage::where('art_id', $art->art_id)->first();
                $customer = Customer::where('customer_id', $chat->customer_id)->first();
                $ArtData[] = [
                    'art_unique_id' => $art->art_unique_id,
                    'title' => $art->title,
                    'image' => isset($artImage->image) ? url($artImage->image) : null,
                    'reciver_unique_id' => $customer->customer_unique_id,
                    'art_enquiry_chat_id' => $chat->art_enquiry_chat_id,
                    'last_message' => $lastMessagae ?? $artEnquiry,
                ];
            }
        }
        return response()->json([
            'status' => true,
            'message' => 'Chat users fetched successfully.',
            'art_enuqiry_list' => $ArtData,
        ]);
    }


    public function admin_private_enquiry_chat(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_unique_id' => 'required',
            'art_unique_id' => 'required',
            'artist_unique_id' => 'required',
            // 'message' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }
        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        $user = User::where('user_unique_id', $request->user_unique_id)->first();
        $seller = Customer::where('customer_unique_id', $request->artist_unique_id)->first();
        $art = Art::where('art_unique_id', $request->art_unique_id)->first();

        // dd($art);

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'No User Found'
            ]);
        }
        if (!$seller) {
            return response()->json([
                'status' => false,
                'message' => 'No Artist Found'
            ]);
        }
        if (!$art) {
            return response()->json([
                'status' => false,
                'message' => 'No Art Found'
            ]);
        }

        $chatData = [
            'seller_id' => $seller->customer_id,
            'art_id' => $art->art_id,
            'user_id' => $user->user_id,
            'role' => 'superadmin',
            'status' => 'Active',
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ];

        $existingChat = DB::table('private_enquiry_chat')
            ->where('art_id', $art->art_id)
            ->where('user_id',  $user->customer_id)
            ->first();

        if ($existingChat) {
            return response()->json([
                'status' => false,
                'message' => 'Your Enquiry Alredy Exists',
                'private_enquiry_chat_id' => $existingChat->private_enquiry_chat_id,

            ]);
        }

        $artImage = ArtImage::where('art_id', $art->art_id)->first();

        $chatDataId =  DB::table('private_enquiry_chat')->insertGetId($chatData);

        // $messageDataId = DB::table('admin_private_enquiry_chat_message')->insertGetId([
        //     'sender_id' => $user->user_id,
        //     'receiver_id' => $seller->customer_id,
        //     'private_enquiry_chat_id' => $chatDataId,
        //     'message' => $request->message,
        //     'inserted_date' => $insertDate,
        //     'inserted_time' => $insertTime,
        // ]);

        // $messageData = DB::table('admin_private_enquiry_chat_message')->where('admin_private_enquiry_chat_message_id', $messageDataId)->first();


        return response()->json([
            'status' => true,
            'art_title' => $art->title,
            'private_enquiry_chat_id' => $chatDataId,
            'art_image' => isset($artImage->image) ? url($artImage->image) : null,
            // 'message' => $messageData->message,
            'reciver_unique_id' => $seller->customer_unique_id,
        ]);
    }


    public function get_seller_all_private_art_enquiry(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();


        $chats = PrivateEnquiryChat::with(['AdminPrivateEnquiryChatMessage' => function ($query) {
            $query->select('admin_private_enquiry_chat_message_id', 'private_enquiry_chat_id', 'message', 'images', 'status', 'inserted_date', 'inserted_time')
                ->orderByDesc('inserted_date')
                ->orderByDesc('inserted_time');
        }])
            ->Where('seller_id', $customer->customer_id)
            ->Where('role', 'superadmin')
            ->orderByDesc('inserted_date')
            ->orderByDesc('inserted_time')
            ->get();

        $ArtData = [];
        foreach ($chats as $chat) {
            // dd("jbdhcds:",$chat);
            $art = Art::where('art_id', $chat->art_id)->first();
            if(!$art){
                continue;
            }
            $lastMessage = $chat->AdminPrivateEnquiryChatMessage->first();
            $artImage = ArtImage::where('art_id', $art->art_id)->first();
            $user = User::where('user_id', $chat->user_id)->first();
            $ArtData[] = [
                'art_unique_id' => $art->art_unique_id??null,
                'title' => $art->title??null,
                'image' => isset($artImage->image) ? url($artImage->image) : null,
                'reciver_unique_id' => $user->user_unique_id,
                'private_enquiry_chat_id' => $chat->private_enquiry_chat_id,
                'last_message' => $lastMessage,
                // 'admin_private_enquiry_chat_message_id'=>$chat->admin_private_enquiry_chat_message_id,
                // 'chat'=>$chat,
            ];
        }
        return response()->json([
            'status' => true,
            'message' => 'Chat users fetched successfully.',
            'private_enuqiry_list' => $ArtData,
        ]);
    }




    public function seller_update_retrun_status(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'art_unique_id' => 'required',
            'tracking_status' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }
        $art = Art::where('art_unique_id', $request->art_unique_id)->first();

        $orderArt = DB::table('ordered_arts')
            ->where('art_id', $art->art_id)
            ->where('art_order_status', 'Return')
            ->first();
        if (!$orderArt) {
            return response()->json([
                'status' => false,
                'message' => 'No return order found for this art',
            ]);
        }
        $updateData = [
            'return_tracking_status' => $request->tracking_status
        ];

        $currentDateTime = Carbon::now();
        $insertDate = $currentDateTime->toDateString();
        if ($request->tracking_status === 'Return-Received') {
            $updateData['return_recived_date'] = $insertDate;
        }

        $updateResult = DB::table('ordered_arts')
            ->where('art_id', $art->art_id)
            ->update($updateData);

        if ($updateResult) {
            if (!empty($request->fcm_token)) {
                try {
                    $firebase = (new Factory)->withServiceAccount(base_path('mira-monet-firebase-adminsdk-fbsvc-864230166e.json'));
                    $messaging = $firebase->createMessaging();
                    $fcm_token = $request->fcm_token;

                    $messageData = CloudMessage::withTarget('token', $fcm_token)
                        ->withNotification([
                            'title' => 'Update: Your Artwork Return for "' . $art->title . '" Is in Progress!',
                            'body' => 'Thank you for your patience! Your return tracking status is: ' . $request->tracking_status,

                        ]);

                    $messaging->send($messageData);
                } catch (\Exception $e) {
                    \Log::error("FCM Notification Error: " . $e->getMessage());
                }
            }
            return response()->json([
                'status' => true,
                'message' => 'Ordered Art Tracking status successfully updated to ' . $request->tracking_status,
            ]);
        }
        return response()->json([
            'status' => false,
            'message' => 'Failed to update Ordered Art status',
        ]);
    }



    public function seller_get_all_enquiry(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();


        $chats = PrivateEnquiryChat::with(['AdminPrivateEnquiryChatMessage' => function ($query) {
            $query->select('admin_private_enquiry_chat_message_id', 'private_enquiry_chat_id', 'message', 'images', 'status', 'inserted_date', 'inserted_time')
                ->orderByDesc('inserted_date')
                ->orderByDesc('inserted_time');
        }])
            ->Where('seller_id', $customer->customer_id)
            ->Where('role', 'superadmin')
            ->orderByDesc('inserted_date')
            ->orderByDesc('inserted_time')
            ->get();

        $ArtData = [];
        foreach ($chats as $chat) {
            // dd("jbdhcds:",$chat);
            $art = Art::where('art_id', $chat->art_id)->first();
            $lastMessage = $chat->AdminPrivateEnquiryChatMessage->first();
            $artImage = ArtImage::where('art_id', $art->art_id)->first();
            $user = User::where('user_id', $chat->user_id)->first();
            $ArtData[] = [
                'art_unique_id' => $art->art_unique_id,
                'title' => $art->title,
                'art_type' => $art->art_type,

                'image' => isset($artImage->image) ? url($artImage->image) : null,
                'reciver_unique_id' => $user->user_unique_id,
                'reciver_fcm_token' => $user->fcm_token,
                'private_enquiry_chat_id' => $chat->private_enquiry_chat_id,
                'last_message' => $lastMessage,
                // 'admin_private_enquiry_chat_message_id'=>$chat->admin_private_enquiry_chat_message_id,
                // 'chat'=>$chat,
            ];
        }


        $Artchats = ArtEnquiryChat::with(['ArtEnquiryChatMessage' => function ($query) {
            $query->select('art_enquiry_chat_message_id', 'art_enquiry_chat_id', 'message', 'images', 'status', 'inserted_date', 'inserted_time')
                ->orderByDesc('inserted_date')
                ->orderByDesc('inserted_time');
        }])
            ->Where('seller_id', $customer->customer_id)
            ->orderByDesc('inserted_date')
            ->orderByDesc('inserted_time')
            ->get();



        $Data = [];
        foreach ($Artchats as $chat) {
            // Get the other participant in the chat
            $lastMessagae = DB::table('art_enquiry_chat_message')
                ->where('art_enquiry_chat_id', $chat->art_enquiry_chat_id)
                ->orderBy('inserted_date', 'desc')
                ->orderBy('inserted_time', 'desc')
                ->first();
            $artEnquiry = DB::table('art_enquiry_chat')->where('art_enquiry_chat_id', $chat->art_enquiry_chat_id)->first();

            $art = Art::where('art_id', $chat->art_id)->first();

            $artImage = ArtImage::where('art_id', $art->art_id)->first();
            $customer = Customer::where('customer_id', $chat->customer_id)->first();
            $Data[] = [
                'art_unique_id' => $art->art_unique_id,
                'title' => $art->title,
                'art_type' => $art->art_type,
                'image' => isset($artImage->image) ? url($artImage->image) : null,
                'reciver_unique_id' => $customer->customer_unique_id,
                'reciver_fcm_token' => $customer->fcm_token,
                'art_enquiry_chat_id' => $chat->art_enquiry_chat_id,
                'last_message' => $lastMessagae ?? $artEnquiry,
            ];
        }



        $data = [
            'private_enuqiry_list' => $ArtData,
            'art_enuqiry_list' => $Data
        ];

        return response()->json(data: [
            'status' => true,
            'message' => 'Chat users fetched successfully.',
            'data' => $data,
        ]);
    }


    public function seller_get_single_art_enquiry_chat_app(Request $request)
    {
        // Validate the input
        $validator = Validator::make($request->all(), [
            'art_enquiry_chat_id' => 'required|exists:art_enquiry_chat,art_enquiry_chat_id',
            'reciver_unique_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $chatInitiate = ArtEnquiryChat::with(['ArtEnquiryChatMessage'])
            ->where('art_enquiry_chat_id', $request->art_enquiry_chat_id)
            ->first();




        if (!$chatInitiate) {
            return response()->json([
                'status' => false,
                'message' => 'Chat not initiated or not found.',
            ], 404);
        }

        $art = Art::where('art_id', $chatInitiate->art_id)->first();
        $artImage = ArtImage::where('art_id', $chatInitiate->art_id)->first();

        $artData = [
            'title' => $art->title,
            'art_unique_id' => $art->art_unique_id,
            'image' => isset($artImage->image) ? url($artImage->image) : null,
        ];

        $customer_with_chat = Customer::where('customer_unique_id', operator: $request->reciver_unique_id)->first();




        $reciver_data = [
            'reciver_unique_id' => $customer_with_chat->customer_unique_id,
            'reciver_profile_image' => isset($customer_with_chat->customer_profile_image) ? url($customer_with_chat->customer_profile_image) : null,
            'reciver_name' => $customer_with_chat->full_name,
            'reciver_email' => $customer_with_chat->email,
            'reciver_mobile' => $customer_with_chat->mobile,
            'reciver_fcm_token' => $customer_with_chat->fcm_token,
            'reciver_latitude' => $customer_with_chat->latitude,
            'reciver_longitude' => $customer_with_chat->longitude,
            'reciver_role' => $customer_with_chat->role,

        ];


        $art_enquiry_chat = ArtEnquiryChat::where('art_enquiry_chat_id', $request->art_enquiry_chat_id)->first();



        $chatDetails = $chatInitiate->ArtEnquiryChatMessage->map(function ($message) {
            $sender_unique_id = Customer::where('customer_id', $message->sender_id)->value('customer_unique_id');
            $receiver_unique_id = Customer::where('customer_id', $message->receiver_id)->value('customer_unique_id');


            $customer_with_chat = Customer::where('customer_id',  $message->receiver_id)->first();


            return [
                'sender_id' => $message->sender_id,
                'receiver_id' => $message->receiver_id,
                'sender_unique_id' => $sender_unique_id,
                'receiver_unique_id' => $receiver_unique_id,
                'reciver_fcm_token' => $customer_with_chat->fcm_token,
                'inserted_date' => $message->inserted_date,
                'inserted_time' => $message->inserted_time,
                'message' => $message->message ?? null,
                'image' => $message->images ? url($message->images) : null,
            ];
        });

        $data = [
            'reciver_fcm_token' => $customer_with_chat->fcm_token,
            'art_enquiry_chat_id' => $request->art_enquiry_chat_id,
            'chatDetails' => $chatDetails,
            'artData' => $artData,
        ];

        return response()->json([
            'status' => true,
            'reciver_fcm_token' => $customer_with_chat->fcm_token,
            'art_enquiry_chat_id' => $request->art_enquiry_chat_id,
            'chatDetails' => $chatDetails,
            'artData' => $artData,

        ]);
    }
    public function seller_get_single_private_enquiry_chat_app(Request $request)
    {
        // Validate the input
        $validator = Validator::make($request->all(), [
            'private_enquiry_chat_id' => 'required|exists:private_enquiry_chat,private_enquiry_chat_id',
            'reciver_unique_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $chatInitiate = PrivateEnquiryChat::with(['AdminPrivateEnquiryChatMessage'])
            ->where('private_enquiry_chat_id', $request->private_enquiry_chat_id)
            ->first();




        if (!$chatInitiate) {
            return response()->json([
                'status' => false,
                'message' => 'Chat not initiated or not found.',
            ], 404);
        }

        $art = Art::where('art_id', $chatInitiate->art_id)->first();
        $artImage = ArtImage::where('art_id', $chatInitiate->art_id)->first();

        $artData = [
            'title' => $art->title,
            'art_unique_id' => $art->art_unique_id,
            'image' => isset($artImage->image) ? url($artImage->image) : null,
        ];

        $customer_with_chat = User::where('user_unique_id', $request->user_unique_id)->first();




        // $reciver_data = [
        //     'reciver_unique_id' => $customer_with_chat->customer_unique_id,
        //     'reciver_profile_image' => isset($customer_with_chat->customer_profile_image) ? url($customer_with_chat->customer_profile_image) : null,
        //     'reciver_name' => $customer_with_chat->full_name,
        //     'reciver_email' => $customer_with_chat->email,
        //     'reciver_mobile' => $customer_with_chat->mobile,
        //     'reciver_fcm_token' => $customer_with_chat->fcm_token,
        //     'reciver_latitude' => $customer_with_chat->latitude,
        //     'reciver_longitude' => $customer_with_chat->longitude,
        //     'reciver_role' => $customer_with_chat->role,

        // ];


        // $art_enquiry_chat = private_enquiry_chat_id::where('private_enquiry_chat_id', $request->private_enquiry_chat_id)->first();



        $chatDetails = $chatInitiate->AdminPrivateEnquiryChatMessage->map(function ($message) {

            if ($message->role == 'superadmin') {

                $sender_unique_id = User::where('user_id', $message->sender_id)->value('user_unique_id');
                $receiver_unique_id = Customer::where('customer_id', $message->receiver_id)->value('customer_unique_id');
            } else {
                $sender_unique_id = Customer::where('customer_id', $message->sender_id)->value('customer_unique_id');
                $receiver_unique_id = User::where('user_id', $message->receiver_id)->value('user_unique_id');
            }





            return [
                'sender_id' => $message->sender_id,
                'receiver_id' => $message->receiver_id,
                'sender_unique_id' => $sender_unique_id,
                'receiver_unique_id' => $receiver_unique_id,
                // 'reciver_fcm_token' => $reciver_fcm_token??null,
                'inserted_date' => $message->inserted_date,
                'inserted_time' => $message->inserted_time,
                'message' => $message->message ?? null,
                'image' => $message->images ? url($message->images) : null,
            ];
        });

        $data = [
            // 'reciver_fcm_token' => $customer_with_chat->fcm_token,
            'private_enquiry_chat_id' => $request->private_enquiry_chat_id,
            'chatDetails' => $chatDetails,
            'artData' => $artData,
        ];

        return response()->json([
            'status' => true,
            'private_enquiry_chat_id' => $request->private_enquiry_chat_id,
            'chatDetails' => $chatDetails,
            'artData' => $artData,
        ]);
    }

    public function SellersendPrivateMessage(Request $request)
    {

        // if (!Auth::guard('customer_api')->check()) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Unauthorized access.',
        //     ], 400);
        // }

        $validator = Validator::make($request->all(), [
            'private_enquiry_chat_id' => 'required|exists:private_enquiry_chat,private_enquiry_chat_id',
            'message' => 'nullable|string',
            'sender_unique_id' => 'required|string',
            'receiver_unique_id' => 'required|string',
            'images' => 'nullable|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $sender = Customer::where('customer_unique_id', $request->sender_unique_id)->first();
        $chatInitiate = PrivateEnquiryChat::where('private_enquiry_chat_id', $request->private_enquiry_chat_id)->first();
        $receiverData = User::where('user_unique_id', $request->receiver_unique_id)->first();


        $filePath = null;

        if ($request->hasFile('images') && $request->file('images')->isValid()) {
            $file = $request->file('images');

            $fileName = time() . '.' . $file->getClientOriginalExtension();

            $file->move(public_path('privateArtEnquiry/images'), $fileName);

            $filePath = 'privateArtEnquiry/images/' . $fileName;
        }


        $timezone = $sender->timezone ?? 'Asia/Kolkata';
        $currentDateTime = now($timezone);


        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();


        $message = AdminPrivateEnquiryChatMessage::create([
            'private_enquiry_chat_id' => $request->private_enquiry_chat_id,
            'sender_id' => $sender->customer_id,
            'receiver_id' => $receiverData->user_id,
            'message' => $request->message,
            'role' => $sender->role,
            'images' => $filePath,
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ]);

        $data = [
            'private_enquiry_chat_id' => $request->private_enquiry_chat_id,
            'sender_id' => $sender->customer_id,
            'sender_unique_id' => $request->sender_unique_id,
            'receiver_id' => $receiverData->user_id,
            'receiver_unique_id' => $request->receiver_unique_id,
            'message' => $request->message,
            'image' => url($filePath),
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ];


        return response()->json([
            'status' => true,
            'message' => $data
        ]);
    }


    public function seller_help_center_chat(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required',
            'name' => 'required',
            'email' => 'required',
            'mobile' => 'required',
            // 'art_unique_id' => 'required',
            'enquiry_category_id' => 'required',
            'issue' => 'required',
            'images' => 'required|array',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }





        $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();
        $user = User::where('user_unique_id', '987654321')->first();
        $timezone = $customer->timezone ?? 'Asia/Kolkata';
        $currentDateTime = now($timezone);
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        // $art = Art::where('art_unique_id', $request->art_unique_id)->first();

        // dd($art);

        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'No Customer Found'
            ]);
        }
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'No User Found'
            ]);
        }
        // if (!$art) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'No Art Found'
        //     ]);
        // }

        $chatData = [
            'customer_id' => $customer->customer_id,
            'artist_name' => $request->artist_name,
            'role' => $customer->role,
            'user_id' => $user->user_id,
            'full_name' => $request->name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'enquiry_category_id' => $request->enquiry_category_id,
            'issue' => $request->issue,
            'status' => 'Active',
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ];

        $existingChat = DB::table('help_center_chat')
            ->where('enquiry_category_id', $request->enquiry_category_id)
            ->where('customer_id',  $customer->customer_id)
            ->where('role', 'seller')
            ->first();

        if ($existingChat) {
            table:
            return response()->json([
                'status' => false,
                'message' => 'Your Enquiry Alredy Exists',
                'help_center_chat_id' => $existingChat->help_center_chat_id,

            ]);
        }

        $enquiryCategory = DB::table('enquiry_category')->where('enquiry_category_id', $request->enquiry_category_id)->select('enquiry_category_name')->first();

        // $artImage = ArtImage::where('art_id', $art->art_id)->first();

        $chatDataId =  DB::table('help_center_chat')->insertGetId($chatData);

        $existingImages = [];
        $uploadedImages = [];

        foreach ($request->file('images') as $image) {
            if (!$image->isValid()) {
                continue;
            }

            $fileName = uniqid('art_', true) . '.' . $image->getClientOriginalExtension();
            $filePath = 'helpCenter/image/' . $fileName;

            if (in_array($filePath, $existingImages)) {
                continue;
            }

            $image->move(public_path('helpCenter/image'), $fileName);

            $uploadedImages[] = [
                'help_center_chat_id' => $chatDataId,
                'image' => $filePath
            ];

            $existingImages[] = $filePath;
        }
        if (!empty($uploadedImages)) {
            DB::table('help_center_images')->insert($uploadedImages);
        }

        $issueImages = DB::table('help_center_images')
            ->where('help_center_chat_id', $chatDataId)
            ->get()
            ->map(function ($issueImage) {
                $issueImage->image = isset($issueImage->image) ? url($issueImage->image) : null;
                return $issueImage;
            });

        // $issueimages=DB::table('help_center_images')->where('help_center_image_id',$help_center_image_id)->select('help_center_chat_id','image')->get();
        return response()->json([
            'status' => true,
            // 'art_title' => $art->title,
            'message' => 'Help Enquiry Raised Successfully',
            'help_center_chat_id' => $chatDataId,
            // 'art_image' => isset($artImage->image) ? url($artImage->image) : null,
            'reciver_unique_id' => $user->user_unique_id,
            'images' => $issueImages,
            'issue' => $request->issue,
            'enquiryCategory' => $enquiryCategory->enquiry_category_name
        ]);
    }


    public function get_seller_all_help_enquiry(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        // Find the customer
        $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();


        // dd($customer->customer_id);
        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'Customer not found.',
            ]);
        }


        $chats = HelpCenterChat::with([
            'EnquiryCategory' => function ($query) {
                $query->select('enquiry_category_id', 'enquiry_category_name');
            },
            'HelpCenterChatImages' => function ($query) {
                $query->select('help_center_image_id', 'help_center_chat_id', 'image');
            },
            'HelpCenterChatMessage' => function ($query) {
                $query->select('help_center_chat_message_id', 'help_center_chat_id', 'image', 'message', 'inserted_date', 'inserted_time')
                    ->orderByDesc('inserted_date')
                    ->orderByDesc('inserted_time');
            },
        ])
            ->where('customer_id', $customer->customer_id)
            ->orderByDesc('inserted_date')
            ->orderByDesc('inserted_time')
            ->get();


        if ($chats->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No Data Found'
            ]);
        }
        $ArtData = [];

        foreach ($chats as $chat) {


            $user = User::where('user_id', $chat->user_id)->first();


            $lastMessagae = DB::table('help_center_chat_message')
                ->where('help_center_chat_id', $chat->help_center_chat_id)
                ->orderBy('inserted_date', 'desc')
                ->orderBy('inserted_time', 'desc')
                ->first();

            $help_center_chat = DB::table('help_center_chat')
                ->where('help_center_chat_id', $chat->help_center_chat_id)
                ->first();

            $help_center_issue_images = DB::table('help_center_images')
                ->where('help_center_chat_id', $chat->help_center_chat_id)
                ->get();

            foreach ($help_center_issue_images as $help_center_issue_image) {
                $help_center_issue_image->image = isset($help_center_issue_image->image) ? url($help_center_issue_image->image) : null;
            }


            $ArtData[] = [
                'reciver_unique_id' => $user->user_unique_id,
                'artist_name' => $customer->name,
                'help_center_issue_images' => $help_center_issue_images,
                'help_center_chat_id' => $chat->help_center_chat_id,
                'enquiry_category_name' => $chat->EnquiryCategory->enquiry_category_name,
                'last_message' => $lastMessagae ?? $help_center_chat,
            ];
        }

        return response()->json([
            'status' => true,
            'message' => 'Chat users fetched successfully.',
            'help_enuqiry_list' => $ArtData,
        ]);
    }



    public function seller_get_single_help_enquiry_chat_app(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'help_center_chat_id' => 'required|exists:help_center_chat,help_center_chat_id',
            'reciver_unique_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $chatInitiate = HelpCenterChat::with(['HelpCenterChatMessage', 'EnquiryCategory' => function ($query) {
            $query->select('enquiry_category_id', 'enquiry_category_name');
        }])
            ->where('help_center_chat_id', $request->help_center_chat_id)
            ->first();

        $issue = $chatInitiate->issue;

        $enquiryCategoryName = $chatInitiate->EnquiryCategory ? $chatInitiate->EnquiryCategory->enquiry_category_name : null;


        if (!$chatInitiate) {
            return response()->json([
                'status' => false,
                'message' => 'Chat not initiated or not found.',
            ]);
        }

        $helpmessage = DB::table('help_center_chat')->where('help_center_chat_id', $request->help_center_chat_id)->first();

        // dd($helpmessage->issue);
        $customer_with_chat = User::where('user_unique_id', $request->reciver_unique_id)->first();


        $reciver_data = [
            'reciver_unique_id' => $customer_with_chat->customer_unique_id,
            'reciver_profile_image' => isset($customer_with_chat->customer_profile_image) ? url($customer_with_chat->customer_profile_image) : null,
            'reciver_name' => $customer_with_chat->full_name,
            'reciver_email' => $customer_with_chat->email,
            'reciver_mobile' => $customer_with_chat->mobile,
            'reciver_fcm_token' => $customer_with_chat->fcm_token,
            'reciver_latitude' => $customer_with_chat->latitude,
            'reciver_longitude' => $customer_with_chat->longitude,
            'reciver_role' => $customer_with_chat->role,

        ];
        $issueImages = DB::table('help_center_images')
            ->where('help_center_chat_id', $chatInitiate->help_center_chat_id)
            ->get()
            ->map(function ($issueImage) {
                $issueImage->image = isset($issueImage->image) ? url($issueImage->image) : null;
                return $issueImage;
            });



        $chatDetails = $chatInitiate->HelpCenterChatMessage->map(function ($message) {
            $sender_unique_id = Customer::where('customer_id', $message->sender_id)->value('customer_unique_id');
            $receiver_unique_id = User::where('user_id', $message->receiver_id)->value('user_unique_id');




            return [
                'sender_id' => $message->sender_id,
                'receiver_id' => $message->receiver_id,
                'sender_unique_id' => $sender_unique_id,
                'receiver_unique_id' => $receiver_unique_id,
                // 'reciver_fcm_token' => $reciver_fcm_token,
                'inserted_date' => $message->inserted_date,
                'inserted_time' => $message->inserted_time,
                'message' => $message->message ?? null,
                'image' => $message->image ? url($message->image) : null,
            ];
        });

        $data = [
            'help_center_chat_id' => $request->help_center_chat_id,
            'chatDetails' => isset($chatDetails) ? $chatDetails : $helpmessage,
            'enquiryCategoryName' => $enquiryCategoryName,
            'issue' => $issue,
            'issueImages' => $issueImages,
        ];

        return response()->json([
            'status' => true,
            'data' => $data

        ]);
    }


    public function SellersendhelpMessage(Request $request)
    {

        // if (!Auth::guard('customer_api')->check()) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Unauthorized access.',
        //     ], 400);
        // }

        $validator = Validator::make($request->all(), [
            'help_center_chat_id' => 'required|exists:help_center_chat,help_center_chat_id',
            'message' => 'nullable|string',
            'sender_unique_id' => 'required|string',
            'receiver_unique_id' => 'required|string',



            'images' => 'nullable|max:2048',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $sender = Customer::where('customer_unique_id', $request->sender_unique_id)->first();
        $chatInitiate = HelpCenterChat::where('help_center_chat_id', $request->help_center_chat_id)->first();
        $receiverData = User::where('user_unique_id', $request->receiver_unique_id)->first();


        $filePath = null;

        if ($request->hasFile('images') && $request->file('images')->isValid()) {
            $file = $request->file('images');

            $fileName = time() . '.' . $file->getClientOriginalExtension();

            $file->move(public_path('HelpArtEnquiry/images'), $fileName);

            $filePath = 'HelpArtEnquiry/images/' . $fileName;
        }


        $timezone = $sender->timezone ?? 'Asia/Kolkata';
        $currentDateTime = now($timezone);

        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();


        $message = HelpCenterChatMessage::create([
            'help_center_chat_id' => $request->help_center_chat_id,
            'sender_id' => $sender->customer_id,
            'receiver_id' => $receiverData->user_id,
            'message' => $request->message,
            'role' => $sender->role,
            'image' => $filePath,
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ]);

        $data = [
            'help_center_chat_id' => $request->help_center_chat_id,
            'sender_id' => $sender->customer_id,
            'sender_unique_id' => $request->sender_unique_id,
            'receiver_id' => $receiverData->user_id,
            'receiver_unique_id' => $request->receiver_unique_id,
            'message' => $request->message,
            'image' => url($filePath),
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ];


        return response()->json([
            'status' => true,
            'message' => $data
        ]);
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

        $val = rand(0, 999);  // Generates a random number between 0 and 999 (inclusive)

        $uniqueId = $baseUniqueId . $ArtCountPadded . $val;


        return $uniqueId;
    }
    // public function addExhibitionArtwork(Request $request)
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
    //         'category_id' => 'required',
    //         'edition' => 'required',
    //         'art_type' => 'required',
    //         'estimate_price_from' => 'required',
    //         'estimate_price_to' => 'required',
    //         'exhibition_unique_id' => 'required',
    //         'since' => 'required',
    //         'pickup_address' => 'required',
    //         'portal_percentages' => 'required',
    //         'pincode' => 'required',
    //         'country' => 'required',
    //         'state' => 'required',
    //         'city' => 'required',
    //         'frame' => 'required',
    //         'paragraph' => 'required',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => $validator->errors()->first(),
    //         ], 400);
    //     }

    //     $customer = Auth::guard('customer_api')->user();
    //     $customer_unique_id = $request->customer_unique_id;

    //     if ($customer->customer_unique_id != $customer_unique_id) {
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

    //     $exhibitions = DB::table('exhibitions')->where('exhibition_unique_id', $request->exhibition_unique_id)->first();

    //     $existingArtworksCount = Art::where('customer_id', $customer_id)
    //         ->where('exhibition_id', $exhibitions->exhibition_id)
    //         ->count();

    //     $isAdd = true;
    //     if ($existingArtworksCount >= 3) {

    //         $isAdd = false;
    //         // return response()->json([
    //         //     'status' => false,
    //         //     'message' => 'You can only upload a maximum of 3 artworks for this exhibition.',
    //         // ]);
    //     }

    //     $existingArt = Art::where('customer_id', $customer_id)
    //         ->where('title', $request->title)
    //         // ->where('art_unique_id', $this->generateUniqueId($customer_id, $request->category_id))
    //         ->first();

    //     if ($existingArt) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'An Art with this title already exists for this customer.',
    //         ]);
    //     }

    //     $currentDateTime = Carbon::now('Asia/Kolkata');


    //     $artData = [
    //         'customer_id' => $customer_id,
    //         'art_unique_id' => $this->generateUniqueId($customer_id, $request->category_id),
    //         'title' => $request->title,
    //         'artist_name' => $customers->name,
    //         'category_id' => $request->category_id,
    //         'edition' => $request->edition,
    //         'art_type' => $request->art_type,
    //         'exhibition_id' => $exhibitions->exhibition_id ?? null,
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
    //         'paragraph' => $request->paragraph,
    //         'portal_percentages' => $request->portal_percentages,
    //         'status' => 'Pending',
    //         'inserted_date' => $currentDateTime->toDateString(),
    //         'inserted_time' => $currentDateTime->toTimeString(),
    //     ];

    //     try {
    //         $art = Art::create($artData);
    //         $artId = $art->id;

    //         // Use lastInsertId() as a fallback
    //         if (!$artId) {
    //             $artId = DB::getPdo()->lastInsertId();
    //         }
    //         if ($request->category_id == '1') {
    //             $category = Category::where('category_id', $request->category_id)->first();
    //             return response()->json([
    //                 'status' => true,
    //                 'message' => 'Exhibition Artwork added successfully.',
    //                 'art_unique_id' => $art->art_unique_id,
    //                 'isAdd' => $isAdd,
    //                 'category_id' => $art->category_id,
    //                 'category_name' => $category->category_name,
    //             ]);
    //         } else {
    //             return response()->json([
    //                 'status' => true,
    //                 'message' => 'Exhibition Artwork added successfully.',
    //                 'art_unique_id' => $art->art_unique_id,
    //                 'isAdd' => $isAdd
    //             ]);
    //         }
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Failed to add artwork: ' . $e->getMessage(),
    //         ], 500);
    //     }
    // }

    public function addExhibitionArtwork(Request $request)
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
            'estimate_price_from' => 'required',
            'estimate_price_to' => 'required|gt:estimate_price_from',
            'exhibition_unique_id' => 'required|exists:exhibitions,exhibition_unique_id',
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
            ], 200);
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
        $exhibitions = DB::table('exhibitions')->where('exhibition_unique_id', $request->exhibition_unique_id)->first();

        if (!$exhibitions) {
            return response()->json([
                'status' => false,
                'message' => 'No Exhibition Found!',
            ]);
        }

        $existingArtworksCount = Art::where('customer_id', $customer_id)
            ->where('exhibition_id', $exhibitions->exhibition_id)
            ->count();

        $isAdd = true;
        if ($existingArtworksCount >= 3) {
            $isAdd = false;
        }

        $existingArt = Art::where('customer_id', $customer_id)
            ->where('title', $request->title)
            ->where('exhibition_id', $exhibitions->exhibition_id)
            ->first();

        if ($existingArt) {
            return response()->json([
                'status' => false,
                'message' => 'An Art with this title already exists for this customer.',
            ]);
        }

        $currentDateTime = Carbon::now('Asia/Kolkata');

        $art_unique_id = $this->generateUniqueId($customer_id, $request->category_id);
        if (!$art_unique_id) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to generate unique ID for artwork.',
            ], 500);
        }

        DB::beginTransaction();
        try {
            $artData = [
                'customer_id' => $customer_id,
                'art_unique_id' => $art_unique_id,
                'title' => $request->title,
                'artist_name' => $customers->name,
                'category_id' => $request->category_id,
                'sub_category_1_id' => $request->sub_category_1_id,
                'edition' => $request->edition,
                'art_type' => $request->art_type,
                'exhibition_id' => $exhibitions->exhibition_id ?? null,
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

            $art = Art::create($artData);

            DB::commit();

            if ($request->category_id == '1') {
                $category = Category::where('category_id', $request->category_id)->first();
                return response()->json([
                    'status' => true,
                    'message' => 'Exhibition Artwork added successfully.',
                    'art_unique_id' => $art->art_unique_id,
                    'isAdd' => $isAdd,
                    'category_id' => $art->category_id,
                    'category_name' => $category->category_name,
                ]);
            } else {
                return response()->json([
                    'status' => true,
                    'message' => 'Exhibition Artwork added successfully.',
                    'art_unique_id' => $art->art_unique_id,
                    'isAdd' => $isAdd
                ]);
            }
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => false,
                'message' => 'Failed to add artwork: ' . $e->getMessage(),
            ], 500);
        }
    }


    public function updateExhibitionArtwork(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'exhibition_unique_id' => 'required|exists:exhibitions,exhibition_unique_id',
            'customer_unique_id' => 'required|exists:customers,customer_unique_id',
            'title' => 'nullable|string',
            'category_id' => 'nullable|integer|exists:categories,id',
            'sub_category_1_id' => 'nullable|integer|exists:sub_categories,id',
            'edition' => 'nullable|string',
            'art_type' => 'nullable|string',
            'estimate_price_from' => 'nullable|numeric',
            'estimate_price_to' => 'nullable|numeric|gt:estimate_price_from',
            'since' => 'nullable|integer',
            'pickup_address' => 'nullable|string',
            'portal_percentages' => 'nullable|string',
            'pincode' => 'nullable|string',
            'country' => 'nullable|string',
            'state' => 'nullable|string',
            'city' => 'nullable|string',
            'frame' => 'nullable|string',
            'paragraph' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*.type' => 'required_with:images|string',
            'images.*.art_image' => 'required_with:images|file|mimes:jpg,jpeg,png',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();
        $exhibition = Exhibition::where('exhibition_unique_id', $request->exhibition_unique_id)->first();
        $artwork = Art::where('customer_id', $customer->customer_id)
            ->where('exhibition_id', $exhibition->exhibition_id)
            ->where('title', $request->title)
            ->first();

        if (!$artwork) {
            return response()->json([
                'status' => false,
                'message' => 'Artwork not found for the given exhibition.',
            ], 404);
        }

        $updateData = $request->only([
            'title', 'category_id', 'sub_category_1_id', 'edition', 'art_type',
            'estimate_price_from', 'estimate_price_to', 'since', 'pickup_address',
            'portal_percentages', 'pincode', 'country', 'state', 'city', 'frame', 'paragraph'
        ]);

        $artwork->update($updateData);

        if ($request->has('images')) {
            foreach ($request->images as $image) {
                $path = $image['art_image']->store('exhibition_images', 'public');
                ExhibitionArtImage::create([
                    'exhibition_id' => $exhibition->exhibition_id,
                    'type' => $image['type'],
                    'image_path' => $path,
                ]);
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Exhibition artwork updated successfully.',
            'updated_artwork' => $artwork,
        ]);
    }


    public function addExhibitionArtImage(Request $request)
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

        $customer = Auth::guard('customer_api')->user();

        $customers = Customer::where('customer_unique_id', $customer->customer_unique_id)->first();

        $existingArtworksCount = Art::where('customer_id', $customers->customer_id)
            ->where('exhibition_id', $request->exhibition_id)
            ->count();

        $isAdd = true;
        if ($existingArtworksCount >= 3) {

            $isAdd = false;
            // return response()->json([
            //     'status' => false,
            //     'message' => 'You can only upload a maximum of 3 artworks for this exhibition.',
            // ]);
        }

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $fileName = time() . '_' . Str::random(5) . '.' . $file->getClientOriginalExtension();
            $filePath = 'exhibitions/image/' . $fileName;
            $file->move(public_path('exhibitions/image'), $fileName);


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
                'art_image_id' => $image->id,
                'isAdd' => $isAdd
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'No image file provided.',
            ], 400);
        }
    }

    public function exhibition_art_detail(Request $request)
    {
        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ]);
        }
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
        $existingArtworksCount = Art::where('customer_id', $art->customer_id)
            ->where('exhibition_id', $art->exhibition_id)
            ->where('art_type', 'Exhibition')
            ->count();

        $isAdd = true;
        if ($existingArtworksCount >= 5) {
            $isAdd = false;
        }

        $existingArtworksCount = Art::where('customer_id', $art->customer_id)
            ->where('exhibition_id', $art->exhibition_id)
            ->where('art_type', 'Exhibition')
            ->count();

        $isSubmit = false;
        if (in_array($existingArtworksCount, [3, 4, 5])) {
            $isSubmit = true;
        }


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


        $art['colorCode'] = $colorCode->status_color_code;
        $art['image'] = $image;
        $art['isAdd'] = $isAdd;
        $art['isSubmit'] = $isSubmit;
        $art['artCount'] = $existingArtworksCount;
        return response()->json([
            'status' => true,
            'artallDetails' => $art
        ]);
    }



    public function artist_exh_reg(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required',
            'mobile' => 'required',
            'artist_name' => 'required',
            'portfolio_link' => 'required',
            'social_link' => 'required',
            'address' => 'required',
            'exhibition_unique_id' => 'required',
            'customer_unique_id' => 'required'

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $exhibition = DB::table('exhibitions')->where('exhibition_unique_id', $request->exhibition_unique_id)->first();

        // $exhibition->logo = isset($exhibition->logo) ? 'https://artist.genixbit.com/'.$exhibition->logo : null;
        // $exhibition->banner = isset($exhibition->banner) ? 'https://artist.genixbit.com/'.$exhibition->banner : null;


        if (!$exhibition) {
            return response()->json([
                'status' => false,
                'message' => 'No Exhibiton found',
            ]);
        }
        $country=Country::where('country_id',$exhibition->country)->first();
        $state=State::where('state_subdivision_id',$exhibition->state)->first();
        $city=City::where('cities_id',$exhibition->city)->first();
        $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();
        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'No customer found',
            ]);
        }
        $name = $request->input('name');
        $email = $request->input('email');
        $mobile = $request->input('mobile');
        $artist_name = $request->input('artist_name');
        $portfolio_link = $request->input('portfolio_link');
        $social_link = $request->input('social_link');
        $address = $request->input('address');
        $exhibitionId = $exhibition->exhibition_id;
        $currentDateTime = Carbon::now('Asia/Kolkata');

        $exhibitionsData = [
            'exhibition_id' => $exhibition->exhibition_id,
            'exhibition_unique_id' => $exhibition->exhibition_unique_id,
            'name' => $exhibition->name,
            'tagline' => $exhibition->tagline,
            'description' => $exhibition->description,
            'start_date' => $exhibition->start_date,
            'end_date' => $exhibition->end_date,
            'amount' => $exhibition->amount,
            'inserted_date' => $exhibition->inserted_date,
            'updated_date' => $exhibition->updated_date,
            'country' => $exhibition->country,
            'state' => $exhibition->state,
            'city' => $exhibition->city,
            'country_name' => $country->country_name,
            'state_subdivision_name' => $state->state_subdivision_name,
            'name_of_city' => $city->name_of_city,
            'address1' => $exhibition->address1,
            'address2' => $exhibition->address2,
            'contact_number' => $exhibition->contact_number,
            'contact_email' => $exhibition->contact_email,
            'website_link' => $exhibition->website_link,
            'status' => $exhibition->status,
            'logo' => isset($exhibition->logo) ? 'https://artist.genixbit.com/' . $exhibition->logo : null,
        ];

        $amount = $exhibition->amount;
        $isPaid = $amount > 0 ? 1 : 0;
        $exhibitionUniqueId = $exhibition->exhibition_unique_id;

        $existingRegistration = DB::table('artist_exhibition_registration')->where('exhibition_id', $exhibitionId)
            ->where('mobile', $mobile)
            ->where('status', 'Inactive')
            ->first();


        if ($existingRegistration) {
            $seatsData = DB::table('booth_seats')
            ->where('booth_seat_id', $request->booth_seat_id)
            ->first();
            DB::table('artist_exhibition_registration')->where('exhibition_id', $exhibitionId)
                ->where('mobile', $mobile)
                ->where('status', 'Inactive')->update([
                    'name' => $name,
                    'email' => $email,
                    'mobile' => $mobile,
                    'artist_name' => $artist_name,
                    'customer_id' => $customer->customer_id,
                    'portfolio_link' => $portfolio_link,
                    'social_link' => $social_link,
                    'address' => $address,
                    'booth_seat_id' => $request->booth_seat_id,
                    'seat_name' => $seatsData->seat_name,
                    'inserted_date' => $currentDateTime->toDateString(),
                    'inserted_time' => $currentDateTime->toTimeString(),

                ]);

            DB::table('booth_seats')
                ->where('booth_seat_id', $request->booth_seat_id)
                ->update([
                    'status' => 'Active'
                ]);

            if ($request->booth_seat_id) {
                $seats = DB::table('booth_seats')
                    ->where('booth_seat_id', $request->booth_seat_id)
                    ->first();

                $booth = DB::table('exhibition_booths')
                    ->where('exhibition_booth_id', $seats->exhibition_booth_id)
                    ->first();

                if ($seats) {
                    $amount = $existingRegistration->amount + $booth->price;
                    $exhibitions = DB::table('exhibitions')->where('exhibition_id', $exhibitionId)->first();

                    if ($exhibitions) {
                        $exhibitions->amount = $amount;
                    }
                }
            }

            return response()->json([
                'status' => 'true',
                'message' => 'Customer registration updated successfully.',
                'is_paid' => $isPaid,
                'amount' => $amount,
                'exhibition_data' => $exhibitions,
                'registration_code' => $existingRegistration->registration_code,
            ]);
        }

        $totalRegistrations = DB::table('artist_exhibition_registration')
            ->where('exhibition_id', $exhibitionId)
            ->count();
        $registrationCode = $exhibitionUniqueId . ($totalRegistrations + 1);

        $seatsData = DB::table('booth_seats')
        ->where('booth_seat_id', $request->booth_seat_id)
        ->first();




        $registration = DB::table('artist_exhibition_registration')->insertGetId([
            'name' => $name,
            'email' => $email,
            'mobile' => $mobile,
            'artist_name' => $artist_name,
            'portfolio_link' => $portfolio_link,
            'social_link' => $social_link,
            'address' => $address,

            'customer_id' => $customer->customer_id,
            'exhibition_id' => $exhibitionId,
            'registration_code' => $registrationCode,
            'status' => 'Inactive',
            'amount' => $amount,
            'inserted_date' => $currentDateTime->toDateString(),
            'inserted_time' => $currentDateTime->toTimeString(),

        ]);

        if ($request->booth_seat_id) {

            DB::table('booth_seats')
            ->where('booth_seat_id', $request->booth_seat_id)
            ->update([
                'status' => 'Active'
            ]);

            $seats = DB::table('booth_seats')
                ->where('booth_seat_id', $request->booth_seat_id)
                ->first();

            $booth = DB::table('exhibition_booths')
                ->where('exhibition_booth_id', $seats->exhibition_booth_id)
                ->first();

            if ($seats) {
                $amount = $amount + $booth->price;
                DB::table('artist_exhibition_registration')
                    ->where('artist_exhibition_registration_id', $registration)
                    ->update([
                        'amount' => $amount,
                        'booth_seat_id' => $request->booth_seat_id,
                        'seat_name' => $seatsData->seat_name,
                    ]);
            }
            $exhibitions = DB::table('exhibitions')->where('exhibition_id', $exhibitionId)->first();

            if ($exhibitions) {
                $exhibitions->amount = $amount;
            }
        }


        return response()->json([
            'status' => 'true',
            'message' => 'Artist registered successfully.',
            'is_paid' => $isPaid,
            'amount' => $amount,
            'exhibition_data' => $exhibitionsData,
            'registration_code' => $registrationCode,
        ]);
    }



    public function exhibition_submit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required',
            'exhibition_unique_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();
        $exhibition = DB::table('exhibitions')->where('exhibition_unique_id', $request->exhibition_unique_id)->first();

        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'No Customer Found'
            ]);
        }
        if (!$exhibition) {
            return response()->json([
                'status' => false,
                'message' => 'No Exhibition Found'
            ]);
        }

        $art = Art::where('exhibition_id', $exhibition->exhibition_id)
            ->where('customer_id', $customer->customer_id)
            ->select('art_unique_id', 'title', 'artist_name', 'exhibition_id')
            ->get();

        $mailData = [];
        foreach ($art as $value) {

            $mailData[] = [
                'art_unique_id' => $value->art_unique_id,
                'title' => $value->title,
                'artist_name' => $value->artist_name,
                'exhibition_name' => $exhibition->name,
            ];
        }

        // if (app()->environment('local', 'testing')) {
        //     return response()->json([
        //         'status' => 'true',
        //         'message' => 'Request received! We will revert within 72 hours.',

        //     ], 200);
        // }
        try {
            $emailContent = view('emails.art_exhibition', ['mailData' => $mailData])->render();

            Mail::send('emails.art_exhibition', ['mailData' => $mailData], function ($message) use ($customer) {
                $message->to($customer->email)
                    ->subject('Seller Upload Art in Exhibition')
                    ->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'));
            });

            return response()->json(
                [
                    'status' => 'true',
                    'message' => 'Request received! We will revert within 72 hours.'
                ],
                200
            );
        } catch (\Exception $e) {
            \Log::error('Failed to send  email: ' . $e->getMessage());

            return response()->json([
                'status' => 'false',
                'message' => 'Failed to send message email',
                'error' => $e->getMessage(),
            ]);
        }
    }


    public function get_exhibition_per(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'exhibition_unique_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $exhibitions = DB::table('exhibitions')
            ->where('exhibition_unique_id', $request->exhibition_unique_id)
            ->select('art_commision')
            ->first();

        if (!$exhibitions) {
            return response()->json([
                'status' => false,
                'message' => 'No Exhibition Found'
            ]);
        }

        return response()->json([
            'status' => true,
            'percantage' => $exhibitions->art_commision ?? null
        ]);
    }

    public function artistfreeExhReg(Request $request)
    {
        $request->validate([
            'registration_code' => 'required|string'
        ]);

        $registrationCode = $request->input('registration_code');

        $registration = DB::table('artist_exhibition_registration')->where('registration_code', $registrationCode)->first();

        if (!$registration) {
            return response()->json(['status' => 'false', 'message' => 'Registration not found.']);
        }

        $exhibition = DB::table('exhibitions')->where('exhibition_id', $registration->exhibition_id)
            ->join('countries', 'countries.country_id', '=', 'exhibitions.country')
            ->join('states', 'states.state_subdivision_id', '=', 'exhibitions.state')
            ->join('cities', 'cities.cities_id', '=', 'exhibitions.city')
            // ->select('exhibitions.*', 'countries.name as country_name', 'states.name as state_name', 'cities.name as city_name') // Select necessary fields
            ->first();

        $exhibition->logo = isset($exhibition->logo) ? url($exhibition->logo) : null;



        if (!$exhibition || $registration->amount > 0) {
            return response()->json(['status' => 'false', 'message' => 'This exhibition requires payment.']);
        }

        $sponsors = DB::table('exhibition_sponsor')
            ->where('exhibition_id', $registration->exhibition_id)
            ->get();

        // Update registration status
        DB::table('artist_exhibition_registration')->where('registration_code', $registrationCode)->update(['status' => 'Active']);

        $spo = [];
        foreach ($sponsors as $sponsor) {
            $logo = url('/') . '/' . $sponsor->logo;
            $spo[] = [
                'logo' => $logo,
            ];
        }

        if (!empty($registration->booth_seat_id)) {
            $seat = DB::table('booth_seats')
                ->where('booth_seat_id', $registration->booth_seat_id)
                ->first();
        }
        $exhibition->seat_name = $seat->seat_name ?? null;
        $exhibition->exhibition_booth_id = $seat->exhibition_booth_id ?? null;


        $baseUrl = url('/');
        $result = [
            'exhibition' => $exhibition,
            'customer_data' => [
                'name' => $registration->name,
                'email' => $registration->email,
                'mobile' => $registration->mobile
            ],
            // 'sponsors' => $spo,
            'logo' => $baseUrl . '/' . $exhibition->logo,
            'registration_code' => $registration->registration_code,

            'registration' => $registration,
        ];


        return response()->json([
            'status' => 'true',
            'message' => 'Registration activated successfully for the free exhibition.',
            'data' => $result,
        ]);
    }



    public function admin_get_all_help_enquiry(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_unique_id' => 'required',
            'min_date' => 'nullable|date',
            'max_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        // Find the customer
        $User = User::where('user_unique_id', $request->user_unique_id)->first();
        $threeMonthsAgo = Carbon::now()->subMonths(3)->toDateString();


        // dd($customer->customer_id);
        if (!$User) {
            return response()->json([
                'status' => false,
                'message' => 'User not found.',
            ]);
        }


        $chats = HelpCenterChat::with([
            'EnquiryCategory' => function ($query) {
                $query->select('enquiry_category_id', 'enquiry_category_name');
            },
            'HelpCenterChatImages' => function ($query) {
                $query->select('help_center_image_id', 'help_center_chat_id', 'image');
            },
            'HelpCenterChatMessage' => function ($query) {
                $query->select('help_center_chat_message_id', 'help_center_chat_id', 'image', 'message', 'inserted_date', 'inserted_time')
                    ->orderByDesc('inserted_date')
                    ->orderByDesc('inserted_time');
            },
        ])
            ->where('user_id', $User->user_id)
            ->orderByDesc('inserted_date')
            ->orderByDesc('inserted_time');

            // ->get();
            if ($request->min_date) {
                $chats->where('help_center_chat.inserted_date', '>=', $request->min_date);
            }

            if ($request->max_date) {
                $chats->where('help_center_chat.inserted_date', '<=', $request->max_date);
            }
            $chats = $chats->where('inserted_date', '>=', $threeMonthsAgo)
            // ->orderBy('contact_us.contact_us_id', 'desc')
            // ->groupBy('contact_us.contact_us_id')
            ->get();


        if ($chats->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No Data Found'
            ]);
        }
        $ArtData = [];

        foreach ($chats as $chat) {


            $customer = Customer::where('customer_id', $chat->customer_id)->first();


            $lastMessagae = DB::table('help_center_chat_message')
                ->where('help_center_chat_id', $chat->help_center_chat_id)
                ->orderBy('inserted_date', 'desc')
                ->orderBy('inserted_time', 'desc')
                ->first();

            $help_center_chat = DB::table('help_center_chat')
                ->where('help_center_chat_id', $chat->help_center_chat_id)
                ->first();

            $help_center_issue_images = DB::table('help_center_images')
                ->where('help_center_chat_id', $chat->help_center_chat_id)
                ->get();

            foreach ($help_center_issue_images as $help_center_issue_image) {
                $help_center_issue_image->image = isset($help_center_issue_image->image) ? url($help_center_issue_image->image) : null;
            }


            $ArtData[] = [
                'reciver_unique_id' => $customer->customer_unique_id,
                'reciver_role' => $customer->role,
                'reciver_name' => $customer->name,
                'help_center_issue_images' => $help_center_issue_images,
                'inserted_date' => $chat->inserted_date,
                'inserted_time' => $chat->inserted_time,
                'help_center_chat_id' => $chat->help_center_chat_id,
                'enquiry_category_name' => $chat->EnquiryCategory->enquiry_category_name,
                'last_message' => $lastMessagae ?? $help_center_chat,
            ];
        }

        return response()->json([
            'status' => true,
            'message' => 'Chat users fetched successfully.',
            'help_enuqiry_list' => $ArtData,
        ]);
    }

    public function admin_get_single_help_enquiry_chat(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'help_center_chat_id' => 'required|exists:help_center_chat,help_center_chat_id',
            'reciver_unique_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $chatInitiate = HelpCenterChat::with(['HelpCenterChatMessage', 'EnquiryCategory' => function ($query) {
            $query->select('enquiry_category_id', 'enquiry_category_name');
        }])
            ->where('help_center_chat_id', $request->help_center_chat_id)
            ->first();

        $issue = $chatInitiate->issue;

        $enquiryCategoryName = $chatInitiate->EnquiryCategory ? $chatInitiate->EnquiryCategory->enquiry_category_name : null;


        if (!$chatInitiate) {
            return response()->json([
                'status' => false,
                'message' => 'Chat not initiated or not found.',
            ]);
        }

        $helpmessage = DB::table('help_center_chat')->where('help_center_chat_id', $request->help_center_chat_id)->first();

        // dd($helpmessage->issue);
        $customer_with_chat = Customer::where('customer_unique_id', $request->reciver_unique_id)->first();


        $reciver_data = [
            'reciver_unique_id' => $customer_with_chat->customer_unique_id,
            'reciver_profile_image' => isset($customer_with_chat->customer_profile) ? url($customer_with_chat->customer_profile) : null,
            'reciver_name' => $customer_with_chat->name,
            'reciver_email' => $customer_with_chat->email,
            'reciver_mobile' => $customer_with_chat->mobile,
            'reciver_fcm_token' => $customer_with_chat->fcm_token,
            'reciver_latitude' => $customer_with_chat->latitude,
            'reciver_longitude' => $customer_with_chat->longitude,
            'reciver_role' => $customer_with_chat->role,

        ];
        $issueImages = DB::table('help_center_images')
            ->where('help_center_chat_id', $chatInitiate->help_center_chat_id)
            ->get()
            ->map(function ($issueImage) {
                $issueImage->image = isset($issueImage->image) ? url($issueImage->image) : null;
                return $issueImage;
            });



        $chatDetails = $chatInitiate->HelpCenterChatMessage->map(function ($message) {

            if ($message->role == 'superadmin') {
                $receiver_unique_id = Customer::where('customer_id', $message->receiver_id)->value('customer_unique_id');
                $sender_unique_id = User::where('user_id', $message->sender_id)->value('user_unique_id');
            } else {

                $sender_unique_id = Customer::where('customer_id', $message->sender_id)->value('customer_unique_id');
                $receiver_unique_id = User::where('user_id', $message->receiver_id)->value('user_unique_id');
            }




            return [
                'sender_id' => $message->sender_id,
                'receiver_id' => $message->receiver_id,
                'sender_unique_id' => $sender_unique_id,
                'receiver_unique_id' => $receiver_unique_id,
                // 'reciver_fcm_token' => $reciver_fcm_token,
                'inserted_date' => $message->inserted_date,
                'inserted_time' => $message->inserted_time,
                'message' => $message->message ?? null,
                'image' => $message->image ? url($message->image) : null,
            ];
        });

        $data = [
            'help_center_chat_id' => $request->help_center_chat_id,
            'chatDetails' => isset($chatDetails) ? $chatDetails : $helpmessage,
            'enquiryCategoryName' => $enquiryCategoryName,
            'issue' => $issue,
            'issueImages' => $issueImages,
        ];

        return response()->json([
            'status' => true,
            'help_center_chat_id' => $request->help_center_chat_id,
            'chatDetails' => isset($chatDetails) ? $chatDetails : $helpmessage,
            'enquiryCategoryName' => $enquiryCategoryName,
            'issue' => $issue,
            'issueImages' => $issueImages,
            'reciver_data' => $reciver_data,
        ]);
    }




    public function admin_get_art_enquiry(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'min_date' => 'nullable|date',
            'max_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }
        $threeMonthsAgo = Carbon::now()->subMonths(3)->toDateString();

        $chats = ArtEnquiryChat::with(['ArtEnquiryChatMessage' => function ($query) use ($threeMonthsAgo) {
            $query->select('art_enquiry_chat_message_id', 'art_enquiry_chat_id', 'message', 'images', 'status', 'inserted_date', 'inserted_time')
                ->where('inserted_date', '>=', $threeMonthsAgo)
                ->orderByDesc('inserted_date')
                ->orderByDesc('inserted_time');
        }])
            ->where('inserted_date', '>=', $threeMonthsAgo) // Filter chats within the last 3 months
            ->orderByDesc('inserted_date')
            ->orderByDesc('inserted_time');
            // ->get();
            if ($request->min_date) {
                $chats->where('art_enquiry_chat.inserted_date', '>=', $request->min_date);
            }

            if ($request->max_date) {
                $chats->where('art_enquiry_chat.inserted_date', '<=', $request->max_date);
            }
            $chats = $chats->where('inserted_date', '>=', $threeMonthsAgo)
            // ->orderBy('contact_us.contact_us_id', 'desc')
            // ->groupBy('contact_us.contact_us_id')
            ->get();


        $ArtData = [];
        foreach ($chats as $chat) {
            $lastMessage = DB::table('art_enquiry_chat_message')
                ->where('art_enquiry_chat_id', $chat->art_enquiry_chat_id)
                ->where('inserted_date', '>=', $threeMonthsAgo) // Filter messages within the last 3 months
                ->orderBy('inserted_date', 'desc')
                ->orderBy('inserted_time', 'desc')
                ->first();

            $artEnquiry = DB::table('art_enquiry_chat')
                ->where('art_enquiry_chat_id', $chat->art_enquiry_chat_id)
                ->first();

            // $art = Art::where('art_id', optional($chat)->art_id)->first();
            $art = !empty($chat->art_id) ? Art::where('art_id', $chat->art_id)->first() : null;

            if (!$art) {
                continue;
            }
            $artImage = ArtImage::where('art_id', optional($art)->art_id)->first();

            $customer = Customer::where('customer_id', $chat->seller_id)->first();
            $sender = Customer::where('customer_id', $chat->customer_id)->first();

            $ArtData[] = [
                'customer_unique_id' => $sender->customer_unique_id,
                'art_unique_id' => $art->art_unique_id??null,
                'title' => $art->title??null,
                'image' => isset($artImage->image) ? url($artImage->image) : null,
                'inserted_date' => $chat->inserted_date,
                'inserted_time' => $chat->inserted_time,
                'reciver_unique_id' => $customer->customer_unique_id,
                'art_enquiry_chat_id' => $chat->art_enquiry_chat_id,
                'last_message' => $lastMessage ?? $artEnquiry,
            ];
        }

        return response()->json([
            'status' => true,
            'message' => 'Chat users fetched successfully.',
            'art_enquiry_list' => $ArtData,
        ]);
    }


    public function admin_get_single_art_enquiry_chat_app(Request $request)
    {
        // Validate the input
        $validator = Validator::make($request->all(), [
            'art_enquiry_chat_id' => 'required|exists:art_enquiry_chat,art_enquiry_chat_id',
            'reciver_unique_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $chatInitiate = ArtEnquiryChat::with(['ArtEnquiryChatMessage'])
            ->where('art_enquiry_chat_id', $request->art_enquiry_chat_id)
            ->first();




        if (!$chatInitiate) {
            return response()->json([
                'status' => false,
                'message' => 'Chat not initiated or not found.',
            ], 404);
        }

        $art = Art::where('art_id', $chatInitiate->art_id)->first();
        $artImage = ArtImage::where('art_id', $chatInitiate->art_id)->first();

        $artData = [
            'title' => $art->title ?? null,
            'art_unique_id' => $art->art_unique_id ?? null,
            'image' => isset($artImage->image) ? url($artImage->image) : null,
        ];

        $customer_with_chat = Customer::where('customer_unique_id',  $request->reciver_unique_id)->first();




        // $reciver_data = [
        //     'reciver_unique_id' => $customer_with_chat->customer_unique_id,
        //     'reciver_profile_image' => isset($customer_with_chat->customer_profile_image) ? url($customer_with_chat->customer_profile_image) : null,
        //     'reciver_name' => $customer_with_chat->full_name,
        //     'reciver_email' => $customer_with_chat->email,
        //     'reciver_mobile' => $customer_with_chat->mobile,
        //     'reciver_fcm_token' => $customer_with_chat->fcm_token,
        //     'reciver_latitude' => $customer_with_chat->latitude,
        //     'reciver_longitude' => $customer_with_chat->longitude,
        //     'reciver_role' => $customer_with_chat->role,

        // ];


        $art_enquiry_chat = ArtEnquiryChat::where('art_enquiry_chat_id', $request->art_enquiry_chat_id)->first();
        $senderData = Customer::where('customer_id', $art_enquiry_chat->customer_id)->first();
        $receiverData = Customer::where('customer_id', $art_enquiry_chat->seller_id)->first();

        $customerData = [
            'customer_unique_id' => $senderData->customer_unique_id,
            'name' => $senderData->name,
            'customer_profile' => isset($senderData->customer_profile) ? url($senderData->customer_profile) : null,
        ];

        $reciverData = [
            'customer_unique_id' => $receiverData->customer_unique_id,
            'name' => $receiverData->name,
            'customer_profile' => isset($receiverData->customer_profile) ? url($receiverData->customer_profile) : null,

        ];




        $chatDetails = $chatInitiate->ArtEnquiryChatMessage->map(function ($message) {
            $sender_unique_id = Customer::where('customer_id', $message->sender_id)->value('customer_unique_id');
            $receiver_unique_id = Customer::where('customer_id', $message->receiver_id)->value('customer_unique_id');



            $customer_with_chat = Customer::where('customer_id',  $message->receiver_id)->first();


            return [
                'sender_id' => $message->sender_id,
                'receiver_id' => $message->receiver_id,
                'sender_unique_id' => $sender_unique_id,
                'receiver_unique_id' => $receiver_unique_id,
                'reciver_fcm_token' => $customer_with_chat->fcm_token ?? null,
                'inserted_date' => $message->inserted_date,
                'inserted_time' => $message->inserted_time,
                'message' => $message->message ?? null,
                'image' => $message->images ? url($message->images) : null,
            ];
        });

        // $data = [
        //     'reciver_fcm_token' => $customer_with_chat->fcm_token,
        //     'art_enquiry_chat_id' => $request->art_enquiry_chat_id,
        //     'chatDetails' => $chatDetails,
        //     'artData' => $artData,
        // ];



        return response()->json([
            'status' => true,
            'reciver_fcm_token' => $customer_with_chat->fcm_token ?? null,
            'art_enquiry_chat_id' => $request->art_enquiry_chat_id,
            'chatDetails' => $chatDetails,
            'artData' => $artData,
            'customerData' => $customerData,
            'reciverData' => $reciverData,

        ]);
    }

    public function mockup_images(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'art_unique_id' => 'required',
            'data' => 'required|array'
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
                'message' => 'No Data found for the provided art_unique_id.',
            ]);
        }
        $data = $request->data;
        $new = [];
        $currentDateTime = Carbon::now();
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();
        foreach ($data as $index => $image) {
            switch ($index) {
                case 0:
                    $artType = 'Mocup 1';
                    break;
                case 1:
                    $artType = 'Mocup 2';
                    break;
                case 2:
                    $artType = 'Mocup 3';
                    break;
                case 3:
                    $artType = 'Mocup 4';
                    break;
                case 4:
                    $artType = 'Mocup 5';
                    break;
                case 5:
                    $artType = 'Mocup 6';
                    break;
                default:
                    $artType = 'Mocup Extra';
            }
            $new[] = [
                'art_id' => $art->art_id,
                'art_type' => $artType,
                'image' => $image,
                'inserted_date' => $insertDate,
                'inserted_time' => $insertTime,
            ];
        }

        $added = DB::table('art_images')->insert($new);

        if ($added) {
            return response()->json([
                'status' => true,
                'message' => 'Images have been successfully uploaded.',
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Internal Server error',
            ]);
        }
    }


    public function intern_form(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required',
            'mobile' => 'required',
            'message' => 'required',
            'document' => 'required|image|max:2048',
            'role' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $currentDateTime = Carbon::now();
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $fileName = time() . '_' . Str::random(5) . '.' . $file->getClientOriginalExtension();
            $filePath = 'intern/document/' . $fileName;
            $file->move(public_path('intern/document'), $fileName);


            $currentDateTime = Carbon::now('Asia/Kolkata');
            $data = DB::table('intern_form')
                ->insert([
                    'name' => $request->name,
                    'email' => $request->email,
                    'mobile' => $request->mobile,
                    'role' => $request->role,
                    'message' => $request->message,
                    'document' => $filePath,
                    'inserted_date' => $insertDate,
                    'inserted_time' => $insertTime,
                ]);



            return response()->json([
                'status' => true,
                'message' => 'Form added successfully.',
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'No  file provided.',
            ], 400);
        }
    }

    public function update_donation_image(Request $request)
    {

        if ($request->hasFile(' ') && $request->file('donation_image')->isValid()) {
            $file = $request->file('donation_image');

            $fileName = time() . '.' . $file->getClientOriginalExtension();

            $file->move(public_path('donation_image'), $fileName);

            $filePath = 'donation_image/' . $fileName;
        } else {
            $filePath = $request->donation_image;
        }

        DB::table('donation_page_images')->where('donation_page_images_id', $request->donation_page_images_id)->update([
            'images' => $filePath,
        ]);
    }


    public function adminsendPrivateMessagecustomer(Request $request)
    {

        // if (!Auth::guard('customer_api')->check()) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Unauthorized access.',
        //     ], 400);
        // }

        $validator = Validator::make($request->all(), [
            'private_enquiry_chat_id' => 'required|exists:private_enquiry_chat,private_enquiry_chat_id',
            'message' => 'nullable|string',
            'sender_unique_id' => 'required|string',
            'receiver_unique_id' => 'required|string',
            'images' => 'nullable|file',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $sender = User::where('user_unique_id', $request->sender_unique_id)->first();
        $chatInitiate = PrivateEnquiryChat::where('private_enquiry_chat_id', $request->private_enquiry_chat_id)->first();
        $receiverData = Customer::where('customer_unique_id', $request->receiver_unique_id)->first();

        if (!$chatInitiate) {
            return response()->json([
                'status' => false,
                'message' => 'Chat not found.',
            ]);
        }

        $filePath = null;

        if ($request->hasFile('images') && $request->file('images')->isValid()) {
            $file = $request->file('images');

            $fileName = time() . '.' . $file->getClientOriginalExtension();

            $file->move(public_path('privateArtEnquiry/images'), $fileName);

            $filePath = 'privateArtEnquiry/images/' . $fileName;
        }


        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();


        $message = PrivateEnquiryChatMessage::create([
            'private_enquiry_chat_id' => $request->private_enquiry_chat_id,
            'sender_id' => $sender->user_id,
            'receiver_id' => $receiverData->customer_id,
            'message' => $request->message,
            'role' => $sender->role,
            'images' => $filePath,
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ]);

        $data = [
            'private_enquiry_chat_id' => $request->private_enquiry_chat_id,
            'sender_id' => $sender->user_id,
            'sender_unique_id' => $request->sender_unique_id,
            'receiver_id' => $receiverData->customer_id,
            'receiver_unique_id' => $request->receiver_unique_id,
            'message' => $request->message,
            'image' => url($filePath),
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ];


        return response()->json([
            'status' => true,
            'message' => $data
        ]);
    }

    public function admin_get_all_private_art_enquiry(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_unique_id' => 'required',
            'min_date' => 'nullable|date',
            'max_date' => 'nullable|date',
        ]);


        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        $User = User::where('user_unique_id', $request->user_unique_id)->first();
        $threeMonthsAgo = Carbon::now()->subMonths(3)->toDateString();


        $chats = PrivateEnquiryChat::with(['PrivateEnquiryChatMessage' => function ($query) {
            $query->select('private_enquiry_chat_message_id', 'private_enquiry_chat_id', 'message', 'images', 'status', 'inserted_date', 'inserted_time')
                ->orderByDesc('inserted_date')
                ->orderByDesc('inserted_time');
        }])
            ->Where('user_id', $User->user_id)
            ->Where('role', 'customer')
            ->orderByDesc('inserted_date')
            ->orderByDesc('inserted_time');
            // ->get();
            if ($request->min_date) {
                $chats->where('private_enquiry_chat.inserted_date', '>=', $request->min_date);
            }

            if ($request->max_date) {
                $chats->where('private_enquiry_chat.inserted_date', '<=', $request->max_date);
            }
            $chats = $chats->where('inserted_date', '>=', $threeMonthsAgo)
            // ->orderBy('contact_us.contact_us_id', 'desc')
            // ->groupBy('contact_us.contact_us_id')
            ->get();


        $ArtData = [];
        foreach ($chats as $chat) {

            $existingEnquiry = PrivateEnquiryChat::where('seller_id', $chat->seller_id)
                ->where('role', 'superadmin')
                ->where('art_id', $chat->art_id)
                ->first();

                $isSold=DB::table('private_ordered_art')
                ->where('art_id',$chat->art_id)
                // ->where('customer_id',$chat->customer_id)
                ->first();
                $isSell=true;
                if($isSold){
                    $isSell=false;
                }
                $isCustomerSold=DB::table('private_ordered_art')
                ->where('art_id',$chat->art_id)
                ->where('customer_id',$chat->customer_id)
                ->first();
                $isCustomerPurchased=false;
                if($isCustomerSold){
                    $isCustomerPurchased=true;
                }
            //    dd($chat);
            $art = Art::where('art_id', $chat->art_id)->first();
            if(!$art){
                continue;
            }
            $art = $art ?? null;
            $lastMessage = $chat->PrivateEnquiryChatMessage->first();
            // $artImage = ArtImage::where('art_id', $art->art_id)->first();
            $artImage = $art ? ArtImage::where('art_id', $art->art_id)->first() : null;
            $artImageUrl = $artImage ? url($artImage->image) : null;

            $customer = Customer::where('customer_id', $chat->customer_id)->first();

            $customerData = [
                'customer_unique_id' => $customer->customer_unique_id,
                'customer_name' => $customer->name,

            ];
            $seller = Customer::where('customer_id', $chat->seller_id)->first();
            $ArtData[] = [
                'isSell'=>$isSell,
                'isCustomerPurchased'=>$isCustomerPurchased,
                'customer_unique_id' => $customer->customer_unique_id,
                'customer_name' => $customer->name,

                'art_unique_id' => $art->art_unique_id??null,
                'title' => $art->title??null,
                'image' => $artImageUrl ?? null,
                'reciver_unique_id' => $customer->customer_unique_id,
                'artist_unique_id' => $seller->customer_unique_id,
                'artist_name' => $seller->name,
                'inserted_date' => $chat->inserted_date,
                'inserted_time' => $chat->inserted_time,
                'private_enquiry_chat_id' => $chat->private_enquiry_chat_id,
                'seller_private_enquiry_chat_id' => $existingEnquiry->private_enquiry_chat_id ?? null,
                'last_message' => $lastMessage,
                // 'admin_private_enquiry_chat_message_id'=>$chat->admin_private_enquiry_chat_message_id,
                // 'chat'=>$chat,
            ];
        }
        return response()->json([
            'status' => true,
            'message' => 'Chat users fetched successfully.',
            'private_enuqiry_list' => $ArtData,
        ]);
    }

    public function admin_get_single_private_art_enquiry(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'private_enquiry_chat_id' => 'required|exists:private_enquiry_chat,private_enquiry_chat_id',
            'reciver_unique_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $chatInitiate = PrivateEnquiryChat::with(['PrivateEnquiryChatMessage'])
            ->where('private_enquiry_chat_id', $request->private_enquiry_chat_id)
            ->first();




        if (!$chatInitiate) {
            return response()->json([
                'status' => false,
                'message' => 'Chat not initiated or not found.',
            ], 404);
        }

        $art = Art::where('art_id', $chatInitiate->art_id)->first();
        $artImage = ArtImage::where('art_id', $chatInitiate->art_id)->first();

        $artData = [
            'title' => $art->title,
            'art_unique_id' => $art->art_unique_id,
            'image' => isset($artImage->image) ? url($artImage->image) : null,
        ];


        // $isVerify = DB::table('payment_link')
        //     ->where('private_enquiry_chat_id', $request->private_enquiry_chat_id)
        //     ->where('customer_id', $chatInitiate->customer_id)
        //     ->exists();





        $chatDetails = $chatInitiate->PrivateEnquiryChatMessage->map(function ($message) {

            if ($message->role == 'superadmin') {

                $sender_unique_id = User::where('user_id', $message->sender_id)->value('user_unique_id');
                $receiver_unique_id = Customer::where('customer_id', $message->receiver_id)->value('customer_unique_id');
            } else {
                $sender_unique_id = Customer::where('customer_id', $message->sender_id)->value('customer_unique_id');
                $receiver_unique_id = User::where('user_id', $message->receiver_id)->value('user_unique_id');
            }





            return [
                'sender_id' => $message->sender_id,
                'receiver_id' => $message->receiver_id,
                'sender_unique_id' => $sender_unique_id,
                'receiver_unique_id' => $receiver_unique_id,
                // 'reciver_fcm_token' => $reciver_fcm_token??null,
                'inserted_date' => $message->inserted_date,
                'inserted_time' => $message->inserted_time,
                'message' => $message->message ?? null,
                'image' => $message->images ? url($message->images) : null,
            ];
        });

        $data = [
            // 'reciver_fcm_token' => $customer_with_chat->fcm_token,
            'private_enquiry_chat_id' => $request->private_enquiry_chat_id,
            'chatDetails' => $chatDetails,
            'artData' => $artData,
        ];

        return response()->json([
            'status' => true,
            'private_enquiry_chat_id' => $request->private_enquiry_chat_id,
            'chatDetails' => $chatDetails,
            'artData' => $artData,
            // 'isVerify'=>$isVerify
        ]);
    }
    public function admin_get_single_private_art_enquiry_seller(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'private_enquiry_chat_id' => 'required|exists:private_enquiry_chat,private_enquiry_chat_id',
            'reciver_unique_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $chatInitiate = PrivateEnquiryChat::with(['AdminPrivateEnquiryChatMessage'])
            ->where('private_enquiry_chat_id', $request->private_enquiry_chat_id)
            ->first();




        if (!$chatInitiate) {
            return response()->json([
                'status' => false,
                'message' => 'Chat not initiated or not found.',
            ], 404);
        }

        $art = Art::where('art_id', $chatInitiate->art_id)->first();
        $artImage = ArtImage::where('art_id', $chatInitiate->art_id)->first();

        $artData = [
            'title' => $art->title,
            'art_unique_id' => $art->art_unique_id,
            'image' => isset($artImage->image) ? url($artImage->image) : null,
        ];








        $chatDetails = $chatInitiate->AdminPrivateEnquiryChatMessage->map(function ($message) {

            if ($message->role == 'superadmin') {

                $sender_unique_id = User::where('user_id', $message->sender_id)->value('user_unique_id');
                $receiver_unique_id = Customer::where('customer_id', $message->receiver_id)->value('customer_unique_id');
            } else {
                $sender_unique_id = Customer::where('customer_id', $message->sender_id)->value('customer_unique_id');
                $receiver_unique_id = User::where('user_id', $message->receiver_id)->value('user_unique_id');
            }





            return [
                'sender_id' => $message->sender_id,
                'receiver_id' => $message->receiver_id,
                'sender_unique_id' => $sender_unique_id,
                'receiver_unique_id' => $receiver_unique_id,
                // 'reciver_fcm_token' => $reciver_fcm_token??null,
                'inserted_date' => $message->inserted_date,
                'inserted_time' => $message->inserted_time,
                'message' => $message->message ?? null,
                'image' => $message->images ? url($message->images) : null,
            ];
        });

        $data = [
            // 'reciver_fcm_token' => $customer_with_chat->fcm_token,
            'private_enquiry_chat_id' => $request->private_enquiry_chat_id,
            'chatDetails' => $chatDetails,
            'artData' => $artData,
        ];

        return response()->json([
            'status' => true,
            'private_enquiry_chat_id' => $request->private_enquiry_chat_id,
            'chatDetails' => $chatDetails,
            'artData' => $artData,
        ]);
    }
    public function adminsendPrivateMessageseller(Request $request)
    {

        // if (!Auth::guard('customer_api')->check()) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Unauthorized access.',
        //     ], 400);
        // }

        $validator = Validator::make($request->all(), [
            'private_enquiry_chat_id' => 'required|exists:private_enquiry_chat,private_enquiry_chat_id',
            'message' => 'nullable|string',
            'sender_unique_id' => 'required|string',
            'receiver_unique_id' => 'required|string',
            'images' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $sender = User::where('user_unique_id', $request->sender_unique_id)->first();
        $chatInitiate = PrivateEnquiryChat::where('private_enquiry_chat_id', $request->private_enquiry_chat_id)->first();
        $receiverData = Customer::where('customer_unique_id', $request->receiver_unique_id)->first();

        if (!$chatInitiate) {
            return response()->json([
                'status' => false,
                'message' => 'Chat not found.',
            ]);
        }

        $filePath = null;

        if ($request->hasFile('images') && $request->file('images')->isValid()) {
            $file = $request->file('images');

            $fileName = time() . '.' . $file->getClientOriginalExtension();

            $file->move(public_path('privateArtEnquiry/images'), $fileName);

            $filePath = 'privateArtEnquiry/images/' . $fileName;
        }


        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();


        $message = AdminPrivateEnquiryChatMessage::create([
            'private_enquiry_chat_id' => $request->private_enquiry_chat_id,
            'sender_id' => $sender->user_id,
            'receiver_id' => $receiverData->customer_id,

            'role' => $sender->role,
            'images' => $filePath,
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ]);

        $data = [
            'private_enquiry_chat_id' => $request->private_enquiry_chat_id,
            'sender_id' => $sender->user_id,
            'sender_unique_id' => $request->sender_unique_id,
            'receiver_id' => $receiverData->customer_id,
            'receiver_unique_id' => $request->receiver_unique_id,
            'message' => $request->message,
            'image' => url($filePath),
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ];


        return response()->json([
            'status' => true,
            'message' => $data
        ]);
    }


    public function about()
    {
        $about = About::where('status', 'Active')->first();
        $about->image = isset($about->image) ? url($about->image) : null;
        $about = $about ? $about : null;

        $aboutAnalytics = DB::table('about_analytics')->get();
        foreach ($aboutAnalytics as $data) {
            $data->icon = isset($data->icon) ? url($data->icon) : null;
        }
        $aboutAnalytics = $aboutAnalytics->isEmpty() ? [] : $aboutAnalytics;

        $aboutbanner = DB::table('about_images')->where('type', 'banner')->get();
        foreach ($aboutbanner as $data) {
            $data->image = isset($data->image) ? url($data->image) : null;
        }
        $aboutbanner = $aboutbanner->isEmpty() ? [] : $aboutbanner;

        $aboutimage = DB::table('about_images')->where('type', 'image')->get();
        foreach ($aboutimage as $data) {
            $data->image = isset($data->image) ? url($data->image) : null;
        }
        $aboutimage = $aboutimage->isEmpty() ? [] : $aboutimage;

        $aboutPara = DB::table('about_para')->get();
        $aboutPara = $aboutPara->isEmpty() ? [] : $aboutPara;

        $response = [
            'about' => $about,
            'aboutAnalytics' => $aboutAnalytics,
            'aboutBanner' => $aboutbanner,
            'aboutImage' => $aboutimage,
            'aboutPara' => $aboutPara
        ];

        return response()->json([
            'status' => true,
            'data' => $response
        ]);
    }

    public function get_buy_processdata(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:buy,sell'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $data = DB::table('how_buy')
            ->select('title', 'sub_heading', 'para', 'links', 'sequence')
            ->where('type', $request->type)
            ->orderBy('sequence', 'asc')
            ->get();


        return response()->json([
            'status' => true,
            'data' => $data

        ]);
    }
    // public function get_buy_processdata(Request $request)
    // {
    //     $title = DB::table('how_buy')
    //         ->where('title', '!=', '')
    //         ->orderBy('how_buy_id', 'asc')
    //         ->get();

    //     $title = $title->map(function ($item) {
    //         return [
    //             'how_buy_id' => $item->how_buy_id,
    //             'title' => $item->title,
    //         ];
    //     });

    //     $sub_heading = DB::table('how_buy')
    //         ->where('sub_heading', '!=', '')
    //         ->orderBy('how_buy_id', 'asc')
    //         ->get();

    //     $sub_heading = $sub_heading->map(function ($item) {
    //         return [
    //             'how_buy_id' => $item->how_buy_id,
    //             'sub_heading' => $item->sub_heading,
    //         ];
    //     });

    //     $para = DB::table('how_buy')
    //         ->where('para', '!=', '')
    //         ->orderBy('how_buy_id', 'asc')
    //         ->get();

    //     $para = $para->map(function ($item) {
    //         return [
    //             'how_buy_id' => $item->how_buy_id,
    //             'para' => $item->para,
    //         ];
    //     });

    //     $links = DB::table('how_buy')
    //         ->where('links', '!=', '')
    //         ->orderBy('how_buy_id', 'asc')
    //         ->get();

    //     $links = $links->map(function ($item) {
    //         return [
    //             'how_buy_id' => $item->how_buy_id,
    //             'links' => $item->links,
    //         ];
    //     });

    //     $data =[
    //         'title' => $title,
    //         'sub_heading' => $sub_heading,
    //         'para' => $para,
    //         'links' => $links,
    //     ];
    //     return response()->json([
    //         'status' => true,
    //         'data'=>$data

    //     ]);
    // }


    public function get_newsletter()
    {
        $newsletters = DB::table('newsletter')
            ->select('newsletter_id', 'heading', 'image1', 'status')
            // ->where('status', 'Active')
            ->orderBy('newsletter_id', 'DESC')
            ->get();
        $result = null;
        foreach ($newsletters as $newsletter) {
            $content_data = DB::table('newsletter_content')
                ->select('sub_heading', 'paragraph')
                ->where('newsletter_id', $newsletter->newsletter_id)
                ->get();

            // dd($content_data);
            $ar = [
                'newsletter_id' => $newsletter->newsletter_id,
                'image' => $newsletter->image1,
                'title' => $newsletter->heading,
                'status' => $newsletter->status,
            ];
            if ($content_data->isNotEmpty()) {
                $ar['sub_title'] = $content_data[0]->sub_heading;
                $ar['description'] = $content_data[0]->paragraph;
            } else {
                $ar['sub_title'] = null;
                $ar['description'] = null;
            }
            $result[] = $ar;
        }
        return response()->json([
            'status' => true,
            'news' => $result
        ]);
    }

    // public function add_news(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'title' => 'required',
    //         'sub_title' => 'required',
    //         'description' => 'required',
    //         'images' => 'required',
    //         'location' => 'required',
    //         'date' => 'required',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => $validator->errors()->first(),
    //         ], 400);
    //     }
    //     if ($request->hasFile('images')) {
    //         $file = $request->file('images');
    //         $fileName = time() . '_' . Str::random(5) . '.' . $file->getClientOriginalExtension();
    //         $filePath = 'news/images/' . $fileName;
    //         $file->move(public_path('news/images/'), $fileName);


    //         $currentDateTime = Carbon::now('Asia/Kolkata');
    //         $news_id = DB::table('news')->insertGetId([
    //             'title' => $request->title,
    //             'sub_title' => $request->sub_title,
    //             'description' => $request->description,
    //             'location' => $request->location,
    //             'date' => $request->date,
    //             'images' => $filePath,
    //             'status' => 'Active',
    //             'inserted_date' => $currentDateTime->toDateString(),
    //             'inserted_time' => $currentDateTime->toTimeString(),
    //         ]);

    //         if ($request->hasFile('image')) {
    //             $file = $request->file('image');
    //             $fileName = time() . '_' . Str::random(5) . '.' . $file->getClientOriginalExtension();
    //             $filePath = 'news/images/' . $fileName;
    //             $file->move(public_path('news/images'), $fileName);


    //             $currentDateTime = Carbon::now('Asia/Kolkata');
    //             $data = DB::table('news_image')->insert([
    //                 'news_id' => $news_id,
    //                 'image' => $filePath,

    //             ]);

    //             $data = DB::table('news_image')->where('news_id', $news_id)->get();

    //             foreach ($data as $value) {
    //                 $value->image = isset($value->image) ? url($value->image) : null;
    //             }


    //             return response()->json([
    //                 'status' => true,
    //                 'message' => 'News added successfully.',
    //                 'data' => $data

    //             ]);
    //         }

    //         if ($request->has('para')) {
    //             $paragraphs = $request->para;
    //             foreach ($paragraphs as $paragraph) {
    //                 DB::table('news_content')->insert([
    //                     'news_id' => $news_id,
    //                     'para' => $paragraph,

    //                 ]);
    //             }
    //         }



    //         return response()->json([
    //             'status' => true,
    //             'message' => 'News added successfully.',

    //         ]);
    //     } else {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'No image file provided.',
    //         ], 400);
    //     }
    // }

    public function add_news(Request $request)
    {
        try {
            // Validate input
            $validator = Validator::make($request->all(), [
                'title'       => 'required|string|max:255',
                'sub_title'   => 'required|string|max:255',
                'description' => 'required|string',
                'images'      => 'required|image|mimes:jpeg,png,jpg,gif,svg',
                'location'    => 'required|string|max:255',
                'date'        => 'required',
                'image.*'     => 'required|image|mimes:jpeg,png,jpg,gif,svg',
                'para'   => 'required|array',
                'para.*' => 'required|string',
            ], [
                'para.required'   => 'The paragraph field is required.',
                'para.array'      => 'The paragraph field must be an array.',
                'para.*.required' => 'Paragraph entry is required.',
                'para.*.string'   => 'Paragraph entry must be a string.'

            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => false,
                    'message' => $validator->errors()->first(),
                ], 200);
            }

            // Upload main image
            if (!$request->hasFile('images')) {
                return response()->json([
                    'status'  => false,
                    'message' => 'No image file provided.',
                ], 200);
            }

            $file = $request->file('images');
            $fileName = time() . '_' . Str::random(5) . '.' . $file->getClientOriginalExtension();
            $filePath = 'news/images/' . $fileName;
            $file->move(public_path('news/images/'), $fileName);

            // Insert news data
            $currentDateTime = Carbon::now('Asia/Kolkata');
            $news_id = DB::table('news')->insertGetId([
                'title'         => $request->title,
                'sub_title'     => $request->sub_title,
                'description'   => $request->description,
                'location'      => $request->location,
                'date'          => $request->date,
                'images'        => $filePath,
                'status'        => 'Active',
                'inserted_date' => $currentDateTime->toDateString(),
                'inserted_time' => $currentDateTime->toTimeString(),
            ]);

            // Upload multiple images for news
            $newsImages = [];
            if ($request->hasFile('image')) {
                foreach ($request->file('image') as $file) {
                    $imageFileName = time() . '_' . Str::random(5) . '.' . $file->getClientOriginalExtension();
                    $imageFilePath = 'news/images/' . $imageFileName;
                    $file->move(public_path('news/images/'), $imageFileName);

                    DB::table('news_image')->insert([
                        'news_id' => $news_id,
                        'image'   => $imageFilePath,
                    ]);

                    $newsImages[] = url($imageFilePath);
                }
            }

            // Insert news paragraphs if provided
            if ($request->has('para') && is_array($request->para)) {
                $paragraphs = array_filter($request->para, function ($paragraph) {
                    return !empty($paragraph);
                });

                if (!empty($paragraphs)) {
                    foreach ($paragraphs as $paragraph) {
                        DB::table('news_content')->insert([
                            'news_id' => $news_id,
                            'para'    => $paragraph,
                        ]);
                    }
                }
            }

            return response()->json([
                'status'   => true,
                'message'  => 'News added successfully.',
                'news_id'  => $news_id,
                'image'    => url($filePath),
                'gallery'  => $newsImages,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong. Please try again!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
    // public function add_news(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'title' => 'required',
    //         'sub_title' => 'required',
    //         'description' => 'required',
    //         'images' => 'required',
    //         'location' => 'required',
    //         'date' => 'required',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => $validator->errors()->first(),
    //         ], 400);
    //     }
    //     if ($request->hasFile('images')) {
    //         $file = $request->file('images');
    //         $fileName = time() . '_' . Str::random(5) . '.' . $file->getClientOriginalExtension();
    //         $filePath = 'news/images/' . $fileName;
    //         $file->move(public_path('news/images/'), $fileName);


    //         $currentDateTime = Carbon::now('Asia/Kolkata');
    //         $image = DB::table('news')->insertGetId([
    //             'title' => $request->title,
    //             'sub_title' => $request->sub_title,
    //             'description' => $request->description,
    //             'location' => $request->location,
    //             'date' => $request->date,
    //             'images' => $filePath,
    //             'status' => 'Active',
    //             'inserted_date' => $currentDateTime->toDateString(),
    //             'inserted_time' => $currentDateTime->toTimeString(),
    //         ]);



    //         return response()->json([
    //             'status' => true,
    //             'message' => 'News added successfully.',

    //         ]);
    //     } else {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'No image file provided.',
    //         ], 400);
    //     }
    // }


    public function add_news_images(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'news_id' => 'required',
            'image' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $news = DB::table('news')->where('news_id', $request->news_id)->first();
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $fileName = time() . '_' . Str::random(5) . '.' . $file->getClientOriginalExtension();
            $filePath = 'news/images/' . $fileName;
            $file->move(public_path('news/images'), $fileName);


            $currentDateTime = Carbon::now('Asia/Kolkata');
            $data = DB::table('news_image')->insert([
                'news_id' => $news->news_id,
                'image' => $filePath,

            ]);

            $data = DB::table('news_image')->where('news_id', $news->news_id)->get();

            foreach ($data as $value) {
                $value->image = isset($value->image) ? url($value->image) : null;
            }


            return response()->json([
                'status' => true,
                'message' => 'News added successfully.',
                'data' => $data

            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'No image file provided.',
            ], 400);
        }
    }
    public function add_news_para(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'news_id' => 'required|exists:news,news_id',
            'para' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $news = DB::table('news')->where('news_id', $request->news_id)->first();

        if (!$news) {
            return response()->json([
                'status' => false,
                'message' => 'News not found.',
            ], 404);
        }

        $currentDateTime = Carbon::now('Asia/Kolkata');

        $inserted = DB::table('news_content')->insert([
            'news_id' => $news->news_id,
            'para' => $request->para,

        ]);

        if ($inserted) {
            $data = DB::table('news_content')->where('news_id', $news->news_id)->get();
            return response()->json([
                'status' => true,
                'message' => 'News paragraph added successfully.',
                'data' => $data
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Failed to add news paragraph.',
            ], 500);
        }
    }

    public function get_news()
    {
        $newsletters = DB::table('news')
            ->select('news_id', 'title', 'images', 'sub_title', 'description', 'location', 'date')
            ->where('status', 'Active')
            ->orderBy('news_id', 'DESC')
            ->get();
        if ($newsletters->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No Data Found'
            ]);
        }

        foreach ($newsletters as $newsletter) {
            $newsletter->images = isset($newsletter->images) ? url($newsletter->images) : null;
        }
        return response()->json([
            'status' => true,
            'news' => $newsletters
        ]);
    }
    public function get_singlenews(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'news_id' => 'required|exists:news,news_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $news = DB::table('news')
            ->where('news_id', $request->news_id)
            ->select('news_id', 'title', 'images', 'sub_title', 'description', 'location', 'date')
            ->first();

        if (!$news) {
            return response()->json([
                'status' => false,
                'message' => 'News not found.',
            ], 404);
        }

        $news->images = isset($news->images) ? url($news->images) : null;

        $newsImages = DB::table('news_image')
            ->where('news_id', $news->news_id)
            ->get();

        foreach ($newsImages as $newsImage) {
            $newsImage->image = isset($newsImage->image) ? url($newsImage->image) : null;
        }

        $newsPara = DB::table('news_content')
            ->where('news_id', $news->news_id)
            ->get();

        $news->newsImages = $newsImages;
        $news->newsPara = $newsPara;


        $recentNews = DB::table('news')
            ->where('news_id', '!=', $news->news_id)
            ->orderBy('date', 'desc')
            ->limit(5)
            ->select('news_id', 'title', 'sub_title', 'description', 'images', 'location', 'date')
            ->get();

        foreach ($recentNews as $recent) {
            $recent->images = isset($recent->images) ? url($recent->images) : null;
        }

        return response()->json([
            'status' => true,
            'news' => $news,
            'recentNews' => $recentNews,
        ]);
    }

    public function add_about(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $user = Auth::guard('api')->user();

        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'sub_heading' => 'required',
            'image' => 'required',
            'para' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $aboutPage = DB::table('about')->first();

        if ($aboutPage) {

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $fileName = time() . '_' . Str::random(5) . '.' . $file->getClientOriginalExtension();
                $filePath = 'about/images/' . $fileName;
                $file->move(public_path('about/images/'), $fileName);

                $timezone = $user->timezone ?? 'America/Los_Angeles';

                $currentDateTime = Carbon::now($timezone);
                DB::table('about')->where('about_id', $aboutPage->about_id)->update([
                    'title' => $request->title,
                    'sub_heading' => $request->sub_heading,
                    'para' => $request->para,
                    'image' => $filePath,
                    'status' => 'Active',
                    // 'inserted_date' => $currentDateTime->toDateString(),
                    // 'inserted_time' => $currentDateTime->toTimeString(),
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'No image file provided.',
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'About page updated successfully.',
            ]);
        } else {
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $fileName = time() . '_' . Str::random(5) . '.' . $file->getClientOriginalExtension();
                $filePath = 'about/images/' . $fileName;
                $file->move(public_path('about/images/'), $fileName);

                $timezone = $user->timezone ?? 'America/Los_Angeles';

                $currentDateTime = Carbon::now($timezone);
                DB::table('about')->insert([
                    'title' => $request->title,
                    'sub_heading' => $request->sub_heading,
                    'para' => $request->para,
                    'image' => $filePath,
                    'status' => 'Active',
                    // 'inserted_date' => $currentDateTime->toDateString(),
                    // 'inserted_time' => $currentDateTime->toTimeString(),
                ]);

                return response()->json([
                    'status' => true,
                    'message' => 'About page added successfully.',
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'No image file provided.',
                ]);
            }
        }
    }
    public function add_about_analytic(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $user = Auth::guard('api')->user();
        $validator = Validator::make($request->all(), [
            'analytic' => 'required',
            'tagline' => 'required',
            'icon' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }
        if ($request->hasFile('icon')) {
            $file = $request->file('icon');
            $fileName = time() . '_' . Str::random(5) . '.' . $file->getClientOriginalExtension();
            $filePath = 'about/icon/' . $fileName;
            $file->move(public_path('about/icon/'), $fileName);

            $timezone = $user->timezone ?? 'America/Los_Angeles';
            $currentDateTime = Carbon::now($timezone);
            $image = DB::table('about_analytics')->insertGetId([
                'analytic' => $request->analytic,
                'tagline' => $request->tagline,
                'icon' => $filePath,
                'inserted_date' => $currentDateTime->toDateString(),
                'inserted_time' => $currentDateTime->toTimeString(),
            ]);



            return response()->json([
                'status' => true,
                'message' => 'About Analytics added successfully.',

            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'No image file provided.',
            ], 400);
        }
    }

    public function add_community(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }

        $user = Auth::guard('api')->user();

        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'sub_title' => 'required',
            'image' => 'required',
            'para' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $aboutPage = DB::table('comunity')->first();

        if ($aboutPage) {

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $fileName = time() . '_' . Str::random(5) . '.' . $file->getClientOriginalExtension();
                $filePath = 'comunity/images/' . $fileName;
                $file->move(public_path('comunity/images/'), $fileName);

                $timezone = $user->timezone ?? 'America/Los_Angeles';

                $currentDateTime = Carbon::now($timezone);
                DB::table('comunity')->where('comunity_id', $aboutPage->comunity_id)->update([
                    'title' => $request->title,
                    'sub_title' => $request->sub_title,
                    'para' => $request->para,
                    'image' => $filePath,

                    'inserted_date' => $currentDateTime->toDateString(),
                    'inserted_time' => $currentDateTime->toTimeString(),
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'No image file provided.',
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'comunity page updated successfully.',
            ]);
        } else {
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $fileName = time() . '_' . Str::random(5) . '.' . $file->getClientOriginalExtension();
                $filePath = 'comunity/images/' . $fileName;
                $file->move(public_path('comunity/images/'), $fileName);

                $timezone = $user->timezone ?? 'America/Los_Angeles';

                $currentDateTime = Carbon::now($timezone);
                DB::table('comunity')->insert([
                    'title' => $request->title,
                    'sub_title' => $request->sub_title,
                    'para' => $request->para,
                    'image' => $filePath,

                    'inserted_date' => $currentDateTime->toDateString(),
                    'inserted_time' => $currentDateTime->toTimeString(),
                ]);

                return response()->json([
                    'status' => true,
                    'message' => 'comunity page added successfully.',
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'No image file provided.',
                ]);
            }
        }
    }

    public function get_comunity()
    {
        $comunity = DB::table('comunity')
            ->select('comunity_id', 'title', 'sub_title', 'para', 'image')
            ->orderBy('comunity_id', 'DESC')
            ->get();
        if ($comunity->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No comunity Found'
            ]);
        }

        foreach ($comunity as $newsletter) {
            $newsletter->image = isset($newsletter->image) ? url($newsletter->image) : null;
        }
        return response()->json([
            'status' => true,
            'comunity' => $comunity
        ]);
    }

    public function get_customer_all_enquiry(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();

        $Artchats = ArtEnquiryChat::with([
            'ArtEnquiryChatMessage' => function ($query) {
                $query->select(
                    'art_enquiry_chat_message_id',
                    'art_enquiry_chat_id',
                    'message',
                    'images',
                    'status',
                    'inserted_date',
                    'inserted_time'
                )
                    ->orderByDesc('inserted_date')
                    ->orderByDesc('inserted_time');
            }
        ])
            ->where('customer_id', $customer->customer_id)
            ->addSelect([
                'latest_message_date' => ArtEnquiryChatMessage::select('inserted_date')
                    ->whereColumn('art_enquiry_chat_id', 'art_enquiry_chat.art_enquiry_chat_id')
                    ->orderByDesc('inserted_date')
                    ->orderByDesc('inserted_time')
                    ->limit(1),

                'latest_message_time' => ArtEnquiryChatMessage::select('inserted_time')
                    ->whereColumn('art_enquiry_chat_id', 'art_enquiry_chat.art_enquiry_chat_id')
                    ->orderByDesc('inserted_date')
                    ->orderByDesc('inserted_time')
                    ->limit(1)
            ])
            ->orderByDesc('latest_message_date')
            ->orderByDesc('latest_message_time')
            ->get();


        $ArtData = [];
        foreach ($Artchats as $chat) {
            // Get the other participant in the chat

            $art = Art::where('art_id', $chat->art_id)->first();

            $lastMessagae = DB::table('art_enquiry_chat_message')
                ->where('art_enquiry_chat_id', $chat->art_enquiry_chat_id)
                ->orderByDesc('inserted_date')
                ->orderByDesc('inserted_time')
                ->first();
            $artImage = ArtImage::where('art_id', $art->art_id)->first();
            $seller = Customer::where('customer_id', $chat->seller_id)->first();
            $ArtData[] = [
                'art_unique_id' => $art->art_unique_id,
                'title' => $art->title,
                'art_type' => $art->art_type,
                'image' => isset($artImage->image) ? url($artImage->image) : null,
                'reciver_unique_id' => $seller->customer_unique_id,
                'reciver_fcm_token' => $seller->fcm_token ?? null,
                'art_enquiry_chat_id' => $chat->art_enquiry_chat_id,
                'last_message' => $lastMessagae,
            ];
        }


        $Privatechats = PrivateEnquiryChat::with(['PrivateEnquiryChatMessage' => function ($query) {
            $query->select('private_enquiry_chat_message_id', 'private_enquiry_chat_id', 'message', 'images', 'status', 'inserted_date', 'inserted_time')
                ->orderByDesc('inserted_date')
                ->orderBy('inserted_time', 'asc');
        }])
            ->Where('customer_id', $customer->customer_id)
            ->addSelect([
                'latest_message_date' => PrivateEnquiryChatMessage::select('inserted_date')
                    ->whereColumn('private_enquiry_chat_id', 'private_enquiry_chat.private_enquiry_chat_id')
                    ->orderByDesc('inserted_date')
                    ->orderByDesc('inserted_time')
                    ->limit(1),

                'latest_message_time' => PrivateEnquiryChatMessage::select('inserted_time')
                    ->whereColumn('private_enquiry_chat_id', 'private_enquiry_chat.private_enquiry_chat_id')
                    ->orderByDesc('inserted_date')
                    ->orderByDesc('inserted_time')
                    ->limit(1)
            ])
            ->orderByDesc('latest_message_date')
            ->orderByDesc('latest_message_time')
            ->get();

        $PrivateArtData = [];
        foreach ($Privatechats as $chat) {
            $isSold=DB::table('private_ordered_art')
                    ->where('art_id',$chat->art_id)
                    ->where('customer_id',$customer->customer_id)
                    ->first();
                    $isSell=false;
                    if($isSold){
                        $isSell=true;
                    }
            $art = Art::where('art_id', $chat->art_id)->first();
            $lastMessagae = DB::table('private_enquiry_chat_message')
                ->where('private_enquiry_chat_id', $chat->private_enquiry_chat_id)
                // ->orderBy('inserted_date', 'desc')
                ->orderBy('inserted_time', 'desc')
                ->first();
            $artImage = ArtImage::where('art_id', $art->art_id)->first();
            $user = User::where('user_id', $chat->user_id)->first();
            $PrivateArtData[] = [
                'isSell'=>$isSell,
                'art_unique_id' => $art->art_unique_id,
                'title' => $art->title,
                'art_type' => $art->art_type,
                'image' => isset($artImage->image) ? url($artImage->image) : null,
                'reciver_unique_id' => $user->user_unique_id,
                'reciver_fcm_token' => $user->fcm_token ?? null,
                'private_enquiry_chat_id' => $chat->private_enquiry_chat_id,
                'last_message' => $lastMessagae,
            ];
        }

        $data = [
            'art_enuqiry_list' => $ArtData,
            'private_enuqiry_list' => $PrivateArtData
        ];
        return response()->json([
            'status' => true,
            'message' => 'Chat users fetched successfully.',
            'data' => $data,
        ]);
    }

    public function home_category_with_subcategory()
    {
        $categories = DB::table('category')
            ->select('category.category_id', 'category.category_name', 'category.category_image', 'category.category_icon', 'category.sub_text')
            ->where('category.status', 'Active')
            ->get();

        if ($categories->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Categories Not Found ...'
            ]);
        }

        $categories_array = [];
        foreach ($categories as $category) {
            $subCategories = DB::table('sub_category_1')
                ->select('sub_category_1.sub_category_1_id', 'sub_category_1.sub_category_1_name')
                ->where('sub_category_1.status', 'Active')
                ->where('sub_category_1.category_id', $category->category_id)
                ->get();

            $subCategories_array = [];

            foreach ($subCategories as $subCategory) {
                $subCategories_array[] = [
                    'sub_category_id' => $subCategory->sub_category_1_id,
                    'sub_category_name' => $subCategory->sub_category_1_name,
                ];
            }

            $categories_array[] = [

                'category_id' => $category->category_id,
                'category_name' => $category->category_name,
                'category_image' => isset($category->category_image) ? url($category->category_image) : null,
                'category_icon' => $category->category_icon ? url($category->category_icon) : null,
                'sub_text' => $category->sub_text,
                'sub_categories' => $subCategories_array
            ];
        }

        return response()->json([
            'status' => true,
            'message' => 'Categories Found Successfully',
            'categories' => $categories_array
        ]);
    }

    public function subCategories(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }
        $categoryId = $request->input('category_id');

        if (empty($categoryId)) {
            return response()->json([
                'status' => false,
                'message' => 'Category ID is required'
            ]);
        }

        $categoryExists = DB::table('category')
            ->where('category_id', $categoryId)
            ->exists();

        if (!$categoryExists) {
            return response()->json([
                'status' => false,
                'message' => 'Category ID not found'
            ]);
        }
        $category = DB::table('category')
        ->where('category_id', $categoryId)
        ->first();

        $subCategories = DB::table('sub_category_1')
            ->where('category_id', $categoryId)
            ->where('status', 'Active')
            ->get();

        if ($subCategories->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Sub Categories Not Found ...'
            ]);
        }

        $discountArray = [];

        foreach ($subCategories as $subCategory) {
            $bannerData = [
                'sub_category_1_id' => $subCategory->sub_category_1_id,
                'sub_category_1_name' => $subCategory->sub_category_1_name,
                'sub_category_1_status' => $subCategory->status

            ];

            $discountArray[] = $bannerData;
        }

        return response()->json([
            'status' => true,
            'message' => 'Sub Categories Found Successfully',
            'sub_categories_array' => $discountArray,
            'category_name' => $category->category_name
        ]);
    }


    public function adminsendhelpmessage(Request $request)
    {

        // if (!Auth::guard('customer_api')->check()) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Unauthorized access.',
        //     ], 400);
        // }

        $validator = Validator::make($request->all(), [
            'help_center_chat_id' => 'required|exists:help_center_chat,help_center_chat_id',
            'message' => 'nullable|string',
            'sender_unique_id' => 'required|string',
            'receiver_unique_id' => 'required|string',
            'images' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $sender = User::where('user_unique_id', $request->sender_unique_id)->first();
        $chatInitiate = HelpCenterChat::where('help_center_chat_id', $request->help_center_chat_id)->first();
        $receiverData = Customer::where('customer_unique_id', $request->receiver_unique_id)->first();

        if (!$chatInitiate) {
            return response()->json([
                'status' => false,
                'message' => 'Chat not found.',
            ]);
        }

        $filePath = null;

        if ($request->hasFile('images') && $request->file('images')->isValid()) {
            $file = $request->file('images');

            $fileName = time() . '.' . $file->getClientOriginalExtension();

            $file->move(public_path('helpEnquiry/images'), $fileName);

            $filePath = 'helpEnquiry/images/' . $fileName;
        }


        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();


        $message = HelpCenterChatMessage::create([
            'help_center_chat_id' => $request->help_center_chat_id,
            'sender_id' => $sender->user_id,
            'receiver_id' => $receiverData->customer_id,
            'message' => $request->message,
            'role' => $sender->role,
            'image' => $filePath,
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ]);

        $data = [
            'help_center_chat_id' => $request->help_center_chat_id,
            'sender_id' => $sender->user_id,
            'sender_unique_id' => $request->sender_unique_id,
            'receiver_id' => $receiverData->customer_id,
            'receiver_unique_id' => $request->receiver_unique_id,
            'message' => $request->message,
            'image' => url($filePath),
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ];


        return response()->json([
            'status' => true,
            'message' => $data
        ]);
    }
    public function get_all_contact(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'min_date' => 'nullable|date',
            'max_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $threeMonthsAgo = Carbon::now()->subMonths(3);
        $productData = DB::table('contact_us');
        // ->where('inserted_date', '>=', $threeMonthsAgo)
        // ->get();

            if ($request->min_date) {
                $productData->where('contact_us.inserted_date', '>=', $request->min_date);
            }

            if ($request->max_date) {
                $productData->where('contact_us.inserted_date', '<=', $request->max_date);
            }



            $productData = $productData->where('inserted_date', '>=', $threeMonthsAgo)
                ->orderBy('contact_us.contact_us_id', 'desc')
                ->groupBy('contact_us.contact_us_id')
                ->get();



        if ($productData->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No Data Found'
            ]);
        }
        foreach($productData as $val){

            $val->inserted_date = date('m-d-y', strtotime($val->inserted_date));
        }


        return response()->json([
            'status' => true,
            'data' => $productData
        ]);
    }

    public function admin_contact(Request $request)
    {

        if (!Auth::guard('api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 400);
        }
        $validator = Validator::make($request->all(), [
            'contact_us_id' => 'required|exists:contact_us,contact_us_id',
            'reply' => 'required'

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }



        $contactData = DB::table('contact_us')
            ->where('contact_us_id', $request->contact_us_id)
            ->first();

        if (!$contactData) {
            return response()->json([
                'status' => false,
                'message' => 'No Contact Data Found'
            ]);
        }


        $mailData = [
            'name' => $contactData->name ?? 'User',
            'message' => $contactData->message ?? 'No message provided',
            'reply' => $request->reply
        ];
        $user = Auth::guard('api')->user();

        $userData = User::where('user_unique_id', $user->user_unique_id)->first();



        $timezone = $user->timezone ?? 'America/Los_Angeles';

        $currentDateTime = Carbon::now($timezone);

        $insertDate = $currentDateTime->format('m-d-Y');
        $insertTime = $currentDateTime->format('H:i:s'); // Correct format for time (24-hour format)


        try {
            Mail::send('emails.contact_reply', ['mailData' => $mailData], function ($message) use ($contactData) {
                $message->to($contactData->email)
                    ->subject('Response to Your Inquiry')
                    ->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
                    ->replyTo(env('MAIL_REPLY_TO_ADDRESS', env('MAIL_FROM_ADDRESS')));
            });

            DB::table('contact_us')
            ->where('contact_us_id', $request->contact_us_id)
            ->update([
                'status'=>'Completed'
            ]);

            $new = DB::table('contact_us_reply')
                ->insert([
                    'contact_us_id' => $request->contact_us_id,
                    'message' => $request->reply,
                    'inserted_date' => $insertDate,
                    'inserted_time' => $insertTime,
                ]);


            return response()->json([
                'status' => true,
                'message' => 'Reply email sent successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to send email.',
                'error' => $e->getMessage()
            ]);
        }
    }


    public function get_newsAdmin()
    {
        try {
            $newsletters = DB::table('news')
                ->select('news_id', 'title', 'images', 'sub_title', 'description', 'location', 'date', 'status')
                ->orderBy('news_id', 'DESC')
                ->get();

            if ($newsletters->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No Data Found'
                ], 404);
            }

            $newsIds = $newsletters->pluck('news_id')->toArray();

            $newsImages = DB::table('news_image')
                ->whereIn('news_id', $newsIds)
                ->get()
                ->map(function ($image) {
                    $image->image = url($image->image); // Convert path to full URL
                    return $image;
                })
                ->groupBy('news_id');

            // Fetch all related paragraphs for these news IDs
            $newsParas = DB::table('news_content')
                ->whereIn('news_id', $newsIds)
                ->get()
                ->groupBy('news_id'); // Group by news_id for easy mapping

            // Attach images and paragraphs to each news item
            foreach ($newsletters as $newsletter) {
                $newsletter->images = $newsletter->images ? url($newsletter->images) : null;
                $newsletter->newsImages = isset($newsImages[$newsletter->news_id]) ? $newsImages[$newsletter->news_id] : [];
                $newsletter->newsPara = isset($newsParas[$newsletter->news_id]) ? $newsParas[$newsletter->news_id] : [];
            }

            return response()->json([
                'status' => true,
                'news' => $newsletters
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong!',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function get_singlenewsAdmin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'news_id' => 'required|exists:news,news_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $news = DB::table('news')
            ->where('news_id', $request->news_id)
            ->select('news_id', 'title', 'images', 'sub_title', 'description', 'location', 'date', 'status')
            ->first();

        if (!$news) {
            return response()->json([
                'status' => false,
                'message' => 'News not found.',
            ], 404);
        }

        $news->images = isset($news->images) ? url($news->images) : null;

        $newsImages = DB::table('news_image')
            ->where('news_id', $news->news_id)
            ->get();

        foreach ($newsImages as $newsImage) {
            $newsImage->image = isset($newsImage->image) ? url($newsImage->image) : null;
        }

        $newsPara = DB::table('news_content')
            ->where('news_id', $news->news_id)
            ->get();

        $news->newsImages = $newsImages;
        $news->newsPara = $newsPara;



        return response()->json([
            'status' => true,
            'news' => $news,
        ]);
    }


    public function about_admin()
    {
        $about = About::first();
        $about->image = isset($about->image) ? url($about->image) : null;
        $about = $about ? $about : null;

        $aboutAnalytics = DB::table('about_analytics')->orderBy('about_analytics_id','desc')->get();
        foreach ($aboutAnalytics as $data) {
            $data->icon = isset($data->icon) ? url($data->icon) : null;
        }
        $aboutAnalytics = $aboutAnalytics->isEmpty() ? [] : $aboutAnalytics;

        // $aboutbanner = DB::table('about_images')->where('type', 'banner')->get();
        // foreach ($aboutbanner as $data) {
        //     $data->image = isset($data->image) ? url($data->image) : null;
        // }
        // $aboutbanner = $aboutbanner->isEmpty() ? [] : $aboutbanner;

        $aboutimage = DB::table('about_images')->OrderBy('about_images_id','desc')->get();
        foreach ($aboutimage as $data) {
            $data->image = isset($data->image) ? url($data->image) : null;
        }
        $aboutimage = $aboutimage->isEmpty() ? [] : $aboutimage;

        $aboutPara = DB::table('about_para')->orderBy('about_para_id','desc')->get();
        $aboutPara = $aboutPara->isEmpty() ? [] : $aboutPara;

        $response = [
            'about' => $about,
            'aboutAnalytics' => $aboutAnalytics,
            // 'aboutBanner' => $aboutbanner,
            'aboutImage' => $aboutimage,
            'aboutPara' => $aboutPara
        ];

        return response()->json([
            'status' => true,
            'data' => $response
        ]);
    }


    public function update_about_analytic(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'about_analytics_id' => 'required',
                'analytic' => 'nullable|string|max:255',
                'tagline'  => 'nullable|string|max:255',
                'icon'     => 'nullable',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => false,
                    'message' => $validator->errors()->first(),
                ], 400);
            }

            // Get request data
            $analytic = $request->input('analytic'); // Get analytic text
            $tagline  = $request->input('tagline');  // Get tagline text
            $icon     = $request->input('icon');      // Get file (if provided)

            $aboutAnalytic = DB::table('about_analytics')->where('about_analytics_id', $request->about_analytics_id)->first();

            if (!$aboutAnalytic) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Analytics record not found.',
                ], 404);
            }

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $fileName = time() . '_' . Str::random(5) . '.' . $file->getClientOriginalExtension();
                $filePath = 'about/icon/' . $fileName;
                $file->move(public_path('about/image'), $fileName);
            } else {
                $filePath = $aboutAnalytic->icon;
            }


            $currentDateTime = Carbon::now('Asia/Kolkata');
            $update = DB::table('about_analytics')->where('about_analytics_id', $request->about_analytics_id)->update([
                'analytic' => $request->analytic ?? $aboutAnalytic->analytic,
                'tagline' => $request->tagline ?? $aboutAnalytic->tagline,
                'icon' => $filePath,
                'inserted_date' => $currentDateTime->toDateString(),
                'inserted_time' => $currentDateTime->toTimeString(),
            ]);


            if ($update) {
                return response()->json([
                    'status'  => true,
                    'message' => 'About Analytics updated successfully.',
                    'data'    => $update
                ]);
            } else {
                return response()->json([
                    'status'  => false,
                    'message' => 'Something went wrong. Please try again!',

                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong. Please try again!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // public function get_art_data_admin(Request $request)
    // {


    //     $artData = DB::table('art_data')
    //     ->leftJoin('category', 'art_data.category_id', '=', 'category.category_id')
    //     ->select(
    //         'category.category_id',
    //         'category.category_name',
    //         'art_data.art_data_id',
    //         'art_data.art_data_title',
    //         'art_data.required',
    //         'art_data.status',
    //         'art_data.inserted_date',
    //         'art_data.inserted_time'
    //     )
    //     ->get();

    // $groupedData = [];
    // foreach ($artData as $data) {
    //     $categoryId = $data->category_id;

    //     if (!isset($groupedData[$categoryId])) {
    //         $groupedData[$categoryId] = [
    //             'category_id'   => $data->category_id,
    //             'category_name' => $data->category_name,
    //             'art_data'      => []
    //         ];
    //     }


    //     $groupedData[$categoryId]['art_data'][] = [
    //         'art_data_id'    => $data->art_data_id,
    //         'art_data_title' => $data->art_data_title,
    //         'required'       => $data->required,
    //         'status'         => $data->status,
    //        'inserted_date' => date('m-d-Y', strtotime($data->inserted_date)),
    //         'inserted_time'  => $data->inserted_time,
    //     ];
    // }

    // $groupedData = array_values($groupedData);



    //     if (empty($groupedData)) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'No Art data found!'
    //         ]);
    //     } else {
    //         return response()->json([
    //             'status' => true,
    //             'artData' => $groupedData
    //         ]);
    //     }
    // }
    // public function get_art_data_admin(Request $request)
    // {


    //     $artData = DB::table('art_data')
    //     ->leftJoin('category', 'art_data.category_id', '=', 'category.category_id')
    //     ->select(
    //         'category.category_id',
    //         'category.category_name',
    //         'art_data.art_data_id',
    //         'art_data.art_data_title',
    //         'art_data.required',
    //         'art_data.status',
    //         'art_data.inserted_date',
    //         'art_data.inserted_time'
    //     )
    //     ->get();

    // $groupedData = [];
    // foreach ($artData as $data) {
    //     $categoryId = $data->category_id;

    //     if (!isset($groupedData[$categoryId])) {
    //         $groupedData[$categoryId] = [
    //             'category_id'   => $data->category_id,
    //             'category_name' => $data->category_name,
    //             'art_data'      => []
    //         ];
    //     }


    //     $groupedData[$categoryId]['art_data'][] = [
    //         'art_data_id'    => $data->art_data_id,
    //         'art_data_title' => $data->art_data_title,
    //         'required'       => $data->required,
    //         'status'         => $data->status,
    //        'inserted_date' => date('m-d-Y', strtotime($data->inserted_date)),
    //         'inserted_time'  => $data->inserted_time,
    //     ];
    // }

    // $groupedData = array_values($groupedData);



    //     if (empty($groupedData)) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'No Art data found!'
    //         ]);
    //     } else {
    //         return response()->json([
    //             'status' => true,
    //             'artData' => $groupedData
    //         ]);
    //     }
    // }



    public function get_art_data_admin(Request $request)
    {
        $artData = DB::table('art_data')
        ->leftJoin('category', 'art_data.category_id', '=', 'category.category_id')
        ->select(
            'category.category_id',
            'category.category_name',
            'art_data.art_data_id',
            'art_data.art_data_title',
            'art_data.required',
            'art_data.status',
            'art_data.placeholder',
            'art_data.inserted_date',
            'art_data.inserted_time'
        )
        ->orderBy('art_data_id','desc')
        ->get();

    // $groupedData = [];
    // foreach ($artData as $data) {
    //     $categoryId = $data->category_id;

    //     if (!isset($groupedData[$categoryId])) {
    //         $groupedData[$categoryId] = [
    //             'category_id'   => $data->category_id,
    //             'category_name' => $data->category_name,
    //             'art_data'      => []
    //         ];
    //     }


    //     $groupedData[$categoryId]['art_data'][] = [
    //         'art_data_id'    => $data->art_data_id,
    //         'art_data_title' => $data->art_data_title,
    //         'required'       => $data->required,
    //         'status'         => $data->status,
    //        'inserted_date' => date('m-d-Y', strtotime($data->inserted_date)),
    //         'inserted_time'  => $data->inserted_time,
    //     ];
    // }

    // $groupedData = array_values($groupedData);



        if ($artData->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No Art data found!'
            ]);
        } else {
            return response()->json([
                'status' => true,
                'artData' => $artData
            ]);
        }
    }


    public function getBankDetails(Request $request)

    {
        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ]);
        }

        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }
        $customer=Customer::where('customer_unique_id',$request->customer_unique_id)->first();

        $bankDetails = BankDetail::where('customer_id', $customer->customer_id)->first();

        if (!$bankDetails) {

            return response()->json(['status' => false, 'message' => 'Bank details not found.'], 404);

        }

        return response()->json(['status' => true, 'data' => $bankDetails], 200);

    }


    public function updateBankDetails(Request $request)
    {
        if (!Auth::guard('customer_api')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'customer_unique_id' => 'required|string',
            'account_holder_name' => 'sometimes|string',
            'bank_name' => 'sometimes|string',
            'account_number' => 'sometimes|string',
            'bank_code' => 'sometimes|string',
            'digital_payment_id' => 'nullable|string',
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
                'message' => 'Customer not found.',
            ], 404);
        }

        $bankDetails = BankDetail::where('customer_id', $customer->customer_id)->first();

        if ($bankDetails) {
            // Update existing bank details
            BankDetail::where('customer_id', $customer->customer_id)->update($request->only([
                'account_holder_name',
                'bank_name',
                'account_number',
                'bank_code',
                'digital_payment_id'
            ]));

            return response()->json([
                'status' => true,
                'message' => 'Bank details updated successfully.',
                'data' => $bankDetails
            ], 200);
        } else {

            $bankDetails = BankDetail::create([
                'customer_id' => $customer->customer_id,
                'account_holder_name' => $request->account_holder_name,
                'bank_name' => $request->bank_name,
                'account_number' => $request->account_number,
                'bank_code' => $request->bank_code,
                'digital_payment_id' => $request->digital_payment_id,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Bank details added successfully.',
                'data' => $bankDetails
            ], 200);
        }
    }


    public function admin_get_all_miramonet_chat(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_unique_id' => 'required',
            'min_date' => 'nullable|date',
            'max_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        $User = User::where('user_unique_id', $request->user_unique_id)->first();
        $threeMonthsAgo = Carbon::now()->subMonths(3)->toDateString();


        $chats = MiramonetChat::with(['MiramonetChatMessage' => function ($query) {
            $query->select('miramonet_chat_message_id', 'miramonet_chat_id', 'message', 'image', 'inserted_date', 'inserted_time')
                ->orderByDesc('inserted_date')
                ->orderByDesc('inserted_time');
        }])
            ->Where('user_id', $User->user_id)
            ->orderByDesc('inserted_date')
            ->orderByDesc('inserted_time');
            // ->get();

            if ($request->min_date) {
                $chats->where('miramonet_chat.inserted_date', '>=', $request->min_date);
            }else{
                $chats->where('miramonet_chat.inserted_date', '>=', $threeMonthsAgo);
            }

            if ($request->max_date) {
                $chats->where('miramonet_chat.inserted_date', '<=', $request->max_date);
            }else{
                $chats->where('miramonet_chat.inserted_date', '>=', $threeMonthsAgo);
            }

            $chats = $chats
            // ->orderBy('contact_us.contact_us_id', 'desc')
            // ->groupBy('contact_us.contact_us_id')
            ->get();


        $ArtData = [];


        foreach ($chats as $chat) {


            $customer = Customer::where('customer_id', $chat->customer_id)->first();


            $lastMessagae = DB::table('miramonet_chat_message')
                ->where('miramonet_chat_id', $chat->miramonet_chat_id)
                ->orderBy('inserted_date', 'desc')
                ->orderBy('inserted_time', 'desc')
                ->first();

            $miramonet_chat = DB::table('miramonet_chat')
                ->where('miramonet_chat_id', $chat->miramonet_chat_id)
                ->first();





            $ArtData[] = [
                'reciver_unique_id' => $customer->customer_unique_id,
                'reciver_role' => $customer->role,
                'reciver_name' => $customer->name,
                'inserted_date' => $chat->inserted_date,
                'inserted_time' => $chat->inserted_time,
                'miramonet_chat_id' => $chat->miramonet_chat_id,
                'last_message' => $lastMessagae ?? $miramonet_chat,
            ];
        }
        return response()->json([
            'status' => true,
            'message' => 'Chat users fetched successfully.',
            'miramonet_list' => $ArtData,
        ]);
    }

    public function admin_get_single_miramonet_chat(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'miramonet_chat_id' => 'required|exists:miramonet_chat,miramonet_chat_id',
            'reciver_unique_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $chatInitiate = MiramonetChat::with(['MiramonetChatMessage'])
            ->where('miramonet_chat_id', $request->miramonet_chat_id)
            ->first();


        if (!$chatInitiate) {
            return response()->json([
                'status' => false,
                'message' => 'Chat not initiated or not found.',
            ]);
        }

        $Miramonetmessage = DB::table('miramonet_chat')->where('miramonet_chat_id', $request->miramonet_chat_id)->first();


        $customer_with_chat = Customer::where('customer_unique_id', $request->reciver_unique_id)->first();


        $reciver_data = [
            'reciver_unique_id' => $customer_with_chat->customer_unique_id,
            'reciver_profile_image' => isset($customer_with_chat->customer_profile) ? url($customer_with_chat->customer_profile) : null,
            'reciver_name' => $customer_with_chat->name,
            'reciver_email' => $customer_with_chat->email,
            'reciver_mobile' => $customer_with_chat->mobile,
            'reciver_fcm_token' => $customer_with_chat->fcm_token,
            'reciver_latitude' => $customer_with_chat->latitude,
            'reciver_longitude' => $customer_with_chat->longitude,
            'reciver_role' => $customer_with_chat->role,

        ];



        $chatDetails = $chatInitiate->MiramonetChatMessage->map(function ($message) {

            if ($message->role == 'superadmin') {
                $receiver_unique_id = Customer::where('customer_id', $message->receiver_id)->value('customer_unique_id');
                $sender_unique_id = User::where('user_id', $message->sender_id)->value('user_unique_id');
            } else {

                $sender_unique_id = Customer::where('customer_id', $message->sender_id)->value('customer_unique_id');
                $receiver_unique_id = User::where('user_id', $message->receiver_id)->value('user_unique_id');
            }




            return [
                'sender_id' => $message->sender_id,
                'receiver_id' => $message->receiver_id,
                'sender_unique_id' => $sender_unique_id,
                'receiver_unique_id' => $receiver_unique_id,
                // 'reciver_fcm_token' => $reciver_fcm_token,
                'inserted_date' => $message->inserted_date,
                'inserted_time' => $message->inserted_time,
                'message' => $message->message ?? null,
                'image' => $message->image ? url($message->image) : null,
            ];
        });

        // $data = [
        //     'help_center_chat_id' => $request->help_center_chat_id,
        //     'chatDetails' => isset($chatDetails) ? $chatDetails : null,

        // ];

        return response()->json([
            'status' => true,
            'miramonet_chat_id' => $request->miramonet_chat_id,
            'chatDetails' => isset($chatDetails) ? $chatDetails : null,
            'reciver_data' => $reciver_data,
        ]);
    }
    public function adminsendMiramonetmessage(Request $request)
    {
        // if (!Auth::guard('customer_api')->check()) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Unauthorized access.',
        //     ], 400);
        // }

        $validator = Validator::make($request->all(), [
            'miramonet_chat_id' => 'required|exists:miramonet_chat,miramonet_chat_id',
            'message' => 'nullable|string',
            'sender_unique_id' => 'required|string',
            'receiver_unique_id' => 'required|string',
            'images' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $sender = User::where('user_unique_id', $request->sender_unique_id)->first();
        $chatInitiate = MiramonetChat::where('miramonet_chat_id', $request->miramonet_chat_id)->first();
        $receiverData = Customer::where('customer_unique_id', $request->receiver_unique_id)->first();

        if (!$chatInitiate) {
            return response()->json([
                'status' => false,
                'message' => 'Chat not found.',
            ]);
        }

        $filePath = null;

        if ($request->hasFile('images') && $request->file('images')->isValid()) {
            $file = $request->file('images');

            $fileName = time() . '.' . $file->getClientOriginalExtension();

            $file->move(public_path('miramonet/images'), $fileName);

            $filePath = 'miramonet/images/' . $fileName;
        }


        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();


        $message = MiramonetChatMessage::create([
            'miramonet_chat_id' => $request->miramonet_chat_id,
            'sender_id' => $sender->user_id,
            'receiver_id' => $receiverData->customer_id,
            'message' => $request->message,
            'role' => $sender->role,
            'image' => $filePath,
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ]);

        $data = [
            'miramonet_chat_id' => $request->miramonet_chat_id,
            'sender_id' => $sender->user_id,
            'sender_unique_id' => $request->sender_unique_id,
            'receiver_id' => $receiverData->customer_id,
            'receiver_unique_id' => $request->receiver_unique_id,
            'message' => $request->message,
            'image' => url($filePath),
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ];


        return response()->json([
            'status' => true,
            'message' => $data
        ]);
    }


    public function admin_miramonet_chat(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_unique_id' => 'required',
            'customer_unique_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }
        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        $user = User::where('user_unique_id', $request->user_unique_id)->first();
        $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();
        $customerData = Customer::where('customer_unique_id', $request->customer_unique_id)->first();



        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'No User Found'
            ]);
        }
        if (!$customer) {
            return response()->json([
                'status' => false,
                'message' => 'No customer Found'
            ]);
        }


        $chatData = [
            'customer_id' => $customer->customer_id,
            'user_id' => $user->user_id,
            'role' => 'superadmin',
            'status' => 'Active',
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ];

        $existingChat = DB::table('miramonet_chat')
            ->where('customer_id', $customer->customer_id)
            ->where('user_id',  $user->user_id)
            ->first();

        if ($existingChat) {
            return response()->json([
                'status' => false,
                'message' => 'Your Enquiry Alredy Exists',
                'miramonet_chat_id' => $existingChat->miramonet_chat_id,

            ]);
        }


        $chatDataId =  DB::table('miramonet_chat')->insertGetId($chatData);

        $customer=[
            'customer_unique_id'=>$request->customer_unique_id,
            'customer_name'=>$customer->name,
            'customer_role'=>$customer->role,
            'customer_profile'=>$customer->customer_profile?url($customer->customer_profile):null,
        ];
        $message = MiramonetChatMessage::create([
            'miramonet_chat_id' => $chatDataId,
            'sender_id' => $user->user_id,
            'receiver_id' => $customerData->customer_id,
            'message' => 'Hello',
            'role' => $user->role,
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ]);

        return response()->json([
            'status' => true,
            'customer'=>$customer,
            'miramonet_chat_id' => $chatDataId,
            'reciver_unique_id' => $customerData->customer_unique_id,
        ]);
    }

    public function get_single_auction_art(Request $request)
    {
        // if (!Auth::guard('customer_api')->check()) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Unauthorized access.',
        //     ], 401);
        // }

        $validator = Validator::make($request->all(), [
            'art_unique_id' => 'required|exists:exhibition_art,art_unique_id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 200);
        }

        // $customerData = null;
        // if ($request->has('customer_unique_id')) {
        //     $customerData = Customer::where('customer_unique_id', $request->customer_unique_id)->first();
        // }
        // $customerData =Customer::where('customer_unique_id',$request->customer_unique_id)->first();


        $art = ExhibitionArt::with([
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
            },
            'category' => function ($query) {
                $query->select('category_id', 'category_name', 'sub_text', 'category_image', 'category_icon');
            }
        ])
            ->where('art_unique_id', $request->art_unique_id)
            ->first();

        if (!$art) {
            return response()->json([
                'status' => false,
                'message' => 'No Art Found!',
            ]);
        }




        $artDetails = $this->ExhibitionformatArtDetails($art);




        return response()->json([
            'status' => true,
            'art' => $artDetails,
        ]);
    }



    private function ExhibitionformatArtDetails($art)
    {


        $subCategory = DB::table('sub_category_1')
                    ->where('sub_category_1_id',$art->sub_category_1_id)
                    ->first();

        return [
            'art_unique_id' => $art->art_unique_id,
            'title' => $art->title,
            'artist_name' => $art->artist_name,
            'art_type' => $art->art_type,
            'category' => [
                'category_id' => $art->category->category_id,
                'category_name' => $art->category->category_name,
                'category_icon' => url($art->category->category_icon),
                'category_image' => $art->category->category_image,
                'sub_text' => $art->category->sub_text,
            ],
            'sub_category_1_id' => $art->sub_category_1_id,
            'sub_category_1_name' => $subCategory->sub_category_1_name,
            'edition' => $art->edition,
            'price' => $art->price ?? $art->estimate_price_from . ' - ' . $art->estimate_price_to,
            'estimate_price_from' => $art->estimate_price_from,
            'estimate_price_to' => $art->estimate_price_to,
            'since' => $art->since,
            'pickup_address' => $art->pickup_address,
            'pincode' => $art->pincode,
            'country' => $art->countries->country_name,
            'country_id' => $art->countries->country_id,
            'state' => $art->states->state_subdivision_name,
            'state_subdivision_id' => $art->states->state_subdivision_id,
            'city' => $art->cities->name_of_city,
            'cities_id' => $art->cities->cities_id,
            'frame' => $art->frame,
            'paragraph' => $art->paragraph,
            'status' => $art->status,
            'bid_start_from' => $art->bid_start_from,
            'bid_start_to' => $art->bid_start_to,
            'ExhibitionArtImage' => $art->ExhibitionArtImage->map(function ($image) {
                return [
                    'exhibition_art_image_id' => $image->exhibition_art_image_id,
                    'art_type' => $image->art_type,
                    'image' => url($image->image),
                ];
            }),
        ];
    }
    public function get_single_auction_art_admin(Request $request)
    {
        // if (!Auth::guard('customer_api')->check()) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Unauthorized access.',
        //     ], 401);
        // }

        $validator = Validator::make($request->all(), [
            'art_unique_id' => 'required|exists:exhibition_art,art_unique_id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 200);
        }

        // $customerData = null;
        // if ($request->has('customer_unique_id')) {
        //     $customerData = Customer::where('customer_unique_id', $request->customer_unique_id)->first();
        // }
        // $customerData =Customer::where('customer_unique_id',$request->customer_unique_id)->first();


        $art = ExhibitionArt::with([
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
            },
            'category' => function ($query) {
                $query->select('category_id', 'category_name', 'sub_text', 'category_image', 'category_icon');
            }
        ])
            ->where('art_unique_id', $request->art_unique_id)
            ->first();

        if (!$art) {
            return response()->json([
                'status' => false,
                'message' => 'No Art Found!',
            ]);
        }




        $artDetails = $this->ExhibitionformatArtDetailsAdmin($art);




        return response()->json([
            'status' => true,
            'art' => $artDetails,
        ]);
    }



    private function ExhibitionformatArtDetailsAdmin($art)
    {


        $subCategory = DB::table('sub_category_1')
                    ->where('sub_category_1_id',$art->sub_category_1_id)
                    ->first();

        return [
            'art_unique_id' => $art->art_unique_id,
            'title' => $art->title,
            'artist_name' => $art->artist_name,
            'art_type' => $art->art_type,
            'category' => [
                'category_id' => $art->category->category_id,
                'category_name' => $art->category->category_name,
                'category_icon' => url($art->category->category_icon),
                'category_image' => $art->category->category_image,
                'sub_text' => $art->category->sub_text,
            ],
            'sub_category_1_id' => $art->sub_category_1_id,
            'sub_category_1_name' => $subCategory->sub_category_1_name,
            'edition' => $art->edition,
            // 'price' => $art->price ?? $art->estimate_price_from . ' - ' . $art->estimate_price_to,
            'estimate_price_from' => $art->estimate_price_from,
            'estimate_price_to' => $art->estimate_price_to,
            'since' => $art->since,
            'pickup_address' => $art->pickup_address,
            'pincode' => $art->pincode,
            'country' => $art->countries->country_id,
            'country_name' => $art->countries->country_name,
            'state' => $art->states->state_subdivision_id,
            'state_name' => $art->states->state_subdivision_name,
            'city' => $art->cities->cities_id,
            'cities_name' =>$art->cities->name_of_city ,
            'frame' => $art->frame,
            'paragraph' => $art->paragraph,
            'status' => $art->status,
            'bid_start_from' => $art->bid_start_from,
            'bid_start_to' => $art->bid_start_to,
            'ExhibitionArtImage' => $art->ExhibitionArtImage->map(function ($image) {
                return [
                    'exhibition_art_image_id' => $image->exhibition_art_image_id,
                    'art_type' => $image->art_type,
                    'image' => url($image->image),
                ];
            }),
        ];
    }

    public function update_exhibiton_image(Request $request)
    {
        // header("Access-Control-Allow-Origin: *");
        // header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        // header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        // header("Access-Control-Expose-Headers: Content-Disposition");

        $validator = Validator::make($request->all(), [
            'exhibition_art_image_id' => 'required|exists:exhibition_art_images,exhibition_art_image_id',

            'image' => 'required|image',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }
        $exhibition_art_image_id = $request->exhibition_art_image_id;

        $art = DB::table('exhibition_art_images')->where('exhibition_art_image_id', $exhibition_art_image_id)->first();

        if (!$art) {
            return response()->json([
                'status' => false,
                'message' => 'No Art Image Found',
            ]);
        }

        if ($request->hasFile('image')) {
            $file = $request->file('image');

            $filename = Str::random(10) . '_' . str_replace(' ', '_', $file->getClientOriginalName());
            $filePath = 'exhibition/art/image/' . $filename;

            $storedPath = Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');

            if (!$storedPath) {
                return response()->json(['error' => 'File upload failed'], 500);
            }
            $fileUrl = Storage::disk('s3')->url($filePath);
            // $file = $request->file('image');
            // $fileName = time() . '_' . Str::random(5) . '.' . $file->getClientOriginalExtension();
            // $filePath = 'selling/image/' . $fileName;
            // $file->move(public_path('selling/image'), $fileName);
        }
        else{
            $fileUrl=$art->image;
        }
        $currentDateTime = Carbon::now('Asia/Kolkata');
        // if($request->exhibition_art_image_id){
        //     $image = DB::table('exhibition_art_images')->where('exhibition_art_image_id',$exhibition_art_image_id)->update([
        //         'image' => $filePath,
        //         'inserted_date' => $currentDateTime->toDateString(),
        //         'inserted_time' => $currentDateTime->toTimeString(),
        //     ]);



        //     return response()->json([
        //         'status' => true,
        //         'message' => 'Image update successfully.',
        //         'image_path' => url($filePath),

        //     ]);
        // }else{
        //     $image = DB::table('exhibition_art_images')->insert([
        //         'image' => $filePath,
        //         'inserted_date' => $currentDateTime->toDateString(),
        //         'inserted_time' => $currentDateTime->toTimeString(),
        //     ]);

        //     return response()->json([
        //         'status' => true,
        //         'message' => 'Image update successfully.',
        //         'image_path' => url($filePath),

        //     ]);
        // }

        $image = DB::table('exhibition_art_images')->where('exhibition_art_image_id',$exhibition_art_image_id)->update([
            'image' => $fileUrl,
            'inserted_date' => $currentDateTime->toDateString(),
            'inserted_time' => $currentDateTime->toTimeString(),
        ]);



        return response()->json([
            'status' => true,
            'message' => 'Image update successfully.',
            'image_path' =>  $fileUrl,

        ]);

        }


}
