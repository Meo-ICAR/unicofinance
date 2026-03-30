<?php

namespace App\Filament\Resources\Clients;

use App\Filament\Resources\Clients\Pages\CreateConsultant;
use App\Filament\Resources\Clients\Pages\EditConsultant;
use App\Filament\Resources\Clients\Pages\ListConsultants;
use App\Filament\Resources\Clients\RelationManagers\BusinessFunctionsRelationManager;
use App\Models\Client;
use App\Models\ClientType;
use BackedEnum;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ConsultantResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationLabel = 'Consulenti / Fornitori';

    protected static string|UnitEnum|null $navigationGroup = 'Organigramma';

    protected static ?string $slug = 'consultants';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    /**
     * Filtra solo i record consulenti/aziende fornitore (is_company = true).
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_company', true);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Dati Consulente / Fornitore')->schema([
                Toggle::make('is_person')
                    ->label('Persona Fisica')
                    ->default(false),
                TextInput::make('name')
                    ->label('Ragione Sociale / Cognome')
                    ->required(),
                TextInput::make('first_name')
                    ->label('Nome')
                    ->visible(fn ($get) => $get('is_person')),
                TextInput::make('tax_code')->label('Codice Fiscale')->maxLength(16),
                TextInput::make('vat_number')->label('Partita IVA')->maxLength(20),
                TextInput::make('email')->label('Email')->email(),
                TextInput::make('phone')->label('Telefono')->maxLength(50),
                Select::make('client_type_id')
                    ->label('Tipo')
                    ->options(fn () => ClientType::pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),
                Select::make('status')
                    ->label('Stato')
                    ->options([
                        'raccolta_dati' => '📋 Raccolta Dati',
                        'valutazione_aml' => '🔍 Valutazione AML',
                        'approvata' => '✅ Approvata',
                        'sos_inviata' => '🚨 SOS Inviata',
                        'chiusa' => '🔒 Chiusa',
                    ])
                    ->default('raccolta_dati'),
                TextInput::make('contoCOGE')->label('Conto COGE'),
                Textarea::make('subfornitori')->label('Subfornitori')->rows(3),
            ])->columns(2),
            Section::make('Consensi & Flags')->schema([
                Toggle::make('is_requiredApprovation')->label('Richiede Approvazione'),
                Toggle::make('is_approved')->label('Approvato')->default(true),
                Toggle::make('is_anonymous')->label('Anonimo'),
                Toggle::make('is_art108')->label('Esente Art. 108'),
                DateTimePicker::make('acquired_at')->label('Data Acquisizione'),
                DateTimePicker::make('blacklist_at')->label('Data Blacklist'),
                TextInput::make('blacklisted_by')->label('Inserito in blacklist da'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Ragione Sociale')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('clientType.name')
                    ->label('Tipo')
                    ->badge(),
                TextColumn::make('vat_number')
                    ->label('P.IVA')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'approvata' => 'success',
                        'valutazione_aml' => 'warning',
                        'sos_inviata' => 'danger',
                        'chiusa' => 'gray',
                        default => 'info',
                    }),
                IconColumn::make('is_approved')->label('Approvato')->boolean(),
            ])
            ->filters([])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            BusinessFunctionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConsultants::route('/'),
            'create' => CreateConsultant::route('/create'),
            'edit' => EditConsultant::route('/{record}/edit'),
        ];
    }
}
