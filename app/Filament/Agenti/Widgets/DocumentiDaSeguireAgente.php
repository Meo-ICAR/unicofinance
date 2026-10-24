<?php

namespace App\Filament\Agenti\Widgets;

use App\Filament\Agenti\Resources\Pratiche\PraticaResource;
use App\Filament\Widgets\CollapsibleTableWidget;
use App\Filament\Widgets\EspostoInApi;
use App\Models\Pratica;
use App\Services\Agenti\AgentImpersonationService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Models\Document;

/** Documenti delle pratiche dell'agente ancora da consegnare o da correggere (mancanti, respinti, anomali, integrativi). */
class DocumentiDaSeguireAgente extends CollapsibleTableWidget implements EspostoInApi
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Documenti da consegnare';

    /** @var array<int, string> */
    private const STATI = ['richiesto', 'respinto', 'anomalo', 'integrativo'];

    public static function parametroApi(): string
    {
        return 'fornitori_id';
    }

    public static function queryApi(?int $id): Builder
    {
        return static::baseQuery($id ?? 0);
    }

    public static function rigaApi(Model $record): array
    {
        /** @var Document $record */
        return [
            'id' => $record->getKey(),
            'pratica_id' => $record->documentable_id,
            'cliente' => $record->documentable?->cognome_cliente,
            'banca' => $record->documentable?->denominazione_banca,
            'documento' => $record->name,
            'stato' => $record->status instanceof \BackedEnum ? $record->status->value : $record->status,
            'nota' => $record->rejection_note,
            'aggiornato_il' => $record->updated_at?->toIso8601String(),
        ];
    }

    private static function baseQuery(int $fornitoreId): Builder
    {
        return Document::query()
            ->where('documentable_type', 'pratica')
            ->whereIn('status', self::STATI)
            ->whereIn('documentable_id', Pratica::query()
                ->where('fornitori_id', $fornitoreId)
                ->whereNull('rejected_at')
                ->whereNull('erogated_at')
                ->select('id'))
            ->with('documentable');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => static::baseQuery(app(AgentImpersonationService::class)->agentFornitoreId() ?? 0))
            ->defaultSort('updated_at', 'desc')
            ->paginated([10, 25])
            ->columns([
                TextColumn::make('documentable.cognome_cliente')
                    ->label('Pratica')
                    ->description(fn (Document $record): ?string => $record->documentable?->denominazione_banca)
                    ->url(fn (Document $record): ?string => $record->documentable && PraticaResource::canEdit($record->documentable) ? PraticaResource::getUrl('edit', ['record' => $record->documentable]) : null)
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                        Pratica::query()->select('cognome_cliente')->whereColumn('pratiches.id', 'documents.documentable_id')->limit(1),
                        $direction === 'desc' ? 'desc' : 'asc',
                    )),
                TextColumn::make('name')->label('Documento')->searchable()->sortable(),
                TextColumn::make('status')->label('Stato')->badge()->sortable(),
                TextColumn::make('rejection_note')->label('Nota')->limit(60)->placeholder('—')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stato')
                    ->multiple()
                    ->options(collect(self::STATI)->mapWithKeys(fn (string $stato): array => [$stato => DocumentStatus::from($stato)->getLabel()])->all()),
            ]);
    }
}
