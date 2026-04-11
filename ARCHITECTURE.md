# UnicoFinance — BPM Architecture Document

> **Business Process Management system** built for GDPR-compliant workflow automation in financial advisory firms (OAM/IVASS registered).

---

## Technology Stack

| Layer                   | Technology                                  |
| ----------------------- | ------------------------------------------- |
| **Framework**           | Laravel 13 (PHP 8.3+)                       |
| **Admin Panel**         | Filament 5.4 (multi-tenant via `Company`)   |
| **Excel Import/Export** | pxlrbt/filament-excel                       |
| **Authentication**      | Laravel Socialite (Microsoft, Google)       |
| **Authorization**       | Spatie Laravel Permission + Filament Shield |
| **Media**               | Spatie Media Library                        |
| **Audit Trail**         | Spatie Activity Log + wildside/userstamps   |
| **Testing**             | Pest PHP                                    |

---

## Core Concept: Template → Execution Pattern

The BPM follows a **two-phase design**:

1. **Template Phase** — Define processes, tasks, checklists, RACI roles, and privacy data as reusable blueprints.
2. **Execution Phase** — At runtime, a process is instantiated into `TaskExecution` records (tickets/pratiche) that track real work being done.

```
┌─────────────────────────────────────────────────────────┐
│                   TEMPLATE PHASE                         │
│                                                          │
│  Process (definition)                                    │
│   └── ProcessTask  (step 1, 2, 3…)                      │
│        ├── Checklist  (instruction group)               │
│        │    └── ChecklistItem  (single rule/step)       │
│        ├── RaciAssignment  (R/A/C/I matrix)             │
│        └── ProcessTaskPrivacyData  (GDPR record)        │
└─────────────────────────────────────────────────────────┘
                          │  StartProcessAction
                          ▼
┌─────────────────────────────────────────────────────────┐
│                   EXECUTION PHASE                        │
│                                                          │
│  TaskExecution  (runtime instance / ticket)              │
│   ├── employee_id / client_id  (assignee)               │
│   ├── TaskExecutionChecklistItem  (cloned from template)│
│   │    └── observer triggers action_class on complete   │
│   └── TaskDeadline  (SLA tracking)                      │
└─────────────────────────────────────────────────────────┘
```

---

## Data Model

### Entity Relationship Diagram

```
Company (1) ──────────┬── (N) Process
                      │
                      ├── (N) BusinessFunction ──┬── (N) Process (owner_function)
                      │                          └── (N) RaciAssignment
                      │
                      ├── (N) Employee ───(observer)──→ auto-starts PROC-ONBOARDING
                      │
                      └── (N) Client

Process (1) ── (N) ProcessTask
                     │
                     ├── (1:N) RaciAssignment ──→ BusinessFunction  (who does what: R/A/C/I)
                     │
                     ├── (1:N) Checklist ── (1:N) ChecklistItem
                     │                              │
                     │                              ├── require_condition_class  → App\Rules\Bpm\*
                     │                              ├── skip_condition_class     → App\Rules\Bpm\*
                     │                              └── action_class             → App\Actions\Bpm\*
                     │
                     └── (1:1) ProcessTaskPrivacyData ──→ PrivacyDataType
                                                          PrivacyLegalBase

ProcessTask (1) ── (N) TaskExecution  (runtime)
                        │
                        ├── (1:N) TaskExecutionChecklistItem  (cloned from ChecklistItem)
                        │    └── Observer fires action_class on is_checked=true
                        │
                        └── (1:N) TaskDeadline  (SLA tracking)
```

### Core Tables

