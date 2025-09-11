<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class ADOIteration extends Model
{
    use HasFactory;

    protected $table = 'ado_iterations';
    
    // Tell Laravel that the ID is not auto-incrementing (we use Azure DevOps identifier as primary key)
    public $incrementing = false;
    
    // Tell Laravel that the primary key is a string
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'path',
        'url',
        'project_id',
        'project_name',
        'start_date',
        'finish_date',
        'time_frame',
        'attributes',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'finish_date' => 'date',
        'attributes' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the project this iteration belongs to
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(ADOProject::class, 'project_id', 'id');
    }

    /**
     * Get team iterations for this iteration
     */
    public function teamIterations(): HasMany
    {
        return $this->hasMany(ADOTeamIteration::class, 'iteration_identifier', 'id');
    }

    /**
     * Get work items in this iteration
     */
    public function workItems(): HasMany
    {
        return $this->hasMany(ADOWorkItem::class, 'iteration_id', 'id');
    }

    /**
     * Scope to filter by project
     */
    public function scopeByProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope to filter active iterations (current and future)
     */
    public function scopeActivePeriod($query)
    {
        return $query->where('finish_date', '>=', now()->toDateString());
    }

    /**
     * Scope to filter active iterations (enabled status)
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by time frame
     */
    public function scopeByTimeFrame($query, $timeFrame)
    {
        return $query->where('time_frame', $timeFrame);
    }
}
