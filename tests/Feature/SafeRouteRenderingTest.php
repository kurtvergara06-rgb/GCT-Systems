<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SafeRouteRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_trip_records_page_ignores_an_unavailable_sidebar_destination(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('trip-records'))
            ->assertOk();
    }
}
