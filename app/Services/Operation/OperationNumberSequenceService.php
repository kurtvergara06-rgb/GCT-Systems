<?php

namespace App\Services\Operation;

use Illuminate\Support\Facades\DB;

class OperationNumberSequenceService
{
    public function next(string $key, int $existingMaximum = 0): int
    {
        $minimum = $existingMaximum + 1;
        $timestamp = now();

        // LAST_INSERT_ID(expr) is connection-scoped. This single statement either
        // creates the counter at the historical floor or increments it atomically,
        // without holding a SELECT ... FOR UPDATE lock while the caller creates its
        // parent and child records.
        DB::statement(
            <<<'SQL'
                INSERT INTO operation_number_sequences
                    (sequence_key, next_number, created_at, updated_at)
                VALUES (?, LAST_INSERT_ID(?), ?, ?)
                ON DUPLICATE KEY UPDATE
                    next_number = LAST_INSERT_ID(GREATEST(next_number + 1, ?)),
                    updated_at = ?
            SQL,
            [$key, $minimum, $timestamp, $timestamp, $minimum, $timestamp]
        );

        return (int) DB::scalar('SELECT LAST_INSERT_ID()');
    }
}
