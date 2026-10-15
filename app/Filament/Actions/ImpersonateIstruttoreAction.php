<?php

namespace App\Filament\Actions;

use App\Models\User;
use App\Services\Istruttoria\IstruttoreImpersonationService;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Unico\Core\Models\Employee;

/** Entra nel pannello come l'istruttore selezionato. */
class ImpersonateIstruttoreAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'impersonateIstruttore';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Impersonifica')
            ->icon('heroicon-o-eye')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Impersonifica istruttore')
            ->modalDescription('Entrerai nel pannello come questo istruttore, con la sua dashboard e le sue pratiche. Potrai tornare al tuo utente in qualsiasi momento.')
            ->visible(function (?Model $record): bool {
                $utente = Auth::user();

                return $record instanceof Employee && $utente instanceof User && app(IstruttoreImpersonationService::class)->canImpersonate($utente, $record);
            })
            ->action(function (Employee $record) {
                $utente = Auth::user();

                if (! $utente instanceof User) {
                    return;
                }

                try {
                    $url = app(IstruttoreImpersonationService::class)->start($utente, $record);
                } catch (RuntimeException $exception) {
                    $this->failureNotificationTitle($exception->getMessage());
                    $this->failure();

                    return;
                }

                $this->redirect($url);
            });
    }
}
