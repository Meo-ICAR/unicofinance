<?php

namespace App\Filament\Actions;

use App\Models\User;
use App\Services\Istruttoria\CreaUtenteIstruttore;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Unico\Core\Models\Employee;

/** Crea l'utente di accesso dell'istruttore che non ne ha ancora uno: al suo posto non si può impersonificare. */
class CreaUtenteIstruttoreAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'creaUtenteIstruttore';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Crea utente')
            ->icon('heroicon-o-user-plus')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Crea utente istruttore')
            ->modalDescription("Viene creato l'utente con l'email dell'istruttore e collegato alla sua scheda. La password iniziale è «password».")
            ->visible(function (?Model $record): bool {
                $utente = Auth::user();

                return $record instanceof Employee
                    && $record->user_id === null
                    && $record->is_active
                    && $utente instanceof User
                    && in_array($utente->role, ['admin', 'super_admin'], true);
            })
            ->action(function (Employee $record): void {
                try {
                    $utente = app(CreaUtenteIstruttore::class)->crea($record);
                } catch (RuntimeException $e) {
                    Notification::make()->title('Utente non creato')->body($e->getMessage())->warning()->send();

                    return;
                }

                Notification::make()->title('Utente creato e collegato')->body($utente->email)->success()->send();
            });
    }
}
