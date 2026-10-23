<?php

namespace App\Filament\Resources\Clients\Tables;

use App\Filament\Actions\ExcelExportAction;
use App\Filament\Resources\Clients\ClientResource;
use App\Models\Client;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->headerActions([ExcelExportAction::make()])
            ->columns([
                TextColumn::make('name')
                    ->label('Cognome / Ragione sociale')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tipo_prodotto')
                    ->label('Tipo prodotto')
                    ->state(fn (Client $record): ?string => $record->praticaDiRiferimento()?->tipo_prodotto)
                    ->placeholder('—'),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Cellulare')
                    ->searchable(),
                TextColumn::make('banca')
                    ->label('Banca')
                    ->state(fn (Client $record): ?string => $record->praticaDiRiferimento()?->denominazione_banca)
                    ->placeholder('—'),
                TextColumn::make('agente')
                    ->label('Agente')
                    ->state(fn (Client $record): ?string => $record->praticaDiRiferimento()?->fornitori?->name)
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Stato verifica')
                    ->searchable(),
                TextColumn::make('first_name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('tax_code')
                    ->label('Codice fiscale / P.IVA')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('clientType.name')
                    ->label('Tipo cliente')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_pep')
                    ->label('PEP')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_sanctioned')
                    ->label('Sanzionato / blacklist')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('company.name')
                    ->label('Società')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_person')
                    ->label('Persona fisica')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('vat_number')
                    ->label('Partita IVA')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('iban')
                    ->label('IBAN')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('employer.name')
                    ->label('Datore di lavoro')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('employment_typology')
                    ->label('Tipologia di rapporto')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('profession')
                    ->label('Professione')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('hiring_date')
                    ->label('Data di assunzione')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('website')
                    ->label('Sito web')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_remote_interaction')
                    ->label('Interazione a distanza')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('general_consent_at')
                    ->label('Consenso base')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('privacy_policy_read_at')
                    ->label('Informativa letta')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('consent_special_categories_at')
                    ->label('Categorie particolari')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('consent_sic_at')
                    ->label('Consenso SIC (CRIF)')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('consent_marketing_at')
                    ->label('Marketing')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('consent_profiling_at')
                    ->label('Profilazione')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('consent_group_at')
                    ->label('Comunicazione al gruppo')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('consent_third_parties_at')
                    ->label('Comunicazione a terzi')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_company')
                    ->label('Società')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_lead')
                    ->label('Lead')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('leadsource_id')
                    ->label('Fonte del lead')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('acquired_at')
                    ->label('Acquisito il')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('contoCOGE')
                    ->label('Conto COGE')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('privacy_consent')
                    ->label('Consenso privacy attivo')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_client')
                    ->label('Cliente')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_requiredApprovation')
                    ->label('Richiede approvazione')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_approved')
                    ->label('Approvato')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_anonymous')
                    ->label('Anonimo')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('blacklist_at')
                    ->label('Data blacklist')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('blacklisted_by')
                    ->label('Inserito in blacklist da')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('salary')
                    ->label('Reddito')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('salary_quote')
                    ->label('Quota cedibile')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_art108')
                    ->label('Esente art. 108 TUB')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_consultant_gdpr')
                    ->label('Consulente GDPR')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('privacy_contact_email')
                    ->label('Email contatto privacy')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('dpo_email')
                    ->label('Email DPO')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_iso27001_certified')
                    ->label('Certificato ISO 27001')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_dummy')
                    ->label('Fittizio')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user.name')
                    ->label('Utente')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('birth_date')
                    ->label('Data di nascita')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('birth_place')
                    ->label('Luogo di nascita')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sex')
                    ->label('Sesso')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('citizenship')
                    ->label('Cittadinanza')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('legalRepresentative.name')
                    ->label('Legale rappresentante')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('pec')
                    ->label('PEC')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ateco_code')
                    ->label('Codice Ateco')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cciaa_registration')
                    ->label('Iscrizione CCIAA')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('legal_form')
                    ->label('Forma giuridica')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sdi_code')
                    ->label('Codice SDI')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('activity_status')
                    ->label('Stato attività')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('company_started_at')
                    ->label('Inizio attività')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('share_capital')
                    ->label('Capitale sociale')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('employees')
                    ->label('Dipendenti')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('turnover')
                    ->label('Fatturato')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('net_worth')
                    ->label('Patrimonio netto')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('balance_year')
                    ->label('Anno bilancio')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('registry_updated_at')
                    ->label('Registro imprese aggiornato il')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Aggiornato il')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // I datori di lavoro hanno la loro voce di menu: qui si vedono solo con il filtro acceso.
            ->modifyQueryUsing(fn (Builder $query, $livewire): Builder => (($livewire->tableFilters['con_datori']['isActive'] ?? false) ? $query : $query->senzaDatoriDiLavoro())->with('ultimaPratica.fornitori'))
            ->filters([
                Filter::make('con_datori')->label('Includi i datori di lavoro')->toggle()->query(fn (Builder $query): Builder => $query),
            ])
            ->recordUrl(fn (Client $record): string => ClientResource::getUrl('edit', ['record' => $record]))
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
