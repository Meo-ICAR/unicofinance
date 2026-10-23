<?php

namespace App\Filament\Agenti\Resources\Scadenziario;

use App\Filament\Actions\ExcelExportAction;
use App\Filament\Agenti\Resources\Scadenziario\Pages\ListScadenziario;
use App\Filament\Agenti\Widgets\ProvvigioniAgente;
use App\Filament\Traits\HasPlanAccess;
use App\Models\Pratica;
use App\Services\Agenti\AgentImpersonationService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Models\Document;

/**
 * Scadenziario dell'agente: i documenti delle pratiche su cui matura provvigioni, sue o dei suoi collaboratori.
 * Di default solo quelli alla firma; si può passare a tutti e distinguere le pratiche dirette da quelle dei collaboratori.
 */
class ScadenziarioResource extends Resource
{
    use HasPlanAccess;

    protected static ?string $model = Document::class;

    protected static ?string $slug = 'scadenziario';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Scadenziario';

    protected static ?string $modelLabel = 'Documento';

    protected static ?string $pluralModelLabel = 'Scadenziario';

    protected static ?int $navigationSort = 3;

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

    /** Pratiche (id) legate alle provvigioni dell'agente, escluse declinate e sospese. */
    public static function idPratiche(): array
    {
        $codici = ProvvigioniAgente::base()->whereNotNull('id_pratica')->distinct()->pluck('id_pratica');

        return Pratica::query()->whereIn('codice_pratica', $codici)->pluck('id')->all();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('documentable_type', 'pratica')
            ->whereIn('documentable_id', array_map('strval', static::idPratiche()))
            ->with(['documentType', 'documentable.fornitori']);
    }

    public static function table(Table $table): Table
    {
        $agente = fn (): int => app(AgentImpersonationService::class)->agentFornitoreId() ?? 0;

        return $table
            ->headerActions([ExcelExportAction::make()])
            ->defaultSort('expires_at')
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('documentable.cognome_cliente')
                    ->label('Pratica')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereIn('documentable_id', Pratica::query()->where('cognome_cliente', 'like', "%{$search}%")->select('id')))
                    ->description(fn (Document $record): ?string => $record->documentable?->denominazione_banca),
                TextColumn::make('origine')
                    ->label('Pratica di')
                    ->state(fn (Document $record): string => (int) $record->documentable?->fornitori_id === $agente() ? 'Diretta' : (string) ($record->documentable?->fornitori?->name ?? 'Collaboratore'))
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Diretta' ? 'success' : 'gray'),
                TextColumn::make('name')->label('Documento')->searchable(),
                TextColumn::make('status')->label('Stato')->badge(),
                TextColumn::make('firma')
                    ->label('Firma')
                    ->state(fn (Document $record): string => match (true) {
                        $record->signed_at !== null => 'Firmato',
                        $record->latestSignatureRequest()?->isOpen() === true => 'In firma (OTP)',
                        (bool) $record->documentType?->is_signed => 'Da firmare',
                        default => '—',
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Firmato' => 'success',
                        'In firma (OTP)' => 'info',
                        'Da firmare' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('expires_at')->label('Scadenza')->date('d/m/Y')->sortable()->placeholder('—'),
                TextColumn::make('giorni')
                    ->label('Giorni')
                    ->state(fn (Document $record): ?int => $record->expires_at === null ? null : (int) now()->startOfDay()->diffInDays($record->expires_at->startOfDay(), false))
                    ->badge()
                    ->color(fn (?int $state): string => $state === null ? 'gray' : ($state < 0 ? 'danger' : ($state <= 7 ? 'warning' : 'gray')))
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : ($state < 0 ? 'scaduto da '.abs($state).' gg' : 'tra '.$state.' gg')),
            ])
            ->filters([
                // Predefinito: solo i documenti alla firma (da firmare o con richiesta di firma aperta).
                TernaryFilter::make('alla_firma')
                    ->label('Alla firma')
                    ->placeholder('Tutti')
                    ->trueLabel('Solo alla firma')
                    ->falseLabel('Non alla firma')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $query): Builder => static::allaFirma($query),
                        false: fn (Builder $query): Builder => $query->whereNotIn('id', static::allaFirma(Document::query())->select('id')),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                SelectFilter::make('origine')
                    ->label('Pratiche')
                    ->options(['dirette' => 'Dirette', 'collaboratori' => 'Dei miei collaboratori'])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'dirette' => $query->whereIn('documentable_id', Pratica::query()->where('fornitori_id', $agente())->select('id')),
                        'collaboratori' => $query->whereIn('documentable_id', Pratica::query()->where(fn (Builder $pratica) => $pratica->where('fornitori_id', '!=', $agente())->orWhereNull('fornitori_id'))->select('id')),
                        default => $query,
                    }),
                Filter::make('in_scadenza')
                    ->label('Scaduti o in scadenza (30 giorni)')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('expires_at')->where('expires_at', '<=', now()->addDays(30)->endOfDay())),
            ]);
    }

    /** Documenti da firmare e non ancora firmati, oppure con una richiesta di firma aperta. */
    public static function allaFirma(Builder $query): Builder
    {
        return $query
            ->whereNull('signed_at')
            ->whereNotIn('status', [DocumentStatus::NA->value, DocumentStatus::REJECTED->value, DocumentStatus::REVOKED->value])
            ->whereHas('documentType', fn (Builder $tipo) => $tipo->where('is_signed', true));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListScadenziario::route('/'),
        ];
    }
}
