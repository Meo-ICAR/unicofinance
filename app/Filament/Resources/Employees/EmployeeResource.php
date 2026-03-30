<?php

namespace App\Filament\Resources\Employees;

use App\Enums\EmployeeType;
use App\Enums\SupervisorType;
use App\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\Employees\RelationManagers\BusinessFunctionsRelationManager;
use App\Models\CompanyBranch;
use App\Models\Employee;
use BackedEnum;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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
use UnitEnum;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Dipendenti';

    protected static string|UnitEnum|null $navigationGroup = 'Organigramma';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Tabs::make('employee_tabs')->tabs([

                Tab::make('Anagrafica')->schema([
                    Section::make('Dati Personali')->schema([
                        TextInput::make('name')->label('Nome Completo')->required(),
                        TextInput::make('cf')->label('Codice Fiscale')->maxLength(16),
                        TextInput::make('email')->label('Email Aziendale')->email(),
                        TextInput::make('pec')->label('PEC'),
                        TextInput::make('phone')->label('Telefono')->maxLength(16),
                        TextInput::make('role_title')->label('Qualifica')->maxLength(100),
                        TextInput::make('department')->label('Dipartimento')->maxLength(100),
                    ])->columns(2),

                    Section::make('Contratto e Sede')->schema([
                        Select::make('employee_types')
                            ->label('Tipo Dipendente')
                            ->options(collect(EmployeeType::cases())->mapWithKeys(fn ($e) => [$e->value => $e->getLabel()]))
                            ->required(),
                        Select::make('supervisor_type')
                            ->label('Supervisore?')
                            ->options(collect(SupervisorType::cases())->mapWithKeys(fn ($e) => [$e->value => $e->getLabel()]))
                            ->required(),
                        DatePicker::make('hiring_date')->label('Data Assunzione'),
                        DatePicker::make('termination_date')->label('Data Fine Rapporto'),
                        Select::make('company_branch_id')
                            ->label('Sede')
                            ->options(fn () => CompanyBranch::pluck('name', 'id'))
                            ->searchable()
                            ->nullable(),
                        Select::make('coordinated_by_id')
                            ->label('Coordinatore')
                            ->options(fn () => Employee::pluck('name', 'id'))
                            ->searchable()
                            ->nullable(),
                        Toggle::make('is_structure')->label('Personale di Struttura'),
                        Toggle::make('is_ghost')->label('Personale Prestato'),
                    ])->columns(2),
                ]),

                Tab::make('OAM / IVASS / RUI')->schema([
                    Section::make('Registrazioni Normative')->schema([
                        TextInput::make('oam')->label('Codice OAM')->maxLength(100),
                        DatePicker::make('oam_at')->label('Data Iscrizione OAM'),
                        DatePicker::make('oam_dismissed_at')->label('Data Revoca OAM'),
                        TextInput::make('oam_name')->label('Nome OAM')->maxLength(100),
                        TextInput::make('numero_iscrizione_rui')->label('N. Iscrizione RUI')->maxLength(50),
                        TextInput::make('ivass')->label('Codice IVASS')->maxLength(100),
                    ])->columns(2),
                ]),

                Tab::make('Privacy / GDPR')->schema([
                    Section::make('Dati Privacy')->schema([
                        TextInput::make('privacy_role')->label('Ruolo Privacy'),
                        TextInput::make('retention_period')->label('Tempi di Conservazione'),
                        TextInput::make('extra_eu_transfer')->label('Trasferimento Extra-UE'),
                        TextInput::make('privacy_data')->label('Altri Dati Privacy'),
                        Textarea::make('purpose')->label('Finalità del Trattamento')->rows(3),
                        Textarea::make('data_subjects')->label('Categorie di Interessati')->rows(3),
                        Textarea::make('data_categories')->label('Categorie di Dati')->rows(3),
                        Textarea::make('security_measures')->label('Misure di Sicurezza')->rows(3),
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
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employee_types')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof EmployeeType ? $state->getLabel() : $state),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('branch.name')
                    ->label('Sede')
                    ->sortable(),
                TextColumn::make('hiring_date')
                    ->label('Assunto il')
                    ->date('d/m/Y')
                    ->sortable(),
                IconColumn::make('is_structure')
                    ->label('Struttura')
                    ->boolean(),
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
            'index' => ListEmployees::route('/'),
            'create' => CreateEmployee::route('/create'),
            'edit' => EditEmployee::route('/{record}/edit'),
        ];
    }
}
