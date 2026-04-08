<?php

namespace App\Filament\Resources\Processes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

use App\Filament\Pages\ManualeOperativo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Process;

class ProcessesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('businessFunction.name')
                    ->label('Funzione Aziendale')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('ownerFunction.name')
                    ->label('Proprietario')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('target_model')
                    ->label('Target Model')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Stato')
                    ->boolean()
                    ->sortable(),



                TextColumn::make('updated_at')
                    ->label('Modificato il')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('print')
                    ->label('Stampa')
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->url(fn (Process $record): string => ManualeOperativo::getUrl(['process_id' => $record->id, 'print' => true]))
                    ->openUrlInNewTab(),
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
