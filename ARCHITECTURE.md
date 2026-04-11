# UnicoFinance — BPM Architecture Document

> **Business Process Management platform** for GDPR-aware workflow automation, operational accountability, privacy-rule extraction, and permission orchestration in financial advisory firms.

---

## Document Metadata

- **Document version:** 2.0
- **Last updated:** 2026-04-11
- **Verified stack:** Laravel 13, PHP 8.3+, Filament 5.4
- **Audit logging:** `alizharb/filament-activity-log`
- **Primary architectural pattern:** Template → Execution
- **Reference note:** This document is intended to be a reliable architectural reference for development, refactoring, and future BPM evolution.

### Scope

This document describes:

- the BPM core architecture
- the separation between template design and runtime execution
- the multi-tenant model
- the extensibility model for rules and actions
- the operational guarantees expected from the engine
- the architectural decisions and tradeoffs behind the current direction

### Architectural Invariant

> Once a process template is used to generate runtime executions, later edits must not alter the historical meaning of already-started work. Runtime records must therefore behave as immutable operational snapshots of the relevant template state, while template evolution is handled through versioning and soft-deletes rather than destructive replacement.

---

## Technology Stack

| Layer                  | Technology                       |
| ---------------------- | -------------------------------- |
| **Framework**          | Laravel 13                       |
| **Language**           | PHP 8.3+                         |
| **Admin Panel**        | Filament 5.4                     |
| **Authentication**     | Laravel Socialite                |
| **Authorization**      | Policies + role/permission layer |
| **Audit Trail**        | `alizharb/filament-activity-log` |
| **Media**              | Spatie Media Library             |
| **Testing**            | Pest PHP                         |
| **Queue / Async**      | Laravel Queues                   |
| **Multi-tenancy root** | `Company`                        |

---

## Purpose of the BPM

The BPM is not only used to orchestrate work. It is also designed to:

- define and enforce standard operating procedures
- assign responsibilities through RACI semantics
- extract privacy rules from operational templates
- connect process steps to compliance metadata
- derive permissions and visibility rules from organizational structure
- generate runtime work items that can be tracked, audited, and completed
- provide a compliant operational history of who did what, when, and under which rule set

In practice, the BPM acts as a **procedural backbone** for:

1. **operational execution**
2. **compliance enforcement**
3. **privacy governance**
4. **permission-aware navigation and visibility**
5. **tenant-specific business automation**

---

## Bounded Architecture Layers

The system should be reasoned about in four layers.

### 1. Core BPM Kernel

The reusable workflow engine:

- `Process`
- `ProcessTask`
- `Checklist`
- `ChecklistItem`
- `RaciAssignment`
- `TaskExecution`
- `TaskExecutionChecklistItem`
- BPM services, actions, rules, events, and orchestration logic

### 2. Compliance Modules

Modules that attach legal and audit meaning to the BPM:

- `ProcessTaskPrivacyData`
- `PrivacyDataType`
- `PrivacyLegalBase`
- `RequestRegistry`
- `ConsentLog`
- `SuppressionList`
- `DataBreach`
- SLA and deadline tracking
- activity logs and event history

### 3. Domain-Specific Workflows

Concrete workflows built on top of the BPM engine:

- employee onboarding
- client onboarding
- AML-related flows
- GDPR request handling
- proforma and invoice generation
- lead transfer / reassignment processes
- recruitment / agent workflows

### 4. Integrations

External or infrastructure-connected behaviors:

- email delivery
- SAP or external ERP synchronization
- Socialite login providers
- queued notifications
- external APIs triggered by BPM actions

This separation keeps the BPM reusable while allowing compliance and business-specific modules to evolve independently.

---

## Core Concept: Template → Execution Pattern

The BPM follows a strict **two-phase design**.

### Template Phase

This is the manual, governance, and modeling layer.

Template records define:

- what the process is
- which steps it contains
- which checklist instructions belong to each step
- who is responsible, accountable, consulted, and informed
- which privacy/legal metadata applies
- which dynamic rules and actions are attached

Template entities are **definitions**, not live work.

### Execution Phase

This is the operational layer.

Execution records define:

- which concrete work item was generated
- who is currently assigned to perform it
- which checklist items are active in runtime
- what has been completed
- which deadlines and SLA timestamps apply
- which events and actions occurred during execution

