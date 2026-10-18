<?php

namespace App\Filament\Resources\ProvvigioniAttive;

use App\Filament\Resources\ProvvigioniAttive\Pages\CreateProvvigioneAttiva;
use App\Filament\Resources\ProvvigioniAttive\Pages\EditProvvigioneAttiva;
use App\Filament\Resources\ProvvigioniAttive\Pages\ListProvvigioniAttive;
use App\Filament\Resources\ProvvigioniAttive\Schemas\ProvvigioneAttivaForm;
use App\Filament\Resources\ProvvigioniAttive\Tables\ProvvigioniAttiveTable;
use App\Filament\Traits\HasPlanAccess;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Unico\Core\Models\ProvvigioniRule;

/** Provvigioni attive: quanto riconosce la banca per tipo prodotto (le regole senza agente né livello di `provvigioni_rules`). */
class ProvvigioniAttiveResource extends Resource
{
    use HasPlanAccess;

    protected static ?string $model = ProvvigioniRule::class;

    protected static ?string $slug = 'provvigioni-attive';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|\UnitEnum|null $navigationGroup = 'Configurazione';

    protected static ?string $navigationLabel = 'Provvigioni attive';

    protected static ?string $modelLabel = 'Provvigione attiva';

    protected static ?string $pluralModelLabel = 'Provvigioni attive';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isStaff() && static::hasPlanFeatureAccess();
    }

    /** Le regole per agente o livello della rete sono un'altra cosa: qui solo quelle della banca. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNull('fornitori_id')->whereNull('kind_id');
    }

    public static function form(Schema $schema): Schema
    {
        return ProvvigioneAttivaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProvvigioniAttiveTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProvvigioniAttive::route('/'),
            'create' => CreateProvvigioneAttiva::route('/create'),
            'edit' => EditProvvigioneAttiva::route('/{record}/edit'),
        ];
    }
}
