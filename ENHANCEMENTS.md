# UnicoFinance — Suggested Enhancements

> Prioritized recommendations based on current BPM architecture review.

---

## 1. Process Execution & Engine

### 1.1 State Machine for TaskExecution
Add `spatie/laravel-model-states` or Symfony Workflow to formalize `TaskExecution` status transitions.

**Why:** Prevents invalid state changes (e.g., `completed` → `in_progress`) that are currently only guarded by application logic.

**Impact:** Medium effort, high reliability gain.

### 1.2 Parallel Task Execution
Support tasks that can run concurrently (not just sequential) via a `dependency_graph` JSON column on `ProcessTask`.

**Why:** Real-world processes have independent steps that shouldn't block each other.

**Impact:** Requires engine changes to `BpmEngineService` and `StartProcessAction`.

### 1.3 Sub-process Invocation
Allow a `ProcessTask` to trigger another `Process` as a nested workflow (e.g., AML check triggers its own sub-process).

**Why:** Promotes template reuse and models complex business workflows.

**Impact:** High effort — requires recursive execution logic and parent/child tracking on `TaskExecution`.

### 1.4 Process Versioning
Add `version` column to `Process` so template changes don't affect in-flight executions.

**Why:** When a process definition is updated, existing tickets should continue using the template version they started with.

**Impact:** Low-medium effort — add version column, clone on edit, reference version in `TaskExecution`.

---

## 2. Observability & Auditing

### 2.1 Process Execution Log
Create a dedicated `process_events` table logging every state transition, action fired, and rule evaluation.

**Why:** Currently this information is scattered across observers and activity logs, making debugging difficult.

**Schema suggestion:**
```
process_events
├── id (UUID)
├── task_execution_id (nullable)
├── event_type (string: 'state_change', 'action_fired', 'rule_evaluated', 'sla_breached')
├── payload (JSON)
├── created_at
```

### 2.2 Audit Dashboard Widget
Build a real-time Filament widget showing:
- Overdue tasks
- Breached SLAs
- Stuck processes (no activity in N days)
- Failed action executions

### 2.3 Action Failure Retry Queue
When an `action_class` throws an exception, push to a retry queue with exponential backoff instead of failing silently.

**Why:** Email sends and external API calls should be resilient to transient failures.

**Impact:** Use Laravel's built-in queue retry mechanism (`--tries=3 --backoff=60,300,900`).

---

## 3. BusinessRule & BpmAction Extensibility

### 3.1 Composite Rules
Support AND/OR/NOT combinations of rules via a `RuleGroup` class.

**Why:** Real conditions are rarely atomic. Example: "Foreigner AND High Net Worth" or "Not EU Citizen".

**Design:**
```php
class RuleGroup implements BusinessRule {
    public function __construct(
        public string $operator, // 'and', 'or', 'not'
        public array $rules,     // [BusinessRule, BusinessRule, ...]
    ) {}

    public function evaluate(Client $client, ?TaskExecution $execution = null): bool {
        $results = array_map(fn($r) => $r->evaluate($client, $execution), $this->rules);
        return match($this->operator) {
            'and' => array_reduce($results, fn($a, $b) => $a && $b, true),
            'or'  => array_reduce($results, fn($a, $b) => $a || $b, false),
            'not' => !$results[0],
        };
    }
}
```

### 3.2 Rule Caching
Cache `BusinessRule::evaluate()` results per client/session to avoid redundant DB hits.

**Why:** The same rule may be evaluated multiple times during checklist rendering and completion.

### 3.3 Parameterized Actions
Add `action_params` JSON column to `checklist_items` to pass runtime configuration to actions.

**Why:** A single `SendEmailAction` could handle multiple templates/recipients without needing a separate class per variant.

```php
// checklist_items table
action_class: "App\Actions\Bpm\SendEmailAction"
action_params: {"template": "welcome_v2", "recipient": "client", "cc": "compliance@..."}
```

### 3.4 Pre-Action Hooks
Add `beforeExecute()` to the `BpmAction` contract for validation/preparation steps.

```php
interface BpmAction {
    public function beforeExecute(TaskExecution $execution, array $params = []): bool;
    public function execute(TaskExecution $execution, array $params = []): void;
}
```

**Why:** Allows actions to validate prerequisites and short-circuit before executing.

---

## 4. SLA & Deadline Management

### 4.1 Escalation Policies
Auto-reassign or notify managers when SLA warning threshold is hit. Link to existing `sla_policies` table.

**Why:** Prevents tasks from silently missing deadlines.

### 4.2 SLA Templates
Predefined SLA profiles (urgent, normal, low) assignable per `ProcessTask`.

**Why:** Not all steps need the same urgency. Template profiles simplify configuration.

### 4.3 Multi-Calendar Support
Support company-specific holiday calendars instead of hardcoded Italian holidays.

**Why:** Multi-tenant companies may operate in different jurisdictions.

---

## 5. Multi-Tenancy & Federation

### 5.1 Tenant Isolation Tests
Add Pest tests ensuring no cross-company data leakage in queries/scopes.

**Why:** Critical for GDPR compliance and multi-tenant security.

### 5.2 Shared Process Templates
Allow a `Process` to be published to a marketplace and cloned by other tenants.

