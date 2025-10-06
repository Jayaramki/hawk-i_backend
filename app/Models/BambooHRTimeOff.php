<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BambooHRTimeOff extends Model
{
    use HasFactory;

    protected $table = 'bamboohr_time_off';

    protected $fillable = [
        'bamboohr_id',
        'employee_id',
        'time_off_type_id',
        'start_date',
        'end_date',
        'days_requested',
        'status',
        'requested_date',
        'approved_date',
        'approved_by',
        'notes',
        'last_sync_at',
        'sync_status',
        'error_message'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'requested_date' => 'datetime',
        'approved_date' => 'datetime',
        'last_sync_at' => 'datetime',
    ];

    /**
     * Get the employee who requested the time off
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(BambooHREmployee::class, 'employee_id', 'id');
    }

    /**
     * Get the approver of the time off request
     */
    public function approver()
    {
        return $this->belongsTo(BambooHREmployee::class, 'approved_by', 'id');
    }

    /**
     * Get the time-off type for this request
     */
    public function timeOffType()
    {
        return $this->belongsTo(TimeOffType::class, 'time_off_type_id');
    }

    /**
     * Check if time off request is approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if time off request is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if time off request is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
