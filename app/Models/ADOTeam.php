<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ADOTeam extends Model
{
    use HasFactory;

    protected $table = 'ado_teams';
    
    // Tell Laravel that the ID is not auto-incrementing (we use IDs from Azure DevOps)
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'description',
        'url',
        'identity_url',
        'project_id',
        'project_name',
        'identity_id',

    ];

    protected $casts = [
        //
    ];

    /**
     * Get the project this team belongs to
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(ADOProject::class, 'project_id', 'id');
    }

    /**
     * Get team iterations
     */
    public function teamIterations(): HasMany
    {
        return $this->hasMany(ADOTeamIteration::class, 'team_id', 'id');
    }

    /**
     * Get work items assigned to this team
     */
    public function workItems(): HasMany
    {
        return $this->hasMany(ADOWorkItem::class, 'team_id', 'id');
    }

    /**
     * Scope to filter by project
     */
    public function scopeByProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }
}
