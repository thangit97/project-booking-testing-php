<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Room;
use App\Models\Space;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class BookingController extends Controller
{
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'space_id' => 'required|integer',
                'start_time' => 'required|date_format:Y-m-d H:i:s|before:end_time',
                'end_time' => 'required|date_format:Y-m-d H:i:s|after:start_time',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $params = $validator->validated();
        
            $space = Space::with('room.spaces')->find($params['space_id']);
            if (!$space) {
                return response()->json(['error' => 'Space not found'], 422);
            }
            $room = $space->room;
            $spacesInRoom = $room->spaces->pluck('id');
            $bookings = Booking::whereIn('space_id', $spacesInRoom)->get();

            // Check for conflict
            foreach ($bookings as $booking) {
                if ($this->checkTimeOverlap($booking, $params['start_time'], $params['end_time'])) {
                    return response()->json(['message' => 'The selected time slot is already booked.'], 422);
                }
            }

            $booking = Booking::create($params);

            return response()->json($booking);

        } catch (ModelNotFoundException $ex) {
            return response()->json(['error' => $ex->getMessage()], 422);
        } catch (Exception $ex) {
            return response()->json(['error' => $ex->getMessage()], 500);
        }
    }

     /**
     * Checks for conflicts between two bookings.
     *
     * @param Booking $existingBooking
     * @param string $newStartTime
     * @param string $newEndTime
     * @return bool
     */
    private function checkTimeOverlap(Booking $existingBooking, string $newStartTime, string $newEndTime) : bool
    {

        return ($newStartTime < $existingBooking->end_time && $newEndTime > $existingBooking->start_time) ;
    }

    public function storeMultiple(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                '*.room_id' => 'required|integer',
                '*.start_time' => 'required|date_format:Y-m-d H:i:s|before:*.end_time',
                '*.end_time' => 'required|date_format:Y-m-d H:i:s|after:*.start_time',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $params = $validator->validated();
            $responses = [];
            $bookingsData = [];

            // Retrieve all existing bookings for the spaces in the given rooms
            $roomIds = array_unique(array_column($params, 'room_id'));
            $existingBookings = Booking::whereIn('space_id', function ($query) use ($roomIds) {
                $query->select('id')
                    ->from('spaces')
                    ->whereIn('room_id', $roomIds);
            })->get();

            foreach ($params as $bookingData) {
                $room = Room::with('spaces')->find($bookingData['room_id']);
                if (!$room) {
                    $responses[] = [
                        'booking' => $bookingData,
                        'error' => 'Room not found'
                    ];
                    continue;
                }

                $spacesInRoom = $room->spaces->pluck('id');
                $bookings = $existingBookings->whereIn('space_id', $spacesInRoom);

                $hasConflict = $bookings->some(function ($existingBooking) use ($bookingData) {
                    return $this->checkTimeOverlap($existingBooking, $bookingData['start_time'], $bookingData['end_time']);
                });

                if ($hasConflict) {
                    $responses[] = [
                        'booking' => $bookingData,
                        'message' => 'The selected time slot is already booked.'
                    ];
                    continue;
                }

                $availableSpace = Space::where('room_id', $bookingData['room_id'])->first();
                if (!$availableSpace) {
                    $responses[] = [
                        'booking' => $bookingData,
                        'error' => 'No available spaces in the room.'
                    ];
                    continue;
                }

                $bookingsData[] = [
                    'space_id' => $availableSpace->id,
                    'start_time' => $bookingData['start_time'],
                    'end_time' => $bookingData['end_time'],
                ];
            }

            Booking::insert($bookingsData);

            return response()->json([
                'message' => 'Bookings created successfully.',
                'error' => $responses,
                'data' => $bookingsData
            ]);

        } catch (Exception $ex) {
            return response()->json(['error' => $ex->getMessage()], 500);
        }
    }

}
