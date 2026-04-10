<x-filament-panels::page>
    {{--
        BPM Task Runner — Split-view UI
        Left: Target document (Proforma) details
        Right: Operational checklists with async checkboxes
    --}}

    <div
        x-data="bpmTaskRunner({{ $executionId ?? 'null' }})"
        x-init="init()"
        class="grid grid-cols-1 gap-6 lg:grid-cols-2"
    >
        {{-- ─── LEFT: Target Document Panel ─── --}}
        <div class="flex flex-col gap-6">
            {{-- Loading state --}}
            <template x-if="loading">
                <div class="flex items-center justify-center p-12">
                    <x-filament::loading-indicator class="h-8 w-8 text-primary-500" />
                </div>
            </template>

            <template x-if="!loading && executionData">
                <div class="flex flex-col gap-6">
                    {{-- Task Header --}}
                    <x-filament::section>
                        <x-slot name="heading" x-text="executionData.process_task.name"></x-slot>
                        <x-slot name="description" x-text="executionData.process_task.description"></x-slot>

                        <div class="flex flex-wrap gap-3 text-sm text-gray-500 dark:text-gray-400">
                            <span>
                                <strong>Processo:</strong>
                                <span x-text="executionData.process_task.process_name"></span>
                            </span>
                            <span>
                                <strong>Step:</strong>
                                #<span x-text="executionData.process_task.sequence_number"></span>
                            </span>
                            <span>
                                <strong>Assegnato a:</strong>
                                <span x-text="executionData.assignee?.name ?? 'N/D'"></span>
                            </span>
                            <span x-show="executionData.execution.due_date">
                                <strong>Scadenza:</strong>
                                <span x-text="executionData.execution.due_date"></span>
                            </span>
                        </div>
                    </x-filament::section>

                    {{-- Target Model (Proforma) Card --}}
                    <x-filament::section>
                        <x-slot name="heading">Documento Target</x-slot>

                        <template x-if="executionData.target">
                            <div class="flex flex-col gap-4">
                                <div class="flex items-center gap-2">
                                    <x-filament::badge color="primary" x-text="executionData.target.type.split('\\').pop()"></x-filament::badge>
                                    <span class="text-sm font-medium" x-text="'#'+ executionData.target.data.proforma_number"></span>
                                </div>

                                <div class="grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <span class="text-gray-500 dark:text-gray-400">Importo Totale</span>
                                        <p class="font-semibold" x-text="formatCurrency(executionData.target.data.total_amount)"></p>
                                    </div>
                                    <div>
                                        <span class="text-gray-500 dark:text-gray-400">Totale Commissioni</span>
                                        <p class="font-semibold" x-text="formatCurrency(executionData.target.data.total_commissions)"></p>
                                    </div>
                                    <div>
                                        <span class="text-gray-500 dark:text-gray-400">Stato</span>
                                        <p class="font-semibold" x-text="executionData.target.data.status"></p>
                                    </div>
                                    <div>
                                        <span class="text-gray-500 dark:text-gray-400">Valuta</span>
                                        <p class="font-semibold" x-text="executionData.target.data.currency"></p>
                                    </div>
                                    <div x-show="executionData.target.data.issue_date">
                                        <span class="text-gray-500 dark:text-gray-400">Data Emissione</span>
                                        <p class="font-semibold" x-text="executionData.target.data.issue_date"></p>
                                    </div>
                                    <div x-show="executionData.target.data.invoice_number">
                                        <span class="text-gray-500 dark:text-gray-400">N. Fattura</span>
                                        <p class="font-semibold" x-text="executionData.target.data.invoice_number"></p>
                                    </div>
                                </div>

                                <div x-show="executionData.target.data.description" class="text-sm text-gray-600 dark:text-gray-300">
                                    <span class="font-medium">Descrizione:</span>
                                    <span x-text="executionData.target.data.description"></span>
                                </div>

                                {{-- Commission mismatch warning --}}
                                <template x-if="commissionMismatch">
                                    <x-filament::alert color="danger" icon="heroicon-o-exclamation-triangle">
                                        <strong>Attenzione:</strong> Le commissioni totali
                                        (<span x-text="formatCurrency(executionData.target.data.total_commissions)"></span>)
                                        non corrispondono all'importo del proforma
                                        (<span x-text="formatCurrency(executionData.target.data.total_amount)"></span>).
                                    </x-filament::alert>
                                </template>
                            </div>
                        </template>
                    </x-filament::section>
                </div>
            </template>
        </div>

        {{-- ─── RIGHT: Operational Checklist Panel ─── --}}
        <div class="flex flex-col gap-6">
            <template x-if="loading">
                <div class="flex items-center justify-center p-12">
                    <x-filament::loading-indicator class="h-8 w-8 text-primary-500" />
                </div>
            </template>

            <template x-if="!loading && executionData">
                <div class="flex flex-col gap-4">
                    <x-filament::section>
                        <x-slot name="heading">Checklist Operativa</x-slot>
                        <x-slot name="description">
                            <span x-text="checklistProgress"></span>
                        </x-slot>

                        <div class="flex flex-col gap-3">
                            <template x-for="item in executionData.checklists" :key="item.id">
                                <div
                                    class="flex items-start gap-3 rounded-lg border p-4 transition-colors"
                                    :class="{
                                        'border-success-500 bg-success-50 dark:bg-success-900/10': item.is_checked,
                                        'border-gray-200 dark:border-gray-700': !item.is_checked,
                                        'border-danger-300 dark:border-danger-700': item.is_mandatory && !item.is_checked,
                                    }"
                                >
                                    {{-- Checkbox with spinner --}}
                                    <div class="flex-shrink-0 pt-0.5">
                                        <button
                                            type="button"
                                            @click="toggleItem(item)"
                                            :disabled="togglingItem === item.id"
                                            class="flex h-5 w-5 items-center justify-center rounded border"
                                            :class="{
                                                'bg-success-500 border-success-500 text-white': item.is_checked,
                                                'border-gray-300 dark:border-gray-600': !item.is_checked,
                                                'opacity-50 cursor-wait': togglingItem === item.id,
                                            }"
                                        >
                                            <template x-if="togglingItem === item.id">
                                                <svg class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                </svg>
                                            </template>
                                            <template x-if="item.is_checked && togglingItem !== item.id">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                            </template>
                                        </button>
                                    </div>

                                    {{-- Item content --}}
                                    <div class="flex-1">
                                        <p class="text-sm font-medium" x-text="item.instruction"></p>

                                        <div class="mt-1 flex flex-wrap gap-2">
                                            <x-filament::badge x-show="item.is_mandatory" color="danger" size="xs">
                                                Obbligatorio
                                            </x-filament::badge>

                                            <x-filament::badge x-show="item.has_action" color="info" size="xs">
                                                <span x-text="'⚡ ' + item.action_label"></span>
                                            </x-filament::badge>

                                            <span x-show="item.checked_at" class="text-xs text-gray-400">
                                                Completato: <span x-text="formatDate(item.checked_at)"></span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        {{-- Action buttons --}}
                        <div class="mt-6 flex justify-end gap-3">
                            <button
                                type="button"
                                @click="completeTask"
                                :disabled="!canComplete"
                                class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <svg x-show="completingTask" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span x-text="completingTask ? 'Completamento...' : 'Completa Task'"></span>
                            </button>
                        </div>
                    </x-filament::section>

                    {{-- Error toast --}}
                    <template x-if="errorMessage">
                        <div class="rounded-lg border border-danger-300 bg-danger-50 p-4 dark:bg-danger-900/10">
                            <div class="flex items-start gap-3">
                                <svg class="h-5 w-5 flex-shrink-0 text-danger-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-danger-800 dark:text-danger-200" x-text="errorMessage"></p>
                                </div>
                                <button @click="errorMessage = null" class="flex-shrink-0 text-danger-400 hover:text-danger-600">
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </template>

                    {{-- Success toast --}}
                    <template x-if="successMessage">
                        <div class="rounded-lg border border-success-300 bg-success-50 p-4 dark:bg-success-900/10">
                            <div class="flex items-start gap-3">
                                <svg class="h-5 w-5 flex-shrink-0 text-success-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-success-800 dark:text-success-200" x-text="successMessage"></p>
                                </div>
                                <button @click="successMessage = null" class="flex-shrink-0 text-success-400 hover:text-success-600">
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>

    {{-- Alpine.js Component --}}
    @push('scripts')
    <script>
    function bpmTaskRunner(executionId) {
        return {
            executionData: null,
            loading: false,
            togglingItem: null,       // checklist_item_id currently being toggled
            completingTask: false,
            errorMessage: null,
            successMessage: null,

            init() {
                if (executionId) {
                    this.fetchData();
                }
            },

            async fetchData() {
                this.loading = true;
                this.errorMessage = null;
                try {
                    const response = await fetch(`/api/bpm/task-runner/${executionId}`);
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    const json = await response.json();
                    this.executionData = json;
                } catch (e) {
                    this.errorMessage = `Errore nel caricamento: ${e.message}`;
                } finally {
                    this.loading = false;
                }
            },

            async toggleItem(item) {
                const newChecked = !item.is_checked;
                this.togglingItem = item.id;
                this.errorMessage = null;
                this.successMessage = null;

                try {
                    const response = await fetch(
                        `/api/bpm/task-runner/${executionId}/checklist/${item.id}/toggle`,
                        {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                                    || document.querySelector('[name="_token"]')?.value
                                    || '',
                            },
                            body: JSON.stringify({ is_checked: newChecked }),
                        }
                    );

                    const result = await response.json();

                    if (!response.ok || !result.success) {
                        throw new Error(result.message || 'Operazione fallita');
                    }

                    // Update local state
                    item.is_checked = result.is_checked;
                    item.checked_at = result.checked_at;

                    if (result.is_checked) {
                        this.successMessage = result.message;
                    } else {
                        this.successMessage = 'Item rimosso.';
                    }
                } catch (e) {
                    this.errorMessage = e.message;
                    // Revert optimistic update is not needed since we update only on success
                } finally {
                    this.togglingItem = null;
                }
            },

            get checklistProgress() {
                if (!this.executionData) return '';
                const total = this.executionData.checklists.length;
                const checked = this.executionData.checklists.filter(i => i.is_checked).length;
                return `${checked} / ${checked === total ? 'completati' : 'completate'} (${total} totali)`;
            },

            get commissionMismatch() {
                if (!this.executionData?.target?.data) return false;
                const d = this.executionData.target.data;
                return Math.abs(parseFloat(d.total_commissions || 0) - parseFloat(d.total_amount || 0)) > 0.01;
            },

            get canComplete() {
                if (!this.executionData) return false;
                return this.executionData.checklists.some(i => i.is_mandatory && !i.is_checked) === false
                    && this.executionData.checklists.length > 0;
            },

            async completeTask() {
                this.completingTask = true;
                this.errorMessage = null;
                this.successMessage = null;

                try {
                    const response = await fetch(`/api/bpm/task-runner/${executionId}/complete`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                                || document.querySelector('[name="_token"]')?.value
                                || '',
                        },
                    });

                    const result = await response.json();

                    if (!response.ok || !result.success) {
                        throw new Error(result.message || 'Errore nel completamento');
                    }

                    this.successMessage = result.message;
                    await this.fetchData();
                } catch (e) {
                    this.errorMessage = e.message;
                } finally {
                    this.completingTask = false;
                }
            },

            formatCurrency(value) {
                if (value === null || value === undefined) return '€ 0,00';
                return new Intl.NumberFormat('it-IT', {
                    style: 'currency',
                    currency: 'EUR',
                }).format(parseFloat(value));
            },

            formatDate(dateString) {
                if (!dateString) return '';
                return new Date(dateString).toLocaleString('it-IT', {
                    day: '2-digit', month: '2-digit', year: 'numeric',
                    hour: '2-digit', minute: '2-digit',
                });
            },
        };
    }
    </script>
    @endpush
</x-filament-panels::page>
