<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestADOConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ado:test-connection 
                            {--sync-projects : Only sync projects to database}
                            {--sync-users : Only sync users to database}
                            {--sync-teams : Only sync teams to database}
                            {--sync-iterations : Only sync iterations to database}
                            {--sync-team-iterations : Only sync team iterations to database}
                            {--sync-work-items : Only sync work items to database}
                            {--depth=10 : Depth for classification nodes API}
                            {--clear-cache : Clear cache before syncing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Azure DevOps connection and sync data to database (FOR TESTING ONLY - use ado:sync for production)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $startTime = microtime(true);
        
        try {
            $this->info('🚀 Testing Azure DevOps connection...');
            $this->info('=' . str_repeat('=', 50));
            
            // Test configuration
            $config = config('services.azure_devops');
            $this->info('Configuration loaded: ' . json_encode($config));
            
            // Test service creation
            $service = new \App\Services\AzureDevOpsService();
            $this->info('Azure DevOps service created successfully');
            
            // Check if any specific sync option is provided
            $hasSpecificSync = $this->option('sync-projects') || 
                              $this->option('sync-users') || 
                              $this->option('sync-teams') || 
                              $this->option('sync-iterations') || 
                              $this->option('sync-team-iterations') || 
                              $this->option('sync-work-items');
            
            // If no specific sync option, run all syncs
            if (!$hasSpecificSync) {
                $this->info('🔄 No specific sync option provided. Running all syncs...');
                $this->runAllSyncs();
            } else {
                $this->info('🔄 Running specific sync operations...');
                $this->runSpecificSyncs();
            }
            
            $duration = round(microtime(true) - $startTime, 2);
            
            $this->info('=' . str_repeat('=', 50));
            $this->info("🎉 Azure DevOps connection test completed successfully!");
            $this->info("⏱️  Duration: {$duration} seconds");
            
            return self::SUCCESS;
            
        } catch (\Exception $e) {
            $duration = round(microtime(true) - $startTime, 2);
            
            $this->error('❌ Azure DevOps connection test failed!');
            $this->error("   Error: {$e->getMessage()}");
            $this->error("   Duration: {$duration} seconds");
            $this->error('Stack trace: ' . $e->getTraceAsString());
            
            return self::FAILURE;
        }
    }

    /**
     * Run all sync operations
     */
    private function runAllSyncs(): void
    {
        $syncService = new \App\Services\ADOSyncService();
        
        // Clear cache if requested
        if ($this->option('clear-cache')) {
            $this->info('🧹 Clearing ADO cache...');
            $syncService->clearCache();
        }
        
        // Run all syncs
        $this->info('🔄 Starting comprehensive sync...');
        $results = $syncService->syncAll();
        
        if ($results['success']) {
            $this->info('✅ All syncs completed successfully!');
            $this->displaySyncResults($results['results']);
        } else {
            $this->error('❌ Sync failed: ' . $results['error']);
        }
    }

    /**
     * Run specific sync operations based on flags
     */
    private function runSpecificSyncs(): void
    {
        $syncService = new \App\Services\ADOSyncService();
        
        // Clear cache if requested
        if ($this->option('clear-cache')) {
            $this->info('🧹 Clearing ADO cache...');
            $syncService->clearCache();
        }
        
        // Sync projects
        if ($this->option('sync-projects')) {
            $this->info('=' . str_repeat('=', 50));
            $this->info('🔄 Starting projects sync to database...');
            $results = $syncService->syncProjects();
            $this->displaySingleSyncResults('Projects', $results);
        }
        
        // Sync users
        if ($this->option('sync-users')) {
            $this->info('=' . str_repeat('=', 50));
            $this->info('🔄 Starting users sync to database...');
            $results = $syncService->syncUsers();
            $this->displaySingleSyncResults('Users', $results);
        }
        
        // Sync teams
        if ($this->option('sync-teams')) {
            $this->info('=' . str_repeat('=', 50));
            $this->info('🔄 Starting teams sync to database...');
            $results = $syncService->syncTeams();
            $this->displaySingleSyncResults('Teams', $results);
        }
        
        // Sync iterations
        if ($this->option('sync-iterations')) {
            $this->info('=' . str_repeat('=', 50));
            $this->info('🔄 Starting iterations sync to database...');
            $depth = (int) $this->option('depth');
            $this->info("📊 Using depth: {$depth} for classification nodes");
            $results = $syncService->syncIterations($depth);
            $this->displaySingleSyncResults('Iterations', $results);
        }
        
        // Sync team iterations
        if ($this->option('sync-team-iterations')) {
            $this->info('=' . str_repeat('=', 50));
            $this->info('🔄 Starting team iterations sync to database...');
            $results = $syncService->syncTeamIterations();
            $this->displaySingleSyncResults('Team Iterations', $results);
        }
        
        // Sync work items
        if ($this->option('sync-work-items')) {
            $this->info('=' . str_repeat('=', 50));
            $this->info('🔄 Starting work items sync to database...');
            
            // First, let's check if we have projects in the database
            $projectCount = \App\Models\ADOProject::count();
            $this->info("📊 Found {$projectCount} projects in database");
            
            if ($projectCount === 0) {
                $this->warn('⚠️ No projects found in database. Please run projects sync first.');
                $this->info('💡 Try: php artisan ado:test-connection --sync-projects');
                return;
            }
            
            // Show real-time progress by tailing the log file
            $this->info('📋 Monitoring progress in real-time...');
            $this->info('💡 Check storage/logs/laravel.log for detailed progress');
            $this->info('🎯 Testing mode: First batch only');
            
            $results = $syncService->syncWorkItems(null, null, true); // true = firstBatchOnly
            $this->displaySingleSyncResults('Work Items', $results);
        }
    }

    /**
     * Display results for a single sync operation
     */
    private function displaySingleSyncResults(string $resource, array $results): void
    {
        $this->info("✅ {$resource} sync completed!");
        $this->info("   📊 Inserted: {$results['inserted']}");
        $this->info("   🔄 Updated: {$results['updated']}");
        $this->info("   📈 Total: {$results['total']}");
    }

    /**
     * Display results for all sync operations
     */
    private function displaySyncResults(array $results): void
    {
        foreach ($results as $resource => $result) {
            if (isset($result['inserted']) && isset($result['updated'])) {
                $this->info("   📊 {$resource}: Inserted {$result['inserted']}, Updated {$result['updated']}");
            }
        }
    }
}
