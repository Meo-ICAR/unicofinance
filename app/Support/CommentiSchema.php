<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Commenti di tabelle e colonne (database MySQL/MariaDB): ogni tabella e ogni campo ne ha uno. Aggiunge quelli mancanti
 * senza toccare tipo, valore predefinito e chiavi della colonna; quelli già presenti non si sovrascrivono.
 */
class CommentiSchema
{
    /**
     * @param  array<string, string>  $colonne  nome colonna => commento
     */
    public static function applica(string $connessione, string $tabella, ?string $commentoTabella, array $colonne): void
    {
        $db = DB::connection($connessione);

        if ($db->getDriverName() === 'sqlite' || ! $db->getSchemaBuilder()->hasTable($tabella)) {
            return;
        }

        $esistente = $db->selectOne('select table_comment as c from information_schema.tables where table_schema = database() and table_name = ?', [$tabella]);

        if ($commentoTabella !== null && blank($esistente?->c)) {
            $db->statement('ALTER TABLE `'.$tabella.'` COMMENT = '.$db->getPdo()->quote($commentoTabella));
        }

        foreach ($colonne as $colonna => $commento) {
            $campo = $db->selectOne('SHOW FULL COLUMNS FROM `'.$tabella.'` WHERE Field = ?', [$colonna]);

            if ($campo === null || filled($campo->Comment)) {
                continue;
            }

            $db->statement('ALTER TABLE `'.$tabella.'` MODIFY COLUMN `'.$colonna.'` '.self::definizione($db, $campo).' COMMENT '.$db->getPdo()->quote($commento));
        }
    }

    private static function definizione($db, object $campo): string
    {
        $sql = $campo->Type;

        if (filled($campo->Collation)) {
            $sql .= ' CHARACTER SET '.explode('_', $campo->Collation)[0].' COLLATE '.$campo->Collation;
        }

        $sql .= $campo->Null === 'YES' ? ' NULL' : ' NOT NULL';

        if ($campo->Default !== null) {
            $sql .= str_contains(strtoupper((string) $campo->Default), 'CURRENT_TIMESTAMP') ? ' DEFAULT '.$campo->Default : ' DEFAULT '.$db->getPdo()->quote((string) $campo->Default);
        } elseif ($campo->Null === 'YES' && ! str_contains(strtolower($campo->Type), 'text') && ! str_contains(strtolower($campo->Type), 'json') && ! str_contains(strtolower($campo->Type), 'blob')) {
            $sql .= ' DEFAULT NULL';
        }

        $extra = trim(str_ireplace('DEFAULT_GENERATED', '', (string) $campo->Extra));

        return $extra === '' ? $sql : $sql.' '.$extra;
    }
}
