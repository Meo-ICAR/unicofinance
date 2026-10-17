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

/** Documenti delle pratiche aperte già scaduti o in scadenza: una scadenza passata blocca la pratica in banca. */
class ScadenzeDocumenti extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Documenti in scadenza';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Document::query()
                ->where('documentable_type', 'pratica')
                ->whereNotNull('expires_at')
                ->whereNotIn('status', [DocumentStatus::REJECTED->value, DocumentStatus::NA->value, DocumentStatus::REVOKED->value])
                ->whereIn('documentable_id', Pratica::query()->whereNull('rejected_at')->whereNull('erogated_at')->select('id'))
                ->with('documentable'))
            ->defaultSort('expires_at')
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
                TextColumn::make('expires_at')->label('Scade il')->date('d/m/Y')->sortable(),
                TextColumn::make('giorni')
                    ->label('Giorni')
                    ->state(fn (Document $record): int => (int) now()->startOfDay()->diffInDays($record->expires_at->startOfDay(), false))
                    ->badge()
                    ->color(fn (int $state): string => $state < 0 ? 'danger' : ($state <= 7 ? 'warning' : 'gray'))
                    ->formatStateUsing(fn (int $state): string => $state < 0 ? 'scaduto da '.abs($state).' gg' : 'tra '.$state.' gg')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('expires_at', $direction === 'desc' ? 'desc' : 'asc')),
            ])
            ->filters([
                Filter::make('mie')
                    ->label('Solo le mie pratiche')
                    ->toggle()
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->whereIn('documentable_id', Pratica::query()->where('employee_id', IstruttoreCorrente::id() ?? 0)->select('id'))),
                SelectFilter::make('entro')
                    ->label('Scadenza')
                    ->options(['0' => 'Già scaduti', '7' => 'Entro 7 giorni', '30' => 'Entro 30 giorni', '90' => 'Entro 90 giorni'])
                    ->default('30')
                    ->selectablePlaceholder(false)
                    ->query(fn (Builder $query, array $data): Builder => $query->where('expires_at', '<=', now()->addDays((int) ($data['value'] ?? 30))->endOfDay())),
            ]);
    }
}
