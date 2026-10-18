<?php

namespace App\Services\Bpm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * Espone la struttura di un modello (colonne con i commenti reali di MySQL e relazioni belongsTo trattate come «lookup») a
 * unicobpm, che non ha accesso a questi modelli e la usa per le select di configurazione dei processi e per scrivere un campo a
 * fine processo. I modelli esposti sono in `bpm.models`; i campi che non si scrivono in `bpm.campi_non_scrivibili`.
 * Stesso contratto che unicobpm usava su unicoloan.
 */
class ModelFieldIntrospector
{
    /** @return class-string<Model> */
    public function resolveModelClass(string $modelType): string
    {
        return config('bpm.models.'.$modelType) ?? throw new InvalidArgumentException("Modello sconosciuto: {$modelType}");
    }

    /**
     * @return array<int, array{name: string, type: string, comment: string|null, nullable: bool}>
     */
    public function columns(string $modelType): array
    {
        return $this->columnsForClass($this->resolveModelClass($modelType));
    }

    /** Il campo si può scrivere con la scrittura generica: esiste e non è fra quelli riservati. */
    public function isWritable(string $modelType, string $field): bool
    {
        $reserved = array_merge(config('bpm.campi_non_scrivibili.*', []), config('bpm.campi_non_scrivibili.'.$modelType, []));

        return ! in_array($field, $reserved, true);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<int, array{name: string, type: string, comment: string|null, nullable: bool}>
     */
    private function columnsForClass(string $modelClass): array
    {
        $model = new $modelClass;
        $connection = $model->getConnectionName() ?? config('database.default');
        $table = $model->getTable();
        $table = str_contains($table, '.') ? substr($table, strpos($table, '.') + 1) : $table;

        $rows = DB::connection($connection)->select(
            'select column_name as `name`, column_type as `type`, column_comment as `comment`, is_nullable as `nullable`
             from information_schema.columns
             where table_schema = ? and table_name = ?
             order by ordinal_position',
            [DB::connection($connection)->getDatabaseName(), $table]
        );

        return collect($rows)->map(fn (object $row): array => [
            'name' => $row->name,
            'type' => $row->type,
            'comment' => $row->comment !== '' ? $row->comment : null,
            'nullable' => $row->nullable === 'YES',
        ])->all();
    }

    /**
     * Relazioni belongsTo del modello: scrivere sulla `foreign_key` punta a un record del modello correlato, identificato
     * dalla sua `owner_key`.
     *
     * @return array<int, array{relation: string, foreign_key: string, owner_key: string, related_model: string}>
     */
    public function lookups(string $modelType): array
    {
        $modelClass = $this->resolveModelClass($modelType);
        $model = new $modelClass;
        $lookups = [];

        foreach ((new ReflectionClass($modelClass))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getNumberOfParameters() > 0 || $method->isStatic() || ! $this->declaresRelation($method)) {
                continue;
            }

            try {
                $result = $method->invoke($model);
            } catch (Throwable) {
                continue;
            }

            if (! $result instanceof BelongsTo) {
                continue;
            }

            $lookups[] = [
                'relation' => $method->getName(),
                'foreign_key' => $result->getForeignKeyName(),
                'owner_key' => $result->getOwnerKeyName(),
                'related_model' => array_search(get_class($result->getRelated()), config('bpm.models'), true) ?: get_class($result->getRelated()),
            ];
        }

        return $lookups;
    }

    /**
     * Se `field` è la foreign key di una relazione belongsTo, risolve $value nel valore da scrivere: prima sulla owner key,
     * poi sulle colonne «display» più comuni (name, nome, codice, code). Altrimenti $value resta com'è.
     *
     * @throws InvalidArgumentException se il campo è un lookup ma il valore non si risolve
     */
    public function resolveFieldValue(string $modelType, string $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $lookup = collect($this->lookups($modelType))->firstWhere('foreign_key', $field);

        if (! $lookup) {
            return $value;
        }

        $relatedClass = config('bpm.models.'.$lookup['related_model']) ?? $lookup['related_model'];
        $ownerKey = $lookup['owner_key'];
        $existingColumns = collect($this->columnsForClass($relatedClass))->pluck('name');

        foreach (collect([$ownerKey, 'name', 'nome', 'codice', 'code'])->unique()->filter(fn (string $column): bool => $existingColumns->contains($column)) as $column) {
            $matches = (new $relatedClass)->newQuery()->where($column, $value)->limit(2)->get();

            if ($matches->count() === 1) {
                return $matches->first()->{$ownerKey};
            }

            if ($matches->isNotEmpty()) {
                throw new InvalidArgumentException("Valore '{$value}' ambiguo per il campo lookup '{$field}' su {$modelType}.");
            }
        }

        throw new InvalidArgumentException("Impossibile risolvere il valore '{$value}' per il campo lookup '{$field}' su {$modelType}.");
    }

    /** Solo i metodi con tipo di ritorno di relazione: invocare qualsiasi metodo pubblico potrebbe avere effetti. */
    private function declaresRelation(ReflectionMethod $method): bool
    {
        $type = $method->getReturnType();

        return $type instanceof \ReflectionNamedType && is_a($type->getName(), BelongsTo::class, true);
    }
}
