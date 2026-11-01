<?php

namespace Tests\Feature;

use Tests\TestCase;

class PrototypeRouteTest extends TestCase
{
    public function test_operator_prototype_is_available_for_local_design_review(): void
    {
        $this->get('/prototype/operator')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('operator-prototype')
            ->assertSee('noindex, nofollow');
    }

    public function test_operator_prototype_is_not_available_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->get('/prototype/operator')->assertNotFound();
    }
}
