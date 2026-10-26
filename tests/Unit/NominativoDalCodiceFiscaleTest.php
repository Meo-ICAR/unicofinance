<?php

namespace Tests\Unit;

use App\Support\NominativoDalCodiceFiscale;
use PHPUnit\Framework\TestCase;

class NominativoDalCodiceFiscaleTest extends TestCase
{
    public function test_swapped_names_are_put_right_and_the_right_ones_are_left_alone(): void
    {
        $this->assertSame(['NAPOLI', 'DOMENICO'], NominativoDalCodiceFiscale::ordina('DOMENICO', 'NAPOLI', 'NPLDNC86L22G791C'));
        $this->assertSame(['NAPOLI', 'DOMENICO'], NominativoDalCodiceFiscale::ordina('NAPOLI', 'DOMENICO', 'NPLDNC86L22G791C'));
    }

    public function test_without_a_person_tax_code_or_with_a_missing_name_nothing_changes(): void
    {
        $this->assertSame(['DOMENICO', 'NAPOLI'], NominativoDalCodiceFiscale::ordina('DOMENICO', 'NAPOLI', '12345678903'));
        $this->assertSame(['DOMENICO', null], NominativoDalCodiceFiscale::ordina('DOMENICO', null, 'NPLDNC86L22G791C'));
        $this->assertSame(['ROSSI', 'MARIO'], NominativoDalCodiceFiscale::ordina('ROSSI', 'MARIO', 'XXXXXXXXXXXXXXXX'));
    }

    public function test_accents_and_short_names_are_handled(): void
    {
        $this->assertSame('DXX', NominativoDalCodiceFiscale::codiceCognome('D'));
        $this->assertSame('MRC', NominativoDalCodiceFiscale::codiceCognome('Mercuri'));
        $this->assertSame('VNT', NominativoDalCodiceFiscale::codiceNome('Valentina'));
        $this->assertSame(['NIGRO', 'JOSÈ'], NominativoDalCodiceFiscale::ordina('JOSÈ', 'NIGRO', 'NGRJSO80A01H501X'));
    }
}
