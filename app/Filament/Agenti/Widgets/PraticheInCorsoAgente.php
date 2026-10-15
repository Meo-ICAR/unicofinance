<?php

namespace App\Filament\Agenti\Widgets;

use App\Filament\Agenti\Resources\Pratiche\PraticaResource;
use App\Models\Pratica;
use App\Services\Agenti\AgentImpersonationService;
use App\Services\Istruttoria\CompletezzaDocumentale;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Pratiche dell'agente ancora aperte. Si modificano finché nessun istruttore le ha prese in carico. */
class PraticheInCorsoAgente extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Le mie pratiche in corso';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Pratica::query()
                ->where('fornitori_id', app(AgentImpersonationService::class)->agentFornitoreId() ?? 0)
                ->whereNull('rejected_at')
                ->whereNull('erogated_at')
                ->whereDoesntHave('praticaStato', fn (Builder $stato) => $stato->where('codice', 'PREVENTIVO'))
                ->with(['tipoprodotto', 'praticaStato', 'employee']))
            ->defaultSort('updated_at', 'desc')
            ->paginated([10, 25])
            ->columns([
                TextColumn::make('cognome_cliente')->label('Cliente')->searchable()->description(fn (Pratica $record): ?string => $record->nome_cliente),
                TextColumn::make('tipoprodotto.name')->label('Prodotto'),
                TextColumn::make('denominazione_banca')->label('Banca'),
                TextColumn::make('praticaStato.name')->label('Stato')->badge(),
                TextColumn::make('employee.name')->label('In carico a')->placeholder('Da assegnare'),
                TextColumn::make('documentazione')
                    ->label('Documenti')
                    ->state(function (Pratica $record): string {
                        $completezza = app(CompletezzaDocumentale::class)->per($record);

                        return $completezza['totale'] === 0 ? '—' : $completezza['presenti'].'/'.$completezza['totale'];
                    })
                    ->badge(),
            ])
            ->recordUrl(fn (Pratica $record): ?string => PraticaResource::canEdit($record) ? PraticaResource::getUrl('edit', ['record' => $record]) : null);
    }
}
