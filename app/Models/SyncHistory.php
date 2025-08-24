<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncHistory extends Model
{
    use HasFactory;

    protected $table = 'sync_history';

    protected $fillable = [
        'table_name',
        'project_id',
        'last_sync_at',
        'sync_type',
        'status',
        'records_processed',
        'error_message',
    ];

    protected $casts = [
        'last_sync_at' => 'datetime',
        'records_processed' => 'integer',
    ];

    // Constants for table names
    const TABLE_PROJECTS = 'projects';
    const TABLE_USERS = 'users';
    const TABLE_TEAMS = 'teams';
    const TABLE_ITERATIONS = 'iterations';
    const TABLE_TEAM_ITERATIONS = 'team_iterations';
    const TABLE_WORK_ITEMS = 'work_items';

    // Constants for sync types
    const SYNC_TYPE_FULL = 'full';
    const SYNC_TYPE_INCREMENTAL = 'incremental';

    // Constants for status
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    const STATUS_IN_PROGRESS = 'in_progress';

    /**
     * Get the last sync time for a specific table and project
     */
    public static function getLastSyncTime(string $tableName, ?string $projectId = null): ?\DateTime
    {
        $record = self::where('table_name', $tableName)
            ->where('project_id', $projectId)
            ->where('status', self::STATUS_SUCCESS)
            ->first();

        return $record ? $record->last_sync_at->toDateTime() : null;
    }

    /**
     * Update or create sync history record
     */
    public static function updateSyncHistory(
        string $tableName,
        ?string $projectId = null,
        string $syncType = self::SYNC_TYPE_FULL,
        string $status = self::STATUS_SUCCESS,
        int $recordsProcessed = 0,
        ?string $errorMessage = null
    ): self {
        return self::updateOrCreate(
            [
                'table_name' => $tableName,
                'project_id' => $projectId,
            ],
            [
                'last_sync_at' => now(),
                'sync_type' => $syncType,
                'status' => $status,
                'records_processed' => $recordsProcessed,
                'error_message' => $errorMessage,
            ]
        );
    }

    /**
     * Mark sync as in progress
     */
    public static function markSyncInProgress(string $tableName, ?string $projectId = null): self
    {
        return self::updateOrCreate(
            [
                'table_name' => $tableName,
                'project_id' => $projectId,
            ],
            [
                'status' => self::STATUS_IN_PROGRESS,
                'last_sync_at' => now(),
            ]
        );
    }

    /**
     * Scope to filter by table name
     */
    public function scopeForTable($query, string $tableName)
    {
        return $query->where('table_name', $tableName);
    }

    /**
     * Scope to filter by project
     */
    public function scopeForProject($query, ?string $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope to filter successful syncs only
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }
}
