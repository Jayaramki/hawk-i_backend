<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeAttendance extends Model
{
    use HasFactory;

    protected $table = 'employee_attendance';

    protected $fillable = [
        'attendance_date',
        'ina_employee_id',
        'employee_name',
        'department',
        'in_time',
        'out_time'
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'in_time' => 'datetime:H:i:s',
        'out_time' => 'datetime:H:i:s',
    ];

    /**
     * Scope to filter attendance by date
     */
    public function scopeForDate($query, $date)
    {
        return $query->where('attendance_date', $date);
    }

    /**
     * Scope to filter attendance by employee ID
     */
    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('ina_employee_id', $employeeId);
    }

    /**
     * Scope to filter attendance by department
     */
    public function scopeForDepartment($query, $department)
    {
        return $query->where('department', $department);
    }

    /**
     * Scope to filter attendance by date range
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('attendance_date', [$startDate, $endDate]);
    }

    /**
     * Get the total working hours for this attendance record
     */
    public function getWorkingHoursAttribute()
    {
        if (!$this->in_time || !$this->out_time) {
            return null;
        }

        $inTime = \Carbon\Carbon::parse($this->in_time);
        $outTime = \Carbon\Carbon::parse($this->out_time);

        return $outTime->diffInHours($inTime, true);
    }

    /**
     * Get the total working minutes for this attendance record
     */
    public function getWorkingMinutesAttribute()
    {
        if (!$this->in_time || !$this->out_time) {
            return null;
        }

        $inTime = \Carbon\Carbon::parse($this->in_time);
        $outTime = \Carbon\Carbon::parse($this->out_time);

        return $outTime->diffInMinutes($inTime);
    }

    /**
     * Check if the employee was late (assuming 9:00 AM as standard in time)
     */
    public function isLate($standardInTime = '09:00:00')
    {
        if (!$this->in_time) {
            return false;
        }

        $inTime = \Carbon\Carbon::parse($this->in_time);
        $standardTime = \Carbon\Carbon::parse($standardInTime);

        return $inTime->isAfter($standardTime);
    }

    /**
     * Check if the employee left early (assuming 6:00 PM as standard out time)
     */
    public function leftEarly($standardOutTime = '18:00:00')
    {
        if (!$this->out_time) {
            return false;
        }

        $outTime = \Carbon\Carbon::parse($this->out_time);
        $standardTime = \Carbon\Carbon::parse($standardOutTime);

        return $outTime->isBefore($standardTime);
    }

    /**
     * Get attendance status based on in and out times
     */
    public function getStatusAttribute()
    {
        if (!$this->in_time && !$this->out_time) {
            return 'absent';
        }

        if ($this->in_time && !$this->out_time) {
            return 'in_progress';
        }

        if ($this->in_time && $this->out_time) {
            return 'completed';
        }

        return 'unknown';
    }
}