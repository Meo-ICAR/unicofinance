<?php

namespace App\Filament\Agenti\Resources\Pratiche\Schemas;

use App\Models\Client;
use App\Models\Pratica;
use App\Services\AmlScreeningService;
use App\Services\CervedService;
use App\Services\OpenApiCompanyService;
use App\Services\PartitaIva;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/** Interrogazioni Cerved, sanctions.io e Openapi, come sulla scheda cliente di unicoloan. */
class ClienteApiActions
{
    public static function cerved(): Action
    {
        return Action::make('cerved')
            ->label('Cerved')
            ->tooltip('Interroga Cerved')
            ->icon('heroicon-m-magnifying-glass')
            ->mountUsing(function (Action $action, Get $get, ?Pratica $record): void {
                $client = self::savedClient($action, $record);
                if ($client === null) {
                    return;
                }

                self::unchanged($action, $get('codice_fiscale'), $record?->codice_fiscale);
            })
            ->requiresConfirmation()
            ->modalHeading('Interrogare Cerved?')
            ->modalDescription('La consultazione di Cerved è a pagamento. Confermi il lancio?')
            ->modalSubmitActionLabel('Interroga')
            ->action(function (Get $get, ?Pratica $record): void {
                $taxCode = (string) $get('codice_fiscale');

                if (blank($taxCode)) {
                    Notification::make()->title('Inserire prima codice fiscale / P.IVA')->warning()->send();

                    return;
                }

                try {
                    $score = app(CervedService::class)->fetchScore($taxCode, $record?->richiedente ?? $record);
                } catch (\Throwable $e) {
                    Notification::make()->title('Cerved')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title('Cerved: '.($score['denominazione'] ?? $taxCode))
                    ->body(collect([
                        $score['descrizione_score'],
                        'Valore: '.$score['valore'],
                        'Categoria: '.$score['categoria_codice'].' - '.$score['categoria_descrizione'],
                    ])->filter()->implode(' · '))
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    public static function verificaPep(): Action
    {
        return Action::make('verificaPep')
            ->label('Verifica PEP')
            ->icon('heroicon-m-shield-exclamation')
            ->mountUsing(function (Action $action, Get $get, ?Pratica $record): void {
                $client = self::savedClient($action, $record);
                if ($client === null) {
                    return;
                }

                foreach (['name', 'first_name', 'birth_date', 'is_person'] as $field) {
                    if (self::normalize($get($field)) !== self::normalize($client->getAttribute($field))) {
                        self::pendingChanges($action);

                        return;
                    }
                }
            })
            ->requiresConfirmation()
            ->modalHeading('Verificare le liste PEP?')
            ->modalDescription('Il nominativo del cliente viene inviato al servizio sanctions.io. Confermi?')
            ->modalSubmitActionLabel('Verifica')
            ->action(function (Get $get, ?Pratica $record): void {
                $isPerson = (bool) $get('is_person');
                $name = trim(($isPerson ? ($get('first_name').' ') : '').$get('name'));

                if (blank($name)) {
                    Notification::make()->title('Inserire prima il nominativo del cliente')->warning()->send();

                    return;
                }

                try {
                    $matches = app(AmlScreeningService::class)->searchPep(
                        $name,
                        $isPerson,
                        $isPerson ? $get('birth_date') : null,
                        $record?->richiedente,
                    );
                } catch (\Throwable $e) {
                    Notification::make()->title('Verifica PEP')->body($e->getMessage())->danger()->send();

                    return;
                }

                if ($matches === []) {
                    Notification::make()->title('Verifica PEP: nessuna corrispondenza')->body($name)->success()->send();

                    return;
                }

                Notification::make()
                    ->title('Verifica PEP: '.count($matches).' possibili corrispondenze')
                    ->body(collect($matches)->take(5)->map(fn (array $match): string => $match['name'].' ('.number_format($match['score'] * 100).'%)'.($match['countries'] !== [] ? ' - '.implode(', ', $match['countries']) : ''))->implode(' · ').'. Verificare a mano prima di spuntare PEP.')
                    ->warning()
                    ->persistent()
                    ->send();
            });
    }

    /**
     * Lente Openapi per le società, esposta sul campo IBAN come in unicoloan.
     * La chiamata usa la partita IVA del cliente, non l'IBAN.
     */
    public static function openapi(): Action
    {
        return Action::make('openapi')
            ->label('Openapi')
            ->tooltip('Dati societari e soci da Openapi')
            ->icon('heroicon-m-building-office-2')
            ->visible(function (Get $get, ?Pratica $record): bool {
                if ($record?->richiedente === null) {
                    return false;
                }

                $isPerson = $get('richiedente.is_person');

                return ! ($isPerson ?? $record->richiedente->is_person);
            })
            ->mountUsing(function (Action $action, Get $get, ?Pratica $record): void {
                $client = self::savedClient($action, $record);
                if ($client === null) {
                    return;
                }

                $vat = (string) ($get('richiedente.tax_code') ?: $get('codice_fiscale'));
                self::unchanged($action, $vat, $client->tax_code);
            })
            ->requiresConfirmation()
            ->modalHeading('Interrogare Openapi?')
            ->modalDescription('La consultazione di Openapi è a pagamento (dati societari e, se non inclusi, elenco soci). Confermi il lancio?')
            ->modalSubmitActionLabel('Interroga')
            ->action(function (Get $get, Set $set, ?Pratica $record): void {
                $vat = (string) ($get('richiedente.tax_code') ?: $get('codice_fiscale'));

                if (! app(PartitaIva::class)->isValid($vat)) {
                    Notification::make()->title('Inserire una partita IVA valida')->warning()->send();

                    return;
                }

                try {
                    $result = app(OpenApiCompanyService::class)->fetchCompany($vat, $record?->richiedente);
                } catch (\Throwable $e) {
                    Notification::make()->title('Openapi')->body($e->getMessage())->danger()->send();

                    return;
                }

                $company = $result['company'];

                if (filled($company['name'])) {
                    $record?->richiedente?->update(['name' => $company['name']]);
                    $set('richiedente.name', $company['name']);
                    $set('cognome_cliente', $company['name']);
                }

                $body = collect($result['shareholders'])
                    ->map(fn (array $holder): string => trim(($holder['first_name'] ?? '').' '.($holder['name'] ?? '')).' ('.($holder['percent'] ?? '?').'%)')
                    ->implode(' · ');

                Notification::make()
                    ->title('Openapi: '.($company['name'] ?? $vat))
                    ->body($result['shareholders'] === [] ? 'Dati societari compilati. Nessun socio con quota >= 10%.' : "Soci: {$body}.")
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    private static function savedClient(Action $action, ?Pratica $record): ?Client
    {
        $client = $record?->richiedente;

        if ($client === null) {
            Notification::make()
                ->title('Salva prima il cliente')
                ->body('Le interrogazioni ai servizi esterni si lanciano su un cliente già salvato.')
                ->warning()
                ->send();
            $action->cancel();
        }

        return $client;
    }

    private static function unchanged(Action $action, mixed $current, mixed $saved): void
    {
        if (self::normalize($current) !== self::normalize($saved)) {
            self::pendingChanges($action);
        }
    }

    private static function pendingChanges(Action $action): void
    {
        Notification::make()
            ->title('Salva le modifiche prima di interrogare')
            ->body('I dati del cliente sono stati modificati e non ancora salvati.')
            ->warning()
            ->send();
        $action->cancel();
    }

    private static function normalize(mixed $value): string
    {
        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : trim((string) ($value ?? ''));
    }
}
