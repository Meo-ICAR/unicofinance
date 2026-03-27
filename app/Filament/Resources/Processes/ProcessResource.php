<?php

namespace App\Filament\Resources\Processes;

use App\Filament\Resources\Processes\RelationManagers\ProcessTasksRelationManager;
use App\Filament\Resources\Processes\Pages;
use App\Models\BusinessFunction;
use App\Models\Process;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use BackedEnum;
use UnitEnum;

class ProcessResource extends Resource
{
    protected static ?string $model = Process::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static string|UnitEnum|null $navigationGroup = 'Organigramma';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    /**
     * QUESTO METODO FILTRA TUTTA LA RISORSA
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();
        $companyId = Filament::getCurrentPanel()->getId();

        // 1. Se l'utente è un SUPER ADMIN tuo (es. tu che sviluppi),
        // fagli vedere tutto per poter fare manutenzione.
        if ($user->is_super_admin) {
            return $query;
        }

        // 2. Se è un utente normale/cliente, filtra!
        return $query->where(function (Builder $q) use ($user, $companyId) {
            $q
                ->whereNull('company_id')  // Mostra i template GLOBALI
                ->orWhere('company_id', $companyId);  // Mostra i template CUSTOM della sua azienda
        });
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Dettagli Processo')->schema([
                TextInput::make('name')
                    ->label('Nome Processo')
                    ->required()
                    ->maxLength(255),
                Select::make('business_function_id')
                    ->label('Funzione di Appartenenza')
                    ->relationship('businessFunction', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('owner_function_id')
                    ->label('Funzione Proprietaria (Supervisione)')
                    ->relationship('ownerFunction', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Select::make('target_model')
                    ->label('Modello di Destinazione (Contesto)')
                    ->helperText('Seleziona da quale tabella si potrà avviare questo processo. Lascia vuoto per renderlo avviabile da ovunque.')
                    ->options([
                        'App\Models\Employee' => 'Dipendenti (Employee)',
                        'App\Models\Client' => 'Consulenti/Clienti (Client)',
                        // Aggiungi qui altre tabelle future (es. 'App\Models\Project' => 'Progetti')
                    ])
                    ->clearable()  // Permette di svuotare la tendina per farlo tornare NULL
                    ->searchable(),
                Toggle::make('is_active')
                    ->label('Attivo')
                    ->default(true),
                Textarea::make('description')
                    ->label('Descrizione')
                    ->columnSpanFull()
                    ->rows(3),
            ])->columns(2),
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
                TextColumn::make('businessFunction.name')
                    ->label('Funzione')
                    ->badge()
                    ->sortable(),
                TextColumn::make('ownerFunction.name')
                    ->label('Proprietario')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->placeholder('Nessuno'),
                IconColumn::make('is_active')
                    ->label('Attivo')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('business_function_id')
                    ->label('Filtra per Funzione')
                    ->relationship('businessFunction', 'name'),
            ])
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
            ProcessTasksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProcesses::route('/'),
            'create' => Pages\CreateProcess::route('/create'),
            'edit' => Pages\EditProcess::route('/{record}/edit'),
        ];
    }
}
