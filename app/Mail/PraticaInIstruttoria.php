<?php

namespace App\Mail;

use App\Filament\Resources\Praticas\PraticaResource;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Unico\Core\Models\Document;
use Unico\Core\Models\Pratica;

/** Avviso all'istruttoria: una pratica con il fascicolo firmato è pronta da prendere in carico. */
class PraticaInIstruttoria extends Mailable
{
    use Queueable;

    /** Oltre questa dimensione il plico non si allega e si rimanda alla pratica. */
    private const MAX_ALLEGATO = 15 * 1024 * 1024;

    public function __construct(public Pratica $pratica, public ?Document $plico = null, public bool $simulazione = false) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: ($this->simulazione ? '[DEMO] ' : '')."Nuova pratica in istruttoria: {$this->pratica->codice_pratica} – {$this->pratica->cognome_cliente}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.pratica-in-istruttoria', with: [
            'url' => PraticaResource::getUrl('edit', ['record' => $this->pratica], panel: 'admin'),
            'prodotto' => $this->pratica->tipoprodotto?->name,
            'agente' => $this->pratica->fornitori?->name,
        ]);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $media = $this->plico?->currentMedia();

        if ($media === null || $media->size > self::MAX_ALLEGATO) {
            return [];
        }

        return [Attachment::fromPath($media->getPath())->as($media->file_name)->withMime('application/zip')];
    }
}
