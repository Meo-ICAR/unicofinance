<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Praticas\PraticaResource;
use App\Models\Pratica;
use App\Services\Istruttoria\CompletezzaDocumentale;
use App\Support\IstruttoreCorrente;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Unico\Core\Models\Document;

/** Pratiche senza istruttore (`employee_id`), escluse respinte, sospese e negli stati in lavorazione (`is_working`): l'istruttore le prende in carico. */
class DaPrendereInCarico extends CollapsibleTableWidget implements EspostoInApi
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Da prendere in carico';

    /** Pratiche senza istruttore: non c'è un parametro che le limiti. */
    public static function parametroApi(): string
    {
        return 'employee_id';
    }

    public static function queryApi(?int $id): Builder
    {
        return static::baseQuery();
    }

    public static function rigaApi(Model $record): array
    {
        /** @var Pratica $record */
        return [
            'id' => $record->getKey(),
            'codice_pratica' => $record->codice_pratica,
            'cliente' => trim($record->cognome_cliente.' '.$record->nome_cliente),
            'prodotto' => $record->tipoprodotto?->name,
            'banca' => $record->denominazione_banca,
            'stato_pratica' => $record->praticaStato?->name,
            'agente' => $record->fornitori?->name,
            'data_inserimento_pratica' => $record->data_inserimento_pratica?->toDateString(),
            'giorni_attesa' => $record->data_inserimento_pratica === null ? null : (int) $record->data_inserimento_pratica->diffInDays(today()),
        ];
    }

    private static function baseQuery(): Builder
    {
        return Pratica::query()
            ->whereNull('employee_id')
            ->where('is_notowned', false)
            ->whereNull('rejected_at')
            ->whereDoesntHave('praticaStato', fn (Builder $stato) => $stato->where('is_suspended', true))
            ->whereHas('praticaStato', fn (Builder $stato) => $stato->where('is_working', false))
            ->with(['tipoprodotto', 'fornitori', 'praticaStato']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => static::baseQuery())
            ->defaultSort('data_inserimento_pratica', 'desc')
            ->paginated([10, 25])
            ->columns([
                TextColumn::make('cognome_cliente')->label('Cliente')->searchable()->sortable()->description(fn (Pratica $record): ?string => $record->nome_cliente),
                TextColumn::make('tipoprodotto.name')->label('Prodotto')->sortable()->description(fn (Pratica $record): ?string => $record->denominazione_banca),
                TextColumn::make('praticaStato.name')->label('Stato pratica')->placeholder('—')->badge()->sortable(),
                TextColumn::make('fornitori.name')->label('Agente')->placeholder('—')->sortable(),
                TextColumn::make('giorni_attesa')
                    ->label('Attesa')
                    ->state(fn (Pratica $record): ?int => $record->data_inserimento_pratica === null ? null : (int) $record->data_inserimento_pratica->diffInDays(today()))
                    ->numeric(decimalPlaces: 0)
                    ->placeholder('—')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('data_inserimento_pratica', $direction === 'asc' ? 'desc' : 'asc')),
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
            ->filters([
                // Le pratiche storiche sono tutte senza istruttore: di default si guarda solo il recente.
                SelectFilter::make('recenti')
                    ->label('Inserite')
                    ->options(['7' => 'Ultimi 7 giorni', '30' => 'Ultimi 30 giorni', '90' => 'Ultimi 90 giorni', 'tutte' => 'Tutte'])
                    ->default('30')
                    ->selectablePlaceholder(false)
                    ->query(fn (Builder $query, array $data): Builder => ($data['value'] ?? null) && $data['value'] !== 'tutte'
                        ? $query->where('data_inserimento_pratica', '>=', now()->subDays((int) $data['value'])->toDateString())
                        : $query),
            ])
            ->recordUrl(fn (Pratica $record): string => PraticaResource::getUrl('edit', ['record' => $record]))
            ->recordActions([self::prendiInCarico()])
            ->toolbarActions([BulkActionGroup::make([self::prendiInCaricoMassivo()])]);
    }

    private static function prendiInCarico(): Action
    {
        return Action::make('prendiInCarico')
            ->label('Prendi in carico')
            ->icon(Heroicon::OutlinedHandRaised)
            ->action(function (Pratica $record): void {
                $istruttore = IstruttoreCorrente::id();

                if ($istruttore === null) {
                    self::senzaScheda();

                    return;
                }

                $record->update(['employee_id' => $istruttore]);
                Notification::make()->title('Pratica presa in carico')->success()->send();
            });
    }

    private static function prendiInCaricoMassivo(): BulkAction
    {
        return BulkAction::make('prendiInCaricoMassivo')
            ->label('Prendi in carico')
            ->icon(Heroicon::OutlinedHandRaised)
            ->action(function (Collection $records): void {
                $istruttore = IstruttoreCorrente::id();

                if ($istruttore === null) {
                    self::senzaScheda();

                    return;
                }

                $records->each(fn (Pratica $pratica) => $pratica->update(['employee_id' => $istruttore]));
                Notification::make()->title($records->count().' pratiche prese in carico')->success()->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    private static function senzaScheda(): void
    {
        Notification::make()->title('Nessuna scheda dipendente')->body('Il tuo utente non è collegato a un dipendente: chiedi di collegarlo per prendere in carico le pratiche.')->warning()->send();
    }
}
