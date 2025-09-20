<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InatechEmployee extends Model
{
    use HasFactory;

    protected $table = 'inatech_employees';

    protected $fillable = [
        'ina_emp_id',
        'employee_name',
        'status'
    ];

    protected $casts = [
        'status' => 'string',
    ];

    /**
     * Get the attendance records for this employee
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(EmployeeAttendance::class, 'ina_employee_id');
    }

    /**
     * Check if employee is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Scope to get only active employees
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get only inactive employees
     */
    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }
}
