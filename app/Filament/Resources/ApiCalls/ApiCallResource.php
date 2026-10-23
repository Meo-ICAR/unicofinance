<?php

namespace App\Filament\Resources\ApiCalls;

use App\Filament\Actions\ExcelExportAction;
use App\Filament\Resources\ApiCalls\Pages\ListApiCalls;
use App\Filament\Resources\ApiCalls\Pages\ViewApiCall;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Praticas\PraticaResource;
use App\Filament\Traits\HasPlanAccess;
use App\Models\ApiCall;
use App\Models\User;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Unico\Core\Models\Document;
use UnitEnum;

/** Registro delle chiamate ai web service esterni (AI, Cerved, Openapi, sanzioni), in sola lettura. */
class ApiCallResource extends Resource
{
    use HasPlanAccess;

    protected static ?string $model = ApiCall::class;

    protected static ?string $slug = 'chiamate-api';

    protected static string|UnitEnum|null $navigationGroup = 'Configurazione';

    protected static ?string $navigationLabel = 'Registro chiamate API';

    protected static ?string $modelLabel = 'Chiamata API';

    protected static ?string $pluralModelLabel = 'Chiamate API';

    protected static ?int $navigationSort = 6;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isStaff() && static::hasPlanFeatureAccess();
    }

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
        return parent::getEloquentQuery()->with(['subject' => function (MorphTo $morph): void {
            $morph->morphWith([
                Document::class => ['documentable'],
            ]);
        }]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('created_at')->label('Data')->dateTime('d/m/Y H:i:s'),
            TextEntry::make('provider')->label('Servizio')->badge(),
            TextEntry::make('operation')->label('Operazione'),
            TextEntry::make('status')->label('Esito')->badge(),
            TextEntry::make('cost')->label('Costo')->money('EUR'),
            TextEntry::make('pratica_riferimento')
                ->label('Pratica')
                ->state(fn (ApiCall $record): ?string => $record->etichettaPratica())
                ->url(fn (ApiCall $record): ?string => ($pratica = $record->praticaDiRiferimento()) ? PraticaResource::getUrl('edit', ['record' => $pratica]) : null)
                ->placeholder('—'),
            TextEntry::make('cliente_riferimento')
                ->label('Cliente')
                ->state(fn (ApiCall $record): ?string => $record->etichettaCliente())
                ->url(fn (ApiCall $record): ?string => ($cliente = $record->clienteDiRiferimento()) ? ClientResource::getUrl('edit', ['record' => $cliente]) : null)
                ->placeholder('—'),
            TextEntry::make('http_status')->label('HTTP')->placeholder('—'),
            TextEntry::make('duration_ms')->label('Durata (ms)')->placeholder('—'),
            TextEntry::make('summary')->label('Sintesi')->placeholder('—')->columnSpanFull(),
            TextEntry::make('error')->label('Errore')->placeholder('—')->columnSpanFull(),
            TextEntry::make('request')->label('Richiesta')->formatStateUsing(fn ($state): string => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))->placeholder('—')->columnSpanFull(),
            TextEntry::make('response')->label('Risposta')->formatStateUsing(fn ($state): string => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))->placeholder('—')->columnSpanFull(),
        ])->columns(4);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([ExcelExportAction::make()->sumColumns(['Costo'])])
            ->columns([
                TextColumn::make('created_at')->label('Data')->dateTime('d/m/y H:i')->sortable(),
                TextColumn::make('provider')->label('Servizio')->badge()->sortable(),
                TextColumn::make('operation')->label('Operazione')->searchable()->sortable(),
                TextColumn::make('status')->label('Esito')->badge()->sortable(),
                TextColumn::make('summary')->label('Sintesi')->limit(60)->sortable(),
                TextColumn::make('cost')->label('Costo')->money('EUR')->sortable(),
                TextColumn::make('pratica_riferimento')
                    ->label('Pratica')
                    ->state(fn (ApiCall $record): ?string => $record->etichettaPratica())
                    ->url(fn (ApiCall $record): ?string => ($pratica = $record->praticaDiRiferimento()) ? PraticaResource::getUrl('edit', ['record' => $pratica]) : null)
                    ->placeholder('—')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByPratica($direction)),
                TextColumn::make('cliente_riferimento')
                    ->label('Cliente')
                    ->state(fn (ApiCall $record): ?string => $record->etichettaCliente())
                    ->url(fn (ApiCall $record): ?string => ($cliente = $record->clienteDiRiferimento()) ? ClientResource::getUrl('edit', ['record' => $cliente]) : null)
                    ->placeholder('—')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByCliente($direction)),
            ])
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (ApiCall $record): string => static::getUrl('view', ['record' => $record]));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApiCalls::route('/'),
            'view' => ViewApiCall::route('/{record}'),
        ];
    }
}
