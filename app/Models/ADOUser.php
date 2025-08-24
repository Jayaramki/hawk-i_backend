<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ADOUser extends Model
{
    use HasFactory;

    protected $table = 'ado_users';

    protected $fillable = [
        'descriptor',
        'display_name',
        'mail_address',
        'origin',
        'origin_id',
        'subject_kind',
        'url',
        'meta_type',
        'directory_alias',
        'domain',
        'principal_name',
        'is_active',

    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get work items assigned to this user
     */
    public function assignedWorkItems(): HasMany
    {
        return $this->hasMany(ADOWorkItem::class, 'assigned_to', 'descriptor');
    }

    /**
     * Get work items created by this user
     */
    public function createdWorkItems(): HasMany
    {
        return $this->hasMany(ADOWorkItem::class, 'created_by', 'descriptor');
    }

    /**
     * Get work items modified by this user
     */
    public function modifiedWorkItems(): HasMany
    {
        return $this->hasMany(ADOWorkItem::class, 'modified_by', 'descriptor');
    }

    /**
     * Scope to filter active users
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by subject kind
     */
    public function scopeBySubjectKind($query, $subjectKind)
    {
        return $query->where('subject_kind', $subjectKind);
    }

    /**
     * Scope to filter by origin
     */
    public function scopeByOrigin($query, $origin)
    {
        return $query->where('origin', $origin);
    }
}
