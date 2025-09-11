<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BambooHRDepartment extends Model
{
    use HasFactory;

    protected $table = 'bamboohr_departments';

    protected $fillable = [
        'bamboohr_id',
        'name',
        'description',
        'parent_department_id',
        'last_sync_at',
        'sync_status',
        'error_message'
    ];

    protected $casts = [
        'last_sync_at' => 'datetime',
    ];

    /**
     * Get the employees in this department
     */
    public function employees(): HasMany
    {
        return $this->hasMany(BambooHREmployee::class, 'department_id');
    }

    /**
     * Get the parent department
     */
    public function parentDepartment()
    {
        return $this->belongsTo(BambooHRDepartment::class, 'parent_department_id');
    }

    /**
     * Get the child departments
     */
    public function childDepartments(): HasMany
    {
        return $this->hasMany(BambooHRDepartment::class, 'parent_department_id');
    }
}
