<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Space extends Model
{
    use HasFactory;
    protected $table = 'spaces';
    protected $fillable = ['room_id', 'name'];
    
    public function room()
    {
        return $this->belongsTo(Room::class);
    }

}
