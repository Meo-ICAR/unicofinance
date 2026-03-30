<?php

namespace App\Filament\Resources\Clients;

use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
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
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Clienti';

    protected static string|UnitEnum|null $navigationGroup = 'Anagrafiche';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    /**
     * Mostra solo i clienti reali, escludendo consulenti/fornitori (is_company=true)
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_company', false);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Tabs::make('client_tabs')->tabs([
                Tab::make('Anagrafica')->schema([
                    Section::make('Dati Anagrafici')->schema([
                        Toggle::make('is_person')
                            ->label('Persona Fisica')
                            ->default(true)
                            ->reactive(),
                        TextInput::make('name')
                            ->label(fn ($get) => $get('is_person') ? 'Cognome' : 'Ragione Sociale')
                            ->required(),
                        TextInput::make('first_name')
                            ->label('Nome')
                            ->visible(fn ($get) => $get('is_person')),
                        TextInput::make('tax_code')
                            ->label('Codice Fiscale')
                            ->maxLength(16),
                        TextInput::make('vat_number')
                            ->label('Partita IVA')
                            ->maxLength(20)
                            ->visible(fn ($get) => ! $get('is_person')),
                        TextInput::make('email')->label('Email')->email(),
                        TextInput::make('phone')->label('Telefono')->maxLength(50),
                        Select::make('client_type_id')
                            ->label('Tipo Cliente')
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
                    ])->columns(2),
                    Section::make('Flags')->schema([
                        Toggle::make('is_lead')->label('Lead (non convertito)'),
                        Toggle::make('is_company')->label('Azienda Fornitore'),
                        Toggle::make('is_client')->label('Contraente')->default(true),
                        Toggle::make('is_anonymous')->label('Anonimo'),
                        Toggle::make('is_art108')->label('Esente Art. 108'),
                        Toggle::make('is_approved')->label('Approvato')->default(true),
                        Toggle::make('is_requiredApprovation')->label('Richiede Approvazione'),
                        TextInput::make('salary')->label('Retribuzione Annua')->numeric()->prefix('€'),
                        TextInput::make('salary_quote')->label('Quota Retribuzione')->numeric()->prefix('€'),
                    ])->columns(3),
                ]),
                Tab::make('AML / Rischio')->schema([
                    Section::make('Valutazione Antiriciclaggio')->schema([
                        Toggle::make('is_pep')->label('PEP (Persona Politicamente Esposta)'),
                        Toggle::make('is_sanctioned')->label('In Liste Sanzionatorie'),
                        Toggle::make('is_remote_interaction')->label('Operatività a Distanza (rischio elevato)'),
                        DateTimePicker::make('blacklist_at')->label('Data Inserimento Blacklist'),
                        TextInput::make('blacklisted_by')->label('Inserito in Blacklist Da'),
                        Textarea::make('subfornitori')->label('Subfornitori')->rows(3),
                    ])->columns(2),
                ]),
                Tab::make('Consensi Privacy')->schema([
                    Section::make('Consensi Trattamento Dati')->schema([
                        Toggle::make('privacy_consent')->label('Consenso Privacy Generico'),
                        DateTimePicker::make('general_consent_at')->label('Consenso Generale (Art. 6)'),
                        DateTimePicker::make('privacy_policy_read_at')->label('Presa Visione Informativa (Art. 13)'),
                        DateTimePicker::make('consent_special_categories_at')->label('Consenso Dati Sanitari/Giudiziari'),
                        DateTimePicker::make('consent_sic_at')->label('Consenso SIC (CRIF/CTC/Experian)'),
                        DateTimePicker::make('consent_marketing_at')->label('Consenso Marketing'),
                        DateTimePicker::make('consent_profiling_at')->label('Consenso Profilazione'),
                        DateTimePicker::make('acquired_at')->label('Data Acquisizione Contatto'),
                    ])->columns(2),
                ]),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nominativo')
                    ->description(fn (Client $r) => $r->is_person ? $r->first_name : null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('clientType.name')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),
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
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                IconColumn::make('is_pep')->label('PEP')->boolean(),
                IconColumn::make('is_sanctioned')->label('Sanzioni')->boolean(),
                TextColumn::make('created_at')->label('Acquisito')->date('d/m/Y')->sortable(),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClients::route('/'),
            'create' => CreateClient::route('/create'),
            'edit' => EditClient::route('/{record}/edit'),
        ];
    }
}
