<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Praticas\PraticaResource;
use App\Models\Pratica;
use App\Support\IstruttoreCorrente;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Pratiche inviate alla banca senza esito da più giorni: l'istruttore controlla il portale e aggiorna lo stato. */
class RisposteBancaInSospeso extends TableWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'In attesa della banca';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Pratica::query()
                ->whereNotNull('sended_at')
                ->whereNull('approved_at')
                ->whereNull('rejected_at')
                ->whereNull('erogated_at')
                ->whereDoesntHave('praticaStato', fn (Builder $stato) => $stato->where('is_suspended', true))
                ->with('praticaStato'))
            ->defaultSort('sended_at')
            ->paginated([10, 25])
            ->columns([
                TextColumn::make('cognome_cliente')->label('Cliente')->searchable()->description(fn (Pratica $record): ?string => $record->nome_cliente),
                TextColumn::make('denominazione_banca')->label('Banca')->searchable(),
                TextColumn::make('codice_pratica_banca')->label('Codice banca')->placeholder('—'),
                TextColumn::make('praticaStato.name')->label('Stato')->badge(),
                TextColumn::make('sended_at')->label('Inviata il')->date('d/m/Y')->sortable(),
                TextColumn::make('giorni')
                    ->label('Senza esito')
                    ->state(fn (Pratica $record): int => (int) $record->sended_at->diffInDays(now()))
                    ->suffix(' gg')
                    ->badge()
                    ->color(fn (int $state): string => $state > 14 ? 'danger' : ($state > 7 ? 'warning' : 'gray')),
            ])
            ->filters([
                Filter::make('mie')
                    ->label('Solo le mie pratiche')
                    ->toggle()
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->where('employee_id', IstruttoreCorrente::id() ?? 0)),
                SelectFilter::make('da')
                    ->label('Inviate da')
                    ->options(['0' => 'Qualsiasi momento', '7' => 'Più di 7 giorni', '14' => 'Più di 14 giorni', '30' => 'Più di 30 giorni'])
                    ->default('7')
                    ->selectablePlaceholder(false)
                    ->query(fn (Builder $query, array $data): Builder => (int) ($data['value'] ?? 0) > 0 ? $query->where('sended_at', '<=', now()->subDays((int) $data['value'])->toDateString()) : $query),
            ])
            ->recordUrl(fn (Pratica $record): string => PraticaResource::getUrl('edit', ['record' => $record]));
    }
}
