<?php

namespace Tests\Unit;

use App\Services\CodiceFiscaleDecoder;
use Carbon\Carbon;
use Tests\TestCase;

class CodiceFiscaleDecoderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-06');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_decodes_a_man_born_in_a_municipality(): void
    {
        $data = (new CodiceFiscaleDecoder)->decode('RSSMRA80A01H501U');

        $this->assertSame('1980-01-01', $data['birth_date']->toDateString());
        $this->assertSame('M', $data['sex']);
        $this->assertSame('Roma (RM)', $data['birth_place']);
    }

    public function test_decodes_a_woman_by_the_day_offset(): void
    {
        $data = (new CodiceFiscaleDecoder)->decode('BNCLRA85M41F839X');

        $this->assertSame('1985-08-01', $data['birth_date']->toDateString());
        $this->assertSame('F', $data['sex']);
        $this->assertSame('Napoli (NA)', $data['birth_place']);
    }

    public function test_picks_the_century_that_is_not_in_the_future(): void
    {
        $decoder = new CodiceFiscaleDecoder;

        $this->assertSame('2005-03-15', $decoder->decode('RSSMRA05C15H501X')['birth_date']->toDateString());
        $this->assertSame('2026-03-15', $decoder->decode('RSSMRA26C15H501X')['birth_date']->toDateString());
        $this->assertSame('1926-12-15', $decoder->decode('RSSMRA26T15H501X')['birth_date']->toDateString());
    }

    public function test_handles_omocodia(): void
    {
        $data = (new CodiceFiscaleDecoder)->decode('RSSMRAULALMHRLMU');

        $this->assertSame('1980-01-01', $data['birth_date']->toDateString());
        $this->assertSame('M', $data['sex']);
        $this->assertSame('Roma (RM)', $data['birth_place']);
    }

    public function test_foreign_and_unknown_places_get_a_placeholder(): void
    {
        $decoder = new CodiceFiscaleDecoder;

        $this->assertSame('Estero (Z129)', $decoder->decode('RSSMRA80A01Z129U')['birth_place']);
        $this->assertSame('Comune non identificato (H000)', $decoder->decode('RSSMRA80A01H000U')['birth_place']);
    }

    public function test_rejects_invalid_codes(): void
    {
        $decoder = new CodiceFiscaleDecoder;

        $this->assertNull($decoder->decode(null));
        $this->assertNull($decoder->decode(''));
        $this->assertNull($decoder->decode('03562440986'));
        $this->assertNull($decoder->decode('RSSMRA80A41H501UX'));
        $this->assertNull($decoder->decode('RSSMRA80B30H501U'));
        $this->assertNull($decoder->decode('RSSMRA80X01H501U'));
    }

    public function test_accepts_lowercase_and_padding(): void
    {
        $this->assertSame('M', (new CodiceFiscaleDecoder)->decode(' rssmra80a01h501u ')['sex']);
    }

    public function test_checksum_is_verified(): void
    {
        $decoder = new CodiceFiscaleDecoder;

        $this->assertTrue($decoder->hasValidChecksum('RSSMRA80A01H501U'));
        $this->assertTrue($decoder->hasValidChecksum(' meopgs64d04f839i '));
        $this->assertFalse($decoder->hasValidChecksum('RSSMRA80A01H501X'));
        $this->assertFalse($decoder->hasValidChecksum('12345678901'));
        $this->assertFalse($decoder->hasValidChecksum(null));
    }

    public function test_decode_verified_returns_data_only_with_a_correct_checksum(): void
    {
        $decoder = new CodiceFiscaleDecoder;

        $this->assertSame('1964-04-04', $decoder->decodeVerified('MEOPGS64D04F839I')['birth_date']->toDateString());
        $this->assertNull($decoder->decodeVerified('MEOPGS64D04F839X'));
    }
}
