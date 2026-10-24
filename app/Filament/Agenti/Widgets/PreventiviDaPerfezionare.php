<?php

namespace App\Filament\Agenti\Widgets;

use App\Enums\SimulationContractType;
use App\Filament\Agenti\Pages\Simulazione;
use App\Filament\Widgets\CollapsibleTableWidget;
use App\Filament\Widgets\EspostoInApi;
use App\Models\FinancingQuote;
use App\Models\Pratica;
use App\Services\Agenti\AgentImpersonationService;
use App\Services\CambioStatoPratica;
use App\Services\Preventivazione\EliminaPreventivo;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** Preventivi salvati dall'agente e non ancora perfezionati: si perfezionano, si sospendono (la pratica passa a SOSPESA e esce dall'elenco finché non si riprende) o si eliminano, anche in blocco. */
class PreventiviDaPerfezionare extends CollapsibleTableWidget implements EspostoInApi
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Preventivi da perfezionare';

    public static function parametroApi(): string
    {
        return 'fornitori_id';
    }

    public static function queryApi(?int $id): Builder
    {
        return static::senzaSospesi(static::baseQuery($id ?? 0), $id ?? 0);
    }

    public static function rigaApi(Model $record): array
    {
        /** @var FinancingQuote $record */
        return [
            'id' => $record->getKey(),
            'numero' => $record->number,
            'prodotto' => SimulationContractType::tryFrom((string) $record->contract_type)?->getLabel() ?? $record->contract_type,
            'cliente' => $record->company_name,
            'codice_pratica' => $record->pratica?->codice_pratica,
            'importo_richiesto' => $record->requested_amount,
            'quota_cedibile' => $record->assignable_quota,
            'erogabile_min' => $record->disbursed_min,
            'erogabile_max' => $record->disbursed_max,
            'stato_pratica' => $record->pratica?->praticaStato?->name,
            'salvato_il' => $record->created_at?->toIso8601String(),
        ];
    }

    private static function baseQuery(int $fornitoreId): Builder
    {
        // Le pratiche stanno nel database del core: gli id di quelle già in carico all'istruttoria si leggono a parte.
        $inCarico = Pratica::query()->where('fornitori_id', $fornitoreId)->whereNotNull('employee_id')->pluck('id');

        return FinancingQuote::query()
            ->where('fornitore_id', $fornitoreId)
            ->where('status', 'salvato')
            ->where(fn (Builder $query) => $query->whereNull('pratica_id')->orWhereNotIn('pratica_id', $inCarico))
            ->with('pratica.praticaStato');
    }

    /** I preventivi di pratiche sospese si vedono solo accendendo il filtro «Includi i sospesi». */
    private static function senzaSospesi(Builder $query, int $fornitoreId): Builder
    {
        $sospese = Pratica::query()->where('fornitori_id', $fornitoreId)->whereHas('praticaStato', fn (Builder $stato) => $stato->where('is_suspended', true))->pluck('id');

        return $query->where(fn (Builder $q) => $q->whereNull('pratica_id')->orWhereNotIn('pratica_id', $sospese));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => static::baseQuery(app(AgentImpersonationService::class)->agentFornitoreId() ?? 0))
            ->modifyQueryUsing(fn (Builder $query): Builder => ($this->tableFilters['sospesi']['isActive'] ?? false)
                ? $query
                : static::senzaSospesi($query, app(AgentImpersonationService::class)->agentFornitoreId() ?? 0))
            ->filters([
                Filter::make('sospesi')->label('Includi i sospesi')->toggle()->query(fn (Builder $query): Builder => $query),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25])
            ->columns([
                TextColumn::make('number')->label('N.')->sortable(),
                TextColumn::make('contract_type')
                    ->label('Prodotto')
                    ->formatStateUsing(fn (string $state): string => SimulationContractType::tryFrom($state)?->getLabel() ?? $state)
                    ->badge()
                    ->sortable(),
                TextColumn::make('company_name')->label('Cliente')->placeholder('Persona fisica')->description(fn (FinancingQuote $record): ?string => $record->pratica?->codice_pratica)->sortable(),
                TextColumn::make('requested_amount')->label('Importo richiesto')->money('EUR')->placeholder('—')->sortable(),
                TextColumn::make('assignable_quota')->label('Quota cedibile')->money('EUR')->placeholder('—')->sortable(),
                TextColumn::make('disbursed_max')
                    ->label('Erogabile')
                    ->state(fn (FinancingQuote $record): ?string => $record->disbursed_min === null ? null : number_format((float) $record->disbursed_min, 0, ',', '.').' – '.number_format((float) $record->disbursed_max, 0, ',', '.').' €')
                    ->placeholder('—')
                    ->sortable(['disbursed_min', 'disbursed_max']),
                TextColumn::make('pratica.praticaStato.name')
                    ->label('Stato')
                    ->badge()
                    ->color(fn (FinancingQuote $record): string => $record->pratica?->praticaStato?->is_suspended ? 'warning' : 'gray')
                    ->placeholder('—')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        $direction = $direction === 'asc' ? 'asc' : 'desc';
                        $core = (new Pratica)->getConnection()->getDatabaseName();
                        $tabella = $query->getModel()->getTable();

                        return $query->orderByRaw("(select ps.name from `{$core}`.pratiches as p inner join `{$core}`.pratica_stati as ps on ps.id = p.pratica_stato_id where p.id = {$tabella}.pratica_id limit 1) {$direction}");
                    }),
                TextColumn::make('created_at')->label('Salvato')->since()->sortable(),
            ])
            ->recordActions([
                Action::make('perfeziona')
                    ->label('Perfeziona')
                    ->tooltip('Perfeziona')
                    ->iconButton()
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (FinancingQuote $record): bool => ! $record->pratica?->praticaStato?->is_suspended)
                    ->url(fn (FinancingQuote $record): string => Simulazione::getUrl(['preventivo' => $record->getKey()])),
                Action::make('sospendi')
                    ->label('Sospendi')
                    ->tooltip('Sospendi il preventivo')
                    ->iconButton()
                    ->icon(Heroicon::OutlinedPauseCircle)
                    ->color('warning')
                    ->visible(fn (FinancingQuote $record): bool => $record->pratica !== null && ! $record->pratica->praticaStato?->is_suspended)
                    ->requiresConfirmation()
                    ->modalDescription('La pratica passa allo stato «SOSPESA» ed esce dall\'elenco finché non la riprendi: si vede accendendo «Includi i sospesi».')
                    ->action(function (FinancingQuote $record): void {
                        if (! app(CambioStatoPratica::class)->sospendi($record->pratica)) {
                            Notification::make()->title('Preventivo non sospeso')->body('Non esiste lo stato «SOSPESA» per questo prodotto.')->warning()->send();
                        }
                    }),
                Action::make('riprendi')
                    ->label('Riprendi')
                    ->tooltip('Riprendi il preventivo')
                    ->iconButton()
                    ->icon(Heroicon::OutlinedPlayCircle)
                    ->color('info')
                    ->visible(fn (FinancingQuote $record): bool => (bool) $record->pratica?->praticaStato?->is_suspended)
                    ->action(function (FinancingQuote $record): void {
                        if (! app(CambioStatoPratica::class)->riprendi($record->pratica)) {
                            Notification::make()->title('Preventivo non ripreso')->body('Non esiste lo stato «PREVENTIVO» per questo prodotto.')->warning()->send();
                        }
                    }),
                Action::make('elimina')
                    ->label('Elimina')
                    ->tooltip('Elimina il preventivo')
                    ->iconButton()
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Il preventivo viene eliminato insieme alla sua pratica in stato preventivo.')
                    ->action(function (FinancingQuote $record): void {
                        if (app(EliminaPreventivo::class)->elimina($record)) {
                            Notification::make()->title('Preventivo eliminato')->success()->send();

                            return;
                        }

                        Notification::make()->title('Preventivo non eliminato')->body('È già perfezionato o in carico all\'istruttoria.')->warning()->send();
                    }),
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
