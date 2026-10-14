<?php

namespace App\Filament\Resources\Produttori\RelationManagers;

use App\Models\BlacklistClienteFornitore;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Unico\Core\Models\Cliente;

/** Istituti che non vogliono lavorare con il produttore (blacklist_clienti_fornitori). */
class IstitutiBloccatiRelationManager extends RelationManager
{
    protected static string $relationship = 'blacklistIstituti';

    protected static ?string $title = 'Istituti che non lavorano con lui';

    protected static ?string $modelLabel = 'Divieto';

    protected static ?string $pluralModelLabel = 'Divieti';

    /** Il resource Produttori è in sola lettura, ma i divieti si gestiscono da qui. */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('cliente_id')
                    ->label('Istituto')
                    ->options(fn () => Cliente::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->unique(
                        BlacklistClienteFornitore::class,
                        'cliente_id',
                        ignoreRecord: true,
                        modifyRuleUsing: fn ($rule) => $rule->where('fornitore_id', (string) $this->getOwnerRecord()->getKey()),
                    ),
                Textarea::make('motivo')->label('Motivo'),
                DatePicker::make('data_inizio')->label('Dal'),
                DatePicker::make('data_fine')->label('Al')->afterOrEqual('data_inizio'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn ($query) => $query->with('cliente'))
            ->columns([
                TextColumn::make('cliente.name')->label('Istituto'),
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
