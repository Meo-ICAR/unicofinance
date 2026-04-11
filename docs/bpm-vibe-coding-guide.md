# UnicoFinance — BPM Vibe Coding Guide

> Fast implementation guide for coding against the UnicoFinance BPM architecture without breaking the core model.

---

## Stack Reference

- **Framework:** Laravel 13
- **Admin Panel:** Filament 5.4
- **Language:** PHP 8.3+
- **Audit log plugin:** `alizharb/filament-activity-log`
- **Queue system:** Laravel Queues
- **Tenant root:** `Company`

---

## First Principle

Always think in two strictly separated layers:

### 1. Template Layer

This is the **manual / governance / blueprint** layer.

Includes:

- `Process`
- `ProcessTask`
- `Checklist`
- `ChecklistItem`
- `RaciAssignment`
- `ProcessTaskPrivacyData`
- `ProcessMacroCategory`

This layer defines what should happen.

### 2. Execution Layer

This is the **runtime / operations / desk** layer.

Includes:

- `TaskExecution`
- `TaskExecutionChecklistItem`
- `TaskDeadline`

This layer records what is actually happening.

### Rule

Never mix template concerns with execution concerns.

Examples:

- do not mutate template records to track runtime status
- do not infer historical runtime meaning from mutable template records
- do not put execution-only logic into template-only models unless it is version-safe

---

## Architecture Intent

The BPM is used to:

- orchestrate standard operating procedures
- assign responsibilities via RACI
- extract privacy rules from operational processes
- derive permission-relevant relationships
- track execution and compliance
- support GDPR-aware auditability

This means the BPM is both:

- a workflow engine
- a compliance and privacy architecture layer

---

## Core Mental Model

```text
Template defines the procedure.
Execution records the work.
Versioning preserves meaning.
Soft-deletes preserve history.
Domain actions orchestrate behavior.
Queues handle external side effects.
```

---

## Template Versioning Rules

When changing a process template:

- prefer creating a new `Process.version`
- keep previous versions available
- use **soft-deletes only**
- do not destroy template records used in production history

Minimum expectation:

- `processes.version`
- runtime linkage to the used version
- snapshot fields on execution records where needed

Examples of useful runtime snapshots:

- task name
- checklist instruction
- action class
- action params
- assignee resolution context
- relevant rule interpretation

---

## Multi-Tenancy Rules

Tenant root is `Company`.

### Tenant-owned tables

If a table has `company_id`, it is tenant-scoped and must be isolated.

### Shared tables

If a table does not have `company_id`, it is generally a:

- lookup table
- catalog table
- shared template-support table

These are readable by every company unless restricted explicitly.

### Coding rule

Do not assume UI filtering is enough. Enforce tenant boundaries in:

- queries
- policies
- actions
- queued jobs
- notifications
- audit-related reads

---

## RACI Semantics

RACI is not just a label. It has runtime consequences.

### Template meaning

- `R` = Responsible
- `A` = Accountable
- `C` = Consulted
- `I` = Informed

### Runtime meaning

The `R` role must be resolved into a concrete assignee.

Possible template sources:

- `business_function_id`
- `employee_id`
- `client_id`

### Assignee resolution order

1. explicit `employee_id`
2. explicit `client_id`
3. resolve from `business_function_id`
4. if multiple candidates exist, use deterministic resolver
5. if no candidate exists, fail clearly or block execution

Do not blur these concepts:

- business owner
- assignee
- approver
- notification target
- viewer

---

## Preferred Implementation Pattern

For important business behavior, prefer explicit domain actions/services.

### Prefer this

- `StartProcessForTargetAction`
- `ResolveTaskAssigneeAction`
- `CreateTaskExecutionAction`
- `CloneChecklistToExecutionAction`
- `CompleteExecutionChecklistItemAction`
- `AdvanceTaskExecutionStateAction`

### Avoid this

Do not hide core BPM orchestration only inside observers.

Observers are acceptable for:

- lightweight synchronization
- audit enrichment
- dispatching domain events