| Table                            | Role                    | Key Columns                                                                                                                                |
| -------------------------------- | ----------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| `companies`                      | Multi-tenant root       | `id` (UUID), `name`, `domain`, `vat_number`, `oam`, `ivass`                                                                                |
| `company_branches`               | Company branch offices  | `company_id`, `name`, `is_main_office`, `manager_first_name`, `manager_last_name`, `manager_tax_code`                                      |
| `processes`                      | Process definition      | `company_id`, `owner_function_id`, `name`, `description`, `target_model`, `is_active`                                                      |
| `process_macros`                 | Process macro category  | `name`, `description`                                                                                                                      |
| `process_request_mappings`       | Request type → Process  | `request_type`, `process_id`, `is_suggested`                                                                                               |
| `process_tasks`                  | Individual steps        | `process_id`, `sequence_number`, `name`, `description`                                                                                     |
| `checklists`                     | Instruction groups      | `process_task_id`, `name`, `description`, `sort_order`                                                                                     |
| `checklist_items`                | Single instructions     | `checklist_id`, `instruction`, `is_mandatory`, `sort_order`, **`require_condition_class`**, **`skip_condition_class`**, **`action_class`** |
| `raci_assignments`               | RACI matrix             | `process_task_id`, `business_function_id`, `role` (R/A/C/I) — unique on `(process_task_id, role)`                                          |
| `process_task_privacy_data`      | GDPR processing record  | `process_task_id`, `privacy_data_type_id`, `privacy_legal_base_id`, `access_level`, `purpose`, `is_encrypted`, `is_shared_externally`      |
| `task_executions`                | Runtime ticket          | `process_task_id`, `employee_id`, `client_id`, `status`, `due_date`, `started_at`, `completed_at`, `target_type`, `target_id`              |
| `task_execution_checklist_items` | Runtime checklist check | `task_execution_id`, `checklist_item_id`, `is_checked`, `checked_at`                                                                       |
| `task_deadlines`                 | SLA tracking            | `task_execution_id`, `status`, `warning_at`, `breached_at`, `completed_at`                                                                 |

### Supporting Tables

| Table                          | Purpose                                                                                               |
| ------------------------------ | ----------------------------------------------------------------------------------------------------- |
| `users`                        | Application users (multi-tenant via `Company`)                                                        |
| `socialite_users`              | OAuth login records (Microsoft, Google)                                                               |
| `business_functions`           | Organizational units (org chart) with GDPR metadata                                                   |
| `business_function_client`     | Pivot: client ↔ business function (with `start_date`, `end_date`, `temporary_reason`)                 |
| `business_function_employee`   | Pivot: employee ↔ business function (with `is_manager`, `start_date`, `end_date`, `temporary_reason`) |
| `employees`                    | Employee registry with OAM/IVASS registration                                                         |
| `clients`                      | Client/lead registry with consent & GDPR fields                                                       |
| `client_types`                 | Client type catalog (`name`, `description`)                                                           |
| `privacy_data_types`           | GDPR data type catalog (comuni, particolari, giudiziari)                                              |
| `privacy_legal_bases`          | Legal bases for processing (Art. 6 GDPR)                                                              |
| `sla_policies`                 | SLA policy definitions (duration, warning %, weekend exclusion)                                       |
| `holidays`                     | Holiday calendar for business-day SLA calculations                                                    |
| `request_registries`           | GDPR request registry (access, deletion, rectification…)                                              |
| `request_registry_actions`     | Audit log of actions performed on a registry entry                                                    |
| `request_registry_attachments` | Files uploaded to a registry entry                                                                    |
| `request_registry_processes`   | Links a registry entry to processes/process_tasks being executed                                      |
| `consent_logs`                 | Logs of consent given by clients                                                                      |
| `suppression_lists`            | Opt-out / blacklist entries                                                                           |
| `data_breaches`                | Data breach tracking with DPA reporting flag                                                          |
| `lead_transfers`               | Lead ownership transfer between clients                                                               |
| `lead_return_logs`             | Lead reassignment / return log                                                                        |
| `agents`                       | Candidate/agent registry for recruitment BPM process                                                  |
| `proformas`                    | Preliminary invoices for "Gestione Proforma ed Emissione Fattura" BPM                                 |
| `commissions`                  | Fee/commission lines linked to a Proforma                                                             |

### BPM Models (37 total)

