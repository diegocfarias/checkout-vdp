<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancellationPolicyPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_cancellation_policy_page_is_available(): void
    {
        $this->get(route('cancellation-policy'))
            ->assertOk()
            ->assertSee('Política de cancelamento e reembolso')
            ->assertSee('Cancelamento sem custo')
            ->assertSee('Como solicitar');
    }
}
