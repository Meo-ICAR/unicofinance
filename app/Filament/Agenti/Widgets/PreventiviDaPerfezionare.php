<?php

namespace App\Filament\Agenti\Widgets;

use App\Enums\SimulationContractType;
use App\Filament\Agenti\Pages\Simulazione;
use App\Models\FinancingQuote;
use App\Models\Pratica;
use App\Services\Agenti\AgentImpersonationService;
use App\Services\Preventivazione\EliminaPreventivo;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Preventivi salvati dall'agente e non ancora perfezionati: si riprendono per perfezionarli o si eliminano (anche in blocco). */
class PreventiviDaPerfezionare extends TableWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Preventivi da perfezionare';

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $fornitoreId = app(AgentImpersonationService::class)->agentFornitoreId() ?? 0;

                // Le pratiche stanno nel database del core: gli id di quelle già in carico all'istruttoria si leggono a parte.
                $inCarico = Pratica::query()->where('fornitori_id', $fornitoreId)->whereNotNull('employee_id')->pluck('id');

                return FinancingQuote::query()
                    ->where('fornitore_id', $fornitoreId)
                    ->where('status', 'salvato')
                    ->where(fn (Builder $query) => $query->whereNull('pratica_id')->orWhereNotIn('pratica_id', $inCarico))
                    ->with('pratica');
            })
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25])
            ->columns([
                TextColumn::make('number')->label('N.')->sortable(),
                TextColumn::make('contract_type')
                    ->label('Prodotto')
                    ->formatStateUsing(fn (string $state): string => SimulationContractType::tryFrom($state)?->getLabel() ?? $state)
                    ->badge(),
                TextColumn::make('company_name')->label('Cliente')->placeholder('Persona fisica')->description(fn (FinancingQuote $record): ?string => $record->pratica?->codice_pratica),
                TextColumn::make('requested_amount')->label('Importo richiesto')->money('EUR')->placeholder('—'),
                TextColumn::make('assignable_quota')->label('Quota cedibile')->money('EUR')->placeholder('—'),
                TextColumn::make('disbursed_max')
                    ->label('Erogabile')
                    ->state(fn (FinancingQuote $record): ?string => $record->disbursed_min === null ? null : number_format((float) $record->disbursed_min, 0, ',', '.').' – '.number_format((float) $record->disbursed_max, 0, ',', '.').' €')
                    ->placeholder('—'),
                TextColumn::make('created_at')->label('Salvato')->since()->sortable(),
            ])
            ->recordActions([
                Action::make('perfeziona')
                    ->label('Perfeziona')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->url(fn (FinancingQuote $record): string => Simulazione::getUrl(['preventivo' => $record->getKey()])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('elimina')
                        ->label('Elimina preventivi')
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription('I preventivi selezionati vengono eliminati insieme alle loro pratiche in stato preventivo.')
                        ->action(function (Collection $records): void {
                            $eliminati = $records->filter(fn (FinancingQuote $quote): bool => app(EliminaPreventivo::class)->elimina($quote))->count();

                            Notification::make()->title($eliminati.' preventivi eliminati')->success()->send();

                            if ($eliminati < $records->count()) {
                                Notification::make()->title(($records->count() - $eliminati).' preventivi non eliminati')->body('Sono già perfezionati o in carico all\'istruttoria.')->warning()->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
