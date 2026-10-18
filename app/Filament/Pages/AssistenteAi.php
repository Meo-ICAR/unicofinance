<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\Istruttoria\ContestoAssistente;
use App\Services\Istruttoria\RispostaAssistente;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Throwable;
use UnitEnum;

/**
 * Coda dell'istruttore. Visibile solo allo staff del pannello admin.
 * Non modifica pratiche: spiega il prossimo passo.
 *
 * @property-read Schema $form
 */
class AssistenteAi extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected string $view = 'filament.pages.assistente-ai';

    protected static ?string $navigationLabel = 'Assistente istruttoria';

    protected static UnitEnum|string|null $navigationGroup = 'Manuali';

    protected static ?int $navigationSort = 30;

    protected static ?string $title = 'Assistente istruttoria';

    protected static ?string $slug = 'assistente-istruttoria';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public ?string $answer = null;

    public ?string $error = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isStaff();
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Textarea::make('prompt')
                        ->label('Chiedi all\'assistente')
                        ->placeholder('Es: quali pratiche sono in attesa della banca?')
                        ->rows(3)
                        ->autosize()
                        ->required(),
                ])
                    ->id('assistente-ai-form')
                    ->livewireSubmitHandler('send')
                    ->footer([
                        Actions::make([
                            Action::make('send')
                                ->label('Chiedi')
                                ->icon('heroicon-o-paper-airplane')
                                ->submit('assistente-ai-form'),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function contestoCoda(): string
    {
        return app(ContestoAssistente::class)->coda();
    }

    /**
     * @return list<array{ruolo: string, testo: string}>
     */
    public function messaggi(): array
    {
        return app(RispostaAssistente::class)->messaggi();
    }

    public function send(): void
    {
        $this->error = null;
        $this->answer = null;
        $prompt = trim((string) ($this->form->getState()['prompt'] ?? ''));

        if ($prompt === '') {
            return;
        }

        try {
            $this->answer = app(RispostaAssistente::class)->chiedi($prompt);
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }
}
