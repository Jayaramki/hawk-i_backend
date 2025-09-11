<?php

namespace App\Services;

use App\Models\ADOUser;
use App\Models\ADOProject;
use App\Models\ADOTeam;
use App\Models\ADOIteration;
use App\Models\ADOTeamIteration;
use App\Models\ADOWorkItem;
use App\Models\SyncHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use DateTime;

class ADOSyncService
{
    private AzureDevOpsService $adoService;
    private array $syncStats = [
        'inserted' => 0,
        'updated' => 0,
        'errors' => 0,
    ];

    public function __construct()
    {
        $this->adoService = new AzureDevOpsService();
    }

    /**
     * Sync all ADO resources
     */
    public function syncAll(): array
    {
        $startTime = microtime(true);
        $results = [];

        try {
            Log::info('🚀 Starting Azure DevOps sync engine...');
            Log::info('📋 Sync order follows dependency chain: Projects → Users → Teams → Iterations → Team Iterations → Work Items');
            
            // Step 1: Sync projects first (foundation - all other entities depend on projects)
            Log::info('1️⃣ Syncing Projects...');
            $results['projects'] = $this->syncProjects();
            
            // Step 2: Sync users (needed for work item foreign key references)
            Log::info('2️⃣ Syncing Users...');
            $results['users'] = $this->syncUsers();
            
            // Step 3: Sync teams (depends on projects existing)
            Log::info('3️⃣ Syncing Teams...');
            $results['teams'] = $this->syncTeams();
            
            // Step 4: Sync iterations (depends on projects existing)
            Log::info('4️⃣ Syncing Iterations...');
            $results['iterations'] = $this->syncIterations();
            
            // Step 5: Sync team iterations (depends on BOTH teams AND iterations existing)
            Log::info('5️⃣ Syncing Team Iterations...');
            $results['team_iterations'] = $this->syncTeamIterations();
            
            // Step 6: Sync work items (depends on projects, users, teams, iterations for foreign keys)
            Log::info('6️⃣ Syncing Work Items...');
            $results['work_items'] = $this->syncWorkItems();
            
            $duration = round(microtime(true) - $startTime, 2);
            
            Log::info("✅ ADO sync completed in {$duration} seconds", $results);
            
            return [
                'success' => true,
                'duration' => $duration,
                'results' => $results,
                'summary' => $this->getSyncSummary($results),
            ];
            
        } catch (Exception $e) {
            Log::error('❌ ADO sync failed', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'duration' => round(microtime(true) - $startTime, 2),
            ];
        }
    }

