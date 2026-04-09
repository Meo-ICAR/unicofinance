<?php

namespace App\Filament\Resources\Clients\Tables;

use App\Filament\Resources\Clients\Schemas\ClientInfolist;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Anagrafica')
                    ->description(fn ($record) => $record->tax_code ?? $record->vat_number)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('clientType.name')
                    ->label('Tipo Profilo')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'lead' => 'gray',
                        'raccolta_dati' => 'warning',
                        'istruttoria' => 'info',
                        'approvato' => 'success',
                        'respinto' => 'danger',
                        'cliente' => 'success',
                        'ex_cliente' => 'gray',
                        default => 'gray',
                    })
                    ->searchable(),

                TextColumn::make('email')
                    ->label('Contatti')
                    ->description(fn ($record) => $record->phone)
                    ->searchable(),

                IconColumn::make('is_pep')
                    ->label('PEP')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_sanctioned')
                    ->label('Sanz.')
                    ->boolean()
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('salary')
                    ->label('Reddito')
                    ->money('EUR')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('acquired_at')
                    ->label('Acquisito il')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'lead' => 'Lead',
                        'raccolta_dati' => 'Raccolta Dati',
                        'istruttoria' => 'Istruttoria',
                        'approvato' => 'Approvato',
                        'respinto' => 'Respinto',
                        'cliente' => 'Cliente Attivo',
                        'ex_cliente' => 'Ex Cliente',
                    ]),
                \Filament\Tables\Filters\SelectFilter::make('client_type_id')
                    ->label('Tipo Profilo')
                    ->relationship('clientType', 'name'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                ViewAction::make('stampa_nomina_esterna')
                    ->label('Visualizza Nomina')
                    ->icon('heroicon-o-document-text')
                    ->infolist(fn (Schema $infolist) => ClientInfolist::configure($infolist))
                    ->modalHeading('Anteprima Nomina Responsabile Esterno')
                    ->modalWidth('5xl')
                    ->slideOver()
                    ->color('warning'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
