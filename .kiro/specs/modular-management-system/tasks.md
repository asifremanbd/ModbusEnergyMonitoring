# Implementation Plan

- [x] 1. Database structure optimization and migration




  - Create migration to add missing Gateway model fields (last_seen_at)
  - Update Device model to use proper field names (device_name instead of name)
  - Create migration to rename DataPoint to Register and update field mappings
  - Add proper foreign key constraints and indexes for performance
  - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

- [x] 2. Update and enhance Gateway model and relationships





  - Add last_seen_at field and proper casting to Gateway model
  - Implement devices() relationship method in Gateway model
  - Add registers() hasManyThrough relationship in Gateway model
  - Create gateway status computation methods for UI display
  - Add validation rules for IP address and port combinations
  - _Requirements: 1.1, 1.2, 1.3, 1.7, 8.1, 8.2, 8.3_

- [x] 3. Refactor Device model for proper hierarchy





  - Update Device model fillable fields to match new schema (device_name, enabled)
  - Implement proper relationship methods (gateway, registers)
  - Add device type and load category enum constants and accessors
  - Create device statistics methods (register counts, enabled counts)
  - Add device validation rules and custom validation messages
  - _Requirements: 3.1, 3.2, 3.3, 3.8, 6.4, 8.1, 8.4, 8.5_

- [x] 4. Create Register model from DataPoint refactoring





  - Create new Register model with proper field mappings
  - Implement device() belongsTo relationship
  - Add Modbus function, data type, and byte order enum constants
  - Create register validation methods for address ranges and Modbus constraints
  - Add register conversion and scaling methods
  - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.10, 6.5, 8.1, 8.4_

- [x] 5. Enhance GatewayResource with improved statistics and navigation





  - Update Gateway table columns to show device count and register count
  - Add gateway status badge with color coding based on last_seen_at
  - Implement connection test action with proper error handling
  - Update gateway form with all required fields and validation
  - Add "Manage Devices" action button for navigation to device management
  - _Requirements: 1.1, 1.2, 1.3, 1.6, 2.1, 2.2, 2.3, 8.1, 8.2_

- [x] 6. Refactor ManageGatewayDevices page for proper Device model integration





  - Update device table query to use Device model instead of DataPoint grouping
  - Modify device creation form to create Device records with proper relationships
  - Update device editing to modify Device model fields
  - Implement device deletion with cascade to registers
  - Add device statistics display (register counts, enabled status)
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 4.1, 4.2, 4.3, 6.2_

- [x] 7. Update ManageDeviceRegisters page for Register model





  - Modify register table query to use Register model with device relationship
  - Update register creation form to create Register records linked to Device
  - Implement register editing with proper Modbus field validation
  - Add bulk enable/disable actions for register management
  - Update register deletion and bulk operations
  - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7, 5.8, 5.9, 5.10, 6.3_

- [x] 8. Implement comprehensive form validation and error handling





  - Add custom validation rules for IP addresses, port ranges, and Modbus addresses
  - Create user-friendly error messages for all validation scenarios
  - Implement proper exception handling in form submissions
  - Add validation for unique constraints and foreign key relationships
  - Create validation feedback for real-time form validation
  - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6_

- [x] 9. Enhance navigation and breadcrumb system





  - Update breadcrumb generation to show proper hierarchy context
  - Implement consistent "Back" navigation buttons across all levels
  - Add parent item information display on child pages
  - Create navigation state preservation for filters and sorting
  - Update page titles to reflect current context and parent items
  - _Requirements: 7.1, 7.2, 7.3, 7.4, 2.2, 2.3, 4.2, 4.3_

- [x] 10. Create DeviceResource for standalone device management


















  - Implement DeviceResource with proper gateway relationship display
  - Add device table with gateway name, type, category, and register counts
  - Create device form with gateway selection and device configuration
  - Implement device filtering by gateway and device type
  - Add device actions for register management navigation
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8_
-

- [x] 11. Write comprehensive unit tests for models





  - Create unit tests for Gateway model relationships and methods
  - Write unit tests for Device model validation and relationships
  - Implement unit tests for Register model Modbus functionality
  - Test model scopes, accessors, and computed attributes
  - Validate model casting and data type conversions
  - _Requirements: All model-related requirements_

- [x] 12. Create integration tests for FilamentPHP resources






  - Write integration tests for GatewayResource CRUD operations
  - Test ManageGatewayDevices page functionality and navigation
  - Create tests for ManageDeviceRegisters page operations
  - Test form validation and error handling across all resources
  - Validate navigation flow and breadcrumb functionality
  - _Requirements: All UI and navigation requirements_

- [x] 13. Implement data migration from current structure






  - Create migration script to populate Device table from existing DataPoint data
  - Update existing DataPoint records to reference new Device records
  - Migrate device-specific fields from DataPoint to Device model
  - Ensure data integrity during migration process
  - Create rollback procedures for migration safety
  - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

- [x] 14. Add performance optimizations and indexing





  - Create database indexes for foreign key relationships
  - Implement eager loading for relationship queries in resources
  - Add query optimization for device and register counting
  - Create efficient pagination for large datasets
  - Implement caching for frequently accessed gateway statistics
  - _Requirements: 1.3, 3.1, 5.1, 7.3_

- [ ] 15. Final integration testing and UI polish










  - Test complete workflow from gateway creation to register management
  - Validate all navigation paths and breadcrumb accuracy
  - Test bulk operations and error handling scenarios
  - Verify responsive design and mobile compatibility
  - Conduct user acceptance testing for workflow efficiency
  - _Requirements: All requirements validation_/