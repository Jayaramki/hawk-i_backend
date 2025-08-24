<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ADOTeamIteration extends Model
{
    use HasFactory;

    protected $table = 'ado_team_iterations';

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

    ];

    protected $casts = [
        'assigned' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
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
        return $this->belongsTo(ADOIteration::class, 'iteration_identifier', 'identifier');
    }

    /**
     * Get the project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(ADOProject::class, 'project_id', 'id');
    }
}