| Model                        | Type  | Key Relationships                                                                                                                                                                                                                                                           |
| ---------------------------- | ----- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Agent`                      | Model | `belongsTo(User)`, `morphMany(TaskExecution)`                                                                                                                                                                                                                               |
| `BusinessFunction`           | Model | `belongsTo(Company)`, `hasMany(Process)`, `hasMany(RaciAssignment)`, `belongsToMany(Client)`, `belongsToMany(Employee)`                                                                                                                                                     |
| `BusinessFunctionClient`     | Pivot | `business_function_id`, `client_id`, `start_date`, `end_date`, `temporary_reason`                                                                                                                                                                                           |
| `BusinessFunctionEmployee`   | Pivot | `business_function_id`, `employee_id`, `is_manager`, `start_date`, `end_date`, `temporary_reason`                                                                                                                                                                           |
| `Checklist`                  | Model | `belongsTo(ProcessTask)`, `hasMany(ChecklistItem)`                                                                                                                                                                                                                          |
| `ChecklistItem`              | Model | `belongsTo(Checklist)`, `require_condition_class`, `skip_condition_class`, `action_class`                                                                                                                                                                                   |
| `Client`                     | Model | `belongsTo(Company)`, `belongsTo(ClientType)`, `morphMany(TaskExecution)`, `belongsToMany(BusinessFunction)`                                                                                                                                                                |
| `ClientType`                 | Model | `hasMany(Client)`                                                                                                                                                                                                                                                           |
| `Commission`                 | Model | `belongsTo(Proforma)`, `belongsTo(Company)`                                                                                                                                                                                                                                 |
| `Company`                    | Model | `hasMany(Process, Employee, Client, BusinessFunction, CompanyBranch, RequestRegistry)`                                                                                                                                                                                      |
| `CompanyBranch`              | Model | `belongsTo(Company)`                                                                                                                                                                                                                                                        |
| `ConsentLog`                 | Model | `belongsTo(Client)`                                                                                                                                                                                                                                                         |
| `DataBreach`                 | Model | `belongsTo(Company)`                                                                                                                                                                                                                                                        |
| `Employee`                   | Model | `belongsTo(Company)`, `belongsTo(CompanyBranch)`, `morphMany(TaskExecution)`, `belongsToMany(BusinessFunction)`                                                                                                                                                             |
| `Holiday`                    | Model | `belongsTo(Company)`                                                                                                                                                                                                                                                        |
| `LeadReturnLog`              | Model | `belongsTo(Company)`, `belongsTo(Client, client_id)`, `belongsTo(Client, lead_id)`                                                                                                                                                                                          |
| `LeadTransfer`               | Model | `belongsTo(Company)`, `belongsTo(Client, lead_id)`, `belongsTo(Client, purchaser_id)`                                                                                                                                                                                       |
| `PrivacyDataType`            | Model | `hasMany(ProcessTaskPrivacyData)`                                                                                                                                                                                                                                           |
| `PrivacyLegalBase`           | Model | `hasMany(ProcessTaskPrivacyData)`                                                                                                                                                                                                                                           |
| `Process`                    | Model | `belongsTo(Company)`, `belongsTo(BusinessFunction, owner_function_id)`, `belongsTo(ProcessMacroCategory)`, `hasMany(ProcessTask)`, `hasMany(ProcessRequestMapping)`                                                                                                         |
| `ProcessMacroCategory`       | Model | `hasMany(Process)`                                                                                                                                                                                                                                                          |
| `ProcessRequestMapping`      | Model | `belongsTo(Process)`, scopes: `suggested()`, `byRequestType()`                                                                                                                                                                                                              |
| `ProcessTask`                | Model | `belongsTo(Process)`, `hasMany(Checklist)`, `hasMany(RaciAssignment)`, `hasOne(ProcessTaskPrivacyData)`, `hasMany(TaskExecution)`                                                                                                                                           |
| `ProcessTaskPrivacyData`     | Model | `belongsTo(ProcessTask)`, `belongsTo(PrivacyDataType)`, `belongsTo(PrivacyLegalBase)`                                                                                                                                                                                       |
| `Proforma`                   | Model | `belongsTo(Company)`, `belongsTo(Client)`, `belongsTo(Employee, employee_id)`, `hasMany(Commission)`, `morphMany(TaskExecution)`                                                                                                                                            |
| `RaciAssignment`             | Model | `belongsTo(ProcessTask)`, `belongsTo(BusinessFunction)`                                                                                                                                                                                                                     |
| `RequestRegistry`            | Model | `belongsTo(Company)`, `belongsTo(User, assigned_to)`, `belongsTo(Process, active_process_id)`, `belongsTo(ProcessTask, process_task_id)`, `morphTo(dataSubject)`, `hasMany(RequestRegistryAttachment)`, `hasMany(RequestRegistryAction)`, `hasMany(RequestRegistryProcess)` |
| `RequestRegistryAction`      | Model | `belongsTo(RequestRegistry)`, `belongsTo(User, performed_by)`                                                                                                                                                                                                               |
| `RequestRegistryAttachment`  | Model | `belongsTo(RequestRegistry)`, `belongsTo(User, uploaded_by)`                                                                                                                                                                                                                |
| `RequestRegistryProcess`     | Model | `belongsTo(RequestRegistry)`, `belongsTo(Process)`, `belongsTo(ProcessTask)`                                                                                                                                                                                                |
| `SlaPolicy`                  | Model | `belongsTo(Company)`                                                                                                                                                                                                                                                        |
| `SocialiteUser`              | Model | Extends `DutchCodingCompany\FilamentSocialite\Models\SocialiteUser`                                                                                                                                                                                                         |
| `SuppressionList`            | Model | `belongsTo(Company)`                                                                                                                                                                                                                                                        |
| `TaskDeadline`               | Model | `belongsTo(TaskExecution)`                                                                                                                                                                                                                                                  |
| `TaskExecution`              | Model | `belongsTo(ProcessTask)`, `belongsTo(Employee)`, `belongsTo(Client)`, `belongsTo(RequestRegistry)`, `morphTo(target)`, `hasMany(TaskExecutionChecklistItem)`, `hasMany(TaskDeadline)`                                                                                       |
| `TaskExecutionChecklistItem` | Model | `belongsTo(TaskExecution)`, `belongsTo(ChecklistItem)`                                                                                                                                                                                                                      |
| `User`                       | Model | `belongsToMany(Company)`, `hasOne(SocialiteUser)`, `hasMany(Employee)`, `hasMany(RequestRegistry, assigned_to)`                                                                                                                                                             |

---

## Extension Points

The BPM is extensible through two **contract-based** plugin patterns on `ChecklistItem`:

### 1. BusinessRule — Dynamic Conditions

```php
// app/Contracts/BusinessRule.php
interface BusinessRule {
    public function evaluate(Client $client, ?TaskExecution $execution = null): bool;
}
```

| ChecklistItem Field       | Purpose                                                                                 |
| ------------------------- | --------------------------------------------------------------------------------------- |
| `skip_condition_class`    | If the rule evaluates `true`, the item is **skipped** (removed from the checklist)      |
| `require_condition_class` | If the rule evaluates `true`, the item becomes **mandatory** (overrides `is_mandatory`) |

**Example** — `App\Rules\Bpm\ForeignerRule`:

```php
class ForeignerRule implements BusinessRule {
    public function evaluate(Client $client, ?TaskExecution $execution = null): bool {
        return $client->citizenship !== 'IT';
    }
}
```

### 2. BpmAction — Post-Completion Triggers

```php
// app/Contracts/BpmAction.php
interface BpmAction {
    public function execute(TaskExecution $execution, array $params = []): void;
}
```

| ChecklistItem Field | Purpose                                                                                                                       |
| ------------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| `action_class`      | When the corresponding `TaskExecutionChecklistItem.is_checked` transitions to `true`, the action is **executed** via observer |

**Existing Actions** in `app/Actions/Bpm/`:

| Class                        | What it does                                                    |
| ---------------------------- | --------------------------------------------------------------- |
| `PromoteClientStatus`        | Sets `client.status` to `'approvato'`                           |
| `UpdateClientToAmlCheck`     | Sets `client.status` to `'valutazione_aml'`, sets `acquired_at` |
| `SendPrivacyWelcomeEmail`    | Sends privacy information email to client                       |
| `SendPrivacyOnboardingEmail` | (stub — empty)                                                  |

### Rule Discovery

The Filament form for `ChecklistItem` scans `app/Rules/` via `glob()` to populate dropdowns for `require_condition_class` and `skip_condition_class`.

### Action Registry

`config/bpm_registry.php` controls which actions and conditions are visible **per tenant**:

```php
'actions' => [
    ActivateEmployeeAction::class => [
        'name' => '🟢 Attiva Dipendente',
        'group' => 'Risorse Umane',
        'companies' => null,   // null = all tenants
    ],
    SendDataToSapAction::class => [
        'name' => '⚙️ Sincronizza dati con SAP',
        'group' => 'Integrazioni Custom',
        'companies' => [4, 7],  // visible only to these companies
    ],
],
```

---

## Navigation Access Control

The navigation bar items available to a user are determined by the **ProcessTask** and **RaciAssignment** relationship and enforced through **Laravel Policies**:

- Each `ProcessTask` defines a step within a process template
- `RaciAssignment` links a `ProcessTask` to a `BusinessFunction` with a specific role (R/A/C/I)
- Users are associated with business functions (through their role/assignment), and the navigation items they can see are filtered based on which `ProcessTask` records have a `RaciAssignment` for their business function
- Access is enforced via **Policies** in `app/Policies/` (e.g., `ProcessPolicy`, `EmployeePolicy`, `ClientPolicy`) that use a shared **trait** to encapsulate the RACI-based authorization logic
- This creates a dynamic, role-based navigation system that adapts per tenant and per user's organizational position

```
User → Employee → BusinessFunction → RaciAssignment → ProcessTask → Policy (trait) → Navigation Item
```

---

## Runtime User Tracking

When a process is executed on a specific record (employee, client, etc.), the system creates runtime instances that track **which specific user operates each step**:

- Starting a process creates a `TaskExecution` (runtime instance / ticket)
- `TaskExecution` records link to the `employee_id` (or other assignee) who is responsible for that task
- Each `TaskExecution` clones all `ChecklistItem` records from the template into `TaskExecutionChecklistItem` records
- When a user completes a checklist item (marks `is_checked = true`), the `TaskExecutionChecklistItemObserver` fires and the action is attributed to that specific user
- This creates a complete audit trail: **who** did **what**, **when**, on **which process step**

```
TaskExecution (runtime ticket)
├── employee_id          → the user assigned to this task
├── client_id            → the client/record being processed
├── started_at           → when the user began work
├── completed_at         → when the user finished
│
└── TaskExecutionChecklistItem (cloned from template)
     ├── is_checked      → completion flag
     ├── checked_at      → timestamp of completion
     └── [via observer]  → action_class executed, attributed to this user
