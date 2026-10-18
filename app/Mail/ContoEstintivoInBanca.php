<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Unico\Core\Models\Document;
use Unico\Core\Models\Pratica;

/** Il conto estintivo verificato dall'istruttore, trasmesso alla banca con il documento in allegato. */
class ContoEstintivoInBanca extends Mailable
{
    use Queueable;

    public function __construct(public Pratica $pratica, public Document $documento) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Conto estintivo pratica {$this->pratica->codice_pratica} – {$this->pratica->cognome_cliente}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.conto-estintivo-in-banca', with: ['istituto' => $this->pratica->clienti?->name]);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $media = $this->documento->currentMedia();

        return $media === null ? [] : [Attachment::fromPath($media->getPath())->as($media->file_name)->withMime((string) $media->mime_type)];
    }
}
