<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ADOSyncService;

class SyncAzureDevOps extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ado:sync 
                            {--all : Sync all Azure DevOps resources}
                            {--projects : Sync only projects}
                            {--users : Sync only users}
                            {--teams : Sync only teams}
                            {--iterations : Sync only iterations}
                            {--team-iterations : Sync only team iterations}
                            {--work-items : Sync only work items}
                            {--depth=10 : Depth for classification nodes API}
                            {--clear-cache : Clear cache before syncing}
                            {--first-batch-only : For work items, sync only first batch (testing)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Azure DevOps data (projects, users, teams, iterations, work items)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $startTime = microtime(true);
        
        try {
            $this->info('🚀 Starting Azure DevOps Data Synchronization...');
            $this->info('=' . str_repeat('=', 60));
            
            $syncService = new ADOSyncService();
            
            // Clear cache if requested
            if ($this->option('clear-cache')) {
                $this->info('🧹 Clearing Azure DevOps cache...');
                $syncService->clearCache();
            }
            
            // Check if any specific sync option is provided
            $hasSpecificSync = $this->option('projects') || 
                              $this->option('users') || 
                              $this->option('teams') || 
                              $this->option('iterations') || 
                              $this->option('team-iterations') || 
                              $this->option('work-items');
            
            if ($this->option('all') || !$hasSpecificSync) {
                $this->info('🔄 Running complete Azure DevOps sync (all resources)...');
                $this->runAllSyncs($syncService);
            } else {
                $this->info('🔄 Running specific Azure DevOps sync operations...');
                $this->runSpecificSyncs($syncService);
            }
            
            $duration = round(microtime(true) - $startTime, 2);
            
            $this->info('=' . str_repeat('=', 60));
            $this->info("🎉 Azure DevOps synchronization completed successfully!");
            $this->info("⏱️  Total Duration: {$duration} seconds");
            
            return self::SUCCESS;
            
        } catch (\Exception $e) {
            $duration = round(microtime(true) - $startTime, 2);
            
            $this->error('❌ Azure DevOps synchronization failed!');
            $this->error("   Error: {$e->getMessage()}");
            $this->error("   Duration: {$duration} seconds");
            $this->error('   Stack trace: ' . $e->getTraceAsString());
            
            return self::FAILURE;
        }
    }

    /**
     * Run all sync operations in correct dependency order
     */
    private function runAllSyncs(ADOSyncService $syncService): void
    {
        $this->info('📋 Following dependency chain: Projects → Users → Teams → Iterations → Team Iterations → Work Items');
        $this->newLine();
        
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
    private function runSpecificSyncs(ADOSyncService $syncService): void
    {
        // Sync projects
        if ($this->option('projects')) {
            $this->info('=' . str_repeat('=', 50));
            $this->info('1️⃣ Starting projects synchronization...');
            $results = $syncService->syncProjects();
            $this->displaySingleSyncResults('Projects', $results);
        }
        
        // Sync users
        if ($this->option('users')) {
            $this->info('=' . str_repeat('=', 50));
            $this->info('2️⃣ Starting users synchronization...');
            $results = $syncService->syncUsers();
            $this->displaySingleSyncResults('Users', $results);
        }
        
        // Sync teams
        if ($this->option('teams')) {
            $this->info('=' . str_repeat('=', 50));
            $this->info('3️⃣ Starting teams synchronization...');
            $results = $syncService->syncTeams();
            $this->displaySingleSyncResults('Teams', $results);
        }
        
        // Sync iterations
        if ($this->option('iterations')) {
            $this->info('=' . str_repeat('=', 50));
            $this->info('4️⃣ Starting iterations synchronization...');
            $depth = (int) $this->option('depth');
            $this->info("📊 Using depth: {$depth} for classification nodes");
            $results = $syncService->syncIterations($depth);
            $this->displaySingleSyncResults('Iterations', $results);
        }
        
        // Sync team iterations
        if ($this->option('team-iterations')) {
            $this->info('=' . str_repeat('=', 50));
            $this->info('5️⃣ Starting team iterations synchronization...');
            $results = $syncService->syncTeamIterations();
            $this->displaySingleSyncResults('Team Iterations', $results);
        }
        
        // Sync work items
        if ($this->option('work-items')) {
            $this->info('=' . str_repeat('=', 50));
            $this->info('6️⃣ Starting work items synchronization...');
            
            // Check if we have required dependencies
            $projectCount = \App\Models\ADOProject::processable()->count();
            $this->info("📊 Found {$projectCount} active projects for processing");
            
            if ($projectCount === 0) {
                $this->warn('⚠️ No active projects found. Please run projects sync first or check is_active flags.');
                $this->info('💡 Try: php artisan ado:sync --projects');
                return;
            }
            
            if ($this->option('first-batch-only')) {
                $this->info('🎯 Testing mode: First batch only');
            }
            
            $results = $syncService->syncWorkItems(null, null, $this->option('first-batch-only'));
            $this->displaySingleSyncResults('Work Items', $results);
        }
    }

    /**
     * Display results for a single sync operation
     */
    private function displaySingleSyncResults(string $resource, array $results): void
    {
        $this->info("✅ {$resource} synchronization completed!");
        $this->info("   📊 Inserted: " . ($results['inserted'] ?? 0));
        $this->info("   🔄 Updated: " . ($results['updated'] ?? 0));
        
        // Some sync methods return 'total', others don't - calculate if missing
        $total = $results['total'] ?? (($results['inserted'] ?? 0) + ($results['updated'] ?? 0));
        $this->info("   📈 Total: {$total}");
        $this->newLine();
    }

    /**
     * Display results for all sync operations
     */
    private function displaySyncResults(array $results): void
    {
        $this->newLine();
        $this->info("📊 SYNCHRONIZATION SUMMARY");
        $this->info("-" . str_repeat('-', 40));
        
        foreach ($results as $resource => $result) {
            if (isset($result['inserted']) && isset($result['updated'])) {
                $resource_name = ucwords(str_replace('_', ' ', $resource));
                $this->info("   {$resource_name}: Inserted {$result['inserted']}, Updated {$result['updated']}");
            }
        }
    }
}
