<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/** Ogni tabella e colonna dei database ha un commento; ogni campo, colonna e azione Filament ha un'etichetta in italiano. */
class CommentiEEtichetteTest extends TestCase
{
    private const COMPONENTI = 'TextInput|Select|Textarea|Toggle|DatePicker|DateTimePicker|Checkbox|TextColumn|IconColumn|ImageColumn|SelectColumn|Filter|SelectFilter|TernaryFilter|TextEntry|IconEntry|ToggleButtons|CheckboxList|Repeater|FileUpload|ColorPicker|Action|BulkAction';

    /** @return array<int, string> */
    private function schemi(): array
    {
        return array_values(array_unique([config('database.connections.mysql.database'), config('database.connections.core.database')]));
    }

    public function test_every_table_and_column_of_the_databases_has_a_comment(): void
    {
        $connessione = DB::connection('core');

        foreach ($this->schemi() as $schema) {
            $tabelle = $connessione->select("select table_name as n from information_schema.tables where table_schema = ? and table_type = 'BASE TABLE' and (table_comment is null or table_comment = '')", [$schema]);
            $colonne = $connessione->select("select concat(c.table_name, '.', c.column_name) as n from information_schema.columns c join information_schema.tables t on t.table_schema = c.table_schema and t.table_name = c.table_name and t.table_type = 'BASE TABLE' where c.table_schema = ? and (c.column_comment is null or c.column_comment = '')", [$schema]);

            $this->assertSame([], array_map(fn ($r) => $r->n, $tabelle), "Tabelle senza commento in {$schema}");
            $this->assertSame([], array_map(fn ($r) => $r->n, $colonne), "Colonne senza commento in {$schema}");
        }
    }

    public function test_every_filament_field_column_and_action_has_a_label(): void
    {
        $senza = [];

        foreach ($this->fileFilament() as $file) {
            $codice = file_get_contents($file);

            preg_match_all('/\b('.self::COMPONENTI.')::make\(\s*([\'"])([^\'"]+)\2/', $codice, $trovati, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

            foreach ($trovati as $trovato) {
                $dopo = substr($codice, $trovato[0][1] + strlen($trovato[0][0]), 600);
                $segmento = explode(';', preg_split('/\b[A-Z][A-Za-z]+::make\(/', $dopo)[0])[0];

                if (! str_contains($segmento, '->label(')) {
                    $senza[] = basename($file).': '.$trovato[1][0].'::make('.$trovato[3][0].')';
                }
            }
        }

        $this->assertSame([], $senza, 'Campi, colonne o azioni senza ->label()');
    }

    public function test_labels_do_not_contain_english_words(): void
    {
        $inglesi = [];

        foreach ($this->fileFilament() as $file) {
            preg_match_all('/->(?:label|heading|title|tooltip)\(\s*\'([^\']+)\'/', file_get_contents($file), $trovati);

            foreach ($trovati[1] as $etichetta) {
                if (preg_match('/\b(address|company|user|employer|client type|legal representative|leadsource|created|updated|name|status|date)\b/i', $etichetta)) {
                    $inglesi[] = basename($file).': '.$etichetta;
                }
            }
        }

        $this->assertSame([], $inglesi, 'Etichette in inglese');
    }

    /** @return array<int, string> */
    private function fileFilament(): array
    {
        $file = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Filament'))) as $percorso) {
            if ($percorso->isFile() && $percorso->getExtension() === 'php') {
                $file[] = $percorso->getPathname();
            }
        }

        return $file;
    }
}