**Why:** Reduces template authoring duplication across companies.

### 5.3 DB-Backed Feature Flags
Store action/condition toggles in the database per company instead of only in `bpm_registry.php` config.

**Why:** Enables runtime admin control without config redeployments.

---

## 6. GDPR & Compliance

### 6.1 Automated DPA Notification
When `DataBreaches.severity = 'high'` and `reported_to_dpa = false`, auto-send email to Data Protection Authority within 72 hours.

### 6.2 Consent Expiry
Track `consent_logs.expires_at` and auto-trigger re-consent processes via a scheduled command.

### 6.3 Data Retention Policies
Auto-archive or purge `TaskExecution` records after a configurable retention period per company.

**Why:** GDPR Article 5(1)(e) — data minimization and storage limitation principle.

---

## 7. Developer Experience

### 7.1 Scaffolding Artisan Commands
```bash
php artisan bpm:make:rule ForeignerRule
php artisan bpm:make:action SendEmailAction
```

Generate boilerplate files implementing the correct contracts with namespace resolution.

### 7.2 Process Visualizer
Render `Process → ProcessTask → Checklist` hierarchy as Mermaid.js diagram or custom SVG in a Filament page.

### 7.3 Integration Tests for BPM Flow
Full lifecycle tests covering:
1. Template creation (Process, ProcessTask, Checklist, ChecklistItem)
2. Process start via `StartProcessAction`
3. Checklist item completion
4. `action_class` trigger verification

---

## 8. Performance

### 8.1 Eager Loading Scopes
Add `Process::withFullGraph()` scope to avoid N+1 queries on `tasks → checklists → items → conditions`.

### 8.2 Checkpoint Snapshots
Serialize `TaskExecutionChecklistItem` state into a JSON snapshot column on `TaskExecution` upon completion for faster historical reads.

### 8.3 Queue Worker Priority
Route `action_class` executions through Laravel queues with priority levels:
- **Critical:** Email sends, compliance notifications
- **Normal:** Status updates
- **Low:** Analytics, audit logging

---

## 9. User Experience

### 9.1 Bulk Process Start
Select multiple clients/employees and start a process for all in one Filament action.

### 9.2 Process Comparison View
Side-by-side view of two `TaskExecution` instances for audit/review purposes.

### 9.3 Checklist Item Comments
Allow assignees to add notes/context to checklist items during execution (stored in `task_execution_checklist_items.notes` text column).

---

## 10. Architecture Hygiene

### 10.1 Resolve Dead Code
- **`RaciAssignmentObserver`** — Uncomment and activate, or remove from `AppServiceProvider`.
- **`SendPrivacyOnboardingEmail`** — Implement or remove (currently a stub).

### 10.2 Form Request Validation
Replace inline Filament form validation with dedicated Form Request classes for complex BPM operations.

### 10.3 Domain Events
Emit decoupled events for key lifecycle moments:
- `ProcessStarted`
- `ProcessCompleted`
- `ChecklistItemCompleted`
- `SlaBreached`

**Why:** Allows listeners to react without coupling to observers. Example: an analytics service could listen for `ProcessCompleted` without modifying the engine.

---

## Priority Matrix

| Priority | Enhancement | Effort | Impact |
|----------|-------------|--------|--------|
| 🔴 High | 10.1 Resolve Dead Code | Low | Low |
| 🔴 High | 2.3 Action Failure Retry Queue | Low | High |
| 🔴 High | 5.1 Tenant Isolation Tests | Medium | Critical |
| 🟡 Medium | 1.4 Process Versioning | Medium | High |
| 🟡 Medium | 2.1 Process Execution Log | Medium | High |
| 🟡 Medium | 3.3 Parameterized Actions | Low | Medium |
| 🟡 Medium | 7.1 Scaffolding Commands | Low | Medium |
| 🟡 Medium | 8.1 Eager Loading Scopes | Low | Medium |
| 🟡 Medium | 9.3 Checklist Item Comments | Low | Medium |
| 🟡 Medium | 10.3 Domain Events | Medium | High |
| 🟢 Low | 1.1 State Machine | Medium | Medium |
| 🟢 Low | 1.2 Parallel Task Execution | High | Medium |
| 🟢 Low | 1.3 Sub-process Invocation | High | High |
| 🟢 Low | 3.1 Composite Rules | Medium | Medium |
| 🟢 Low | 4.1 Escalation Policies | Medium | Medium |
| 🟢 Low | 5.2 Shared Process Templates | High | Low |
| 🟢 Low | 6.1 Automated DPA Notification | Low | High |
| 🟢 Low | 6.2 Consent Expiry | Medium | Medium |
| 🟢 Low | 6.3 Data Retention Policies | Medium | High |
| 🟢 Low | 7.2 Process Visualizer | Medium | Low |
| 🟢 Low | 7.3 Integration Tests | High | High |
| 🟢 Low | 8.2 Checkpoint Snapshots | Medium | Low |
| 🟢 Low | 8.3 Queue Worker Priority | Medium | Medium |
| 🟢 Low | 9.1 Bulk Process Start | Medium | Medium |
| 🟢 Low | 9.2 Process Comparison View | Medium | Low |

---

*Generated from architecture review on 2026-04-10.*
