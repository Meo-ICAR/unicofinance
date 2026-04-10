## Qwen Added Memories
- ## UnicoFinance - BPM Application Architecture

**Stack**: Laravel 13, Filament 5.4, pxlrbt/filament-excel, PHP 8.3+
**Multi-tenant**: via Company model (UUID PK), Filament tenant switching
**Auth**: Socialite (Microsoft, Google), Spatie Permission + Filament Shield
**Media**: Spatie Media Library
**Testing**: Pest PHP

### Core BPM Data Model (Template → Execution Pattern)
- **Process** (template) → 1:N **ProcessTask** (steps, ordered by sequence_number)
- **ProcessTask** → 1:N **Checklist** (instruction groups) → 1:N **ChecklistItem** (individual rules)
- **ProcessTask** → 1:1 **RaciAssignment** (R/A/C/I roles linking to BusinessFunction)
- **ProcessTask** → 1:1 **ProcessTaskPrivacyData** (GDPR processing record)
- **ProcessTask** → 1:N **ChecklistItem** fields:
  - `require_condition_class` → points to `App\Rules\Bpm\*` (implements BusinessRule) → if true, item is mandatory
  - `skip_condition_class` → points to `App\Rules\Bpm\*` (implements BusinessRule) → if true, item is skipped
  - `action_class` → points to `App\Actions\Bpm\*` (implements BpmAction) → runs on item completion

### Runtime Execution
- Starting a process creates **TaskExecution** (runtime instance / "ticket")
- TaskExecution → clones all ChecklistItems into **TaskExecutionChecklistItem**
- TaskExecutionChecklistItem observer triggers `action_class` on `is_completed` → true
- TaskExecution → **TaskDeadline** (SLA tracking with business-day calculations)

### Key Extension Points
- **Contracts**: `BpmAction` (execute method) and `BusinessRule` (evaluate method)
- **Rules**: `app/Rules/Bpm/` - currently ForeignerRule.php
- **Actions**: `app/Actions/Bpm/` - PromoteClientStatus, UpdateClientToAmlCheck, SendPrivacyWelcomeEmail, SendPrivacyOnboardingEmail
- **Services**: BpmEngineService, BpmRegistryService, SlaService

### Key Directories
- Models: `app/Models/` (32 models)
- Filament Resources: `app/Filament/Resources/` (20 resources)
- Observers: `app/Observers/` (EmployeeObserver, RaciAssignmentObserver [commented out], TaskExecutionChecklistItemObserver)
- Config: `config/bpm_registry.php` controls multi-tenant action/condition visibility
