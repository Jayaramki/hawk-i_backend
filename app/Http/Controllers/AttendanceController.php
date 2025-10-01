<?php

namespace App\Http\Controllers;

use App\Models\EmployeeAttendance;
use App\Models\BambooHREmployee;
use App\Models\InatechEmployee;
use App\Models\EmployeeMapping;
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
            $requiredColumns = ['Attendance Date', 'Employee Code', 'Employee Name', 'Department', 'In Time', 'Out Time'];
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
                        
                        // First, try to find as INA employee code
                        $inaEmployee = InatechEmployee::where('ina_emp_id', $employeeCode)->first();
                        if ($inaEmployee) {
                            // Check if there's a mapping to BambooHR employee
                            $mapping = EmployeeMapping::where('ina_emp_id', $inaEmployee->id)->first();
                            if ($mapping && $mapping->bamboohr_id) {
                                $employee = BambooHREmployee::find($mapping->bamboohr_id);
                                $employeeId = $employee ? $employee->id : null;
                            } else {
                                // If no mapping exists, use the INA employee directly
                                $employeeId = $inaEmployee->id;
                            }
                        } else {
                            // Fallback: try as BambooHR employee ID
                            $employee = BambooHREmployee::where('bamboohr_id', $employeeCode)->first();
                            $employeeId = $employee ? $employee->id : null;
                        }
                        
                        if (!$employeeId) {
                            $errors[] = "Row " . ($index + 1) . ": Employee with Code '{$employeeCode}' not found";
                            $errorCount++;
                            continue;
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
                        'errors' => $errors
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
     * Get attendance records with filtering
     */
    public function getAttendance(Request $request): JsonResponse
    {
        try {
            $query = EmployeeAttendance::with(['employee']);

            // Filter by employee ID
            if ($request->has('employee_id')) {
                $query->where('ina_employee_id', $request->employee_id);
            }

            // Filter by date range
            if ($request->has('start_date')) {
                $query->where('attendance_date', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->where('attendance_date', '<=', $request->end_date);
            }

            // Filter by department
            if ($request->has('department_id')) {
                $query->whereHas('employee', function ($q) use ($request) {
                    $q->where('department_id', $request->department_id);
                });
            }

            $perPage = $request->get('per_page', 15);
            \Log::info('Attendance pagination - per_page: ' . $perPage . ', requested: ' . $request->get('per_page', 'not provided'));
            $attendance = $query->orderBy('attendance_date', 'desc')
                              ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $attendance
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attendance: ' . $e->getMessage()
            ], 500);
        }
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