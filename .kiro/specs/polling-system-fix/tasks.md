# Implementation Plan

- [x] 1. Create system diagnostic command





  - Build artisan command to check why polling isn't working
  - Check queue workers, Redis connection, and gateway status
  - Show clear output of what's broken and how to fix it
  - _Requirements: 3.4_

- [x] 2. Fix queue worker issues





  - Check if systemd services are running and restart if needed
  - Clear stuck jobs from Redis queues
  - Ensure queue workers are processing polling jobs
  - _Requirements: 3.1, 3.2_

- [x] 3. Fix polling job scheduling





  - Ensure enabled gateways actually have polling jobs scheduled
  - Fix disconnect between gateway polling_enabled flag and actual jobs
  - Start missing polling jobs for enabled gateways
  - _Requirements: 1.1, 1.2, 1.3_

- [x] 4. Add duplicate prevention





  - Add database unique constraint on gateway_id + read_at
  - Update polling code to handle duplicate insert errors gracefully
  - Test that duplicates are prevented under concurrent polling
  - _Requirements: 2.1, 2.2_

- [x] 5. Create polling repair command




  - Build single command to diagnose and fix all polling issues
  - Include options to restart workers, clear queues, and restart polling
  - Add validation that repair actually worked
  - _Requirements: 1.1, 1.4, 3.1, 3.2_

- [x] 6. Test the complete fix





  - Verify enabled gateways show live data in admin interface
  - Confirm past readings are being collected and stored
  - Test that no duplicates are created during normal operation
  - _Requirements: 1.4, 2.3_