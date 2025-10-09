<?php

namespace App\Http\Controllers;

use App\Models\EmployeeAttendance;
use App\Models\BambooHREmployee;
use App\Models\InatechEmployee;
use App\Models\EmployeeMapping;
use App\Models\BambooHRTimeOff;
use App\Services\AttendanceLogicService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * Import attendance data from spreadsheet
     */
    public function importAttendance(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // 10MB max
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('file');
            $fileExtension = $file->getClientOriginalExtension();
            
            // Read file based on extension
            $data = $this->readSpreadsheetFile($file, $fileExtension);
            
            if (empty($data)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No data found in the file'
                ], 400);
            }

            // Validate required columns
            $requiredColumns = ['Attendance Date', 'Employee Code', 'Employee Name', 'In Time', 'Out Time'];
            $headers = array_keys($data[0]);
            $missingColumns = array_diff($requiredColumns, $headers);
            
            if (!empty($missingColumns)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing required columns: ' . implode(', ', $missingColumns),
                    'required_columns' => $requiredColumns
                ], 400);
            }

            $processedCount = 0;
            $errorCount = 0;
            $errors = [];
            $newEmployees = []; // Track newly created employees

            DB::beginTransaction();
            
            try {
                foreach ($data as $index => $row) {
                    try {
                        $employeeCode = trim($row['Employee Code'] ?? '');
                        $dateValue = $row['Attendance Date'] ?? '';
                        $inTimeValue = $row['In Time'] ?? '';
                        $outTimeValue = $row['Out Time'] ?? '';
                        // $employeeName = trim($row['Employee Name'] ?? '');
                        // $department = trim($row['Department'] ?? '');

                        // Validate employee exists - first try as INA employee code
                        $employee = null;
                        $employeeId = null;
                        $employeeName = trim($row['Employee Name'] ?? '');
                        
                        // First, try to find as INA employee code
                        $inaEmployee = InatechEmployee::where('ina_emp_id', $employeeCode)->first();
                        if ($inaEmployee) {
                            // Always use Inatech employee ID for attendance records
                            $employeeId = $inaEmployee->id;
                        } else {
                            // Employee not found - create new employee
                            $newEmployee = InatechEmployee::create([
                                'ina_emp_id' => $employeeCode,
                                'employee_name' => $employeeName ?: "Employee {$employeeCode}",
                                'status' => 'active'
                            ]);
                            
                            $employeeId = $newEmployee->id;
                            
                            // Track newly created employee
                            $newEmployees[] = [
                                'id' => $newEmployee->id,
                                'ina_emp_id' => $employeeCode,
                                'employee_name' => $newEmployee->employee_name
                            ];
                        }

                        // Parse and validate date
                        $attendanceDate = $this->parseDate($dateValue);
                        if (!$attendanceDate) {
                            $errors[] = "Row " . ($index + 1) . ": Invalid date format '{$dateValue}'";
                            $errorCount++;
                            continue;
                        }

                        // Parse times
                        $inTime = $this->parseTime($inTimeValue);
                        $outTime = $this->parseTime($outTimeValue);

                        // Skip if both In Time and Out Time are empty
                        if (empty($inTime) && empty($outTime)) {
                            // Skip this record - no attendance data to store
                            continue;
                        }

                        // Upsert attendance record
                        EmployeeAttendance::updateOrCreate(
                            [
                                'attendance_date' => $attendanceDate,
                                'ina_employee_id' => $employeeId,
                            ],
                            [
                                'in_time' => $inTime,
                                'out_time' => $outTime,
                            ]
                        );

                        $processedCount++;

                    } catch (\Exception $e) {
                        $errors[] = "Row " . ($index + 1) . ": " . $e->getMessage();
                        $errorCount++;
                    }
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Attendance import completed',
                    'data' => [
                        'processed_count' => $processedCount,
                        'error_count' => $errorCount,
                        'total_rows' => count($data),
                        'errors' => $errors,
                        'new_employees' => $newEmployees,
                        'new_employees_count' => count($newEmployees)
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attendance records with filtering and time-off integration
     */
    public function getAttendance(Request $request): JsonResponse
    {
        try {
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            $employeeId = $request->get('employee_id');
            $viewType = $request->get('view_type', 'day'); // day, week, month
            $perPage = $request->get('per_page', 15);

            // Get all Inatech employees
            $employeesQuery = InatechEmployee::query();
            
            if ($employeeId) {
                $employeesQuery->where('id', $employeeId);
            }

            $employees = $employeesQuery->orderBy('employee_name')->get();
            $attendanceData = [];

            $attendanceLogicService = new AttendanceLogicService();

            foreach ($employees as $employee) {
                $processedRecords = $attendanceLogicService->getAttendanceWithTimeOff(
                    $employee->id,
                    $startDate,
                    $endDate,
                    $viewType
                );

                $attendanceData = array_merge($attendanceData, $processedRecords);
            }

            // For day, week, and month views, return all data for client-side processing
            if ($viewType === 'day' || $viewType === 'week' || $viewType === 'month') {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'data' => $attendanceData,
                        'total' => count($attendanceData)
                    ]
                ]);
            } else {
                // Apply pagination for other views if needed
                $totalRecords = count($attendanceData);
                $offset = ($request->get('page', 1) - 1) * $perPage;
                $paginatedData = array_slice($attendanceData, $offset, $perPage);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'data' => $paginatedData,
                        'total' => $totalRecords,
                        'per_page' => $perPage,
                        'current_page' => $request->get('page', 1),
                        'last_page' => ceil($totalRecords / $perPage)
                    ]
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attendance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process attendance records with time-off integration
     */
    private function processAttendanceWithTimeOff($employee, $attendanceRecords, $timeOffRecords, $startDate, $endDate, $viewType)
    {
        
        $processedRecords = [];
        $attendanceByDate = $attendanceRecords->keyBy(function($record) {
            return Carbon::parse($record->attendance_date)->format('Y-m-d');
        });
        $timeOffByDate = $timeOffRecords->groupBy(function($item) {
            $start = Carbon::parse($item->start_date);
            $end = Carbon::parse($item->end_date);
            $dates = [];
            while ($start->lte($end)) {
                $dates[] = $start->format('Y-m-d');
                $start->addDay();
            }
            return $dates;
        })->flatten()->keyBy('date');

        // Generate date range based on view type
        $dateRange = $this->generateDateRange($startDate, $endDate, $viewType);

        foreach ($dateRange as $date) {
            $attendanceRecord = $attendanceByDate->get($date);
            $timeOffRecord = $timeOffByDate->get($date);


            if ($attendanceRecord) {
                // Has attendance record - check if both in_time and out_time are available
                $hasInTime = !empty($attendanceRecord->in_time);
                $hasOutTime = !empty($attendanceRecord->out_time);
                
                if ($hasInTime && $hasOutTime) {
                    // Complete attendance - both check-in and check-out
                    $processedRecords[] = [
                        'id' => $attendanceRecord->id,
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->employee_name,
                        'attendance_date' => $date,
                        'in_time' => $attendanceRecord->in_time,
                        'out_time' => $attendanceRecord->out_time,
                        'working_hours' => $attendanceRecord->working_hours,
                        'status' => 'present',
                        'status_label' => 'Present',
                        'status_color' => 'success',
                        'time_off_type' => null,
                        'time_off_type_name' => null
                    ];
                } else {
                    // No check-in AND no check-out - check for time-off
                    if ($timeOffRecord) {
                        // Has time-off record
                        $processedRecords[] = [
                            'id' => $attendanceRecord->id,
                            'employee_id' => $employee->id,
                            'employee_name' => $employee->employee_name,
                            'attendance_date' => $date,
                            'in_time' => $attendanceRecord->in_time,
                            'out_time' => $attendanceRecord->out_time,
                            'working_hours' => $attendanceRecord->working_hours,
                            'status' => 'time_off',
                            'status_label' => 'Time Off',
                            'status_color' => 'warning',
                            'time_off_type' => $timeOffRecord->time_off_type_id,
                            'time_off_type_name' => $timeOffRecord->timeOffType ? $timeOffRecord->timeOffType->name : 'Time Off'
                        ];
                    } else {
                        // No time-off record - no track
                        $processedRecords[] = [
                            'id' => $attendanceRecord->id,
                            'employee_id' => $employee->id,
                            'employee_name' => $employee->employee_name,
                            'attendance_date' => $date,
                            'in_time' => $attendanceRecord->in_time,
                            'out_time' => $attendanceRecord->out_time,
                            'working_hours' => $attendanceRecord->working_hours,
                            'status' => 'no_track',
                            'status_label' => 'No Track',
                            'status_color' => 'danger',
                            'time_off_type' => null,
                            'time_off_type_name' => null
                        ];
                    }
                }
            } elseif ($timeOffRecord) {
                // Has time-off record
                $processedRecords[] = [
                    'id' => null,
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->employee_name,
                    'attendance_date' => $date,
                    'in_time' => null,
                    'out_time' => null,
                    'working_hours' => null,
                    'status' => 'time_off',
                    'status_label' => 'Time Off',
                    'status_color' => 'warning',
                    'time_off_type' => $timeOffRecord->time_off_type_id,
                    'time_off_type_name' => $timeOffRecord->timeOffType ? $timeOffRecord->timeOffType->name : 'Time Off'
                ];
            } else {
                // No tracking
                $processedRecords[] = [
                    'id' => null,
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->employee_name,
                    'attendance_date' => $date,
                    'in_time' => null,
                    'out_time' => null,
                    'working_hours' => null,
                    'status' => 'no_track',
                    'status_label' => 'No Track',
                    'status_color' => 'secondary',
                    'time_off_type' => null,
                    'time_off_type_name' => null
                ];
            }
        }

        return $processedRecords;
    }

    /**
     * Generate date range based on view type
     */
    private function generateDateRange($startDate, $endDate, $viewType)
    {
        $start = $startDate ? Carbon::parse($startDate) : Carbon::now()->startOfDay();
        $end = $endDate ? Carbon::parse($endDate) : Carbon::now()->endOfDay();

        switch ($viewType) {
            case 'week':
                $start = $start->startOfWeek();
                $end = $start->copy()->endOfWeek();
                break;
            case 'month':
                $start = $start->startOfMonth();
                $end = $start->copy()->endOfMonth();
                break;
            default:
                // Default to day view - no changes needed
                break;
        }

        $dates = [];
        while ($start->lte($end)) {
            $dates[] = $start->format('Y-m-d');
            $start->addDay();
        }

        return $dates;
    }

    /**
     * Read spreadsheet file and return data as array
     */
    private function readSpreadsheetFile($file, $extension): array
    {
        $data = [];
        
        if ($extension === 'csv') {
            $handle = fopen($file->getPathname(), 'r');
            $headers = fgetcsv($handle);
            
            while (($row = fgetcsv($handle)) !== false) {
                $data[] = array_combine($headers, $row);
            }
            
            fclose($handle);
        } else {
            // For Excel files, we'll use a simple approach
            // In production, you might want to use PhpSpreadsheet
            $data = $this->readExcelFile($file);
        }
        
        return $data;
    }

    /**
     * Read Excel file (simplified version)
     */
    private function readExcelFile($file): array
    {
        // This is a simplified version
        // In production, use PhpSpreadsheet library
        return [];
    }

    /**
     * Parse date from various formats
     */
    private function parseDate($dateValue)
    {
        if (empty($dateValue)) {
            return null;
        }

        try {
            // Handle different date formats
            $dateValue = trim($dateValue);
            
            // Try to parse with Carbon's flexible parsing
            $carbon = Carbon::parse($dateValue);
            
            // If the year is 2 digits, assume 20xx
            if ($carbon->year < 100) {
                $carbon->addYears(2000);
            }
            
            return $carbon->format('Y-m-d');
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error("Date parsing failed for: {$dateValue}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Parse time from various formats
     */
    private function parseTime($timeValue)
    {
        if (empty($timeValue)) {
            return null;
        }

        try {
            return Carbon::parse($timeValue)->format('H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }
}