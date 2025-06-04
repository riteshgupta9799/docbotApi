<?php

namespace App\Http\Controllers;

use App\Models\EventImage;
use App\Models\UpcomingEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Validator;

class EventController extends Controller
{
    //
    public function add_upcoming_event(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'event_title' => 'required',
            'user_id' => 'required',
            'event_images' => 'required|array',
            'event_type' => 'required',
            'event_range' => 'required',
            'event_price' => 'required',
            'country' => 'required',
            'state' => 'required',
            'city' => 'required',
            'zip_code' => 'required',
            'started_date' => 'required',
            'ended_date' => 'required',
            'started_time' => 'required',
            'ended_time' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        $eventData = [
            'user_id' => $request->user_id,
            'event_title' => $request->event_title,
            'event_type' => $request->event_type,
            'event_range' => $request->event_range,
            'event_price' => $request->event_price,
            'country' => $request->country,
            'state' => $request->state,
            'city' => $request->city,
            'zip_code' => $request->zip_code,
            'started_date' => $request->started_date,
            'ended_date' => $request->ended_date,
            'started_time' => $request->started_time,
            'ended_time' => $request->ended_time,
            'status' => 'Active',
            'inserted_date' => $insertDate,
            'inserted_time' => $insertTime,
        ];

        $upcomingEvent = UpcomingEvent::create($eventData);

        foreach ($request->event_images as $image) {
            EventImage::create([
                'upcoming_events_id' => $upcomingEvent->upcoming_events_id,
                'event_image' => $image,
                'status' => 'Active',
                'inserted_date' => $insertDate,
                'inserted_time' => $insertTime,
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Event Added successfully',
            // 'project_id' => $eventData,
        ]);
    }

    public function get_upcoming_event(Request $request)
    {
        $country = $request->country;
        $state = $request->state;

        $upcomingEvents = UpcomingEvent::where('status', 'Active')
            ->where(function ($query) use ($country) {
                $query->where('country', $country)->where('event_range', 'within country');
            })
            ->orWhere(function ($query) use ($state) {
                $query->where('state', $state)->where('event_range', 'within state');
            })->orWhere(function ($query) {
                $query->where('event_range', 'world wide');
            })
            ->get();

        if ($upcomingEvents->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Event Not Found!'
            ]);
        }

        $eventDetails = [];

        foreach ($upcomingEvents as $upcomingEvent) {
            $eventDetail = [
                'upcoming_events_id' => $upcomingEvent->upcoming_events_id,
                'user_id' => $upcomingEvent->user_id,
                'event_title' => $upcomingEvent->event_title,
                'event_type' => $upcomingEvent->event_type,
                'event_range' => $upcomingEvent->event_range,
                'event_price' => $upcomingEvent->event_price,
                'country' => $upcomingEvent->country,
                'state' => $upcomingEvent->state,
                'city' => $upcomingEvent->city,
                'zip_code' => $upcomingEvent->zip_code,
                'started_date' => $upcomingEvent->started_date,
                'ended_date' => $upcomingEvent->ended_date,
                'started_time' => $upcomingEvent->started_time,
                'ended_time' => $upcomingEvent->ended_time,
                'status' => $upcomingEvent->status,
                'inserted_date' => $upcomingEvent->inserted_date,
                'inserted_time' => $upcomingEvent->inserted_time,
                'eventImages' => [],
            ];

            foreach ($upcomingEvent->eventImages as $eventImage) {
                $eventDetail['eventImages'][] = [
                    'event_images_id' => $eventImage->event_images_id,
                    'event_image' => $eventImage->event_image
                ];
            }
            $eventDetails[] = $eventDetail;
        }

        return response()->json([
            'status' => true,
            'message' => 'Event Found successfully',
            'eventDetails' => $eventDetails,
        ]);
    }

    public function update_upcoming_event(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'upcoming_events_id' => 'required',
            // 'event_images_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $events = UpcomingEvent::where('upcoming_events_id', $request->upcoming_events_id)->where('status', 'Active')->first();

        if (!$events) {
            return response()->json(['status' => false, 'message' => 'Upcoming Events not found'], 404);
        }

        $eventData = [
            'event_title' => $request->event_title ?? $events->event_title,
            'event_type' => $request->event_type ?? $events->event_type,
            'started_date' => $request->started_date ?? $events->started_date,
            'ended_date' => $request->ended_date ?? $events->ended_date,
            'started_time' => $request->started_time ?? $events->started_time,
            'ended_time' => $request->ended_time ?? $events->ended_time,
        ];

        $updateEvent = $events->update($eventData);

        $currentDateTime = Carbon::now('Asia/Kolkata');
        $insertDate = $currentDateTime->toDateString();
        $insertTime = $currentDateTime->toTimeString();

        if ($request->event_image) {
            foreach ($request->event_image as $imageData) {

                if (!empty($imageData['event_images_id'])) {
                    EventImage::where('upcoming_events_id', $request->upcoming_events_id)
                        ->where('event_images_id', $imageData['event_images_id'])
                        ->where('status', 'Active')
                        ->update([
                            'event_image' => $imageData['event_image'],
                        ]);
                } else {
                    EventImage::create([
                        'upcoming_events_id' => $request->upcoming_events_id,
                        'status' => 'Active',
                        'event_image' => $imageData['event_image'],
                        'inserted_date' => $insertDate,
                        'inserted_time' => $insertTime,
                    ]);
                }
            }
        }

        if ($updateEvent) {
            return response()->json([
                'status' => true,
                'message' => 'Event updated successfully!',
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => 'Failed to update event.',
        ]);
    }

    public function upcoming_event_status_update(Request $request)
    {
        $request->validate([
            'upcoming_events_id' => 'required',
            'status' => 'required'
        ]);

        $existEvent = UpcomingEvent::where('upcoming_events_id', $request->upcoming_events_id)->exists();

        if(!$existEvent){
            return response()->json([
                'status' => false,
                'message' => 'Event Not Found!',
            ]);
        }
        $events = UpcomingEvent::where('upcoming_events_id', $request->upcoming_events_id)->update([
            'status' => $request->status
        ]);

        if (!$events) {
            return response()->json(['status' => false, 'message' => 'This Events Already ' . $request->status], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Event Status Chnage To ' . $request->status . ' successfully',
        ]);
    }

}
