<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ADOProject extends Model
{
    use HasFactory;

    protected $table = 'ado_projects';
    
    // Tell Laravel that the ID is not auto-incrementing (we use UUIDs from Azure DevOps)
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'description',
        'url',
        'state',
        'revision',
        'visibility',
        'default_team_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get teams in this project
     */
    public function teams(): HasMany
    {
        return $this->hasMany(ADOTeam::class, 'project_id', 'id');
    }

    /**
     * Get iterations in this project
     */
    public function iterations(): HasMany
    {
        return $this->hasMany(ADOIteration::class, 'project_id', 'id');
    }

    /**
     * Get work items in this project
     */
    public function workItems(): HasMany
    {
        return $this->hasMany(ADOWorkItem::class, 'project_id', 'id');
    }

    /**
     * Scope to filter active projects (controlled by is_active flag)
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter well-formed projects (Azure DevOps state)
     */
    public function scopeWellFormed($query)
    {
        return $query->where('state', 'wellFormed');
    }

    /**
     * Scope to filter projects that are both active and well-formed
     */
    public function scopeProcessable($query)
    {
        return $query->where('is_active', true)->where('state', 'wellFormed');
    }

    /**
     * Scope to filter by visibility
     */
    public function scopeByVisibility($query, $visibility)
    {
        return $query->where('visibility', $visibility);
    }
}
