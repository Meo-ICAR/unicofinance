<?php

namespace App\Filament\Resources\TaskExecutions;

use App\Filament\Resources\TaskExecutions\Pages\ManageTaskExecutions;
use App\Models\Employee;
use App\Models\TaskExecution;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use BackedEnum;
use UnitEnum;

class TaskExecutionResource extends Resource
{
    protected static ?string $model = TaskExecution::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|UnitEnum|null $navigationGroup = 'Processi';

    protected static ?string $recordTitleAttribute = 'id';

    public static function getEloquentQuery(): Builder
    {
        // Recuperiamo l'ID dell'impiegato partendo dall'utente loggato.
        // Usiamo ->value('id') per fare una query leggerissima ed estrarre solo quel numero.
        $employeeId = Employee::where('user_id', auth()->id())->value('id');

        // Se l'utente loggato non è collegato a nessun dipendente (es. è un Super Admin di sistema)
        // blocchiamo la visualizzazione dei task per evitare errori o mostrare task non suoi.
        if (!$employeeId) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->where('employee_id', $employeeId)
            ->orderBy('due_date', 'asc');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('status')
                    ->required(),
                Section::make('Dettagli Operazione')
                    ->schema([
                        // Placeholder: Testo in sola lettura
                        Placeholder::make('task_name')
                            ->label('Cosa devi fare:')
                            ->content(fn(?TaskExecution $record): string => $record?->templateTask?->name ?? 'N/D'),
                        Select::make('status')
                            ->label('Stato Lavorazione')
                            ->options([
                                'todo' => 'Da Iniziare',
                                'in_progress' => 'In Corso',
                                'completed' => 'Completato',
                            ])
                            ->required()
                            ->native(false),
                    ])
                    ->columns(2),
                Section::make('Checklist Operativa')
                    ->description('Spunta le voci man mano che completi le operazioni')
                    ->schema([
                        Repeater::make('checkedItems')
                            ->relationship()  // Punta al metodo checkedItems() nel modello
                            // BLINDIAMO IL REPEATER: non si aggiunge, non si toglie, non si riordina
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->schema([
                                // Mostriamo l'istruzione originale
                                Placeholder::make('instruction')
                                    ->label('')
                                    ->content(fn($record) => $record?->originalItem?->instruction)
                                    ->extraAttributes(['class' => 'text-lg font-medium']),
                                // L'unica cosa che l'utente può toccare: la spunta
                                Toggle::make('is_checked')
                                    ->label('Fatto?')
                                    ->onIcon('heroicon-m-check')
                                    ->offIcon('heroicon-m-x-mark')
                                    ->onColor('success'),
                            ])
                            ->columns(2),  // Mette istruzione e toggle sulla stessa riga
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('processTask.name')
                    ->label('Task'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('templateTask.name')
                    ->label('Task')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Stato')
                    ->colors([
                        'danger' => 'todo',
                        'warning' => 'in_progress',
                        'success' => 'completed',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'todo' => 'Da Iniziare',
                        'in_progress' => 'In Corso',
                        'completed' => 'Completato',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Scadenza')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                // Filtro rapido per vedere solo le cose da finire
                SelectFilter::make('status')
                    ->options([
                        'todo' => 'Da Iniziare',
                        'in_progress' => 'In Corso',
                        'completed' => 'Completati',
                    ]),
            ])
            ->actions([
                // L'azione di Edit è l'unica che serve qui
                EditAction::make()
                    ->modalHeading('Esecuzione Task')
                    ->modalWidth('2xl'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTaskExecutions::route('/'),
        ];
    }
}
