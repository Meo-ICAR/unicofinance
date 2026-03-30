<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use STS\FilamentImpersonate\Actions\Impersonate;

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
                IconColumn::make('is_approved')
                    ->label('Approvato')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_super_admin')
                    ->label('S.Admin')
                    ->boolean()
                    ->visible(fn () => auth()->user()->is_super_admin),
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
                    ->query(fn (Builder $query): Builder => $query->whereHas('companies', function ($q) {
                        // Cerca nella tabella pivot company_user
                        $q->where('company_user.role', 'admin');
                    })),
                Filter::make('in_attesa')
                    ->label('In Attesa di Approvazione')
                    ->query(fn (Builder $query): Builder => $query->where('is_approved', false)),
            ])
            ->recordActions([
                Action::make('approva')
                    ->label('Approva')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->hidden(fn ($record) => $record->is_approved)
                    ->action(fn ($record) => $record->update(['is_approved' => true])),
                Action::make('sospendi')
                    ->label('Sospendi')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->is_approved && ! $record->is_super_admin)
                    ->action(fn ($record) => $record->update(['is_approved' => false])),
                Impersonate::make()
                    // Fondamentale: SOLO il super admin può impersonare altri!
                    ->visible(fn () => auth()->user()->is_super_admin)
                    // Opzionale: impedisci di impersonare te stesso
                    ->hidden(fn ($record) => $record->id === auth()->id()),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