Execution entities are **runtime operational snapshots**, not editable templates.

### Boundary Rules

The Template → Execution boundary must remain strict:

- template data defines behavior
- execution data records actual work
- execution records must not derive their meaning from future template edits
- runtime status changes do not mutate template definitions
- templates may evolve, but already-started work preserves the original business meaning through versioning and soft-deletes

### Minimum Recommendation

To preserve auditability and deterministic runtime behavior, the minimum architecture recommendation is:

- use `version` on `processes`
- keep historical template records via **soft-deletes only**
- avoid destructive cancellation/removal of templates used in production
- store the essential execution context as a snapshot on runtime records where needed:
    - task name
    - checklist instruction
    - action class
    - assignee resolution context
    - relevant rule metadata

---

## High-Level Domain Flow

```text
TEMPLATE LAYER
Process
 └── ProcessTask
      ├── Checklist
      │    └── ChecklistItem
      ├── RaciAssignment
      └── ProcessTaskPrivacyData

            │ publish / select version
            │ start process for target
            ▼

EXECUTION LAYER
TaskExecution
 ├── assignee context
 ├── target morph
 ├── status / timestamps
 ├── TaskExecutionChecklistItem
 ├── TaskDeadline
 └── process/runtime events
```

---

## Data Model

### Primary Template Entities

| Entity                   | Purpose                                                    |
| ------------------------ | ---------------------------------------------------------- |
| `Process`                | Top-level process template definition                      |
| `ProcessTask`            | Ordered or engine-managed task definition inside a process |
| `Checklist`              | Logical instruction group attached to a task               |
| `ChecklistItem`          | Atomic executable/trackable instruction                    |
| `RaciAssignment`         | Responsibility model for the task                          |
| `ProcessTaskPrivacyData` | Privacy/legal metadata attached to a task                  |
| `ProcessMacroCategory`   | Macro classification for the process                       |

### Primary Runtime Entities

| Entity                       | Purpose                                                     |
| ---------------------------- | ----------------------------------------------------------- |
| `TaskExecution`              | Runtime instance of work generated from a process task      |
| `TaskExecutionChecklistItem` | Runtime checklist instance cloned/snapshotted from template |
| `TaskDeadline`               | SLA/deadline tracking for runtime execution                 |

### Core Tables

| Table                            | Role                           | Key Columns                                                                                                                                                                     |
| -------------------------------- | ------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `companies`                      | Tenant root                    | `id`, `name`, `domain`, `vat_number`, `oam`, `ivass`                                                                                                                            |
| `company_branches`               | Branch offices                 | `company_id`, `name`, manager metadata                                                                                                                                          |
| `processes`                      | Process template               | `company_id`, `owner_function_id`, `process_macro_category_id`, `name`, `description`, `target_model`, `version`, `is_active`, `deleted_at`                                     |
| `process_request_mappings`       | Request type → process mapping | `request_type`, `process_id`, `is_suggested`                                                                                                                                    |
| `process_tasks`                  | Task template                  | `process_id`, `sequence_number`, `name`, `description`, `deleted_at`                                                                                                            |
| `checklists`                     | Checklist template group       | `process_task_id`, `name`, `description`, `sort_order`, `deleted_at`                                                                                                            |
| `checklist_items`                | Atomic template instruction    | `checklist_id`, `instruction`, `is_mandatory`, `sort_order`, `require_condition_class`, `skip_condition_class`, `action_class`, `action_params`, `deleted_at`                   |
| `raci_assignments`               | RACI definition                | `process_task_id`, `business_function_id`, `employee_id`, `client_id`, `role`, `deleted_at`                                                                                     |
| `process_task_privacy_data`      | GDPR metadata                  | `process_task_id`, `privacy_data_type_id`, `privacy_legal_base_id`, `access_level`, `purpose`, `is_encrypted`, `is_shared_externally`                                           |
| `task_executions`                | Runtime work item              | `process_task_id`, `process_version`, `employee_id`, `client_id`, `status`, `due_date`, `started_at`, `completed_at`, `target_type`, `target_id`, `snapshot`, `idempotency_key` |
| `task_execution_checklist_items` | Runtime checklist item         | `task_execution_id`, `checklist_item_id`, `instruction_snapshot`, `action_class_snapshot`, `action_params_snapshot`, `is_checked`, `checked_at`                                 |
| `task_deadlines`                 | SLA tracking                   | `task_execution_id`, `status`, `warning_at`, `breached_at`, `completed_at`                                                                                                      |

