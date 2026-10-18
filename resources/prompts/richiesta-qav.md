# Richiesta QAV

Funzione AI non implementata. Non c'è un prompt che chieda o compili il questionario.

Nel codice la compilazione è il form «Adeguata verifica (KYC)» di `App\Filament\Agenti\Pages\Simulazione::clienteForm`, salvato da `salvaCliente` in `App\Models\KycQuestionnaire`. Il tipo documento «QAV Persona fisica» è un modulo PDF del fascicolo Prestito (`ChecklistPerfezionamentoSeeder`), stampato e firmato con OTP, senza istruzione a un modello.
