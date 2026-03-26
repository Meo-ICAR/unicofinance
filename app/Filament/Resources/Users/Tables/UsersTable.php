<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use STS\FilamentImpersonate\Actions\Impersonate;
use Filament\Tables\Filters\Filter;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Filtro personalizzato per mostrare SOLO gli Admin
                Filter::make('solo_admin')
                    ->label('Solo Amministratori')
                    ->toggle()  // Lo trasforma in un comodo interruttore
                    ->query(fn(Builder $query): Builder => $query->whereHas('companies', function ($q) {
                        // Cerca nella tabella pivot company_user
                        $q->where('company_user.role', 'admin');
                    })),
            ])
            ->recordActions([
                Impersonate::make()
                    // Fondamentale: SOLO il super admin può impersonare altri!
                    ->visible(fn() => auth()->user()->is_super_admin)
                    // Opzionale: impedisci di impersonare te stesso
                    ->hidden(fn($record) => $record->id === auth()->id()),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
