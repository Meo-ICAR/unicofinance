<?php

namespace App\Filament\Resources\Solleciti;

use App\Filament\Actions\ExcelExportAction;
use App\Filament\Resources\Praticas\PraticaResource;
use App\Filament\Resources\Solleciti\Pages\ListSolleciti;
use App\Filament\Traits\HasPlanAccess;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Unico\Core\Documents\PracticeDocumentRequestMailer;
use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Models\Document;
use Unico\Core\Models\Employee;
use Unico\Core\Models\Pratica;

/**
 * Solleciti: i documenti richiesti e non ancora caricati delle pratiche prese in carico da ottobre 2026, non inviate in banca,
 * né respinte, né sospese. Con un'azione di gruppo si manda il sollecito a chi li ha in carico.
 */
class SollecitoResource extends Resource
{
    use HasPlanAccess;

    /** Le pratiche prese in carico da questa data in poi entrano nei solleciti. */
    public const DAL = '2026-10-01';

    protected static ?string $model = Document::class;

    protected static ?string $slug = 'solleciti';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?string $navigationLabel = 'Solleciti';

    protected static ?string $modelLabel = 'Sollecito';

    protected static ?string $pluralModelLabel = 'Solleciti';

    protected static ?int $navigationSort = 5;

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

    /** Pratiche i cui documenti si sollecitano: in carico dal 1° ottobre 2026, non inviate, non respinte, non sospese. */
    public static function pratiche(): Builder
    {
        return Pratica::query()
            ->whereDate('presa_in_carico_at', '>=', self::DAL)
            ->whereNull('sended_at')
            ->whereNull('rejected_at')
            ->where(fn (Builder $pratica) => $pratica
                ->whereDoesntHave('praticaStato')
                ->orWhereHas('praticaStato', fn (Builder $stato) => $stato->where('is_suspended', false)));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('documentable_type', 'pratica')
            ->where('status', DocumentStatus::PENDING)
            ->whereIn('documentable_id', static::pratiche()->select('id'))
            ->with(['documentType', 'responsible', 'documentable.employee']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([ExcelExportAction::make()])
            ->defaultSort('id')
            ->columns([
                TextColumn::make('documentable.employee.name')->label('Istruttore')->placeholder('—'),
                TextColumn::make('responsabile')
                    ->label('In carico a')
                    ->state(fn (Document $record): string => (string) ($record->responsible?->name ?? '—'))
                    ->description(fn (Document $record): ?string => $record->responsible_type)
                    ->searchable(query: fn (Builder $query, string $search): Builder => static::inCaricoA($query, $search)),
                TextColumn::make('pratica')
                    ->label('Pratica')
                    ->state(fn (Document $record): string => static::denominazione($record->documentable))
                    ->url(fn (Document $record): ?string => $record->documentable === null ? null : PraticaResource::getUrl('edit', ['record' => $record->documentable]))
                    ->color('primary'),
                TextColumn::make('name')->label('Documento')->searchable(),
            ])
            ->filters([
                SelectFilter::make('istruttore')
                    ->label('Istruttore')
                    ->options(fn (): array => Employee::query()->where('employee_types', 'istruttore')->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereIn('documentable_id', Pratica::query()->where('employee_id', $data['value'])->select('id'))
                        : $query),
                SelectFilter::make('tipologia')
                    ->label('Tipologia in carico a')
                    ->options(['employee' => 'Dipendente', 'client' => 'Cliente', 'fornitore' => 'Produttore', 'cliente' => 'Istituto'])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null) ? $query->where('responsible_type', $data['value']) : $query),
                Filter::make('in_carico_a')
                    ->label('In carico a')
                    ->schema([TextInput::make('nome')->label('In carico a')])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['nome'] ?? null) ? static::inCaricoA($query, $data['nome']) : $query),
            ])
            ->recordActions([])
            ->toolbarActions([
                BulkAction::make('sollecita')
                    ->label('Invia sollecito')
                    ->icon('heroicon-o-envelope')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Invia sollecito')
                    ->modalDescription("Per ogni pratica e destinatario parte un'unica email con l'elenco dei documenti ancora da caricare; l'istruttore è in copia.")
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records) => static::invia($records)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSolleciti::route('/'),
        ];
    }

    private static function denominazione(?Model $pratica): string
    {
        if (! $pratica instanceof Pratica) {
            return '—';
        }

        return collect([trim($pratica->cognome_cliente.' '.$pratica->nome_cliente), $pratica->denominazione_prodotto])->filter()->implode(' — ') ?: (string) $pratica->codice_pratica;
    }

    private static function inCaricoA(Builder $query, string $nome): Builder
    {
        return $query->whereHasMorph('responsible', ['employee', 'client', 'fornitore', 'cliente'], fn (Builder $responsabile) => $responsabile->where('name', 'like', "%{$nome}%"));
    }

    /**
     * Un'email per pratica e destinatario, con i documenti ancora da caricare; l'istruttore in copia.
     *
     * @param  Collection<int, Document>  $documenti
     */
    private static function invia(Collection $documenti): void
    {
        $mailer = app(PracticeDocumentRequestMailer::class);
        $inviati = 0;
        $senzaEmail = 0;

        $documenti->load(['documentType', 'responsible', 'documentable.employee'])
            ->groupBy(fn (Document $documento): string => $documento->documentable_id.'|'.$documento->responsible_type.'|'.$documento->responsible_id)
            ->each(function (Collection $gruppo) use ($mailer, &$inviati, &$senzaEmail): void {
                $primo = $gruppo->first();
                $pratica = $primo->documentable;
                $destinatario = $primo->responsible;
                $email = trim((string) ($destinatario?->email));
                $tipi = $gruppo->pluck('documentType')->filter()->unique('id')->values();

                if (! $pratica instanceof Pratica || $email === '' || $tipi->isEmpty()) {
                    $senzaEmail++;

                    return;
                }

                $mailer->send($pratica, $tipi, $email, (string) $destinatario->name, $pratica->employee?->email, $pratica->employee?->name, auth()->user(), 'warning');

                Document::query()->whereKey($gruppo->modelKeys())->update(['last_sent_at' => now(), 'reminders_count' => \DB::raw('coalesce(reminders_count, 0) + 1')]);
                $inviati++;
            });

        Notification::make()
            ->title($inviati.' solleciti inviati')
            ->body($senzaEmail > 0 ? $senzaEmail.' gruppi saltati: destinatario senza email o documento senza tipo.' : null)
            ->color($inviati > 0 ? 'success' : 'warning')
            ->send();
    }
}
