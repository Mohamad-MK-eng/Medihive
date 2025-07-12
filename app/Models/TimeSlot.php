<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class TimeSlot extends Model
{
    protected $fillable = [
        'doctor_id',
        'date',
        'start_time',
        'end_time',
        'is_booked'
    ];

protected $casts = [
    'date' => 'date:Y-m-d',
    'start_time' => 'string', // Store as string since it's just time
    'end_time' => 'string',   // Store as string since it's just time
    'is_booked' => 'boolean'
];



    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }




    // TimeSlot.php
public function getFormattedStartTimeAttribute(): string
{
    return Carbon::parse($this->start_time)->format('g:i A');
}


public function getStartTimeDigitalAttribute(): string
{
    return Carbon::parse($this->start_time)->format('H:i');
}

public function getEndTimeDigitalAttribute(): string
{
    return Carbon::parse($this->end_time)->format('H:i');
}





    public function getFormattedEndTimeAttribute(): string
    {
        return Carbon::parse($this->end_time)->format('g:i A');
    }
}
