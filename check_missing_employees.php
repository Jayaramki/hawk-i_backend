<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Checking Missing Employee IDs ===\n";

$missingIds = [93, 95, 96, 97, 98];

foreach ($missingIds as $id) {
    $count = \App\Models\EmployeeAttendance::where('ina_employee_id', $id)->count();
    echo "Employee ID $id: $count records\n";
    
    if ($count > 0) {
        // Check if this ID exists in BambooHREmployee table
        $bambooEmployee = \App\Models\BambooHREmployee::find($id);
        if ($bambooEmployee) {
            echo "  -> This is a BambooHR employee ID: {$bambooEmployee->first_name} {$bambooEmployee->last_name}\n";
            
            // Check if there's a mapping
            $mapping = \App\Models\EmployeeMapping::where('bamboohr_id', $id)->first();
            if ($mapping) {
                echo "  -> Mapped to Inatech employee ID: {$mapping->ina_emp_id}\n";
            } else {
                echo "  -> No mapping found\n";
            }
        } else {
            echo "  -> Not found in BambooHREmployee table either\n";
        }
    }
}

echo "\n=== Solution: Delete these orphaned records ===\n";
$totalOrphaned = 0;
foreach ($missingIds as $id) {
    $count = \App\Models\EmployeeAttendance::where('ina_employee_id', $id)->count();
    if ($count > 0) {
        echo "Deleting $count records with employee ID $id\n";
        \App\Models\EmployeeAttendance::where('ina_employee_id', $id)->delete();
        $totalOrphaned += $count;
    }
}

echo "Total orphaned records deleted: $totalOrphaned\n";

// Final check
echo "\n=== Final Check ===\n";
$finalCount = \App\Models\EmployeeAttendance::count();
echo "Total attendance records after cleanup: $finalCount\n";

$finalUniqueIds = \App\Models\EmployeeAttendance::distinct()->pluck('ina_employee_id');
$finalExistsInInatech = 0;
foreach ($finalUniqueIds as $employeeId) {
    if (\App\Models\InatechEmployee::where('id', $employeeId)->exists()) {
        $finalExistsInInatech++;
    }
}

echo "Employee IDs that now exist in InatechEmployee: $finalExistsInInatech\n";
echo "Employee IDs that still don't exist in InatechEmployee: " . ($finalUniqueIds->count() - $finalExistsInInatech) . "\n";
