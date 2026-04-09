<?php

namespace App\Filament\Resources\RequestRegistries\RelationManagers;

use App\Models\RequestRegistryAttachment;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';

    public function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->components([
                FileUpload::make('file_path')
                    ->label('File')
                    ->directory('request-registry-attachments')
                    ->required(),
                Select::make('file_type')
                    ->label('Tipo Documento')
                    ->options([
                        'richiesta' => 'Richiesta',
                        'documento_identita' => 'Documento Identità',
                        'procura_mandato' => 'Procura / Mandato',
                        'risposta' => 'Risposta',
                        'documentazione_interna' => 'Documentazione Interna',
                    ])
                    ->default('richiesta')
                    ->required(),
                Select::make('uploaded_by')
                    ->label('Caricato da')
                    ->options(User::query()->pluck('name', 'id'))
                    ->searchable()
                    ->default(auth()->id()),
            ])
            ->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('file_path')
                    ->label('File')
                    ->limit(50)
                    ->searchable(),
                BadgeColumn::make('file_type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (string $state) => ucfirst(str_replace('_', ' ', $state)))
                    ->colors([
                        'info' => 'richiesta',
                        'success' => 'documento_identita',
                        'warning' => 'procura_mandato',
                        'success' => 'risposta',
                        'gray' => 'documentazione_interna',
                    ]),
                TextColumn::make('uploader.name')
                    ->label('Caricato da')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Data Caricamento')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