```

This design ensures full traceability for compliance purposes (GDPR, OAM/IVASS regulations) while maintaining the separation between **template definition** (ProcessTask, RaciAssignment) and **runtime execution** (TaskExecution, TaskExecutionChecklistItem).

---

## Key Services

| Service                | Responsibility                                                                                                                                                   |
| ---------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **BpmEngineService**   | `getAvailableActions()`, `getAvailableConditions()`, `getEvaluatedChecklist()` (evaluates skip/require rules), `completeChecklistItem()` (triggers action_class) |
| **BpmRegistryService** | `getOptionsForFilament()` reads from `config/bpm_registry.php` for multi-tenant filtering                                                                        |
| **SlaService**         | Business-day-aware SLA deadline calculation, skipping weekends and Italian holidays                                                                              |

---

## Observers & Lifecycle Events

| Observer                               | Model                        | Trigger                                           | Behavior                                                                                                  |
| -------------------------------------- | ---------------------------- | ------------------------------------------------- | --------------------------------------------------------------------------------------------------------- |
| **EmployeeObserver**                   | `Employee`                   | `created`                                         | Auto-starts `PROC-ONBOARDING` process — creates a `TaskExecution` for each task in the onboarding process |
| **TaskExecutionChecklistItemObserver** | `TaskExecutionChecklistItem` | `updated` (is_checked → true)                     | Loads `action_class` from the master `ChecklistItem` and executes it                                      |
| **RaciAssignmentObserver**             | `RaciAssignment`             | _(currently commented out in AppServiceProvider)_ | Syncs `business_function_id` from RACI 'A' role up to ProcessTask and Process                             |

### TaskExecution Boot Logic

When a `TaskExecution` is created, the `booted()` method automatically clones all `ChecklistItem` records from the template into `TaskExecutionChecklistItem` records:

```php
static::created(function (TaskExecution $execution) {
    $checklistItems = ChecklistItem::whereHas('checklist', function ($query) use ($execution) {
        $query->where('process_task_id', $execution->process_task_id);
    })->get();

    foreach ($checklistItems as $item) {
        TaskExecutionChecklistItem::create([
            'task_execution_id' => $execution->id,
            'checklist_item_id' => $item->id,
            'is_checked' => false,
        ]);
    }
});
```

### ProcessTask Boot Logic

When a `ProcessTask` is created, it auto-creates 4 `RaciAssignment` records (R, A, C, I):

```php
static::created(function (ProcessTask $task) {
    $roles = ['R', 'A', 'C', 'I'];
    foreach ($roles as $role) {
        $task->raciAssignments()->firstOrCreate(
            ['role' => $role],
            ['business_function_id' => $process->business_function_id]
        );
    }
});
```

---

## Starting a Process

`App\Filament\Actions\StartProcessAction` is a Filament table action that:

1. Presents a modal to select an active `Process` (filtered by `target_model` polymorphic type)
2. For each `ProcessTask`, creates a `TaskExecution` with the assignee from the RACI 'R' role
3. Sends notifications to the assignee (employee, client, or external email)

The polymorphic `target_type` / `target_id` on `TaskExecution` allows any model (Employee, Client, etc.) to be the subject of a process.

---

## Multi-Tenancy

All data is scoped to `Company` (UUID primary key). Filament uses tenant switching via `Filament::getTenant()`. The `bpm_registry.php` config controls which actions/conditions are visible per company.

The `Company` model implements:

- `HasTenants` on `User` — users belong to multiple companies
- `HasFilamentName` — returns company name for UI
- `HasAvatar` via Spatie Media Library — company logo

---

## Directory Structure

```
app/
├── Actions/Bpm/              # BpmAction implementations
│   ├── PromoteClientStatus.php
│   ├── UpdateClientToAmlCheck.php
│   ├── SendPrivacyWelcomeEmail.php
│   └── SendPrivacyOnboardingEmail.php
├── Contracts/
│   ├── BpmAction.php         # Interface for action_class
│   └── BusinessRule.php      # Interface for skip/require conditions
├── Enums/
│   ├── BusinessFunctionType.php
│   ├── EmployeeType.php
│   ├── MacroArea.php
│   ├── OutsourcableStatus.php
│   ├── RequestType.php
│   └── SupervisorType.php
├── Filament/
│   ├── Actions/
│   │   └── StartProcessAction.php    # Launch a BPM process
│   ├── Resources/                    # 20 Filament resources
│   ├── Pages/
│   │   └── ManualeOperativo.php      # Process documentation viewer
│   └── Widgets/
│       └── MasterBrokerStatsWidget.php
├── Models/                           # 32 Eloquent models
├── Observers/
│   ├── EmployeeObserver.php
│   ├── RaciAssignmentObserver.php    # (commented out)
│   └── TaskExecutionChecklistItemObserver.php
├── Rules/Bpm/
│   └── ForeignerRule.php             # BusinessRule: citizenship != IT
├── Services/
│   ├── BpmEngineService.php
│   ├── BpmRegistryService.php
│   └── SlaService.php
└── Notifications/
    └── TaskAssignedNotification.php
```

---

## GDPR / Privacy Compliance

The system tracks privacy data processing at the **ProcessTask** level:

- **ProcessTaskPrivacyData** links each task to `PrivacyDataType` (comuni, particolari, giudiziari) with `access_level`, `purpose`, `legal_base`, encryption flags
- **RequestRegistries** manages GDPR subject requests (access, deletion, rectification) with auto-generated protocol numbers and 30-day deadlines
- **ConsentLogs** logs consent timestamps
- **SuppressionLists** maintains opt-out records
- **DataBreaches** tracks breaches with severity and DPA reporting

SLA deadlines use **business day** calculations (skipping weekends and Italian holidays from the `holidays` table).
