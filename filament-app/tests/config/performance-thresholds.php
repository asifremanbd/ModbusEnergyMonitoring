<?php

/**
 * Performance Thresholds Configuration
 * 
 * This file defines the performance thresholds for various operations
 * in the Teltonika Gateway Monitor application.
 */

return [
    
    /**
     * Gateway Polling Performance Thresholds
     */
    'polling' => [
        'single_gateway_max_time_ms' => 1000,
        'single_gateway_20_points_max_time_ms' => 1000,
        'multiple_gateways_10x10_max_time_ms' => 5000,
        'high_frequency_polling_avg_time_ms' => 200,
        'high_frequency_polling_max_time_ms' => 500,
        'polling_with_failures_max_time_ms' => 2000,
        'memory_usage_50_gateways_max_mb' => 50,
        'database_writes_100_records_max_time_ms' => 3000,
        'mixed_data_types_max_time_ms' => 1000,
    ],

    /**
     * Database Performance Thresholds
     */
    'database' => [
        'recent_readings_query_max_time_ms' => 500,
        'gateway_readings_query_max_time_ms' => 300,
        'kpi_aggregate_query_max_time_ms' => 200,
        'batch_insert_100_records_max_time_per_record_ms' => 10,
        'batch_insert_500_records_max_time_per_record_ms' => 10,
        'batch_insert_1000_records_max_time_per_record_ms' => 10,
        'batch_insert_2000_records_max_time_per_record_ms' => 10,
        'indexed_query_max_time_ms' => 100,
        'concurrent_read_during_write_max_time_ms' => 50,
        'concurrent_writes_max_time_ms' => 2000,
        'data_retention_deletion_max_time_ms' => 1000,
        'dashboard_kpi_queries_max_time_ms' => 300,
        'live_data_query_max_time_ms' => 200,
    ],

    /**
     * Load Testing Thresholds
     */
    'load' => [
        'concurrent_gateway_creation_avg_time_ms' => 500,
        'concurrent_gateway_creation_max_time_ms' => 2000,
        'concurrent_polling_50_gateways_max_time_ms' => 10000,
        'concurrent_polling_memory_usage_max_mb' => 100,
        'concurrent_dashboard_access_avg_time_ms' => 1000,
        'concurrent_dashboard_access_max_time_ms' => 3000,
        'concurrent_live_data_access_avg_time_ms' => 800,
        'concurrent_live_data_access_max_time_ms' => 2500,
        'concurrent_gateway_operations_total_max_time_ms' => 15000,
        'concurrent_gateway_operations_avg_time_ms' => 600,
        'database_error_rate_max_percent' => 5,
        'database_query_avg_time_max_ms' => 200,
        'sustained_load_memory_increase_max_mb' => 50,
        'sustained_load_peak_memory_max_mb' => 100,
        'cache_operations_avg_time_max_ms' => 10,
        'cache_operations_max_time_ms' => 50,
    ],

    /**
     * User Interface Performance Thresholds
     */
    'ui' => [
        'dashboard_load_time_max_ms' => 1000,
        'gateway_list_load_time_max_ms' => 800,
        'live_data_load_time_max_ms' => 600,
        'gateway_creation_form_load_time_max_ms' => 500,
        'gateway_edit_form_load_time_max_ms' => 500,
        'connection_test_response_time_max_ms' => 5000,
    ],

    /**
     * Memory Usage Thresholds
     */
    'memory' => [
        'single_gateway_polling_max_mb' => 10,
        'multiple_gateway_polling_max_mb' => 50,
        'dashboard_rendering_max_mb' => 20,
        'live_data_rendering_max_mb' => 25,
        'large_dataset_processing_max_mb' => 100,
        'sustained_operations_max_mb' => 75,
    ],

    /**
     * Scalability Thresholds
     */
    'scalability' => [
        'max_concurrent_users' => 50,
        'max_gateways_per_instance' => 100,
        'max_data_points_per_gateway' => 50,
        'max_readings_per_minute' => 5000,
        'max_database_connections' => 20,
    ],

    /**
     * Reliability Thresholds
     */
    'reliability' => [
        'polling_success_rate_min_percent' => 95,
        'database_operation_success_rate_min_percent' => 99,
        'ui_response_success_rate_min_percent' => 99.5,
        'connection_test_success_rate_min_percent' => 90,
        'data_integrity_success_rate_min_percent' => 100,
    ],

    /**
     * Network Performance Thresholds
     */
    'network' => [
        'modbus_connection_timeout_ms' => 5000,
        'modbus_read_timeout_ms' => 3000,
        'websocket_message_delay_max_ms' => 100,
        'api_response_time_max_ms' => 1000,
    ],

    /**
     * Data Quality Thresholds
     */
    'data_quality' => [
        'reading_accuracy_min_percent' => 99.9,
        'timestamp_accuracy_max_drift_ms' => 1000,
        'data_conversion_accuracy_min_percent' => 100,
        'quality_indicator_accuracy_min_percent' => 100,
    ],

    /**
     * Error Handling Thresholds
     */
    'error_handling' => [
        'error_recovery_time_max_ms' => 5000,
        'retry_attempt_delay_max_ms' => 4000,
        'circuit_breaker_failure_threshold' => 10,
        'error_notification_delay_max_ms' => 1000,
    ],

    /**
     * Test Environment Specific Thresholds
     * (More lenient for CI/CD environments)
     */
    'test_environment' => [
        'ci_polling_time_multiplier' => 2.0,
        'ci_database_time_multiplier' => 1.5,
        'ci_memory_usage_multiplier' => 1.3,
        'ci_load_test_time_multiplier' => 2.5,
    ],
];