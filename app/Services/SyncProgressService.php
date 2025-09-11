<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncProgressService
{
    private const CACHE_PREFIX = 'sync_progress_';
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Initialize progress tracking for a service and operation
     */
    public function initializeProgress(string $service, string $operation, array $initialData = []): void
    {
        $key = $this->getCacheKey($service, $operation);
        
        $progress = [
            'service' => $service,
            'operation' => $operation,
            'status' => 'initializing',
            'progress' => array_merge([
                'total_employees' => 0,
                'total_batches' => 0,
                'processed_employees' => 0,
                'processed_batches' => 0,
                'current_batch' => 0,
                'api_calls_made' => 0,
                'current_employee' => null,
                'errors' => []
            ], $initialData),
            'message' => 'Initializing sync operation...',
            'started_at' => now()->toISOString(),
            'completed_at' => null
        ];

        Cache::put($key, $progress, self::CACHE_TTL);
        
        Log::info('Sync progress initialized', [
            'service' => $service,
            'operation' => $operation,
            'progress' => $progress
        ]);
    }

    /**
     * Update progress for a service and operation
     */
    public function updateProgress(string $service, string $operation, array $updateData): void
    {
        $key = $this->getCacheKey($service, $operation);
        $progress = Cache::get($key);

        if (!$progress) {
            Log::warning('Attempted to update progress that does not exist', [
                'service' => $service,
                'operation' => $operation
            ]);
            return;
        }

        // Update progress data
        $progress['progress'] = array_merge($progress['progress'], $updateData);
        $progress['status'] = 'in_progress';
        $progress['message'] = $this->generateProgressMessage($progress);

        Cache::put($key, $progress, self::CACHE_TTL);
        
        Log::debug('Sync progress updated', [
            'service' => $service,
            'operation' => $operation,
            'update_data' => $updateData
        ]);
    }

    /**
     * Complete progress for a service and operation
     */
    public function completeProgress(string $service, string $operation, array $finalData = []): void
    {
        $key = $this->getCacheKey($service, $operation);
        $progress = Cache::get($key);

        if (!$progress) {
            Log::warning('Attempted to complete progress that does not exist', [
                'service' => $service,
                'operation' => $operation
            ]);
            return;
        }

        // Update final progress data
        $progress['progress'] = array_merge($progress['progress'], $finalData);
        $progress['status'] = 'completed';
        $progress['message'] = 'Sync operation completed successfully';
        $progress['completed_at'] = now()->toISOString();

        Cache::put($key, $progress, self::CACHE_TTL);
        
        Log::info('Sync progress completed', [
            'service' => $service,
            'operation' => $operation,
            'final_data' => $finalData
        ]);
    }

    /**
     * Mark progress as failed for a service and operation
     */
    public function failProgress(string $service, string $operation, string $errorMessage): void
    {
        $key = $this->getCacheKey($service, $operation);
        $progress = Cache::get($key);

        if (!$progress) {
            Log::warning('Attempted to fail progress that does not exist', [
                'service' => $service,
                'operation' => $operation
            ]);
            return;
        }

        $progress['status'] = 'failed';
        $progress['message'] = $errorMessage;
        $progress['completed_at'] = now()->toISOString();

        Cache::put($key, $progress, self::CACHE_TTL);
        
        Log::error('Sync progress failed', [
            'service' => $service,
            'operation' => $operation,
            'error_message' => $errorMessage
        ]);
    }

    /**
     * Get progress for a service and operation
     */
    public function getProgress(string $service, string $operation): ?array
    {
        $key = $this->getCacheKey($service, $operation);
        return Cache::get($key);
    }

    /**
     * Get all active progress
     */
    public function getAllProgress(): array
    {
        $allProgress = [];

        // Get all cache keys with our prefix
        $keys = Cache::get('sync_progress_keys', []);
        
        foreach ($keys as $key) {
            $progress = Cache::get($key);
            if ($progress && $progress['status'] !== 'completed' && $progress['status'] !== 'failed') {
                $allProgress[] = $progress;
            }
        }

        return $allProgress;
    }

    /**
     * Clear progress for a service and operation
     */
    public function clearProgress(string $service, string $operation): void
    {
        $key = $this->getCacheKey($service, $operation);
        Cache::forget($key);
        
        Log::info('Sync progress cleared', [
            'service' => $service,
            'operation' => $operation
        ]);
    }

    /**
     * Clear all progress
     */
    public function clearAllProgress(): void
    {
        $keys = Cache::get('sync_progress_keys', []);
        
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        
        Cache::forget('sync_progress_keys');
        
        Log::info('All sync progress cleared');
    }

    /**
     * Generate a human-readable progress message
     */
    private function generateProgressMessage(array $progress): string
    {
        $data = $progress['progress'];
        $operation = $progress['operation'];
        
        switch ($operation) {
            case 'directory':
                $processed = $data['processed_employees'] ?? 0;
                $total = $data['total_employees'] ?? 0;
                $batches = $data['processed_batches'] ?? 0;
                $totalBatches = $data['total_batches'] ?? 0;
                
                return "Processing directory sync: {$processed}/{$total} employees ({$batches}/{$totalBatches} batches)";
                
            case 'detailed':
                $processed = $data['processed_employees'] ?? 0;
                $total = $data['total_employees'] ?? 0;
                $apiCalls = $data['api_calls_made'] ?? 0;
                $currentEmployee = $data['current_employee'] ?? null;
                
                $message = "Processing detailed sync: {$processed}/{$total} employees ({$apiCalls} API calls)";
                if ($currentEmployee) {
                    $message .= " - Currently: {$currentEmployee}";
                }
                
                return $message;
                
            default:
                return "Processing {$operation} sync...";
        }
    }

    /**
     * Get cache key for service and operation
     */
    private function getCacheKey(string $service, string $operation): string
    {
        return self::CACHE_PREFIX . $service . '_' . $operation;
    }
}