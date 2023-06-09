<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EgotaxiRideRequest extends Model
{
    use HasFactory; 



    protected $fillable = [ 'rider_id', 'destinations', 'distance', 'status', 'service_id' ];

    // protected $casts = [
    //     'user_id'       => 'integer',
    //     'complaint_id'  => 'integer',
    // ];
        
    // public function user() {
    //     return $this->belongsTo( User::class, 'user_id', 'id');
    // }

    // public function complaint() {
    //     return $this->belongsTo( Complaint::class, 'complaint_id', 'id');
    // }
}
   