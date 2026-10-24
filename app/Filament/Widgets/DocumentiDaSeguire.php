<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Praticas\PraticaResource;
use App\Models\Pratica;
use App\Support\IstruttoreCorrente;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Models\Document;

/** Documenti delle pratiche aperte da seguire: mancanti (richiesti), respinti, anomali o integrativi. Di default solo le mie pratiche. */
class DocumentiDaSeguire extends CollapsibleTableWidget implements EspostoInApi
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Documenti da seguire';

    /** @var array<int, string> */
    private const STATI = ['richiesto', 'respinto', 'anomalo', 'integrativo'];

    public static function parametroApi(): string
    {
        return 'employee_id';
    }

    public static function queryApi(?int $id): Builder
    {
        return static::baseQuery()->when($id, fn (Builder $query, int $istruttore): Builder => static::perIstruttore($query, $istruttore));
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

    private static function baseQuery(): Builder
    {
        return Document::query()
            ->where('documentable_type', 'pratica')
            ->whereIn('status', self::STATI)
            ->whereIn('documentable_id', Pratica::query()->whereNull('rejected_at')->whereNull('erogated_at')->select('id'))
            ->with('documentable');
    }

    private static function perIstruttore(Builder $query, int $istruttore): Builder
    {
        return $query->whereIn('documentable_id', Pratica::query()->where('employee_id', $istruttore)->select('id'));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => static::baseQuery())
            ->defaultSort('updated_at', 'desc')
            ->paginated([10, 25])
            ->columns([
                TextColumn::make('documentable.cognome_cliente')
                    ->label('Pratica')
                    ->description(fn (Document $record): ?string => $record->documentable?->denominazione_banca)
                    ->url(fn (Document $record): ?string => $record->documentable ? PraticaResource::getUrl('edit', ['record' => $record->documentable]) : null)
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                        Pratica::query()->select('cognome_cliente')->whereColumn('pratiches.id', 'documents.documentable_id')->limit(1),
                        $direction === 'desc' ? 'desc' : 'asc',
                    )),
                TextColumn::make('name')->label('Documento')->searchable()->sortable(),
                TextColumn::make('status')->label('Stato')->badge()->sortable(),
                TextColumn::make('rejection_note')->label('Nota')->limit(60)->placeholder('—')->sortable(),
                TextColumn::make('updated_at')->label('Aggiornato')->since()->sortable(),
            ])
            ->filters([
                Filter::make('mie')
                    ->label('Solo le mie pratiche')
                    ->toggle()
                    ->default()
                    ->query(fn (Builder $query): Builder => static::perIstruttore($query, IstruttoreCorrente::id() ?? 0)),
                SelectFilter::make('status')
                    ->label('Stato')
                    ->multiple()
                    ->options(collect(self::STATI)->mapWithKeys(fn (string $stato): array => [$stato => DocumentStatus::from($stato)->getLabel()])->all()),
            ]);
    }
}
