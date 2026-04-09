<?php

namespace App\Filament\Resources\RequestRegistries\Pages;

use App\Filament\Resources\RequestRegistries\RequestRegistryResource;
use App\Models\RequestRegistry;
use App\Models\RequestRegistryAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditRequestRegistry extends EditRecord
{
    protected static string $resource = RequestRegistryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('log_action')
                ->label('Registra Azione')
                ->icon(Heroicon::OutlinedPlusCircle)
                ->form([
                    \Filament\Forms\Components\DateTimePicker::make('action_date')
                        ->label('Data/Ora')
                        ->required()
                        ->default(now())
                        ->native(false),
                    \Filament\Forms\Components\Select::make('action_type')
                        ->label('Tipo Azione')
                        ->options([
                            'assegnazione' => 'Assegnazione',
                            'inoltro' => 'Inoltro',
                            'risposta_preliminare' => 'Risposta Preliminare',
                            'evasione' => 'Evasione',
                            'estensione_termini' => 'Estensione Termini',
                            'reclamo_interno' => 'Reclamo Interno',
                        ])
                        ->required(),
                    \Filament\Forms\Components\Textarea::make('description')
                        ->label('Descrizione')
                        ->rows(3)
                        ->required(),
                ])
                ->action(function (array $data, RequestRegistry $record) {
                    RequestRegistryAction::create([
                        'registry_id' => $record->id,
                        'action_date' => $data['action_date'],
                        'action_type' => $data['action_type'],
                        'description' => $data['description'],
                        'performed_by' => auth()->id(),
                    ]);
                })
                ->successNotificationTitle('Azione registrata nel log'),
        ];
    }
}
