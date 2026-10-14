<?php

namespace App\Filament\Resources\Produttori;

use App\Filament\Actions\ImpersonateFornitoreAction;
use App\Filament\Resources\Produttori\Pages\ListProduttori;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Unico\Core\Models\Fornitore;

class ProduttoreResource extends Resource
{
    protected static ?string $model = Fornitore::class;

    protected static ?string $slug = 'produttori';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Produttori';

    protected static ?string $modelLabel = 'Produttore';

    protected static ?string $pluralModelLabel = 'Produttori';

    protected static ?int $navigationSort = -1;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isStaff();
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->whereNull('dismissed_at')
                    ->orWhere('dismissed_at', '>', today());
            });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Produttore')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('piva')
                    ->label('P. IVA')
                    ->searchable(),
                TextColumn::make('user.email')
                    ->label('Accesso portale')
                    ->placeholder('Nessun accesso'),
            ])
            ->defaultSort('name')
            ->recordActions([
                ImpersonateFornitoreAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProduttori::route('/'),
        ];
    }
}
