<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAttendance extends Model
{
    use HasFactory;

    protected $table = 'employee_attendance';

    protected $fillable = [
        'attendance_date',
        'ina_employee_id',
        'in_time',
        'out_time',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'in_time' => 'datetime:H:i:s',
        'out_time' => 'datetime:H:i:s',
    ];

    /**
     * Get the employee that this attendance record belongs to
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(BambooHREmployee::class, 'ina_employee_id');
    }

    /**
     * Get the employee name through the relationship
     */
    public function getEmployeeNameAttribute(): string
    {
        return $this->employee ? $this->employee->full_name : '';
    }

    /**
     * Get the department through the employee relationship
     */
    public function getDepartmentAttribute(): string
    {
        return $this->employee && $this->employee->department 
            ? $this->employee->department->name 
            : '';
    }

    /**
     * Get the department ID through the employee relationship
     */
    public function getDepartmentIdAttribute(): ?int
    {
        return $this->employee ? $this->employee->department_id : null;
    }

    /**
     * Calculate total working hours for the day
     */
    public function getWorkingHoursAttribute(): ?float
    {
        if (!$this->in_time || !$this->out_time) {
            return null;
        }

        $inTime = \Carbon\Carbon::parse($this->in_time);
        $outTime = \Carbon\Carbon::parse($this->out_time);
        
        return $outTime->diffInHours($inTime, true);
    }

    /**
     * Check if the attendance is complete (both in and out time recorded)
     */
    public function isComplete(): bool
    {
        return !is_null($this->in_time) && !is_null($this->out_time);
    }

    /**
     * Check if the employee is currently checked in (has in_time but no out_time)
     */
    public function isCheckedIn(): bool
    {
        return !is_null($this->in_time) && is_null($this->out_time);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('attendance_date', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by employee
     */
    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('ina_employee_id', $employeeId);
    }

    /**
     * Scope to filter by department
     */
    public function scopeForDepartment($query, $departmentId)
    {
        return $query->whereHas('employee', function ($q) use ($departmentId) {
            $q->where('department_id', $departmentId);
        });
    }
}