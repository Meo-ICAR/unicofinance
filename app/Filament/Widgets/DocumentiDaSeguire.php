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
use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Models\Document;

/** Documenti delle pratiche aperte da seguire: mancanti (richiesti), respinti, anomali o integrativi. Di default solo le mie pratiche. */
class DocumentiDaSeguire extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Documenti da seguire';

    /** @var array<int, string> */
    private const STATI = ['richiesto', 'respinto', 'anomalo', 'integrativo'];

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Document::query()
                ->where('documentable_type', 'pratica')
                ->whereIn('status', self::STATI)
                ->whereIn('documentable_id', Pratica::query()->whereNull('rejected_at')->whereNull('erogated_at')->select('id'))
                ->with('documentable'))
            ->defaultSort('updated_at', 'desc')
            ->paginated([10, 25])
            ->columns([
                TextColumn::make('documentable.cognome_cliente')
                    ->label('Pratica')
                    ->description(fn (Document $record): ?string => $record->documentable?->denominazione_banca)
                    ->url(fn (Document $record): ?string => $record->documentable ? PraticaResource::getUrl('edit', ['record' => $record->documentable]) : null),
                TextColumn::make('name')->label('Documento')->searchable(),
                TextColumn::make('status')->label('Stato')->badge(),
                TextColumn::make('rejection_note')->label('Nota')->limit(60)->placeholder('—'),
                TextColumn::make('updated_at')->label('Aggiornato')->since(),
            ])
            ->filters([
                Filter::make('mie')
                    ->label('Solo le mie pratiche')
                    ->toggle()
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->whereIn('documentable_id', Pratica::query()->where('employee_id', IstruttoreCorrente::id() ?? 0)->select('id'))),
                SelectFilter::make('status')
                    ->label('Stato')
                    ->multiple()
                    ->options(collect(self::STATI)->mapWithKeys(fn (string $stato): array => [$stato => DocumentStatus::from($stato)->getLabel()])->all()),
            ]);
    }
}
