<?php

namespace Tests\Feature\Warehouse;

use App\Models\Warehouse\InventoryIssuance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SignedWarehouseHandoverEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_unsigned_demo_issuances_do_not_count_as_signed_handovers(): void
    {
        $base = [
            'issue_no' => 'TEST-SWIS-01',
            'issued_to' => 'Maintenance',
            'purpose' => 'Item Request issuance',
            'reference_no' => 'IR-2026-0005',
            'issued_at' => now(),
        ];

        $legacy = InventoryIssuance::create($base);
        $missingConfirmation = InventoryIssuance::create(array_merge($base, [
            'issue_no' => 'TEST-SWIS-02',
            'reference_no' => 'IR-2026-0005-B2',
            'signed_slip_path' => 'warehouse/signed-ir-slips/unconfirmed.pdf',
            'received_by' => 'Mechanic',
        ]));
        $confirmed = InventoryIssuance::create(array_merge($base, [
            'issue_no' => 'TEST-SWIS-03',
            'reference_no' => 'IR-2026-0005-B3',
            'signed_slip_path' => 'warehouse/signed-ir-slips/signed.pdf',
            'received_by' => 'Mechanic',
            'handover_confirmed_at' => now(),
        ]));

        $signed = InventoryIssuance::query()->confirmedSigned()->pluck('id')->all();

        $this->assertSame([$confirmed->id], $signed);
        $this->assertDatabaseHas('inventory_issuances', ['id' => $legacy->id]);
        $this->assertDatabaseHas('inventory_issuances', ['id' => $missingConfirmation->id]);
        $this->assertSame(3, InventoryIssuance::count());
    }

    public function test_jo_quantity_requires_explicit_positive_quantity(): void
    {
        $parser = app(\App\Services\PartParser::class);
        $this->assertFalse($parser->hasExplicitQuantities('Brake Pad'));
        $this->assertFalse($parser->hasExplicitQuantities('Oil Filter - Qty: 0 pcs'));
        $this->assertFalse($parser->hasExplicitQuantities('Brake Pad - Qty: 4 pcs, Oil Filter'));
        $this->assertTrue($parser->hasExplicitQuantities('Brake Pad - Qty: 4 pcs'));
        $this->assertTrue($parser->hasExplicitQuantities('Oil Filter [item:4] - Qty: 10 pcs'));
        $this->assertTrue($parser->hasExplicitQuantities('Engine Oil (12 liters), Wiper Blade 10 pcs'));
    }

    public function test_item_request_queries_use_signed_handover_scope_and_demo_is_excluded_from_ml(): void
    {
        $controller = File::get(app_path('Http/Controllers/Warehouse/ItemRequestController.php'));
        $pipeline = File::get(base_path('python_engine/inventory/training_data.py'));
        $browser = File::get(resource_path('js/Warehouse/item-requests.js'));

        $this->assertGreaterThanOrEqual(8, substr_count($controller, '->confirmedSigned()'));
        $this->assertStringContainsString("WHERE sm.source = 'app'", $pipeline);
        $this->assertStringContainsString("Signed SWIS history could not be verified.", $browser);
        $this->assertStringContainsString("Storage::disk('local')->exists(\$batch->signed_slip_path)", $controller);
        $this->assertStringContainsString("'signedSlipByRequest'", $controller);
        $this->assertStringContainsString("'signedSlipByRequest', 'incompleteIssuedIds'", $controller);

        $table = File::get(resource_path('views/Warehouse/item-requests.blade.php'));
        $this->assertStringContainsString("data-ir-signed-slip-url=\"{{ \$signedSlipByRequest[\$ir->id] ?? '' }}\"", $table);
        $this->assertStringNotContainsString("in_array(\$ir->status, ['Issued', 'Partially Issued'], true) ? route('warehouse.item-requests.signed-slip'", $table);
    }
}
