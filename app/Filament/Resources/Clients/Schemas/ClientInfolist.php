<?php

namespace App\Filament\Resources\Clients\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ClientInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('company.name')
                    ->label('Società')
                    ->placeholder('-'),
                IconEntry::make('is_person')
                    ->label('Persona fisica')
                    ->boolean(),
                TextEntry::make('name')
                    ->label('Cognome / Ragione sociale'),
                TextEntry::make('first_name')
                    ->label('Nome')
                    ->placeholder('-'),
                TextEntry::make('tax_code')
                    ->label('Codice fiscale / P.IVA')
                    ->placeholder('-'),
                TextEntry::make('vat_number')
                    ->label('Partita IVA')
                    ->placeholder('-'),
                TextEntry::make('email')
                    ->label('Email')
                    ->placeholder('-'),
                TextEntry::make('phone')
                    ->label('Telefono')
                    ->placeholder('-'),
                TextEntry::make('iban')
                    ->label('IBAN')
                    ->placeholder('-'),
                TextEntry::make('employer.name')
                    ->label('Datore di lavoro')
                    ->placeholder('-'),
                TextEntry::make('employment_typology')
                    ->label('Tipologia di rapporto')
                    ->placeholder('-'),
                TextEntry::make('profession')
                    ->label('Professione')
                    ->placeholder('-'),
                TextEntry::make('hiring_date')
                    ->label('Data di assunzione')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('richiesta_benestare')
                    ->label('Richiesta benestare')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('ritiro_benestare')
                    ->label('Ritiro benestare')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('notifica')
                    ->label('Notifica')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('website')
                    ->label('Sito web')
                    ->placeholder('-'),
                IconEntry::make('is_pep')
                    ->label('PEP')
                    ->boolean(),
                TextEntry::make('clientType.name')
                    ->label('Tipo cliente')
                    ->placeholder('-'),
                IconEntry::make('is_sanctioned')
                    ->label('Sanzionato / blacklist')
                    ->boolean(),
                IconEntry::make('is_remote_interaction')
                    ->label('Interazione a distanza')
                    ->boolean(),
                TextEntry::make('general_consent_at')
                    ->label('Consenso base')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('privacy_policy_read_at')
                    ->label('Informativa letta')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('consent_special_categories_at')
                    ->label('Categorie particolari')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('consent_sic_at')
                    ->label('Consenso SIC (CRIF)')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('consent_marketing_at')
                    ->label('Marketing')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('consent_profiling_at')
                    ->label('Profilazione')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('consent_group_at')
                    ->label('Comunicazione al gruppo')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('consent_third_parties_at')
                    ->label('Comunicazione a terzi')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('status')
                    ->label('Stato verifica'),
                IconEntry::make('is_company')
                    ->label('Società')
                    ->boolean(),
                IconEntry::make('is_lead')
                    ->label('Lead')
                    ->boolean(),
                TextEntry::make('leadsource.id')
                    ->label('Fonte del lead')
                    ->placeholder('-'),
                TextEntry::make('acquired_at')
                    ->label('Acquisito il')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('contoCOGE')
                    ->label('Conto COGE')
                    ->placeholder('-'),
                IconEntry::make('privacy_consent')
                    ->label('Consenso privacy attivo')
                    ->boolean(),
                IconEntry::make('is_client')
                    ->label('Cliente')
                    ->boolean(),
                TextEntry::make('subfornitori')
                    ->label('Subfornitori')
                    ->placeholder('-')
                    ->columnSpanFull(),
                IconEntry::make('is_requiredApprovation')
                    ->label('Richiede approvazione')
                    ->boolean(),
                IconEntry::make('is_approved')
                    ->label('Approvato')
                    ->boolean(),
                IconEntry::make('is_anonymous')
                    ->label('Anonimo')
                    ->boolean(),
                TextEntry::make('blacklist_at')
                    ->label('Data blacklist')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('blacklisted_by')
                    ->label('Inserito in blacklist da')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('salary')
                    ->label('Reddito')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('salary_quote')
                    ->label('Quota cedibile')
                    ->numeric()
                    ->placeholder('-'),
                IconEntry::make('is_art108')
                    ->label('Esente art. 108 TUB')
                    ->boolean(),
                IconEntry::make('is_consultant_gdpr')
                    ->label('Consulente GDPR')
                    ->boolean(),
                TextEntry::make('privacy_contact_email')
                    ->label('Email contatto privacy')
                    ->placeholder('-'),
                TextEntry::make('dpo_email')
                    ->label('Email DPO')
                    ->placeholder('-'),
                IconEntry::make('is_iso27001_certified')
                    ->label('Certificato ISO 27001')
                    ->boolean(),
                IconEntry::make('is_dummy')
                    ->label('Fittizio')
                    ->boolean(),
                TextEntry::make('user.name')
                    ->label('Utente')
                    ->placeholder('-'),
                TextEntry::make('birth_date')
                    ->label('Data di nascita')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('birth_place')
                    ->label('Luogo di nascita')
                    ->placeholder('-'),
                TextEntry::make('sex')
                    ->label('Sesso')
                    ->placeholder('-'),
                TextEntry::make('citizenship')
                    ->label('Cittadinanza')
                    ->placeholder('-'),
                TextEntry::make('legalRepresentative.name')
                    ->label('Legale rappresentante')
                    ->placeholder('-'),
                TextEntry::make('pec')
                    ->label('PEC')
                    ->placeholder('-'),
                TextEntry::make('ateco_code')
                    ->label('Codice Ateco')
                    ->placeholder('-'),
                TextEntry::make('cciaa_registration')
                    ->label('Iscrizione CCIAA')
                    ->placeholder('-'),
                TextEntry::make('legal_form')
                    ->label('Forma giuridica')
                    ->placeholder('-'),
                TextEntry::make('sdi_code')
                    ->label('Codice SDI')
                    ->placeholder('-'),
                TextEntry::make('activity_status')
                    ->label('Stato attività')
                    ->placeholder('-'),
                TextEntry::make('company_started_at')
                    ->label('Inizio attività')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('share_capital')
                    ->label('Capitale sociale')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('employees')
                    ->label('Dipendenti')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('turnover')
                    ->label('Fatturato')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('net_worth')
                    ->label('Patrimonio netto')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('balance_year')
                    ->label('Anno bilancio')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('registry_updated_at')
                    ->label('Registro imprese aggiornato il')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->label('Creato il')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->label('Aggiornato il')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
