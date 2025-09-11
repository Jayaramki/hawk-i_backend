<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;
use DateTime;

class AzureDevOpsService
{
    private string $baseUrl;
    private string $pat;
    private string $organization;
    private ?string $project;
    private array $headers;

    public function __construct()
    {
        $this->organization = config('services.azure_devops.organization');
        $this->project = config('services.azure_devops.project');
        $this->pat = config('services.azure_devops.pat');
        $this->baseUrl = config('services.azure_devops.base_url') . "/{$this->organization}";
        
        $this->headers = [
            'Authorization' => 'Basic ' . base64_encode(":{$this->pat}"),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        
        // Debug: Log the base URL
        Log::info("Azure DevOps Service initialized with base URL: {$this->baseUrl}");
    }

    /**
     * Make a GET request to Azure DevOps API with rate limiting and retry logic
     */
    private function makeRequest(string $endpoint, array $params = [], int $maxRetries = 3): array
    {
        $url = $this->baseUrl . $endpoint;
        
        // Debug: Log the actual URL being requested
        Log::info("Making GET request to URL: {$url}");
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::withHeaders($this->headers)
                    ->timeout(30)
                    ->get($url, $params);

                if ($response->successful()) {
                    return $response->json();
                }

                // Handle rate limiting
                if ($response->status() === 429) {
                    $retryAfter = $response->header('Retry-After') ?? 60;
                    Log::warning("Rate limited by Azure DevOps API. Waiting {$retryAfter} seconds.");
                    sleep($retryAfter);
                    continue;
                }

                // Handle other errors
                Log::error("Azure DevOps API error", [
                    'status' => $response->status(),
                    'endpoint' => $endpoint,
                    'response' => $response->body(),
                    'attempt' => $attempt
                ]);

                if ($attempt === $maxRetries) {
                    throw new Exception("Azure DevOps API request failed after {$maxRetries} attempts: " . $response->body());
                }

                // Wait before retry
                sleep(pow(2, $attempt));

            } catch (Exception $e) {
                Log::error("Exception in Azure DevOps API request", [
                    'endpoint' => $endpoint,
                    'attempt' => $attempt,
                    'error' => $e->getMessage()
                ]);

                if ($attempt === $maxRetries) {
                    throw $e;
                }

                sleep(pow(2, $attempt));
            }
        }

