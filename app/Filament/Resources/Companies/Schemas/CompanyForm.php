<?php

namespace App\Filament\Resources\Companies\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;


class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('company_tabs')
                    ->tabs([
                        Tab::make('Anagrafica Generale')
                            ->schema([
                                Section::make('Dati Identificativi')
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Ragione Sociale')
                                            ->required()
                                            ->columnSpan(2),
                                        TextInput::make('domain')
                                            ->label('Dominio Email (es. azienda.it)')
                                            ->placeholder('azienda.it')
                                            ->helperText('Usato per l\'auto-associazione degli utenti social'),
                                        Select::make('company_type')
                                            ->label('Tipo Azienda')
                                            ->options([
                                                'mediatore' => 'Mediatore',
                                                'call center' => 'Call center',
                                                'hotel' => 'Hotel',
                                                'sw house' => 'Sw house',
                                            ]),
                                        TextInput::make('vat_number')
                                            ->label('Partita IVA'),
                                        TextInput::make('vat_name')
                                            ->label('Intestazione Fiscale (se diversa)'),
                                        TextInput::make('sponsor')
                                            ->label('Sponsor / Partner'),
                                    ])->columns(2),

                                Section::make('Branding')
                                    ->schema([
                                        SpatieMediaLibraryFileUpload::make('logo')
                                            ->label('Logo Aziendale')
                                            ->collection('logo')
                                            ->image(),
                                        Textarea::make('page_header')
                                            ->label('Header Personalizzato (HTML/Testo)')
                                            ->rows(3),
                                        Textarea::make('page_footer')
                                            ->label('Footer Personalizzato (HTML/Testo)')
                                            ->rows(3),
                                    ]),
                            ]),

                        Tab::make('Compliance & Iscrizioni')
                            ->schema([
                                Section::make('OAM (Organismo Agenti e Mediatori)')
                                    ->schema([
                                        TextInput::make('oam')
                                            ->label('Codice OAM'),
                                        DatePicker::make('oam_at')
                                            ->label('Data Iscrizione OAM'),
                                        TextInput::make('oam_name')
                                            ->label('Nome Registrato OAM')
                                            ->columnSpanFull(),
                                    ])->columns(2),

                                Section::make('IVASS / RUI')
                                    ->schema([
                                        TextInput::make('ivass')
                                            ->label('Codice IVASS'),
                                        DatePicker::make('ivass_at')
                                            ->label('Data Iscrizione IVASS'),
                                        TextInput::make('ivass_name')
                                            ->label('Nome Registrato IVASS'),
                                        Select::make('ivass_section')
                                            ->label('Sezione RUI')
                                            ->options(['A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D', 'E' => 'E']),
                                        TextInput::make('numero_iscrizione_rui')
                                            ->label('N. Iscrizione RUI')
                                            ->columnSpanFull(),
                                    ])->columns(2),
                            ]),

                        Tab::make('Email / SMTP')
                            ->schema([
                                Section::make('Configurazione Server di Invio')
                                    ->description('Parametri per l\'invio di notifiche e credenziali tramite mail aziendale.')
                                    ->schema([
                                        Toggle::make('smtp_enabled')
                                            ->label('Attiva Invio Email')
                                            ->columnSpanFull(),
                                        TextInput::make('smtp_host')
                                            ->label('Host SMTP')
                                            ->placeholder('smtp.example.com'),
                                        TextInput::make('smtp_port')
                                            ->label('Porta SMTP')
                                            ->numeric()
                                            ->placeholder('587'),
                                        TextInput::make('smtp_username')
                                            ->label('Username SMTP'),
                                        TextInput::make('smtp_password')
                                            ->label('Password SMTP')
                                            ->password()
                                            ->revealable(),
                                        Select::make('smtp_encryption')
                                            ->label('Crittografia')
                                            ->options([
                                                'tls' => 'TLS',
                                                'ssl' => 'SSL',
                                                'none' => 'Nessuna',
                                            ]),
                                        Toggle::make('smtp_verify_ssl')
                                            ->label('Verifica Certificato SSL')
                                            ->default(true),
                                        TextInput::make('smtp_from_email')
                                            ->label('Email Mittente (From)')
                                            ->email(),
                                        TextInput::make('smtp_from_name')
                                            ->label('Nome Mittente (From)'),
                                    ])->columns(2),
                            ]),

                        Tab::make('Amministrazione')
                            ->schema([
                                Section::make('Amministratore Principale')
                                    ->description('Crea l\'utente root per questo tenant.')
                                    ->schema([
                                        TextInput::make('admin_name')
                                            ->label('Nome Completo')
                                            ->required()
                                            ->dehydrated(false),
                                        TextInput::make('admin_email')
                                            ->label('Indirizzo Email')
                                            ->email()
                                            ->required()
                                            ->unique('users', 'email')
                                            ->dehydrated(false),
                                        TextInput::make('admin_password')
                                            ->label('Password Iniziale')
                                            ->password()
                                            ->required()
                                            ->dehydrated(false),
                                    ])->columns(1),
                            ])
                            ->hiddenOn('edit'),
                    ]),
            ])
            ->columns(1);
    }
}
