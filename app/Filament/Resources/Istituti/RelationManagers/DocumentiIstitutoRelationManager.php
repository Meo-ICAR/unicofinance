<?php

namespace App\Filament\Resources\Istituti\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Unico\Core\Documents\BankDocumentRequirements;
use Unico\Core\Models\DocumentType;
use Unico\Core\Models\TipoProdotto;

/**
 * Documenti richiesti solo per le pratiche di questo istituto, per uno o più prodotti (es. il mutuo).
 * I documenti generici (informativa privacy, documento d'identità) non hanno istituto e valgono per tutti.
 */
class DocumentiIstitutoRelationManager extends RelationManager
{
    protected static string $relationship = 'documentTypes';

    protected static ?string $title = 'Documenti richiesti';

    protected static ?string $modelLabel = 'Documento';

    protected static ?string $pluralModelLabel = 'Documenti richiesti';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Documento')->required()->maxLength(255)->columnSpanFull(),
            Select::make('prodotti')
                ->label('Prodotti')
                ->helperText('Il documento si chiede solo per le pratiche di questo istituto e di questi prodotti.')
                ->options(fn (): array => TipoProdotto::query()->orderBy('name')->pluck('name', 'name')->all())
                ->multiple()
                ->required(),
            Select::make('fase')
                ->label('Fase')
                ->options(BankDocumentRequirements::fasi())
                ->default(BankDocumentRequirements::CHECKLIST)
                ->required(),
            Toggle::make('is_signed')->label('Da firmare'),
            TextInput::make('document_url')->label('Indirizzo del modulo')->url()->maxLength(255),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label('Documento')->searchable(),
                TextColumn::make('prodotti')
                    ->label('Prodotti')
                    ->state(fn (DocumentType $record): array => app(BankDocumentRequirements::class)->prodotti($record)['prodotti'])
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('fase')
                    ->label('Fase')
                    ->state(fn (DocumentType $record): string => BankDocumentRequirements::fasi()[app(BankDocumentRequirements::class)->prodotti($record)['fase']]),
                IconColumn::make('is_signed')->label('Firma')->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(fn (array $data): array => $data + ['source' => 'cliente', 'client_scope' => 'persona_fisica'])
                    ->using(function (array $data, self $livewire): Model {
                        $type = $livewire->getOwnerRecord()->documentTypes()->create(collect($data)->except(['prodotti', 'fase'])->all());
                        app(BankDocumentRequirements::class)->sync($type, $data['prodotti'], $data['fase']);

                        return $type;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->mountUsing(fn (Schema $schema, DocumentType $record) => $schema->fill($record->attributesToArray() + app(BankDocumentRequirements::class)->prodotti($record)))
                    ->using(function (DocumentType $record, array $data): Model {
                        $record->update(collect($data)->except(['prodotti', 'fase'])->all());
                        app(BankDocumentRequirements::class)->sync($record, $data['prodotti'], $data['fase']);

                        return $record;
                    }),
                DeleteAction::make()->before(fn (DocumentType $record) => app(BankDocumentRequirements::class)->sync($record, [], BankDocumentRequirements::CHECKLIST)),
            ]);
    }
}
