<?php

namespace App\Filament\Pages;

use App\Models\Process;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\FontWeight;
use Filament\Facades\Filament;
use Livewire\Component;
use Filament\Support\Enums\TextSize;
use BackedEnum;

class ManualeOperativo extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $title = 'Manuale Operativo';
    protected string $view = 'filament.pages.manuale-operativo';

    /**
     * In Filament 5.4 registriamo lo schema esplicitamente.
     */
    protected function getSchemas(): array
    {
        return [
            'infolistSchema',
        ];
    }

    /**
     * Definiamo lo schema dell'Infolist.
     */
    public function infolistSchema(Schema $schema): Schema
    {
        return $schema
            ->state(['processes' => $this->getProcessesData()])
            ->components([
                RepeatableEntry::make('processes')
                    ->label('')
                    ->schema([

                                TextEntry::make('name')
                                    ->label('Nome Processo')
                                    ->weight(FontWeight::Bold)
                                ->size(TextSize::Large),

                                TextEntry::make('business_function.name')
                                    ->label('Owner')
                                    ->color('primary'),

                                TextEntry::make('description')
                                    ->label('Descrizione Processo')
                                    ->markdown(),

                                RepeatableEntry::make('tasks')
                                    ->label('Step Operativi')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                TextEntry::make('sequence_number')
                                                    ->label('Step')
                                                    ->weight(FontWeight::Bold)
                                                    ->columnSpan(1),
                                                TextEntry::make('name')
                                                    ->label('Task')
                                                    ->weight(FontWeight::Bold)
                                                    ->columnSpan(2),
                                            ]),

                                        TextEntry::make('description')
                                            ->hiddenLabel()
                                            ->color('gray'),


                                            RepeatableEntry::make('privacy_data')
    ->label('Dati Privacy Trattati')
    ->schema([
        Grid::make(3)->schema([
            TextEntry::make('privacy_data_type.name')
                ->label('Tipo Dato')
                ->weight(FontWeight::Bold),
            TextEntry::make('access_level')
                ->label('Accesso')
                ->badge()
                ->color('warning'),
            TextEntry::make('legal_basis')
                ->label('Base Giuridica'),
        ]),
        TextEntry::make('purpose')
            ->label('Finalità')
            ->size(TextEntry\TextEntrySize::Small)
            ->italic(),
    ])
    ->columns(1)
    ->grid(2), // Mostra i dati privacy su due colonne per risparmiare spazio verticale

                                        RepeatableEntry::make('checklists')
                                            ->label('Checklist Operative')
                                            ->schema([
                                                TextEntry::make('name')
                                                    ->label('Gruppo')
                                                    ->weight(FontWeight::SemiBold)
                                                    ->color('primary'),

                                                RepeatableEntry::make('items')
                                                    ->label('Istruzioni di Dettaglio')
                                                    ->schema([
                                                        TextEntry::make('instruction')
                                                            ->hiddenLabel()
                                                            ->bulleted()
                                                            ->columns(1)
                                                    ])
                                            ]),

                                        RepeatableEntry::make('raci_assignments')
                                            ->label('Matrice RACI')
                                            ->grid(4)
                                            ->schema([
                                                TextEntry::make('business_function.name')
                                                    ->label('Funzione'),
                                                TextEntry::make('role')
                                                    ->label('Ruolo')
                                                    ->badge()
                                                    ->color(fn (string $state): string => match ($state) {
                                                        'R' => 'info',
                                                        'A' => 'danger',
                                                        'C' => 'warning',
                                                        'I' => 'success',
                                                        default => 'gray',
                                                    }),
                                            ]),
                                    ]),

                    ]),
            ]);
    }

    /**
     * Recupero dati con filtro Tenant per sicurezza (visto l'URL precedentemente fornito).
     */
    protected function getProcessesData(): array
    {
        return Process::query()
            ->where('company_id', Filament::getTenant()?->id)
            ->with(['businessFunction', 'tasks.raciAssignments.businessFunction', 'tasks.checklists.items'])
            ->where('is_active', true)
            ->get()
            ->toArray();
    }


}
