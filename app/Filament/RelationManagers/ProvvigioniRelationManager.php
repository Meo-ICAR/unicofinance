<?php

namespace App\Filament\RelationManagers;

use App\Models\Pratica;
use App\Services\Agenti\AgentImpersonationService;
use App\Services\Provvigioni\PercentualeListino;
use App\Support\PannelloProduttori;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Unico\Core\Models\Fornitore;

class ProvvigioniRelationManager extends RelationManager
{
    protected static string $relationship = 'provvigioni';

    protected static ?string $title = 'Provvigioni';

    protected static ?string $modelLabel = 'Provvigione';

    protected static ?string $pluralModelLabel = 'Provvigioni';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Pratica && ! $ownerRecord->is_notowned;
    }

    /** Listino del produttore per il prodotto della pratica, con le percentuali per voce. */
    private function descrizioneListino(): ?string
    {
        $pratica = $this->getOwnerRecord();
        $servizio = app(PercentualeListino::class);
        $listino = $pratica->fornitori_id === null ? null : $servizio->listino($pratica->fornitori_id, $pratica->tipoprodotto_id);

        if ($listino === null) {
            return $pratica->fornitori_id === null ? null : 'Il produttore non ha un listino assegnato per questo prodotto.';
        }

        return "Listino del produttore: {$listino->name} — {$listino->riepilogoVoci()}";
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('denominazione_riferimento')->label('Produttore'),
            TextInput::make('importo')->label('Importo')->numeric()->prefix('€'),
            TextInput::make('descrizione')->label('Descrizione')->required()->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        $produttore = PannelloProduttori::attivo();

        return $table
            ->description(fn (): ?string => $this->descrizioneListino())
            // Il produttore vede solo i propri compensi, mai quelli dell'istituto né dei suoi colleghi.
            ->modifyQueryUsing(function (Builder $query) use ($produttore): Builder {
                if (! $produttore) {
                    return $query;
                }

                $agente = Fornitore::query()->find(app(AgentImpersonationService::class)->agentFornitoreId());

                return $agente === null ? $query->whereRaw('0 = 1') : $query->dellAgente($agente);
            })
            ->columns([
                TextColumn::make('entrata_uscita')
                    ->visible(! $produttore)
                    ->label('Entrata/Uscita')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'Entrata' => 'success',
                        'Uscita' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('denominazione_riferimento')->label('Produttore'),
                TextColumn::make('importo')->label('Importo')->money('EUR'),
                TextColumn::make('percentuale')->label('%')->suffix('%')->placeholder('—'),
                TextColumn::make('descrizione')->label('Descrizione'),
                TextColumn::make('status_compenso')->label('Stato compenso'),
                TextColumn::make('data_status')->label('Data stato')->date('d/m/Y'),
            ])
            ->recordActions($produttore ? [] : [
                EditAction::make(),
            ]);
    }
}
