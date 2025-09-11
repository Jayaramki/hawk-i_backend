<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BambooHRJobTitle extends Model
{
    use HasFactory;

    protected $table = 'bamboohr_job_titles';

    protected $fillable = [
        'bamboohr_id',
        'title',
        'description',
        'department_id',
        'last_sync_at',
        'sync_status',
        'error_message'
    ];

    protected $casts = [
        'last_sync_at' => 'datetime',
    ];

    /**
     * Get the employees with this job title
     */
    public function employees(): HasMany
    {
        return $this->hasMany(BambooHREmployee::class, 'job_title_id');
    }

    /**
     * Get the department for this job title
     */
    public function department()
    {
        return $this->belongsTo(BambooHRDepartment::class, 'department_id');
    }
}
