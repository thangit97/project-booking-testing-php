<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Room;
use App\Models\Space;
use App\Models\Booking;
use Tests\TestCase;
use Illuminate\Http\Response;

class MultipleBookingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test successful booking creation with valid data and no conflicts.
     */
    /** @test */
    public function test_store_multiple_bookings_success()
    {
        $room = Room::create(['name' => 'Room1']);
        $space = Space::create(['room_id' => $room->id, 'name' => 'Space 1']);
        $response = $this->postJson('/api/bookings/multiple', [
            [
                'room_id' => $room->id,
                'start_time' => '2024-07-25 09:00:00',
                'end_time' => '2024-07-26 12:00:00',
            ],
        ]);

        $response->assertStatus(Response::HTTP_OK)
                 ->assertJson([
                     'message' => 'Bookings created successfully.',
                     'error' => [],
                     'data' => [
                         [
                             'space_id' => $space->id,
                             'start_time' => '2024-07-25 09:00:00',
                             'end_time' => '2024-07-26 12:00:00',
                         ],
                     ],
                 ]);

        $this->assertDatabaseHas('bookings', [
            'space_id' => $space->id,
            'start_time' => '2024-07-25 09:00:00',
            'end_time' => '2024-07-26 12:00:00',
        ]);
    }

    /**
     * Test booking creation with valid data but with time conflicts.
     */
    /** @test */
    public function test_store_multiple_bookings_with_time_conflict()
    {
        $room = Room::create(['name' => 'Room1']);
        $space = Space::create(['room_id' => $room->id, 'name' => 'Space 1']);

        Booking::create([
            'space_id' => $space->id,
            'start_time' => '2024-07-26 09:00:00',
            'end_time' => '2024-07-27 12:00:00',
        ]);
        $response = $this->postJson('/api/bookings/multiple', [
            [
                'room_id' => $room->id,
                'start_time' => '2024-07-26 10:00:00',
                'end_time' => '2024-07-27 11:00:00',
            ],
            [
                'room_id' => $room->id,
                'start_time' => '2024-07-22 09:00:00',
                'end_time' => '2024-07-22 12:00:00',
            ],
        ]);

        $response->assertStatus(Response::HTTP_OK)
                 ->assertJson([
                     'message' => 'Bookings created successfully.',
                     'error' => [
                         [
                             'booking' => [
                                 'room_id' => $room->id,
                                 'start_time' => '2024-07-26 10:00:00',
                                 'end_time' => '2024-07-27 11:00:00',
                             ],
                             'message' => 'The selected time slot is already booked.'
                         ]
                     ],
                     'data' => [
                        [
                            'space_id' => $space->id,
                            'start_time' => '2024-07-22 09:00:00',
                            'end_time' => '2024-07-22 12:00:00',
                        ]
                     ],
                 ]);
    }

     /**
     * Test booking creation with valid data but no available space.
     */
    /** @test */
    public function test_store_multiple_bookings_no_available_space()
    {
        $room = Room::create(['name' => 'Room1']);

        $response = $this->postJson('/api/bookings/multiple', [
            [
                'room_id' => $room->id,
                'start_time' => '2024-07-22 09:00:00',
                'end_time' => '2024-07-22 12:00:00',
            ],
        ]);

        $response->assertStatus(Response::HTTP_OK)
                 ->assertJson([
                     'message' => 'Bookings created successfully.',
                     'error' => [
                         [
                             'booking' => [
                                 'room_id' => $room->id,
                                 'start_time' => '2024-07-22 09:00:00',
                                 'end_time' => '2024-07-22 12:00:00',
                             ],
                             'error' => 'No available spaces in the room.'
                         ]
                     ],
                     'data' => [],
                 ]);
    }

    /**
     * Test booking creation with invalid data.
     */
    /** @test */
    public function test_store_multiple_bookings_invalid_data()
    {

        $response = $this->postJson('/api/bookings/multiple', [
            [
                'room_id' => '',
                'start_time' => 'bbb',
                'end_time' => 'ddd',
            ],
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
                 ->assertJson([
                     'errors' => [
                         '0.room_id' => ['The 0.room_id field is required.'],
                         '0.start_time' => [
                             'The 0.start_time field must match the format Y-m-d H:i:s.',
                             'The 0.start_time field must be a date before 0.end_time.',
                         ],
                         '0.end_time' => [
                             'The 0.end_time field must match the format Y-m-d H:i:s.',
                             'The 0.end_time field must be a date after 0.start_time.',
                         ],
                     ],
                 ]);
    }
}
