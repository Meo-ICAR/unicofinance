# Respingimento documento non pertinente

Funzione non implementata come passo AI autonomo. Non esiste un prompt di respingimento nel codice: non usarne uno inventato.

Il riconoscimento «questo file è un altro documento» è il campo `kind_detected` del prompt di estrazione (`estrazione-anagrafiche-da-documento.md`). Il controllo procedurale `Unico\Core\Documents\Checks\KindCheck` (codice `tipo_documento`) confronta quel campo con `ai_kind` e, se non coincide, scrive in `document_analyses` un esito `failed`. `DocumentPipeline` non imposta lo stato `respinto` e non invia email.

Lo stato Respinto lo assegna una persona con `Unico\Core\Filament\Actions\DocumentOutcomeAction`.