---

## Multi-Tenancy Model

The system is multi-tenant with `Company` as the root tenant.

### Tenant-Scoped Tables

Tables that contain `company_id` are tenant-owned data and must always be isolated by tenant context.

Examples:

- `processes`
- `employees`
- `clients`
- `business_functions`
- `request_registries`
- `sla_policies`
- `holidays`
- `proformas`
- `commissions`

### Shared Lookup or Template Tables

Tables without `company_id` are generally one of these:

1. **lookup tables**
2. **reference catalogs**
3. **shared template-support tables**
4. **system-wide enum-like records**

These tables can be read by every company unless additional restrictions are explicitly introduced.

Examples:

- `privacy_data_types`
- `privacy_legal_bases`
- some classification catalogs
- framework/system support tables

### Tenant Rules

- tenant scoping must be enforced at query, policy, and UI level
- tenant visibility in Filament is not sufficient by itself; server-side rules remain mandatory
- queued jobs must preserve tenant context when executing BPM actions
- media, audit views, and registry access must respect the active tenant
- shared lookup tables are readable across tenants, but tenant-owned records are never shared by default

---

## Template Versioning Strategy

Process templates evolve through **versioning + soft-deletes**, not destructive replacement.

### Versioning Rules

- every published `Process` has a `version`
- new structural changes should create a new version of the process template
- prior versions remain available for historical linkage
- templates are retired using **soft-deletes**
- no destructive cancellation strategy is used for historical BPM records

### Why Soft-Deletes

Soft-deletes are preferred because they:

- preserve auditability
- preserve runtime references
- support legal/compliance traceability
- allow historical reconstruction of template state
- avoid broken links for in-flight or completed executions

### Runtime Linkage

`TaskExecution` should keep enough information to reconstruct the version used at the moment of launch, including:

- `process_version`
- relevant task/checklist snapshots
- resolved action metadata
- assignee resolution context

---

## Entity Relationship Overview

```text
Company (1) ─── (N) Process
Company (1) ─── (N) BusinessFunction
Company (1) ─── (N) Employee
Company (1) ─── (N) Client

Process (1) ─── (N) ProcessTask
Process (N) ─── (1) ProcessMacroCategory
ProcessTask (1) ─── (N) Checklist
Checklist (1) ─── (N) ChecklistItem
ProcessTask (1) ─── (N) RaciAssignment
ProcessTask (1) ─── (1) ProcessTaskPrivacyData

ProcessTask (1) ─── (N) TaskExecution
TaskExecution (1) ─── (N) TaskExecutionChecklistItem
TaskExecution (1) ─── (N) TaskDeadline
TaskExecution (M) ─── (1) target morph
```

---

## RACI Semantics and Assignment Resolution

RACI must be understood in two layers: **organizational semantics** and **runtime assignment**.

### Template-Level Meaning

- **R = Responsible**: who is expected to execute the task
- **A = Accountable**: who owns the result and approval responsibility
- **C = Consulted**: who may need to be involved before or during execution
- **I = Informed**: who should be notified or made aware

At template level, a `RaciAssignment` can identify:

- a `BusinessFunction`
- a specific `Employee`
- a specific `Client`
- or a combination depending on the business rule

### Runtime Assignment Semantics

Runtime assignment is not just “who appears in RACI”. It is a resolution process.

#### Resolution Rules

1. If role `R` identifies an explicit `employee_id`, assign the task to that employee.
2. If role `R` identifies an explicit `client_id`, assign the task to that client.
3. If role `R` identifies a `business_function_id`, resolve the active runtime assignee from the valid membership of that business function.
4. If multiple eligible assignees exist, apply deterministic selection rules:
    - designated manager first, if required by the workflow
    - otherwise configured default resolver
    - otherwise explicit user selection at process start
5. If no eligible assignee exists, process start must fail explicitly or enter a blocked state rather than creating ambiguous runtime work.

