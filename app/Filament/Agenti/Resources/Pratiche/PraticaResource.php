<?php

namespace App\Filament\Agenti\Resources\Pratiche;

use App\Filament\Agenti\Resources\Pratiche\Pages\ListPratiche;
use App\Filament\Agenti\Resources\Pratiche\Pages\ViewPratica;
use App\Services\Agenti\AgentImpersonationService;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Unico\Core\Models\Pratica;

class PraticaResource extends Resource
{
    protected static ?string $model = Pratica::class;

    protected static ?string $slug = 'pratiche';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Pratiche';

    protected static ?string $modelLabel = 'Pratica';

    protected static ?string $pluralModelLabel = 'Pratiche';

    protected static ?string $recordTitleAttribute = 'codice_pratica';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $fornitoreId = app(AgentImpersonationService::class)->agentFornitoreId();
        $query = parent::getEloquentQuery();

        if ($fornitoreId === null) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where('fornitori_id', $fornitoreId);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('codice_pratica')->label('Codice'),
            TextEntry::make('cognome_cliente')->label('Cognome'),
            TextEntry::make('nome_cliente')->label('Nome'),
            TextEntry::make('tipoprodotto.name')->label('Tipo prodotto'),
            TextEntry::make('praticaStato.codice')->label('Stato')->badge(),
            TextEntry::make('denominazione_banca')->label('Banca'),
            TextEntry::make('amount')->label('Importo')->money('EUR'),
            TextEntry::make('erogato')->label('Erogato')->money('EUR'),
            TextEntry::make('rata')->label('Rata')->money('EUR'),
            TextEntry::make('nrate')->label('Rate'),
            TextEntry::make('data_inserimento_pratica')->label('Inserita il')->date('d/m/Y'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('data_inserimento_pratica', 'desc')
            ->columns([
                TextColumn::make('codice_pratica')->label('Codice')->searchable()->sortable(),
                TextColumn::make('cognome_cliente')->label('Cognome')->searchable()->sortable(),
                TextColumn::make('nome_cliente')->label('Nome')->searchable(),
                TextColumn::make('tipoprodotto.name')->label('Tipo prodotto')->sortable(),
                TextColumn::make('praticaStato.codice')->label('Stato')->badge(),
                TextColumn::make('amount')->label('Importo')->money('EUR')->sortable(),
                TextColumn::make('data_inserimento_pratica')->label('Inserita il')->date('d/m/Y')->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPratiche::route('/'),
            'view' => ViewPratica::route('/{record}'),
        ];
    }
}
