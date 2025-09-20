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

        $employees = $query->orderBy('employee_name')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $employees
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
        $employee = InatechEmployee::find($id);

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        // Get all BambooHR employees
        $bambooHREmployees = BambooHREmployee::where('status', 'active')->get();

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
                    'is_already_mapped' => EmployeeMapping::where('bamboohr_id', $bambooEmployee->id)->exists()
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
                'suggestions' => $suggestions
            ]
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
