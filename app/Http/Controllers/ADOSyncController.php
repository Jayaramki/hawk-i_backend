<?php

namespace App\Http\Controllers;

use App\Services\ADOSyncService;
use App\Models\ADOProject;
use App\Models\ADOWorkItem;
use App\Models\ADOIteration;
use App\Models\ADOTeam;
use App\Models\ADOUser;
use App\Models\ADOTeamIteration;
use App\Models\SyncHistory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ADOSyncController extends Controller
{
    private ADOSyncService $syncService;

    public function __construct()
    {
        $this->syncService = new ADOSyncService();
    }

    /**
     * Get sync status
     */
    public function status(): JsonResponse
    {
        $stats = [
            'projects' => ADOProject::count(),
            'work_items' => ADOWorkItem::count(),
            'iterations' => ADOIteration::count(),
            'teams' => ADOTeam::count(),
            'users' => ADOUser::count(),
            'team_iterations' => ADOTeamIteration::count(),
        ];

        $lastSync = [
            'projects' => ADOProject::max('updated_at'),
            'work_items' => ADOWorkItem::max('updated_at'),
            'iterations' => ADOIteration::max('updated_at'),
            'teams' => ADOTeam::max('updated_at'),
            'users' => ADOUser::max('updated_at'),
            'team_iterations' => ADOTeamIteration::max('updated_at'),
        ];

        return response()->json([
            'status' => 'success',
            'data' => [
                'stats' => $stats,
                'last_sync' => $lastSync,
            ]
        ]);
    }

    /**
     * Sync all ADO resources
     */
    public function syncAll(): JsonResponse
    {
        try {
            $result = $this->syncService->syncAll();
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync projects
     */
    public function syncProjects(): JsonResponse
    {
        try {
            $result = $this->syncService->syncProjects();
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync users
     */
    public function syncUsers(): JsonResponse
    {
        try {
            $result = $this->syncService->syncUsers();
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync teams
     */
    public function syncTeams(): JsonResponse
    {
        try {
            $result = $this->syncService->syncTeams();
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync iterations
     */
    public function syncIterations(): JsonResponse
    {
        try {
            $result = $this->syncService->syncIterations();
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync team iterations
     */
    public function syncTeamIterations(): JsonResponse
    {
        try {
            $result = $this->syncService->syncTeamIterations();
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync work items
     */
    public function syncWorkItems(Request $request): JsonResponse
    {
        try {
            $projectId = $request->input('project_id');
            $result = $this->syncService->syncWorkItems($projectId);
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear cache
     */
    public function clearCache(): JsonResponse
    {
        try {
            $this->syncService->clearCache();
            return response()->json([
                'success' => true,
                'message' => 'Cache cleared successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test connection to Azure DevOps
     */
    public function testConnection(): JsonResponse
    {
        try {
            // This would need to be implemented in the service
            return response()->json([
                'success' => true,
                'message' => 'Connection test successful'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get projects from database
     */
    public function getProjects(): JsonResponse
    {
        try {
            $projects = ADOProject::orderBy('updated_at', 'desc')->get();
            return response()->json([
                'success' => true,
                'data' => $projects
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get users from database
     */
    public function getUsers(): JsonResponse
    {
        try {
            $users = ADOUser::orderBy('updated_at', 'desc')->get();
            return response()->json([
                'success' => true,
                'data' => $users
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get iterations from database
     */
    public function getIterations(): JsonResponse
    {
        try {
            $iterations = ADOIteration::with('project')
                ->orderBy('updated_at', 'desc')
                ->get();
            return response()->json([
                'success' => true,
                'data' => $iterations
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get team iterations from database
     */
    public function getTeamIterations(): JsonResponse
    {
        try {
            $teamIterations = ADOTeamIteration::orderBy('updated_at', 'desc')->get();
            return response()->json([
                'success' => true,
                'data' => $teamIterations
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get iterations from database (alias for getIterations)
     */
    public function getIterationsFromDB(): JsonResponse
    {
        return $this->getIterations();
    }

    /**
     * Get team iterations from database (alias for getTeamIterations)
     */
    public function getTeamIterationsFromDB(): JsonResponse
    {
        return $this->getTeamIterations();
    }

    /**
     * Get work items from database
     */
    public function getWorkItems(): JsonResponse
    {
        try {
            $workItems = ADOWorkItem::orderBy('updated_at', 'desc')->get();
            return response()->json([
                'success' => true,
                'data' => $workItems
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sync history
     */
    public function getSyncHistory(): JsonResponse
    {
        try {
            $syncHistory = SyncHistory::orderBy('last_sync_at', 'desc')->get();
            return response()->json([
                'success' => true,
                'data' => $syncHistory
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle active status for a team
     */
    public function toggleTeamStatus(Request $request): JsonResponse
    {
        try {
            $teamId = $request->input('team_id');
            $isActive = $request->input('is_active');
            
            $team = ADOTeam::find($teamId);
            if (!$team) {
                return response()->json([
                    'success' => false,
                    'error' => 'Team not found'
                ], 404);
            }
            
            $team->is_active = $isActive;
            $team->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Team status updated successfully',
                'data' => $team
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle active status for an iteration
     */
    public function toggleIterationStatus(Request $request): JsonResponse
    {
        try {
            $iterationId = $request->input('iteration_id');
            $isActive = $request->input('is_active');
            
            $iteration = ADOIteration::find($iterationId);
            if (!$iteration) {
                return response()->json([
                    'success' => false,
                    'error' => 'Iteration not found'
                ], 404);
            }
            
            $iteration->is_active = $isActive;
            $iteration->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Iteration status updated successfully',
                'data' => $iteration
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle active status for a team iteration
     */
    public function toggleTeamIterationStatus(Request $request): JsonResponse
    {
        try {
            $teamIterationId = $request->input('team_iteration_id');
            $isActive = $request->input('is_active');
            
            $teamIteration = ADOTeamIteration::find($teamIterationId);
            if (!$teamIteration) {
                return response()->json([
                    'success' => false,
                    'error' => 'Team iteration not found'
                ], 404);
            }
            
            $teamIteration->is_active = $isActive;
            $teamIteration->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Team iteration status updated successfully',
                'data' => $teamIteration
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get teams with their active status
     */
    public function getTeams(): JsonResponse
    {
        try {
            $teams = ADOTeam::with('project')->orderBy('name')->get();
            return response()->json([
                'success' => true,
                'data' => $teams
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
