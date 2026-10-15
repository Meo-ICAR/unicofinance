<?php

namespace App\Filament\Agenti\Resources\Clients\Schemas;

use App\Filament\Agenti\Resources\Pratiche\Schemas\ClienteApiActions;
use App\Filament\Resources\Clients\Schemas\KycQuestionnaireForm;
use App\Services\CodiceFiscaleDecoder;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Anagrafica, valutazione AML e KYC del richiedente: la stessa scheda nel form della pratica (relazione `richiedente`)
 * e nella scheda cliente. I percorsi relativi (`../is_person`) valgono in entrambi perché i campi stanno allo stesso livello.
 */
class ClientForm
{
    /**
     * @param  Closure(?Get): bool  $isPerson
     * @param  bool  $withScreening  azione di verifica PEP (usa la pratica aperta: solo nel form della pratica)
     * @return array<int, mixed>
     */
    public static function components(Closure $isPerson, bool $withScreening = true): array
    {
        return [
            TextInput::make('name')->label('Cognome / ragione sociale')->required()->maxLength(255),
            TextInput::make('first_name')->label('Nome')->maxLength(255),
            TextInput::make('tax_code')
                ->label('Codice fiscale')
                ->maxLength(16)
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                    if (! $get('is_person') || strlen(trim((string) $state)) !== 16) {
                        return;
                    }

                    $decoded = app(CodiceFiscaleDecoder::class)->decodeVerified($state);

                    if ($decoded === null) {
                        Notification::make()->title('Codice fiscale non valido')->body('Il carattere di controllo non è corretto: dati anagrafici non compilati.')->warning()->send();

                        return;
                    }

                    $set('birth_date', $decoded['birth_date']->format('Y-m-d'));
                    $set('sex', $decoded['sex']);
                    $set('birth_place', $decoded['birth_place']);
                }),
            Toggle::make('is_person')->label('Persona fisica')->live(),
            DatePicker::make('birth_date')->label('Data di nascita')->native(false)->displayFormat('d/m/Y'),
            TextInput::make('birth_place')->label('Luogo di nascita')->maxLength(100),
            Select::make('sex')->label('Sesso')->options(['M' => 'M', 'F' => 'F']),
            TextInput::make('citizenship')->label('Cittadinanza')->maxLength(60),
            TextInput::make('salary')->label('Reddito')->numeric()->prefix('€'),
            Section::make('Valutazione rischio (AML)')
                ->key('valutazioneAml')
                ->columns(3)
                ->headerActions([
                    ClienteApiActions::verificaPep(),
                ])
                ->schema([
                    Toggle::make('is_pep')->label('PEP (esposto politicamente)'),
                    Toggle::make('is_sanctioned')->label('Sanzionato / blacklist'),
                    Toggle::make('is_art108')->label('Esente art. 108 TUB'),
                    Toggle::make('is_remote_interaction')->label('Interazione a distanza'),
                    Toggle::make('is_approved')->label('Approvato'),
                    Select::make('status')
                        ->label('Stato verifica cliente')
                        ->options([
                            'raccolta_dati' => 'Raccolta dati',
                            'valutazione_aml' => 'Valutazione AML',
                            'approvata' => 'Approvata',
                            'sos_inviata' => 'SOS inviata',
                            'chiusa' => 'Chiusa',
                        ]),
                    DateTimePicker::make('blacklist_at')->label('Data blacklist')->readOnly(),
                ]),
            Section::make('KYC (adeguata verifica)')
                ->relationship('questionarioKyc')
                ->columns(2)
                ->schema([
                    KycQuestionnaireForm::financingPurpose($isPerson),
                    KycQuestionnaireForm::riskLevel(),
                    KycQuestionnaireForm::pepStatus($isPerson),
                    Textarea::make('notes')->label('Note')->columnSpanFull(),
                    KycQuestionnaireForm::personSection($isPerson),
                    Section::make('Persona giuridica')
                        ->columns(2)
                        ->visible(fn (?Get $get = null): bool => ! ($get instanceof Get && (bool) $get('../is_person')))
                        ->schema(KycQuestionnaireForm::companyFields()),
                ]),

        ];
    }
}
