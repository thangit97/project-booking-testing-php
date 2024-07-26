<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\Space;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_can_create_a_booking()
    {
        $room = Room::create(['name' => 'Room121']);
        $space = Space::create(['room_id' => $room->id, 'name' => 'Space 12']);

        $response = $this->postJson('/api/bookings', [
            'space_id' => $space->id,
            'start_time' => '2024-07-25 10:00:00',
            'end_time' => '2024-07-25 12:00:00',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas('bookings', [
            'space_id' => $space->id,
            'start_time' => '2024-07-25 10:00:00',
            'end_time' => '2024-07-25 12:00:00',
        ]);
    }

    /** @test */
    public function test_detects_conflict_with_existing_bookings()
    {
        $room = Room::create(['name' => 'Room2']);
        $space = Space::create(['room_id' => $room->id, 'name' => 'Space 2']);

        Booking::create([
            'space_id' => $space->id,
            'start_time' => '2024-07-25 09:00:00',
            'end_time' => '2024-07-25 11:00:00',
        ]);

        $response = $this->postJson('/api/bookings', [
            'space_id' => $space->id,
            'start_time' => '2024-07-25 10:00:00',
            'end_time' => '2024-07-25 12:00:00',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJson([
            'message' => 'The selected time slot is already booked.',
        ]);
    }

    /** @test */
    public function test_returns_error_if_space_not_found()
    {
        $response = $this->postJson('/api/bookings', [
            'space_id' => 123456,
            'start_time' => '2024-07-25 10:00:00',
            'end_time' => '2024-07-25 12:00:00',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJson([
            'error' => 'Space not found',
        ]);
    }

    /** @test */
    public function test_returns_error_for_invalid_data()
    {
        $response = $this->postJson('/api/bookings', [
            'space_id' => '',
            'start_time' => 'abc',
            'end_time' => 'test',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJson([
            'errors' => [
                'space_id' => ['The space id field is required.'],
                'start_time' => ['The start time field must match the format Y-m-d H:i:s.', 'The start time field must be a date before end time.'],
                'end_time' => ['The end time field must match the format Y-m-d H:i:s.', 'The end time field must be a date after start time.'],
            ],
        ]);
    }
}