        throw new Exception("Azure DevOps API request failed after {$maxRetries} attempts");
    }

    /**
     * Make a POST request to Azure DevOps API with rate limiting and retry logic
     */
    private function makePostRequest(string $endpoint, array $body = [], int $maxRetries = 3): array
    {
        $url = $this->baseUrl . $endpoint;
        
        // Debug: Log the actual URL being requested
        Log::info("Making POST request to URL: {$url}");
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::withHeaders($this->headers)
                    ->timeout(60) // Longer timeout for POST requests
                    ->post($url, $body);

                if ($response->successful()) {
                    return $response->json();
                }

                // Handle rate limiting
                if ($response->status() === 429) {
                    $retryAfter = $response->header('Retry-After') ?? 60;
                    Log::warning("Rate limited by Azure DevOps API. Waiting {$retryAfter} seconds.");
                    sleep($retryAfter);
                    continue;
                }

                // Handle other errors
                Log::error("Azure DevOps API POST error", [
                    'status' => $response->status(),
                    'endpoint' => $endpoint,
                    'response' => $response->body(),
                    'attempt' => $attempt
                ]);

                if ($attempt === $maxRetries) {
                    throw new Exception("Azure DevOps API POST request failed after {$maxRetries} attempts: " . $response->body());
                }

                // Wait before retry
                sleep(pow(2, $attempt));

            } catch (Exception $e) {
                Log::error("Exception in Azure DevOps API POST request", [
                    'endpoint' => $endpoint,
                    'attempt' => $attempt,
                    'error' => $e->getMessage()
                ]);

                if ($attempt === $maxRetries) {
                    throw $e;
                }

                sleep(pow(2, $attempt));
            }
        }

        throw new Exception("Azure DevOps API POST request failed after {$maxRetries} attempts");
    }

    /**
     * Get all projects
     */
    public function getProjects(): array
    {
        $cacheKey = 'ado_projects';
        
        return Cache::remember($cacheKey, 3600, function () {
            $endpoint = "/_apis/projects?api-version=7.0";
            $response = $this->makeRequest($endpoint);
            
            $projects = $response['value'] ?? [];
            
            return $projects;
        });
    }

    /**
     * Get all teams from Azure DevOps Core API
     */
    public function getTeams(string $projectId = null, bool $mine = null, int $top = null, int $skip = null, bool $expandIdentity = null): array
    {
        $params = ['api-version' => '7.0'];
        
        if ($mine !== null) {
            $params['$mine'] = $mine ? 'true' : 'false';
        }
        
        if ($top !== null) {
            $params['$top'] = $top;
        }
        
        if ($skip !== null) {
            $params['$skip'] = $skip;
        }
        
        if ($expandIdentity !== null) {
            $params['$expandIdentity'] = $expandIdentity ? 'true' : 'false';
        }

        $cacheKey = 'ado_all_teams_' . md5(serialize($params));
        
        return Cache::remember($cacheKey, 1800, function () use ($params) {
            $endpoint = "/_apis/teams?" . http_build_query($params);
            $response = $this->makeRequest($endpoint);
            
            return $response['value'] ?? [];
        });
    }

    /**
     * Get teams for a specific project (legacy method for backward compatibility)
     */
    public function getTeamsForProject(string $projectId): array
    {
        $cacheKey = "ado_teams_{$projectId}";
        
        return Cache::remember($cacheKey, 1800, function () use ($projectId) {
            $endpoint = "/{$projectId}/_apis/teams?api-version=7.0";
            $response = $this->makeRequest($endpoint);
            
            return $response['value'] ?? [];
        });
    }

    /**
     * Get all users from Graph API
     */
    public function getUsers(string $subjectTypes = null, string $scopeDescriptor = null): array
    {
        $params = ['api-version' => '7.0-preview.1'];
        
        if ($subjectTypes) {
            $params['subjectTypes'] = $subjectTypes;
        }
        
        if ($scopeDescriptor) {
            $params['scopeDescriptor'] = $scopeDescriptor;
        }

        // Use Graph API base URL
        $graphBaseUrl = config('services.azure_devops.graph_api_url') . "/{$this->organization}";
        $endpoint = "/_apis/graph/users?" . http_build_query($params);
        
        $url = $graphBaseUrl . $endpoint;
        
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $response = Http::withHeaders($this->headers)
                    ->timeout(30)
                    ->get($url);

                if ($response->successful()) {
                    return $response->json()['value'] ?? [];
                }

                // Handle rate limiting
                if ($response->status() === 429) {
                    $retryAfter = $response->header('Retry-After') ?? 60;
                    Log::warning("Rate limited by Azure DevOps Graph API. Waiting {$retryAfter} seconds.");
                    sleep($retryAfter);
                    continue;
                }

                // Handle other errors
                Log::error("Azure DevOps Graph API error", [
                    'status' => $response->status(),
                    'endpoint' => $endpoint,
                    'response' => $response->body(),
                    'attempt' => $attempt
                ]);

                if ($attempt === 3) {
                    throw new Exception("Azure DevOps Graph API request failed after 3 attempts: " . $response->body());
                }

                sleep(pow(2, $attempt));

            } catch (Exception $e) {
                Log::error("Exception in Azure DevOps Graph API request", [
                    'endpoint' => $endpoint,
                    'attempt' => $attempt,
                    'error' => $e->getMessage()
                ]);

                if ($attempt === 3) {
                    throw $e;
                }

                sleep(pow(2, $attempt));
            }
        }

        throw new Exception("Azure DevOps Graph API request failed after 3 attempts");
    }

    /**
     * Get iterations for a project using Classification Nodes API
     */
    public function getIterations(string $projectId): array
    {
        $cacheKey = "ado_iterations_{$projectId}";
        
        return Cache::remember($cacheKey, 1800, function () use ($projectId) {
            $endpoint = "/{$projectId}/_apis/wit/classificationnodes/iterations?api-version=7.0&\$expand=true";
            $response = $this->makeRequest($endpoint);
            
            return $this->extractIterationsFromNode($response);
        });
    }

    /**
     * Get classification nodes for a project (for iterations)
     */
    public function getClassificationNodes(string $projectId, string $structureGroup = 'Iterations', int $depth = 10): array
    {
        $cacheKey = "ado_classification_nodes_{$projectId}_{$structureGroup}_{$depth}";
        
        return Cache::remember($cacheKey, 1800, function () use ($projectId, $structureGroup, $depth) {
            $endpoint = "/{$projectId}/_apis/wit/classificationnodes/{$structureGroup}?api-version=7.0&\$depth={$depth}";
            $response = $this->makeRequest($endpoint);
            
            return $response;
        });
    }

    /**
     * Get team iterations from Azure DevOps API
     */
    public function getTeamIterationsFromAPI(string $projectId, string $teamId): array
    {
        $cacheKey = "ado_team_iterations_api_{$projectId}_{$teamId}";
        
        return Cache::remember($cacheKey, 1800, function () use ($projectId, $teamId) {
            // Get team name from database
            $team = \App\Models\ADOTeam::find($teamId);
            if (!$team) {
                Log::error("Team not found in database: {$teamId}");
                return [];
            }
            
            // Use the correct Azure DevOps API endpoint with team name
            $endpoint = "/{$projectId}/{$team->name}/_apis/work/teamsettings/iterations?api-version=7.0";
            
            Log::info("Fetching team iterations from API for project: {$projectId}, team: {$team->name} (ID: {$teamId})");
            Log::info("Making GET request to URL: {$this->baseUrl}{$endpoint}");
            
            $response = Http::withHeaders($this->headers)
                ->timeout(30)
                ->get($this->baseUrl . $endpoint);
            
            if (!$response->successful()) {
                Log::error("Failed to get team iterations from API", [
                    'project_id' => $projectId,
                    'team_id' => $teamId,
                    'team_name' => $team->name,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return [];
            }
            
            $data = $response->json();
            $iterations = $data['value'] ?? [];
            
            Log::info("Found " . count($iterations) . " team iterations from API for project: {$projectId}, team: {$team->name}");
            
            return $iterations;
        });
    }

    /**
     * Get team iterations from database
     */
    public function getTeamIterations(string $projectId, string $teamId): array
    {
        $cacheKey = "ado_team_iterations_db_{$projectId}_{$teamId}";
        
        return Cache::remember($cacheKey, 1800, function () use ($projectId, $teamId) {
            // Query the ado_team_iterations table using team_id
            $teamIterations = \App\Models\ADOTeamIteration::where('team_id', $teamId)
                ->where('project_id', $projectId)
                ->where('assigned', true) // Only get assigned iterations
                ->orderBy('start_date', 'asc')
                ->get()
                ->map(function ($iteration) {
                    return [
                        'id' => $iteration->iteration_identifier,
                        'name' => $iteration->iteration_name,
                        'path' => $iteration->iteration_path,
                        'startDate' => $iteration->start_date?->toDateString(),
                        'finishDate' => $iteration->end_date?->toDateString(),
                        'timeFrame' => $iteration->timeframe,
                        'assigned' => $iteration->assigned,
                        'teamName' => $iteration->team_name
                    ];
                })
                ->toArray();
            
            return $teamIterations;
        });
    }

    /**
     * Get work items with WIQL query and incremental sync support
     */
    public function getWorkItems(string $projectName, ?\DateTime $lastSyncTime = null, string $wiql = null, int $top = 1000, bool $firstBatchOnly = false, array $activeTeams = [], array $activeIterations = [], array $activeTeamIterations = []): array
    {
        // Get project from database using project name
        $project = \App\Models\ADOProject::where('name', $projectName)->first();
        if (!$project) {
            Log::error("Project not found in database: {$projectName}");
            return [];
        }
        
        if (!$wiql) {
            // Build WIQL query with active filtering - escape single quotes like Python
            $escapedProjectName = str_replace("'", "''", $projectName);
            $queryParts = [
                "SELECT [System.Id]",
                "FROM WorkItems",
                "WHERE [System.TeamProject] = '{$escapedProjectName}'"
            ];
            
            // Collect all active iteration paths from both iterations and team iterations
            $allActiveIterationPaths = [];
            
            // Add active iterations filter if we have active iterations
            if (!empty($activeIterations)) {
                Log::info("🎯 Adding iteration filter to WIQL query", ['active_iterations_count' => count($activeIterations)]);
                
                // Get iteration paths for active iterations
                $activeIterationPaths = \App\Models\ADOIteration::whereIn('id', $activeIterations)
                    ->pluck('path')
                    ->filter() // Remove null values
                    ->toArray();
                
                $allActiveIterationPaths = array_merge($allActiveIterationPaths, $activeIterationPaths);
                Log::info("🎯 Added iteration paths from active iterations", ['paths' => $activeIterationPaths]);
            }
            
            // Add active team iterations filter for more precise team-based filtering
            if (!empty($activeTeamIterations)) {
                Log::info("🎯 Adding team iteration paths filter to WIQL query", ['active_team_iterations_count' => count($activeTeamIterations)]);
                
                // Get iteration paths for active team iterations
                $activeTeamIterationPaths = \App\Models\ADOTeamIteration::whereIn('id', $activeTeamIterations)
                    ->pluck('iteration_path')
                    ->filter() // Remove null values
                    ->toArray();
                
                $allActiveIterationPaths = array_merge($allActiveIterationPaths, $activeTeamIterationPaths);
                Log::info("🎯 Added iteration paths from active team iterations", ['paths' => $activeTeamIterationPaths]);
            }
            
            // Apply combined iteration path filter if we have any active paths
            if (!empty($allActiveIterationPaths)) {
                $uniqueIterationPaths = array_unique($allActiveIterationPaths);
                $escapedPaths = array_map(function($path) {
                    return "'" . str_replace("'", "''", $path) . "'";
                }, $uniqueIterationPaths);
                
                $iterationPathsString = implode(", ", $escapedPaths);
                $queryParts[] = "AND [System.IterationPath] IN ({$iterationPathsString})";
                Log::info("🎯 Applied combined iteration paths filter", ['total_paths' => count($uniqueIterationPaths), 'paths' => $uniqueIterationPaths]);
            }
            
            // Add incremental sync filter if lastSyncTime is provided
            if ($lastSyncTime) {
                // Format the date for WIQL (date-only format)
                $formattedDate = $lastSyncTime->format('Y-m-d');
                $queryParts[] = "AND [System.ChangedDate] >= '{$formattedDate}'";
            }
            
            $queryParts[] = "ORDER BY [System.Id]";
            $wiql = implode("\n", $queryParts);
        }

        // Use the correct URL structure like Python script
        $endpoint = "/_apis/wit/wiql?api-version=7.0";
        $body = [
            'query' => $wiql,
            'top' => $top
        ];

        Log::info("Fetching work items for project: {$projectName} (ID: {$project->id})");
        Log::info("WIQL Query: {$wiql}");

        $response = Http::withHeaders($this->headers)
            ->timeout(60) // Increased timeout for large queries
            ->post($this->baseUrl . $endpoint, $body);

        if (!$response->successful()) {
            Log::error("Failed to get work items", [
                'project' => $projectName,
                'status' => $response->status(),
                'response' => $response->body()
            ]);
            throw new Exception("Failed to get work items: " . $response->body());
        }

        $wiqlResponse = $response->json();
        $workItemIds = collect($wiqlResponse['workItems'] ?? [])->pluck('id')->toArray();

        $workItemCount = count($workItemIds);
        $filteringApplied = !empty($activeIterations) || !empty($activeTeamIterations);
        
        if ($filteringApplied) {
            Log::info("🎯 Found {$workItemCount} filtered work items for project: {$projectName} (WIQL pre-filtering applied)");
        } else {
            Log::info("Found {$workItemCount} work items for project: {$projectName} (no filtering applied)");
        }
        
        if ($workItemCount > 1000) {
            $warningMsg = "⚠️ Large sync detected: {$workItemCount} work items. This may take several minutes.";
            Log::warning($warningMsg);
            echo $warningMsg . "\n";
        }

        if (empty($workItemIds)) {
            return [];
        }

        // Get detailed work item information using batch approach (like Python script)
        return $this->getWorkItemsBatch($workItemIds, $projectName, $project, $firstBatchOnly, $activeTeams, $activeIterations, $activeTeamIterations);
    }

    /**
     * Get detailed work item information
     */
    public function getWorkItemDetails(array $workItemIds): array
    {
        if (empty($workItemIds)) {
            return [];
        }

        $ids = implode(',', $workItemIds);
        $endpoint = "/_apis/wit/workitems?ids={$ids}&api-version=7.0&\$expand=all";
        
        $response = $this->makeRequest($endpoint);
        
        return $response['value'] ?? [];
    }

    /**
     * Get work item by ID
     */
    public function getWorkItem(int $workItemId): array
    {
        $endpoint = "/_apis/wit/workItems/{$workItemId}?api-version=7.0&\$expand=all";
        
        return $this->makeRequest($endpoint);
    }

    /**
     * Get work items by iteration from database
     */
    public function getWorkItemsByIteration(string $projectId, string $iterationId, array $filters = []): array
    {
        $cacheKey = "ado_work_items_iteration_{$projectId}_{$iterationId}_" . md5(serialize($filters));
        
        return Cache::remember($cacheKey, 1800, function () use ($projectId, $iterationId, $filters) {
            // Debug logging
            Log::info("🔍 getWorkItemsByIteration called with projectId: {$projectId}, iterationId: {$iterationId}");
            
            // Find the team iteration by iteration_identifier
            $teamIteration = \App\Models\ADOTeamIteration::where('iteration_identifier', $iterationId)
                ->where('project_id', $projectId)
                ->first();
            
            Log::info("🔍 Team iteration lookup result: " . ($teamIteration ? "Found with ID: {$teamIteration->id}" : "Not found"));
            
            if (!$teamIteration) {
                Log::warning("⚠️ No team iteration found for identifier: {$iterationId}");
                return [];
            }
            
            // Check if project exists
            $project = \App\Models\ADOProject::find($projectId);
            Log::info("🔍 Project lookup result: " . ($project ? "Found: {$project->name}" : "Not found"));
            
            // Check total work items for this project
            $totalWorkItemsInProject = \App\Models\ADOWorkItem::where('project_id', $projectId)->count();
            Log::info("🔍 Total work items in project: {$totalWorkItemsInProject}");
            
            // Check work items with this team iteration
            $workItemsWithTeamIteration = \App\Models\ADOWorkItem::where('project_id', $projectId)
                ->where('team_iteration_id', $teamIteration->id)
                ->count();
            Log::info("🔍 Work items with team_iteration_id {$teamIteration->id}: {$workItemsWithTeamIteration}");
            
            // If no work items found by team_iteration_id, try searching by iteration_path
            if ($workItemsWithTeamIteration == 0) {
                Log::info("🔍 No work items found by team_iteration_id, trying iteration_path search");
                
                $iterationPath = $teamIteration->iteration_path;
                Log::info("🔍 Searching by iteration_path: {$iterationPath}");
                
                $workItemsByPath = \App\Models\ADOWorkItem::where('project_id', $projectId)
                    ->where('iteration_path', $iterationPath)
                    ->count();
                Log::info("🔍 Work items with iteration_path {$iterationPath}: {$workItemsByPath}");
                
                if ($workItemsByPath > 0) {
                    $query = \App\Models\ADOWorkItem::byProject($projectId)
                        ->where('iteration_path', $iterationPath);
                } else {
                    // Fallback to team_iteration_id even if count is 0
                    $query = \App\Models\ADOWorkItem::byProject($projectId)
                        ->byTeamIteration($teamIteration->id);
                }
            } else {
                $query = \App\Models\ADOWorkItem::byProject($projectId)
                    ->byTeamIteration($teamIteration->id);
            }
            
            // Apply filters if provided
            if (!empty($filters['state_filter'])) {
                $query->byState($filters['state_filter']);
            }
            
            if (!empty($filters['assignee_filter'])) {
                $query->assignedTo($filters['assignee_filter']);
            }
            
            if (!empty($filters['work_item_type_filter'])) {
                $query->byType($filters['work_item_type_filter']);
            }
            
            $workItems = $query->get();
            Log::info("🔍 Final query returned " . $workItems->count() . " work items");
            
            $mappedWorkItems = $workItems->map(function ($workItem) {
                return [
                    'id' => $workItem->id,
                    'url' => $workItem->url,
                    'title' => $workItem->title,
                    'workItemType' => $workItem->work_item_type,
                    'state' => $workItem->state,
                    'priority' => $workItem->priority,
                    'storyPoints' => $workItem->story_points,
                    'effort' => $workItem->effort,
                    'remainingWork' => $workItem->remaining_work,
                    'completedWork' => $workItem->completed_work,
                    'originalEstimate' => $workItem->original_estimate,
                    'assignedTo' => $workItem->assigned_to_display_name,
                    'assignedToDescriptor' => $workItem->assigned_to,
                    'modifiedBy' => $workItem->modified_by_display_name,
                    'createdDate' => $workItem->created_date?->toISOString(),
                    'changedDate' => $workItem->changed_date?->toISOString(),
                    'areaPath' => $workItem->area_path,
                    'iterationPath' => $workItem->iteration_path,
                    'tags' => $workItem->tags,
                    'ruddrTaskName' => $workItem->ruddr_task_name,
                    'ruddrProjectId' => $workItem->ruddr_project_id,
                    'taskStartDt' => $workItem->task_start_dt?->toISOString(),
                    'taskEndDt' => $workItem->task_end_dt?->toISOString(),
                    'delayedCompletion' => $workItem->delayed_completion?->toISOString(),
                    'delayedReason' => $workItem->delayed_reason,
                    'movedFromSprint' => $workItem->moved_from_sprint,
                    'spilloverReason' => $workItem->spillover_reason,
                    'effortSavedUsingAI' => $workItem->effort_saved_using_ai,
                    'parentId' => $workItem->parent_id
                ];
            })->toArray();
            
            Log::info("🔍 Returning " . count($mappedWorkItems) . " mapped work items");
            
            return $mappedWorkItems;
        });
    }

    /**
     * Get work items in batch using the correct Azure DevOps API
     */
    public function getWorkItemsBatch(array $workItemIds, string $projectName = null, $project = null, bool $firstBatchOnly = false, array $activeTeams = [], array $activeIterations = [], array $activeTeamIterations = []): array
    {
        if (empty($workItemIds)) {
            return [];
        }

        // Process in batches of 20 (reduced from 50 to further optimize memory usage)
        $batchSize = 20;
        $allWorkItems = [];
        $totalInserted = 0;
        $totalUpdated = 0;
        $startTime = microtime(true);
        
        for ($i = 0; $i < count($workItemIds); $i += $batchSize) {
            $batchIds = array_slice($workItemIds, $i, $batchSize);
            $batchNumber = ($i / $batchSize) + 1;
            $totalBatches = ceil(count($workItemIds) / $batchSize);
            
            // Use the correct endpoint: POST with project in URL and IDs in body
            $endpoint = "/{$projectName}/_apis/wit/workitemsbatch?api-version=7.0";
            $body = [
                'ids' => $batchIds,
                '$expand' => 'all'
            ];
            
            $batchMsg = "🔄 Fetching API batch {$batchNumber}/{$totalBatches} of " . count($batchIds) . " work items for project: {$projectName}";
            Log::info($batchMsg);
            echo $batchMsg . "\n";
            Log::info("URL: " . $this->baseUrl . $endpoint);
            
            $response = $this->makePostRequest($endpoint, $body);
            $batchWorkItems = $response['value'] ?? [];
            
            // Clear response immediately to free memory
            unset($response);
            

            
            $retrievedMsg = "✅ Retrieved " . count($batchWorkItems) . " work items from API batch {$batchNumber}";
            Log::info($retrievedMsg);
            echo $retrievedMsg . "\n";
            
            // Process this batch immediately into database
            if (!empty($batchWorkItems) && $project) {
                $batchInserted = 0;
                $batchUpdated = 0;
                $batchErrors = 0;
                
                // Process each work item individually with better error handling (like Python script)
                foreach ($batchWorkItems as $workItemData) {
                    try {
                        $fields = $workItemData['fields'] ?? [];
                        
                        // Validate required fields exist
                        if (!isset($workItemData['id'])) {
                            Log::warning("Work item missing ID, skipping");
                            $batchErrors++;
                            continue;
                        }
                    
                        // Find team iteration based on iteration path
                        $teamIterationId = null;
                        $iterationPath = $fields['System.IterationPath'] ?? null;
                        if ($iterationPath) {
                            // Find team iteration by matching iteration path
                            $teamIteration = \App\Models\ADOTeamIteration::where('project_id', $project->id)
                                ->where('iteration_path', $iterationPath)
                                ->first();
                            
                            if ($teamIteration) {
                                $teamIterationId = $teamIteration->id;
                                Log::info("🔗 Found team iteration ID {$teamIterationId} for path: {$iterationPath}");
                            } else {
                                Log::warning("⚠️ No team iteration found for path: {$iterationPath} in project: {$project->name}");
                            }
                        }
                        
                        // Minimal post-API filtering since most filtering is done at WIQL level
                        // We only need to double-check team iteration membership for precision
                        if (!empty($activeTeamIterations) && $teamIterationId) {
                            if (!in_array($teamIterationId, $activeTeamIterations)) {
                                Log::info("🚫 Skipping work item {$workItemData['id']} - team iteration {$teamIterationId} not in active list (post-API check)");
                                continue;
                            }
                        }
                        
                    // Prepare work item data with actual Azure DevOps timestamps
                    $workItemUpdateData = [
                        'url' => $workItemData['url'],
                        'project_id' => $project->id,
                        'team_id' => null, // Don't map System.AreaId to team_id - it's an area ID, not team ID
                        'iteration_id' => $this->validateIterationIdAsString($fields['System.IterationId'] ?? null),
                        'team_iteration_id' => $teamIterationId,
                        'iteration_path' => $iterationPath,
                        'work_item_type' => $fields['System.WorkItemType'] ?? null,
                        'title' => $fields['System.Title'] ?? null,
                        'state' => $fields['System.State'] ?? null,
                        'priority' => $fields['Microsoft.VSTS.Common.Priority'] ?? null,
                        'story_points' => $fields['Microsoft.VSTS.Scheduling.StoryPoints'] ?? null,
                        'effort' => $fields['Microsoft.VSTS.Scheduling.Effort'] ?? null,
                        'remaining_work' => $fields['Microsoft.VSTS.Scheduling.RemainingWork'] ?? null,
                        'completed_work' => $fields['Microsoft.VSTS.Scheduling.CompletedWork'] ?? null,
                        'original_estimate' => $fields['Microsoft.VSTS.Scheduling.OriginalEstimate'] ?? null,
                        'assigned_to' => $this->validateUserDescriptor($this->extractUserDescriptor($fields['System.AssignedTo'] ?? null)),
                        'assigned_to_display_name' => $this->extractUserDisplayName($fields['System.AssignedTo'] ?? null),
                        'modified_by' => $this->validateUserDescriptor($this->extractUserDescriptor($fields['System.ChangedBy'] ?? null)),
                        'modified_by_display_name' => $this->extractUserDisplayName($fields['System.ChangedBy'] ?? null),
                        'created_date' => $fields['System.CreatedDate'] ?? null,
                        'changed_date' => $fields['System.ChangedDate'] ?? null,
                        'area_path' => $fields['System.AreaPath'] ?? null,
                        'tags' => isset($fields['System.Tags']) && $fields['System.Tags'] ? explode(';', $fields['System.Tags']) : [],
                        'ruddr_task_name' => $fields['Custom.RuddrTaskName'] ?? null,
                        'ruddr_project_id' => $fields['Custom.RuddrProjectUID'] ?? null,
                        'task_start_dt' => $fields['Custom.StartDt'] ?? null,
                        'task_end_dt' => $fields['Custom.EndDt'] ?? null,
                        'delayed_completion' => $fields['Custom.DelayedCompletion'] ?? null,
                        'delayed_reason' => $fields['Custom.DelayedReason'] ?? null,
                        'moved_from_sprint' => $fields['Custom.MovedFromSprint'] ?? null,
                        'spillover_reason' => $fields['Custom.SpilloverReason'] ?? null,
                        'effort_saved_using_ai' => $fields['Custom.EffortSavedUsingAI'] ?? null,
                        'parent_id' => $fields['System.Parent'] ?? null,
                    ];
                        
                    // Add actual Azure DevOps timestamps (like Python script)
                    if (isset($fields['System.CreatedDate'])) {
                        $workItemUpdateData['created_at'] = $fields['System.CreatedDate'];
                    }
                    if (isset($fields['System.ChangedDate'])) {
                        $workItemUpdateData['updated_at'] = $fields['System.ChangedDate'];
                    }

                                    $workItem = \App\Models\ADOWorkItem::updateOrCreate(
                    ['id' => (string) $workItemData['id']],
                    $workItemUpdateData
                );
                        
                        if ($workItem->wasRecentlyCreated) {
                            $batchInserted++;
                            $totalInserted++;
                        } else {
                            $batchUpdated++;
                            $totalUpdated++;
                        }
                            
                    } catch (Exception $e) {
                        $batchErrors++;
                        // Log only first few errors to avoid spam (like Python script)
                        if ($batchErrors <= 5) {
                            Log::error("Error syncing work item {$workItemData['id']}: " . $e->getMessage());
                        } elseif ($batchErrors == 6) {
                            Log::warning("Additional errors will be suppressed for this batch...");
                        }
                        // Continue processing other items (like Python script)
                        continue;
                    }
                }
                            $batchCompleteMsg = "💾 Database batch {$batchNumber} complete: Inserted {$batchInserted}, Updated {$batchUpdated}, Errors {$batchErrors}";
            Log::info($batchCompleteMsg);
            echo $batchCompleteMsg . "\n";
            
            // Show progress and estimated time remaining
            $elapsedTime = microtime(true) - $startTime;
            $avgTimePerBatch = $elapsedTime / $batchNumber;
            $remainingBatches = $totalBatches - $batchNumber;
            $estimatedTimeRemaining = $avgTimePerBatch * $remainingBatches;
            
            if ($remainingBatches > 0) {
                $progressMsg = "⏱️ Progress: {$batchNumber}/{$totalBatches} batches ({$remainingBatches} remaining, ~" . round($estimatedTimeRemaining, 1) . "s)";
                echo $progressMsg . "\n";
            }
            }
            
            $allWorkItems = array_merge($allWorkItems, $batchWorkItems);
            
            // Aggressive memory management after each batch
            unset($batchWorkItems); // Clear batch data immediately
            Cache::flush();
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles(); // Force garbage collection
            }
            
            // Log memory usage
            $memoryUsage = round(memory_get_usage(true) / 1024 / 1024, 2);
            $memoryPeak = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
            Log::info("🧹 Batch {$batchNumber} complete. Memory: {$memoryUsage}MB, Peak: {$memoryPeak}MB");
            
            // Break after first batch if firstBatchOnly is true
            if ($firstBatchOnly) {
                Log::info("🎯 First batch only mode - stopping after batch {$batchNumber}");
                break;
            }
            
            // Add small delay between batches for rate limiting
            if ($i + $batchSize < count($workItemIds)) {
                Log::info("⏳ Waiting 100ms before next API batch...");
                usleep(100000); // 100ms delay
            }
        }
        
        $summaryMsg = "📊 Total database operations: Inserted {$totalInserted}, Updated {$totalUpdated}";
        Log::info($summaryMsg);
        echo $summaryMsg . "\n";
        
        // Final memory cleanup
        $finalMemory = round(memory_get_usage(true) / 1024 / 1024, 2);
        $peakMemory = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $memoryMsg = "💾 Final memory usage: {$finalMemory}MB, Peak: {$peakMemory}MB";
        Log::info($memoryMsg);
        echo $memoryMsg . "\n";
        
        return $allWorkItems;
    }



    /**
     * Get work item revisions
     */
    public function getWorkItemRevisions(int $workItemId): array
    {
        $endpoint = "/_apis/wit/workItems/{$workItemId}/revisions?api-version=7.0";
        
        $response = $this->makeRequest($endpoint);
        
        return $response['value'] ?? [];
    }

    /**
     * Get work item links
     */
    public function getWorkItemLinks(int $workItemId): array
    {
        $endpoint = "/_apis/wit/workItems/{$workItemId}?api-version=7.0&\$expand=relations";
        
        $response = $this->makeRequest($endpoint);
        
        return $response['relations'] ?? [];
    }

    /**
     * Extract iterations from classification node tree (enhanced version)
     */
    private function extractIterationsFromNode(array $node, string $pathPrefix = ''): array
    {
        $iterations = [];
        
        // Extract current node if it has iteration data
        if (isset($node['identifier']) && isset($node['name'])) {
            $iterationData = [
                'id' => $node['id'] ?? null,
                'identifier' => $node['identifier'],
                'name' => $node['name'],
                'path' => $pathPrefix ? "{$pathPrefix}\\{$node['name']}" : $node['name'],
                'url' => $node['url'] ?? null,
                'attributes' => $node['attributes'] ?? [],
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
            }
            
            $iterations[] = $iterationData;
        }
        
        // Process children
        if (isset($node['children'])) {
            $currentPath = $pathPrefix ? "{$pathPrefix}\\{$node['name']}" : $node['name'];
            foreach ($node['children'] as $child) {
                $childIterations = $this->extractIterationsFromNode($child, $currentPath);
                $iterations = array_merge($iterations, $childIterations);
            }
        }
        
        return $iterations;
    }

    /**
     * Clear cache for specific resource
     */
    public function clearCache(string $resource, string $identifier = null): void
    {
        $cacheKey = $identifier ? "ado_{$resource}_{$identifier}" : "ado_{$resource}";
        Cache::forget($cacheKey);
    }

    /**
     * Extract user descriptor from user field (like Python script)
     */
    private function extractUserDescriptor($userField): ?string
    {
        if (!$userField || !is_array($userField)) {
            return null;
        }
        
        return $userField['descriptor'] ?? null;
    }
    
    /**
     * Extract user display name from user field (like Python script)
     */
    private function extractUserDisplayName($userField): ?string
    {
        if (!$userField || !is_array($userField)) {
            return null;
        }
        
        return $userField['displayName'] ?? null;
    }
    
    /**
     * Validate if iteration ID exists in database, return null if not (like Python script)
     */
    private function validateIterationId($iterationId): ?int
    {
        if (!$iterationId) {
            return null;
        }
        
        // Check if iteration exists in database
        $exists = \App\Models\ADOIteration::where('id', $iterationId)->exists();
        
        return $exists ? (int)$iterationId : null;
    }
    
    /**
     * Validate if iteration ID exists in database, return as string or null (for new string primary keys)
     */
    private function validateIterationIdAsString($iterationId): ?string
    {
        if (!$iterationId) {
            return null;
        }
        
        // Check if iteration exists in database
        $exists = \App\Models\ADOIteration::where('id', $iterationId)->exists();
        
        return $exists ? (string)$iterationId : null;
    }
    
    /**
     * Validate if user descriptor exists in database, return null if not (like Python script)
     */
    private function validateUserDescriptor($userDescriptor): ?string
    {
        if (!$userDescriptor) {
            return null;
        }
        
        // Check if user exists in database (now using id instead of descriptor)
        $exists = \App\Models\ADOUser::where('id', $userDescriptor)->exists();
        
        return $exists ? $userDescriptor : null;
    }

    /**
     * Get daily progress tracking data for a sprint
     */
    public function getDailyProgress(string $projectId, string $sprintId, string $startDate, string $endDate): array
    {
        $cacheKey = "ado_daily_progress_{$projectId}_{$sprintId}_{$startDate}_{$endDate}";
        
        return Cache::remember($cacheKey, 1800, function () use ($projectId, $sprintId, $startDate, $endDate) {
            // First, try to find the iteration by identifier (from team iterations)
            $teamIteration = \App\Models\ADOTeamIteration::where('iteration_identifier', $sprintId)
                ->where('project_id', $projectId)
                ->first();
            
            if ($teamIteration) {
                // If found in team iterations, get the corresponding iteration ID
                $iteration = \App\Models\ADOIteration::where('identifier', $sprintId)
                    ->where('project_id', $projectId)
                    ->first();
                
                if ($iteration) {
                    $actualIterationId = $iteration->id;
                } else {
                    // If no iteration found, return empty array
                    return [];
                }
            } else {
                // If not found in team iterations, assume it's already an iteration ID
                $actualIterationId = $sprintId;
            }
            
            // Get work items for the sprint
            $workItems = \App\Models\ADOWorkItem::byProject($projectId)
                ->byIteration($actualIterationId)
                ->whereNotNull('changed_date')
                ->whereBetween('changed_date', [$startDate, $endDate])
                ->orderBy('changed_date')
                ->get();
            
            // Group by date and calculate daily metrics
            $dailyProgress = [];
            $currentDate = \Carbon\Carbon::parse($startDate);
            $endDateCarbon = \Carbon\Carbon::parse($endDate);
            
            while ($currentDate->lte($endDateCarbon)) {
                $dateKey = $currentDate->toDateString();
                
                // Get work items changed on this date
                $dayWorkItems = $workItems->filter(function ($workItem) use ($dateKey) {
                    return $workItem->changed_date->toDateString() === $dateKey;
                });
                
                $dailyProgress[] = [
                    'date' => $dateKey,
                    'totalWorkItems' => $dayWorkItems->count(),
                    'completedWorkItems' => $dayWorkItems->whereIn('state', ['Completed', 'Done', 'Closed', 'Resolved'])->count(),
                    'inProgressWorkItems' => $dayWorkItems->whereIn('state', ['In Progress', 'Active', 'Committed'])->count(),
                    'notStartedWorkItems' => $dayWorkItems->whereNotIn('state', ['Completed', 'Done', 'Closed', 'Resolved', 'In Progress', 'Active', 'Committed'])->count(),
                    'totalStoryPoints' => $dayWorkItems->sum('story_points'),
                    'totalEffort' => $dayWorkItems->sum('effort'),
                    'totalCompletedWork' => $dayWorkItems->sum('completed_work'),
                    'totalRemainingWork' => $dayWorkItems->sum('remaining_work')
                ];
                
                $currentDate->addDay();
            }
            
            return $dailyProgress;
        });
    }

    /**
     * Clear all ADO cache
     */
    public function clearAllCache(): void
    {
        $patterns = ['ado_projects*', 'ado_teams*', 'ado_iterations*', 'ado_team_iterations*'];
        
        foreach ($patterns as $pattern) {
            Cache::flush();
        }
    }
}
