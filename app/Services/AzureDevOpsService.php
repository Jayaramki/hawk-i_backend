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
     * Get team iterations
     */
    public function getTeamIterations(string $projectId, string $teamId): array
    {
        $cacheKey = "ado_team_iterations_{$projectId}_{$teamId}";
        
        return Cache::remember($cacheKey, 1800, function () use ($projectId, $teamId) {
            $endpoint = "/{$projectId}/{$teamId}/_apis/work/teamsettings/iterations?api-version=7.0";
            $response = $this->makeRequest($endpoint);
            
            $teamIterations = $response['value'] ?? [];
            
            return $teamIterations;
        });
    }

    /**
     * Get work items with WIQL query and incremental sync support
     */
    public function getWorkItems(string $projectName, ?\DateTime $lastSyncTime = null, string $wiql = null, int $top = 1000, bool $firstBatchOnly = false): array
    {
        // Get project from database using project name
        $project = \App\Models\ADOProject::where('name', $projectName)->first();
        if (!$project) {
            Log::error("Project not found in database: {$projectName}");
            return [];
        }
        
        if (!$wiql) {
            // Build WIQL query similar to Python script - escape single quotes like Python
            $escapedProjectName = str_replace("'", "''", $projectName);
            $queryParts = [
                "SELECT [System.Id]",
                "FROM WorkItems",
                "WHERE [System.TeamProject] = '{$escapedProjectName}'"
            ];
            
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

        Log::info("Found " . count($workItemIds) . " work items for project: {$projectName}");

        if (empty($workItemIds)) {
            return [];
        }

        // Get detailed work item information using batch approach (like Python script)
        return $this->getWorkItemsBatch($workItemIds, $projectName, $project, $firstBatchOnly);
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
     * Get work items in batch using the correct Azure DevOps API
     */
    public function getWorkItemsBatch(array $workItemIds, string $projectName = null, $project = null, bool $firstBatchOnly = false): array
    {
        if (empty($workItemIds)) {
            return [];
        }

        // Process in batches of 20 (reduced from 50 to further optimize memory usage)
        $batchSize = 20;
        $allWorkItems = [];
        $totalInserted = 0;
        $totalUpdated = 0;
        
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
            
            Log::info("🔄 Fetching API batch {$batchNumber}/{$totalBatches} of " . count($batchIds) . " work items for project: {$projectName}");
            Log::info("URL: " . $this->baseUrl . $endpoint);
            
            $response = $this->makePostRequest($endpoint, $body);
            $batchWorkItems = $response['value'] ?? [];
            
            // Clear response immediately to free memory
            unset($response);
            

            
            Log::info("✅ Retrieved " . count($batchWorkItems) . " work items from API batch {$batchNumber}");
            
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
                            
                        // Prepare work item data with actual Azure DevOps timestamps
                        $workItemUpdateData = [
                                 'url' => $workItemData['url'],
                                 'project_id' => $project->id,
                             'team_id' => null, // Don't map System.AreaId to team_id - it's an area ID, not team ID
                             'iteration_id' => $this->validateIterationId($fields['System.IterationId'] ?? null),
                                 'iteration_path' => $fields['System.IterationPath'] ?? null,
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
                         ['id' => $workItemData['id']],
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
                    
                    Log::info("💾 Database batch {$batchNumber} complete: Inserted {$batchInserted}, Updated {$batchUpdated}, Errors {$batchErrors}");
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
        
        Log::info("📊 Total database operations: Inserted {$totalInserted}, Updated {$totalUpdated}");
        
        // Final memory cleanup
        $finalMemory = round(memory_get_usage(true) / 1024 / 1024, 2);
        $peakMemory = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        Log::info("💾 Final memory usage: {$finalMemory}MB, Peak: {$peakMemory}MB");
        
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
     * Validate if user descriptor exists in database, return null if not (like Python script)
     */
    private function validateUserDescriptor($userDescriptor): ?string
    {
        if (!$userDescriptor) {
            return null;
        }
        
        // Check if user exists in database
        $exists = \App\Models\ADOUser::where('descriptor', $userDescriptor)->exists();
        
        return $exists ? $userDescriptor : null;
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
