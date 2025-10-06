<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimeOffType extends Model
{
    use HasFactory;

    protected $table = 'time_off_types';

    protected $fillable = [
        'name',
        'bamboohr_id',
        'icon',
        'description',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the time-off requests for this type
     */
    public function timeOffRequests(): HasMany
    {
        return $this->hasMany(BambooHRTimeOff::class, 'time_off_type_id');
    }

    /**
     * Scope to get only active types
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Find or create a time-off type by name
     */
    public static function findOrCreateByName($name, $bamboohrId = null, $icon = null)
    {
        return static::firstOrCreate(
            ['name' => $name],
            [
                'bamboohr_id' => $bamboohrId,
                'icon' => $icon,
                'is_active' => true
            ]
        );
    }
}