<?php

namespace App\Services;

use App\Models\BambooHREmployee;
use App\Models\BambooHRDepartment;
use App\Models\BambooHRJobTitle;
use App\Models\BambooHRTimeOff;
use App\Models\SyncHistory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Services\SyncProgressService;
use App\Services\WebSocketService;
use Exception;
use DateTime;

class BambooHRService
{
    private string $baseUrl;
    private string $apiKey;
    private array $headers;
    private SyncProgressService $progressService;
    private WebSocketService $webSocketService;

    public function __construct(SyncProgressService $progressService, WebSocketService $webSocketService)
    {
        $this->apiKey = config('services.bamboohr.api_key') ?? '';
        $this->baseUrl = config('services.bamboohr.base_url') ?? 'https://api.bamboohr.com/api/gateway.php';
        $this->progressService = $progressService;
        $this->webSocketService = $webSocketService;
        
        // Only set headers if API key is available
        if (!empty($this->apiKey)) {
            $this->headers = [
                'Authorization' => 'Basic ' . base64_encode("{$this->apiKey}:x"),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ];
        } else {
            $this->headers = [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ];
        }
    }

    /**
     * Test connection to BambooHR API
     */
    public function testConnection(): array
    {
        try {
            // Check if API key is configured
            if (empty($this->apiKey)) {
                return [
                    'success' => false,
                    'message' => 'BambooHR API key not configured. Please set BAMBOOHR_API_KEY in your .env file.',
                    'data' => [
                        'status' => 'not_configured',
                        'error' => 'Missing API key configuration',
                        'timestamp' => now()->toISOString()
                    ]
                ];
            }

            $subdomain = config('services.bamboohr.subdomain');
            $response = Http::withHeaders($this->headers)
                ->timeout(30)
                ->get("https://{$subdomain}.bamboohr.com/api/gateway.php/{$subdomain}/v1/employees/directory");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Connection successful',
                    'data' => [
                        'status' => 'connected',
                        'timestamp' => now()->toISOString()
                    ]
                ];
            }

