<?php

namespace App\Http\Controllers;

use App\Models\InatechEmployee;
use App\Models\BambooHREmployee;
use App\Models\EmployeeMapping;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class InatechEmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = InatechEmployee::query();

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('employee_name', 'like', "%{$search}%")
                  ->orWhere('ina_emp_id', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Return all employees without pagination (client-side grid handles pagination)
        $employees = $query->orderBy('employee_name')->get();

        // Add mapping information to each employee
        $employeesWithMapping = $employees->map(function ($employee) {
            $mapping = EmployeeMapping::where('ina_emp_id', $employee->id)->first();
            
            return [
                'id' => $employee->id,
                'employee_name' => $employee->employee_name,
                'ina_emp_id' => $employee->ina_emp_id,
                'department' => $employee->department,
                'job_title' => $employee->job_title,
                'status' => $employee->status,
                'is_mapped' => $mapping ? true : false,
                'mapping_status' => $mapping ? 'Mapped' : 'Unmapped',
                'bamboohr_id' => $mapping ? $mapping->bamboohr_id : null,
                'mapping_id' => $mapping ? $mapping->id : null,
                'created_at' => $employee->created_at,
                'updated_at' => $employee->updated_at
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'data' => $employeesWithMapping, // Include mapping information
                'total' => $employeesWithMapping->count()
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ina_emp_id' => 'required|string|unique:inatech_employees,ina_emp_id',
            'employee_name' => 'required|string|max:255',
            'status' => 'required|string|in:active,inactive'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $employee = InatechEmployee::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Employee created successfully',
            'data' => $employee
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $employee = InatechEmployee::find($id);

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $employee
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $employee = InatechEmployee::find($id);

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'ina_emp_id' => 'required|string|unique:inatech_employees,ina_emp_id,' . $id,
            'employee_name' => 'required|string|max:255',
            'status' => 'required|string|in:active,inactive'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $employee->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Employee updated successfully',
            'data' => $employee
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $employee = InatechEmployee::find($id);

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        $employee->delete();

        return response()->json([
            'success' => true,
            'message' => 'Employee deleted successfully'
        ]);
    }

    /**
     * Get intelligent mapping suggestions for an Inatech employee
     */
    public function getMappingSuggestions(string $id): JsonResponse
    {
        \Log::info("Getting mapping suggestions for employee ID: {$id}");
        $employee = InatechEmployee::find($id);

        if (!$employee) {
            \Log::warning("Employee not found with ID: {$id}");
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        // Check if this Inatech employee is already mapped
        $existingMapping = EmployeeMapping::where('ina_emp_id', $id)->first();
        if ($existingMapping) {
            return response()->json([
                'success' => true,
                'data' => [
                    'inatech_employee' => $employee,
                    'is_already_mapped' => true,
                    'existing_mapping' => $existingMapping->load('bambooHREmployee'),
                    'suggestions' => [] // No suggestions needed if already mapped
                ]
            ]);
        }

        // Get all BambooHR employees (excluding already mapped ones)
        $bambooHREmployees = BambooHREmployee::where('status', 'active')
            ->whereNotIn('id', function($query) {
                $query->select('bamboohr_id')
                      ->from('employee_mapping')
                      ->whereNotNull('bamboohr_id');
            })
            ->orderBy('last_name')
            ->get();

        $suggestions = [];

        foreach ($bambooHREmployees as $bambooEmployee) {
            $similarity = $this->calculateNameSimilarity(
                $employee->employee_name,
                $bambooEmployee->getFullNameAttribute()
            );

            // Only include suggestions with at least 30% similarity
            if ($similarity >= 30) {
                $suggestions[] = [
                    'bamboo_employee' => $bambooEmployee,
                    'similarity_percentage' => round($similarity, 2),
                    'is_available_for_mapping' => true // All returned employees are available
                ];
            }
        }

        // Sort by similarity percentage (highest first)
        usort($suggestions, function($a, $b) {
            return $b['similarity_percentage'] <=> $a['similarity_percentage'];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'inatech_employee' => $employee,
                'is_already_mapped' => false,
                'suggestions' => $suggestions
            ]
        ]);
    }

    /**
     * Check if BambooHR employee is already mapped
     */
    public function checkMapping(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bamboohr_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $existingMapping = EmployeeMapping::where('bamboohr_id', $request->bamboohr_id)
            ->with(['bambooHREmployee', 'inaEmployee'])
            ->first();

        if ($existingMapping) {
            return response()->json([
                'success' => true,
                'is_mapped' => true,
                'message' => 'BambooHR employee is already mapped',
                'data' => [
                    'mapping_id' => $existingMapping->id,
                    'bamboohr_employee' => $existingMapping->bambooHREmployee,
                    'ina_employee' => $existingMapping->inaEmployee,
                    'mapped_since' => $existingMapping->created_at
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'is_mapped' => false,
            'message' => 'BambooHR employee is not mapped to any Inatech employee'
        ]);
    }

    /**
     * Create or update employee mapping
     */
    public function createMapping(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ina_emp_id' => 'required|integer|exists:inatech_employees,id',
            'bamboohr_id' => 'required|integer|exists:bamboohr_employees,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if BambooHR employee is already mapped
        $existingMapping = EmployeeMapping::where('bamboohr_id', $request->bamboohr_id)->first();
        if ($existingMapping) {
            return response()->json([
                'success' => false,
                'message' => 'This BambooHR employee is already mapped to another Inatech employee'
            ], 409);
        }

        // Create or update mapping
        $mapping = EmployeeMapping::updateOrCreate(
            ['ina_emp_id' => $request->ina_emp_id],
            ['bamboohr_id' => $request->bamboohr_id]
        );

        return response()->json([
            'success' => true,
            'message' => 'Employee mapping created successfully',
            'data' => $mapping->load('bambooHREmployee')
        ]);
    }

    /**
     * Remove employee mapping
     */
    public function removeMapping(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ina_emp_id' => 'required|integer|exists:inatech_employees,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $mapping = EmployeeMapping::where('ina_emp_id', $request->ina_emp_id)->first();

        if (!$mapping) {
            return response()->json([
                'success' => false,
                'message' => 'No mapping found for this employee'
            ], 404);
        }

        $mapping->delete();

        return response()->json([
            'success' => true,
            'message' => 'Employee mapping removed successfully'
        ]);
    }

    /**
     * Get bulk mapping data for the interface
     */
    public function getBulkMappingData(): JsonResponse
    {
        // Get unmapped BambooHR employees
        $unmappedBambooHR = BambooHREmployee::where('status', 'active')
            ->whereNotIn('id', function($query) {
                $query->select('bamboohr_id')
                      ->from('employee_mapping')
                      ->whereNotNull('bamboohr_id');
            })
            ->orderBy('last_name')
            ->get()
            ->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'name' => $employee->getFullNameAttribute(),
                    'email' => $employee->email,
                    'department' => $employee->department ? $employee->department->name : 'N/A',
                    'job_title' => $employee->job_title,
                    'hire_date' => $employee->hire_date
                ];
            });

        // Get unmapped Inatech employees
        $unmappedInatech = InatechEmployee::where('status', 'active')
            ->whereNotIn('id', function($query) {
                $query->select('ina_emp_id')
                      ->from('employee_mapping')
                      ->whereNotNull('ina_emp_id');
            })
            ->orderBy('employee_name')
            ->get()
            ->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'name' => $employee->employee_name,
                    'ina_emp_id' => $employee->ina_emp_id,
                    'department' => $employee->department,
                    'job_title' => $employee->job_title
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'bamboohr_employees' => $unmappedBambooHR,
                'inatech_employees' => $unmappedInatech,
                'total_bamboohr' => $unmappedBambooHR->count(),
                'total_inatech' => $unmappedInatech->count()
            ]
        ]);
    }

    /**
     * Get all employee mappings
     */
    public function getAllMappings(Request $request): JsonResponse
    {
        try {
            $query = EmployeeMapping::with(['bambooHREmployee', 'inaEmployee']);

            // Apply search filter
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->whereHas('bambooHREmployee', function($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                })->orWhereHas('inaEmployee', function($q) use ($search) {
                    $q->where('employee_name', 'like', "%{$search}%")
                      ->orWhere('ina_emp_id', 'like', "%{$search}%");
                });
            }

            // Get all mappings
            $mappings = $query->orderBy('created_at', 'desc')->get();

            // Format the response
            $formattedMappings = $mappings->map(function ($mapping) {
                return [
                    'id' => $mapping->id,
                    'bamboohrEmployee' => $mapping->bambooHREmployee ? [
                        'id' => $mapping->bambooHREmployee->id,
                        'name' => $mapping->bambooHREmployee->getFullNameAttribute(),
                        'email' => $mapping->bambooHREmployee->email,
                        'department' => $mapping->bambooHREmployee->department ? $mapping->bambooHREmployee->department->name : 'N/A',
                        'job_title' => $mapping->bambooHREmployee->job_title
                    ] : null,
                    'inatechEmployee' => $mapping->inaEmployee ? [
                        'id' => $mapping->inaEmployee->id,
                        'name' => $mapping->inaEmployee->employee_name,
                        'ina_emp_id' => $mapping->inaEmployee->ina_emp_id,
                        'department' => $mapping->inaEmployee->department,
                        'job_title' => $mapping->inaEmployee->job_title
                    ] : null,
                    'confidence' => null, // This could be calculated if needed
                    'mappedAt' => $mapping->created_at,
                    'created_at' => $mapping->created_at,
                    'updated_at' => $mapping->updated_at
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'data' => $formattedMappings,
                    'total' => $formattedMappings->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching mappings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get intelligent mapping suggestions for bulk mapping
     */
    public function getBulkMappingSuggestions(): JsonResponse
    {
        // Get all unmapped employees
        $unmappedBambooHR = BambooHREmployee::where('status', 'active')
            ->whereNotIn('id', function($query) {
                $query->select('bamboohr_id')
                      ->from('employee_mapping')
                      ->whereNotNull('bamboohr_id');
            })
            ->orderBy('last_name')
            ->get();

        $unmappedInatech = InatechEmployee::where('status', 'active')
            ->whereNotIn('id', function($query) {
                $query->select('ina_emp_id')
                      ->from('employee_mapping')
                      ->whereNotNull('ina_emp_id');
            })
            ->orderBy('employee_name')
            ->get();

        $suggestions = [];

        foreach ($unmappedInatech as $inatechEmployee) {
            $employeeSuggestions = [];

            foreach ($unmappedBambooHR as $bambooEmployee) {
                $matchScore = 0;
                $matchType = 'none';
                $similarity = 0;

                // First priority: Email matching
                if (!empty($bambooEmployee->email) && !empty($bambooEmployee->work_email)) {
                    $emails = array_filter([
                        $bambooEmployee->email,
                        $bambooEmployee->work_email
                    ]);
                    
                    foreach ($emails as $email) {
                        // Check if email contains the employee name (common pattern)
                        $emailName = $this->extractNameFromEmail($email);
                        if (!empty($emailName)) {
                            $emailSimilarity = $this->calculateNameSimilarity(
                                $inatechEmployee->employee_name,
                                $emailName
                            );
                            
                            if ($emailSimilarity >= 70) {
                                $matchScore = $emailSimilarity;
                                $matchType = 'email_name';
                                $similarity = $emailSimilarity;
                                break;
                            }
                        }
                    }
                }

                // Second priority: Direct name similarity (if no email match)
                if ($matchScore === 0) {
                    $similarity = $this->calculateNameSimilarity(
                        $inatechEmployee->employee_name,
                        $bambooEmployee->getFullNameAttribute()
                    );

                    // Only include suggestions with at least 50% similarity for name matching
                    if ($similarity >= 50) {
                        $matchScore = $similarity;
                        $matchType = 'name';
                    }
                }

                // Include suggestion if we have a good match
                if ($matchScore > 0) {
                    $employeeSuggestions[] = [
                        'bamboohr_employee' => [
                            'id' => $bambooEmployee->id,
                            'name' => $bambooEmployee->getFullNameAttribute(),
                            'email' => $bambooEmployee->email,
                            'work_email' => $bambooEmployee->work_email,
                            'department' => $bambooEmployee->department ? $bambooEmployee->department->name : 'N/A',
                            'job_title' => $bambooEmployee->job_title
                        ],
                        'similarity_percentage' => round($matchScore, 2),
                        'match_type' => $matchType,
                        'confidence' => $this->calculateConfidence($matchScore, $matchType)
                    ];
                }
            }

            // Sort by confidence score (highest first)
            usort($employeeSuggestions, function($a, $b) {
                return $b['confidence'] <=> $a['confidence'];
            });

            // Only include if there are suggestions
            if (!empty($employeeSuggestions)) {
                $suggestions[] = [
                    'inatech_employee' => [
                        'id' => $inatechEmployee->id,
                        'name' => $inatechEmployee->employee_name,
                        'ina_emp_id' => $inatechEmployee->ina_emp_id,
                        'department' => $inatechEmployee->department,
                        'job_title' => $inatechEmployee->job_title
                    ],
                    'suggestions' => $employeeSuggestions
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'suggestions' => $suggestions,
                'total_suggestions' => count($suggestions)
            ]
        ]);
    }

    /**
     * Create bulk employee mappings
     */
    public function createBulkMappings(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'mappings' => 'required|array|min:1',
            'mappings.*.inatech_id' => 'required|integer|exists:inatech_employees,id',
            'mappings.*.bamboohr_id' => 'required|integer|exists:bamboohr_employees,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $mappings = $request->mappings;
        $createdMappings = [];
        $errors = [];

        foreach ($mappings as $index => $mapping) {
            try {
                // Check if BambooHR employee is already mapped to any Inatech employee
                $existingBambooMapping = EmployeeMapping::where('bamboohr_id', $mapping['bamboohr_id'])->first();
                if ($existingBambooMapping) {
                    $bambooEmployee = BambooHREmployee::find($mapping['bamboohr_id']);
                    $bambooName = $bambooEmployee ? $bambooEmployee->getFullNameAttribute() : "ID {$mapping['bamboohr_id']}";
                    $errors[] = "BambooHR employee '{$bambooName}' is already mapped to another Inatech employee";
                    continue;
                }

                // Check if Inatech employee is already mapped to any BambooHR employee
                $existingInatechMapping = EmployeeMapping::where('ina_emp_id', $mapping['inatech_id'])->first();
                if ($existingInatechMapping) {
                    $inatechEmployee = InatechEmployee::find($mapping['inatech_id']);
                    $inatechName = $inatechEmployee ? $inatechEmployee->employee_name : "ID {$mapping['inatech_id']}";
                    $errors[] = "Inatech employee '{$inatechName}' is already mapped to another BambooHR employee";
                    continue;
                }

                // Create one-to-one mapping
                $newMapping = EmployeeMapping::create([
                    'ina_emp_id' => $mapping['inatech_id'],
                    'bamboohr_id' => $mapping['bamboohr_id']
                ]);

                $createdMappings[] = $newMapping->load(['bambooHREmployee', 'inaEmployee']);

            } catch (\Exception $e) {
                $errors[] = "Error creating mapping for pair " . ($index + 1) . ": " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Bulk mappings processed',
            'data' => [
                'created_mappings' => $createdMappings,
                'total_created' => count($createdMappings),
                'total_requested' => count($mappings),
                'errors' => $errors
            ]
        ]);
    }

    /**
     * Extract name from email address
     */
    private function extractNameFromEmail(string $email): ?string
    {
        // Extract the part before @
        $localPart = explode('@', $email)[0];
        
        // Remove common separators and numbers
        $name = preg_replace('/[._-]/', ' ', $localPart);
        $name = preg_replace('/\d+/', '', $name);
        $name = trim($name);
        
        // Only return if it looks like a name (has letters and spaces)
        if (preg_match('/^[a-zA-Z\s]+$/', $name) && strlen($name) > 2) {
            return $name;
        }
        
        return null;
    }

    /**
     * Calculate confidence score based on match type and similarity
     */
    private function calculateConfidence(float $similarity, string $matchType): float
    {
        $baseScore = $similarity;
        
        // Boost confidence based on match type
        switch ($matchType) {
            case 'email_name':
                return min(100, $baseScore * 1.2); // 20% boost for email-based matching
            case 'name':
                return $baseScore;
            default:
                return $baseScore * 0.8; // 20% penalty for unknown match type
        }
    }

    /**
     * Calculate name similarity percentage using Levenshtein distance
     */
    private function calculateNameSimilarity(string $name1, string $name2): float
    {
        $name1 = strtolower(trim($name1));
        $name2 = strtolower(trim($name2));

        if ($name1 === $name2) {
            return 100.0;
        }

        $maxLength = max(strlen($name1), strlen($name2));
        if ($maxLength === 0) {
            return 0.0;
        }

        $distance = levenshtein($name1, $name2);
        $similarity = (($maxLength - $distance) / $maxLength) * 100;

        return max(0, $similarity);
    }
}