    /**
     * Sync projects
     */
    public function syncProjects(): array
    {
        try {
            Log::info('🔄 Starting ADO sync for Projects');
            
            $projects = $this->adoService->getProjects();
            Log::info('📊 Projects fetched from API:', [
                'count' => count($projects),
                'first_project_sample' => !empty($projects) ? $projects[0] : null
            ]);
            
            $inserted = 0;
            $updated = 0;
            
            foreach ($projects as $projectData) {
                try {
                    Log::info("🔄 Processing project: {$projectData['name']} (ID: {$projectData['id']})", [
                        'project_structure' => array_keys($projectData),
                        'project_data' => $projectData
                    ]);
                    
                    // Check if project already exists to preserve is_active setting
                    $existingProject = ADOProject::find($projectData['id']);
                    
                    $updateData = [
                        'name' => $projectData['name'],
                        'description' => $projectData['description'] ?? null,
                        'url' => $projectData['url'],
                        'state' => $projectData['state'],
                        'revision' => $projectData['revision'],
                        'visibility' => $projectData['visibility'],
                        'default_team_id' => $projectData['defaultTeam']['id'] ?? null,
                    ];
                    
                    // Only set is_active for new projects (preserve existing value for updates)
                    if (!$existingProject) {
                        $updateData['is_active'] = true; // Default to active for new projects
                    }
                    
                    $project = ADOProject::updateOrCreate(
                        ['id' => $projectData['id']],
                        $updateData
                    );
                    
                    if ($project->wasRecentlyCreated) {
                        $inserted++;
                        Log::info("✅ Inserted new project: {$projectData['name']}");
                    } else {
                        $updated++;
                        Log::info("🔄 Updated existing project: {$projectData['name']}");
                    }
                    
                } catch (Exception $e) {
                    Log::error("❌ Error syncing project {$projectData['id']}: " . $e->getMessage(), [
                        'project_data' => $projectData,
                        'exception' => $e->getTraceAsString()
                    ]);
                    $this->syncStats['errors']++;
                }
            }
            
            Log::info("✅ Projects sync completed: Inserted: {$inserted}, Updated: {$updated}");
            
            // Update sync history for projects (global sync, no specific project ID)
            SyncHistory::updateSyncHistory(
                SyncHistory::TABLE_PROJECTS,
                null, // Projects sync is global, not project-specific
                SyncHistory::SYNC_TYPE_FULL,
                SyncHistory::STATUS_SUCCESS,
                $inserted + $updated
            );
            
            return [
                'inserted' => $inserted,
                'updated' => $updated,
                'total' => count($projects),
            ];
            
        } catch (Exception $e) {
            Log::error('❌ Projects sync failed: ' . $e->getMessage());
            
            // Record failed sync in history
            SyncHistory::updateSyncHistory(
                SyncHistory::TABLE_PROJECTS,
                null,
                SyncHistory::SYNC_TYPE_FULL,
                SyncHistory::STATUS_FAILED,
                0,
                $e->getMessage()
            );
            
            throw $e;
        }
    }

    /**
     * Sync users
     */
    public function syncUsers(string $subjectTypes = null, string $scopeDescriptor = null): array
    {
        try {
            Log::info('🔄 Starting ADO sync for Users');
            
            $users = $this->adoService->getUsers($subjectTypes, $scopeDescriptor);
            $inserted = 0;
            $updated = 0;
            
            foreach ($users as $userData) {
                try {
                    $user = ADOUser::updateOrCreate(
                        ['id' => $userData['descriptor']],
                        [
                            'display_name' => $userData['displayName'],
                            'mail_address' => $userData['mailAddress'] ?? null,
                            'origin' => $userData['origin'],
                            'origin_id' => $userData['originId'] ?? null,
                            'subject_kind' => $userData['subjectKind'],
                            'url' => $userData['url'],
                            'meta_type' => $userData['metaType'] ?? null,
                            'directory_alias' => $userData['directoryAlias'] ?? null,
                            'domain' => $userData['domain'] ?? null,
                            'principal_name' => $userData['principalName'] ?? null,
                            'is_active' => true,
                        ]
                    );
                    
                    if ($user->wasRecentlyCreated) {
                        $inserted++;
                    } else {
                        $updated++;
                    }
                    
                } catch (Exception $e) {
                    Log::error("Error syncing user {$userData['descriptor']}: " . $e->getMessage());
                    $this->syncStats['errors']++;
                }
            }
            
            Log::info("✅ Users sync completed: Inserted: {$inserted}, Updated: {$updated}");
            
            // Update sync history for users (global sync, no specific project ID)
            SyncHistory::updateSyncHistory(
                SyncHistory::TABLE_USERS,
                null, // Users sync is global, not project-specific
                SyncHistory::SYNC_TYPE_FULL,
                SyncHistory::STATUS_SUCCESS,
                $inserted + $updated
            );
            
            return [
                'inserted' => $inserted,
                'updated' => $updated,
                'total' => count($users),
            ];
            
        } catch (Exception $e) {
            Log::error('❌ Users sync failed: ' . $e->getMessage());
            
            // Record failed sync in history
            SyncHistory::updateSyncHistory(
                SyncHistory::TABLE_USERS,
                null,
                SyncHistory::SYNC_TYPE_FULL,
                SyncHistory::STATUS_FAILED,
                0,
                $e->getMessage()
            );
            
            throw $e;
        }
    }

