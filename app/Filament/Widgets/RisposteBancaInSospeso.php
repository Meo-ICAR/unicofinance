<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Praticas\PraticaResource;
use App\Models\Pratica;
use App\Services\CambioStatoPratica;
use App\Support\IstruttoreCorrente;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Unico\Core\Models\PraticaStato;

/** Pratiche inviate alla banca senza esito da più giorni: l'istruttore controlla il portale e aggiorna lo stato. */
class RisposteBancaInSospeso extends CollapsibleTableWidget implements EspostoInApi
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'In attesa della banca';

    public static function parametroApi(): string
    {
        return 'employee_id';
    }

    public static function queryApi(?int $id): Builder
    {
        return static::baseQuery()->when($id, fn (Builder $query, int $istruttore): Builder => $query->where('employee_id', $istruttore));
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
            'codice_pratica_banca' => $record->codice_pratica_banca,
            'employee_id' => $record->employee_id,
            'stato_pratica' => $record->praticaStato?->name,
            'inviata_il' => $record->sended_at?->toDateString(),
            'giorni_senza_esito' => (int) $record->sended_at?->diffInDays(now()),
        ];
    }

    private static function baseQuery(): Builder
    {
        return Pratica::query()
            ->whereNotNull('sended_at')
            ->where('is_notowned', false)
            ->whereNull('approved_at')
            ->whereNull('rejected_at')
            ->whereNull('erogated_at')
            ->whereDoesntHave('praticaStato', fn (Builder $stato) => $stato->where('is_suspended', true))
            ->with(['praticaStato', 'clienti', 'tipoprodotto']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => static::baseQuery())
            ->defaultSort('sended_at')
            ->paginated([10, 25])
            ->columns([
                TextColumn::make('cognome_cliente')->label('Cliente')->searchable()->sortable()->description(fn (Pratica $record): ?string => $record->nome_cliente),
                TextColumn::make('denominazione_banca')->label('Banca')->searchable()->sortable(),
                TextColumn::make('codice_pratica_banca')->label('Codice banca')->placeholder('—')->sortable(),
                TextColumn::make('praticaStato.name')->label('Stato')->badge()->sortable(),
                TextColumn::make('sended_at')->label('Inviata il')->date('d/m/Y')->sortable(),
                TextColumn::make('giorni')
                    ->label('Senza esito')
                    ->state(fn (Pratica $record): int => (int) $record->sended_at->diffInDays(now()))
                    ->suffix(' gg')
                    ->badge()
                    ->color(fn (int $state): string => $state > 14 ? 'danger' : ($state > 7 ? 'warning' : 'gray'))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('sended_at', $direction === 'asc' ? 'desc' : 'asc')),
            ])
            ->filters([
                Filter::make('mie')
                    ->label('Solo le mie pratiche')
                    ->toggle()
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->where('employee_id', IstruttoreCorrente::id() ?? 0)),
                SelectFilter::make('da')
                    ->label('Inviate da')
                    ->options(['0' => 'Qualsiasi momento', '7' => 'Più di 7 giorni', '14' => 'Più di 14 giorni', '30' => 'Più di 30 giorni'])
                    ->default('7')
                    ->selectablePlaceholder(false)
                    ->query(fn (Builder $query, array $data): Builder => (int) ($data['value'] ?? 0) > 0 ? $query->where('sended_at', '<=', now()->subDays((int) $data['value'])->toDateString()) : $query),
                SelectFilter::make('clienti_id')
                    ->label('Banca')
                    ->relationship('clienti', 'name', fn (Builder $query) => $query->where('is_active', true))
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([self::cambiaStato()])
            ->recordUrl(fn (Pratica $record): string => PraticaResource::getUrl('edit', ['record' => $record]));
    }

    /** Stesso cambio di stato dell'elenco pratiche: stato del prodotto, motivazione e storico. */
    private static function cambiaStato(): Action
    {
        return Action::make('cambiaStato')
            ->label('Cambia stato')
            ->icon(Heroicon::OutlinedArrowPath)
            ->modalHeading('Cambia stato della pratica')
            ->modalSubmitActionLabel('Salva')
            ->fillForm(fn (Pratica $record): array => ['stato' => $record->pratica_stato_id])
            ->schema(fn (Pratica $record): array => [
                Select::make('stato')
                    ->label('Nuovo stato')
                    ->options(self::stati($record))
                    ->searchable()
                    ->required(),
                Textarea::make('motivazione')->label('Motivazione (facoltativa)')->maxLength(1000)->rows(3),
            ])
            ->action(function (Pratica $record, array $data): void {
                $servizio = app(CambioStatoPratica::class);
                $mancanti = $servizio->documentiMancanti($record, (int) $data['stato']);

                if ($mancanti?->isNotEmpty() && $servizio->documentazioneBloccante()) {
                    Notification::make()->title('Documentazione incompleta')->body('Mancano: '.$servizio->messaggioMancanti($mancanti))->danger()->send();

                    return;
                }

                $servizio->cambia($record, (int) $data['stato'], $data['motivazione'], 'admin');
                Notification::make()->title('Stato aggiornato')->success()->send();
            });
    }

    /**
     * @return array<int|string, string>
     */
    private static function stati(Pratica $pratica): array
    {
        $query = PraticaStato::query()->orderBy('ordine')->orderBy('name');
        $prodotto = $pratica->tipoprodotto?->name;

        if ($prodotto !== null && PraticaStato::query()->where('tipo_finanziamento', $prodotto)->exists()) {
            $query->where('tipo_finanziamento', $prodotto);
        }

        return $query->pluck('name', 'id')->all();
    }
}
