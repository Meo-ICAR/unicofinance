<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Praticas\PraticaResource;
use App\Models\Pratica;
use App\Support\IstruttoreCorrente;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Pratiche in lavorazione: non respinte, sospese, erogate o chiuse. Di default solo quelle dell'istruttore. */
class PraticheInCorso extends CollapsibleTableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Pratiche in corso';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Pratica::query()
                ->whereNotNull('employee_id')
                ->where('is_notowned', false)
                ->whereNull('rejected_at')
                ->whereNull('erogated_at')
                ->whereDoesntHave('praticaStato', fn (Builder $stato) => $stato->where('is_suspended', true)->orWhere('is_estingued', true))
                ->with(['tipoprodotto', 'praticaStato', 'praticaStatusHistory']))
            ->defaultSort('updated_at', 'asc')
            ->paginated([10, 25])
            ->columns([
                TextColumn::make('cognome_cliente')->label('Cliente')->searchable()->sortable()->description(fn (Pratica $record): ?string => $record->nome_cliente),
                TextColumn::make('tipoprodotto.name')->label('Prodotto')->sortable(),
                TextColumn::make('denominazione_banca')->label('Banca')->searchable()->sortable(),
                TextColumn::make('codice_pratica_banca')->label('Codice banca')->placeholder('—')->sortable(),
                TextColumn::make('praticaStato.name')->label('Stato')->badge()->sortable(),
                TextColumn::make('giorni_nello_stato')
                    ->label('Nello stato')
                    ->state(function (Pratica $record): int {
                        $dal = $record->praticaStatusHistory->max('changed_at') ?? $record->updated_at;

                        return (int) $dal?->diffInDays(now());
                    })
                    ->suffix(' gg')
                    ->badge()
                    ->color(fn (int $state): string => $state > 14 ? 'danger' : ($state > 7 ? 'warning' : 'gray'))
                    ->tooltip('Giorni dall\'ultimo cambio di stato: oltre 7 giallo, oltre 14 rosso')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(
                        'coalesce((select max(changed_at) from pratica_status_history where pratica_status_history.pratica_id = pratiches.id), pratiches.updated_at) '.($direction === 'asc' ? 'desc' : 'asc')
                    )),
            ])
            ->filters([
                Filter::make('mie')
                    ->label('Solo le mie pratiche')
                    ->toggle()
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->where('employee_id', IstruttoreCorrente::id() ?? 0)),
            ])
            ->recordUrl(fn (Pratica $record): string => PraticaResource::getUrl('edit', ['record' => $record]));
    }
}