### Important Distinctions

The document distinguishes:

- **organizational owner**: the business function responsible for the role
- **runtime assignee**: the concrete employee/client that must perform the task
- **notification target**: the party who receives a notification
- **approval owner**: the accountable actor
- **visibility subject**: users who may view but not execute the task

These roles must not be conflated.

---

## Runtime Execution Model

### TaskExecution

`TaskExecution` is the runtime work record generated from a `ProcessTask`.

It should represent:

- the task being performed
- the target record on which the process is running
- the actual assignee
- the active process version
- the runtime state and timestamps
- the SLA/deadline context
- the execution snapshot used for compliance reconstruction

### TaskExecutionChecklistItem

Each runtime checklist item is a runtime copy/snapshot of a template instruction. It should be treated as runtime evidence, not a live pointer to mutable template logic.

### Dynamic Task Execution

Task status movement is part of **dynamic task execution**. The engine, user actions, and domain services determine when tasks move through states. Status transitions are therefore runtime concerns, not template concerns.

---

## State Model

The BPM state machine is runtime-oriented and belongs to `TaskExecution`.

Typical states include:

- `todo`
- `in_progress`
- `completed`
- `blocked`
- `failed`

The exact transition orchestration is owned by dynamic task execution and BPM services.

### State Transition Principles

- transitions must be explicit and validated
- runtime services decide whether a transition is allowed
- mandatory checklist requirements must be respected before completion where applicable
- failed integrations or unmet prerequisites may move execution into `blocked` or `failed`
- status changes should produce auditable events

This document intentionally separates the **status lifecycle** from template design. The process template describes what must happen; runtime services determine how execution advances.

---

## Condition Evaluation Lifecycle

Checklist rules must have a defined evaluation lifecycle.

### Supported Concepts

| Field                     | Meaning                                                   |
| ------------------------- | --------------------------------------------------------- |
| `skip_condition_class`    | If evaluated true, the item is skipped in runtime         |
| `require_condition_class` | If evaluated true, the item becomes mandatory in runtime  |
| `action_class`            | Runtime action to be triggered when the item is completed |

### Recommended Lifecycle

The recommended lifecycle is:

1. evaluate rules when runtime checklist items are generated
2. persist the resulting runtime meaning in execution snapshots
3. avoid relying on future template edits or late class changes to reinterpret past executions
4. optionally allow re-evaluation only when the process explicitly supports it

### Why This Matters

Without a defined lifecycle, the same task can mean different things at different times depending on:

- later model changes
- later rule code changes
- different rendering moments
- tenant-specific visibility changes

For audit-heavy BPM, a persisted runtime interpretation is preferred.

---

## Extensibility Model

The BPM is extensible through contracts and container-resolved implementations.

### BusinessRule Contract

Rules determine dynamic checklist behavior.

```php
interface BusinessRule
{
    public function evaluate(mixed $subject, ?TaskExecution $execution = null): bool;
}
```

### BpmAction Contract

Actions execute runtime side effects.

```php
interface BpmAction
{
    public function execute(TaskExecution $execution, array $params = []): void;
}
```

### Parameterized Actions

Checklist items may store `action_params` JSON to configure reusable actions without requiring one class per variation.

### Container Tags Instead of `glob()`

Discovery of rules and actions should use **container tags**, not filesystem scanning.

#### Why container tags

- safer refactors
- explicit registration
- better metadata control
- package-friendly design
- testable resolution
- no dependency on directory scanning

#### Recommended pattern

- bind rule classes in a service provider
- tag them, e.g. `bpm.rule`
- bind action classes and tag them, e.g. `bpm.action`
- let registry services query the container/tagged services for available options

This replaces brittle `glob()`-based discovery.

---

## Domain Services, Actions, and Events

Critical BPM behavior should be modeled as explicit domain orchestration, not hidden inside observers.

### Recommended Domain Actions

- `StartProcessForTargetAction`
- `ResolveTaskAssigneeAction`
- `CreateTaskExecutionAction`
- `CloneChecklistToExecutionAction`
- `CompleteExecutionChecklistItemAction`
- `AdvanceTaskExecutionStateAction`
- `AutoStartOnboardingForEmployeeAction`

