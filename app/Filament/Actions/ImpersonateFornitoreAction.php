<?php

namespace App\Filament\Actions;

use App\Models\User;
use App\Services\Agenti\AgentImpersonationService;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Unico\Core\Models\Fornitore;

/** Entra nel portale agenti come il produttore selezionato. */
class ImpersonateFornitoreAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'impersonateFornitore';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Impersona')
            ->icon('heroicon-o-eye')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Impersona produttore')
            ->modalDescription('Entrerai nel portale agenti come questo produttore. Potrai tornare all\'admin in qualsiasi momento.')
            ->visible(function (?Model $record): bool {
                if (! $record instanceof Fornitore) {
                    return false;
                }

                $user = Auth::user();

                return $user instanceof User
                    && app(AgentImpersonationService::class)->canImpersonate($user, $record);
            })
            ->action(function (Fornitore $record) {
                $user = Auth::user();

                if (! $user instanceof User) {
                    return;
                }

                try {
                    $url = app(AgentImpersonationService::class)->start($user, $record);
                } catch (RuntimeException $exception) {
                    $this->failureNotificationTitle($exception->getMessage());
                    $this->failure();

                    return;
                }

                $this->redirect($url);
            });
    }
}
