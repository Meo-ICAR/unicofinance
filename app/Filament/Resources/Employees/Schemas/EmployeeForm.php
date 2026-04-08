<?php

namespace App\Filament\Resources\Employees\Schemas;

use App\Enums\EmployeeType;
use App\Enums\SupervisorType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Tabs::make('employee_tabs')->tabs([

                Tab::make('Anagrafica')
                    ->icon('heroicon-o-user')
                    ->schema([
                        Section::make('Dati Personali')->schema([
                            TextInput::make('name')
                                ->label('Nome Completo')
                                ->required(),
                            TextInput::make('cf')
                                ->label('Codice Fiscale')
                                ->placeholder('RSSMRA80A01H501Z')
                                ->maxLength(16),
                            TextInput::make('email')
                                ->label('E-mail Aziendale')
                                ->email()
                                ->required(),
                            TextInput::make('pec')
                                ->label('PEC')
                                ->email(),
                            TextInput::make('phone')
                                ->label('Telefono / Mobile')
                                ->tel(),
                        ])->columns(2),

                        Section::make('Organizzazione')->schema([
                            TextInput::make('role_title')
                                ->label('Qualifica / Ruolo'),
                            TextInput::make('department')
                                ->label('Dipartimento / Area'),
                        ])->columns(2),
                    ]),

                Tab::make('Inquadramento')
                    ->icon('heroicon-o-briefcase')
                    ->schema([
                        Section::make('Dettagli Rapporto')->schema([
                            Select::make('employee_types')
                                ->label('Tipo Profilo')
                                ->options(EmployeeType::class)
                                ->default('dipendente')
                                ->required(),
                            Select::make('supervisor_type')
                                ->label('Grado Supervisore')
                                ->options(SupervisorType::class)
                                ->default('no')
                                ->required(),
                            DatePicker::make('hiring_date')
                                ->label('Data Assunzione'),
                            DatePicker::make('termination_date')
                                ->label('Data Fine Rapporto'),
                        ])->columns(2),

                        Section::make('Sede e Reporting')->schema([
                            Select::make('company_branch_id')
                                ->label('Sede di Lavoro')
                                ->relationship('branch', 'name')
                                ->searchable()
                                ->preload(),
                            Select::make('coordinated_by_id')
                                ->label('Responsabile Diretto')
                                ->relationship('coordinator', 'name')
                                ->searchable()
                                ->preload(),
                            Toggle::make('is_structure')
                                ->label('Personale di Struttura'),
                            Toggle::make('is_ghost')
                                ->label('Utente "Ghost" (Supporto)'),
                        ])->columns(2),
                    ]),

                Tab::make('Certificazioni')
                    ->icon('heroicon-o-academic-cap')
                    ->schema([
                        Section::make('Registri OAM/IVASS/RUI')->schema([
                            TextInput::make('oam')
                                ->label('Codice OAM'),
                            DatePicker::make('oam_at')
                                ->label('Data Iscrizione OAM'),
                            TextInput::make('oam_name')
                                ->label('Denominazione OAM'),
                            DatePicker::make('oam_dismissed_at')
                                ->label('Data Revoca OAM'),
                            TextInput::make('numero_iscrizione_rui')
                                ->label('Numero Iscrizione RUI'),
                            TextInput::make('ivass')
                                ->label('Codice IVASS'),
                        ])->columns(2),
                    ]),

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
