<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;
    protected $table = 'bookings';
    protected $fillable = ['space_id', 'start_time', 'end_time'];
    protected $dates = ['start_time', 'end_time'];

    public function space()
    {
        return $this->belongsTo(Space::class);
    }
}
