<?php

namespace App\Filament\RelationManagers;

use App\Filament\Resources\Praticas\PraticaResource;
use App\Models\Pratica;
use App\Services\Istruttoria\RichiestaContoEstintivo;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Unico\Core\Models\Cliente;
use Unico\Core\Models\TipoProdotto;

/**
 * Impegni di terzi associati al codice fiscale o alla partita IVA della pratica. Un impegno si apre dalla sua pratica e si segna come
 * da estinguere dalla pratica corrente: un clic su «Da estinguere» imposta `estingued_by` all'id della pratica se è nullo, lo toglie se è già suo, non fa nulla se è di un'altra pratica. «Nuovo» crea un impegno che eredita il richiedente.
 */
class ImpegniTerziRelationManager extends RelationManager
{
    protected static string $relationship = 'impegniTerzi';

    protected static ?string $title = 'Impegni di terzi';

    protected static ?string $modelLabel = 'Impegno';

    protected static ?string $pluralModelLabel = 'Impegni di terzi';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Pratica;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    private function estintoDallaCorrente(Pratica $impegno): bool
    {
        return $impegno->estingued_by !== null && (string) $impegno->estingued_by === (string) $this->getOwnerRecord()->getKey();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('codice_pratica')
            ->modifyQueryUsing(fn ($query) => $query->with('tipoprodotto'))
            ->recordUrl(fn (Pratica $record): string => PraticaResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('tipoprodotto.name')->label('Tipo')->badge(),
                TextColumn::make('denominazione_banca')->label('Banca / ente')->searchable(),
                TextColumn::make('denominazione_prodotto')->label('Prodotto'),
                TextColumn::make('rata')->label('Rata')->money('EUR'),
                TextColumn::make('nrate')->label('Numero rate'),
                TextColumn::make('amount')->label('Importo')->money('EUR'),
                TextColumn::make('data_inserimento_pratica')->label('Decorrenza')->date('d/m/Y')->sortable(),
                IconColumn::make('estinzione')
                    ->label('Da estinguere')
                    ->state(fn (Pratica $record): string => match (true) {
                        $record->estingued_by === null => 'libero',
                        $this->estintoDallaCorrente($record) => 'corrente',
                        default => 'altra',
                    })
                    ->icon(fn (string $state): Heroicon => match ($state) {
                        'corrente' => Heroicon::OutlinedCheckCircle,
                        'altra' => Heroicon::OutlinedMinusCircle,
                        default => Heroicon::OutlinedXCircle,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'corrente' => 'success',
                        'altra' => 'gray',
                        default => 'danger',
                    })
                    ->tooltip(fn (Pratica $record): string => match (true) {
                        $record->estingued_by === null => 'Non estinto: clicca per segnarlo estinto da questa pratica',
                        $this->estintoDallaCorrente($record) => 'Estinto da questa pratica: clicca per toglierlo',
                        default => 'Estinto dalla pratica '.($record->estintaDa?->codice_pratica ?? $record->estingued_by),
                    })
                    ->action(function (Pratica $record): void {
                        if ($record->estingued_by === null) {
                            $record->forceFill(['estingued_by' => $this->getOwnerRecord()->getKey()])->save();
                        } elseif ($this->estintoDallaCorrente($record)) {
                            $record->forceFill(['estingued_by' => null])->save();
                        }
                    }),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Nuovo')
                    ->modalHeading('Nuovo impegno di terzi')
                    ->schema([
                        Select::make('tipoprodotto_id')
                            ->label('Tipo')
                            ->options(fn (): array => TipoProdotto::query()->where('is_third_party', true)->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required(),
                        Select::make('clienti_id')
                            ->label('Istituto')
                            ->helperText('Facoltativo; può essere anche un istituto non attivo.')
                            ->options(fn (): array => Cliente::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable(),
                        TextInput::make('codice_pratica')->label('Numero pratica')->required()->maxLength(255),
                        TextInput::make('rata')->label('Rata')->numeric()->prefix('€'),
                        DatePicker::make('scadenza')->label('Scadenza'),
                    ])
                    ->using(function (array $data): Pratica {
                        $proprietaria = $this->getOwnerRecord();

                        $impegno = Pratica::query()->create($data + [
                            'is_notowned' => true,
                            'upload_at' => now(),
                            'company_id' => $proprietaria->company_id,
                            'codice_fiscale' => $proprietaria->codice_fiscale,
                            'cognome_cliente' => $proprietaria->cognome_cliente,
                            'nome_cliente' => $proprietaria->nome_cliente,
                            'denominazione_banca' => isset($data['clienti_id']) ? Cliente::query()->whereKey($data['clienti_id'])->value('name') : null,
                        ]);

                        if (($avviso = app(RichiestaContoEstintivo::class)->sincronizza($impegno)) !== null) {
                            Notification::make()->title('Conto estintivo')->body($avviso)->warning()->persistent()->send();
                        }

                        return $impegno;
                    }),
            ])
            ->defaultSort('data_inserimento_pratica', 'desc');
    }
}
