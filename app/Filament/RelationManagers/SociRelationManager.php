<?php

namespace App\Filament\RelationManagers;

use App\Filament\Concerns\SoggettoCollegato;
use App\Models\Client;
use App\Services\ContattiSocieta;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Soci, amministratori, titolari effettivi e referente di una persona giuridica (client_relations). Il PEP si verifica sul cliente
 * collegato; il referente è il contatto per le comunicazioni (vedi {@see ContattiSocieta}).
 */
class SociRelationManager extends RelationManager
{
    use SoggettoCollegato;

    protected static string $relationship = 'soci';

    protected static ?string $title = 'Soci e titolari effettivi';

    protected static ?string $modelLabel = 'Soggetto';

    protected static ?string $pluralModelLabel = 'Soci e titolari effettivi';

    /** @var array<string, string> */
    public const RUOLI = [
        'legale_rappresentante' => 'Legale rappresentante',
        'amministratore' => 'Amministratore',
        'socio' => 'Socio',
        'titolare_effettivo' => 'Titolare effettivo',
        'referente' => 'Referente',
        'garante' => 'Garante',
        'dipendente' => 'Dipendente',
        'altro' => 'Altro',
    ];

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Client && ! $ownerRecord->is_person;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            ...self::campiSoggetto(),
            Select::make('role')->label('Ruolo')->options(self::RUOLI)->required(),
            TextInput::make('shares_percentage')->label('Quota %')->numeric()->minValue(0)->maxValue(100)->suffix('%'),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('role')
            ->modifyQueryUsing(fn ($query) => $query->with('client'))
            ->columns([
                TextColumn::make('role')->label('Ruolo')->badge()->formatStateUsing(fn (string $state): string => self::RUOLI[$state] ?? $state),
                TextColumn::make('client.name')->label('Cognome / Ragione sociale')->searchable(),
                TextColumn::make('client.first_name')->label('Nome')->placeholder('—'),
                TextColumn::make('client.tax_code')->label('Codice fiscale / P.IVA')->placeholder('—'),
                TextColumn::make('shares_percentage')->label('Quota')->suffix('%')->placeholder('—'),
                IconColumn::make('client.is_pep')->label('PEP')->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->using(function (array $data, self $livewire): Model {
                    $client = self::trovaOCrea($data, $livewire->getOwnerRecord());

                    return $livewire->getOwnerRecord()->soci()->updateOrCreate(
                        ['client_id' => $client->getKey(), 'role' => $data['role']],
                        self::dati($data),
                    );
                }),
            ])
            ->recordActions([
                EditAction::make()
                    ->mountUsing(fn (Schema $schema, Model $record) => $schema->fill($record->attributesToArray() + [
                        'cognome' => $record->client?->name,
                        'nome' => $record->client?->first_name,
                        'codice_fiscale' => $record->client?->tax_code,
                    ]))
                    ->using(function (Model $record, array $data, self $livewire): Model {
                        $record->update(['client_id' => self::trovaOCrea($data, $livewire->getOwnerRecord())->getKey(), 'role' => $data['role']] + self::dati($data));

                        return $record;
                    }),
                DeleteAction::make(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function dati(array $data): array
    {
        return [
            'shares_percentage' => $data['shares_percentage'] ?? null,
        ];
    }
}