### Recommended Domain Events

- `ProcessStarted`
- `TaskExecutionCreated`
- `ChecklistItemCompleted`
- `TaskExecutionCompleted`
- `SlaBreached`
- `ActionExecutionFailed`

### Why Prefer Domain Actions/Events Over Heavy Observers

- clearer business intent
- easier testing
- explicit transactions
- easier queue integration
- better retry behavior
- less hidden coupling
- better long-term maintainability

Observers may still exist as thin adapters, but critical orchestration should live in explicit domain actions and events.

---

## Observers and Lifecycle Usage

Observers are still acceptable for lightweight reactions, but not as the primary place for complex BPM orchestration.

### Appropriate Observer Usage

Good observer usage includes:

- recording side metadata
- dispatching a domain event
- synchronizing denormalized fields
- lightweight audit enrichment

### Avoid Observer-Heavy Logic For

- full process creation
- assignee resolution
- runtime checklist generation
- workflow advancement
- critical external side effects

Those concerns belong in domain services/actions.

---

## Transactional and Idempotency Rules

The BPM engine must define operational guarantees for consistency.

### Transactions

The following flows should run inside database transactions:

- process start
- creation of runtime tasks
- cloning/snapshotting of runtime checklist items
- state transitions that update multiple runtime records
- SLA record creation tied to runtime creation

### Idempotency

Actions must be safe against duplicate triggers.

Recommended safeguards:

- store an `idempotency_key` on `TaskExecution` or related action execution records
- guard repeated completion events
- ensure a checklist completion action is not re-fired accidentally
- ensure retries of queued jobs do not create duplicate external side effects

### Action Execution Logging

For robust auditing and retries, action execution should be logged explicitly.

Suggested information:

- related execution/checklist item
- action class
- payload / params
- status
- started / finished timestamps
- failure message
- retry count
- triggered by user/system context

This complements activity logs and makes BPM debugging easier.

---

## Queue Strategy

Not all BPM behaviors should execute synchronously.

### Queue Candidates

These should generally be queued:

- emails
- external API calls
- ERP or SAP synchronization
- long-running compliance checks
- retryable side effects
- non-critical notifications
- enrichment or analytics actions

### Synchronous Candidates

These should generally stay synchronous:

- local validation
- deterministic assignee resolution
- local status transitions
- creation of essential runtime DB records inside transactions

### Queue Principles

- preserve tenant context in jobs
- use retry/backoff for transient integration failures
- record failed action executions explicitly
- keep the UI aware of asynchronous follow-up work where relevant
- do not queue the minimum writes required to create a valid runtime task

---

## Navigation, Permissions, and Visibility

The BPM contributes to permission and visibility logic, but three concepts must remain distinct.

### 1. Navigation Visibility

Controls what appears in Filament navigation.

### 2. Authorization

Controls whether a user may access a resource/page/action.

### 3. Execution Permission

Controls whether a user may execute, complete, reassign, or approve runtime tasks.

These can be influenced by:

- tenant membership
- employee/company relationship
- business function membership
- RACI semantics
- policies and domain rules
- active runtime assignment

### Architectural Note

The BPM is used to extract privacy rules and permission-relevant relationships from process design. This means the procedural model is not only operational; it also informs who can see, handle, or act on regulated data.

---

## Compliance and Privacy Layer

The BPM supports privacy-aware and compliance-aware operations by attaching legal metadata directly to procedural steps.

### ProcessTaskPrivacyData

Each `ProcessTask` can define:

- privacy data type
- legal base
- purpose
- access level
- encryption requirement
- external sharing indicator

This enables the process model to act as a source of truth for privacy obligations.

### Related Compliance Modules

- `RequestRegistry`
- `RequestRegistryAction`
- `RequestRegistryAttachment`
- `RequestRegistryProcess`
- `ConsentLog`
- `SuppressionList`
- `DataBreach`
- `SlaPolicy`
- `Holiday`

### Compliance Goal

The objective is not only to track execution, but also to derive:

- who may process which kind of data
- under which legal basis
- under which procedure
- under which organizational responsibility
- with which audit evidence

---

## Key Services

