<?php
namespace App\Contracts;

use App\Models\TaskExecution;

interface BpmAction
{
    /**
     * @param TaskExecution $execution L'esecuzione corrente
     * @param array $params Parametri opzionali
     */
    public function execute(TaskExecution $execution, array $params = []): void;
}
