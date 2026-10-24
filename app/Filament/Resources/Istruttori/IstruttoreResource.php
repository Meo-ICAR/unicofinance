<?php

namespace App\Filament\Resources\Istruttori;

use App\Filament\Actions\CreaUtenteIstruttoreAction;
use App\Filament\Actions\ExcelExportAction;
use App\Filament\Actions\ImpersonateIstruttoreAction;
use App\Filament\Resources\Istruttori\Pages\ListIstruttori;
use App\Filament\Traits\HasPlanAccess;
use App\Models\User;
use App\Support\Istruttori;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Unico\Core\Models\Cliente;
use Unico\Core\Models\Employee;
use Unico\Core\Models\TipoProdotto;

/** Dipendenti dell'istruttoria (e degli altri uffici) con l'accesso al pannello; da qui un amministratore può impersonificarli. */
class IstruttoreResource extends Resource
{
    use HasPlanAccess;

    protected static ?string $model = Employee::class;

    protected static ?string $slug = 'istruttori';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Istruttori';

    protected static ?string $modelLabel = 'Istruttore';

    protected static ?string $pluralModelLabel = 'Istruttori';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        $utente = auth()->user();

        return $utente instanceof User && in_array($utente->role, ['admin', 'super_admin'], true) && static::hasPlanFeatureAccess();
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

    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([ExcelExportAction::make()])
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nome')->searchable()->sortable(),
                TextColumn::make('role_title')->label('Ruolo')->wrap()->placeholder('—'),
                TextColumn::make('employee_types')->label('Tipo')->badge()->placeholder('—'),
                TextColumn::make('email')->label('Email')->placeholder('—'),
                TextColumn::make('tipoprodotto_ids')
                    ->label('Prodotti seguiti')
                    ->state(fn (Employee $record): array => TipoProdotto::query()->whereIn('id', $record->tipoprodotto_ids ?? [])->orderBy('name')->pluck('name')->all())
                    ->badge()
                    ->placeholder('Tutti'),
                TextColumn::make('clienti_ids')
                    ->label('Banche seguite')
                    ->state(fn (Employee $record): array => Cliente::query()->whereIn('id', $record->clienti_ids ?? [])->orderBy('name')->pluck('name')->all())
                    ->badge()
                    ->placeholder('Tutte'),
                IconColumn::make('utente')
                    ->label('Accesso')
                    ->state(fn (Employee $record): bool => $record->user_id !== null)
                    ->boolean()
                    ->tooltip('Ha un utente collegato per entrare nel pannello'),
            ])
            ->filters([
                Filter::make('istruttori')
                    ->label('Solo istruttori')
                    ->toggle()
                    ->default()
                    ->query(fn (Builder $query): Builder => Istruttori::scope($query)),
            ])
            ->recordActions([
                self::specializzazione(),
                ImpersonateIstruttoreAction::make()->iconButton()->tooltip('Impersonifica'),
                CreaUtenteIstruttoreAction::make()->iconButton()->tooltip('Crea utente'),
            ]);
    }

    /** Prodotti e banche che l'istruttore segue: servono ad assegnargli le pratiche quando l'agente le perfeziona. */
    private static function specializzazione(): Action
    {
        return Action::make('specializzazione')
            ->label('Specializzazione')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->iconButton()
            ->tooltip('Specializzazione')
            ->visible(fn (Employee $record): bool => Istruttori::e($record))
            ->modalHeading(fn (Employee $record): string => 'Specializzazione di '.$record->name)
            ->modalDescription('Senza scelte l\'istruttore può prendere qualsiasi pratica. Chi segue la banca della pratica ha la precedenza su chi segue il prodotto.')
            ->fillForm(fn (Employee $record): array => [
                'tipoprodotto_ids' => $record->tipoprodotto_ids ?? [],
                'clienti_ids' => $record->clienti_ids ?? [],
            ])
            ->schema([
                Select::make('tipoprodotto_ids')
                    ->label('Prodotti seguiti')
                    ->multiple()
                    ->options(fn (): array => TipoProdotto::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload(),
                Select::make('clienti_ids')
                    ->label('Banche seguite')
                    ->multiple()
                    ->options(fn (): array => Cliente::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload(),
            ])
            ->action(function (Employee $record, array $data): void {
                $record->update([
                    'tipoprodotto_ids' => array_map('intval', $data['tipoprodotto_ids'] ?? []) ?: null,
                    'clienti_ids' => array_map('intval', $data['clienti_ids'] ?? []) ?: null,
                ]);
                Notification::make()->title('Specializzazione salvata')->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIstruttori::route('/'),
        ];
    }
}
