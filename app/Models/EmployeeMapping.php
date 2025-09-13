<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeMapping extends Model
{
    use HasFactory;

    protected $table = 'employee_mappings';

    protected $fillable = [
        'ina_emp_id',
        'bamboohr_id',
        'ado_user_id',
    ];

    protected $casts = [
        'ina_emp_id' => 'integer',
    ];

    /**
     * Get the BambooHR employee associated with this mapping
     */
    public function bamboohrEmployee(): BelongsTo
    {
        return $this->belongsTo(BambooHREmployee::class, 'bamboohr_id', 'bamboohr_id');
    }

    /**
     * Get the Azure DevOps user associated with this mapping
     */
    public function adoUser(): BelongsTo
    {
        return $this->belongsTo(ADOUser::class, 'ado_user_id', 'id');
    }

    /**
     * Scope to filter by INA employee ID
     */
    public function scopeByInaEmpId($query, $inaEmpId)
    {
        return $query->where('ina_emp_id', $inaEmpId);
    }

    /**
     * Scope to filter by BambooHR ID
     */
    public function scopeByBambooHrId($query, $bambooHrId)
    {
        return $query->where('bamboohr_id', $bambooHrId);
    }

    /**
     * Scope to filter by Azure DevOps user ID
     */
    public function scopeByAdoUserId($query, $adoUserId)
    {
        return $query->where('ado_user_id', $adoUserId);
    }

    /**
     * Check if mapping is complete (has all three IDs)
     */
    public function isComplete(): bool
    {
        return !empty($this->ina_emp_id) && 
               !empty($this->bamboohr_id) && 
               !empty($this->ado_user_id);
    }

    /**
     * Get mapping status
     */
    public function getMappingStatusAttribute(): string
    {
        if ($this->isComplete()) {
            return 'complete';
        }
        
        $mappedCount = 0;
        if (!empty($this->ina_emp_id)) $mappedCount++;
        if (!empty($this->bamboohr_id)) $mappedCount++;
        if (!empty($this->ado_user_id)) $mappedCount++;
        
        return $mappedCount > 0 ? 'partial' : 'empty';
    }
}