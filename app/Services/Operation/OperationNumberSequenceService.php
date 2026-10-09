<?php

namespace App\Services\Operation;

use Illuminate\Support\Facades\DB;

class OperationNumberSequenceService
{
    public function next(string $key, int $existingMaximum = 0): int
    {
        DB::table('operation_number_sequences')->insertOrIgnore([
            'sequence_key' => $key,
            'next_number' => $existingMaximum + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sequence = DB::table('operation_number_sequences')
            ->where('sequence_key', $key)
            ->lockForUpdate()
            ->first();

        $number = max((int) $sequence->next_number, $existingMaximum + 1);

        DB::table('operation_number_sequences')
            ->where('sequence_key', $key)
            ->update([
                'next_number' => $number + 1,
                'updated_at' => now(),
            ]);

        return $number;
    }
}
