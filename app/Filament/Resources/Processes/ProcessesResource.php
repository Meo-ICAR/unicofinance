<?php

namespace App\Filament\Resources\Processes;

use App\Filament\Resources\Processes\Pages\CreateProcesses;
use App\Filament\Resources\Processes\Pages\EditProcesses;
use App\Filament\Resources\Processes\Pages\ListProcesses;
use App\Filament\Resources\Processes\RelationManagers\TasksRelationManager;
use App\Filament\Resources\Processes\Schemas\ProcessesForm;
use App\Filament\Resources\Processes\Tables\ProcessesTable;
use App\Models\BusinessFunction;
use App\Models\Process;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Notifications\Notification;
use BackedEnum;
use UnitEnum;

class ProcessesResource extends Resource
{
    protected static ?string $model = Process::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    // protected static ?string $navigationGroup = 'Processi BPM';

    protected static ?int $navigationSort = 1;

    protected static ?string $label = 'Processi';

    protected static ?string $pluralLabel = 'Processi';

    protected static ?string $modelLabel = 'Processo';

    public static function form(Schema $schema): Schema
    {
        return ProcessesForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProcessesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TasksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProcesses::route('/'),
            'create' => CreateProcesses::route('/create'),
            'edit' => EditProcesses::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();
        $companyId =  $user->current_company_id;

        // 1. Se l'utente è un SUPER ADMIN tuo (es. tu che sviluppi),
        // fagli vedere tutto per poter fare manutenzione.
        if ($user->is_super_admin) {
            return $query;
        }

        // 2. Filtra per company_id del tenant corrente
        $query->where('company_id', $companyId);

        // 3. Se l'utente ha un ruolo specifico nella company_user, mostra solo i processi
        // delle funzioni aziendali a cui ha accesso
        if ($user->companies()->exists()) {
            $userCompanies = $user->companies()->pluck('company_id');
            $accessibleBusinessFunctions = BusinessFunction::whereIn('company_id', $userCompanies)
                ->pluck('id');

            $query->whereHas('businessFunction', function ($q) use ($accessibleBusinessFunctions) {
                $q->whereIn('business_function_id', $accessibleBusinessFunctions);
            });
        }

        return $query;
    }

    public static function getActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn($record) => auth()->user()->can('update', $record)),
            DeleteAction::make()
                ->visible(fn($record) => auth()->user()->can('delete', $record))
                ->requiresConfirmation()
                ->modalHeading('Elimina Processo')
                ->modalDescription('Sei sicuro di voler eliminare questo processo? Questa azione non può essere annullata.')
                ->modalSubmitActionLabel('Sì, elimina')
                ->modalCancelActionLabel('Annulla'),
            Action::make('duplicate')
                ->label('Duplica')
                ->icon('heroicon-o-document-duplicate')
                ->visible(fn($record) => auth()->user()->can('create', Process::class))
                ->action(function ($record) {
                    $newProcess = $record->replicate();
                    $newProcess->name = $record->name . ' (Copia)';
                    $newProcess->save();
Notification::make()
                        ->title('Processo Duplicato')
                        ->body("Il processo '{$record->name}' è stato duplicato con successo.")
                        ->success()
                        ->send();
                }),
            Action::make('activate')
                ->label('Attiva')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn($record) => !$record->is_active && auth()->user()->can('update', $record))
                ->action(function ($record) {
                    $record->is_active = true;
                    $record->save();

                    Notification::make()
                        ->title('Processo Attivato')
                        ->body("Il processo '{$record->name}' è stato attivato.")
                        ->success()
                        ->send();
                }),
            Action::make('deactivate')
                ->label('Disattiva')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn($record) => $record->is_active && auth()->user()->can('update', $record))
                ->action(function ($record) {
                    $record->is_active = false;
                    $record->save();

                    Notification::make()
                        ->title('Processo Disattivato')
                        ->body("Il processo '{$record->name}' è stato disattivato.")
                        ->warning()
                        ->send();
                }),
        ];
    }

    public static function getBulkActions(): array
    {
        return [
            //
        ];
    }
}
