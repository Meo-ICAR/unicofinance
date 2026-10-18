<?php

namespace App\Filament\RelationManagers;

use App\Models\Pratica;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Unico\Core\Models\ActivityLog;

/** Cosa ha mandato unicoagent sulla pratica: la richiesta, gli aggiornamenti e le note per l'istruttore. Solo lettura. */
class AttivitaAgenteRelationManager extends RelationManager
{
    protected static string $relationship = 'attivitaAgente';

    protected static ?string $title = 'Da unicoagent';

    protected static ?string $modelLabel = 'Voce';

    protected static ?string $pluralModelLabel = 'Voci';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Pratica && $ownerRecord->attivitaAgente()->exists();
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Quando')->dateTime('d/m/Y H:i'),
                TextColumn::make('event')->label('Tipo')->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'richiesta' => 'Richiesta',
                        'aggiornamento' => 'Aggiornamento',
                        'nota' => 'Nota',
                        default => (string) $state,
                    })
                    ->color(fn (?string $state): string => $state === 'nota' ? 'info' : 'gray'),
                TextColumn::make('properties.autore')->label('Autore')->placeholder('—'),
                TextColumn::make('description')->label('Testo')->wrap()
                    ->description(fn (ActivityLog $record): ?string => $this->dettaglio($record)),
            ]);
    }

    /** Sotto il testo: i campi aggiornati, oppure fascia d'importo, durata e risposte della richiesta. */
    private function dettaglio(ActivityLog $record): ?string
    {
        $properties = (array) $record->properties;

        if ($record->event === 'aggiornamento') {
            return 'Campi: '.implode(', ', (array) ($properties['campi'] ?? []));
        }

        if ($record->event !== 'richiesta') {
            return null;
        }

        $parti = array_filter([
            isset($properties['importo_fascia']) ? "Importo: {$properties['importo_fascia']}" : null,
            isset($properties['durata_mesi']) ? "Durata: {$properties['durata_mesi']} mesi" : null,
            isset($properties['agente']['whatsapp']) ? "WhatsApp agente: {$properties['agente']['whatsapp']}" : null,
            array_key_exists('contatto_diretto', $properties) ? 'Contatto diretto: '.($properties['contatto_diretto'] ? 'sì' : 'no') : null,
            collect((array) ($properties['risposte'] ?? []))->map(fn ($v, $k): string => "{$k}: ".(is_scalar($v) ? $v : json_encode($v)))->implode(' · ') ?: null,
        ]);

        return $parti === [] ? null : implode(' — ', $parti);
    }
}