Observers are not ideal for:

- full process bootstrapping
- major workflow transitions
- external integrations
- multi-record orchestration

---

## Condition and Action Model

### Contracts

- `BusinessRule`: evaluates dynamic conditions
- `BpmAction`: performs side effects

### Checklist item fields

- `skip_condition_class`
- `require_condition_class`
- `action_class`
- `action_params`

### Rule lifecycle

Preferred behavior:

1. evaluate conditions when generating runtime checklist items
2. persist the runtime interpretation
3. avoid reinterpreting past executions after template/rule changes

This is important for compliance and audit consistency.

---

## Discovery Pattern

Do not use `glob()`-based discovery for rules and actions.

Use **container tags**.

### Recommended tags

- `bpm.rule`
- `bpm.action`

### Why

- safer refactors
- better testability
- explicit registration
- easier metadata control
- package-friendly architecture

---

## State Management

`TaskExecution` owns runtime state.

Typical states:

- `todo`
- `in_progress`
- `completed`
- `blocked`
- `failed`

The actual transition logic belongs to dynamic task execution services.

### Rule

Do not treat task status as a template concern.

---

## Transactional Rules

Use database transactions for:

- process start
- task execution creation
- checklist cloning/snapshotting
- state transitions affecting multiple records
- SLA creation coupled to runtime creation

---

## Idempotency Rules

Protect BPM behavior from duplicate triggers.

Recommended safeguards:

- idempotency key on runtime operations
- duplicate-completion guards
- deduplicated action execution
- retry-safe queued jobs

Especially important for:

- emails
- external APIs
- ERP synchronization
- notifications

---

## Queue Strategy

### Queue these

- emails
- external integrations
- retryable side effects
- non-critical notifications
- long-running compliance actions

### Keep synchronous

- validation
- assignee resolution
- required DB writes
- local status transitions

### Important

Queued jobs must preserve tenant context.

---

## Audit and Observability

Audit is not only generic CRUD logging.

Current official audit source:

- `alizharb/filament-activity-log`

But BPM-specific observability should also capture:

- state transitions
- action execution attempts
- rule evaluations
- failures
- SLA events

If you add BPM-critical behavior, think about how it will be debugged later.

---

## Privacy and Permissions

The BPM is also used to extract:

- privacy obligations
- role responsibilities
- permission-relevant relationships
- visibility constraints on regulated data

`ProcessTaskPrivacyData` is part of the architecture, not an accessory.

When implementing features, ask:

1. what data category is touched?
2. under which legal basis?
3. who is allowed to perform the task?
4. who may only view it?
5. what audit evidence is required?

---

## Practical Coding Checklist

Before implementing BPM changes, verify:

- [ ] Is this Template or Execution?
- [ ] Will this change affect historical meaning?
- [ ] Should this create a new process version?
- [ ] Does it require a runtime snapshot?
- [ ] Is tenant isolation preserved?
- [ ] Is assignee resolution deterministic?
- [ ] Should this be a domain action instead of an observer?
- [ ] Does this need a transaction?
- [ ] Does this need idempotency protection?
- [ ] Should this be queued?
- [ ] Is auditability preserved?
- [ ] Does this affect privacy or permission semantics?

---

## Safe Default Recommendations

If uncertain, default to these choices:

- prefer explicit domain services/actions
- prefer runtime snapshots
- prefer soft-deletes over destructive deletes
- prefer versioning over in-place mutation
- prefer queueing for external IO
- prefer container tags over filesystem scanning
- prefer explicit tenant checks over inferred safety
- prefer auditability over clever implicit behavior

---

## Short Architecture Summary

```text
Processes are templates.
TaskExecutions are runtime work.
Templates evolve through versioning.
History is preserved through soft-deletes and snapshots.
RACI defines responsibility, but runtime resolves assignees.
Observers stay thin.
Domain actions orchestrate.
External side effects are queued.
Tenant boundaries are explicit.
The BPM also encodes privacy and permission logic.
```
