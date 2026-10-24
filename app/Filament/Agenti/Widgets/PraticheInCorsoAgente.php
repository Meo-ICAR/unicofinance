<?php

namespace App\Filament\Agenti\Widgets;

use App\Filament\Agenti\Resources\Pratiche\PraticaResource;
use App\Filament\Widgets\CollapsibleTableWidget;
use App\Filament\Widgets\EspostoInApi;
use App\Models\Pratica;
use App\Services\Agenti\AgentImpersonationService;
use App\Services\Istruttoria\CompletezzaDocumentale;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Unico\Core\Models\Document;

/** Pratiche dell'agente ancora aperte. Si modificano finché nessun istruttore le ha prese in carico. */
class PraticheInCorsoAgente extends CollapsibleTableWidget implements EspostoInApi
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Le mie pratiche in corso';

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
        /** @var Pratica $record */
        $completezza = app(CompletezzaDocumentale::class)->per($record);

        return [
            'id' => $record->getKey(),
            'codice_pratica' => $record->codice_pratica,
            'cliente' => trim($record->cognome_cliente.' '.$record->nome_cliente),
            'prodotto' => $record->tipoprodotto?->name,
            'banca' => $record->denominazione_banca,
            'stato_pratica' => $record->praticaStato?->name,
            'in_carico_a' => $record->employee?->name,
            'documenti_obbligatori' => $completezza['totale'],
            'documenti_presenti' => $completezza['presenti'],
        ];
    }

    private static function baseQuery(int $fornitoreId): Builder
    {
        return Pratica::query()
            ->where('fornitori_id', $fornitoreId)
            ->whereNull('rejected_at')
            ->whereNull('erogated_at')
            ->whereDoesntHave('praticaStato', fn (Builder $stato) => $stato->where('codice', 'PREVENTIVO'))
            ->with(['tipoprodotto', 'praticaStato', 'employee']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => static::baseQuery(app(AgentImpersonationService::class)->agentFornitoreId() ?? 0))
            ->defaultSort('updated_at', 'desc')
            ->paginated([10, 25])
            ->columns([
                TextColumn::make('cognome_cliente')->label('Cliente')->searchable()->sortable()->description(fn (Pratica $record): ?string => $record->nome_cliente),
                TextColumn::make('tipoprodotto.name')->label('Prodotto')->sortable(),
                TextColumn::make('denominazione_banca')->label('Banca')->sortable(),
                TextColumn::make('praticaStato.name')->label('Stato')->badge()->sortable(),
                TextColumn::make('employee.name')->label('In carico a')->placeholder('Da assegnare')->sortable(),
                TextColumn::make('documentazione')
                    ->label('Documenti')
                    ->state(function (Pratica $record): string {
                        $completezza = app(CompletezzaDocumentale::class)->per($record);

                        return $completezza['totale'] === 0 ? '—' : $completezza['presenti'].'/'.$completezza['totale'];
                    })
                    ->badge()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                        Document::query()->selectRaw('count(*)')->where('documentable_type', 'pratica')->whereColumn('documents.documentable_id', 'pratiches.id')->whereIn('status', ['na', 'caricato', 'approvato', 'provvisorio', 'firmato_otp', 'da_approvare_otp']),
                        $direction === 'desc' ? 'desc' : 'asc',
                    )),
            ])
            ->recordUrl(fn (Pratica $record): ?string => PraticaResource::canEdit($record) ? PraticaResource::getUrl('edit', ['record' => $record]) : null);
    }
}
