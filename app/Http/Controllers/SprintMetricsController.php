<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\AzureDevOpsService;
use App\Models\ADOProject;
use App\Models\ADOTeam;
use App\Models\ADOIteration;
use App\Models\ADOWorkItem;
use App\Models\ADOUser;
use Illuminate\Support\Facades\Log;

class SprintMetricsController extends Controller
{
    protected $azureDevOpsService;

    public function __construct(AzureDevOpsService $azureDevOpsService)
    {
        $this->azureDevOpsService = $azureDevOpsService;
    }

    /**
     * Get all projects from Azure DevOps
     */
    public function getProjects(): JsonResponse
    {
        try {
            $projects = $this->azureDevOpsService->getProjects();
            
            // Add debug info about database state
            $totalProjects = \App\Models\ADOProject::count();
            $totalWorkItems = \App\Models\ADOWorkItem::count();
            $totalIterations = \App\Models\ADOIteration::count();
            $totalTeamIterations = \App\Models\ADOTeamIteration::count();
            
            Log::info("📊 Database state - Projects: {$totalProjects}, WorkItems: {$totalWorkItems}, Iterations: {$totalIterations}, TeamIterations: {$totalTeamIterations}");
            
            return response()->json([
                'success' => true,
                'data' => $projects,
                'debug' => [
                    'totalProjects' => $totalProjects,
                    'totalWorkItems' => $totalWorkItems,
                    'totalIterations' => $totalIterations,
                    'totalTeamIterations' => $totalTeamIterations
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching projects: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch projects',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get teams for a specific project
     */
    public function getTeams(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'project_id' => 'required|string'
            ]);

            // Try to find project by name first, then by ID
            $project = ADOProject::where('name', $request->project_id)
                ->orWhere('id', $request->project_id)
                ->first();

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found',
                    'error' => 'Project with identifier "' . $request->project_id . '" not found in database'
                ], 404);
            }

            $teams = $this->azureDevOpsService->getTeams($project->id);
            
            return response()->json([
                'success' => true,
                'data' => $teams
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching teams: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch teams',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get iterations/sprints for a specific team
     */
    public function getSprints(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'project_id' => 'required|string',
                'team_id' => 'required|string'
            ]);

            // Try to find project by name first, then by ID
            $project = ADOProject::where('name', $request->project_id)
                ->orWhere('id', $request->project_id)
                ->first();

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found',
                    'error' => 'Project with identifier "' . $request->project_id . '" not found in database'
                ], 404);
            }

            // Try to find team by name first, then by ID
            $team = ADOTeam::where('name', $request->team_id)
                ->orWhere('id', $request->team_id)
                ->where('project_id', $project->id)
                ->first();

            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team not found',
                    'error' => 'Team with identifier "' . $request->team_id . '" not found in project "' . $project->name . '"'
                ], 404);
            }

            $sprints = $this->azureDevOpsService->getTeamIterations($project->id, $team->id);
            
            return response()->json([
                'success' => true,
                'data' => $sprints
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching sprints: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sprints',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sprint metrics for a specific sprint
     */
    public function getSprintMetrics(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'project_id' => 'required|string',
                'team_id' => 'required|string',
                'sprint_id' => 'required|string'
            ]);

            // Try to find project by name first, then by ID
            $project = ADOProject::where('name', $request->project_id)
                ->orWhere('id', $request->project_id)
                ->first();

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found',
                    'error' => 'Project with identifier "' . $request->project_id . '" not found in database'
                ], 404);
            }

            $projectId = $project->id;
            
            // Try to find team by name first, then by ID
            $team = ADOTeam::where('name', $request->team_id)
                ->orWhere('id', $request->team_id)
                ->where('project_id', $project->id)
                ->first();

            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team not found',
                    'error' => 'Team with identifier "' . $request->team_id . '" not found in project "' . $project->name . '"'
                ], 404);
            }

            $teamId = $team->id;
            $sprintId = $request->sprint_id;

            Log::info("🎯 SprintMetricsController - Project: {$project->name} (ID: {$projectId}), Team: {$team->name} (ID: {$teamId}), Sprint: {$sprintId}");

            // Get work items for the sprint
            $workItems = $this->azureDevOpsService->getWorkItemsByIteration($projectId, $sprintId);
            
            Log::info("🎯 Retrieved " . count($workItems) . " work items for sprint metrics");
            
            // Calculate metrics
            $metrics = $this->calculateSprintMetrics($workItems);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'metrics' => $metrics,
                    'workItems' => $workItems
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching sprint metrics: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sprint metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate sprint metrics from work items
     */
    private function calculateSprintMetrics(array $workItems): array
    {
        Log::info("📊 calculateSprintMetrics called with " . count($workItems) . " work items");
        
        $totalWorkItems = count($workItems);
        $completedWorkItems = 0;
        $inProgressWorkItems = 0;
        $notStartedWorkItems = 0;
        
        $workItemTypes = [
            'task' => 0,
            'bug' => 0,
            'feature' => 0,
            'requirement' => 0
        ];
        
        $assignedCount = 0;
        $unassignedCount = 0;
        
        $totalOriginalEstimate = 0;
        $totalCompletedWork = 0;
        $totalRemainingWork = 0;

        foreach ($workItems as $workItem) {
            $state = strtolower($workItem['state'] ?? '');
            $workItemType = strtolower($workItem['workItemType'] ?? '');
            $assignedTo = $workItem['assignedTo'] ?? null;
            
            // Count by state
            if (in_array($state, ['completed', 'done', 'closed', 'resolved'])) {
                $completedWorkItems++;
            } elseif (in_array($state, ['in progress', 'active', 'committed'])) {
                $inProgressWorkItems++;
            } else {
                $notStartedWorkItems++;
            }
            
            // Count by work item type
            if (in_array($workItemType, ['task', 'tasks'])) {
                $workItemTypes['task']++;
            } elseif (in_array($workItemType, ['bug', 'bugs'])) {
                $workItemTypes['bug']++;
            } elseif (in_array($workItemType, ['feature', 'features'])) {
                $workItemTypes['feature']++;
            } elseif (in_array($workItemType, ['requirement', 'requirements', 'user story', 'user stories'])) {
                $workItemTypes['requirement']++;
            }
            
            // Count assigned vs unassigned
            if ($assignedTo) {
                $assignedCount++;
            } else {
                $unassignedCount++;
            }
            
            // Calculate work estimates
            $totalOriginalEstimate += $workItem['originalEstimate'] ?? 0;
            $totalCompletedWork += $workItem['completedWork'] ?? 0;
            $totalRemainingWork += $workItem['remainingWork'] ?? 0;
        }
        
        // Calculate progress percentage
        $progressPercentage = $totalOriginalEstimate > 0 
            ? round(($totalCompletedWork / $totalOriginalEstimate) * 100, 1)
            : 0;
        
        // Calculate velocity (completed work per day - simplified)
        $velocity = $totalCompletedWork > 0 ? round($totalCompletedWork / 10, 1) : 0; // Assuming 10 working days
        
        // Calculate remaining days (simplified)
        $remainingDays = $totalRemainingWork > 0 ? round($totalRemainingWork / 8, 1) : 0; // Assuming 8 hours per day

        $metrics = [
            'totalWorkItems' => $totalWorkItems,
            'completedWorkItems' => $completedWorkItems,
            'inProgressWorkItems' => $inProgressWorkItems,
            'notStartedWorkItems' => $notStartedWorkItems,
            'workItemTypes' => $workItemTypes,
            'teamPerformance' => [
                'assigned' => $assignedCount,
                'unassigned' => $unassignedCount
            ],
            'progressPercentage' => $progressPercentage,
            'remainingDays' => $remainingDays,
            'velocity' => $velocity,
            'totalOriginalEstimate' => $totalOriginalEstimate,
            'totalCompletedWork' => $totalCompletedWork,
            'totalRemainingWork' => $totalRemainingWork
        ];
        
        Log::info("📊 Calculated metrics: " . json_encode($metrics));
        
        return $metrics;
    }

    /**
     * Get work items with filtering options
     */
    public function getWorkItems(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'project_id' => 'required|string',
                'sprint_id' => 'required|string',
                'state_filter' => 'nullable|string',
                'assignee_filter' => 'nullable|string',
                'work_item_type_filter' => 'nullable|string'
            ]);

            // Try to find project by name first, then by ID
            $project = ADOProject::where('name', $request->project_id)
                ->orWhere('id', $request->project_id)
                ->first();

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found',
                    'error' => 'Project with identifier "' . $request->project_id . '" not found in database'
                ], 404);
            }

            $filters = $request->only(['state_filter', 'assignee_filter', 'work_item_type_filter']);
            $workItems = $this->azureDevOpsService->getWorkItemsByIteration(
                $project->id,
                $request->sprint_id,
                $filters
            );
            
            return response()->json([
                'success' => true,
                'data' => $workItems
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching work items: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch work items',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get daily progress tracking data
     */
    public function getDailyProgress(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'project_id' => 'required|string',
                'sprint_id' => 'required|string',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date'
            ]);

            // Try to find project by name first, then by ID
            $project = ADOProject::where('name', $request->project_id)
                ->orWhere('id', $request->project_id)
                ->first();

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found',
                    'error' => 'Project with identifier "' . $request->project_id . '" not found in database'
                ], 404);
            }

            $dailyProgress = $this->azureDevOpsService->getDailyProgress(
                $project->id,
                $request->sprint_id,
                $request->start_date,
                $request->end_date
            );
            
            return response()->json([
                'success' => true,
                'data' => $dailyProgress
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching daily progress: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch daily progress',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Debug endpoint to check database state for a project
     */
    public function debugProject(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'project_id' => 'required|string'
            ]);

            // Try to find project by name first, then by ID
            $project = ADOProject::where('name', $request->project_id)
                ->orWhere('id', $request->project_id)
                ->first();

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found',
                    'error' => 'Project with identifier "' . $request->project_id . '" not found in database'
                ], 404);
            }

            // Get debug information
            $teams = \App\Models\ADOTeam::where('project_id', $project->id)->get();
            $iterations = \App\Models\ADOIteration::where('project_id', $project->id)->get();
            $teamIterations = \App\Models\ADOTeamIteration::where('project_id', $project->id)->get();
            $workItems = \App\Models\ADOWorkItem::where('project_id', $project->id)->get();

            // Sample some work items to see their iteration data
            $sampleWorkItems = \App\Models\ADOWorkItem::where('project_id', $project->id)
                ->select('id', 'title', 'iteration_id', 'iteration_path', 'work_item_type', 'state')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'project' => [
                        'id' => $project->id,
                        'name' => $project->name
                    ],
                    'counts' => [
                        'teams' => $teams->count(),
                        'iterations' => $iterations->count(),
                        'teamIterations' => $teamIterations->count(),
                        'workItems' => $workItems->count()
                    ],
                    'teams' => $teams->map(function($team) {
                        return [
                            'id' => $team->id,
                            'name' => $team->name
                        ];
                    }),
                    'iterations' => $iterations->map(function($iteration) {
                        return [
                            'id' => $iteration->id,
                            'identifier' => $iteration->identifier,
                            'name' => $iteration->name,
                            'path' => $iteration->path
                        ];
                    }),
                    'teamIterations' => $teamIterations->map(function($teamIteration) {
                        return [
                            'iteration_identifier' => $teamIteration->iteration_identifier,
                            'team_id' => $teamIteration->team_id,
                            'team_name' => $teamIteration->team_name,
                            'iteration_name' => $teamIteration->iteration_name,
                            'iteration_path' => $teamIteration->iteration_path
                        ];
                    }),
                    'sampleWorkItems' => $sampleWorkItems
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in debug endpoint: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get debug info',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
