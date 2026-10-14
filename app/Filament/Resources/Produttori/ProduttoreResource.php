<?php

namespace App\Filament\Resources\Produttori;

use App\Filament\Actions\ImpersonateFornitoreAction;
use App\Filament\Resources\Produttori\Pages\ListProduttori;
use App\Filament\Resources\Produttori\Pages\ViewProduttore;
use App\Filament\Resources\Produttori\RelationManagers\IstitutiBloccatiRelationManager;
use App\Filament\Resources\Produttori\RelationManagers\ListiniProvvigionaliRelationManager;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
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

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name')->label('Produttore'),
            TextEntry::make('email')->label('Email'),
            TextEntry::make('piva')->label('P. IVA'),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            ListiniProvvigionaliRelationManager::class,
            IstitutiBloccatiRelationManager::class,
        ];
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
                IconColumn::make('accesso_portale')
                    ->label('Portale')
                    ->state(fn (Fornitore $record): bool => $record->user_id !== null)
                    ->boolean()
                    ->tooltip(fn (Fornitore $record): string => $record->user?->email ?? 'Nessun accesso'),
            ])
            ->defaultSort('name')
            ->recordActions([
                Action::make('listini')
                    ->label('Listini')
                    ->icon('heroicon-o-rectangle-stack')
                    ->url(fn (Fornitore $record): string => static::getUrl('view', ['record' => $record])),
                ImpersonateFornitoreAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProduttori::route('/'),
            'view' => ViewProduttore::route('/{record}'),
        ];
    }
}
