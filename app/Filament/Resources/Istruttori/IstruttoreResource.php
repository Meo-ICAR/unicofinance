<?php

namespace App\Filament\Resources\Istruttori;

use App\Filament\Actions\ImpersonateIstruttoreAction;
use App\Filament\Resources\Istruttori\Pages\ListIstruttori;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Unico\Core\Models\Employee;

/** Dipendenti dell'istruttoria (e degli altri uffici) con l'accesso al pannello; da qui un amministratore può impersonificarli. */
class IstruttoreResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $slug = 'istruttori';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Configurazione';

    protected static ?string $navigationLabel = 'Istruttori';

    protected static ?string $modelLabel = 'Istruttore';

    protected static ?string $pluralModelLabel = 'Istruttori';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 6;

    public static function canViewAny(): bool
    {
        $utente = auth()->user();

        return $utente instanceof User && in_array($utente->role, ['admin', 'super_admin'], true);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nome')->searchable()->sortable(),
                TextColumn::make('role_title')->label('Ruolo')->wrap()->placeholder('—'),
                TextColumn::make('employee_types')->label('Tipo')->badge()->placeholder('—'),
                TextColumn::make('email')->label('Email')->placeholder('—'),
                IconColumn::make('utente')
                    ->label('Accesso')
                    ->state(fn (Employee $record): bool => $record->user_id !== null)
                    ->boolean()
                    ->tooltip('Ha un utente collegato per entrare nel pannello'),
            ])
            ->filters([
                Filter::make('istruttori')
                    ->label('Solo istruttori')
                    ->toggle()
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->where('employee_types', 'istruttore')),
            ])
            ->recordActions([
                ImpersonateIstruttoreAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIstruttori::route('/'),
        ];
    }
}
