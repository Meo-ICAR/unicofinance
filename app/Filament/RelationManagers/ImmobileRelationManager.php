<?php

namespace App\Filament\RelationManagers;

use App\Models\Pratica;
use App\Support\ProdottoMutuo;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Unico\Core\Models\AddressType;
use Unico\Core\Models\PraticaImmobile;

/** Dati dell'immobile di un mutuo; l'indirizzo è un Address di tipo "Località". */
class ImmobileRelationManager extends RelationManager
{
    protected static string $relationship = 'immobili';

    protected static ?string $title = "Dati dell'immobile";

    protected static ?string $modelLabel = 'Immobile';

    protected static ?string $pluralModelLabel = 'Immobili';

    public const TIPO_INDIRIZZO = 'Località';

    private const CAMPI_INDIRIZZO = ['street', 'numero', 'zip_code', 'city', 'province'];

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Pratica && ProdottoMutuo::perPratica($ownerRecord);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Immobile')->columns(3)->columnSpanFull()->schema([
                Select::make('tipologia')->label('Tipologia')->options(['abitazione' => 'Abitazione', 'box' => 'Box / posto auto', 'terreno' => 'Terreno', 'commerciale' => 'Commerciale']),
                Select::make('destinazione_uso')->label('Destinazione')->options(['prima_casa' => 'Prima casa', 'seconda_casa' => 'Seconda casa', 'investimento' => 'Investimento']),
                Select::make('finalita')->label('Finalità del mutuo')->live()->options(['acquisto' => 'Acquisto', 'surroga' => 'Surroga', 'liquidita' => 'Liquidità', 'ristrutturazione' => 'Ristrutturazione']),
                TextInput::make('valore_stimato')->label('Valore stimato')->numeric()->prefix('€'),
                TextInput::make('prezzo_acquisto')->label('Prezzo di acquisto')->numeric()->prefix('€'),
            ]),
            Section::make('Lavori di ristrutturazione')->columns(1)->columnSpanFull()
                ->visible(fn (Get $get, self $livewire): bool => $get('finalita') === 'ristrutturazione' || $livewire->getOwnerRecord()->tipoprodotto?->name === 'Mutuo ristrutturazione')
                ->schema([
                    TextInput::make('importo_lavori')->label('Importo dei lavori')->numeric()->prefix('€'),
                ]),
            Section::make('Località')->columns(3)->columnSpanFull()->schema([
                TextInput::make('street')->label('Via / Piazza')->required()->maxLength(255)->columnSpan(2),
                TextInput::make('numero')->label('Civico')->maxLength(255),
                TextInput::make('zip_code')->label('CAP')->maxLength(20),
                TextInput::make('city')->label('Comune')->maxLength(255),
                TextInput::make('province')->label('Provincia')->maxLength(100),
            ]),
            Textarea::make('notes')->label('Note')->columnSpanFull(),
        ])->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('tipologia')
            ->modifyQueryUsing(fn ($query) => $query->with('address'))
            ->columns([
                TextColumn::make('tipologia')->label('Tipologia')->badge()->placeholder('—'),
                TextColumn::make('address.street')->label('Indirizzo')->formatStateUsing(fn (?string $state, Model $record): string => trim($state.' '.($record->address?->numero ?? '').', '.($record->address?->city ?? ''), ' ,'))->placeholder('—'),
                TextColumn::make('valore_stimato')->label('Valore')->money('EUR')->placeholder('—'),
                TextColumn::make('ltv')
                    ->label('LTV')
                    ->state(fn (PraticaImmobile $record): ?float => $record->ltv($this->getOwnerRecord()->amount))
                    ->suffix('%')
                    ->placeholder('—'),
                TextColumn::make('finalita')->label('Finalità')->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()->using(function (array $data): Model {
                    $immobile = $this->getOwnerRecord()->immobili()->create(self::soloImmobile($data));
                    $this->salvaIndirizzo($immobile, $data);

                    return $immobile;
                }),
            ])
            ->recordActions([
                EditAction::make()
                    ->mountUsing(fn (Schema $schema, PraticaImmobile $record) => $schema->fill($record->attributesToArray() + ($record->address?->only(self::CAMPI_INDIRIZZO) ?? [])))
                    ->using(function (PraticaImmobile $record, array $data): Model {
                        $record->update(self::soloImmobile($data));
                        $this->salvaIndirizzo($record, $data);

                        return $record;
                    }),
                DeleteAction::make()->before(fn (PraticaImmobile $record) => $record->address()->delete()),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function soloImmobile(array $data): array
    {
        return array_diff_key($data, array_flip(self::CAMPI_INDIRIZZO));
    }

    /** @param  array<string, mixed>  $data */
    private function salvaIndirizzo(PraticaImmobile $immobile, array $data): void
    {
        $immobile->address()->updateOrCreate([], array_intersect_key($data, array_flip(self::CAMPI_INDIRIZZO)) + [
            'address_type_id' => AddressType::query()->firstOrCreate(['name' => self::TIPO_INDIRIZZO])->getKey(),
        ]);
    }
}
