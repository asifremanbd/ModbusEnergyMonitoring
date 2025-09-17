<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Comprehensive Test Suite Runner for Teltonika Gateway Monitor
 * 
 * This class provides utilities for running the complete test suite
 * and generating performance reports.
 */
class TestSuiteRunner extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Run all test suites and generate comprehensive report
     */
    public static function runComprehensiveTestSuite(): array
    {
        $results = [
            'unit_tests' => self::runUnitTests(),
            'feature_tests' => self::runFeatureTests(),
            'integration_tests' => self::runIntegrationTests(),
            'performance_tests' => self::runPerformanceTests(),
            'load_tests' => self::runLoadTests(),
            'end_to_end_tests' => self::runEndToEndTests(),
        ];

        $results['summary'] = self::generateSummaryReport($results);
        
        return $results;
    }

    /**
     * Run unit tests
     */
    private static function runUnitTests(): array
    {
        $startTime = microtime(true);
        
        // Run unit tests
        $output = shell_exec('cd ' . base_path() . ' && php artisan test --testsuite=Unit --stop-on-failure');
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        return [
            'execution_time_ms' => $executionTime,
            'output' => $output,
            'status' => strpos($output, 'FAILED') === false ? 'PASSED' : 'FAILED',
        ];
    }

    /**
     * Run feature tests
     */
    private static function runFeatureTests(): array
    {
        $startTime = microtime(true);
        
        $output = shell_exec('cd ' . base_path() . ' && php artisan test --testsuite=Feature --stop-on-failure');
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        return [
            'execution_time_ms' => $executionTime,
            'output' => $output,
            'status' => strpos($output, 'FAILED') === false ? 'PASSED' : 'FAILED',
        ];
    }

    /**
     * Run integration tests
     */
    private static function runIntegrationTests(): array
    {
        $startTime = microtime(true);
        
        $output = shell_exec('cd ' . base_path() . ' && php artisan test tests/Integration --stop-on-failure');
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        return [
            'execution_time_ms' => $executionTime,
            'output' => $output,
            'status' => strpos($output, 'FAILED') === false ? 'PASSED' : 'FAILED',
        ];
    }

    /**
     * Run performance tests
     */
    private static function runPerformanceTests(): array
    {
        $startTime = microtime(true);
        
        $output = shell_exec('cd ' . base_path() . ' && php artisan test tests/Performance --stop-on-failure');
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        return [
            'execution_time_ms' => $executionTime,
            'output' => $output,
            'status' => strpos($output, 'FAILED') === false ? 'PASSED' : 'FAILED',
        ];
    }

    /**
     * Run load tests
     */
    private static function runLoadTests(): array
    {
        $startTime = microtime(true);
        
        // Load tests are part of performance tests but can be run separately
        $output = shell_exec('cd ' . base_path() . ' && php artisan test tests/Performance/LoadTest.php --stop-on-failure');
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        return [
            'execution_time_ms' => $executionTime,
            'output' => $output,
            'status' => strpos($output, 'FAILED') === false ? 'PASSED' : 'FAILED',
        ];
    }

    /**
     * Run end-to-end tests
     */
    private static function runEndToEndTests(): array
    {
        $startTime = microtime(true);
        
        $output = shell_exec('cd ' . base_path() . ' && php artisan test tests/EndToEnd --stop-on-failure');
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        return [
            'execution_time_ms' => $executionTime,
            'output' => $output,
            'status' => strpos($output, 'FAILED') === false ? 'PASSED' : 'FAILED',
        ];
    }

    /**
     * Generate summary report
     */
    private static function generateSummaryReport(array $results): array
    {
        $totalTime = 0;
        $passedSuites = 0;
        $totalSuites = 0;

        foreach ($results as $suiteName => $suiteResults) {
            if ($suiteName === 'summary') continue;
            
            $totalSuites++;
            $totalTime += $suiteResults['execution_time_ms'];
            
            if ($suiteResults['status'] === 'PASSED') {
                $passedSuites++;
            }
        }

        return [
            'total_execution_time_ms' => $totalTime,
            'total_execution_time_minutes' => round($totalTime / 60000, 2),
            'total_suites' => $totalSuites,
            'passed_suites' => $passedSuites,
            'failed_suites' => $totalSuites - $passedSuites,
            'success_rate' => round(($passedSuites / $totalSuites) * 100, 2),
            'overall_status' => $passedSuites === $totalSuites ? 'PASSED' : 'FAILED',
        ];
    }

    /**
     * Validate test environment setup
     */
    public static function validateTestEnvironment(): array
    {
        $validations = [];

        // Check database connection
        try {
            DB::connection()->getPdo();
            $validations['database'] = 'PASSED';
        } catch (\Exception $e) {
            $validations['database'] = 'FAILED: ' . $e->getMessage();
        }

        // Check required directories exist
        $requiredDirs = [
            'tests/Unit',
            'tests/Feature', 
            'tests/Integration',
            'tests/Performance',
            'tests/EndToEnd',
        ];

        foreach ($requiredDirs as $dir) {
            $validations["directory_{$dir}"] = is_dir(base_path($dir)) ? 'PASSED' : 'FAILED';
        }

        // Check PHPUnit configuration
        $validations['phpunit_config'] = file_exists(base_path('phpunit.xml')) ? 'PASSED' : 'FAILED';

        // Check test database configuration
        $testDbConfig = config('database.connections.testing');
        $validations['test_db_config'] = $testDbConfig ? 'PASSED' : 'FAILED';

        return $validations;
    }

    /**
     * Generate performance benchmarks
     */
    public static function generatePerformanceBenchmarks(): array
    {
        return [
            'single_gateway_polling' => [
                'target_time_ms' => 1000,
                'description' => 'Single gateway with 20 data points should poll within 1000ms',
            ],
            'multiple_gateway_polling' => [
                'target_time_ms' => 5000,
                'description' => '10 gateways with 10 data points each should poll within 5000ms',
            ],
            'dashboard_load_time' => [
                'target_time_ms' => 1000,
                'description' => 'Dashboard should load within 1000ms under normal load',
            ],
            'live_data_query' => [
                'target_time_ms' => 200,
                'description' => 'Live data query should complete within 200ms',
            ],
            'database_write_performance' => [
                'target_time_ms' => 3000,
                'description' => '100 reading inserts should complete within 3000ms',
            ],
            'concurrent_operations' => [
                'target_time_ms' => 15000,
                'description' => '30 concurrent gateway operations should complete within 15000ms',
            ],
        ];
    }

    /**
     * Check system requirements for testing
     */
    public static function checkSystemRequirements(): array
    {
        $requirements = [];

        // PHP version
        $requirements['php_version'] = [
            'current' => PHP_VERSION,
            'required' => '8.1.0',
            'status' => version_compare(PHP_VERSION, '8.1.0', '>=') ? 'PASSED' : 'FAILED',
        ];

        // Memory limit
        $memoryLimit = ini_get('memory_limit');
        $requirements['memory_limit'] = [
            'current' => $memoryLimit,
            'recommended' => '512M',
            'status' => 'INFO',
        ];

        // Extensions
        $requiredExtensions = ['pdo', 'pdo_sqlite', 'json', 'mbstring', 'openssl'];
        foreach ($requiredExtensions as $extension) {
            $requirements["extension_{$extension}"] = [
                'status' => extension_loaded($extension) ? 'PASSED' : 'FAILED',
            ];
        }

        return $requirements;
    }
}