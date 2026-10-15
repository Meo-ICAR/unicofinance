<?php

namespace App\Filament\Agenti\Resources\Clients;

use App\Filament\Agenti\Resources\Clients\Pages\EditClient;
use App\Filament\Agenti\Resources\Clients\Pages\ListClients;
use App\Filament\Agenti\Resources\Clients\Schemas\ClientForm;
use App\Models\Client;
use App\Models\Pratica;
use App\Services\Agenti\AgentImpersonationService;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Unico\Core\Models\PraticaClient;

/**
 * Scheda cliente: anagrafica, contatti, valutazione AML e KYC. L'agente vede solo i clienti delle proprie pratiche
 * e la scheda si apre (o si crea) dal codice fiscale del richiedente nella pratica.
 */
class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $slug = 'clienti';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Clienti';

    protected static ?string $modelLabel = 'Cliente';

    protected static ?string $pluralModelLabel = 'Clienti';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
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

        $pratiche = Pratica::query()->where('fornitori_id', $fornitoreId);

        return $query->where(function (Builder $query) use ($pratiche): void {
            $query->whereIn('tax_code', (clone $pratiche)->whereNotNull('codice_fiscale')->select('codice_fiscale'))
                ->orWhereIn('vat_number', (clone $pratiche)->whereNotNull('codice_fiscale')->select('codice_fiscale'))
                ->orWhereIn('id', PraticaClient::query()->whereIn('pratica_id', (clone $pratiche)->select('id'))->select('client_id'));
        });
    }

    public static function form(Schema $schema): Schema
    {
        $isPerson = fn (?Get $get = null): bool => $get instanceof Get && (bool) $get('../is_person');

        return $schema->components([
            Section::make('Contatti')
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('email')->label('Email')->email()->maxLength(255),
                    TextInput::make('phone')->label('Cellulare')->tel()->maxLength(50),
                    TextInput::make('iban')->label('IBAN')->maxLength(34)
                        ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? strtoupper(str_replace(' ', '', $state)) : null),
                    TextInput::make('pec')->label('PEC')->email()->maxLength(255),
                ]),
            Section::make('Anagrafica')
                ->columns(2)
                ->columnSpanFull()
                ->schema(ClientForm::components($isPerson, withScreening: false)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Cognome / ragione sociale')->searchable()->sortable(),
                TextColumn::make('first_name')->label('Nome')->searchable(),
                TextColumn::make('tax_code')->label('Codice fiscale')->searchable(),
                TextColumn::make('vat_number')->label('P. IVA')->searchable(),
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('phone')->label('Cellulare'),
            ])
            ->recordActions([
                EditAction::make()->label('Apri scheda'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClients::route('/'),
            'edit' => EditClient::route('/{record}/edit'),
        ];
    }
}
