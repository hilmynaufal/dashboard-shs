<?php

namespace Tests\Feature;

use Database\Seeders\ReferenceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReferenceCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_catalog_idempotently_with_hierarchy(): void
    {
        $this->seed(ReferenceCatalogSeeder::class);
        $this->seed(ReferenceCatalogSeeder::class);

        $this->assertSame(459, DB::table('item_classifications')->count());
        $this->assertSame(101, DB::table('units')->count());
        $this->assertSame(435, DB::table('account_codes')->count());
        $child = DB::table('item_classifications')->where('code', '1.1.12.01')->first();
        $this->assertSame(DB::table('item_classifications')->where('code', '1.1.12')->value('id'), $child->parent_id);
        $this->assertSame('5.1.02.01.01.0001', DB::table('account_codes')->where('code', '5.1.02.01.01.0001')->value('code'));
    }
}
