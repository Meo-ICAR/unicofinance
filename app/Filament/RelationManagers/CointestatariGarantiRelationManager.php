<?php

namespace App\Filament\RelationManagers;

use App\Filament\Concerns\SoggettoCollegato;
use App\Models\Pratica;
use App\Support\ProdottoMutuo;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Cointestatari (coobbligati) e garanti di un mutuo, oltre al richiedente. */
class CointestatariGarantiRelationManager extends RelationManager
{
    use SoggettoCollegato;

    protected static string $relationship = 'praticaClients';

    protected static ?string $title = 'Cointestatari e garanti';

    protected static ?string $modelLabel = 'Soggetto';

    protected static ?string $pluralModelLabel = 'Cointestatari e garanti';

    /** @var array<string, string> */
    public const RUOLI = ['coobbligato' => 'Cointestatario', 'garante' => 'Garante'];

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Pratica && ProdottoMutuo::perPratica($ownerRecord);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            ...self::campiSoggetto(),
            Select::make('role')->label('Ruolo')->options(self::RUOLI)->required(),
            TextInput::make('share_percent')->label('Quota %')->numeric()->minValue(0)->maxValue(100)->suffix('%'),
            Textarea::make('notes')->label('Note')->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('role', array_keys(self::RUOLI))->with('client'))
            ->columns([
                TextColumn::make('role')->label('Ruolo')->badge()->formatStateUsing(fn (string $state): string => self::RUOLI[$state] ?? $state),
                TextColumn::make('client.name')->label('Cognome / Ragione sociale')->searchable(),
                TextColumn::make('client.first_name')->label('Nome')->placeholder('—'),
                TextColumn::make('client.tax_code')->label('Codice fiscale / P.IVA')->placeholder('—'),
                TextColumn::make('share_percent')->label('Quota')->suffix('%')->placeholder('—'),
                TextColumn::make('notes')->label('Note')->limit(40)->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data, self $livewire, CreateAction $action): ?Model {
                        $client = self::trovaOCrea($data, $livewire->getOwnerRecord());

                        if ($livewire->getOwnerRecord()->praticaClients()->where('client_id', $client->getKey())->where('role', $data['role'])->exists()) {
                            Notification::make()->title('Soggetto già presente con questo ruolo')->warning()->send();
                            $action->halt();
                        }

                        return $livewire->getOwnerRecord()->praticaClients()->create([
                            'client_id' => $client->getKey(),
                            'role' => $data['role'],
                            'share_percent' => $data['share_percent'] ?? null,
                            'notes' => $data['notes'] ?? null,
                        ]);
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->mountUsing(fn (Schema $schema, Model $record) => $schema->fill([
                        'cognome' => $record->client?->name,
                        'nome' => $record->client?->first_name,
                        'codice_fiscale' => $record->client?->tax_code,
                        'role' => $record->role,
                        'share_percent' => $record->share_percent,
                        'notes' => $record->notes,
                    ]))
                    ->using(function (Model $record, array $data, self $livewire): Model {
                        $client = self::trovaOCrea($data, $livewire->getOwnerRecord());
                        $record->update([
                            'client_id' => $client->getKey(),
                            'role' => $data['role'],
                            'share_percent' => $data['share_percent'] ?? null,
                            'notes' => $data['notes'] ?? null,
                        ]);

                        return $record;
                    }),
                DeleteAction::make(),
            ]);
    }
}
