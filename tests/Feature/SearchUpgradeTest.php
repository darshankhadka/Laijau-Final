<?php

namespace Tests\Feature;

use App\Models\Product;
use Tests\TestCase;

class SearchUpgradeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql']);
        config(['database.connections.mysql.database' => 'laijau_staging']);
        config(['database.connections.mysql.host' => '127.0.0.1']);
        config(['database.connections.mysql.username' => 'darshan']);
        config(['database.connections.mysql.password' => 'Dars@@9861']);
        \Illuminate\Support\Facades\DB::purge('mysql');
        \Illuminate\Support\Facades\DB::reconnect('mysql');
    }

    public function test_tokenized_retail_search_matches_multi_word_model(): void
    {
        $matches = Product::searchRetail('2244 black')->get();
        $this->assertGreaterThan(0, $matches->count());
        $this->assertTrue($matches->contains(fn($p) => str_contains(strtolower($p->name), '2244') || str_contains(strtolower($p->sku), '2244')));
    }

    public function test_storefront_search_matches_reversed_and_spaced_terms(): void
    {
        $blackBoots = Product::storefrontReady()->searchRetail('black boots')->get();
        $this->assertGreaterThan(0, $blackBoots->count());

        $drMartens = Product::storefrontReady()->searchRetail('dr martens')->get();
        $this->assertGreaterThan(0, $drMartens->count());
    }

    public function test_search_matches_variant_attributes_like_size_and_color(): void
    {
        $loaferSize40 = Product::storefrontReady()->searchRetail('loafer 40')->get();
        $this->assertGreaterThan(0, $loaferSize40->count());
    }

    public function test_storefront_search_page_loads_with_tokenized_results(): void
    {
        $response = $this->get('/search?q=2244+black');
        $response->assertStatus(200);
        $response->assertSee('2244');
    }

    public function test_search_suggestions_api_returns_tokenized_matches(): void
    {
        $response = $this->getJson('/api/search/suggestions?q=2244+black');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'query',
            'popular_searches',
            'categories',
            'products',
            'total_products_count',
        ]);
        $response->assertJsonPath('total_products_count', 1);
        $this->assertNotEmpty($response->json('products'));
    }
}
