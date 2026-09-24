<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;

class PredictiveInventoryModelPanelTest extends TestCase
{
    public function test_inventory_model_panel_renders_frontend_item_codes_and_demo_label(): void
    {
        $predictive = (object) [
            'inventory' => (object) [
                'rows' => collect([
                    [
                        'item_code' => 'DEMO-PART-001',
                        'name' => 'Brake Pad Set',
                    ],
                    [
                        'item_code' => 'DEMO-PART-002',
                        'name' => 'Oil Filter',
                    ],
                ]),
            ],
        ];

        $this->view('Admin.Analytics.predictive.inventory-ml', [
            'predictive' => $predictive,
        ])
            ->assertSee('Inventory Model #4')
            ->assertSee('Next-Week Demand Forecast')
            ->assertSee('DEMO-PART-001')
            ->assertSee('Brake Pad Set')
            ->assertSee('DEMO MODEL READY', false)
            ->assertSee('inventory-predictions', false)
            ->assertSee('const escapeHtml', false)
            ->assertSee('recommendedAction = escapeHtml', false);
    }
}
