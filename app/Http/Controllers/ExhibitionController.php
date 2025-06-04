<?php

    namespace App\Http\Controllers;

    use App\Models\Art;
    use App\Models\Exhibition;
    use App\Models\ExhibitionBooth;
    use App\Models\ExhibitionBoothRegister;
    use App\Models\Customer;
    use App\Models\ExhibitionArt;
    use App\Models\ExhibitionRegistration;
    use Auth;
    use Illuminate\Http\Request;
    use App\Models\ExhibitionGallery;
    use App\Models\ExhibitionGuest;
    use App\Models\ExhibitionSponsor;
    use App\Models\Country;
    use App\Models\State;
    use App\Models\City;
    use App\Models\ArtImage;
    use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
    use Carbon\Carbon;
    use Illuminate\Support\Facades\Validator;
    use App\Models\PrivateSaleEnquiry;
    use App\Models\ArtEnquiry;
    use Str;
    use TCPDF;
    use Kreait\Firebase\Factory;
    use Kreait\Firebase\Messaging\CloudMessage;
    use Illuminate\Support\Facades\DB;
    use App\Models\GalleryParas;

    use App\Models\Gallery;
    use App\Models\GalleryImages;

    class ExhibitionController extends Controller
    {

        public function getExhibitionDetails(Request $request)
        {
            if (!Auth::guard('customer_api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ]);
            }
            $validated = $request->validate([
                'exhibition_unique_id' => 'required|integer|exists:exhibitions,exhibition_unique_id',
            ]);

            $exhibition = Exhibition::where('exhibition_unique_id', $request->exhibition_unique_id)->first();

            if ($exhibition) {
                return response()->json([
                    'status' => true,
                    'data' => $exhibition,
                ]);
            }

            return response()->json([
                'status' => false,
                'message' => 'Exhibition not found.',
            ]);
        }

        public function getBoothsAndSeats(Request $request)
        {
            if (!Auth::guard('customer_api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ]);
            }
            $validated = $request->validate([
                'exhibition_unique_id' => 'required|integer|exists:exhibitions,exhibition_unique_id',
            ]);

            // return response()->json([
            //     'status' => true,
            //     'data' => $request->exhibition_id,
            // ]);

            $check = Exhibition::where('exhibition_unique_id', $request->exhibition_unique_id)->first();

            if (!$check) {
                return response()->json([
                    'status' => false,
                    'message' => 'No Echibition found!',
                ]);
            }

            // dd($check->exhibition_id);

            $booths = ExhibitionBooth::where('exhibition_id', $check->exhibition_id)
                ->with(['boothSeats'])
                ->get();

            if ($booths->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No booths found for this exhibition.',
                ]);
            }

            return response()->json([
                'status' => true,
                'data' => $booths,
            ]);
        }

        public function getUpcomingExhibitionsWithImages(Request $request)
        {
            if (!Auth::guard('customer_api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ]);
            }
            $customer = Auth::guard('customer_api')->user();


            $seller = Customer::where('customer_unique_id', $customer->customer_unique_id)->first();
            $today = date('Y-m-d');

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
            ])
                // ->where('start_date', '>=', $today)
                ->where('status', 'Active')
                ->orderBy('start_date', 'asc')
                // ->take(3)
                ->get();
            if ($exhibitions->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No upcoming exhibitions found.',
                    'data' => []
                ]);
            }
            $todayCarbon = Carbon::today();

            $todayString = $todayCarbon->toDateString();



            foreach ($exhibitions as $exhibition) {

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

                $exhibitionArt = Art::where('exhibition_id', $exhibition->exhibition_id)
                    ->where('customer_id', $seller->customer_id)
                    ->first();

                // dd($exhibitionArt);
                if ($exhibitionArt && $exhibitionArt->status == 'Approved') {
                    $exhibition->isArtApproved = 1;
                } else {
                    $exhibition->isArtApproved = 0;
                }

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
                'message' => 'Upcoming exhibitions fetched successfully.',
                'data' => $exhibitions
            ]);
        }

        public function getUpcomingExhibitionsWithImagespurchaseuser(Request $request)
        {
            if (!Auth::guard('customer_api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ]);
            }
            $today = date('Y-m-d');

            $exhibitions = Exhibition::where('start_date', '>=', $today)
                ->where('status', 'Active')
                ->orderBy('start_date', 'asc')
                // ->take(3)
                ->get();

            if ($exhibitions->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No upcoming exhibitions found.',
                    'data' => []
                ]);
            }

            $customer = Customer::where('customer_unique_id', $request->input('customer_unique_id'))->first();

            if (!$customer) {
                return response()->json(['status' => 'false', 'message' => 'Customer not found.']);
            }

            $customer_id = $customer->customer_id;
            $exhibitionsWithRegistration = [];

            foreach ($exhibitions as $exhibition) {
                $isRegistered = ExhibitionRegistration::where('exhibition_id', $exhibition->exhibition_id)
                    ->where('customer_id', $customer_id)
                    ->exists();

                if ($isRegistered) {
                    $exhibitionsWithRegistration[] = [
                        'exhibition_id' => $exhibition->exhibition_id,
                        'name' => $exhibition->name,
                        'start_date' => $exhibition->start_date,
                    ];
                }
            }

            if (empty($exhibitionsWithRegistration)) {
                return response()->json([
                    'status' => false,
                    'message' => 'No upcoming exhibitions found for this customer.',
                    'data' => []
                ]);
            }
            return response()->json([
                'status' => true,
                'message' => 'Upcoming exhibitions fetched successfully.',
                'data' => $exhibitionsWithRegistration
            ]);
        }

        private function generateUniqueId()
        {
            do {
                $uniqueId = mt_rand(1000000000, 9999999999);
            } while (Exhibition::where('exhibition_unique_id', $uniqueId)->exists());

            return $uniqueId;
        }

        public function add_exhibition(Request $request)
        {
            if (!Auth::guard('api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ]);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required',
                'tagline' => 'required',
                'description' => 'required',
                'start_date' => 'required',
                'end_date' => 'required',
                'country' => 'required',
                'state' => 'required',
                'city' => 'required',
                'pincode' => 'required',
                'address1' => 'required',
                'address2' => 'nullable',
                'contact_number' => 'required',
                'contact_email' => 'required',
                'theme' => 'required',
                'category_id' => 'required',
                'disclaimer' => 'required',
                'art_submit_last_date' => 'required',
                // 'website_link' => 'required',
                // 'contact_email' => 'required',
                // 'logo' => 'required',
                // 'status' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ], 400);
            }


            // if ($request->hasFile('logo')) {
            //     $file = $request->file('logo');
            //     $fileName = time() . '_' . Str::random(5) . '.' . $file->getClientOriginalExtension();
            //     $filePath = 'exhibition/logo/' . $fileName;
            //     $file->move(public_path('exhibition/logo'), $fileName);
            // }

            $user = Auth::guard('api')->user();
            $timezone = $user->timezone ?? "America/Los_Angeles";

            $currentDateTime = now($timezone);
            $insertDate = $currentDateTime->toDateString();
            $exhibitonData = [
                'name' => $request->name,
                'tagline' => $request->tagline,
                'description' => $request->description,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'country' => $request->country,
                'state' => $request->state,
                'city' => $request->city,
                'pincode' => $request->pincode,
                'address1' => $request->address1,
                'address2' => $request->address2,
                'contact_number' => $request->contact_number,
                'website_link' => $request->website_link,
                'contact_email' => $request->contact_email,
                'theme' => $request->theme,
                'category_id' => $request->category_id,

                'disclaimer' => $request->disclaimer,
                'art_submit_last_date' => $request->art_submit_last_date,
                // 'logo' => $filePath,
                'status' => 'Inactive',
                'inserted_date' => $insertDate,
                'exhibition_unique_id' => $this->generateUniqueId()
            ];

            $exhibition_id = DB::table('exhibitions')->insertGetId($exhibitonData);

            $exhibitionData = DB::table('exhibitions')->where('exhibition_id', $exhibition_id)->first();


            // $exhibition=

            return response()->json([
                'status' => true,
                'message' => 'Exhibition Added  Successfully!',
                'exhibition_unique_id' => $exhibitionData->exhibition_unique_id
            ]);
        }










        public function add_exhibition_artist(Request $request)
        {
            if (!Auth::guard('api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ]);
            }
            $validator = Validator::make($request->all(), [
                'exhibition_unique_id' => 'required|exists:exhibitions,exhibition_unique_id',
                'artist_booth' => 'required|in:Yes,No',

            ]);
            if ($request->artist_booth == 'No') {
                $validator->addRules([
                    'amount' => 'required|numeric|min:0',
                    'artist_count' => 'required|min:1',
                    'card_colour' => 'required|string|size:7|starts_with:#',
                    'text_colour' => 'required|string|size:7|starts_with:#',
                    'art_commision' => 'required|numeric|min:0|max:100',
                ]);
            }

            // If artist_booth is "Yes", validate additional booth-related fields
            if ($request->artist_booth == 'Yes') {
                $validator->addRules([
                    'booth_size' => 'required|string',
                    'no_of_seats' => 'required|min:0',
                    // 'price' => 'required|numeric|min:0',
                    'amount' => 'required|numeric|min:0',

                    'card_colour' => 'required|string|size:7|starts_with:#',
                    'text_colour' => 'required|string|size:7|starts_with:#',
                    'art_commision' => 'required|numeric|min:0|max:100',
                ]);
            }

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ], 400);
            }
            $exhibition_unique_id = $request->exhibition_unique_id;

            $exhibition = Exhibition::where('exhibition_unique_id', $exhibition_unique_id)->first();

            if (!$exhibition) {
                return response()->json([
                    'status' => false,
                    'message' => 'No exhibition Found',
                ]);
            }
            $user = Auth::guard('api')->user();
            $timezone = $user->timezone ?? "America/Los_Angeles";

            $currentDateTime = now($timezone);

            if ($request->artist_booth == 'No') {
                $amount = $request->amount;
                $artist_count = $request->artist_count;
                $card_colour = $request->card_colour;
                $text_colour = $request->text_colour;

                $data = [
                    'amount' => $amount,
                    'artist_count' => $artist_count,
                    'card_colour' => $card_colour,
                    'text_colour' => $text_colour,
                ];

                $new = DB::table('exhibitions')
                    ->where('exhibition_id', $exhibition->exhibition_id)
                    ->update([
                        'amount' => $amount,
                        'artist_count' => $artist_count,
                        'card_colour' => $card_colour,
                        'text_colour' => $text_colour,
                        'art_commision' => $request->art_commision,
                    ]);

                if ($new) {
                    return response()->json([
                        'status' => true,
                        'message' => 'Exhibition Artist Data Updated',
                        'exhibitions' => $exhibition
                    ]);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Failed To Upload',
                    ]);
                }
            } else {
                $booth_size = $request->booth_size;
                $no_of_seats = $request->no_of_seats;
                $price = $request->price;


                $amount = $request->amount;
                $artist_count = $request->artist_count;
                $card_colour = $request->card_colour;
                $text_colour = $request->text_colour;

                $old = DB::table('exhibitions')
                    ->where('exhibition_id', $exhibition->exhibition_id)
                    ->first();

                $new = DB::table('exhibitions')
                    ->where('exhibition_id', $exhibition->exhibition_id)
                    ->update([
                        'amount' => $price ?? $old->amount,
                        'artist_count' => $artist_count ?? $old->amount,
                        'card_colour' => $card_colour ?? $old->card_colour,
                        'text_colour' => $text_colour ?? $old->text_colour,
                        'art_commision' => $request->art_commision ?? $old->art_commision,
                    ]);

                $boothId = DB::table('exhibition_booths')
                    ->insertGetId([
                        "exhibition_id" => $exhibition->exhibition_id,
                        "booth_size" => $request->booth_size,
                        "no_of_seats" => $request->no_of_seats,
                        "price" => $request->amount,
                        'inserted_date' => $currentDateTime->toDateString(),
                        'inserted_time' => $currentDateTime->toTimeString(),
                    ]);

                // if ($request->no_of_seats > 0) {


                //     $seatLabels = [];
                //     $seatsPerRow = 8;

                //     $rows = ceil($request->no_of_seats / $seatsPerRow);

                //     for ($row = 0; $row < $rows; $row++) {
                //         $rowLetter = chr(65 + $row);


                //         $startSeat = 1;
                //         $endSeat = min($startSeat + $seatsPerRow - 1, $request->no_of_seats);

                //         for ($seatNum = $startSeat; $seatNum <= $endSeat; $seatNum++) {
                //             $seatLabels[] = $rowLetter . $seatNum;
                //         }
                //     }

                //     foreach ($seatLabels as $seat) {
                //         DB::table('booth_seats')->insert([
                //             'exhibition_booth_id' => $boothId,
                //             'seat_name' => $seat,
                //             'status' => 'Active',
                //         ]);
                //     }
                //     $data = DB::table('exhibition_booths')
                //         // ->leftjoin('booth_seats', 'exhibition_booths.exhibition_booth_id', '=', 'booth_seats.exhibition_booth_id')
                //         ->where('exhibition_id', $exhibition->exhibition_id)
                //         ->get();

                //     $vals = [];
                //     foreach ($data as $val) {
                //         $exhibition_seat = DB::table('booth_seats')
                //             ->where('exhibition_booth_id', $val->exhibition_booth_id)
                //             ->get();
                //         // $val['seat'] = $exhibition_seat;
                //         // $vals[] = [
                //         //     'booth' => $val,
                //         //     // 'seat'=>$exhibition_seat
                //         // ];
                //         $valArray = (array) $val;
                //         $valArray['seat'] = $exhibition_seat;

                //         $vals[] = $valArray;
                //     }

                //     return response()->json([
                //         'status' => true,
                //         'message' => 'Exhibition Artist Data Updated',
                //         // 'exhibitions' => $exhibition,
                //         'data' => $vals
                //     ]);
                // }


                if ($request->no_of_seats > 0) {

                    $seatLabels = [];
                    $seatsPerRow = 8;
                    $totalSeats = $request->no_of_seats;

                    $rows = ceil($totalSeats / $seatsPerRow);

                    for ($row = 0; $row < $rows; $row++) {
                        $rowLetter = chr(65 + $row); // A, B, C, etc.

                        for ($col = 1; $col <= $seatsPerRow; $col++) {
                            if (count($seatLabels) >= $totalSeats) {
                                break; // Stop when the required number of seats is reached
                            }
                            $seatLabels[] = $rowLetter . $col; // Ensure numbering resets in each row
                        }
                    }

                    foreach ($seatLabels as $seat) {
                        DB::table('booth_seats')->insert([
                            'exhibition_booth_id' => $boothId,
                            'seat_name' => $seat,
                            'status' => 'Inactive',
                        ]);
                    }

                    $data = DB::table('exhibition_booths')
                        ->where('exhibition_id', $exhibition->exhibition_id)
                        ->get();

                    $vals = [];
                    foreach ($data as $val) {
                        $exhibition_seat = DB::table('booth_seats')
                            ->where('exhibition_booth_id', $val->exhibition_booth_id)
                            ->get();

                        $valArray = (array) $val;
                        $valArray['seat'] = $exhibition_seat;

                        $vals[] = $valArray;
                    }

                    return response()->json([
                        'status' => true,
                        'message' => 'Exhibition Artist Data Updated',
                        'data' => $vals
                    ]);
                }

                else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Failed To Upload'
                    ]);
                }
            }
        }
        public function add_exhibiition_gallary(Request $request)
        {
            if (!Auth::guard('api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ]);
            }

            $validator = Validator::make($request->all(), [
                'exhibition_unique_id' => 'required|exists:exhibitions,exhibition_unique_id',
                'tagline' => 'required',
                'link' => 'required|image',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ], 400);
            }
            $exhibition_unique_id = $request->exhibition_unique_id;

            $exhibition = Exhibition::where('exhibition_unique_id', $exhibition_unique_id)->first();

            if (!$exhibition) {
                return response()->json([
                    'status' => false,
                    'message' => 'No exhibition Found',
                ]);
            }

            if ($request->hasFile('link')) {

                $file = $request->file('link');

                $filename = Str::random(10) . '_' . str_replace(' ', '_', $file->getClientOriginalName());
                $filePath = 'exhibition/gallery/' . $filename;

                $storedPath = Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');

                if (!$storedPath) {
                    return response()->json(['error' => 'File upload failed'], 500);
                }

                $fileUrl = Storage::disk('s3')->url($filePath);




                $user = Auth::guard('api')->user();
                $timezone = $user->timezone ?? "America/Los_Angeles";

                $currentDateTime = now($timezone);
                $image = ExhibitionGallery::create([
                    'exhibition_id' => $exhibition->exhibition_id,
                    'tagline' => $request->tagline,
                    'link' => $fileUrl,
                    'status' => 'Active',
                    'inserted_date' => $currentDateTime->toDateString(),
                    'inserted_time' => $currentDateTime->toTimeString(),
                ]);

                $id = $image->exhibition_gallery_id;
                // dd($id);


                return response()->json([
                    'status' => true,
                    'message' => 'Image added successfully.',
                    'image' => url($filePath),
                    'exhibition_gallery_id' => $image->exhibition_gallery_id,
                    'tagline' => $image->tagline,
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'No image file provided.',
                ], 400);
            }
        }

        public function add_exhibition_customer(Request $request)
        {
            if (!Auth::guard('api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ]);
            }
            $validator = Validator::make($request->all(), [
                'exhibition_unique_id' => 'required|exists:exhibitions,exhibition_unique_id',
                'date' => 'required',
                'time' => 'required',
                'visitor_allow' => 'in:Yes,No',
                'visitor_count' => 'nullable|numeric|min:0',
                'visitor_price' => 'nullable|numeric|min:0',
                'private_visitor_allow' => 'in:Yes,No',
                'private_visitor_count' => 'nullable|numeric|min:0',
                'private_visitor_price' => 'nullable|numeric|min:0',
                'auction_visitor_allow' => 'in:Yes,No',
                'auction_visitor_count' => 'nullable|numeric|min:0',
                'auction_visitor_price' => 'nullable|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ], 400);
            }

            $exhibition_unique_id = $request->exhibition_unique_id;

            $exhibition = DB::table('exhibitions')->where('exhibition_unique_id', $exhibition_unique_id)->first();

            // dd($exhibition);
            if (!$exhibition) {
                return response()->json([
                    'status' => false,
                    'message' => 'No exhibition found',
                ]);
            }
            $user = Auth::guard('api')->user();
            $timezone = $user->timezone ?? "America/Los_Angeles";

            $currentDateTime = now($timezone);
            $insertDate = $currentDateTime->toDateString();
            $insertTime = $currentDateTime->toTimeString();

            $updateData = [
                'exhibition_id' => $exhibition->exhibition_id,
                'date' => $request->date,
                'slot_name' => $request->time,
                'status' => 'Active',
                'inserted_date' => $insertDate,
                'inserted_time' => $insertTime,
            ];

            $updateData = array_merge($updateData, $this->handleVisitorSettings($request, 'visitor', 'visitor_count', 'visitor_price'));

            $updateData = array_merge($updateData, $this->handleVisitorSettings($request, 'private_visitor', 'private_visitor_count', 'private_visitor_price'));

            $updateData = array_merge($updateData, $this->handleVisitorSettings($request, 'auction_visitor', 'auction_visitor_count', 'auction_visitor_price'));

            $exhibition_time_slot_id = DB::table('exhibition_time_slot')->insertGetId($updateData);

            if (!$exhibition_time_slot_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to update exhibition time slot',
                ]);
            }
            $exhibition_time_slot = DB::table('exhibition_time_slot')
                ->where('exhibition_id', $exhibition->exhibition_id)
                ->get();


            return response()->json([
                'status' => true,
                'message' => 'Exhibition time slot updated successfully.',
                'exhibition_time_slot' => $exhibition_time_slot
            ]);
        }


        private function handleVisitorSettings($request, $visitorType, $countKey, $priceKey)
        {
            $data = [];

            $allowKey = "{$visitorType}_allow";
            $count = $request->$countKey ?? 0;
            $price = $request->$priceKey ?? 0;

            if ($request->$allowKey == "Yes") {
                $data["{$visitorType}_allow"] = 'Yes';
                $data["{$visitorType}_count"] = $count;
                $data["{$visitorType}_price"] = $price;
            } else {
                $data["{$visitorType}_allow"] = 'No';
                $data["{$visitorType}_count"] = '0';
                $data["{$visitorType}_price"] = '0';
            }

            return $data;
        }


        public function add_exhibition_guideline(Request $request)
        {

            if (!Auth::guard('api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ]);
            }
            $validator = Validator::make($request->all(), [
                'exhibition_unique_id' => 'required|exists:exhibitions,exhibition_unique_id',
                'para' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ], 400);
            }
            $exhibition_unique_id = $request->exhibition_unique_id;

            $exhibition = Exhibition::where('exhibition_unique_id', $exhibition_unique_id)->first();

            if (!$exhibition) {
                return response()->json([
                    'status' => false,
                    'message' => 'No exhibition Found',
                ]);
            }
            $user = Auth::guard('api')->user();
            $timezone = $user->timezone ?? "America/Los_Angeles";

            $currentDateTime = now($timezone);


            $data = [
                'exhibition_id' => $exhibition->exhibition_id,
                'para' => $request->para,
                'status'=>'Active',
                'inserted_date' => $currentDateTime->toDateString(),
                'inserted_time' => $currentDateTime->toTimeString(),
            ];

            $inserted = DB::table('exhibition_paras')->insert($data);

            $key = DB::table('exhibition_paras')->where('exhibition_id', $exhibition->exhibition_id)->get();
            if ($inserted) {
                return response()->json([
                    'status' => true,
                    'message' => 'Guideline Successfully Added',
                    'data' => $key
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'No Data Found'
                ]);
            }
        }
        public function add_exhibition_terms(Request $request)
        {

            if (!Auth::guard('api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ]);
            }
            $validator = Validator::make($request->all(), [
                'exhibition_unique_id' => 'required|exists:exhibitions,exhibition_unique_id',
                'para' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ], 400);
            }
            $exhibition_unique_id = $request->exhibition_unique_id;

            $exhibition = Exhibition::where('exhibition_unique_id', $exhibition_unique_id)->first();

            if (!$exhibition) {
                return response()->json([
                    'status' => false,
                    'message' => 'No exhibition Found',
                ]);
            }
            $user = Auth::guard('api')->user();
            $timezone = $user->timezone ?? "America/Los_Angeles";

            $currentDateTime = now($timezone);


            $data = [
                'exhibition_id' => $exhibition->exhibition_id,
                'para' => $request->para,
                'status'=>'Active',
                'inserted_date' => $currentDateTime->toDateString(),
                'inserted_time' => $currentDateTime->toTimeString(),
            ];

            $inserted = DB::table('exhibition_term')->insert($data);

            $key = DB::table('exhibition_term')->where('exhibition_id', $exhibition->exhibition_id)->get();
            if ($inserted) {
                return response()->json(data: [
                    'status' => true,
                    'message' => 'T&C Successfully Added',
                    'data' => $key
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'No Data Found'
                ]);
            }
        }



        public function add_exhibition_art(Request $request)
        {
            if (!Auth::guard('api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ]);
            }
            $validator = Validator::make($request->all(), [
                'exhibition_unique_id' => 'required|exists:exhibitions,exhibition_unique_id',
                'art_unique_id' => 'required|exists:art,art_unique_id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ]);
            }
            $exhibition_unique_id = $request->exhibition_unique_id;

            $exhibition = Exhibition::where('exhibition_unique_id', $exhibition_unique_id)->first();

            if (!$exhibition) {
                return response()->json([
                    'status' => false,
                    'message' => 'No exhibition Found',
                ]);
            }

            $art = Art::where('art_unique_id', $request->art_unique_id)->first();
            if (!$art) {
                return response()->json([
                    'status' => false,
                    'message' => 'No art Found',
                ]);
            }
            $user = Auth::guard('api')->user();
            $timezone = $user->timezone ?? "America/Los_Angeles";

            $currentDateTime = now($timezone);


            $exhibitionArt = [
                'exhibition_id' => $exhibition->exhibition_id,
                'art_id' => $art->art_id,
                'status' => 'Active',
                'inserted_date' => $currentDateTime->toDateString(),
                'inserted_time' => $currentDateTime->toTimeString(),
            ];
            ExhibitionArt::create($exhibitionArt);

            return response()->json([
                'status' => true,
                'message' => 'Art Successfully Added to Exhibition ',
            ]);
        }





        public function submit_exhibition(Request $request)
        {
            if (!Auth::guard('api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ]);
            }
            $validator = Validator::make($request->all(), [
                'exhibition_unique_id' => 'required|exists:exhibitions,exhibition_unique_id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ]);
            }
            $exhibition_unique_id = $request->exhibition_unique_id;

            $exhibition = Exhibition::where('exhibition_unique_id', $exhibition_unique_id)->first();

            if (!$exhibition) {
                return response()->json([
                    'status' => false,
                    'message' => 'No exhibition Found',
                ]);
            }


            $user = Auth::guard('api')->user();
            $timezone = $user->timezone ?? "America/Los_Angeles";

            $currentDateTime = now($timezone);


            $exhibitionData = [
                'status' => 'Active',
                'inserted_date' => $currentDateTime->toDateString(),
                'inserted_time' => $currentDateTime->toTimeString(),
            ];
            Exhibition::where('exhibition_id', $exhibition->exhibition_id)->update($exhibitionData);

            $firebaseCredentials = (new Factory)->withServiceAccount(base_path('mira-monet-firebase-adminsdk-fbsvc-864230166e.json'));
            $messaging = $firebaseCredentials->createMessaging();

            $failedNotifications = [];
            $successfulNotifications = 0;

            $customers = Customer::where('status','Active')->get();

            $exhibitions =  DB::table('exhibitions')
            ->where('exhibition_unique_id', $request->exhibition_unique_id)
            ->first();




        $title = "New Exhibition Alert! 🎉";
        $body = "A new Exhibition, '{$exhibitions->name}', is happening near you! 📍 Don't miss out.";
        $image = isset($exhibitions->logo) ? url($exhibitions->logo) : null;
            foreach ($customers as $customer) {
                $deviceToken = $customer->fcm_token;
                $timezone = $customer->timezone ?? 'Asia/Kolkata';
                $currentDateTime = Carbon::now($timezone);
                $insertDate = $currentDateTime->toDateString();
                $insertTime = $currentDateTime->toTimeString();
                if (!empty($deviceToken)) {
                    $message = CloudMessage::withTarget('token', $deviceToken)
                        ->withNotification([
                            'title' => $title,
                            'body' => $body,
                            'image' => $image
                        ]);

                    try {
                        $messaging->send($message);
                        $successfulNotifications++;
                        $data = DB::table('notification')
                            ->insert([
                                'title' => $title,
                                'body' => $body,
                                'image' => $image,
                                'customer_id' => $customer->customer_id,
                                'inserted_date' => $insertDate,
                                'inserted_time' => $insertTime,

                            ]);
                    } catch (\Exception $e) {
                        $failedNotifications[] = [
                            'customer_id' => $customer->customer_id,
                            'error' => $e->getMessage(),
                        ];
                    }
                }
            }

            return response()->json([
                'status' => true,
                'message' => ' Exhibition Added Successfully',
                'successful_notifications' => $successfulNotifications,
                'failed_notifications' => $failedNotifications,
            ]);
        }



        public function registerForExhibition(Request $request)
        {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'mobile' => 'required|string|max:15',
                'exhibition_unique_id' => 'required',
                'address' => 'required|string|max:200',
                'country' => 'required|string|max:200',
                'state' => 'required|string|max:200',
                'city' => 'required|string|max:200',
                'zip_code' => 'required|string|max:200',
                'customer_unique_id' => 'required|string',
                'role' => 'required|string',
            ]);


            $exhibition = Exhibition::where('exhibition_unique_id', $request->exhibition_unique_id)->first();


            if (!$exhibition) {
                return response()->json([
                    'status' => false,
                    'message' => 'No Exhibiton found',
                ]);
            }
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
            $exhibitionId = $exhibition->exhibition_id;
            $address = $request->input('address');
            $country = $request->input('country');
            $state = $request->input('state');
            $city = $request->input('city');
            $zip_code = $request->input('zip_code');
            $role = $request->input('role');

            // $exhibition = Exhibition::where('exhibition_id', $exhibitionId)->first();

            // if (!$exhibition) {
            //     return response()->json(['status' => 'false', 'message' => 'Exhibition not found.']);
            // }



            $exhibitions = [
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
                'address1' => $exhibition->address1,
                'address2' => $exhibition->address2,
                'contact_number' => $exhibition->contact_number,
                'contact_email' => $exhibition->contact_email,
                'website_link' => $exhibition->website_link,
                'status' => $exhibition->status,
                'logo' => url($exhibition->logo),
            ];

            $amount = $exhibition->amount;
            $isPaid = $amount > 0 ? 1 : 0;
            $exhibitionUniqueId = $exhibition->exhibition_unique_id;

            $existingRegistration = ExhibitionRegistration::where('exhibition_id', $exhibitionId)
                ->where('mobile', $mobile)
                ->where('status', 'Inactive')
                ->first();


            if ($existingRegistration) {
                $existingRegistration->update([
                    'name' => $name,
                    'email' => $email,
                    'address' => $address,
                    'role' => $role,
                    'country' => $country,
                    'state' => $state,
                    'city' => $city,
                    'zip_code' => $zip_code,
                    'booth_seat_id' => $request->booth_seat_id,
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
                        $amount = $booth->price;
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

            $totalRegistrations = ExhibitionRegistration::where('exhibition_id', $exhibitionId)->count();
            $registrationCode = $exhibitionUniqueId . ($totalRegistrations + 1);


            if ($role == 'customer') {
                if ($totalRegistrations >= $exhibition->visitor_count) {
                    return response()->json([
                        'status' => 'false',
                        'message' => 'Registration limit reached for this exhibition.',
                    ]);
                }
            }

            // $data =ExhibitionRegistration::where('mobile',$mobile)
            // // ->where('email',$email)
            // ->where('exhibition_id',$exhibitionId)
            // ->first();

            // if($data) {
            //     return response()->json([
            //         'status'=>false,
            //         'message'=>'You are alredy Registered'
            //     ]);
            // }

            $registration = ExhibitionRegistration::create([
                'name' => $name,
                'email' => $email,
                'customer_id' => $customer->customer_id,
                'mobile' => $mobile,
                'exhibition_id' => $exhibitionId,
                'registration_code' => $registrationCode,
                'address' => $address,
                'status' => 'Inactive',
                'booth_seat_id' => $request->booth_seat_id,
                'amount' => $amount,
                'role' => $role,
                'country' => $country,
                'state' => $state,
                'city' => $city,
                'zip_code' => $zip_code,
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
                    $amount = $booth->price;
                }
            }

            return response()->json([
                'status' => 'true',
                'message' => 'Customer registered successfully.',
                'is_paid' => $isPaid,
                'amount' => $amount,
                'exhibition_data' => $exhibitions,
                'registration_code' => $registration->registration_code,
            ]);
        }
        public function registerForExhibitionweb(Request $request)
        {
            $request->validate([
                'customer_unique_id' => 'required',
                'exhibition_unique_id' => 'required'

            ]);


            $customer = Customer::where('customer_unique_id', $request->customer_unique_id)->first();
            $exhibitionData = Exhibition::where('exhibition_unique_id', $request->exhibition_unique_id)->first();

            $exhibitionReg = ExhibitionRegistration::where('exhibition_id', $exhibitionData->exhibition_id)
                ->where('customer_id', $customer->customer_id)->first();

            $exhibitionRegWeb = ExhibitionRegistration::where('exhibition_id', $exhibitionData->exhibition_id)
                ->where('customer_id', $customer->customer_id)
                ->where('status', 'Active')
                ->first();

            if ($exhibitionRegWeb) {
                $isRegister = true;
            } else {
                $isRegister = false;
            }


            $country = Country::where('country_id', $exhibitionData->country)->first();
            $state = State::where('state_subdivision_id', $exhibitionData->state)->first();
            $city = City::where('cities_id', $exhibitionData->city)->first();

            if (!$exhibitionReg) {
                return response()->json([
                    'status' => false,
                    'message' => 'No Exhibiton found',
                ], 200);
            }

            $exhibitions = [
                'exhibition_id' => $exhibitionData->exhibition_id,
                'exhibition_unique_id' => $exhibitionData->exhibition_unique_id,
                'name' => $exhibitionData->name,
                'tagline' => $exhibitionData->tagline,
                'description' => $exhibitionData->description,
                'start_date' => $exhibitionData->start_date,
                'end_date' => $exhibitionData->end_date,
                'amount' => $exhibitionData->amount,
                'inserted_date' => $exhibitionData->inserted_date,
                'updated_date' => $exhibitionData->updated_date,
                'country_id' => $exhibitionData->country,
                'country_name' => $country->country_name,
                'state_subdivision_id' => $exhibitionData->state,
                'state_subdivision_name' => $state->state_subdivision_name,
                'cities_id' => $exhibitionData->city,
                'name_of_city' => $city->name_of_city,
                'address1' => $exhibitionData->address1,
                'address2' => $exhibitionData->address2,
                'contact_number' => $exhibitionData->contact_number,
                'contact_email' => $exhibitionData->contact_email,
                'website_link' => $exhibitionData->website_link,
                'status' => $exhibitionData->status,
                'logo' => url($exhibitionData->logo) ?? null,
            ];

            $amount = $exhibitionData->amount;
            $isPaid = $amount > 0 ? 1 : 0;
            $exhibitionUniqueId = $exhibitionData->exhibition_unique_id;

            return response()->json([
                'status' => 'true',
                'message' => 'Customer registered successfully.',
                'is_paid' => $isPaid,
                'amount' => $amount,
                'exhibition_data' => $exhibitions,
                'registration_code' => $exhibitionReg->registration_code,
                'isRegister' => $isRegister
            ]);
        }

        public function freeExhReg(Request $request)
        {
            $request->validate([
                'registration_code' => 'required|string'
            ]);

            $registrationCode = $request->input('registration_code');

            $registration = ExhibitionRegistration::where('registration_code', $registrationCode)->first();

            if (!$registration) {
                return response()->json(['status' => 'false', 'message' => 'Registration not found.']);
            }

            $exhibition = Exhibition::where('exhibition_id', $registration->exhibition_id)
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
            ExhibitionRegistration::where('registration_code', $registrationCode)->update(['status' => 'Active']);

            $spo = [];
            foreach ($sponsors as $sponsor) {
                $logo = url('/') . '/' . $sponsor->logo;
                $spo[] = [
                    'logo' => $logo,
                ];
            }

            $purpose_booking = $registration->purpose_booking;
            $timeSlot = DB::table('exhibition_time_slot')
                ->where('exhibition_time_slot_id', $registration->exhibition_time_slot_id)
                ->first();

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
                'purpose_booking' => $registration->purpose_booking,
                'exhibtion_date' => $registration->exhibtion_date,
                'slot_name' => $timeSlot->slot_name,
                'registration' => $registration,
            ];


            return response()->json([
                'status' => 'true',
                'message' => 'Registration activated successfully for the free exhibition.',
                'data' => $result,
            ]);
        }


        public function ticket(Request $request)
        {
            $request->validate([
                'registration_code' => 'required|string'
            ]);

            $registrationCode = $request->input('registration_code');

            $registration = ExhibitionRegistration::where('registration_code', $registrationCode)->first();

            if (!$registration) {
                return response()->json(['status' => 'false', 'message' => 'Registration not found.']);
            }

            $exhibition = Exhibition::where('exhibition_id', $registration->exhibition_id)
                ->join('countries', 'countries.country_id', '=', 'exhibitions.country')
                ->join('states', 'states.state_subdivision_id', '=', 'exhibitions.state')
                ->join('cities', 'cities.cities_id', '=', 'exhibitions.city')
                // ->select('exhibitions.*', 'countries.name as country_name', 'states.name as state_name', 'cities.name as city_name') // Select necessary fields
                ->first();

            $exhibition->logo = isset($exhibition->logo) ? url($exhibition->logo) : null;

            if (!$exhibition || $exhibition->amount > 0) {
                return response()->json(['status' => 'false', 'message' => 'This exhibition requires payment.']);
            }

            $sponsors = DB::table('exhibition_sponsor')
                ->where('exhibition_id', $registration->exhibition_id)
                ->get();

            // Update registration status
            $registration->where('registration_code', $registrationCode)->update(['status' => 'Active']);

            $spo = [];
            foreach ($sponsors as $sponsor) {
                $logo = url('/') . '/' . $sponsor->logo;
                $spo[] = [
                    'logo' => $logo,
                ];
            }

            $baseUrl = url('/');
            $result = [
                'exhibition' => $exhibition,
                'customer_data' => [
                    'name' => $registration->name,
                    'email' => $registration->email,
                    'mobile' => $registration->mobile
                ],
                'sponsors' => $spo,
                'logo' => $baseUrl . '/' . $exhibition->logo,
                'registration_code' => $registration->registration_code,
            ];

            $pdf = new TCPDF();
            $pdf->AddPage();

            // Set the title of the PDF
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->Cell(0, 10, 'Exhibition Ticket', 0, 1, 'C');

            $pdf->SetFont('helvetica', '', 12);
            $pdf->Ln(10);

            // Display Exhibition Details
            $pdf->Cell(0, 10, 'Exhibition: ' . $result['exhibition']['name'], 0, 1);  // Assuming 'name' is a key in $exhibition
            $pdf->Cell(0, 10, 'Registration Code: ' . $result['registration_code'], 0, 1);

            $pdf->Ln(10);

            // Display Customer Data
            $pdf->Cell(0, 10, 'Customer Name: ' . $result['customer_data']['name'], 0, 1);
            $pdf->Cell(0, 10, 'Email: ' . $result['customer_data']['email'], 0, 1);
            $pdf->Cell(0, 10, 'Mobile: ' . $result['customer_data']['mobile'], 0, 1);

            $pdf->Ln(10);

            // Display Sponsors Logos
            if (!empty($result['sponsors'])) {
                $sponsors = ''; // Prepare an empty string to store sponsor logos (or URLs)
                $yPosition = $pdf->GetY(); // Get current Y position to avoid overlapping text and images

                foreach ($result['sponsors'] as $sponsor) {
                    // If you want to display logos as images, use the Image function (this requires the URL to be publicly accessible)
                    if (filter_var($sponsor['logo'], FILTER_VALIDATE_URL)) {
                        $pdf->Image($sponsor['logo'], 10, $yPosition, 50);  // Display sponsor logo image
                        $yPosition += 30;  // Adjust Y position after displaying the image
                    }

                    // You can also list sponsor logos as text if you prefer, like this:
                    $sponsors .= $sponsor['logo'] . "\n";
                }

                // If you want to display all sponsor logos as text instead of images:
                // $pdf->Cell(0, 10, 'Sponsors: ' . $sponsors, 0, 1);
            }

            $pdf->Ln(10);

            // Display Exhibition Logo (main logo)
            if (!empty($result['logo'])) {
                $logoPath = $result['logo'];
                if (filter_var($logoPath, FILTER_VALIDATE_URL)) {
                    // Assuming the logo URL is publicly accessible, you can add the logo image to the PDF
                    $pdf->Image($logoPath, 10, $pdf->GetY(), 50);  // Display the logo image
                } else {
                    $pdf->Cell(0, 10, 'Logo not found.', 0, 1);
                }
            }

            $pdf->Output('ticket.pdf', 'D'); // Output the PDF file for download

            return response()->json([
                'status' => 'true',
                'message' => 'Registration activated successfully for the free exhibition.',
                'data' => $result,
            ]);
        }
        public function generateTicketPDF($result)
        {
            // Create new PDF document
            $pdf = new TcpdfService();
            $pdf->AddPage();

            // Set title and font
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->Cell(0, 10, 'Exhibition Ticket', 0, 1, 'C');

            // Add exhibition info
            $pdf->SetFont('helvetica', '', 12);
            $pdf->Ln(10);
            $pdf->Cell(0, 10, 'Exhibition: ' . $result['exhibition'], 0, 1);
            $pdf->Cell(0, 10, 'Registration Code: ' . $result['registration_code'], 0, 1);

            // Add customer data
            $pdf->Ln(10);
            $pdf->Cell(0, 10, 'Customer Name: ' . $result['customer_data']['name'], 0, 1);
            $pdf->Cell(0, 10, 'Email: ' . $result['customer_data']['email'], 0, 1);
            $pdf->Cell(0, 10, 'Mobile: ' . $result['customer_data']['mobile'], 0, 1);

            // Add sponsor data
            $pdf->Ln(10);
            $pdf->Cell(0, 10, 'Sponsors: ' . implode(', ', $result['sponsors']), 0, 1);

            // Add the logo
            $pdf->Ln(10);
            if ($result['logo']) {
                $logoPath = public_path($result['logo']);
                $pdf->Image($logoPath, 10, $pdf->GetY(), 50);
            }

            // Output the PDF to the browser
            $pdf->Output('ticket.pdf', 'I');
        }

        public function CancelCustExhReg(Request $request)
        {
            $validator = Validator::make($request->all(), [
                'registration_code' => 'required',

            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ]);
            }
            $registration_code = $request->registration_code;

            $registration = ExhibitionRegistration::where('registration_code', $registration_code)->first();

            // dd($registration);
            if (!$registration) {
                return response()->json(['status' => 'false', 'message' => 'Registration not found.']);
            }

            ExhibitionRegistration::where('registration_code', $registration_code)->delete();

            return response()->json([
                'status' => 'true',
                'message' => 'Registration canceled and deleted successfully.',
            ]);
        }
        public function CancelCustExhRegArtist(Request $request)
        {
            $validator = Validator::make($request->all(), [
                'registration_code' => 'required',

            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ]);
            }
            $registration_code = $request->registration_code;

            $registration = DB::table('artist_exhibition_registration')->where('registration_code', $registration_code)->first();

            // dd($registration);
            if (!$registration) {
                return response()->json(['status' => 'false', 'message' => 'Registration not found.']);
            }

            DB::table('artist_exhibition_registration')->where('registration_code', $registration_code)->delete();

            return response()->json([
                'status' => 'true',
                'message' => 'Registration canceled and deleted successfully.',
            ]);
        }

        public function getCustomerExhibitions(Request $request)
        {
            $request->validate([
                'customer_unique_id' => 'required',
            ]);

            $customer_unique_id = $request->input('customer_unique_id');
            $customer = Customer::where('customer_unique_id', $customer_unique_id)->first();

            if (!$customer) {
                return response()->json(['status' => 'false', 'message' => 'Customer not found.']);
            }

            $customerId = $customer->customer_id;
            $currentDate = now();

            // Fetch registrations with exhibitions using a raw query
            $registrations = DB::table('exhibition_registration')
                ->join('exhibitions', 'exhibitions.exhibition_id', '=', 'exhibition_registration.exhibition_id')
                ->where('exhibition_registration.customer_id', $customerId)
                ->where('exhibition_registration.status', 'Active')
                ->select(
                    'exhibition_registration.registration_code',
                    'exhibitions.exhibition_id',
                    'exhibitions.start_date',
                    'exhibitions.end_date',
                    'exhibitions.name as exhibition_name',
                    'exhibitions.description as exhibition_description',
                    'exhibitions.logo'
                )
                ->get();

            // Organize data into upcoming and recent exhibitions
            $upcomingExhibitions = [];
            $recentExhibitions = [];
            // $baseUrl = url('/');

            foreach ($registrations as $registration) {
                // $customer_name= $registration->name;
                $exhibitionData = [
                    'registration_code' => $registration->registration_code,
                    'exhibition_id' => $registration->exhibition_id,
                    'name' => $registration->exhibition_name,
                    'description' => $registration->exhibition_description,
                    'start_date' => $registration->start_date,
                    'end_date' => $registration->end_date,
                    'logo' => url('/') . '/' . $registration->logo,
                    // 'customer_name'=>$customer_name,
                ];

                if ($registration->start_date < $currentDate) {
                    $upcomingExhibitions[] = $exhibitionData;
                } else {
                    $recentExhibitions[] = $exhibitionData;
                }
            }

            if (empty($upcomingExhibitions) && empty($recentExhibitions)) {
                return response()->json([
                    'status' => 'false',
                    'message' => 'Not registered in exhibitions yet.',
                ]);
            }

            return response()->json([
                'status' => 'true',
                'message' => 'Exhibition data retrieved successfully.',
                'upcoming_exhibitions' => $upcomingExhibitions,
                'recent_exhibitions' => $recentExhibitions,
            ]);
        }


        public function privateArtEnquiry(Request $request)
        {
            $validationRules = [
                'customer_unique_id' => 'required|string',
            ];

            $validator = Validator::make($request->all(), $validationRules);

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

            // $enquiries = DB::table('private_sale_enquiry')
            //     ->join('art', 'private_sale_enquiry.art_id', '=', 'art.art_id')
            //     ->join('art_images', 'art_images.art_id', '=', 'art.art_id')
            //     ->where('private_sale_enquiry.customer_id', $customer->customer_id)
            //     ->where('type', 'first')
            //     ->groupBy('private_sale_enquiry.private_sale_enquiry_id')
            //     ->get();

            $enquiries = DB::table('private_sale_enquiry')
                ->join('art', 'private_sale_enquiry.art_id', '=', 'art.art_id')
                ->leftJoin('art_images', 'art_images.art_id', '=', 'art.art_id')
                ->where('private_sale_enquiry.customer_id', $customer->customer_id)
                ->where('type', 'first')
                ->groupBy('private_sale_enquiry.private_sale_enquiry_id')
                ->select(
                    'private_sale_enquiry.*',
                    'private_sale_enquiry.inserted_date as enquiry_date',
                    'private_sale_enquiry.inserted_time as enquiry_time',
                    'art.*',
                    DB::raw('GROUP_CONCAT(art_images.image) as image_urls')
                )
                ->get();

            foreach ($enquiries as $enquirie) {
                $enquirie->image_urls = url($enquirie->image_urls);
            }


            if ($enquiries->isEmpty()) {
                return response()->json([
                    'status' => 'false',
                    'message' => 'No private art enquiries found.',
                ]);
            }

            $baseUrl = url('/');
            $formattedEnquiries = [];

            foreach ($enquiries as $enquiry) {
                $artImage = DB::table('art_images')
                    ->where('art_id', $enquiry->art_id)
                    ->value('image');

                $formattedEnquiries[] = [
                    'enquiry' => $enquiry,
                    'art_image' => $artImage ? $baseUrl . '/' . $artImage : null,
                ];
            }

            return response()->json([
                'status' => 'true',
                'message' => 'Private art enquiries retrieved successfully.',
                'data' => $formattedEnquiries,
            ]);
        }

        // public function privateArtEnquiry(Request $request)
        // {
        //     $validationRules = [
        //         'customer_unique_id' => 'required|string',
        //     ];

        //     $validator = Validator::make($request->all(), $validationRules);

        //     if ($validator->fails()) {
        //         return response()->json([
        //             'status' => 'false',
        //             'message' => $validator->errors()->first(),
        //         ]);
        //     }

        //     $customer = Customer::where('customer_unique_id', $request->input('customer_unique_id'))->first();

        //     if (!$customer) {
        //         return response()->json(['status' => 'false', 'message' => 'Customer not found.']);
        //     }

        //     $enquiries = DB::table('private_sale_enquiry')
        //         ->join('art', 'private_sale_enquiry.art_id', '=', 'art.art_id')
        //         ->where('private_sale_enquiry.customer_id', $customer->customer_id)
        //         ->where('type', 'first')
        //         ->get();

        //     if ($enquiries->isEmpty()) {
        //         return response()->json([
        //             'status' => 'false',
        //             'message' => 'No private art enquiries found.',
        //         ]);
        //     }

        //     return response()->json([
        //         'status' => 'true',
        //         'message' => 'Private art enquiries retrieved successfully.',
        //         'data' => $enquiries,
        //     ]);
        // }


        public function privateArtReply(Request $request)
        {
            $validationRules = [
                'customer_unique_id' => 'required|string',
                'art_unique_id' => 'required|string',
            ];

            $validator = Validator::make($request->all(), $validationRules);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'false',
                    'message' => $validator->errors()->first(),
                ]);
            }
            $arttt = Art::where('art_unique_id', $request->input('art_unique_id'))->first();
            $art_id = $arttt->art_id;

            $customer = Customer::where('customer_unique_id', $request->input('customer_unique_id'))->first();

            if (!$customer) {
                return response()->json(['status' => 'false', 'message' => 'Customer not found.']);
            }

            $art = Art::where('art_id', $art_id)->first();


            if (!$art) {
                return response()->json(['status' => 'false', 'message' => 'Art not found.']);
            }

            $enquiries = DB::table('private_sale_enquiry')
                ->join('art', 'private_sale_enquiry.art_id', '=', 'art.art_id')
                ->where('private_sale_enquiry.customer_id', $customer->customer_id)
                ->where('private_sale_enquiry.art_id', $art_id)
                ->get();

            if ($enquiries->isEmpty()) {
                return response()->json([
                    'status' => 'false',
                    'message' => 'No private art enquiries found for the given art ID.',
                ]);
            }

            return response()->json([
                'status' => 'true',
                'message' => 'Private art enquiry retrieved successfully.',
                'data' => $enquiries,
                'art_data' => $art,
            ]);
        }

        public function ArtEnquiry(Request $request)
        {
            $validationRules = [
                'customer_unique_id' => 'required|string',
            ];

            $validator = Validator::make($request->all(), $validationRules);

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

            // $enquiries = DB::table('art_enquiry')
            //     ->join('art', 'art_enquiry.art_id', '=', 'art.art_id')
            //     ->join('art_images', 'art_images.art_id', '=', 'art.art_id')
            //     ->where('art_enquiry.customer_id', $customer->customer_id)
            //     ->where('type', 'first')
            //     ->groupBy('art_enquiry.art_enquiry_id')
            //     ->get();

            $enquiries = DB::table('art_enquiry')
                ->join('art', 'art_enquiry.art_id', '=', 'art.art_id')
                ->leftJoin('art_images', 'art_images.art_id', '=', 'art.art_id') // Use LEFT JOIN if some art might not have images
                ->where('art_enquiry.customer_id', $customer->customer_id)
                ->where('type', 'first')
                ->groupBy('art_enquiry.art_enquiry_id')
                ->select(
                    'art_enquiry.*',
                    'art.*',
                    'art_enquiry.inserted_date as enquiry_date',
                    'art_enquiry.inserted_time as enquiry_time',
                    DB::raw('GROUP_CONCAT(art_images.image) as image_urls') // Combine image URLs into a single field
                )
                ->get();

            $baseUrl = url('/');
            $formattedEnquiries = [];
            if ($enquiries->isEmpty()) {
                return response()->json([
                    'status' => 'false',
                    'message' => 'No art enquiries found.',
                ]);
            }
            foreach ($enquiries as $enquiry) {
                $artImage = DB::table('art_images')
                    ->where('art_id', $enquiry->art_id)
                    ->value('image');

                $formattedEnquiries[] = [
                    'enquiry' => $enquiry,
                    'art_image' => $artImage ? $baseUrl . '/' . $artImage : null,
                ];
            }

            return response()->json([
                'status' => 'true',
                'message' => 'Private art enquiries retrieved successfully.',
                'data' => $formattedEnquiries,
            ]);
        }

        public function ArtReply(Request $request)
        {
            $validationRules = [
                'customer_unique_id' => 'required|string',
                'art_unique_id' => 'required|string',
            ];

            $validator = Validator::make($request->all(), $validationRules);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'false',
                    'message' => $validator->errors()->first(),
                ]);
            }
            $arttt = Art::where('art_unique_id', $request->input('art_unique_id'))->first();
            $art_id = $arttt->art_id;

            $customer = Customer::where('customer_unique_id', $request->input('customer_unique_id'))->first();

            if (!$customer) {
                return response()->json(['status' => 'false', 'message' => 'Customer not found.']);
            }

            $art = Art::where('art_id', $art_id)->first();


            if (!$art) {
                return response()->json(['status' => 'false', 'message' => 'Art not found.']);
            }

            $enquiries = DB::table('art_enquiry')
                ->join('art', 'art_enquiry.art_id', '=', 'art.art_id')
                ->where('art_enquiry.customer_id', $customer->customer_id)
                ->where('art_enquiry.art_id', $art_id)
                ->get();

            if ($enquiries->isEmpty()) {
                return response()->json([
                    'status' => 'false',
                    'message' => 'No art enquiries found for the given art ID.',
                ]);
            }

            return response()->json([
                'status' => 'true',
                'message' => 'Art enquiry retrieved successfully.',
                'data' => $enquiries,
                'art_data' => $art,
            ]);
        }

        public function sendArtReply(Request $request)
        {
            $request->validate([
                'customer_unique_id' => 'required',
                'art_unique_id' => 'required',
                'message' => 'required',
                'type' => 'required|in:private,other',
            ]);

            $currentDateTime = Carbon::now();
            $insertDate = $currentDateTime->toDateString();
            $insertTime = $currentDateTime->toTimeString();

            // Fetch art
            $arttt = Art::where('art_unique_id', $request->input('art_unique_id'))->first();
            if (!$arttt) {
                return response()->json(['status' => 'false', 'message' => 'Art not found.'], 404);
            }
            $art_id = $arttt->art_id;

            // Fetch customer
            $customer = Customer::where('customer_unique_id', $request->input('customer_unique_id'))->first();
            if (!$customer) {
                return response()->json(['status' => 'false', 'message' => 'Customer not found.'], 404);
            }

            // Check type
            if ($request->input('type') == 'private') {
                $enquiries = PrivateSaleEnquiry::where('customer_id', $customer->customer_id)
                    ->where('art_id', $art_id)
                    ->first();

                if (!$enquiries) {
                    return response()->json([
                        'status' => 'false',
                        'message' => 'No previous private sale enquiry found.',
                    ]);
                }

                $exhibition = PrivateSaleEnquiry::create([
                    'customer_id' => $customer->customer_id,
                    'art_id' => $art_id,
                    'name' => $enquiries->name,
                    'email' => $enquiries->email,
                    'mobile' => $enquiries->mobile,
                    'message' => $request->input('message'),
                    'type' => 'reply',
                    'role' => 'customer',
                    'inserted_date' => $insertDate,
                    'inserted_time' => $insertTime,
                ]);
            } else {
                $enquiries = ArtEnquiry::where('customer_id', $customer->customer_id)
                    ->where('art_id', $art_id)
                    ->first();

                if (!$enquiries) {
                    return response()->json([
                        'status' => 'false',
                        'message' => 'No previous art enquiry found.',
                    ]);
                }

                $exhibition = ArtEnquiry::create([
                    'customer_id' => $customer->customer_id,
                    'art_id' => $art_id,
                    'name' => $enquiries->name,
                    'email' => $enquiries->email,
                    'mobile' => $enquiries->mobile,
                    'message' => $request->input('message'),
                    'type' => 'reply',
                    'role' => 'customer',

                    'inserted_date' => $insertDate,
                    'inserted_time' => $insertTime,
                ]);
            }
            $enquiries = ArtEnquiry::where('customer_id', $customer->customer_id)
                ->where('art_id', $art_id)
                ->get();

            return response()->json([
                'status' => 'true',
                'message' => 'Enquiry reply sent successfully.',
                'data' => $exhibition,
            ]);
        }
        public function getSingleTicket(Request $request)
        {
            $request->validate([
                'registration_code' => 'required|string'
            ]);

            $registrationCode = $request->input('registration_code');

            $registration = ExhibitionRegistration::where('registration_code', $registrationCode)->first();
            // dd($registration);
            if (!$registration) {
                return response()->json(['status' => 'false', 'message' => 'Registration not found.']);
            }
            // if($registration->status != 'Active'){
            //     return response()->json(['status' => 'false', 'message' => 'Payment Not Done.']);
            // }

            $exhibition = DB::table('exhibitions')->where('exhibition_id', $registration->exhibition_id)
                ->leftjoin('countries', 'countries.country_id', '=', 'exhibitions.country')
                ->leftjoin('states', 'states.state_subdivision_id', '=', 'exhibitions.state')
                ->leftjoin('cities', 'cities.cities_id', '=', 'exhibitions.city')
                // ->select('exhibitions.*', 'countries.name as country_name', 'states.name as state_name', 'cities.name as city_name') // Select necessary fields
                ->first();

                // dd($exhibition);
            $exhibition->logo = isset($exhibition->logo) ? url($exhibition->logo) : null;

            // $sponsors = DB::table('exhibition_sponsor')
            //     ->where('exhibition_id', $registration->exhibition_id)
            //     ->get();


            // $spo = [];
            // foreach ($sponsors as $sponsor) {
            //     $logo = url('/') . '/' . $sponsor->logo;
            //     $spo[] = [
            //         'logo' => $logo,
            //     ];
            // }

            $purpose_booking = $registration->purpose_booking;
            $timeSlot = DB::table('exhibition_time_slot')
                ->where('exhibition_time_slot_id', $registration->exhibition_time_slot_id)
                ->first();

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
                'purpose_booking' => $registration->purpose_booking,
                'exhibtion_date' => $registration->exhibtion_date,
                'slot_name' => $registration->slot_name,
                'registration' => $registration,
            ];

            return response()->json([
                'status' => 'true',
                'message' => 'Registration activated successfully for the free exhibition.',
                'data' => $result,
            ]);
        }

        public function registerForExhibitionNew(Request $request)
        {

            $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'mobile' => 'required|string|max:15',
                'exhibition_unique_id' => 'required',
                'address' => 'required|string|max:200',
                'customer_unique_id' => 'required|string',
                'role' => 'required|string',
                'purpose_booking' => 'required',
                'date' => 'required',
                'exhibition_time_slot_id' => 'required',
                'amount' => 'required',
                'country' => 'required|string|max:200',
                'state' => 'required|string|max:200',
                'city' => 'required|string|max:200',
                'zip_code' => 'required|string|max:200'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ], 400);
            }



            $exhibition = Exhibition::where('exhibition_unique_id', $request->exhibition_unique_id)->first();


            if (!$exhibition) {
                return response()->json([
                    'status' => false,
                    'message' => 'No Exhibiton found',
                ]);
            }
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
            $exhibitionId = $exhibition->exhibition_id;
            $address = $request->input('address');
            $role = $request->input('role');
            $country = $request->input('country');
            $state = $request->input('state');
            $city = $request->input('city');
            $zip_code = $request->input('zip_code');

            $purpose_booking = $request->input('purpose_booking');
            $date = $request->input('date');
            $exhibition_time_slot_id = $request->input('exhibition_time_slot_id');


              $userExists = ExhibitionRegistration::where('email', $email)
            ->where('exhibition_id', $exhibition->exhibition_id)
            ->where('status', 'Active')
            ->first();




        if ($userExists) {
            return response()->json(['status' => 'false', 'message' => 'Email  already registered for this exhibition']);
        }


            $exhibitionUniqueId = $exhibition->exhibition_unique_id;

            $existingRegistration = DB::table('exhibition_registration')->where('exhibition_id', $exhibitionId)
                ->where('mobile', $mobile)
                ->where('status', 'Inactive')
                ->first();

            $amount = $existingRegistration->amount ?? null;
            $isPaid = $amount > 0 ? 1 : 0;

            $exhibitions = [
                'exhibition_id' => $exhibition->exhibition_id,
                'exhibition_unique_id' => $exhibition->exhibition_unique_id,
                'name' => $exhibition->name,
                'tagline' => $exhibition->tagline,
                'description' => $exhibition->description,
                'start_date' => $exhibition->start_date,
                'end_date' => $exhibition->end_date,
                'inserted_date' => $exhibition->inserted_date,
                'updated_date' => $exhibition->updated_date,
                'country' => $exhibition->country,
                'state' => $exhibition->state,
                'city' => $exhibition->city,
                'address1' => $exhibition->address1,
                'address2' => $exhibition->address2,
                'contact_number' => $exhibition->contact_number,
                'contact_email' => $exhibition->contact_email,
                'website_link' => $exhibition->website_link,
                'status' => $exhibition->status,
                'logo' => url($exhibition->logo),

            ];


            $exhibitions['amount'] = strval($amount);

            if ($existingRegistration) {

                ExhibitionRegistration::where('exhibition_id', $exhibitionId)
                    ->where('mobile', $mobile)
                    ->where('status', 'Inactive')
                    ->update([
                        'name' => $name,
                        'email' => $email,
                        'address' => $address,
                        'role' => $role,
                        'purpose_booking' => $request->purpose_booking,
                        'exhibtion_date' => $request->date,
                        'exhibition_time_slot_id' => $request->exhibition_time_slot_id,
                        'amount' => $request->amount,
                        // 'isPaid' => $isPaid,
                        'country' => $country,
                        'state' => $state,
                        'city' => $city,
                        'zip_code' => $zip_code,
                    ]);


                return response()->json([
                    'status' => 'true',
                    'message' => 'Customer registration updated successfully.',
                    'exhibition_data' => $exhibitions,
                    'registration_code' => $existingRegistration->registration_code,
                    'amount' => $request->amount,
                    'isPaid' => $isPaid,
                ]);
            }

            $totalRegistrations = ExhibitionRegistration::where('exhibition_id', $exhibitionId)->where('purpose_booking', $purpose_booking)->count();
            $registrationCode = $exhibitionUniqueId . ($totalRegistrations + 1);

            $type = $request->type;
            $allowColumn = '';

            switch ($type) {
                case 'visitor':
                    $allowColumn = 'visitor_count';
                    break;
                case 'private':
                    $allowColumn = 'private_visitor_count';
                    break;
                case 'auction':
                    $allowColumn = 'auction_visitor_count';
                    break;
                default:
                    return response()->json([
                        'status' => false,
                        'message' => 'Invalid type provided',
                    ]);
            }


            if ($role == 'customer') {
                $news = DB::table('exhibition_time_slot')
                    ->where('exhibition_id', $exhibition->exhibition_id)
                    ->where('date', $request->date)
                    ->where($allowColumn, '!=', null)
                    ->select($allowColumn)
                    ->first();

                if ($news && isset($news->$allowColumn)) {
                    if ($totalRegistrations >= $news->$allowColumn) {
                        return response()->json([
                            'status' => false,
                            'message' => 'Registration limit reached',
                        ]);
                    }
                }
            }
            $slot=DB::table('exhibition_time_slot')
            ->where('exhibition_time_slot_id', $request->exhibition_time_slot_id)
            ->first();


            $registration = ExhibitionRegistration::create([
                'name' => $name,
                'email' => $email,
                'customer_id' => $customer->customer_id,
                'mobile' => $mobile,
                'exhibition_id' => $exhibitionId,
                'registration_code' => $registrationCode,
                'address' => $address,
                'status' => 'Inactive',
                'purpose_booking' => $request->purpose_booking,
                'exhibtion_date' => $request->date,
                'exhibition_time_slot_id' => $request->exhibition_time_slot_id,
                'slot_name' => $slot->slot_name,
                'role' => $role,
                'amount' => $request->amount,
                'country' => $country,
                'state' => $state,
                'city' => $city,
                'zip_code' => $zip_code,
            ]);

            $existingRegistration = DB::table('exhibition_registration')->where('exhibition_id', $exhibitionId)
                ->where('mobile', $mobile)
                ->where('status', 'Inactive')
                ->first();
            $amount = $existingRegistration->amount ?? null;
            $isPaid = $amount > 0 ? 1 : 0;


            $exhibitions['amount'] = strval($request->amount);

            return response()->json([
                'status' => 'true',
                'message' => 'Customer registered successfully.',
                'exhibition_data' => $exhibitions,
                'amount' => $request->amount,
                'isPaid' => $isPaid,
                'registration_code' => $registration->registration_code,
            ]);
        }
        public function getCustomerExhibitionsseller(Request $request)
        {
            $request->validate([
                'customer_unique_id' => 'required',
            ]);

            $customer_unique_id = $request->input('customer_unique_id');
            $customer = Customer::where('customer_unique_id', $customer_unique_id)->first();

            if (!$customer) {
                return response()->json(['status' => 'false', 'message' => 'Customer not found.']);
            }

            $customerId = $customer->customer_id;
            $currentDate = now();

            // Fetch registrations with exhibitions using a raw query
            $registrations = DB::table('artist_exhibition_registration')
                ->join('exhibitions', 'exhibitions.exhibition_id', '=', 'artist_exhibition_registration.exhibition_id')
                ->where('artist_exhibition_registration.customer_id', $customerId)
                ->where('artist_exhibition_registration.status', 'Active')
                ->select(
                    'artist_exhibition_registration.registration_code',
                    'exhibitions.exhibition_id',
                    'exhibitions.start_date',
                    'exhibitions.end_date',
                    'exhibitions.name as exhibition_name',
                    'exhibitions.description as exhibition_description',
                    'exhibitions.logo'
                )
                ->get();

            // Organize data into upcoming and recent exhibitions
            $upcomingExhibitions = [];
            $recentExhibitions = [];
            // $baseUrl = url('/');

            foreach ($registrations as $registration) {
                // $customer_name= $registration->name;
                $exhibitionData = [
                    'registration_code' => $registration->registration_code,
                    'exhibition_id' => $registration->exhibition_id,
                    'name' => $registration->exhibition_name,
                    'description' => $registration->exhibition_description,
                    'start_date' => $registration->start_date,
                    'end_date' => $registration->end_date,
                    'logo' => url('/') . '/' . $registration->logo,
                    // 'customer_name'=>$customer_name,
                ];

                if ($registration->start_date < $currentDate) {
                    $upcomingExhibitions[] = $exhibitionData;
                } else {
                    $recentExhibitions[] = $exhibitionData;
                }
            }

            if (empty($upcomingExhibitions) && empty($recentExhibitions)) {
                return response()->json([
                    'status' => 'false',
                    'message' => 'Not registered in exhibitions yet.',
                ]);
            }

            return response()->json([
                'status' => 'true',
                'message' => 'Exhibition data retrieved successfully.',
                'upcoming_exhibitions' => $upcomingExhibitions,
                'recent_exhibitions' => $recentExhibitions,
            ]);
        }
        public function getSingleTicketseller(Request $request)
        {
            $request->validate([
                'registration_code' => 'required|string'
            ]);

            $registrationCode = $request->input('registration_code');

            $registration = DB::table('artist_exhibition_registration')->where('registration_code', $registrationCode)->first();

            if (!$registration) {
                return response()->json(['status' => 'false', 'message' => 'Registration not found.']);
            }
            // if($registration->status != 'Active'){
            //     return response()->json(['status' => 'false', 'message' => 'Payment Not Done.']);
            // }

            $exhibition = Exhibition::where('exhibition_id', $registration->exhibition_id)
                ->join('countries', 'countries.country_id', '=', 'exhibitions.country')
                ->join('states', 'states.state_subdivision_id', '=', 'exhibitions.state')
                ->join('cities', 'cities.cities_id', '=', 'exhibitions.city')
                // ->select('exhibitions.*', 'countries.name as country_name', 'states.name as state_name', 'cities.name as city_name') // Select necessary fields
                ->first();

            $exhibition->logo = isset($exhibition->logo) ? url($exhibition->logo) : null;
            $exhibition->banner = isset($exhibition->banner) ? url($exhibition->banner) : null;

            $sponsors = DB::table('exhibition_sponsor')
                ->where('exhibition_id', $registration->exhibition_id)
                ->get();


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
                // 'exhibition' => $exhibition,
                'customer_data' => [
                    'name' => $registration->name,
                    'email' => $registration->email,
                    'mobile' => $registration->mobile
                ],
                // 'sponsors' => $spo,
                'logo' => $baseUrl . '/' . $exhibition->logo,
                'registration_code' => $registration->registration_code,

                // 'exhibtion_date' => $registration->exhibtion_date,

                'registration' => $registration,


            ];

            return response()->json([
                'status' => 'true',
                'message' => 'Registration activated successfully for the free exhibition.',
                'data' => $result,
            ]);
        }

        public function delete_exhibition(Request $request){
            $validator = Validator::make($request->all(), [
                "exhibition_unique_id" => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ], 400);
            }


            $exhibitions = DB::table('exhibitions')->where('exhibition_unique_id', $request->exhibition_unique_id)->first();

            if (!$exhibitions) {
                return response()->json([
                    'status' => false,
                    'message' => 'No exhibitions Found!',
                ]);
            }

            DB::beginTransaction();
            try {
                $exhibition_id = $exhibitions->exhibition_id;

                DB::table('exhibition_art')->where('exhibition_id', $exhibition_id)->delete();
            $exhibition_booths =  DB::table('exhibition_booths')->where('exhibition_id', $exhibition_id)->get();
            foreach($exhibition_booths as $exhibition_booth){
                DB::table('booth_seats')->where('exhibition_booth_id', $exhibition_booth->exhibition_booth_id)->delete();

            }
            DB::table('exhibition_booths')->where('exhibition_id', $exhibition_id)->delete();
            DB::table('exhibition_gallery')->where('exhibition_id', $exhibition_id)->delete();
            DB::table('exhibition_guests')->where('exhibition_id', $exhibition_id)->delete();
            DB::table('exhibition_paras')->where('exhibition_id', $exhibition_id)->delete();
            DB::table('exhibition_sponsor')->where('exhibition_id', $exhibition_id)->delete();
            DB::table('exhibition_term')->where('exhibition_id', $exhibition_id)->delete();
            DB::table('exhibition_time_slot')->where('exhibition_id', $exhibition_id)->delete();



                $delete =DB::table('exhibitions')->where('exhibition_id', $exhibition_id)->delete();


                if ($delete) {
                    DB::commit();
                    return response()->json([
                        'status' => true,
                        'message' => 'exhibitions Cancelled and all related data removed!',
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


        public function update_exhibition_guest(Request $request)
        {
            if (!Auth::guard('api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ]);
            }

            $validator = Validator::make($request->all(), [
                'exhibition_guest_id' => 'required|exists:exhibition_guests,exhibition_guest_id',

            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ], 400);
            }
            $exhibition_guest_id = $request->exhibition_guest_id;

            $exhibition_guests = DB::table('exhibition_guests')->where('exhibition_guest_id', $exhibition_guest_id)->first();

            if (!$exhibition_guests) {
                return response()->json([
                    'status' => false,
                    'message' => 'No exhibition guest Found',
                ]);
            }

            if ($request->hasFile('photo')) {

                $file = $request->file('photo');

                $filename = Str::random(10) . '_' . str_replace(' ', '_', $file->getClientOriginalName());
                $filePath = 'exhibition/guest/' . $filename;

                $storedPath = Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');

                if (!$storedPath) {
                    return response()->json(['error' => 'File upload failed'], 500);
                }

                $fileUrl = Storage::disk('s3')->url($filePath);

                // $file = $request->file('photo');
                // $fileName = time() . '_' . Str::random(5) . '.' . $file->getClientOriginalExtension();
                // $filePath = 'exhibition/guest/' . $fileName;
                // $file->move(public_path('exhibition/guest/'), $fileName);

                $user = Auth::guard('api')->user();
                $timezone = $user->timezone ?? "America/Los_Angeles";

                $currentDateTime = now($timezone);


                $guest = ExhibitionGuest::create([
                    'exhibition_id' => $exhibition->exhibition_id,
                    'name' => $request->name,
                    'message' => $request->message,
                    'photo' => $fileUrl??null,
                    'position' => $request->position,
                    'status' => 'Active',
                    'inserted_date' => $currentDateTime->toDateString(),
                    'inserted_time' => $currentDateTime->toTimeString(),
                ]);

                $id = $guest->exhibition_guest_id;
                // dd($id);
                $data = ExhibitionGuest::where('exhibition_guest_id', $guest->exhibition_guest_id)->get();

                foreach ($data as $value) {
                    $value->photo = isset($value->photo) ? url($value->photo) : null;
                }
                return response()->json([
                    'status' => true,
                    'message' => 'Exhibition Guest added successfully.',
                    'data' => $data
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'No image file or Data provided.',
                ], 400);
            }
        }

        public function addExhibitionGallery(Request $request)
        {
            if (!Auth::guard('api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ]);
            }

            $validator = Validator::make($request->all(), [
                'exhibition_unique_id' => 'required|exists:exhibitions,exhibition_unique_id',
                'link' => 'required|url',
                'tagline' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ], 400);
            }

            $exhibition = DB::table('exhibitions')
                ->where('exhibition_unique_id', $request->exhibition_unique_id)
                ->first();

            if (!$exhibition) {
                return response()->json([
                    'status' => false,
                    'message' => 'Exhibition not found.',
                ], 404);
            }

            $timezone = $user->timezone ?? "America/Los_Angeles";

            $currentDateTime = now($timezone);

            $galleryData = [
                'exhibition_id' => $exhibition->exhibition_id,
                'link' => $request->link,
                'tagline' => $request->tagline,
                'status' => 'Active',
                'inserted_date' => $currentDateTime->toDateString(),
                'inserted_time' => $currentDateTime->toTimeString()
            ];

            DB::table('exhibition_gallery')->insert($galleryData);

            $galleryEntries = DB::table('exhibition_gallery')
                ->where('exhibition_id', $exhibition->exhibition_id)
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Gallery entry added successfully.',
                'gallery_entries' => $galleryEntries
            ]);
        }




        public function get_private_order_detail(Request $request){
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
            $privateOrder=DB::table('private_ordered_art')
                            ->where('art_id',$art->art_id)
                            ->first();
            if (!$privateOrder) {
                return response()->json([
                    'status' => false,
                    'message' => 'No  order found for this art',
                ]);
            }

            return response()->json([
                'status'=>true,
                'data'=>$privateOrder
            ]);
        }

        public function update_exhibition_sponsor(Request $request)
        {
            if (!Auth::guard('api')->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access.',
                ]);
            }

            $validator = Validator::make($request->all(), [
                'exhibition_sponsor_id' => 'required|exists:exhibition_sponsor,exhibition_sponsor_id',

            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ], 400);
            }
            $exhibition_sponsor_id = $request->exhibition_sponsor_id;

            $exhibition_sponsor = DB::table('exhibition_sponsor')->where('exhibition_sponsor_id', $exhibition_sponsor_id)->first();

            if (!$exhibition_sponsor_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'No exhibition sponsor Found',
                ]);
            }

            if ($request->hasFile('logo')) {
                $file = $request->file('logo');

                $filename = Str::random(10) . '_' . str_replace(' ', '_', $file->getClientOriginalName());
                $filePath = 'exhibition/sponsor/' . $filename;

                $storedPath = Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');

                if (!$storedPath) {
                    return response()->json(['error' => 'File upload failed'], 500);
                }

                $fileUrl = Storage::disk('s3')->url($filePath);

                // $file = $request->file('logo');
                // $fileName = time() . '_' . Str::random(5) . '.' . $file->getClientOriginalExtension();
                // $filePath = 'exhibition/sponsor/' . $fileName;
                // $file->move(public_path('exhibition/sponsor/'), $fileName);
            } else {
                $fileUrl = $exhibition_sponsor->logo;
            }



            $updatedRows =DB::table('exhibition_sponsor')->where('exhibition_sponsor_id', $exhibition_sponsor_id)
                ->update([

                'title' => $request->title??$exhibition_sponsor->title,
                    'logo' => $fileUrl,

                ]);

            $data = [
                'title' => $request->title,
                    'logo' =>  $fileUrl,
            ];



            if ($updatedRows > 0) {
                return response()->json([
                    'status' => true,
                    'message' => 'Exhibition Sponsor updated successfully!',
                    'data' => $data
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'No records updated.'
                ]);
            }



        }

        public function delete_exhibition_sponsor(Request $request){
            $validator = Validator::make($request->all(), [
                "exhibition_sponsor_id" => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ], 400);
            }


            $exhibition_sponsor_id = DB::table('exhibition_sponsor')->where('exhibition_sponsor_id', $request->exhibition_sponsor_id)->first();

            if (!$exhibition_sponsor_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'No exhibitions sponsor Found!',
                ]);
            }

            DB::beginTransaction();
            try {



                $delete =DB::table('exhibition_sponsor')->where('exhibition_sponsor_id', $request->exhibition_sponsor_id)->delete();


                if ($delete) {
                    DB::commit();
                    return response()->json([
                        'status' => true,
                        'message' => 'exhibitions sponsor data removed!',
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





        public function get_private_art_status(Request $request){
            $validator = Validator::make($request->all(), [

                'art_unique_id' => 'required|exists:art,art_unique_id',

            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ], 400);
            }

            $art_unique_id = $request->art_unique_id;

            $art = DB::table('art')->where('art_unique_id', $art_unique_id)
                ->first();

            if (!$art) {
                return response()->json([
                    'status' => false,
                    'message' => 'Art not found',
                ]);
            }
            $data = DB::table('tracking_status')
        ->where('status', 'Active')
        ->where('type', 'delivery')
        ->pluck('tracking_status');
        $privateOrder = DB::table('private_ordered_art')->where('art_id', $art->art_id)->first();
            // dd($privateOrder);
        if($privateOrder->custom_delivery=='Yes'){
            return response()->json([
                'status'=>true,
                'is_delivery'=>true,
                'current_status'=>$privateOrder->art_order_status,
                'track_status_list'=>$data,
            ]);
        }else{
            return response()->json([
                'status'=>true,
                'is_delivery'=>false,
                'pick_date'=>$privateOrder->pick_date,

            ]);
        }
        }

        public function admin_update_status(Request $request)
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
            $art = DB::table('art')->where('art_unique_id', $request->art_unique_id)->first();
            $artImage = ArtImage::where('art_id', $art->art_id)->first();
            $customer=Customer::where('customer_id',$art->customer_id)->first();
            $privateOrder = DB::table('private_ordered_art')->where('art_id', $art->art_id)->first();

            if (!$privateOrder) {
                return response()->json([
                    'status' => false,
                    'message' => 'No  order found for this art',
                ]);
            }
            $updateData = [
                'art_order_status' => $request->art_order_status
            ];

            $currentDateTime = Carbon::now();
            $insertDate = $currentDateTime->toDateString();
               $updateResult = DB::table('private_ordered_art')
                ->where('art_id', $art->art_id)
                ->update([
                    'art_order_status' => $request->tracking_status??$privateOrder->art_order_status
                ]);
            if ($updateResult > 0) {
                if (!empty($customer->fcm_token)) {
                    try {
                        $firebase = (new Factory)->withServiceAccount(base_path('mira-monet-firebase-adminsdk-fbsvc-864230166e.json'));
                        $messaging = $firebase->createMessaging();
                        $fcm_token = $customer->fcm_token;

                        $messageData = CloudMessage::withTarget('token', $fcm_token)
                            ->withNotification([
                                'title' => 'Your Artwork  ' . $art->title  . 'Is Being Crafted!',
                                'body' => 'Thank you for your order! Your Order tracking status is ' . $request->tracking_status,
                                'image' => isset($artImage->image) ? url($artImage->image) : null,
                            ]);

                        $messaging->send($messageData);
                    } catch (\Exception $e) {
                        Log::error("FCM Notification Error: " . $e->getMessage());
                    }
                }

                return response()->json([
                    'status' => true,
                    'message' => 'Ordered Art Tracking status successfully updated to ' . $request->tracking_status,
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to update Ordered Art status',
                ]);
            }


            // $updateResult = DB::table('private_ordered_art')
            //     ->where('art_id', $art->art_id)
            //     ->update([
            //         'art_order_status' => $request->art_order_status??$privateOrder->art_order_status
            //     ]);


            //     if (!empty($customer->fcm_token)) {
            //         try {
            //             $firebase = (new Factory)->withServiceAccount(base_path('mira-monet-firebase-adminsdk-fbsvc-864230166e.json'));
            //             $messaging = $firebase->createMessaging();
            //             $fcm_token = $customer->fcm_token;

            //             $messageData = CloudMessage::withTarget('token', $fcm_token)
            //                 ->withNotification([
            //                     'title' => 'Your Artwork  ' . $art->title  . 'Is Being Crafted!',
            //                     'body' => 'Thank you for your order! Your Order tracking status is ' . $request->tracking_status,
            //                     'image' => isset($artImage->image) ? url($artImage->image) : null,
            //                 ]);

            //             $messaging->send($messageData);
            //         } catch (\Exception $e) {
            //             \Log::error("FCM Notification Error: " . $e->getMessage());
            //         }
            //     }
            //     return response()->json([
            //         'status' => true,
            //         'message' => 'Ordered Art Tracking status successfully updated to ' . $request->tracking_status,
            //     ]);


        }


        }



