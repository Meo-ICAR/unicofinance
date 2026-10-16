<?php

namespace App\Filament\Resources\Istituti\RelationManagers;

use App\Filament\Resources\ProvvigioniAttive\Schemas\ProvvigioneAttivaForm;
use App\Filament\Resources\ProvvigioniAttive\Tables\ProvvigioniAttiveTable;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Listini delle provvigioni attive dell'istituto: quanto riconosce, per tipo prodotto. */
class ProvvigioniAttiveRelationManager extends RelationManager
{
    protected static string $relationship = 'provvigioniRules';

    protected static ?string $title = 'Provvigioni attive';

    protected static ?string $modelLabel = 'Provvigione attiva';

    protected static ?string $pluralModelLabel = 'Provvigioni attive';

    /** Il resource Istituti è in sola lettura, ma i listini si gestiscono da qui. */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return ProvvigioneAttivaForm::configure($schema, conBanca: false);
    }

    public function table(Table $table): Table
    {
        return ProvvigioniAttiveTable::configure($table, conBanca: false)
            ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('fornitori_id')->whereNull('kind_id')->with(['tipoprodotto', 'clienti']))
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
