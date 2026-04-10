<?php

declare(strict_types=1);

namespace App\Rules\Bpm\Actions;

use App\Contracts\BpmAction;
use App\Contracts\BpmActionInterface;
use App\Models\Agent;
use App\Models\TaskExecution;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ProvisionITAccountsAction
 *
 * When an Agent is approved and contracted, this action:
 *   1. Generates a corporate email address (first.last@tuazienda.it)
 *   2. Creates a `users` record so the agent can access the management system
 *      with a random temporary password
 *   3. Updates the Agent's status to 'attivo'
 *
 * The temporary password is stored hashed in the users table and will
 * be communicated to the agent via the SendWelcomeEmailAction (which
 * should be the next checklist item in the BPM process).
 *
 * Registered in checklist_items.action_class as:
 *   App\Rules\Bpm\Actions\ProvisionITAccountsAction
 */
final class ProvisionITAccountsAction implements BpmAction, BpmActionInterface
{
    use ResolvesTargetAgent;

    /**
     * Corporate email domain — change to your actual company domain.
     */
    private const EMAIL_DOMAIN = 'tuazienda.it';

    public function execute(TaskExecution $execution, array $params = []): void
    {
        $agent = $this->resolveAgent($execution);

        // ── 1. Generate corporate email ──────────────────────────

        $corporateEmail = $this->generateCorporateEmail($agent);

        // Prevent duplicate users for the same email
        $existingUser = User::where('email', $corporateEmail)->first();
        if ($existingUser) {
            Log::warning('ProvisionITAccountsAction: user with corporate email already exists', [
                'agent_id' => $agent->id,
                'email' => $corporateEmail,
                'existing_user_id' => $existingUser->id,
            ]);

            // Link existing user to agent and still activate
            $agent->user()->associate($existingUser);
            $agent->email_corporate = $corporateEmail;
        } else {
            // ── 2. Generate temporary password ───────────────────

            $temporaryPassword = $this->generateTemporaryPassword();

            // ── 3. Create User record ────────────────────────────

            $user = User::create([
                'name' => $agent->full_name,
                'email' => $corporateEmail,
                'password' => Hash::make($temporaryPassword),
                'is_approved' => true,
                'email_verified_at' => now(),
            ]);

            $agent->user()->associate($user);
            $agent->email_corporate = $corporateEmail;

            Log::info('ProvisionITAccountsAction: user account created', [
                'agent_id' => $agent->id,
                'user_id' => $user->id,
                'email' => $corporateEmail,
            ]);
        }

        // ── 4. Activate the agent ───────────────────────────────

        $agent->status = Agent::STATUS_ATTIVO;
        $agent->save();

        // Store the plain-text temporary password on the model temporarily
        // so that SendWelcomeEmailAction can read it.  We use a non-persisted
        // attribute to avoid storing plaintext passwords in the DB.
        $agent->setAttribute('_temporary_password', $temporaryPassword);

        Log::info('ProvisionITAccountsAction: agent activated', [
            'agent_id' => $agent->id,
            'status' => $agent->status,
            'corporate_email' => $corporateEmail,
        ]);
    }

    /**
     * Generate a corporate email from the agent's name.
     *
     * Handles collisions by appending a numeric suffix:
     *   mario.rossi@tuazienda.it → mario.rossi2@tuazienda.it
     */
    private function generateCorporateEmail(Agent $agent): string
    {
        $base = Str::ascii(strtolower($agent->first_name)) . '.'
            . Str::ascii(strtolower($agent->last_name));

        $email = $base . '@' . self::EMAIL_DOMAIN;

        // If the exact email already exists on another agent, add a suffix
        $collision = Agent::where('email_corporate', $email)
            ->whereKeyNot($agent->id)
            ->exists();

        if ($collision) {
            $counter = 2;
            do {
                $email = $base . $counter . '@' . self::EMAIL_DOMAIN;
                $counter++;
            } while (
                User::where('email', $email)->exists()
                || Agent::where('email_corporate', $email)
                    ->whereKeyNot($agent->id)
                    ->exists()
            );
        }

        return $email;
    }

    /**
     * Generate a secure temporary password (16 chars, alphanumeric + symbols).
     */
    private function generateTemporaryPassword(): string
    {
        // 16-character password with uppercase, lowercase, digits, and symbols
        $pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*';
        $length = 16;

        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $pool[random_int(0, strlen($pool) - 1)];
        }

        return $password;
    }
}
