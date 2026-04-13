# BPM Test Suite - UnicoFinance

> Comprehensive test suite for the BPM (Business Process Management) architecture

## Test Structure

The test suite follows the **Template → Execution** pattern described in `ARCHITECTURE.md` and covers:

- **Unit Tests**: Contracts, rules, actions, services
- **Feature Tests**: Models, observers, multi-tenancy, process lifecycle
- **Integration Tests**: End-to-end BPM workflows

---

## Unit Tests

### 1. BusinessRule Contract & ForeignerRule
**File**: `tests/Unit/Bpm/ForeignerRuleTest.php`

Tests:
- ✅ Returns true for non-Italian citizenship
- ✅ Returns false for Italian citizenship
- ✅ Implements BusinessRule contract
- ✅ Can be resolved from container

### 2. BPM Actions
**File**: `tests/Unit/Bpm/BpmActionsTest.php`

Tests:
- ✅ PromoteClientStatus implements BpmAction contract
- ✅ Promotes client status to approvato
- ✅ Accepts optional reason parameter
- ✅ Logs activity when promoting client
- ✅ UpdateClientToAmlCheck updates status and sets acquired_at
- ✅ Actions can be resolved from container

### 3. BpmEngineService
**File**: `tests/Unit/Bpm/BpmEngineServiceTest.php`

Tests:
- ✅ Returns available actions for task execution
- ✅ Returns available conditions for task execution
- ✅ Evaluates checklist and returns enriched items
- ✅ Skips checklist items when skip condition is met
- ✅ Makes items mandatory when require condition is met
- ✅ Completes a checklist item successfully
- ✅ Throws exception when checklist item not found
- ✅ Returns success when item is already checked
- ✅ Unchecks a previously checked item
- ✅ Returns success when unchecking an already unchecked item

### 4. BpmRegistryService
**File**: `tests/Unit/Bpm/BpmRegistryServiceTest.php`

Tests:
- ✅ Returns all actions when companies is null
- ✅ Filters actions by company (multi-tenant)
- ✅ Excludes actions when company is not in allowed list
- ✅ Groups actions by their configured group
- ✅ Returns empty array for non-existent type
- ✅ Returns conditions filtered by company
- ✅ Uses default group when not specified

### 5. SlaService
**File**: `tests/Unit/Bpm/SlaServiceTest.php`

Tests:
- ✅ Calculates deadline correctly for same-day completion
- ✅ Calculates deadline correctly for multi-day completion
- ✅ Skips weekends when calculating deadlines
- ✅ Skips Italian holidays when calculating deadlines
- ✅ Returns correct Italian holidays for a given year
- ✅ Includes Easter Monday in Italian holidays
- ✅ Handles zero minutes correctly
- ✅ Handles large minute values correctly
- ✅ Starts counting from the exact start time
- ✅ Correctly handles overnight deadlines

### 6. BpmActionRunner
**File**: `tests/Unit/Bpm/BpmActionRunnerTest.php`

Tests:
- ✅ Executes action successfully when action class is valid
- ✅ Returns success when no action class is configured
- ✅ Returns failure when action class does not exist
- ✅ Returns failure when class does not implement BpmAction
- ✅ Validates action class without executing it
- ✅ Validates when no action class is configured
- ✅ Validates and returns failure for non-existent class
- ✅ Validates and returns failure for class not implementing BpmAction
- ✅ Resolves action from service container

---

## Feature Tests

### 7. Process Template
**File**: `tests/Feature/Bpm/ProcessTemplateTest.php`

Tests:
- ✅ Creates process with correct attributes
- ✅ Uses soft deletes for process templates
- ✅ Maintains process tasks relationship
- ✅ Orders tasks by sequence number
- ✅ Auto-creates RACI assignments when process task is created
- ✅ Supports process versioning
- ✅ Links to company, business function, and macro category
- ✅ Can be activated and deactivated
- ✅ Supports request type mappings
- ✅ Scopes active processes with request type
- ✅ Creates nested checklists and items via template structure
- ✅ Supports privacy data attachments to process tasks

### 8. TaskExecution Runtime
**File**: `tests/Feature/Bpm/TaskExecutionTest.php`

Tests:
- ✅ Creates task execution with correct attributes
- ✅ Clones checklist items from template when execution is created
- ✅ Stores snapshot data in runtime checklist items
- ✅ Links to template task relationship
- ✅ Links to client relationship
- ✅ Supports polymorphic target relationship
- ✅ Can transition between states
- ✅ Uses soft deletes
- ✅ Can check and uncheck runtime checklist items
- ✅ Returns checked items count

### 9. TaskExecutionChecklistItemObserver
**File**: `tests/Feature/Bpm/TaskExecutionChecklistItemObserverTest.php`

