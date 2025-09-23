<?php

namespace App\Http\Controllers;

use App\Services\ReliablePollingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PollingHealthController extends Controller
{
    public function __construct(
        private ReliablePollingService $pollingService
    ) {}

    /**
     * Get polling system health status.
     */
    public function health(): JsonResponse
    {
        try {
            $status = $this->pollingService->getSystemStatus();
            $issues = $this->pollingService->validatePollingIntegrity();
            
            $health = [
                'status' => empty($issues) ? 'healthy' : 'warning',
                'timestamp' => now()->toISOString(),
                'summary' => $status['summary'],
                'issues_count' => count($issues),
                'issues' => $issues,
            ];
            
            // Determine HTTP status code
            $httpStatus = empty($issues) ? 200 : 207; // 207 = Multi-Status (partial success)
            
            return response()->json($health, $httpStatus);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'timestamp' => now()->toISOString(),
                'message' => 'Failed to check polling system health',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get detailed polling system status.
     */
    public function status(): JsonResponse
    {
        try {
            $status = $this->pollingService->getSystemStatus();
            
            return response()->json([
                'status' => 'success',
                'timestamp' => now()->toISOString(),
                'data' => $status,
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'timestamp' => now()->toISOString(),
                'message' => 'Failed to get polling system status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Trigger system audit and cleanup.
     */
    public function audit(): JsonResponse
    {
        try {
            $results = $this->pollingService->auditAndCleanup();
            
            return response()->json([
                'status' => 'success',
                'timestamp' => now()->toISOString(),
                'message' => 'Audit completed successfully',
                'cleanup_results' => $results,
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'timestamp' => now()->toISOString(),
                'message' => 'Failed to run system audit',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}