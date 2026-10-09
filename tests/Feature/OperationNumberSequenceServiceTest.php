<?php

namespace Tests\Feature;

use App\Services\Operation\OperationNumberSequenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationNumberSequenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_atomically_reserves_numbers_above_the_current_sequence_and_historical_floor(): void
    {
        $service = app(OperationNumberSequenceService::class);

        $this->assertSame(10, $service->next('test:operation', 9));
        $this->assertSame(11, $service->next('test:operation', 9));
        $this->assertSame(21, $service->next('test:operation', 20));
        $this->assertSame(22, $service->next('test:operation', 3));

        $this->assertSame(
            22,
            (int) DB::table('operation_number_sequences')
                ->where('sequence_key', 'test:operation')
                ->value('next_number')
        );
    }
}