            return [
                'success' => false,
                'message' => 'Connection failed',
                'data' => [
                    'status' => 'disconnected',
                    'error' => $response->body(),
                    'timestamp' => now()->toISOString()
                ]
            ];

        } catch (Exception $e) {
            Log::error('BambooHR connection test failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Connection test failed',
                'data' => [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'timestamp' => now()->toISOString()
                ]
            ];
        }
    }

    /**
     * Sync all BambooHR data
     */
    public function syncAll(): array
    {
        try {
            // Check if API key is configured
            if (empty($this->apiKey)) {
                return [
                    'success' => false,
                    'message' => 'BambooHR API key not configured. Please set BAMBOOHR_API_KEY in your .env file.',
                    'error' => 'Missing API key configuration'
                ];
            }

            DB::beginTransaction();

            $results = [
                'departments' => $this->syncDepartments(),
                'job_titles' => $this->syncJobTitles(),
                'employees' => $this->syncEmployees(),
                'time_off' => $this->syncTimeOff(),
            ];

            // Record sync history
            $this->recordSyncHistory('all', null, 'success', 'All data synced successfully');

            DB::commit();

            return [
                'success' => true,
                'message' => 'All data synced successfully',
                'data' => $results
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('BambooHR sync all failed', [
                'error' => $e->getMessage()
            ]);

            $this->recordSyncHistory('all', null, 'error', $e->getMessage());

            return [
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Sync employees from BambooHR - Part 1: Directory sync
     */
    public function syncEmployeesDirectory(): array
    {
        try {
            // Increase execution time limit to 5 minutes for sync operation
            set_time_limit(300);
            
            // Check if API key is configured
            if (empty($this->apiKey)) {
                return [
                    'success' => false,
                    'message' => 'BambooHR API key not configured. Please set BAMBOOHR_API_KEY in your .env file.',
                    'error' => 'Missing API key configuration'
                ];
            }

            // Temporarily disable foreign key checks to allow supervisor_id references that don't exist yet
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            $subdomain = config('services.bamboohr.subdomain');
            $response = Http::withHeaders($this->headers)
                ->timeout(60)
                ->get("https://{$subdomain}.bamboohr.com/api/v1/employees/directory", [
                    'fields' => 'id,displayName,firstName,lastName,preferredName,jobTitle,workEmail,department,location,division,pronouns,photoUploaded,photoUrl,canUploadPhoto'
                ]);

            if (!$response->successful()) {
                throw new Exception("Failed to fetch employees: " . $response->body());
            }

            $responseData = $response->json();
            // Store the response in a .json file
            $jsonFilePath = storage_path('bamboohr_employees_response.json');
            $headersFilePath = storage_path('bamboohr_bearer_token.txt');
            file_put_contents($headersFilePath, json_encode($this->headers, JSON_PRETTY_PRINT));
            file_put_contents($jsonFilePath, json_encode($responseData, JSON_PRETTY_PRINT));
            
            // Check if response is valid
            if (!is_array($responseData)) {
                https://documentation.bamboohr.com/docs/list-of-field-names
                throw new Exception("Invalid response format from BambooHR API. Expected array, got: " . gettype($responseData));
            }

            // Extract employees from the response structure
            $employees = $responseData['employees'] ?? [];
            
            if (!is_array($employees)) {
                throw new Exception("No employees data found in BambooHR API response");
            }

            $syncedCount = 0;
            $errors = [];
            $batchSize = 50; // Process 50 employees at a time for batch upsert
            $batches = array_chunk($employees, $batchSize);
            $totalEmployees = count($employees);
            $totalBatches = count($batches);

            // Initialize progress tracking
            $this->progressService->initializeProgress('bamboohr', 'directory', [
                'total_employees' => $totalEmployees,
                'total_batches' => $totalBatches,
                'processed_employees' => 0,
                'processed_batches' => 0,
                'current_batch' => 0
            ]);
            
            // Broadcast initial progress
            $this->webSocketService->broadcastProgress('bamboohr', 'directory', [
                'status' => 'started',
                'total_employees' => $totalEmployees,
                'total_batches' => $totalBatches,
                'processed_employees' => 0,
                'processed_batches' => 0,
                'current_batch' => 0
            ]);
            
            $this->webSocketService->broadcastLog('bamboohr', 'directory', 'info', 'Starting employee directory sync', [
                'total_employees' => $totalEmployees,
                'batch_size' => $batchSize
            ]);

            Log::info('Starting employee directory sync with batch upsert', [
                'total_employees' => $totalEmployees,
                'batch_size' => $batchSize,
                'total_batches' => $totalBatches
            ]);

            foreach ($batches as $batchIndex => $batch) {
                Log::info("Processing directory batch " . ($batchIndex + 1) . " of " . count($batches), [
                    'batch_size' => count($batch)
                ]);

                $batchData = [];
                $currentTime = now();

                foreach ($batch as $employeeData) {
                try {
                    // Extract employee ID from API response
                    $employeeId = $employeeData['id'] ?? $employeeData['bamboohr_id'] ?? null;
                    
                    if (!$employeeId) {
                        throw new Exception("No valid employee ID found in data: " . json_encode($employeeData));
                    }

                        // Filter by division - only process employees from 'Inatech - INAT'
                    if (!isset($employeeData['division']) || $employeeData['division'] !== 'Inatech - INAT') {
                        continue;
                    }

                    // Get department ID from department name
                    $departmentId = null;
                    $departmentName = $employeeData['department'] ?? $employeeData['departmentId'] ?? null;
                    if ($departmentName) {
                        $department = BambooHRDepartment::where('name', $departmentName)->first();
                        $departmentId = $department ? $department->id : null;
                    }

                        // Prepare data for batch upsert
                        $batchData[] = [
                            'bamboohr_id' => $employeeId,
                            'first_name' => $employeeData['firstName'] ?? $employeeData['first_name'] ?? $employeeData['displayName'] ?? '',
                            'last_name' => $employeeData['lastName'] ?? $employeeData['last_name'] ?? '',
                            'job_title' => $employeeData['jobTitle'] ?? $employeeData['job_title'] ?? $employeeData['title'] ?? '',
                            'department_id' => $departmentId,
                            'photo_url' => $employeeData['photoUrl'] ?? $employeeData['photo_url'] ?? null,
                            'email' => $employeeData['workEmail'] ?? $employeeData['email'] ?? $employeeData['work_email'] ?? '',
                            'status' => $employeeData['status'] ?? 'active',
                            'last_sync_at' => $currentTime,
                            'sync_status' => 'directory_synced',
                            'error_message' => null,
                            'created_at' => $currentTime,
                            'updated_at' => $currentTime
                        ];

                    } catch (Exception $e) {
                        $errors[] = "Employee {$employeeData['id']}: " . $e->getMessage();
                        Log::error('Failed to prepare employee data for batch', [
                            'employee_id' => $employeeData['id'],
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                // Perform batch upsert
                if (!empty($batchData)) {
                    try {
                        // Use upsert for batch insert/update
                        BambooHREmployee::upsert(
                            $batchData,
                            ['bamboohr_id'], // Unique identifier
                            [
                                'first_name', 'last_name', 'job_title', 'department_id', 
                                'photo_url', 'email', 'status', 'last_sync_at', 
                                'sync_status', 'error_message', 'updated_at'
                            ] // Fields to update on duplicate
                        );
                        
                        $syncedCount += count($batchData);
                        
                        Log::info("Batch upsert completed", [
                            'batch_index' => $batchIndex + 1,
                            'records_upserted' => count($batchData),
                            'total_synced' => $syncedCount
                        ]);

                        // Update progress
                        $this->progressService->updateProgress('bamboohr', 'directory', [
                            'processed_employees' => $syncedCount,
                            'processed_batches' => $batchIndex + 1,
                            'current_batch' => $batchIndex + 1,
                            'errors' => $errors
                        ]);
                        
                        // Broadcast progress update
                        $this->webSocketService->broadcastProgress('bamboohr', 'directory', [
                            'status' => 'running',
                            'total_employees' => $totalEmployees,
                            'total_batches' => $totalBatches,
                            'processed_employees' => $syncedCount,
                            'processed_batches' => $batchIndex + 1,
                            'current_batch' => $batchIndex + 1,
                            'errors' => $errors
                        ]);
                        
                        $this->webSocketService->broadcastLog('bamboohr', 'directory', 'info', "Completed batch " . ($batchIndex + 1) . " of " . $totalBatches, [
                            'batch_size' => count($batchData),
                            'total_synced' => $syncedCount
                        ]);

                    } catch (Exception $e) {
                        $errors[] = "Batch upsert failed: " . $e->getMessage();
                        Log::error('Batch upsert failed', [
                            'batch_index' => $batchIndex + 1,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            $this->recordSyncHistory('employees_directory', null, 'success', "Synced {$syncedCount} employees from directory");

            // Complete progress tracking
            $this->progressService->completeProgress('bamboohr', 'directory', [
                'processed_employees' => $syncedCount,
                'processed_batches' => $totalBatches,
                'errors' => $errors
            ]);
            
            // Broadcast completion
            $this->webSocketService->broadcastProgress('bamboohr', 'directory', [
                'status' => 'completed',
                'total_employees' => $totalEmployees,
                'total_batches' => $totalBatches,
                'processed_employees' => $syncedCount,
                'processed_batches' => $totalBatches,
                'errors' => $errors
            ]);
            
            $this->webSocketService->broadcastLog('bamboohr', 'directory', 'success', 'Employee directory sync completed successfully', [
                'synced_count' => $syncedCount,
                'total_count' => $totalEmployees,
                'errors_count' => count($errors)
            ]);

            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            return [
                'synced_count' => $syncedCount,
                'total_count' => count($employees),
                'errors' => $errors,
                'message' => 'Directory sync completed. Run detailed sync to get individual employee data.'
            ];

        } catch (Exception $e) {
            // Re-enable foreign key checks in case of exception
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            
            // Mark progress as failed
            $this->progressService->failProgress('bamboohr', 'directory', $e->getMessage());
            
            Log::error('BambooHR employees directory sync failed', [
                'error' => $e->getMessage()
            ]);

            $this->recordSyncHistory('employees_directory', null, 'error', $e->getMessage());

            throw $e;
        }
    }

    /**
     * Sync employees detailed data from BambooHR - Part 2: Individual employee sync
     */
    public function syncEmployeesDetailed(): array
    {
        try {
            // Increase execution time limit to 5 minutes for sync operation
            set_time_limit(300);
            
            // Temporarily disable foreign key checks to allow supervisor_id references that don't exist yet
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            // Get all employees that need detailed sync
            $employees = BambooHREmployee::where('sync_status', 'directory_synced')
                ->orWhere('sync_status', 'success')
                ->get();

            if ($employees->isEmpty()) {
                return [
                    'synced_count' => 0,
                    'total_count' => 0,
                    'errors' => [],
                    'message' => 'No employees found for detailed sync. Run directory sync first.'
                ];
            }

            $syncedCount = 0;
            $errors = [];
            $batchSize = 10; // Process 10 employees at a time
            $batches = $employees->chunk($batchSize);
            $totalEmployees = $employees->count();
            $totalBatches = $batches->count();

            // Initialize progress tracking
            $this->progressService->initializeProgress('bamboohr', 'detailed', [
                'total_employees' => $totalEmployees,
                'total_batches' => $totalBatches,
                'processed_employees' => 0,
                'processed_batches' => 0,
                'api_calls_made' => 0,
                'current_batch' => 0
            ]);
            
            // Broadcast initial progress
            $this->webSocketService->broadcastProgress('bamboohr', 'detailed', [
                'status' => 'started',
                'total_employees' => $totalEmployees,
                'total_batches' => $totalBatches,
                'processed_employees' => 0,
                'processed_batches' => 0,
                'api_calls_made' => 0,
                'current_batch' => 0
            ]);
            
            $this->webSocketService->broadcastLog('bamboohr', 'detailed', 'info', 'Starting detailed employee sync', [
                'total_employees' => $totalEmployees,
                'batch_size' => $batchSize
            ]);

            Log::info('Starting detailed employee sync in batches', [
                'total_employees' => $totalEmployees,
                'batch_size' => $batchSize,
                'total_batches' => $totalBatches
            ]);

            foreach ($batches as $batchIndex => $batch) {
                Log::info("Processing detailed batch " . ($batchIndex + 1) . " of " . $batches->count(), [
                    'batch_size' => $batch->count()
                ]);

                foreach ($batch as $employee) {
                    try {
                        // Update progress for individual API call
                        $currentProgress = $this->progressService->getProgress('bamboohr', 'detailed');
                        $apiCallsMade = ($currentProgress['api_calls_made'] ?? 0) + 1;
                        
                        $this->progressService->updateProgress('bamboohr', 'detailed', [
                            'api_calls_made' => $apiCallsMade,
                            'current_employee' => $employee->bamboohr_id
                        ]);
                        
                        // Broadcast progress update
                        $this->webSocketService->broadcastProgress('bamboohr', 'detailed', [
                            'status' => 'running',
                            'total_employees' => $totalEmployees,
                            'total_batches' => $totalBatches,
                            'processed_employees' => $syncedCount,
                            'processed_batches' => $batchIndex + 1,
                            'api_calls_made' => $apiCallsMade,
                            'current_employee' => $employee->bamboohr_id
                        ]);
                        
                        $this->webSocketService->broadcastLog('bamboohr', 'detailed', 'info', "Processing employee: {$employee->bamboohr_id}", [
                            'employee_id' => $employee->bamboohr_id,
                            'api_calls_made' => $apiCallsMade
                        ]);

                        // Fetch detailed employee data
                        $detailedData = $this->fetchDetailedEmployeeData($employee->bamboohr_id);
                        
                        // Log the detailed data for debugging
                        Log::info('Processing detailed employee data:', [
                            'employee_id' => $employee->bamboohr_id,
                            'detailed_data' => $detailedData,
                            'hire_date_raw' => $detailedData['hireDate'] ?? 'not_set',
                            'termination_date_raw' => $detailedData['terminationDate'] ?? 'not_set'
                        ]);
                        
                        // Log the final values being saved to database
                        $hireDate = $this->validateDate($detailedData['hireDate'] ?? null);
                        $terminationDate = $this->validateDate($detailedData['terminationDate'] ?? null);
                        
                        Log::info('Final date values for database:', [
                            'employee_id' => $employee->bamboohr_id,
                            'hire_date_final' => $hireDate,
                            'termination_date_final' => $terminationDate
                        ]);
                        
                        // Update employee with detailed data
                        $employee->update([
                            'first_name' => $detailedData['firstName'] ?? $employee->first_name,
                            'last_name' => $detailedData['lastName'] ?? $employee->last_name,
                            'gender' => $detailedData['gender'] ?? null,
                            'status' => $detailedData['employmentStatus'] ?? $employee->status,
                            'hire_date' => $hireDate,
                            'termination_date' => $terminationDate,
                            'work_email' => $detailedData['workEmail'] ?? $employee->work_email,
                            'mobile_phone' => $detailedData['mobilePhone'] ?? $employee->mobile_phone,
                            'work_phone' => $detailedData['workPhone'] ?? $employee->work_phone,
                            'address1' => $detailedData['address1'] ?? $employee->address1,
                            'address2' => $detailedData['address2'] ?? $employee->address2,
                            'city' => $detailedData['city'] ?? $employee->city,
                            'state' => $detailedData['state'] ?? $employee->state,
                            'zip_code' => $detailedData['zipCode'] ?? $employee->zip_code,
                            'country' => $detailedData['country'] ?? $employee->country,
                            'supervisor_id' => $detailedData['supervisorEId'] ?? $employee->supervisor_id,
                            'last_sync_at' => now(),
                            'sync_status' => 'success',
                            'error_message' => null
                        ]);

                    $syncedCount++;

                } catch (Exception $e) {
                        $errors[] = "Employee {$employee->bamboohr_id}: " . $e->getMessage();
                        Log::error('Failed to sync detailed employee data', [
                            'employee_id' => $employee->bamboohr_id,
                        'error' => $e->getMessage()
                    ]);
                        
                        // Mark employee as failed
                        $employee->update([
                            'sync_status' => 'failed',
                            'error_message' => $e->getMessage()
                        ]);
                    }
                }

                // Update progress after batch completion
                $this->progressService->updateProgress('bamboohr', 'detailed', [
                    'processed_employees' => $syncedCount,
                    'processed_batches' => $batchIndex + 1,
                    'current_batch' => $batchIndex + 1,
                    'errors' => $errors
                ]);

                // Log batch completion and add small delay between batches
                $currentProgress = $this->progressService->getProgress('bamboohr', 'detailed');
                Log::info("Completed detailed batch " . ($batchIndex + 1) . " of " . $batches->count(), [
                    'batch_synced' => $syncedCount,
                    'batch_errors' => count($errors),
                    'api_calls_made' => $currentProgress['api_calls_made'] ?? 0
                ]);

                // Small delay between batches to prevent API rate limiting
                if ($batchIndex < $batches->count() - 1) {
                    sleep(1); // 1 second delay between batches
                }
            }

            $this->recordSyncHistory('employees_detailed', null, 'success', "Synced detailed data for {$syncedCount} employees");

            // Broadcast completion
            $finalProgress = $this->progressService->getProgress('bamboohr', 'detailed');
            $this->webSocketService->broadcastProgress('bamboohr', 'detailed', [
                'status' => 'completed',
                'total_employees' => $totalEmployees,
                'total_batches' => $totalBatches,
                'processed_employees' => $syncedCount,
                'processed_batches' => $totalBatches,
                'api_calls_made' => $finalProgress['api_calls_made'] ?? 0,
                'errors' => $errors
            ]);
            
            $this->webSocketService->broadcastLog('bamboohr', 'detailed', 'success', 'Employee detailed sync completed successfully', [
                'synced_count' => $syncedCount,
                'total_count' => $totalEmployees,
                'errors_count' => count($errors)
            ]);

            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            return [
                'synced_count' => $syncedCount,
                'total_count' => $employees->count(),
                'errors' => $errors,
                'message' => 'Detailed sync completed successfully.'
            ];

        } catch (Exception $e) {
            // Re-enable foreign key checks in case of exception
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            
            Log::error('BambooHR detailed employees sync failed', [
                'error' => $e->getMessage()
            ]);

            $this->recordSyncHistory('employees_detailed', null, 'error', $e->getMessage());

            throw $e;
        }
    }

    /**
     * Sync employees from BambooHR (combined method for backward compatibility)
     */
    public function syncEmployees(): array
    {
        // Run directory sync first
        $directoryResult = $this->syncEmployeesDirectory();
        
        if (!$directoryResult['synced_count']) {
            return $directoryResult;
        }
        
        // Then run detailed sync
        $detailedResult = $this->syncEmployeesDetailed();
        
        return [
            'synced_count' => $detailedResult['synced_count'],
            'total_count' => $detailedResult['total_count'],
            'errors' => array_merge($directoryResult['errors'], $detailedResult['errors']),
            'message' => 'Full sync completed: ' . $directoryResult['synced_count'] . ' directory + ' . $detailedResult['synced_count'] . ' detailed'
        ];
    }

    /**
     * Sync departments from BambooHR
     */
    public function syncDepartments(): array
    {
        try {
            // Check if API key is configured
            if (empty($this->apiKey)) {
                return [
                    'success' => false,
                    'message' => 'BambooHR API key not configured. Please set BAMBOOHR_API_KEY in your .env file.',
                    'error' => 'Missing API key configuration'
                ];
            }

            $subdomain = config('services.bamboohr.subdomain');
            $response = Http::withHeaders($this->headers)
                ->timeout(30)
                ->get("https://{$subdomain}.bamboohr.com/api/gateway.php/{$subdomain}/v1/employees/directory");

            if (!$response->successful()) {
                throw new Exception("Failed to fetch departments: " . $response->body());
            }

            $responseData = $response->json();
            
            // Check if response is valid
            if (!is_array($responseData)) {
                throw new Exception("Invalid response format from BambooHR API. Expected array, got: " . gettype($responseData));
            }

            // Extract employees from the response structure
            $employees = $responseData['employees'] ?? [];
            
            if (!is_array($employees)) {
                throw new Exception("No employees data found in BambooHR API response");
            }

            // Extract unique departments from employee data
            $departments = [];
            foreach ($employees as $employee) {
                if (!empty($employee['department'])) {
                    $departments[$employee['department']] = [
                        'name' => $employee['department'],
                        'description' => '',
                        'parent_department_id' => null
                    ];
                }
            }

            $syncedCount = 0;
            $errors = [];

            foreach ($departments as $deptName => $deptData) {
                try {
                    $department = BambooHRDepartment::updateOrCreate(
                        ['name' => $deptData['name']],
                        [
                            'bamboohr_id' => $deptName, // Use department name as ID for now
                            'description' => $deptData['description'] ?? '',
                            'parent_department_id' => $deptData['parent_department_id'] ?? null,
                            'last_sync_at' => now(),
                            'sync_status' => 'success',
                            'error_message' => null
                        ]
                    );

                    $syncedCount++;

                } catch (Exception $e) {
                    $errors[] = "Department {$deptName}: " . $e->getMessage();
                }
            }

            $this->recordSyncHistory('departments', null, 'success', "Synced {$syncedCount} departments");

            // Broadcast completion
            $this->webSocketService->broadcastLog('bamboohr', 'departments', 'success', 'Departments sync completed successfully', [
                'synced_count' => $syncedCount,
                'total_count' => count($departments),
                'errors_count' => count($errors)
            ]);

            return [
                'synced_count' => $syncedCount,
                'total_count' => count($departments),
                'errors' => $errors
            ];

        } catch (Exception $e) {
            Log::error('BambooHR departments sync failed', [
                'error' => $e->getMessage()
            ]);

            $this->recordSyncHistory('departments', null, 'error', $e->getMessage());

            throw $e;
        }
    }

    /**
     * Sync job titles from BambooHR
     */
    public function syncJobTitles(): array
    {
        try {
            // Check if API key is configured
            if (empty($this->apiKey)) {
                return [
                    'success' => false,
                    'message' => 'BambooHR API key not configured. Please set BAMBOOHR_API_KEY in your .env file.',
                    'error' => 'Missing API key configuration'
                ];
            }

            $subdomain = config('services.bamboohr.subdomain');
            $response = Http::withHeaders($this->headers)
                ->timeout(30)
                ->get("https://{$subdomain}.bamboohr.com/api/gateway.php/{$subdomain}/v1/employees/directory");

            if (!$response->successful()) {
                throw new Exception("Failed to fetch job titles: " . $response->body());
            }

            $responseData = $response->json();
            
            // Check if response is valid
            if (!is_array($responseData)) {
                throw new Exception("Invalid response format from BambooHR API. Expected array, got: " . gettype($responseData));
            }

            // Extract employees from the response structure
            $employees = $responseData['employees'] ?? [];
            
            if (!is_array($employees)) {
                throw new Exception("No employees data found in BambooHR API response");
            }

            // Extract unique job titles from employee data
            $jobTitles = [];
            foreach ($employees as $employee) {
                if (!empty($employee['jobTitle'])) {
                    $jobTitles[$employee['jobTitle']] = [
                        'name' => $employee['jobTitle'],
                        'description' => ''
                    ];
                }
            }

            $syncedCount = 0;
            $errors = [];

            foreach ($jobTitles as $titleName => $titleData) {
                try {
                    $jobTitle = BambooHRJobTitle::updateOrCreate(
                        ['title' => $titleData['name']],
                        [
                            'bamboohr_id' => $titleName, // Use job title name as ID for now
                            'description' => $titleData['description'] ?? '',
                            'department_id' => null, // We'll need to link this later
                            'last_sync_at' => now(),
                            'sync_status' => 'success',
                            'error_message' => null
                        ]
                    );

                    $syncedCount++;

                } catch (Exception $e) {
                    $errors[] = "Job Title {$titleName}: " . $e->getMessage();
                }
            }

            $this->recordSyncHistory('job_titles', null, 'success', "Synced {$syncedCount} job titles");

            // Broadcast completion
            $this->webSocketService->broadcastLog('bamboohr', 'job_titles', 'success', 'Job titles sync completed successfully', [
                'synced_count' => $syncedCount,
                'total_count' => count($jobTitles),
                'errors_count' => count($errors)
            ]);

            return [
                'synced_count' => $syncedCount,
                'total_count' => count($jobTitles),
                'errors' => $errors
            ];

        } catch (Exception $e) {
            Log::error('BambooHR job titles sync failed', [
                'error' => $e->getMessage()
            ]);

            $this->recordSyncHistory('job_titles', null, 'error', $e->getMessage());

            throw $e;
        }
    }

    /**
     * Sync time off requests from BambooHR
     */
    public function syncTimeOff(): array
    {
        try {
            // Check if API key is configured
            if (empty($this->apiKey)) {
                return [
                    'success' => false,
                    'message' => 'BambooHR API key not configured. Please set BAMBOOHR_API_KEY in your .env file.',
                    'error' => 'Missing API key configuration'
                ];
            }

            $subdomain = config('services.bamboohr.subdomain');
            
            // Try different possible endpoints for time off requests
            $possibleEndpoints = [
                "https://{$subdomain}.bamboohr.com/api/gateway.php/{$subdomain}/v1/time_off/requests",
                "https://{$subdomain}.bamboohr.com/api/gateway.php/{$subdomain}/v1/time_off",
                "https://{$subdomain}.bamboohr.com/api/gateway.php/{$subdomain}/v1/employees/time_off",
                "https://{$subdomain}.bamboohr.com/api/gateway.php/{$subdomain}/v1/time_off/whos_out"
            ];

            $response = null;
            $workingEndpoint = null;

            foreach ($possibleEndpoints as $endpoint) {
                $response = Http::withHeaders($this->headers)
                    ->timeout(30)
                    ->get($endpoint);

                if ($response->successful()) {
                    $workingEndpoint = $endpoint;
                    break;
                }
                
                Log::info("BambooHR endpoint test failed", [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }

            if (!$response || !$response->successful()) {
                // If no endpoint works, return a graceful response
                Log::warning('BambooHR time off API endpoints not available', [
                    'tested_endpoints' => $possibleEndpoints,
                    'last_status' => $response ? $response->status() : 'no_response',
                    'last_body' => $response ? $response->body() : 'no_response'
                ]);

                $this->recordSyncHistory('time_off', null, 'warning', 'Time off API endpoints not available or accessible');

                return [
                    'success' => true,
                    'message' => 'Time off sync skipped - API endpoints not available',
                    'synced_count' => 0,
                    'total_count' => 0,
                    'errors' => ['Time off API endpoints not available or accessible']
                ];
            }

            $timeOffRequests = $response->json();
            
            // Check if response is valid
            if (!is_array($timeOffRequests)) {
                throw new Exception("Invalid response format from BambooHR API. Expected array, got: " . gettype($timeOffRequests));
            }

            $syncedCount = 0;
            $errors = [];

            foreach ($timeOffRequests as $requestData) {
                try {
                    BambooHRTimeOff::updateOrCreate(
                        ['bamboohr_id' => $requestData['id']],
                        [
                            'employee_id' => $requestData['employeeId'] ?? null,
                            'type' => $requestData['type'] ?? '',
                            'start_date' => $requestData['startDate'] ?? null,
                            'end_date' => $requestData['endDate'] ?? null,
                            'days_requested' => $requestData['daysRequested'] ?? 0,
                            'status' => $requestData['status'] ?? 'pending',
                            'requested_date' => $requestData['requestedDate'] ?? null,
                            'approved_date' => $requestData['approvedDate'] ?? null,
                            'approved_by' => $requestData['approvedBy'] ?? null,
                            'notes' => $requestData['notes'] ?? '',
                            'last_sync_at' => now(),
                            'sync_status' => 'success',
                            'error_message' => null
                        ]
                    );

                    $syncedCount++;

                } catch (Exception $e) {
                    $errors[] = "Time Off Request {$requestData['id']}: " . $e->getMessage();
                }
            }

            $this->recordSyncHistory('time_off', null, 'success', "Synced {$syncedCount} time off requests using endpoint: {$workingEndpoint}");

            // Broadcast completion
            $this->webSocketService->broadcastLog('bamboohr', 'time_off', 'success', 'Time off sync completed successfully', [
                'synced_count' => $syncedCount,
                'total_count' => count($timeOffRequests),
                'errors_count' => count($errors),
                'endpoint_used' => $workingEndpoint
            ]);

            return [
                'synced_count' => $syncedCount,
                'total_count' => count($timeOffRequests),
                'errors' => $errors,
                'endpoint_used' => $workingEndpoint
            ];

        } catch (Exception $e) {
            Log::error('BambooHR time off sync failed', [
                'error' => $e->getMessage()
            ]);

            $this->recordSyncHistory('time_off', null, 'error', $e->getMessage());

            throw $e;
        }
    }

    /**
     * Get sync status and statistics
     */
    public function getStatus(): array
    {
        $stats = [
            'employees' => BambooHREmployee::count(),
            'departments' => BambooHRDepartment::count(),
            'job_titles' => BambooHRJobTitle::count(),
            'time_off' => BambooHRTimeOff::count(),
        ];

        $lastSync = [
            'employees' => BambooHREmployee::max('last_sync_at'),
            'departments' => BambooHRDepartment::max('last_sync_at'),
            'job_titles' => BambooHRJobTitle::max('last_sync_at'),
            'time_off' => BambooHRTimeOff::max('last_sync_at'),
        ];

        return [
            'status' => 'success',
            'data' => [
                'stats' => $stats,
                'last_sync' => $lastSync,
            ]
        ];
    }

    /**
     * Clear cache and reset sync status
     */
    public function clearCache(): array
    {
        try {
            // Clear any cached data
            Cache::forget('bamboohr_connection_test');
            Cache::forget('bamboohr_sync_status');

            // Reset sync status for all models
            BambooHREmployee::query()->update(['sync_status' => 'pending']);
            BambooHRDepartment::query()->update(['sync_status' => 'pending']);
            BambooHRJobTitle::query()->update(['sync_status' => 'pending']);
            BambooHRTimeOff::query()->update(['sync_status' => 'pending']);

            $this->recordSyncHistory('clear_cache', null, 'success', 'Cache cleared successfully');

            return [
                'success' => true,
                'message' => 'Cache cleared successfully'
            ];

        } catch (Exception $e) {
            Log::error('BambooHR cache clear failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Cache clear failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Fetch detailed employee data from BambooHR API
     */
    private function fetchDetailedEmployeeData(string $employeeId): array
    {
        try {
            $subdomain = config('services.bamboohr.subdomain') ?? 'valsoftaspire';
            $url = "https://{$subdomain}.bamboohr.com/api/v1/employees/{$employeeId}";
            $params = [
                'fields' => 'firstName,lastName,gender,supervisorEId,employmentStatus,hireDate,terminationDate,mobilePhone,workEmail,workPhone,address1,address2,city,state,country,zipCode',
                'onlyCurrent' => '1'
            ];

            $response = Http::withHeaders($this->headers)
                ->timeout(30)
                ->get($url, $params);

            if (!$response->successful()) {
                throw new Exception("Failed to fetch detailed employee data for ID {$employeeId}. Status: {$response->status()}, Response: {$response->body()}");
            }

            $data = $response->json();
            
            if (!is_array($data)) {
                throw new Exception("Invalid response format for employee {$employeeId}. Expected array, got: " . gettype($data));
            }

            return $data;
        } catch (Exception $e) {
            Log::error("Failed to fetch detailed employee data for ID {$employeeId}", [
                'employee_id' => $employeeId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Validate and format date values
     */
    private function validateDate($date): ?string
    {
        // Log the input date for debugging
        Log::info('validateDate called with:', ['input_date' => $date, 'type' => gettype($date)]);
        
        if (empty($date) || $date === '-0001-11-30 00:00:00' || $date === '-0001-11-30' || $date === '0000-00-00') {
            Log::info('validateDate returning null for invalid date:', ['date' => $date]);
            return null;
        }
        
        // Additional validation to ensure it's a valid date
        try {
            $dateTime = new DateTime($date);
            $formattedDate = $dateTime->format('Y-m-d');
            Log::info('validateDate returning formatted date:', ['original' => $date, 'formatted' => $formattedDate]);
            return $formattedDate;
        } catch (Exception $e) {
            Log::info('validateDate returning null due to exception:', ['date' => $date, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Sync multiple operations based on selection
     */
    public function syncSelected(array $selectedOperations): array
    {
        try {
            $results = [];
            $overallSuccess = true;
            $overallErrors = [];
            
            $this->webSocketService->broadcastLog('bamboohr', 'selected', 'info', 'Starting selected sync operations', [
                'selected_operations' => $selectedOperations
            ]);
            
            foreach ($selectedOperations as $operation) {
                try {
                    $this->webSocketService->broadcastLog('bamboohr', 'selected', 'info', "Starting sync operation: {$operation}");
                    
                    switch ($operation) {
                        case 'employees':
                            $results['employees'] = $this->syncEmployeesDirectory();
                            break;
                        case 'employee_details':
                            $results['employee_details'] = $this->syncEmployeesDetailed();
                            break;
                        case 'departments':
                            $results['departments'] = $this->syncDepartments();
                            break;
                        case 'job_titles':
                            $results['job_titles'] = $this->syncJobTitles();
                            break;
                        case 'time_off':
                            $results['time_off'] = $this->syncTimeOff();
                            break;
                        default:
                            throw new Exception("Unknown sync operation: {$operation}");
                    }
                    
                    $this->webSocketService->broadcastLog('bamboohr', 'selected', 'success', "Completed sync operation: {$operation}");
                    
                } catch (Exception $e) {
                    $overallSuccess = false;
                    $overallErrors[] = "{$operation}: " . $e->getMessage();
                    $this->webSocketService->broadcastLog('bamboohr', 'selected', 'error', "Failed sync operation: {$operation}", [
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            $this->webSocketService->broadcastLog('bamboohr', 'selected', $overallSuccess ? 'success' : 'warning', 'Selected sync operations completed', [
                'success' => $overallSuccess,
                'errors' => $overallErrors
            ]);
            
            return [
                'success' => $overallSuccess,
                'message' => $overallSuccess ? 'All selected operations completed successfully' : 'Some operations failed',
                'data' => $results,
                'errors' => $overallErrors
            ];
            
        } catch (Exception $e) {
            $this->webSocketService->broadcastLog('bamboohr', 'selected', 'error', 'Selected sync operations failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Selected sync operations failed: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Record sync history
     */
    private function recordSyncHistory(string $table, ?string $projectId, string $status, string $message): void
    {
        try {
            SyncHistory::create([
                'table_name' => $table,
                'project_id' => $projectId,
                'sync_type' => 'bamboohr',
                'status' => $status,
                'records_processed' => 0,
                'error_message' => $status === 'error' ? $message : null,
                'last_sync_at' => now(),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to record sync history', [
                'error' => $e->getMessage()
            ]);
        }
    }
}