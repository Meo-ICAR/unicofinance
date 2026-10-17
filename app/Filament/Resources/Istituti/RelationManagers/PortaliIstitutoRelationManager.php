<?php

namespace App\Filament\Resources\Istituti\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use Unico\Core\Models\Website;

/** I tre portali dell'istituto: caricamento delle pratiche, modulistica e collegamento via API. Un portale per tipo. */
class PortaliIstitutoRelationManager extends RelationManager
{
    protected static string $relationship = 'websites';

    protected static ?string $title = 'Portali';

    protected static ?string $modelLabel = 'Portale';

    protected static ?string $pluralModelLabel = 'Portali';

    /** Il resource Istituti è in sola lettura, ma i portali si gestiscono da qui. */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')
                ->label('Portale')
                ->options(Website::PORTALI)
                ->required()
                ->live()
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('websiteable_type', $this->getOwnerRecord()->getMorphClass())->where('websiteable_id', $this->getOwnerRecord()->getKey())->whereNull('deleted_at'))
                ->validationMessages(['unique' => 'L\'istituto ha già un portale di questo tipo.'])
                ->helperText(fn (?string $state): ?string => match ($state) {
                    Website::PORTAL_CARICAMENTO => 'Dove gli istruttori inseriscono le pratiche: il collegamento compare accanto alla banca nella pratica.',
                    Website::PORTAL_MODULISTICA => 'Da dove si scaricano i moduli della banca.',
                    Website::PORTAL_API => 'Collegamento via API al portale della banca.',
                    default => null,
                }),
            TextInput::make('url')
                ->label('Indirizzo')
                ->url()
                ->required()
                ->maxLength(500),
            TextInput::make('name')->label('Nome')->helperText('Facoltativo: se vuoto vale il tipo di portale.')->maxLength(255),
            Toggle::make('is_active')->label('Attivo')->default(true)->inline(false),
            TextInput::make('api_username')->label('Utente API')->maxLength(190)->visible(fn ($get): bool => $get('type') === Website::PORTAL_API),
            TextInput::make('api_secret')
                ->label('Password / chiave API')
                ->password()
                ->revealable()
                ->helperText('Si salva cifrata; lasciala vuota per non cambiarla.')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->visible(fn ($get): bool => $get('type') === Website::PORTAL_API),
            Textarea::make('notes')->label('Note')->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('type')->label('Portale')->formatStateUsing(fn (?string $state): string => Website::PORTALI[$state] ?? (string) $state)->badge(),
                TextColumn::make('url')->label('Indirizzo')->url(fn (Website $record): ?string => $record->url, shouldOpenInNewTab: true)->limit(60),
                IconColumn::make('is_active')->label('Attivo')->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->mutateFormDataUsing(function (array $data): array {
                    $data['clienti_id'] = $this->getOwnerRecord()->getKey();

                    return $data;
                }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
