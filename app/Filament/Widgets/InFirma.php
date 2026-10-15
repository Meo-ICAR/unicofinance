<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Praticas\PraticaResource;
use App\Models\Pratica;
use App\Support\IstruttoreCorrente;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Unico\Core\Models\SignatureRequest;

/** Richieste di firma aperte (OTP) sui documenti delle pratiche: da quanti giorni aspettano il cliente. */
class InFirma extends TableWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Documenti in firma';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => SignatureRequest::query()
                ->open()
                ->whereHas('document', fn (Builder $document) => $document->where('documentable_type', 'pratica'))
                ->with(['document.documentable', 'signatureRequestSigners']))
            ->defaultSort('sent_at')
            ->paginated([10, 25])
            ->columns([
                TextColumn::make('document.documentable.cognome_cliente')
                    ->label('Pratica')
                    ->description(fn (SignatureRequest $record): ?string => $record->document?->documentable?->denominazione_banca)
                    ->url(fn (SignatureRequest $record): ?string => $record->document?->documentable ? PraticaResource::getUrl('edit', ['record' => $record->document->documentable]) : null),
                TextColumn::make('document.name')->label('Documento'),
                TextColumn::make('signatureRequestSigners.name')->label('Firmatari')->listWithLineBreaks()->placeholder('—'),
                TextColumn::make('status')->label('Stato')->badge(),
                TextColumn::make('sent_at')->label('Inviata')->since()->placeholder('—'),
            ])
            ->filters([
                Filter::make('mie')
                    ->label('Solo le mie pratiche')
                    ->toggle()
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->whereHas('document', fn (Builder $document) => $document->whereIn('documentable_id', Pratica::query()->where('employee_id', IstruttoreCorrente::id() ?? 0)->select('id')))),
            ]);
    }
}
