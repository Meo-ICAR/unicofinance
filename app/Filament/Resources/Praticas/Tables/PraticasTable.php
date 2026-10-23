<?php

namespace App\Filament\Resources\Praticas\Tables;

use App\Filament\Actions\ExcelExportAction;
use App\Filament\Resources\Praticas\PraticaResource;
use App\Models\Pratica;
use App\Services\CambioStatoPratica;
use App\Services\Istruttoria\CompletezzaDocumentale;
use App\Support\CompensoMediazione;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Unico\Core\Models\PraticaStato;

class PraticasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->headerActions([ExcelExportAction::make()])
            ->columns([
                TextColumn::make('denominazione_banca')
                    ->label('Banca')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('codice_pratica_banca')
                    ->label('Codice banca')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('documentazione')
                    ->label('Documenti')
                    ->state(function (Pratica $record): string {
                        $completezza = app(CompletezzaDocumentale::class)->per($record);

                        return $completezza['totale'] === 0 ? '—' : $completezza['presenti'].'/'.$completezza['totale'];
                    })
                    ->badge()
                    ->color(function (string $state): string {
                        if ($state === '—') {
                            return 'gray';
                        }

                        [$presenti, $totale] = array_map('intval', explode('/', $state));

                        return $presenti >= $totale ? 'success' : 'warning';
                    })
                    ->tooltip('Documenti obbligatori presenti sul totale richiesto dal prodotto'),
                TextColumn::make('mediazione')
                    ->label('Mediazione')
                    ->state(fn (Pratica $record): string => CompensoMediazione::formatta($record->mediation_fee_amount, $record->mediation_fee_percent, $record->amount))
                    ->tooltip('Importo di mediazione chiesto al cliente')
                    ->toggleable(),
                TextColumn::make('tipoprodotto.name')
                    ->label('Tipo prodotto')
                    ->sortable(),
                TextColumn::make('praticaStato.name')
                    ->label('Stato pratica')
                    ->badge()
                    ->sortable()
                    ->tooltip('Clicca per cambiare lo stato')
                    ->action(self::cambiaStato()),
                TextColumn::make('stati_da_visionare')
                    ->label('Cambi stato')
                    ->state(fn (Pratica $record): ?string => $record->praticaStatusHistory()->daVisionare()->exists() ? 'Da visionare' : null)
                    ->badge()
                    ->color('warning')
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->placeholder('—')
                    ->tooltip('Cambio di stato non fatto dall\'istruttore (es. rinuncia del produttore), di cui non ha ancora preso visione'),
                TextColumn::make('cognome_cliente')
                    ->label('Cognome cliente')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('stati_da_visionare')
                    ->label('Cambi stato da visionare')
                    ->placeholder('Tutte')
                    ->trueLabel('Con cambi stato da visionare')
                    ->falseLabel('Senza')
                    ->queries(
                        true: fn (Builder $query) => $query->conStatiDaVisionare(),
                        false: fn (Builder $query) => $query->whereNot(fn (Builder $q) => $q->conStatiDaVisionare()),
                    ),
                SelectFilter::make('denominazione_banca')
                    ->label('Banca')
                    ->options(fn (): array => Pratica::query()
                        ->whereNotNull('denominazione_banca')
                        ->distinct()
                        ->orderBy('denominazione_banca')
                        ->pluck('denominazione_banca', 'denominazione_banca')
                        ->all())
                    ->searchable(),
                SelectFilter::make('employee_id')
                    ->label('Istruttore')
                    ->relationship('employee', 'name', fn (Builder $query) => $query->where('employee_types', 'istruttore'))
                    ->searchable()
                    ->preload(),
                Filter::make('inviate_in_banca')
                    ->label('Con data di invio in banca')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('sended_at')),
                Filter::make('non_perfezionate')
                    ->label('Non perfezionate')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNull('erogated_at')),
                SelectFilter::make('fornitori_id')
                    ->label('Agente')
                    ->relationship('fornitori', 'name', fn (Builder $query) => $query->where('is_active', true))
                    ->searchable()
                    ->preload(),
                SelectFilter::make('pratica_stato_id')
                    ->label('Stato pratica')
                    ->relationship('praticaStato', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('ultimo_aggiornamento')
                    ->label('Ultimo aggiornamento')
                    ->schema([
                        DatePicker::make('dal')->label('Aggiornata dal'),
                        DatePicker::make('al')->label('Aggiornata fino al'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dal'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('updated_at', '>=', $date))
                        ->when($data['al'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('updated_at', '<=', $date))),
                // Predefinito: le pratiche sospese sono escluse.
                TernaryFilter::make('sospese')
                    ->label('Pratiche sospese')
                    ->placeholder('Tutte')
                    ->trueLabel('Solo sospese')
                    ->falseLabel('Escluse le sospese')
                    ->default(false)
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('praticaStato', fn (Builder $stato) => $stato->where('is_suspended', true)),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('praticaStato', fn (Builder $stato) => $stato->where('is_suspended', true)),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                Filter::make('escludi_respinte')
                    ->label('Escludi pratiche respinte')
                    ->toggle()
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->whereNull('rejected_at')),
            ])
            ->recordActions([
                self::cambiaStato()->iconButton()->icon(Heroicon::OutlinedArrowPath)->tooltip('Cambia stato'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([self::cambiaStatoMassivo()]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordUrl(fn (Pratica $record): string => PraticaResource::getUrl('edit', ['record' => $record]));
    }

    /** Stati scelti in base al prodotto della pratica (i codici sono per tipo di finanziamento); senza prodotto, tutti. */
    private static function stati(?Pratica $pratica): array
    {
        $query = PraticaStato::query()->orderBy('ordine')->orderBy('name');
        $prodotto = $pratica?->tipoprodotto?->name;

        if ($prodotto !== null && PraticaStato::query()->where('tipo_finanziamento', $prodotto)->exists()) {
            $query->where('tipo_finanziamento', $prodotto);
        }

        return $query->pluck('name', 'id')->all();
    }

    private static function campiCambioStato(?Pratica $pratica = null): array
    {
        return [
            Select::make('stato')->label('Nuovo stato')->options(self::stati($pratica))->searchable()->required(),
            Textarea::make('motivazione')->label('Motivazione')->required()->maxLength(1000)->rows(3),
        ];
    }

    /** Dopo aver aggiornato lo stato sul portale della banca: stato, motivazione nello storico e date automatiche. */
    private static function cambiaStato(): Action
    {
        return Action::make('cambiaStato')
            ->label('Cambia stato')
            ->modalHeading('Cambia stato della pratica')
            ->modalSubmitActionLabel('Salva')
            ->fillForm(fn (Pratica $record): array => ['stato' => $record->pratica_stato_id])
            ->schema(fn (Pratica $record): array => self::campiCambioStato($record))
            ->action(function (Pratica $record, array $data): void {
                $servizio = app(CambioStatoPratica::class);
                $mancanti = $servizio->documentiMancanti($record, (int) $data['stato']);

                if ($mancanti?->isNotEmpty() && $servizio->documentazioneBloccante()) {
                    Notification::make()->title('Documentazione incompleta')->body('Mancano: '.$servizio->messaggioMancanti($mancanti))->danger()->send();

                    return;
                }

                $servizio->cambia($record, (int) $data['stato'], $data['motivazione'], 'admin');
                Notification::make()->title('Stato aggiornato')->success()->send();

                if ($mancanti?->isNotEmpty()) {
                    Notification::make()->title('Documentazione incompleta')->body('Mancano: '.$servizio->messaggioMancanti($mancanti))->warning()->send();
                }
            });
    }

    private static function cambiaStatoMassivo(): BulkAction
    {
        return BulkAction::make('cambiaStatoMassivo')
            ->label('Cambia stato')
            ->icon(Heroicon::OutlinedArrowPath)
            ->modalHeading('Cambia stato delle pratiche selezionate')
            ->schema(fn (): array => self::campiCambioStato())
            ->action(function (Collection $records, array $data): void {
                $servizio = app(CambioStatoPratica::class);
                $incomplete = 0;
                $aggiornate = 0;

                foreach ($records as $pratica) {
                    $mancanti = $servizio->documentiMancanti($pratica, (int) $data['stato']);

                    if ($mancanti?->isNotEmpty()) {
                        $incomplete++;

                        if ($servizio->documentazioneBloccante()) {
                            continue;
                        }
                    }

                    $servizio->cambia($pratica, (int) $data['stato'], $data['motivazione'], 'admin');
                    $aggiornate++;
                }

                Notification::make()->title($aggiornate.' pratiche aggiornate')->success()->send();

                if ($incomplete > 0) {
                    Notification::make()->title($incomplete.' pratiche con documentazione incompleta')->body($servizio->documentazioneBloccante() ? 'Non sono state aggiornate.' : 'Aggiornate comunque: controlla i documenti mancanti.')->warning()->send();
                }
            })
            ->deselectRecordsAfterCompletion();
    }
}
