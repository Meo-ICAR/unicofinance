<?php
namespace App\Actions\Bpm;

use App\Contracts\BpmAction;
use App\Models\TaskExecution;
use Illuminate\Support\Facades\Mail;

class SendPrivacyOnboardingEmail implements BpmAction
{
    public function execute(TaskExecution $execution, array $params = []): void
    {
        $client = $execution->client;

        if ($client && $client->email) {
            Mail::to($client->email)->send(new \App\Mail\PrivacyWelcomeMail($client));

            // Logghiamo l'azione nell'activity log che già usi
            activity()
                ->performedOn($execution)
                ->log('Email di benvenuto inviata automaticamente al cliente.');
        }
    }
}
