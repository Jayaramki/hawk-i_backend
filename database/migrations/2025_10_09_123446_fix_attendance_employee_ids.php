<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // This migration fixes the attendance records that have BambooHR employee IDs
        // instead of Inatech employee IDs. We need to map them correctly.
        
        echo "Starting attendance data fix...\n";
        
        // Get all attendance records
        $attendanceRecords = \App\Models\EmployeeAttendance::all();
        $fixedCount = 0;
        $skippedCount = 0;
        
        foreach ($attendanceRecords as $record) {
            // Check if this ina_employee_id exists in InatechEmployee table
            $inatechEmployee = \App\Models\InatechEmployee::find($record->ina_employee_id);
            
            if ($inatechEmployee) {
                // This record is already correct, skip it
                $skippedCount++;
                continue;
            }
            
            // This record has a BambooHR employee ID, we need to find the mapping
            $mapping = \App\Models\EmployeeMapping::where('bamboohr_id', $record->ina_employee_id)->first();
            
            if ($mapping && $mapping->ina_emp_id) {
                // Found the mapping, update the record
                $record->ina_employee_id = $mapping->ina_emp_id;
                $record->save();
                $fixedCount++;
                echo "Fixed record ID {$record->id}: BambooHR ID {$mapping->bamboohr_id} -> Inatech ID {$mapping->ina_emp_id}\n";
            } else {
                // No mapping found, this is a problem
                echo "WARNING: No mapping found for BambooHR employee ID {$record->ina_employee_id} in record ID {$record->id}\n";
            }
        }
        
        echo "Migration completed. Fixed: $fixedCount, Skipped: $skippedCount\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration cannot be easily reversed as we're fixing data integrity issues
        echo "This migration cannot be reversed as it fixes data integrity issues.\n";
        echo "If you need to restore the data, you should restore from a backup.\n";
    }
};
