<?php

namespace App\Filament\Agenti\Widgets;

use App\Filament\Agenti\Resources\Pratiche\PraticaResource;
use App\Filament\Widgets\CollapsibleTableWidget;
use App\Models\Pratica;
use App\Services\Agenti\AgentImpersonationService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Models\Document;

/** Documenti delle pratiche dell'agente ancora da consegnare o da correggere (mancanti, respinti, anomali, integrativi). */
class DocumentiDaSeguireAgente extends CollapsibleTableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Documenti da consegnare';

    /** @var array<int, string> */
    private const STATI = ['richiesto', 'respinto', 'anomalo', 'integrativo'];

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Document::query()
                ->where('documentable_type', 'pratica')
                ->whereIn('status', self::STATI)
                ->whereIn('documentable_id', Pratica::query()
                    ->where('fornitori_id', app(AgentImpersonationService::class)->agentFornitoreId() ?? 0)
                    ->whereNull('rejected_at')
                    ->whereNull('erogated_at')
                    ->select('id'))
                ->with('documentable'))
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
