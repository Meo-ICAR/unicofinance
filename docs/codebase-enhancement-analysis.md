# UnicoFinance Codebase Enhancement Analysis

Date: 2026-04-10

## Purpose

This document captures a reusable analysis of the current UnicoFinance BPM codebase after reviewing the project documentation and selected implementation files. It is intended to give future agents and developers a persistent reference for prioritizing improvements that align with both the actual code and the recommendations in `ENHANCEMENTS.md`.

## Files Reviewed

### Project documentation

- `ENHANCEMENTS.md`
- `ARCHITECTURE.md`
- `README.md`

### Runtime / BPM implementation

- `app/Services/BpmEngineService.php`
- `app/Observers/TaskExecutionChecklistItemObserver.php`
- `app/Filament/Actions/StartProcessAction.php`
- `app/Providers/AppServiceProvider.php`

## Executive Summary

The codebase has a strong BPM architecture centered on a Template → Execution split, with extension points for `BusinessRule` and `BpmAction`, and a good conceptual direction for compliance-heavy workflow automation.

However, the current implementation shows a gap between the intended architecture and the concrete runtime code. The most important near-term work is not adding new advanced BPM features first, but stabilizing and hardening the current execution flow.

The highest-value enhancements are:

1. Resolve dead or inconsistent BPM wiring
2. Make BPM action execution resilient and auditable
3. Add parameterized action support
4. Add full BPM lifecycle integration tests
5. Reduce client-only assumptions in the runtime engine
6. Formalize task execution state transitions

## Codebase Findings

## 1. Dead or inconsistent BPM wiring

### Observed

- In `app/Providers/AppServiceProvider.php`, `RaciAssignmentObserver` is commented out:
    ```php
    // RaciAssignment::observe(RaciAssignmentObserver::class);
    ```
- In `app/Filament/Actions/StartProcessAction.php`, responsible assignment is resolved via:
    ```php
    $responsible = $task->raciAssignments->where('raci_role', 'R')->first();
    ```
- The architecture and enhancement documents consistently refer to a `role` field for RACI assignments, not `raci_role`.
- `StartProcessAction` also includes a fallback notification branch referencing `App\Models\Customer`, while the domain model described and used elsewhere is `Client`.

### Why it matters

This creates drift between:

- architecture documentation
- expected schema conventions
- actual implementation paths

This can produce subtle assignment failures, stale code paths, or future regressions during BPM expansion.

### Recommendation

- Standardize the RACI role field name across models, observers, resources, and actions
- Either restore and validate `RaciAssignmentObserver`, or remove it completely if it is no longer part of the design
- Replace or remove stale `Customer`-based logic in `StartProcessAction`

### Alignment with `ENHANCEMENTS.md`

- **10.1 Resolve Dead Code** — high priority

---

## 2. BPM action execution is synchronous and fragile

### Observed

In `app/Observers/TaskExecutionChecklistItemObserver.php`:

- action execution happens synchronously during the observer update flow
- execution is wrapped in a transaction
- missing classes or wrong interface implementations only trigger warnings in logs
- there is no persistent failure record
- there is no retry mechanism

Relevant behavior:

```php
if (! class_exists($masterItem->action_class)) {
    Log::warning("BPM Action class not found: {$masterItem->action_class}", [
        'checklist_item_id' => $masterItem->id,
    ]);
    return;
}
```

And later:

```php
\Illuminate\Support\Facades\DB::transaction(function () use ($action, $item) {
    $action->execute($item->taskExecution);
});
```

### Why it matters

BPM actions are one of the system’s most important extension points. In a compliance-heavy and operational workflow system, action failures should not be best-effort or log-only.

This is especially risky for:

- notification delivery
- integrations
- compliance side effects
- data synchronization actions

### Recommendation

- Move action execution into a queued job
- Add retry with exponential backoff
- Persist failures and action attempts in a dedicated table such as `process_events` or a dedicated failure log
- Make the observer responsible only for dispatch, not business side effects

### Alignment with `ENHANCEMENTS.md`

- **2.3 Action Failure Retry Queue**
- **2.1 Process Execution Log**

---

## 3. Missing parameterized action support

### Observed

The current runtime flow executes only an `action_class`, and the action receives only the `TaskExecution`. There is no clear support for runtime configuration per checklist item.

### Why it matters

Without parameterization:

- every action variant may require its own PHP class
- configuration is pushed into code instead of data
- the BPM template layer becomes less expressive

### Recommendation

- Add an `action_params` JSON column to `checklist_items`
- Extend the execution flow so these params are passed into the action
- Consider adding a validation/preparation hook such as `beforeExecute()`

### Alignment with `ENHANCEMENTS.md`

- **3.3 Parameterized Actions**
- **3.4 Pre-Action Hooks**

---

## 4. Missing canonical process execution event trail

### Observed

The runtime lifecycle is spread across:

- services
- observers
- notifications
- model boot logic

There is no single event stream that records the lifecycle of a process or task execution.

### Why it matters

Without a canonical event table, debugging and auditing are much harder than they need to be, especially for:

- task state changes
- checklist completion
- action dispatches
- action failures
- SLA events

### Recommendation

Create a `process_events` table to record:

- state transitions
- checklist item completion
- rule evaluation outcome
- action dispatch
- action success/failure
- SLA warnings and breaches

This should become the primary audit timeline for BPM runtime behavior.

### Alignment with `ENHANCEMENTS.md`

- **2.1 Process Execution Log**

---

## 5. The BPM engine is still strongly client-centric

### Observed

In `app/Services/BpmEngineService.php`:

