<?php

namespace App\Filament\Resources\Clients\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Tabs::make('client_tabs')->tabs([

                Tab::make('Anagrafica')
                    ->icon('heroicon-o-user')
                    ->schema([
                        Section::make('Identità')->schema([
                            Toggle::make('is_person')
                                ->label('Persona Fisica')
                                ->live()
                                ->default(true),
                            Toggle::make('is_company')
                                ->label('Società')
                                ->live()
                                ->default(false),

                            TextInput::make('name')
                                ->label(fn ($get) => $get('is_person') ? 'Cognome / Ragione Sociale' : 'Ragione Sociale')
                                ->required(),
                            TextInput::make('first_name')
                                ->label('Nome')
                                ->visible(fn ($get) => $get('is_person')),

                            TextInput::make('tax_code')
                                ->label('Codice Fiscale')
                                ->placeholder('RSSMRA80A01H501Z')
                                ->visible(fn ($get) => $get('is_person'))
                                ->maxLength(16),
                            TextInput::make('vat_number')
                                ->label('Partita IVA')
                                ->placeholder('01234567890'),
                        ])->columns(2),

                        Section::make('Contatti')->schema([
                            TextInput::make('email')
                                ->label('E-mail')
                                ->email()
                                ->placeholder('esempio@dominio.it'),
                            TextInput::make('phone')
                                ->label('Telefono')
                                ->tel()
                                ->placeholder('+39 012 3456789'),
                        ])->columns(2),

                        Section::make('Classificazione')->schema([
                            Select::make('client_type_id')
                                ->label('Tipo Profilo')
                                ->relationship('clientType', 'name')
                                ->searchable()
                                ->preload(),
                            Select::make('status')
                                ->label('Stato Corrente')
                                ->options([
                                    'lead' => 'Lead',
                                    'raccolta_dati' => 'Raccolta Dati',
                                    'istruttoria' => 'Istruttoria',
                                    'approvato' => 'Approvato',
                                    'respinto' => 'Respinto',
                                    'cliente' => 'Cliente Attivo',
                                    'ex_cliente' => 'Ex Cliente',
                                ])
                                ->default('raccolta_dati')
                                ->required(),
                        ])->columns(2),
                    ]),

                Tab::make('Compliance & GDPR')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Section::make('Indicatori AML')->schema([
                            Toggle::make('is_pep')
                                ->label('Persona Esposta Politicamente (PEP)'),
                            Toggle::make('is_sanctioned')
                                ->label('Soggeto a Sanzioni'),
                            Toggle::make('is_remote_interaction')
                                ->label('Interazione a Distanza'),
                            Toggle::make('is_requiredApprovation')
                                ->label('Richiede Approvazione GRC'),
                            Toggle::make('is_approved')
                                ->label('Approvato'),
                        ])->columns(3),

                        Section::make('Sensi & Consensi Privacy')->schema([
                            Toggle::make('privacy_consent')
                                ->label('Consenso Privacy Generale'),
                            DateTimePicker::make('general_consent_at')
                                ->label('Data Consenso Generale'),
                            DateTimePicker::make('privacy_policy_read_at')
                                ->label('Policy Visionata il'),
                            DateTimePicker::make('consent_special_categories_at')
                                ->label('Consenso Dati Particolari'),
                            DateTimePicker::make('consent_sic_at')
                                ->label('Consenso SIC'),
                            DateTimePicker::make('consent_marketing_at')
                                ->label('Consenso Marketing'),
                            DateTimePicker::make('consent_profiling_at')
                                ->label('Consenso Profilazione'),
                        ])->columns(2),
                    ]),

                Tab::make('Marketing & CRM')
                    ->icon('heroicon-o-presentation-chart-line')
                    ->schema([
                        Grid::make(3)->schema([
                            Toggle::make('is_lead')
                                ->label('Qualificato come Lead'),
                            Toggle::make('is_client')
                                ->label('Qualificato come Cliente'),
                            Toggle::make('is_anonymous')
                                ->label('Anonimizzato'),
                        ]),

                        Select::make('leadsource_id')
                            ->label('Sorgente Lead')
                            ->relationship('leadsource', 'name')
                            ->searchable()
                            ->preload(),

                        DateTimePicker::make('acquired_at')
                            ->label('Data Acquisizione'),

                        DateTimePicker::make('blacklist_at')
                            ->label('Data Blacklist'),

                        TextInput::make('blacklisted_by')
                            ->label('Segnalato da (Blacklist)'),

                        Textarea::make('subfornitori')
                            ->label('Subfornitori / Partner correlati')
                            ->columnSpanFull(),
                    ])->columns(2),

                Tab::make('Finanza')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        TextInput::make('salary')
                            ->label('Reddito Mensile Lordo')
                            ->numeric()
                            ->prefix('€'),
                        TextInput::make('salary_quote')
                            ->label('Quota Cedibile Calcolata')
                            ->numeric()
                            ->prefix('€'),
                        Toggle::make('is_art108')
                            ->label('Soggetto Art. 108 TUB'),
                        TextInput::make('contoCOGE')
                            ->label('Conto Contabile (COGE)'),
                    ])->columns(2),
                      Tab::make('Privacy / GDPR')
                    ->icon('heroicon-o-lock-closed')
                    ->schema([
                        Section::make('Profilo Privacy')->schema([
                            TextInput::make('privacy_role')
                                ->label('Ruolo Privacy Assegnato'),
                            TextInput::make('retention_period')
                                ->label('Termini di Conservazione'),
                            TextInput::make('extra_eu_transfer')
                                ->label('Note Trasferimenti Extra-UE'),
                            TextInput::make('privacy_data')
                                ->label('Altre Informazioni Privacy'),
                        ])->columns(2),

                        Section::make('Dettagli Trattamento')->schema([
                            Textarea::make('purpose')
                                ->label('Finalità del Trattamento')
                                ->rows(3),
                            Textarea::make('data_subjects')
                                ->label('Categorie di Interessati')
                                ->rows(3),
                            Textarea::make('data_categories')
                                ->label('Categorie di Dati')
                                ->rows(3),
                            Textarea::make('security_measures')
                                ->label('Misure di Sicurezza Applicate')
                                ->rows(3),
                        ])->columns(2),
                    ]),


            ])->columnSpanFull(),
        ]);
    }
}
