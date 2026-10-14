<?php

namespace App\Filament\RelationManagers;

use App\Models\Pratica;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class WebsitesRelationManager extends RelationManager
{
    protected static string $relationship = 'websites';

    protected static ?string $title = 'Siti web';

    protected static ?string $modelLabel = 'Sito web';

    protected static ?string $pluralModelLabel = 'Siti web';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Pratica;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('domain')
                ->label('Dominio')
                ->required()
                ->maxLength(255),
            TextInput::make('name')
                ->label('Nome sito')
                ->required()
                ->maxLength(255),
            Select::make('type')
                ->label('Tipologia')
                ->options([
                    'istituzionale' => 'Istituzionale',
                    'social' => 'Social',
                    'landing' => 'Landing',
                    'vetrina' => 'Vetrina',
                    'e-commerce' => 'E-commerce',
                    'altro' => 'Altro',
                ])
                ->live(),
            TextInput::make('url_transparency')
                ->label('URL trasparenza')
                ->visible(fn ($get) => $get('type') === 'istituzionale')
                ->maxLength(255),
            DatePicker::make('transparency_date')
                ->label('Data trasparenza')
                ->visible(fn ($get) => $get('type') === 'istituzionale')
                ->native(false)
                ->displayFormat('d/m/Y'),
            Toggle::make('is_active')
                ->label('Attivo')
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('domain')
            ->columns([
                TextColumn::make('domain')->label('Dominio')->searchable(),
                TextColumn::make('name')->label('Nome')->searchable(),
                TextColumn::make('type')->label('Tipologia'),
                IconColumn::make('is_active')->label('Attivo')->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['company_id'] = $this->getOwnerRecord()->company_id;

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
