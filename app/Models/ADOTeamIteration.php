<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ADOTeamIteration extends Model
{
    use HasFactory;

    protected $table = 'ado_team_iterations';
    
    // Tell Laravel that the ID is not auto-incrementing (we use composite ID)
    public $incrementing = false;
    
    // Tell Laravel that the primary key is a string
    protected $keyType = 'string';

    protected $fillable = [
        'iteration_identifier',
        'team_id',
        'team_name',
        'timeframe',
        'assigned',
        'iteration_name',
        'iteration_path',
        'start_date',
        'end_date',
        'project_id',
        'is_active',
    ];

    protected $casts = [
        'assigned' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get the team
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(ADOTeam::class, 'team_id', 'id');
    }

    /**
     * Get the iteration
     */
    public function iteration(): BelongsTo
    {
        return $this->belongsTo(ADOIteration::class, 'iteration_identifier', 'id');
    }

    /**
     * Get the project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(ADOProject::class, 'project_id', 'id');
    }

    /**
     * Scope to filter active team iterations only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
