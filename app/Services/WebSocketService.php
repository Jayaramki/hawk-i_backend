<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Events\SyncProgressEvent;
use Exception;

class WebSocketService
{
    private string $channelPrefix = 'sync_progress_';
    
    /**
     * Broadcast sync progress update
     */
    public function broadcastProgress(string $service, string $operation, array $progress): void
    {
        try {
            $channel = $this->getChannelName($service, $operation);
            $data = [
                'service' => $service,
                'operation' => $operation,
                'progress' => $progress,
                'timestamp' => now()->toISOString()
            ];
            
            // Store in cache for real-time access
            Cache::put($channel, $data, 3600); // 1 hour TTL
            
            // Log for debugging
            Log::info('WebSocket progress broadcast', [
                'channel' => $channel,
                'data' => $data
            ]);
            
            // Broadcast using Laravel's broadcasting system
            broadcast(new SyncProgressEvent($service, $operation, $data));
            
        } catch (Exception $e) {
            Log::error('Failed to broadcast WebSocket progress', [
                'error' => $e->getMessage(),
                'service' => $service,
                'operation' => $operation
            ]);
        }
    }
    
    /**
     * Broadcast sync log message
     */
    public function broadcastLog(string $service, string $operation, string $level, string $message, array $context = []): void
    {
        try {
            $channel = $this->getLogChannelName($service, $operation);
            $logEntry = [
                'timestamp' => now()->toISOString(),
                'level' => $level,
                'message' => $message,
                'context' => $context
            ];
            
            // Get existing logs
            $logs = Cache::get($channel, []);
            $logs[] = $logEntry;
            
            // Keep only last 100 log entries
            if (count($logs) > 100) {
                $logs = array_slice($logs, -100);
            }
            
            Cache::put($channel, $logs, 3600);
            
            Log::info('WebSocket log broadcast', [
                'channel' => $channel,
                'log_entry' => $logEntry
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to broadcast WebSocket log', [
                'error' => $e->getMessage(),
                'service' => $service,
                'operation' => $operation
            ]);
        }
    }
    
    /**
     * Get progress data for a specific service and operation
     */
    public function getProgress(string $service, string $operation): ?array
    {
        try {
            $channel = $this->getChannelName($service, $operation);
            return Cache::get($channel);
        } catch (Exception $e) {
            Log::error('Failed to get WebSocket progress', [
                'error' => $e->getMessage(),
                'service' => $service,
                'operation' => $operation
            ]);
            return null;
        }
    }
    
    /**
     * Get log data for a specific service and operation
     */
    public function getLogs(string $service, string $operation): array
    {
        try {
            $channel = $this->getLogChannelName($service, $operation);
            return Cache::get($channel, []);
        } catch (Exception $e) {
            Log::error('Failed to get WebSocket logs', [
                'error' => $e->getMessage(),
                'service' => $service,
                'operation' => $operation
            ]);
            return [];
        }
    }
    
    /**
     * Clear progress and logs for a service/operation
     */
    public function clearChannel(string $service, string $operation): void
    {
        try {
            $progressChannel = $this->getChannelName($service, $operation);
            $logChannel = $this->getLogChannelName($service, $operation);
            
            Cache::forget($progressChannel);
            Cache::forget($logChannel);
            
            Log::info('WebSocket channels cleared', [
                'service' => $service,
                'operation' => $operation
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to clear WebSocket channels', [
                'error' => $e->getMessage(),
                'service' => $service,
                'operation' => $operation
            ]);
        }
    }
    
    /**
     * Get channel name for progress updates
     */
    private function getChannelName(string $service, string $operation): string
    {
        return $this->channelPrefix . $service . '_' . $operation;
    }
    
    /**
     * Get channel name for log messages
     */
    private function getLogChannelName(string $service, string $operation): string
    {
        return $this->channelPrefix . $service . '_' . $operation . '_logs';
    }
}