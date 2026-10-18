<?php

namespace App\Services;

use App\Models\SocialiteUser;
use Illuminate\Support\Facades\Storage;

/**
 * L'avatar che arriva da Microsoft o Google. Google dà un indirizzo, che si salva se sta nella colonna; Microsoft dà la foto stessa come
 * `data:image/...;base64`, troppo lunga per la colonna: si salva come file (`avatars/{utente}.{estensione}`) e la colonna resta vuota.
 */
class AvatarSocialite
{
    private const ESTENSIONI = ['jpeg' => 'jpg', 'jpg' => 'jpg', 'png' => 'png', 'webp' => 'webp', 'gif' => 'gif'];

    private const LUNGHEZZA_COLONNA = 255;

    /** Indirizzo da scrivere in `socialite_users.avatar`; una foto in base64 si archivia come file e qui resta vuoto. */
    public function indirizzo(?string $avatar, int|string $utenteId): ?string
    {
        $avatar = trim((string) $avatar);

        if ($avatar === '') {
            return null;
        }

        if (str_starts_with($avatar, 'data:')) {
            $this->archivia($avatar, $utenteId);

            return null;
        }

        return preg_match('#^https?://#i', $avatar) === 1 && strlen($avatar) <= self::LUNGHEZZA_COLONNA ? $avatar : null;
    }

    /** L'indirizzo dell'avatar dell'utente: la foto archiviata, altrimenti l'ultimo indirizzo ricevuto dai provider. */
    public function url(int|string $utenteId): ?string
    {
        foreach (array_unique(array_values(self::ESTENSIONI)) as $estensione) {
            $percorso = $this->percorso($utenteId, $estensione);

            if (Storage::disk('public')->exists($percorso)) {
                return Storage::disk('public')->url($percorso).'?v='.Storage::disk('public')->lastModified($percorso);
            }
        }

        return SocialiteUser::query()->where('user_id', $utenteId)->whereNotNull('avatar')->orderByDesc('updated_at')->value('avatar');
    }

    private function archivia(string $dataUri, int|string $utenteId): void
    {
        if (preg_match('#^data:image/([a-z0-9.+-]+);base64,(.+)$#is', $dataUri, $match) !== 1) {
            return;
        }

        $estensione = self::ESTENSIONI[strtolower($match[1])] ?? null;
        $contenuto = base64_decode($match[2], true);

        if ($estensione === null || $contenuto === false || $contenuto === '') {
            return;
        }

        // Una sola foto per utente: quella nuova prende il posto della vecchia.
        foreach (array_unique(array_values(self::ESTENSIONI)) as $altra) {
            Storage::disk('public')->delete($this->percorso($utenteId, $altra));
        }

        Storage::disk('public')->put($this->percorso($utenteId, $estensione), $contenuto);
    }

    private function percorso(int|string $utenteId, string $estensione): string
    {
        return 'avatars/'.$utenteId.'.'.$estensione;
    }
}
