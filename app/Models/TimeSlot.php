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
        'date' => 'date',
        'is_booked' => 'boolean'
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    // Helper method to format time for display
    public function getFormattedStartTimeAttribute(): string
    {
        return Carbon::parse($this->start_time)->format('g:i A');
    }

    public function getFormattedEndTimeAttribute(): string
    {
        return Carbon::parse($this->end_time)->format('g:i A');
    }
}
