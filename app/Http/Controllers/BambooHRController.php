<?php

namespace App\Http\Controllers;

use App\Services\BambooHRService;
use App\Services\SyncProgressService;
use App\Services\WebSocketService;
use App\Models\BambooHREmployee;
use App\Models\BambooHRDepartment;
use App\Models\BambooHRJobTitle;
use App\Models\BambooHRTimeOff;
use App\Models\SyncHistory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BambooHRController extends Controller
{
    private BambooHRService $bambooHRService;

    public function __construct(SyncProgressService $progressService, WebSocketService $webSocketService)
    {
        $this->bambooHRService = new BambooHRService($progressService, $webSocketService);
    }

    /**
     * Get BambooHR sync status
     */
    public function status(): JsonResponse
    {
        try {
            $result = $this->bambooHRService->getStatus();
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test connection to BambooHR
     */
    public function testConnection(): JsonResponse
    {
        try {
            $result = $this->bambooHRService->testConnection();
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Debug API response structure
     */
    public function debugApiResponse(): JsonResponse
    {
        try {
            $subdomain = config('services.bamboohr.subdomain');
            $apiKey = config('services.bamboohr.api_key');
            
            $headers = [
                'Authorization' => 'Basic ' . base64_encode("{$apiKey}:x"),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ];

            $response = \Illuminate\Support\Facades\Http::withHeaders($headers)
                ->timeout(30)
                ->get("https://{$subdomain}.bamboohr.com/api/gateway.php/{$subdomain}/v1/employees/directory");

            return response()->json([
                'success' => true,
                'status_code' => $response->status(),
                'response_type' => gettype($response->json()),
                'response_data' => $response->json(),
                'raw_response' => $response->body()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Debug failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync all BambooHR data
     */
    public function syncAll(): JsonResponse
    {
        try {
            $result = $this->bambooHRService->syncAll();
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync employees from BambooHR (full sync - both directory and detailed)
     */
    public function syncEmployees(): JsonResponse
    {
        try {
            $result = $this->bambooHRService->syncEmployees();
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee sync failed: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Sync employees directory from BambooHR (Part 1: Basic data only)
     */
    public function syncEmployeesDirectory(): JsonResponse
    {
        try {
            $result = $this->bambooHRService->syncEmployeesDirectory();
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee directory sync failed: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Sync employees detailed data from BambooHR (Part 2: Individual employee data)
     */
    public function syncEmployeesDetailed(): JsonResponse
    {
        try {
            $result = $this->bambooHRService->syncEmployeesDetailed();
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee detailed sync failed: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Sync departments from BambooHR
     */
    public function syncDepartments(): JsonResponse
    {
        try {
            $result = $this->bambooHRService->syncDepartments();
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Department sync failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync job titles from BambooHR
     */
    public function syncJobTitles(): JsonResponse
    {
        try {
            $result = $this->bambooHRService->syncJobTitles();
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Job titles sync failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync time off requests from BambooHR
     */
    public function syncTimeOff(): JsonResponse
    {
        try {
            $result = $this->bambooHRService->syncTimeOff();
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Time off sync failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear cache and reset sync status
     */
    public function clearCache(): JsonResponse
    {
        try {
            $result = $this->bambooHRService->clearCache();
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cache clear failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get employees from database
     */
    public function getEmployees(Request $request): JsonResponse
    {
        try {
            $query = BambooHREmployee::with(['department', 'supervisor']);

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('department_id')) {
                $query->where('department_id', $request->department_id);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Check if all employees should be returned (for client-side pagination)
            if ($request->has('all') && $request->get('all') === 'true') {
                $employees = $query->orderBy('last_name')->get();
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'data' => $employees,
                        'total' => $employees->count(),
                        'per_page' => $employees->count(),
                        'current_page' => 1,
                        'last_page' => 1,
                        'from' => 1,
                        'to' => $employees->count()
                    ]
                ]);
            }

            // Default pagination for backward compatibility
            $employees = $query->orderBy('last_name')->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $employees
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch employees: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get departments from database
     */
    public function getDepartments(Request $request): JsonResponse
    {
        try {
            $query = BambooHRDepartment::with(['parentDepartment', 'childDepartments']);

            if ($request->has('search')) {
                $search = $request->search;
                $query->where('name', 'like', "%{$search}%");
            }

            $departments = $query->orderBy('name')->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $departments
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch departments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get job titles from database
     */
    public function getJobTitles(Request $request): JsonResponse
    {
        try {
            $query = BambooHRJobTitle::with('department');

            if ($request->has('search')) {
                $search = $request->search;
                $query->where('title', 'like', "%{$search}%");
            }

            $jobTitles = $query->orderBy('title')->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $jobTitles
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch job titles: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get time off requests from database
     */
    public function getTimeOff(Request $request): JsonResponse
    {
        try {
            $query = BambooHRTimeOff::with(['employee', 'approver']);

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('employee_id')) {
                $query->where('employee_id', $request->employee_id);
            }

            if ($request->has('start_date')) {
                $query->where('start_date', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->where('end_date', '<=', $request->end_date);
            }

            $timeOff = $query->orderBy('start_date', 'desc')->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $timeOff
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch time off requests: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sync history for BambooHR
     */
    /**
     * Get sync progress for real-time monitoring
     */
    public function getSyncProgress(): JsonResponse
    {
        try {
            $progressService = app(SyncProgressService::class);
            $allProgress = $progressService->getAllProgress();

            return response()->json([
                'success' => true,
                'data' => $allProgress
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get sync progress: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sync progress for specific service and operation
     */
    public function getSyncProgressByService(string $service, string $operation): JsonResponse
    {
        try {
            $progressService = app(SyncProgressService::class);
            $progress = $progressService->getProgress($service, $operation);

            if (!$progress) {
                return response()->json([
                    'success' => false,
                    'message' => 'No progress found for the specified service and operation'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $progress
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get sync progress: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getSyncHistory(Request $request): JsonResponse
    {
        try {
            $query = SyncHistory::where('sync_type', 'bamboohr');

            if ($request->has('table')) {
                $query->where('table_name', $request->table);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $history = $query->orderBy('last_sync_at', 'desc')->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $history
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sync history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get employee by ID
     */
    public function getEmployee($id): JsonResponse
    {
        try {
            $employee = BambooHREmployee::with(['department', 'supervisor', 'timeOffRequests'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $employee
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found: ' . $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get department by ID
     */
    public function getDepartment($id): JsonResponse
    {
        try {
            $department = BambooHRDepartment::with(['parentDepartment', 'childDepartments', 'employees'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $department
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found: ' . $e->getMessage()
            ], 404);
        }
    }

    /**
     * Sync selected operations
     */
    public function syncSelected(Request $request): JsonResponse
    {
        try {
            $selectedOperations = $request->input('operations', []);
            
            if (empty($selectedOperations)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No operations selected'
                ], 400);
            }
            
            $result = $this->bambooHRService->syncSelected($selectedOperations);
            return response()->json($result);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Selected sync failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get WebSocket progress data
     */
    public function getWebSocketProgress(string $service, string $operation): JsonResponse
    {
        try {
            $webSocketService = app(WebSocketService::class);
            $progress = $webSocketService->getProgress($service, $operation);
            
            return response()->json([
                'success' => true,
                'data' => $progress
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get WebSocket progress: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get WebSocket log data
     */
    public function getWebSocketLogs(string $service, string $operation): JsonResponse
    {
        try {
            $webSocketService = app(WebSocketService::class);
            $logs = $webSocketService->getLogs($service, $operation);
            
            return response()->json([
                'success' => true,
                'data' => $logs
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get WebSocket logs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear WebSocket channels
     */
    public function clearWebSocketChannels(string $service, string $operation): JsonResponse
    {
        try {
            $webSocketService = app(WebSocketService::class);
            $webSocketService->clearChannel($service, $operation);
            
            return response()->json([
                'success' => true,
                'message' => 'WebSocket channels cleared successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear WebSocket channels: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get time off request by ID
     */
    public function getTimeOffRequest($id): JsonResponse
    {
        try {
            $timeOff = BambooHRTimeOff::with(['employee', 'approver'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $timeOff
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Time off request not found: ' . $e->getMessage()
            ], 404);
        }
    }
}
