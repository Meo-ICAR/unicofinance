<?php
namespace App\Actions\Bpm;

use App\Contracts\BpmAction;
use App\Mail\PrivacyInformationMail;
use App\Models\TaskExecution;
use Illuminate\Support\Facades\Mail;

class SendPrivacyWelcomeEmail implements BpmAction
{
    public function execute(TaskExecution $execution, array $params = []): void
    {
        $client = $execution->client;

        if ($client && $client->email) {
            // Invio email asincrono (Queue)
            Mail::to($client->email)->send(new PrivacyInformationMail($client));
        }
    }
}
