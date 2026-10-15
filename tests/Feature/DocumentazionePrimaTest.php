<?php

namespace Tests\Feature;

use App\Filament\Agenti\Resources\Clients\ClientResource as ClientiAgenti;
use App\Filament\Agenti\Resources\Pratiche\PraticaResource as PraticaAgenti;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Istituti\IstitutoResource;
use App\Filament\Resources\Praticas\PraticaResource;
use App\Filament\Resources\Produttori\ProduttoreResource;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Unico\Core\Filament\RelationManagers\DocumentsRelationManager;

class DocumentazionePrimaTest extends TestCase
{
    /** La documentazione è il fulcro dell'istruttoria: in ogni risorsa con documenti è la prima scheda. */
    #[DataProvider('risorse')]
    public function test_documents_are_the_first_relation_manager(string $resource): void
    {
        $this->assertSame(DocumentsRelationManager::class, $resource::getRelations()[0]);
    }

    /** @return array<string, array{0: class-string}> */
    public static function risorse(): array
    {
        return [
            'pratiche admin' => [PraticaResource::class],
            'pratiche agenti' => [PraticaAgenti::class],
            'clienti' => [ClientResource::class],
            'clienti agenti' => [ClientiAgenti::class],
            'istituti' => [IstitutoResource::class],
            'produttori' => [ProduttoreResource::class],
        ];
    }
}