    /**
     * Sync teams
     */
    public function syncTeams(): array
    {
        try {
            Log::info('🔄 Starting ADO sync for Teams');
            
            // Get active project IDs for filtering
            $activeProjectIds = ADOProject::active()->pluck('id')->toArray();
            Log::info('📋 Active projects for team sync:', [
                'count' => count($activeProjectIds),
                'project_ids' => $activeProjectIds
            ]);
            
            // Get all teams from Azure DevOps Core API
            $teams = $this->adoService->getTeams();
            $totalInserted = 0;
            $totalUpdated = 0;
            $totalSkipped = 0;
            
            foreach ($teams as $teamData) {
                try {
                    // Extract project ID from URL if available
                    $projectId = $this->extractProjectIdFromUrl($teamData['url'] ?? '');
                    
                    // Skip teams from inactive projects
                    if ($projectId && !in_array($projectId, $activeProjectIds)) {
                        $totalSkipped++;
                        Log::debug("⏭️ Skipping team '{$teamData['name']}' from inactive project: {$projectId}");
                        continue;
                    }
                    
                    // Skip teams without a valid project ID (orphaned teams)
                    if (!$projectId) {
                        $totalSkipped++;
                        Log::debug("⏭️ Skipping team '{$teamData['name']}' without valid project ID");
                        continue;
                    }
                    
                    $team = ADOTeam::updateOrCreate(
                        ['id' => $teamData['id']],
                        [
                            'name' => $teamData['name'],
                            'description' => $teamData['description'] ?? null,
                            'url' => $teamData['url'],
                            'identity_url' => $teamData['identityUrl'] ?? null,
                            'project_id' => $projectId,
                            'project_name' => $this->getProjectNameById($projectId),
                            'identity_id' => $teamData['identity']['id'] ?? null,
                        ]
                    );
                    
                    if ($team->wasRecentlyCreated) {
                        $totalInserted++;
                    } else {
                        $totalUpdated++;
                    }
                    
                } catch (Exception $e) {
                    Log::error("Error syncing team {$teamData['id']}: " . $e->getMessage());
                    $this->syncStats['errors']++;
                }
            }
            
            Log::info("✅ Teams sync completed: Inserted: {$totalInserted}, Updated: {$totalUpdated}, Skipped: {$totalSkipped}");
            
            // Update sync history for teams (global sync, no specific project ID)
            SyncHistory::updateSyncHistory(
                SyncHistory::TABLE_TEAMS,
                null, // Teams sync is global across projects
                SyncHistory::SYNC_TYPE_FULL,
                SyncHistory::STATUS_SUCCESS,
                $totalInserted + $totalUpdated
            );
            
            return [
                'inserted' => $totalInserted,
                'updated' => $totalUpdated,
                'skipped' => $totalSkipped,
            ];
            
        } catch (Exception $e) {
            Log::error('❌ Teams sync failed: ' . $e->getMessage());
            
            // Record failed sync in history
            SyncHistory::updateSyncHistory(
                SyncHistory::TABLE_TEAMS,
                null,
                SyncHistory::SYNC_TYPE_FULL,
                SyncHistory::STATUS_FAILED,
                0,
                $e->getMessage()
            );
            
            throw $e;
        }
    }

