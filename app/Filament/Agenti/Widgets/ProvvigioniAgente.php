<?php

namespace App\Filament\Agenti\Widgets;

use App\Enums\MaturazioneProvvigione;
use App\Filament\Widgets\CollapsibleTableWidget;
use App\Filament\Widgets\EspostoInApi;
use App\Models\Provvigione;
use App\Services\Agenti\AgentImpersonationService;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Unico\Core\Models\Fornitore;

/**
 * Provvigioni dell'agente, senza le pratiche declinate o sospese. Di default solo le maturate: togliendo il filtro si
 * vedono anche quelle in maturazione e quelle già pagate, sempre con lo stato della pratica.
 */
class ProvvigioniAgente extends CollapsibleTableWidget implements EspostoInApi
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Le mie provvigioni';

    public static function parametroApi(): string
    {
        return 'fornitori_id';
    }

    public static function queryApi(?int $id): Builder
    {
        return static::baseDi($id)->with('pratica.praticaStato');
    }

    public static function rigaApi(Model $record): array
    {
        /** @var Provvigione $record */
        return [
            'id' => $record->getKey(),
            'pratica' => $record->id_pratica,
            'cliente' => trim($record->cognome.' '.$record->nome),
            'prodotto' => $record->prodotto,
            'banca' => $record->istituto_finanziario,
            'stato_pratica' => $record->pratica?->praticaStato?->name ?? $record->status_pratica,
            'descrizione' => $record->descrizione,
            'maturazione' => MaturazioneProvvigione::da($record->stato)->value,
            'importo' => $record->importo,
            'data_pagamento' => $record->data_pagamento?->toDateString(),
        ];
    }

    /** Provvigioni dell'agente in sessione, già senza pratiche declinate o sospese. */
    public static function base(): Builder
    {
        return static::baseDi(app(AgentImpersonationService::class)->agentFornitoreId());
    }

    /** Provvigioni dell'agente indicato, già senza pratiche declinate o sospese. */
    public static function baseDi(?int $fornitoreId): Builder
    {
        $agente = Fornitore::query()->find($fornitoreId);

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
                TextColumn::make('cognome')->label('Cliente')->searchable()->sortable()->description(fn (Provvigione $record): ?string => $record->nome),
                TextColumn::make('prodotto')->label('Prodotto')->placeholder('—')->sortable(),
                TextColumn::make('istituto_finanziario')->label('Banca')->placeholder('—')->sortable(),
                TextColumn::make('pratica.praticaStato.name')
                    ->label('Stato pratica')
                    ->badge()
                    ->placeholder(fn (Provvigione $record): string => $record->status_pratica ?: '—')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('status_pratica', $direction === 'desc' ? 'desc' : 'asc')),
                TextColumn::make('descrizione')->label('Descrizione')->limit(40)->sortable(),
                TextColumn::make('maturazione')
                    ->label('Provvigione')
                    ->state(fn (Provvigione $record): MaturazioneProvvigione => MaturazioneProvvigione::da($record->stato))
                    ->badge()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('stato', $direction === 'desc' ? 'desc' : 'asc')),
                TextColumn::make('importo')->label('Importo')->money('EUR')->sortable()->summarize(Sum::make()->money('EUR')->label('Totale')),
                TextColumn::make('data_pagamento')->label('Pagata il')->date('d/m/Y')->placeholder('—')->sortable(),
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
