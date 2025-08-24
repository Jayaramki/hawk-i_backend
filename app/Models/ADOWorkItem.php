<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ADOWorkItem extends Model
{
    use HasFactory;

    protected $table = 'ado_work_items';
    
    // Tell Laravel that the ID is not auto-incrementing (we use IDs from Azure DevOps)
    public $incrementing = false;
    
    // Tell Laravel that the primary key is an integer
    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'url',
        'project_id',
        'team_id',
        'iteration_id',
        'iteration_path',
        'work_item_type',
        'title',
        'state',
        'priority',
        'story_points',
        'effort',
        'remaining_work',
        'completed_work',
        'original_estimate',
        'assigned_to',
        'assigned_to_display_name',
        'modified_by',
        'modified_by_display_name',
        'created_date',
        'changed_date',
        'area_path',
        'tags',
        'ruddr_task_name',
        'ruddr_project_id',
        'task_start_dt',
        'task_end_dt',
        'delayed_completion',
        'delayed_reason',
        'moved_from_sprint',
        'spillover_reason',
        'effort_saved_using_ai',
        'parent_id',

    ];

    protected $casts = [
        'story_points' => 'float',
        'effort' => 'float',
        'remaining_work' => 'float',
        'completed_work' => 'float',
        'original_estimate' => 'float',
        'effort_saved_using_ai' => 'float',
        'priority' => 'integer',
        'created_date' => 'datetime',
        'changed_date' => 'datetime',
        'task_start_dt' => 'datetime',
        'task_end_dt' => 'datetime',
        'delayed_completion' => 'datetime',
        'tags' => 'array',
    ];

    /**
     * Get the project this work item belongs to
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(ADOProject::class, 'project_id', 'id');
    }

    /**
     * Get the team this work item belongs to
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(ADOTeam::class, 'team_id', 'id');
    }

    /**
     * Get the iteration this work item belongs to
     */
    public function iteration(): BelongsTo
    {
        return $this->belongsTo(ADOIteration::class, 'iteration_id', 'id');
    }

    /**
     * Get the assigned user
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(ADOUser::class, 'assigned_to', 'descriptor');
    }

    /**
     * Get the user who created this work item
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(ADOUser::class, 'created_by', 'descriptor');
    }

    /**
     * Get the user who last modified this work item
     */
    public function modifiedByUser(): BelongsTo
    {
        return $this->belongsTo(ADOUser::class, 'modified_by', 'descriptor');
    }

    /**
     * Scope to filter by project
     */
    public function scopeByProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope to filter by team
     */
    public function scopeByTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Scope to filter by iteration
     */
    public function scopeByIteration($query, $iterationId)
    {
        return $query->where('iteration_id', $iterationId);
    }

    /**
     * Scope to filter by work item type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('work_item_type', $type);
    }

    /**
     * Scope to filter by state
     */
    public function scopeByState($query, $state)
    {
        return $query->where('state', $state);
    }

    /**
     * Scope to filter active work items (not closed)
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('state', ['Closed', 'Removed', 'Resolved']);
    }

    /**
     * Scope to filter closed work items
     */
    public function scopeClosed($query)
    {
        return $query->whereIn('state', ['Closed', 'Resolved']);
    }

    /**
     * Scope to filter by assigned user
     */
    public function scopeAssignedTo($query, $userDescriptor)
    {
        return $query->where('assigned_to', $userDescriptor);
    }

    /**
     * Scope to filter by created user
     */
    public function scopeCreatedBy($query, $userDescriptor)
    {
        return $query->where('created_by', $userDescriptor);
    }
}