    /**
     * Extract project ID from Azure DevOps team URL
     */
    private function extractProjectIdFromUrl(string $url): ?string
    {
        if (empty($url)) {
            return null;
        }
        
        try {
            // URL format: https://dev.azure.com/fabrikam/_apis/projects/eb6e4656-77fc-42a1-9181-4c6d8e9da5d1/teams/66df9be7-3586-467b-9c5f-425b29afedfd
            $parts = explode('/', $url);
            for ($i = 0; $i < count($parts); $i++) {
                if ($parts[$i] === 'projects' && isset($parts[$i + 1])) {
                    return $parts[$i + 1];
                }
            }
            return null;
        } catch (Exception $e) {
            Log::warning("Could not extract project ID from URL '{$url}': " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get project name by ID
     */
    private function getProjectNameById(?string $projectId): ?string
    {
        if (!$projectId) {
            return null;
        }
        
        try {
            $project = ADOProject::find($projectId);
            return $project ? $project->name : null;
        } catch (Exception $e) {
            Log::warning("Could not get project name for ID '{$projectId}': " . $e->getMessage());
            return null;
        }
    }

    /**
     * Sync iterations using Classification Nodes API (comprehensive sync like Python script)
     */
    public function syncIterations(int $depth = 10): array
    {
        try {
            Log::info('🔄 Starting comprehensive ADO sync for Iterations');
            
            $allProjects = ADOProject::all();
            $projects = ADOProject::processable()->get();
            $totalInserted = 0;
            $totalUpdated = 0;
            
            Log::info("📊 Project filtering results:", [
                'total_projects_in_db' => $allProjects->count(),
                'active_projects' => $projects->count(),
                'inactive_projects' => $allProjects->where('is_active', false)->count(),
                'active_project_names' => $projects->pluck('name')->toArray(),
                'inactive_project_names' => $allProjects->where('is_active', false)->pluck('name')->toArray()
            ]);
            
            if ($projects->isEmpty()) {
                Log::warning('No active projects found for processing. Please check is_active flags in ado_projects table.');
                return ['inserted' => 0, 'updated' => 0, 'total' => 0];
            }
            
            Log::info("🎯 Processing {$projects->count()} active projects for iterations sync");
            
            foreach ($projects as $project) {
                try {
                    Log::info("Processing project: {$project->name} (ID: {$project->id})");
                    
                    // Step 1: Fetch the full iteration tree via Classification Nodes API
                    $classificationNodes = $this->adoService->getClassificationNodes($project->id, 'Iterations', $depth);
                    
                    if (empty($classificationNodes)) {
                        Log::warning("No classification nodes found for project {$project->name}");
                        continue;
                    }
                    
                    // Step 2: Extract iterations from classification nodes tree
                    $iterations = $this->extractIterationsFromClassificationNodes($classificationNodes, $project->id);
                    
                    Log::info("Successfully extracted " . count($iterations) . " iterations from classification nodes for project {$project->name}");
                    
                    if (!empty($iterations)) {
                        // Step 3: Upsert iterations to database
                        $inserted = 0;
                        $updated = 0;
                        
                        foreach ($iterations as $iterationData) {
                            try {
                                if (!$this->validateIterationData($iterationData)) {
                                    continue;
                                }
                                
                                $iteration = ADOIteration::updateOrCreate(
                                    ['id' => $iterationData['identifier']],
                                    [
                                        'name' => $iterationData['name'],
                                        'path' => $iterationData['path'],
                                        'project_id' => $project->id,
                                        'project_name' => $project->name,
                                        'start_date' => $iterationData['start_date'] ?? null,
                                        'end_date' => $iterationData['finish_date'] ?? null, // Map finish_date to end_date (database column)
                                        'time_frame' => $iterationData['time_frame'] ?? null,
                                        'attributes' => $iterationData['attributes'] ?? [],
                                    ]
                                );
                                
                                if ($iteration->wasRecentlyCreated) {
                                    $inserted++;
                                } else {
                                    $updated++;
                                }
                                
                            } catch (Exception $e) {
                                Log::error("Error processing iteration {$iterationData['identifier']}: " . $e->getMessage());
                                $this->syncStats['errors']++;
                            }
                        }
                        
                        $totalInserted += $inserted;
                        $totalUpdated += $updated;
                        
                        Log::info("Project iterations complete! Inserted: {$inserted}, Updated: {$updated}");
                    }
                    
                } catch (Exception $e) {
                    Log::error("Error syncing iterations for project {$project->id}: " . $e->getMessage());
                }
            }
            
            Log::info("✅ Iterations sync completed: Inserted: {$totalInserted}, Updated: {$totalUpdated}");
            
            // Update sync history for iterations (global sync, no specific project ID)
            SyncHistory::updateSyncHistory(
                SyncHistory::TABLE_ITERATIONS,
                null, // Iterations sync is global across projects
                SyncHistory::SYNC_TYPE_FULL,
                SyncHistory::STATUS_SUCCESS,
                $totalInserted + $totalUpdated
            );
            
            return [
                'inserted' => $totalInserted,
                'updated' => $totalUpdated,
                'total' => $totalInserted + $totalUpdated,
            ];
            
        } catch (Exception $e) {
            Log::error('❌ Iterations sync failed: ' . $e->getMessage());
            
            // Record failed sync in history
            SyncHistory::updateSyncHistory(
                SyncHistory::TABLE_ITERATIONS,
                null,
                SyncHistory::SYNC_TYPE_FULL,
                SyncHistory::STATUS_FAILED,
                0,
                $e->getMessage()
            );
            
            throw $e;
        }
    }

    /**
     * Sync team iterations (comprehensive sync like Python script)
     */
    public function syncTeamIterations(): array
    {
        try {
            Log::info('🔄 Starting comprehensive ADO sync for Team Iterations');
            
            $projects = ADOProject::processable()->get();
            $totalInserted = 0;
            $totalUpdated = 0;
            
            if ($projects->isEmpty()) {
                Log::warning('No projects found in database. Please run projects sync first.');
                return ['inserted' => 0, 'updated' => 0, 'total' => 0];
            }
            
            foreach ($projects as $project) {
                try {
                    Log::info("Processing team iterations for project: {$project->name} (ID: {$project->id})");
                    
                    // Get teams for this project from database
                    $teams = ADOTeam::where('project_id', $project->id)->get();
                    
                    if ($teams->isEmpty()) {
                        Log::warning("No teams found in database for project {$project->name}");
                        continue;
                    }
                    
                    Log::info("Found {$teams->count()} teams in database for project {$project->name}");
                    
                    foreach ($teams as $team) {
                        try {
                            Log::info("Fetching iterations for team: {$team->name}");
                            
                            $teamIterations = $this->adoService->getTeamIterationsFromAPI($project->id, $team->id);
                            
                            // Transform team iterations to our format
                            foreach ($teamIterations as $iteration) {
                                try {
                                    // Extract dates from attributes
                                    $startDate = null;
                                    $endDate = null;
                                    
                                    if (isset($iteration['attributes']['startDate'])) {
                                        try {
                                            $startDate = \Carbon\Carbon::parse($iteration['attributes']['startDate'])->format('Y-m-d');
                                        } catch (\Exception $e) {
                                            Log::warning("Failed to parse startDate for iteration {$iteration['id']}: " . $e->getMessage());
                                        }
                                    }
                                    
                                    if (isset($iteration['attributes']['finishDate'])) {
                                        try {
                                            $endDate = \Carbon\Carbon::parse($iteration['attributes']['finishDate'])->format('Y-m-d');
                                        } catch (\Exception $e) {
                                            Log::warning("Failed to parse finishDate for iteration {$iteration['id']}: " . $e->getMessage());
                                        }
                                    }
                                    
                                    $teamIterationData = [
                                        'iteration_identifier' => $iteration['id'], // Team iterations use 'id' not 'identifier'
                                        'team_id' => $team->id,
                                        'team_name' => $team->name,
                                        'timeframe' => $iteration['attributes']['timeFrame'] ?? null,
                                        'assigned' => true, // If it's in the team's iterations, it's assigned
                                        'iteration_name' => $iteration['name'] ?? null,
                                        'iteration_path' => $iteration['path'] ?? null,
                                        'start_date' => $startDate,
                                        'end_date' => $endDate,
                                        'project_id' => $project->id,
                                    ];
                                    
                                    if (!$this->validateTeamIterationData($teamIterationData)) {
                                        continue;
                                    }
                                    
                                    // Generate composite ID for team iteration
                                    $compositeId = $teamIterationData['team_id'] . '-' . $teamIterationData['iteration_identifier'];
                                    
                                    $teamIteration = ADOTeamIteration::updateOrCreate(
                                        ['id' => $compositeId],
                                        [
                                            'iteration_identifier' => $teamIterationData['iteration_identifier'],
                                            'team_id' => $teamIterationData['team_id'],
                                            'team_name' => $teamIterationData['team_name'],
                                            'timeframe' => $teamIterationData['timeframe'],
                                            'assigned' => $teamIterationData['assigned'],
                                            'iteration_name' => $teamIterationData['iteration_name'],
                                            'iteration_path' => $teamIterationData['iteration_path'],
                                            'start_date' => $teamIterationData['start_date'],
                                            'end_date' => $teamIterationData['end_date'],
                                            'project_id' => $teamIterationData['project_id'],
                                        ]
                                    );
                                    
                                    if ($teamIteration->wasRecentlyCreated) {
                                        $totalInserted++;
                                    } else {
                                        $totalUpdated++;
                                    }
                                    
                                } catch (Exception $e) {
                                    Log::error("Error processing team iteration {$iteration['id']}: " . $e->getMessage());
                                    $this->syncStats['errors']++;
                                }
                            }
                            
                            // Rate limiting - small delay between requests
                            usleep(50000); // 50ms delay
                            
                        } catch (Exception $e) {
                            Log::warning("Error fetching iterations for team {$team->name}: " . $e->getMessage());
                            continue;
                        }
                    }
                    
                } catch (Exception $e) {
                    Log::error("Error syncing team iterations for project {$project->id}: " . $e->getMessage());
                }
            }
            
            Log::info("✅ Team Iterations sync completed: Inserted: {$totalInserted}, Updated: {$totalUpdated}");
            
            // Update sync history for team iterations (global sync, no specific project ID)
            SyncHistory::updateSyncHistory(
                SyncHistory::TABLE_TEAM_ITERATIONS,
                null, // Team iterations sync is global across projects
                SyncHistory::SYNC_TYPE_FULL,
                SyncHistory::STATUS_SUCCESS,
                $totalInserted + $totalUpdated
            );
            
            return [
                'inserted' => $totalInserted,
                'updated' => $totalUpdated,
                'total' => $totalInserted + $totalUpdated,
            ];
            
        } catch (Exception $e) {
            Log::error('❌ Team Iterations sync failed: ' . $e->getMessage());
            
            // Record failed sync in history
            SyncHistory::updateSyncHistory(
                SyncHistory::TABLE_TEAM_ITERATIONS,
                null,
                SyncHistory::SYNC_TYPE_FULL,
                SyncHistory::STATUS_FAILED,
                0,
                $e->getMessage()
            );
            
            throw $e;
        }
    }

    /**
     * Sync work items with incremental sync support
     */
    public function syncWorkItems(string $projectId = null, ?DateTime $lastSyncTime = null, bool $firstBatchOnly = false): array
    {
        try {
            if ($firstBatchOnly) {
                Log::info('🔄 Starting ADO sync for Work Items (First Batch Only)');
            } else if ($lastSyncTime) {
                Log::info('🔄 Starting ADO sync for Work Items (Incremental)');
                Log::info("📅 Incremental sync: fetching work items changed since {$lastSyncTime->format('Y-m-d H:i:s')}");
            } else {
                Log::info('🔄 Starting ADO sync for Work Items (Full)');
            }
            
            $allProjects = ADOProject::all();
            $projects = $projectId
                ? ADOProject::where('id', $projectId)->processable()->get()
                : ADOProject::processable()->get();
            
            Log::info("📊 Work Items - Project filtering results:", [
                'total_projects_in_db' => $allProjects->count(),
                'active_projects' => $projects->count(),
                'inactive_projects' => $allProjects->where('is_active', false)->count(),
                'active_project_names' => $projects->pluck('name')->toArray(),
                'inactive_project_names' => $allProjects->where('is_active', false)->pluck('name')->toArray()
            ]);
            
            if ($projects->isEmpty()) {
                Log::warning('⚠️ No active projects found for work items processing. Please check is_active flags in ado_projects table.');
                return [
                    'inserted' => 0,
                    'updated' => 0,
                    'total' => 0,
                ];
            }
            
            $totalInserted = 0;
            $totalUpdated = 0;
            $projectCount = 0;
             
            foreach ($projects as $project) {
                $projectCount++;
                try {
                    $progressMsg = "🔄 Processing project {$projectCount}/{$projects->count()}: {$project->name}";
                    Log::info($progressMsg);
                    echo $progressMsg . "\n";
                    
                    // Get last sync time for this specific project if not provided
                    $projectLastSyncTime = $lastSyncTime;
                    if (!$projectLastSyncTime) {
                        $projectLastSyncTime = $this->getLastSyncTime(SyncHistory::TABLE_WORK_ITEMS, $project->id);
                        if ($projectLastSyncTime) {
                            $syncTimeMsg = "📅 Using last sync time: {$projectLastSyncTime->format('Y-m-d H:i:s')}";
                            Log::info($syncTimeMsg);
                            echo $syncTimeMsg . "\n";
                        }
                    }
                    
                                        // Get active teams and iterations for this project for filtering
                    $activeTeams = ADOTeam::where('project_id', $project->id)->active()->pluck('id')->toArray();
                    $activeIterations = ADOIteration::where('project_id', $project->id)->active()->pluck('id')->toArray();
                    $activeTeamIterations = ADOTeamIteration::where('project_id', $project->id)->active()->pluck('id')->toArray();
                    
                    Log::info("📊 Active entities for project {$project->name}:", [
                        'active_teams' => count($activeTeams),
                        'active_iterations' => count($activeIterations),
                        'active_team_iterations' => count($activeTeamIterations)
                    ]);
                    
                    // Get work items and process them directly into database (handled in AzureDevOpsService)
                    $workItems = $this->adoService->getWorkItems(
                        $project->name, 
                        $projectLastSyncTime, 
                        null, 
                        1000, 
                        $firstBatchOnly,
                        $activeTeams,
                        $activeIterations,
                        $activeTeamIterations
                    );
                    
                    // Count work items processed
                    $workItemCount = count($workItems);
                    $totalInserted += $workItemCount; // This is a rough count - actual DB counts are in AzureDevOpsService
                    
                    $completeMsg = "✅ Project {$project->name} work items processed: {$workItemCount} items";
                    Log::info($completeMsg);
                    echo $completeMsg . "\n";
                    
                } catch (Exception $e) {
                    $errorMsg = "❌ Error syncing work items for project {$project->id}: " . $e->getMessage();
                    Log::error($errorMsg);
                    echo $errorMsg . "\n";
                }
            }
            
            Log::info("✅ Work Items sync completed: Inserted: {$totalInserted}, Updated: {$totalUpdated}");
            
            // Update sync history for each project
            foreach ($projects as $project) {
                SyncHistory::updateSyncHistory(
                    SyncHistory::TABLE_WORK_ITEMS,
                    $project->id,
                    $firstBatchOnly ? SyncHistory::SYNC_TYPE_INCREMENTAL : SyncHistory::SYNC_TYPE_FULL,
                    SyncHistory::STATUS_SUCCESS,
                    $totalInserted + $totalUpdated
                );
            }
            
            return [
                'inserted' => $totalInserted,
                'updated' => $totalUpdated,
                'total' => $totalInserted + $totalUpdated,
            ];
            
        } catch (Exception $e) {
            Log::error('❌ Work Items sync failed: ' . $e->getMessage());
            
            // Record failed sync in history
            $projects = ADOProject::processable()->get();
            foreach ($projects as $project) {
                SyncHistory::updateSyncHistory(
                    SyncHistory::TABLE_WORK_ITEMS,
                    $project->id,
                    SyncHistory::SYNC_TYPE_FULL,
                    SyncHistory::STATUS_FAILED,
                    0,
                    $e->getMessage()
                );
            }
            
            throw $e;
        }
    }

    /**
     * Get sync summary
     */
    private function getSyncSummary(array $results): array
    {
        $totalInserted = 0;
        $totalUpdated = 0;
        $successfulSyncs = 0;
        $failedSyncs = 0;
        
        foreach ($results as $resource => $result) {
            if (isset($result['inserted']) && isset($result['updated'])) {
                $totalInserted += $result['inserted'];
                $totalUpdated += $result['updated'];
                $successfulSyncs++;
            } else {
                $failedSyncs++;
            }
        }
        
        return [
            'successful_syncs' => $successfulSyncs,
            'failed_syncs' => $failedSyncs,
            'total_inserted' => $totalInserted,
            'total_updated' => $totalUpdated,
        ];
    }

    /**
     * Extract iterations from classification nodes tree (like Python script)
     */
    private function extractIterationsFromClassificationNodes(array $node, string $projectId, string $pathPrefix = ''): array
    {
        $iterations = [];
        
        // Extract current node if it has iteration data
        if (isset($node['identifier']) && isset($node['name'])) {
            $iterationData = [
                'id' => $node['id'] ?? null,
                'identifier' => $node['identifier'],
                'name' => $node['name'],
                'path' => $pathPrefix ? "{$pathPrefix}\\{$node['name']}" : $node['name'],
                'project_id' => $projectId
            ];
            
            // Extract dates from attributes
            if (isset($node['attributes'])) {
                $attributes = $node['attributes'];
                if (isset($attributes['startDate'])) {
                    try {
                        $iterationData['start_date'] = \Carbon\Carbon::parse($attributes['startDate'])->toDateString();
                    } catch (Exception $e) {
                        $iterationData['start_date'] = null;
                    }
                }
                
                if (isset($attributes['finishDate'])) {
                    try {
                        $iterationData['finish_date'] = \Carbon\Carbon::parse($attributes['finishDate'])->toDateString(); // Will be mapped to end_date
                    } catch (Exception $e) {
                        $iterationData['finish_date'] = null;
                    }
                }
                
                $iterationData['time_frame'] = $attributes['timeFrame'] ?? null;
                $iterationData['attributes'] = $attributes;
            }
            
            $iterations[] = $iterationData;
        }
        
        // Recursively process children
        if (isset($node['children'])) {
            $currentPath = $pathPrefix ? "{$pathPrefix}\\{$node['name']}" : $node['name'];
            foreach ($node['children'] as $child) {
                $childIterations = $this->extractIterationsFromClassificationNodes($child, $projectId, $currentPath);
                $iterations = array_merge($iterations, $childIterations);
            }
        }
        
        return $iterations;
    }

    /**
     * Validate iteration data before processing
     */
    private function validateIterationData(array $iterationData): bool
    {
        $requiredFields = ['id', 'identifier', 'name'];
        
        foreach ($requiredFields as $field) {
            if (empty($iterationData[$field])) {
                Log::warning("Warning: Iteration missing required field '{$field}': " . ($iterationData['identifier'] ?? 'unknown'));
                return false;
            }
        }
        
        return true;
    }

    /**
     * Validate team iteration data before processing
     */
    private function validateTeamIterationData(array $teamIterationData): bool
    {
        $requiredFields = ['iteration_identifier', 'team_id', 'team_name'];
        
        foreach ($requiredFields as $field) {
            if (empty($teamIterationData[$field])) {
                Log::warning("Warning: Team iteration missing required field '{$field}': " . ($teamIterationData['iteration_identifier'] ?? 'unknown'));
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get last sync time for a specific table and project
     */
    private function getLastSyncTime(string $tableName, ?string $projectId = null): ?\DateTime
    {
        return SyncHistory::getLastSyncTime($tableName, $projectId);
    }

    /**
     * Clear all ADO cache
     */
    public function clearCache(): void
    {
        $this->adoService->clearAllCache();
        Log::info('🧹 ADO cache cleared');
    }
}
