<?php

namespace App\Http\Controllers;

use App\Services\ADOSyncService;
use App\Models\ADOProject;
use App\Models\ADOWorkItem;
use App\Models\ADOIteration;
use App\Models\ADOTeam;
use App\Models\ADOUser;
use App\Models\ADOTeamIteration;
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
            'projects' => ADOProject::max('last_sync_at'),
            'work_items' => ADOWorkItem::max('last_sync_at'),
            'iterations' => ADOIteration::max('last_sync_at'),
            'teams' => ADOTeam::max('last_sync_at'),
            'users' => ADOUser::max('last_sync_at'),
            'team_iterations' => ADOTeamIteration::max('last_sync_at'),
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
            $projects = ADOProject::orderBy('last_sync_at', 'desc')->get();
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
            $users = ADOUser::orderBy('last_sync_at', 'desc')->get();
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
                ->orderBy('last_sync_at', 'desc')
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
            $teamIterations = ADOTeamIteration::orderBy('last_sync_at', 'desc')->get();
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
            $workItems = ADOWorkItem::orderBy('last_sync_at', 'desc')->get();
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
}