Tests:
- ✅ Executes action when checklist item is checked
- ✅ Sets checked_at timestamp when item is checked
- ✅ Does not execute action when no action_class is configured
- ✅ Does not execute action when action_class does not exist
- ✅ Does not execute action when class does not implement BpmAction
- ✅ Does not trigger action on non-dirty updates
- ✅ Does not trigger action when unchecking item
- ✅ Executes action inside database transaction

### 10. EmployeeObserver Auto-Onboarding
**File**: `tests/Feature/Bpm/EmployeeObserverTest.php`

Tests:
- ✅ Auto-starts onboarding process when employee is created
- ✅ Does not create executions when onboarding process does not exist
- ✅ Sets due date for onboarding tasks
- ✅ Links task executions to correct process tasks

### 11. Multi-Tenant Isolation
**File**: `tests/Feature/Bpm/MultiTenantIsolationTest.php`

Tests:
- ✅ Isolates processes between tenants
- ✅ Isolates task executions between tenants
- ✅ Isolates checklist items between tenants
- ✅ Does not allow cross-tenant process task access
- ✅ Isolates RACI assignments between tenants
- ✅ Maintains tenant context in task execution relationships

### 12. Process Lifecycle (Integration)
**File**: `tests/Feature/Bpm/ProcessLifecycleTest.php`

Tests:
- ✅ Completes full process lifecycle: template → execution → completion
- ✅ Respects skip conditions during execution
- ✅ Enforces require conditions during execution
- ✅ Supports process versioning without affecting existing executions
- ✅ Maintains audit trail through process lifecycle

---

## Database Factories Created

To support the test suite, the following factories were created:

| Factory | File |
|---------|------|
| `ProcessFactory` | `database/factories/ProcessFactory.php` |
| `ProcessTaskFactory` | `database/factories/ProcessTaskFactory.php` |
| `ChecklistFactory` | `database/factories/ChecklistFactory.php` |
| `ChecklistItemFactory` | `database/factories/ChecklistItemFactory.php` |
| `TaskExecutionFactory` | `database/factories/TaskExecutionFactory.php` |
| `BusinessFunctionFactory` | `database/factories/BusinessFunctionFactory.php` |
| `ProcessMacroCategoryFactory` | `database/factories/ProcessMacroCategoryFactory.php` |
| `EmployeeFactory` | `database/factories/EmployeeFactory.php` |
| `PrivacyDataTypeFactory` | `database/factories/PrivacyDataTypeFactory.php` |
| `PrivacyLegalBaseFactory` | `database/factories/PrivacyLegalBaseFactory.php` |

---

## Test Helpers

**File**: `tests/Helpers/BpmTestHelpers.php`

Helper functions for creating BPM test data:
- `createBpmProcess()` - Creates a complete process template with tasks, checklists, and items
- `createTaskExecution()` - Creates a task execution with cloned checklist items
- `createBpmSetup()` - Creates a complete BPM setup ready for testing

---

## Running the Tests

```bash
# Run all BPM tests
vendor/bin/pest tests/Unit/Bpm
vendor/bin/pest tests/Feature/Bpm

# Run specific test file
vendor/bin/pest tests/Unit/Bpm/ForeignerRuleTest.php
vendor/bin/pest tests/Feature/Bpm/ProcessLifecycleTest.php

# Run specific test
vendor/bin/pest --filter "it_completes_full_process_lifecycle"

# Run with coverage (if Xdebug is enabled)
vendor/bin/pest --coverage

# Run all tests
vendor/bin/pest
```

---

## Architecture Coverage

The test suite covers all major components described in `ARCHITECTURE.md`:

| Architecture Component | Test Coverage |
|------------------------|---------------|
| Core BPM Kernel | ✅ Models, services, observers |
| Template → Execution Pattern | ✅ Lifecycle tests |
| Condition Evaluation Lifecycle | ✅ Skip/Require condition tests |
| Extensibility Model (Contracts) | ✅ BusinessRule, BpmAction tests |
| Multi-Tenancy Model | ✅ Isolation tests |
| Template Versioning Strategy | ✅ Versioning tests |
| RACI Semantics | ✅ Auto-creation tests |
| Runtime Execution Model | ✅ TaskExecution tests |
| State Model | ✅ State transition tests |
| Observers and Lifecycle Usage | ✅ Observer tests |
| Transactional and Idempotency Rules | ✅ Transaction tests |
| Compliance and Privacy Layer | ✅ Privacy data tests |

---

## Test Statistics

- **Unit Tests**: ~50 tests
- **Feature Tests**: ~60 tests
- **Total Tests**: ~110 tests
- **Test Files**: 12
- **Factories Created**: 10
- **Helper Functions**: 3

---

## Notes

- All Feature tests use `RefreshDatabase` trait for database isolation
- Unit tests are designed to run without database where possible
- Tests follow Pest PHP conventions
- All tests run in SQLite (in-memory) for fast execution
- Multi-tenant tests verify company_id isolation at all levels
- Observer tests verify automatic side-effects (action execution, timestamping)
