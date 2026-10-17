<?php

namespace App\Filament\Resources\Clients\Tables;

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
            ->columns([
                TextColumn::make('company.name')
                    ->label('Società')
                    ->searchable(),
                IconColumn::make('is_person')
                    ->label('Persona fisica')
                    ->boolean(),
                TextColumn::make('name')
                    ->label('Cognome / Ragione sociale')
                    ->searchable(),
                TextColumn::make('first_name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('tax_code')
                    ->label('Codice fiscale / P.IVA')
                    ->searchable(),
                TextColumn::make('vat_number')
                    ->label('Partita IVA')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Telefono')
                    ->searchable(),
                TextColumn::make('iban')
                    ->label('IBAN')
                    ->searchable(),
                TextColumn::make('employer.name')
                    ->label('Datore di lavoro')
                    ->searchable(),
                TextColumn::make('employment_typology')
                    ->label('Tipologia di rapporto')
                    ->searchable(),
                TextColumn::make('profession')
                    ->label('Professione')
                    ->searchable(),
                TextColumn::make('hiring_date')
                    ->label('Data di assunzione')
                    ->date()
                    ->sortable(),
                TextColumn::make('website')
                    ->label('Sito web')
                    ->searchable(),
                IconColumn::make('is_pep')
                    ->label('PEP')
                    ->boolean(),
                TextColumn::make('clientType.name')
                    ->label('Tipo cliente')
                    ->searchable(),
                IconColumn::make('is_sanctioned')
                    ->label('Sanzionato / blacklist')
                    ->boolean(),
                IconColumn::make('is_remote_interaction')
                    ->label('Interazione a distanza')
                    ->boolean(),
                TextColumn::make('general_consent_at')
                    ->label('Consenso base')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('privacy_policy_read_at')
                    ->label('Informativa letta')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('consent_special_categories_at')
                    ->label('Categorie particolari')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('consent_sic_at')
                    ->label('Consenso SIC (CRIF)')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('consent_marketing_at')
                    ->label('Marketing')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('consent_profiling_at')
                    ->label('Profilazione')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('consent_group_at')
                    ->label('Comunicazione al gruppo')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('consent_third_parties_at')
                    ->label('Comunicazione a terzi')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Stato verifica')
                    ->searchable(),
                IconColumn::make('is_company')
                    ->label('Società')
                    ->boolean(),
                IconColumn::make('is_lead')
                    ->label('Lead')
                    ->boolean(),
                TextColumn::make('leadsource_id')
                    ->label('Fonte del lead')
                    ->placeholder('—'),
                TextColumn::make('acquired_at')
                    ->label('Acquisito il')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('contoCOGE')
                    ->label('Conto COGE')
                    ->searchable(),
                IconColumn::make('privacy_consent')
                    ->label('Consenso privacy attivo')
                    ->boolean(),
                IconColumn::make('is_client')
                    ->label('Cliente')
                    ->boolean(),
                IconColumn::make('is_requiredApprovation')
                    ->label('Richiede approvazione')
                    ->boolean(),
                IconColumn::make('is_approved')
                    ->label('Approvato')
                    ->boolean(),
                IconColumn::make('is_anonymous')
                    ->label('Anonimo')
                    ->boolean(),
                TextColumn::make('blacklist_at')
                    ->label('Data blacklist')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('blacklisted_by')
                    ->label('Inserito in blacklist da')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('salary')
                    ->label('Reddito')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('salary_quote')
                    ->label('Quota cedibile')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_art108')
                    ->label('Esente art. 108 TUB')
                    ->boolean(),
                IconColumn::make('is_consultant_gdpr')
                    ->label('Consulente GDPR')
                    ->boolean(),
                TextColumn::make('privacy_contact_email')
                    ->label('Email contatto privacy')
                    ->searchable(),
                TextColumn::make('dpo_email')
                    ->label('Email DPO')
                    ->searchable(),
                IconColumn::make('is_iso27001_certified')
                    ->label('Certificato ISO 27001')
                    ->boolean(),
                IconColumn::make('is_dummy')
                    ->label('Fittizio')
                    ->boolean(),
                TextColumn::make('user.name')
                    ->label('Utente')
                    ->searchable(),
                TextColumn::make('birth_date')
                    ->label('Data di nascita')
                    ->date()
                    ->sortable(),
                TextColumn::make('birth_place')
                    ->label('Luogo di nascita')
                    ->searchable(),
                TextColumn::make('sex')
                    ->label('Sesso')
                    ->searchable(),
                TextColumn::make('citizenship')
                    ->label('Cittadinanza')
                    ->searchable(),
                TextColumn::make('legalRepresentative.name')
                    ->label('Legale rappresentante')
                    ->searchable(),
                TextColumn::make('pec')
                    ->label('PEC')
                    ->searchable(),
                TextColumn::make('ateco_code')
                    ->label('Codice Ateco')
                    ->searchable(),
                TextColumn::make('cciaa_registration')
                    ->label('Iscrizione CCIAA')
                    ->searchable(),
                TextColumn::make('legal_form')
                    ->label('Forma giuridica')
                    ->searchable(),
                TextColumn::make('sdi_code')
                    ->label('Codice SDI')
                    ->searchable(),
                TextColumn::make('activity_status')
                    ->label('Stato attività')
                    ->searchable(),
                TextColumn::make('company_started_at')
                    ->label('Inizio attività')
                    ->date()
                    ->sortable(),
                TextColumn::make('share_capital')
                    ->label('Capitale sociale')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('employees')
                    ->label('Dipendenti')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('turnover')
                    ->label('Fatturato')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('net_worth')
                    ->label('Patrimonio netto')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('balance_year')
                    ->label('Anno bilancio')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('registry_updated_at')
                    ->label('Registro imprese aggiornato il')
                    ->dateTime()
                    ->sortable(),
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
            ->modifyQueryUsing(fn (Builder $query, $livewire): Builder => ($livewire->tableFilters['con_datori']['isActive'] ?? false) ? $query : $query->senzaDatoriDiLavoro())
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