| Service              | Responsibility                                                                   |
| -------------------- | -------------------------------------------------------------------------------- |
| `BpmEngineService`   | BPM runtime orchestration, evaluated checklist generation, execution advancement |
| `BpmRegistryService` | Resolves visible rules/actions from container registration and tenant context    |
| `SlaService`         | Calculates business-day-aware deadlines and warning thresholds                   |

---

## Starting a Process

A process start flow should be treated as an explicit domain operation.

### Typical Flow

1. select a process version applicable to the target model
2. validate tenant and target eligibility
3. resolve assignees from RACI semantics
4. create runtime `TaskExecution` records in a transaction
5. clone/snapshot checklist items into runtime items
6. create SLA/deadline tracking where required
7. dispatch notifications and async follow-up jobs
8. emit domain events and audit records

### Target Model

The polymorphic `target_type` / `target_id` on `TaskExecution` allows the same BPM engine to operate on different domain entities such as:

- `Employee`
- `Client`
- `Agent`
- `Proforma`
- future target models

---

## Auditability and Observability

Audit is a first-class concern.

### Current Audit Stack

The project uses:

- `alizharb/filament-activity-log`
- domain/runtime timestamps
- execution records
- compliance registries
- notification/event history where applicable

### Recommended Observability Direction

In addition to generic activity logs, BPM-specific execution logs should capture:

- state transitions
- action executions
- rule evaluations
- SLA warnings and breaches
- external integration failures

This provides operational debugging beyond generic CRUD auditing.

---

## Architecture Decisions and Tradeoffs

### Why Template → Execution

Because theoretical procedure design and actual runtime work must remain separate for compliance, auditability, and operational flexibility.

### Why Versioning + Soft-Deletes

Because historical executions must remain interpretable even after template evolution. Soft-deletes preserve references; versioning preserves meaning.

### Why Runtime Snapshots

Because relying on mutable templates during later reads would compromise auditability and reproducibility.

### Why Domain Actions/Events

Because critical BPM flows are business operations, not incidental ORM side effects.

### Why Container Tags

Because filesystem scanning is brittle and difficult to scale across modules, packages, and tenant-aware registries.

### Why the BPM Also Informs Privacy and Permissions

Because the process model contains the real relationship between tasks, roles, data handling, and responsibility. In a regulated environment, workflow design is also an authorization and privacy-design artifact.

---

## Directory Structure

```text
app/
├── Actions/
│   └── Bpm/                      # BpmAction implementations
├── Contracts/
│   ├── BpmAction.php
│   ├── BpmActionInterface.php
│   └── BusinessRule.php
├── Filament/
│   ├── Actions/
│   │   └── StartProcessAction.php
│   ├── Pages/
│   ├── Resources/
│   └── Widgets/
├── Models/
│   ├── Process.php
│   ├── ProcessMacroCategory.php
│   ├── ProcessTask.php
│   ├── Checklist.php
│   ├── ChecklistItem.php
│   ├── RaciAssignment.php
│   ├── TaskExecution.php
│   ├── TaskExecutionChecklistItem.php
│   └── ...
├── Notifications/
├── Observers/
├── Providers/
├── Rules/
│   └── Bpm/
├── Services/
│   ├── BpmEngineService.php
│   ├── BpmRegistryService.php
│   └── SlaService.php
└── Traits/
```

---

## Known Architectural Guidance

The following guidance is intentional and should inform future implementation:

- keep the Template and Execution layers strictly separated
- prefer versioning and soft-deletes over destructive mutation
- resolve runtime behavior through explicit services/actions
- keep tenant boundaries explicit
- treat privacy metadata as part of process architecture, not as an afterthought
- use queues for external and retryable side effects
- use container tags for action/rule discovery
- model action execution and event history explicitly where compliance or debugging requires it

---

## Summary

UnicoFinance’s BPM architecture is designed as a **multi-tenant, compliance-aware, process-driven platform** where:

- template definitions describe procedure and responsibility
- runtime executions track real operational work
- privacy rules and permissions can be derived from procedural design
- versioning and soft-deletes preserve historical meaning
- domain services, actions, and events orchestrate critical behavior
- queueing and idempotency protect external side effects
- activity logs and runtime evidence support audit and regulatory traceability

This architecture allows the platform to serve as both an **operational execution engine** and a **compliance-oriented governance layer**.
