<?php

namespace App\Services;

use App\Models\EmployeeAttendance;
use App\Models\InatechEmployee;
use App\Models\BambooHRTimeOff;
use App\Models\EmployeeMapping;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceLogicService
{
    /**
     * Get attendance data with time-off integration for a date range
     */
    public function getAttendanceWithTimeOff($employeeId, $startDate, $endDate, $viewType = 'day')
    {
        $employee = InatechEmployee::find($employeeId);
        if (!$employee) {
            return [];
        }

        // Get attendance records for the employee
        $attendanceQuery = EmployeeAttendance::where('ina_employee_id', $employee->id);
        
        if ($startDate && $endDate && $startDate === $endDate) {
            // For single day queries, use exact date match
            $attendanceQuery->where('attendance_date', $startDate);
        } else {
            if ($startDate) {
                $attendanceQuery->where('attendance_date', '>=', $startDate);
            }
            if ($endDate) {
                $attendanceQuery->where('attendance_date', '<=', $endDate);
            }
        }

        $attendanceRecords = $attendanceQuery->get();

        // Get time-off records for the employee through mapping
        $timeOffRecords = collect();
        $mapping = EmployeeMapping::where('ina_emp_id', $employee->id)->first();
        
        if ($mapping && $mapping->bamboohr_id) {
            $timeOffQuery = BambooHRTimeOff::where('employee_id', $mapping->bamboohr_id)
                ->where('status', 'approved');
            
            if ($startDate) {
                $timeOffQuery->where(function($q) use ($startDate, $endDate) {
                    $q->whereBetween('start_date', [$startDate, $endDate ?: $startDate])
                      ->orWhereBetween('end_date', [$startDate, $endDate ?: $startDate])
                      ->orWhere(function($subQ) use ($startDate, $endDate) {
                          $subQ->where('start_date', '<=', $startDate)
                               ->where('end_date', '>=', $endDate ?: $startDate);
                      });
                });
            }
            
            $timeOffRecords = $timeOffQuery->with('timeOffType')->get();
        }

        // Process attendance data with time-off integration
        return $this->processAttendanceWithTimeOff(
            $employee,
            $attendanceRecords,
            $timeOffRecords,
            $startDate,
            $endDate,
            $viewType
        );
    }

    /**
     * Process attendance records with time-off integration
     */
    private function processAttendanceWithTimeOff($employee, $attendanceRecords, $timeOffRecords, $startDate, $endDate, $viewType)
    {
        $processedRecords = [];
        $attendanceByDate = $attendanceRecords->keyBy(function($record) {
            // Handle both Y-m-d and Y-m-d H:i:s formats
            $date = Carbon::parse($record->attendance_date);
            return $date->format('Y-m-d');
        });
        
        
        $timeOffByDate = collect();
        foreach ($timeOffRecords as $item) {
            $start = Carbon::parse($item->start_date);
            $end = Carbon::parse($item->end_date);
            while ($start->lte($end)) {
                $dateKey = $start->format('Y-m-d');
                $timeOffByDate->put($dateKey, $item);
                $start->addDay();
            }
        }

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
                    // Incomplete attendance - check for time-off
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
                    'status_color' => 'danger',
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
                // For week view, use the provided start and end dates directly
                // Don't call startOfWeek() as it will change the week range
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
}
