<?php

namespace App\Filament\Concerns;

use App\Filament\Resources\Clients\ClientResource;
use App\Models\Client;
use App\Support\ControlloRichiedente;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Soggetto (cointestatario, garante, socio, ...) indicato da cognome, nome e codice fiscale: il cliente si cerca dal
 * codice fiscale e, se non c'è, si crea. Accanto al codice fiscale un link apre la scheda del cliente.
 */
trait SoggettoCollegato
{
    /** @return array<int, TextInput> */
    protected static function campiSoggetto(): array
    {
        return [
            TextInput::make('cognome')->label('Cognome / Ragione sociale')->required()->maxLength(255),
            TextInput::make('nome')->label('Nome')->maxLength(255),
            TextInput::make('codice_fiscale')
                ->label('Codice fiscale / P.IVA')
                ->required()
                ->maxLength(16)
                ->live(onBlur: true)
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    $codice = self::normalizza((string) $value);
                    $error = ControlloRichiedente::taxCodeError($codice, strlen($codice) !== 11);

                    if ($error !== null) {
                        $fail($error);
                    }
                })
                ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                    $client = self::cliente($state);

                    // Cliente già in anagrafica: i dati si precompilano, altrimenti verrà creato al salvataggio.
                    if ($client !== null) {
                        $set('cognome', $client->name);
                        $set('nome', $client->first_name);
                    }
                })
                ->suffixAction(
                    Action::make('schedaCliente')
                        ->label('Scheda cliente')
                        ->tooltip('Apri la scheda del cliente')
                        ->icon(Heroicon::OutlinedUserCircle)
                        ->url(fn (Get $get): ?string => ($c = self::cliente($get('codice_fiscale'))) ? ClientResource::getUrl('edit', ['record' => $c]) : null, shouldOpenInNewTab: true)
                        ->visible(fn (Get $get): bool => self::cliente($get('codice_fiscale')) !== null && Filament::getCurrentPanel()?->getId() === 'admin'),
                ),
        ];
    }

    protected static function normalizza(string $codice): string
    {
        return strtoupper(preg_replace('/\s+/', '', $codice));
    }

    protected static function cliente(?string $codice): ?Client
    {
        $codice = self::normalizza((string) $codice);

        return $codice === '' ? null : Client::query()->where('tax_code', $codice)->first();
    }

    /** @param  array<string, mixed>  $data */
    protected static function trovaOCrea(array $data, Model $owner): Client
    {
        $codice = self::normalizza((string) $data['codice_fiscale']);

        return Client::query()->firstOrCreate(['tax_code' => $codice], [
            'name' => $data['cognome'],
            'first_name' => $data['nome'] ?? null,
            'is_person' => strlen($codice) !== 11,
            'company_id' => $owner->company_id,
        ]);
    }
}
