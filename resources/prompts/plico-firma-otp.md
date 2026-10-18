# Plico e firma OTP

Funzione AI non implementata. La firma non passa da un modello.

- Stampa e invio: `Unico\Core\Documents\PracticeSignaturePackage` e `Unico\Core\Filament\Schemas\PracticeSignaturePackageForm`, provider Yousign tramite `SignatureProviderManager`.
- Plico e stato «INVIO IN ISTRUTTORIA»: `App\Services\Istruttoria\InvioInIstruttoria`, archivio `Unico\Core\Documents\PracticeDossier`.
- Allineamento stati: comandi `signature:sync` e `signature:check`.
