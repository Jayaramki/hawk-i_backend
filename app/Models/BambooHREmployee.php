<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BambooHREmployee extends Model
{
    use HasFactory;

    protected $table = 'bamboohr_employees';

    protected $fillable = [
        'bamboohr_id',
        'first_name',
        'last_name',
        'gender',
        'email',
        'job_title',
        'department_id',
        'hire_date',
        'termination_date',
        'status',
        'work_email',
        'photo_url',
        'mobile_phone',
        'work_phone',
        'address1',
        'address2',
        'city',
        'state',
        'zip_code',
        'country',
        'supervisor_id',
        'last_sync_at',
        'sync_status',
        'error_message'
    ];

    protected $casts = [
        'hire_date' => 'date',
        'termination_date' => 'date',
        'last_sync_at' => 'datetime',
    ];

    /**
     * Get the department that the employee belongs to
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(BambooHRDepartment::class, 'department_id');
    }

    /**
     * Get the supervisor of the employee
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(BambooHREmployee::class, 'supervisor_id');
    }

    /**
     * Get the time off requests for this employee
     */
    public function timeOffRequests()
    {
        return $this->hasMany(BambooHRTimeOff::class, 'employee_id');
    }

    /**
     * Get the attendance records for this employee
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(EmployeeAttendance::class, 'ina_employee_id');
    }

    /**
     * Get the full name of the employee
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Check if employee is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && !$this->termination_date;
    }
}
