<?php

namespace App\Http\Controllers;

use App\Models\BambooHRTimeOff;
use App\Models\BambooHREmployee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TimeoffController extends Controller
{
    /**
     * Get timeoff requests with filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'employee_id' => 'nullable|integer',
                'status' => 'nullable|string|in:pending,approved,rejected',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date',
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = BambooHRTimeOff::with(['employee', 'approver']);

            // Apply filters
            if ($request->filled('employee_id')) {
                $query->where('employee_id', $request->employee_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('start_date')) {
                $query->where('start_date', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $query->where('end_date', '<=', $request->end_date);
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $page = $request->get('page', 1);

            $timeoffRequests = $query->orderBy('requested_date', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            // Transform data to include employee name
            $transformedData = $timeoffRequests->getCollection()->map(function ($request) {
                return [
                    'id' => $request->id,
                    'employee_id' => $request->employee_id,
                    'employee_name' => $request->employee ? $request->employee->full_name : 'Unknown Employee',
                    'type' => $request->type,
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date,
                    'days_requested' => $request->days_requested,
                    'status' => $request->status,
                    'requested_date' => $request->requested_date,
                    'approved_date' => $request->approved_date,
                    'approved_by' => $request->approver ? $request->approver->full_name : null,
                    'notes' => $request->notes
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $transformedData,
                'total' => $timeoffRequests->total(),
                'current_page' => $timeoffRequests->currentPage(),
                'per_page' => $timeoffRequests->perPage(),
                'last_page' => $timeoffRequests->lastPage()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching timeoff requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a single timeoff request
     */
    public function show($id): JsonResponse
    {
        try {
            $timeoffRequest = BambooHRTimeOff::with(['employee', 'approver'])->find($id);

            if (!$timeoffRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Timeoff request not found'
                ], 404);
            }

            $transformedData = [
                'id' => $timeoffRequest->id,
                'employee_id' => $timeoffRequest->employee_id,
                'employee_name' => $timeoffRequest->employee ? $timeoffRequest->employee->full_name : 'Unknown Employee',
                'type' => $timeoffRequest->type,
                'start_date' => $timeoffRequest->start_date,
                'end_date' => $timeoffRequest->end_date,
                'days_requested' => $timeoffRequest->days_requested,
                'status' => $timeoffRequest->status,
                'requested_date' => $timeoffRequest->requested_date,
                'approved_date' => $timeoffRequest->approved_date,
                'approved_by' => $timeoffRequest->approver ? $timeoffRequest->approver->full_name : null,
                'notes' => $timeoffRequest->notes
            ];

            return response()->json([
                'success' => true,
                'data' => $transformedData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching timeoff request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve a timeoff request
     */
    public function approve($id): JsonResponse
    {
        try {
            $timeoffRequest = BambooHRTimeOff::find($id);

            if (!$timeoffRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Timeoff request not found'
                ], 404);
            }

            if ($timeoffRequest->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending requests can be approved'
                ], 400);
            }

            $timeoffRequest->update([
                'status' => 'approved',
                'approved_date' => Carbon::now(),
                'approved_by' => 1 // TODO: Get from authenticated user
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Timeoff request approved successfully',
                'data' => $timeoffRequest
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving timeoff request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a timeoff request
     */
    public function reject($id): JsonResponse
    {
        try {
            $timeoffRequest = BambooHRTimeOff::find($id);

            if (!$timeoffRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Timeoff request not found'
                ], 404);
            }

            if ($timeoffRequest->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending requests can be rejected'
                ], 400);
            }

            $timeoffRequest->update([
                'status' => 'rejected',
                'approved_date' => Carbon::now(),
                'approved_by' => 1 // TODO: Get from authenticated user
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Timeoff request rejected successfully',
                'data' => $timeoffRequest
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting timeoff request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get timeoff statistics
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'total_requests' => BambooHRTimeOff::count(),
                'pending_requests' => BambooHRTimeOff::where('status', 'pending')->count(),
                'approved_requests' => BambooHRTimeOff::where('status', 'approved')->count(),
                'rejected_requests' => BambooHRTimeOff::where('status', 'rejected')->count(),
                'requests_this_month' => BambooHRTimeOff::whereMonth('requested_date', Carbon::now()->month)->count(),
                'requests_this_year' => BambooHRTimeOff::whereYear('requested_date', Carbon::now()->year)->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching timeoff statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
