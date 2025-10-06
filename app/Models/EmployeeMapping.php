<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeMapping extends Model
{
    use HasFactory;

    protected $table = 'employee_mapping';

    protected $fillable = [
        'ina_emp_id',
        'bamboohr_id',
        'ado_user_id',
    ];

    protected $casts = [
        'ina_emp_id' => 'integer',
    ];

    /**
     * Get the Inatech employee associated with this mapping
     */
    public function inaEmployee(): BelongsTo
    {
        return $this->belongsTo(InatechEmployee::class, 'ina_emp_id', 'id');
    }

    /**
     * Get the BambooHR employee associated with this mapping
     */
    public function bambooHREmployee(): BelongsTo
    {
        return $this->belongsTo(BambooHREmployee::class, 'bamboohr_id', 'id');
    }

    /**
     * Get the ADO user associated with this mapping
     */
    public function adoUser(): BelongsTo
    {
        return $this->belongsTo(ADOUser::class, 'ado_user_id', 'id');
    }

    /**
     * Scope to find by INA employee ID
     */
    public function scopeByInaEmpId($query, $inaEmpId)
    {
        return $query->where('ina_emp_id', $inaEmpId);
    }

    /**
     * Scope to find by BambooHR ID
     */
    public function scopeByBambooHRId($query, $bamboohrId)
    {
        return $query->where('bamboohr_id', $bamboohrId);
    }

    /**
     * Scope to find by ADO user ID
     */
    public function scopeByAdoUserId($query, $adoUserId)
    {
        return $query->where('ado_user_id', $adoUserId);
    }

    /**
     * Check if this mapping has a BambooHR ID
     */
    public function hasBambooHRId(): bool
    {
        return !empty($this->bamboohr_id);
    }

    /**
     * Check if this mapping has an ADO user ID
     */
    public function hasAdoUserId(): bool
    {
        return !empty($this->ado_user_id);
    }

    /**
     * Get or create a mapping by INA employee ID
     */
    public static function findOrCreateByInaEmpId($inaEmpId): self
    {
        return self::firstOrCreate(
            ['ina_emp_id' => $inaEmpId]
        );
    }

    /**
     * Update BambooHR ID for this mapping
     */
    public function updateBambooHRId($bamboohrId): self
    {
        $this->update(['bamboohr_id' => $bamboohrId]);
        return $this;
    }

    /**
     * Update ADO user ID for this mapping
     */
    public function updateAdoUserId($adoUserId): self
    {
        $this->update(['ado_user_id' => $adoUserId]);
        return $this;
    }
}