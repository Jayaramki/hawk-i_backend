<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BambooHRService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FetchTimeOffRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bamboohr:fetch-timeoff 
                            {--date= : Specific date to fetch (Y-m-d format, defaults to today)}
                            {--days=1 : Number of days to fetch (default: 1)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch time-off requests from BambooHR API';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $startTime = microtime(true);
        
        try {
            $this->info('🚀 Starting BambooHR Time-off Requests Fetch...');
            $this->info('=' . str_repeat('=', 60));
            
            // Get date parameters
            $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
            $days = (int) $this->option('days');
            
            $this->info("📅 Fetching time-off requests for: {$date->format('Y-m-d')}");
            if ($days > 1) {
                $endDate = $date->copy()->addDays($days - 1);
                $this->info("📅 Date range: {$date->format('Y-m-d')} to {$endDate->format('Y-m-d')}");
            }
            
            $bambooHRService = new BambooHRService(
                app(\App\Services\SyncProgressService::class),
                app(\App\Services\WebSocketService::class)
            );
            
            // Fetch time-off requests
            $results = [];
            for ($i = 0; $i < $days; $i++) {
                $currentDate = $date->copy()->addDays($i);
                $this->info("🔄 Fetching data for {$currentDate->format('Y-m-d')}...");
                
                $result = $bambooHRService->fetchTimeOffRequests($currentDate->format('Y-m-d'));
                
                if ($result['success']) {
                    $this->info("✅ Successfully fetched data for {$currentDate->format('Y-m-d')}");
                    $this->info("   📊 Found " . count($result['data']) . " time-off requests");
                    $results[] = $result;
                } else {
                    $this->error("❌ Failed to fetch data for {$currentDate->format('Y-m-d')}: {$result['error']}");
                }
            }
            
            $duration = round(microtime(true) - $startTime, 2);
            
            $this->info('=' . str_repeat('=', 60));
            $this->info("🎉 Time-off requests fetch completed!");
            $this->info("⏱️  Total Duration: {$duration} seconds");
            
            // Log the successful execution
            Log::info('BambooHR time-off requests fetch completed', [
                'date' => $date->format('Y-m-d'),
                'days' => $days,
                'duration' => $duration,
                'results_count' => count($results)
            ]);
            
            return self::SUCCESS;
            
        } catch (\Exception $e) {
            $duration = round(microtime(true) - $startTime, 2);
            
            $this->error('❌ Time-off requests fetch failed!');
            $this->error("   Error: {$e->getMessage()}");
            $this->error("   Duration: {$duration} seconds");
            
            // Log the error
            Log::error('BambooHR time-off requests fetch failed', [
                'error' => $e->getMessage(),
                'duration' => $duration,
                'trace' => $e->getTraceAsString()
            ]);
            
            return self::FAILURE;
        }
    }
}

