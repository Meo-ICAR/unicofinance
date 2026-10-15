<?php

namespace App\Filament\Resources\Clients\Tables;

use App\Filament\Resources\Clients\ClientResource;
use App\Models\Client;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company.name')
                    ->searchable(),
                IconColumn::make('is_person')
                    ->boolean(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('first_name')
                    ->searchable(),
                TextColumn::make('tax_code')
                    ->searchable(),
                TextColumn::make('vat_number')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('iban')
                    ->searchable(),
                TextColumn::make('employer.name')
                    ->searchable(),
                TextColumn::make('employment_typology')
                    ->searchable(),
                TextColumn::make('profession')
                    ->searchable(),
                TextColumn::make('hiring_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('website')
                    ->searchable(),
                IconColumn::make('is_pep')
                    ->boolean(),
                TextColumn::make('clientType.name')
                    ->searchable(),
                IconColumn::make('is_sanctioned')
                    ->boolean(),
                IconColumn::make('is_remote_interaction')
                    ->boolean(),
                TextColumn::make('general_consent_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('privacy_policy_read_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('consent_special_categories_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('consent_sic_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('consent_marketing_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('consent_profiling_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('consent_group_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('consent_third_parties_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->searchable(),
                IconColumn::make('is_company')
                    ->boolean(),
                IconColumn::make('is_lead')
                    ->boolean(),
                TextColumn::make('leadsource.id')
                    ->searchable(),
                TextColumn::make('acquired_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('contoCOGE')
                    ->searchable(),
                IconColumn::make('privacy_consent')
                    ->boolean(),
                IconColumn::make('is_client')
                    ->boolean(),
                IconColumn::make('is_requiredApprovation')
                    ->boolean(),
                IconColumn::make('is_approved')
                    ->boolean(),
                IconColumn::make('is_anonymous')
                    ->boolean(),
                TextColumn::make('blacklist_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('blacklisted_by')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('salary')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('salary_quote')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_art108')
                    ->boolean(),
                IconColumn::make('is_consultant_gdpr')
                    ->boolean(),
                TextColumn::make('privacy_contact_email')
                    ->searchable(),
                TextColumn::make('dpo_email')
                    ->searchable(),
                IconColumn::make('is_iso27001_certified')
                    ->boolean(),
                IconColumn::make('is_dummy')
                    ->boolean(),
                TextColumn::make('user.name')
                    ->searchable(),
                TextColumn::make('birth_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('birth_place')
                    ->searchable(),
                TextColumn::make('sex')
                    ->searchable(),
                TextColumn::make('citizenship')
                    ->searchable(),
                TextColumn::make('legalRepresentative.name')
                    ->searchable(),
                TextColumn::make('pec')
                    ->searchable(),
                TextColumn::make('ateco_code')
                    ->searchable(),
                TextColumn::make('cciaa_registration')
                    ->searchable(),
                TextColumn::make('legal_form')
                    ->searchable(),
                TextColumn::make('sdi_code')
                    ->searchable(),
                TextColumn::make('activity_status')
                    ->searchable(),
                TextColumn::make('company_started_at')
                    ->date()
                    ->sortable(),
                TextColumn::make('share_capital')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('employees')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('turnover')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('net_worth')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('balance_year')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('registry_updated_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordUrl(fn (Client $record): string => ClientResource::getUrl('edit', ['record' => $record]))
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