- `getAvailableActions()` and `getAvailableConditions()` derive company context from `$execution->client->company_id`
- `getEvaluatedChecklist()` evaluates rules using `$execution->client`

Relevant examples:

```php
return $this->getOptionsForFilament('actions', $execution->client->company_id);
```

and:

```php
$client = $execution->client;
```

### Why it matters

The architecture describes `TaskExecution` as polymorphic through `target_type` and `target_id`, so execution should be able to support multiple subject models. Current logic makes `Client` the implicit universal target.

That creates friction for:

- employee-based workflows
- future broader polymorphic processes
- cleaner separation between generic BPM runtime and client-specific logic

### Recommendation

- Refactor the BPM engine to use the polymorphic target or another explicit subject abstraction where appropriate
- Keep `Client`-specific rules/actions where needed, but avoid assuming every execution centers on a client
- Clarify which rule contracts are generic vs client-only

### Alignment with `ENHANCEMENTS.md`

- supports the broader architectural goals around polymorphic execution and future extensibility

---

## 6. Task execution state transitions are not formalized

### Observed

The reviewed code uses plain string statuses such as `todo`, but there is no visible formal state guard in the reviewed implementation.

### Why it matters

As workflows become more complex, string-based state changes without a dedicated transition model make invalid transitions easier to introduce and harder to audit.

### Recommendation

Introduce a formal state machine or transition layer for `TaskExecution`, either through:

- `spatie/laravel-model-states`
- Symfony Workflow
- explicit domain transition methods

Suggested transitions:

- `todo -> in_progress`
- `in_progress -> completed`
- optional cancellation or blocked states as needed

### Alignment with `ENHANCEMENTS.md`

- **1.1 State Machine for TaskExecution**

---

## 7. Integration tests are essential for this architecture

### Observed

The BPM runtime relies on:

- model boot hooks
- observers
- Filament actions
- notifications
- action dispatch behavior

These are inherently integration-heavy behaviors.

### Why it matters

This kind of architecture is highly susceptible to regression through small changes in:

- relationships
- observer registration
- action contracts
- transaction boundaries
- notification logic

### Recommendation

Add Pest integration tests that cover:

1. template creation
2. process start
3. task execution creation
4. checklist item cloning
5. checklist completion
6. action execution or dispatch
7. notification delivery behavior
8. failure-path handling

### Alignment with `ENHANCEMENTS.md`

- **7.3 Integration Tests for BPM Flow**
- **5.1 Tenant Isolation Tests**

---

## 8. Eager loading and graph loading should be standardized

### Observed

`BpmEngineService::getEvaluatedChecklist()` builds checklist evaluation through relation traversal:

```php
$items = $execution
    ->processTask
    ->checklists()
    ->with('items')
    ->get()
    ->pluck('items')
    ->flatten();
```

This works, but similar patterns across resources and pages are likely to create N+1 query risks as the system grows.

### Recommendation

Create explicit graph-loading scopes, for example:

- `Process::withFullGraph()`
- `TaskExecution::withRuntimeGraph()`

Then use those consistently in:

- manual viewer pages
- process launch actions
- Filament resources
- dashboards/widgets

### Alignment with `ENHANCEMENTS.md`

- **8.1 Eager Loading Scopes**

---

## Recommended Priority Order

## Phase 1 — Stabilize the current BPM runtime

1. Resolve dead code and stale references
2. Standardize RACI field usage
3. Validate and repair `StartProcessAction` assignment logic

## Phase 2 — Improve reliability and auditability

1. Queue BPM action execution
2. Add retry and failure persistence
3. Create `process_events`

## Phase 3 — Improve extensibility

1. Add `action_params`
2. Add optional pre-execution hooks
3. Reduce hard client-only coupling in the engine

## Phase 4 — Harden architecture

1. Introduce state transitions/state machine
2. Add BPM lifecycle integration tests
3. Add tenant isolation tests

## Phase 5 — Optimize visibility and performance

1. Add eager loading scopes
2. Add audit widgets / operational dashboards
3. Add SLA escalation behavior

## Most Valuable Next Implementation Package

If only one package of work is started next, the recommended bundle is:

1. Resolve dead/inconsistent BPM code paths
2. Queue and log BPM action execution failures
3. Add `action_params` to checklist items
4. Write end-to-end BPM lifecycle tests

This sequence provides:

- immediate correctness improvements
- safer side-effect execution
- better extensibility
- protection against regression

## Suggested Follow-up Tasks

### Immediate

- Inspect the `RaciAssignment` model, migration, and any Filament resources using role fields
- Inspect `TaskExecution` model boot logic and relationships
- Inspect `Process`, `ProcessTask`, and `ChecklistItem` models for consistency with the documented architecture
- Validate how tenant scoping is actually enforced in queries and policies

### Near-term implementation candidates

- `process_events` migration + model + writer service
- queued BPM action dispatcher job
- `action_params` migration and contract update
- BPM integration test suite in Pest

## Notes for Future Agents

When continuing BPM improvement work:

1. Read `ENHANCEMENTS.md` first
2. Reuse this file as a starting analysis, but do not rely on it blindly
3. Re-read the exact implementation files involved in the current task before making changes
4. Respect the Template vs Execution split described in the architecture
5. Prefer Laravel- and Filament-native solutions over ad hoc infrastructure
6. Treat action execution, auditability, and tenant correctness as high-sensitivity areas

## Conclusion

The project already has a strong conceptual BPM foundation. The next stage should focus on closing the gap between architecture and runtime implementation. Stability, observability, and extension-point hardening should come before more ambitious workflow features like subprocesses or parallel execution.
