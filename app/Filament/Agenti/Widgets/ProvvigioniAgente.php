<?php

namespace App\Filament\Agenti\Widgets;

use App\Enums\MaturazioneProvvigione;
use App\Models\Provvigione;
use App\Services\Agenti\AgentImpersonationService;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Unico\Core\Models\Fornitore;

/**
 * Provvigioni dell'agente, senza le pratiche declinate o sospese. Di default solo le maturate: togliendo il filtro si
 * vedono anche quelle in maturazione e quelle già pagate, sempre con lo stato della pratica.
 */
class ProvvigioniAgente extends TableWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Le mie provvigioni';

    /** Provvigioni dell'agente in sessione, già senza pratiche declinate o sospese. */
    public static function base(): Builder
    {
        $agente = Fornitore::query()->find(app(AgentImpersonationService::class)->agentFornitoreId());

        if ($agente === null) {
            return Provvigione::query()->whereRaw('0 = 1');
        }

        return Provvigione::query()->dellAgente($agente)->senzaPraticheDeclinateOSospese();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => static::base()->with('pratica.praticaStato'))
            ->defaultSort('data_inserimento_compenso', 'desc')
            ->paginated([10, 25])
            ->columns([
                TextColumn::make('cognome')->label('Cliente')->searchable()->description(fn (Provvigione $record): ?string => $record->nome),
                TextColumn::make('prodotto')->label('Prodotto')->placeholder('—'),
                TextColumn::make('istituto_finanziario')->label('Banca')->placeholder('—'),
                TextColumn::make('pratica.praticaStato.name')->label('Stato pratica')->badge()->placeholder(fn (Provvigione $record): string => $record->status_pratica ?: '—'),
                TextColumn::make('descrizione')->label('Descrizione')->limit(40),
                TextColumn::make('maturazione')
                    ->label('Provvigione')
                    ->state(fn (Provvigione $record): MaturazioneProvvigione => MaturazioneProvvigione::da($record->stato))
                    ->badge(),
                TextColumn::make('importo')->label('Importo')->money('EUR')->sortable()->summarize(Sum::make()->money('EUR')->label('Totale')),
                TextColumn::make('data_pagamento')->label('Pagata il')->date('d/m/Y')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('maturazione')
                    ->label('Provvigione')
                    ->multiple()
                    ->options(MaturazioneProvvigione::class)
                    ->default([MaturazioneProvvigione::Maturata->value])
                    ->query(fn (Builder $query, array $data): Builder => ($data['values'] ?? []) === []
                        ? $query
                        : $query->where(fn (Builder $q) => collect($data['values'])->each(fn (string $valore) => $q->orWhere(fn (Builder $w) => MaturazioneProvvigione::from($valore)->applica($w))))),
            ]);
    }
}
