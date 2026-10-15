<?php

namespace App\Filament\Resources\Istituti\RelationManagers;

use App\Models\BlacklistClienteFornitore;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Unico\Core\Models\Fornitore;

/** Produttori con cui l'istituto non vuole lavorare (blacklist_clienti_fornitori). */
class ProduttoriBloccatiRelationManager extends RelationManager
{
    protected static string $relationship = 'blacklistProduttori';

    protected static ?string $title = 'Blacklist';

    protected static ?string $modelLabel = 'Divieto';

    protected static ?string $pluralModelLabel = 'Divieti';

    /** Il resource Istituti è in sola lettura, ma i divieti si gestiscono da qui. */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)->schema([
                    Select::make('fornitore_id')
                        ->label('Produttore')
                        ->options(fn () => Fornitore::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        ->unique(
                            BlacklistClienteFornitore::class,
                            'fornitore_id',
                            ignoreRecord: true,
                            modifyRuleUsing: fn ($rule) => $rule->where('cliente_id', (string) $this->getOwnerRecord()->getKey()),
                        ),
                    DatePicker::make('data_inizio')->label('Dal')->default(now()),
                ]),
                Textarea::make('motivo')->label('Motivo')->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn ($query) => $query->with('fornitore'))
            ->columns([
                TextColumn::make('fornitore.name')->label('Produttore'),
                TextColumn::make('motivo')->label('Motivo')->limit(60),
                TextColumn::make('data_inizio')->label('Dal')->date('d/m/Y'),
                TextColumn::make('data_fine')->label('Al')->date('d/m/Y')->placeholder('A tempo indeterminato'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
