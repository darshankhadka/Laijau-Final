<?php

namespace Tests\Feature;

use Livewire\Mechanisms\HandleRequests\EndpointResolver;
use Tests\TestCase;

class LivewireProductionAssetTest extends TestCase
{
    /**
     * Test 1: Exact dynamic Livewire script route returns 200 with javascript content type.
     */
    public function test_dynamic_livewire_script_route_returns_200(): void
    {
        $scriptPath = EndpointResolver::scriptPath();
        $response = $this->get($scriptPath);

        $response->assertStatus(200);
        $this->assertStringContainsString('application/javascript', $response->headers->get('Content-Type'));
        $this->assertTrue($response->headers->has('Content-Length') || $response->headers->has('Cache-Control'));
    }

    /**
     * Test 2: Standard Livewire compatibility path /livewire/livewire.js returns 200.
     */
    public function test_standard_livewire_compatibility_path_returns_200(): void
    {
        $response = $this->get('/livewire/livewire.js');

        $response->assertStatus(200);
        $this->assertStringContainsString('application/javascript', $response->headers->get('Content-Type'));
        $this->assertTrue($response->headers->has('Content-Length') || $response->headers->has('Cache-Control'));
    }

    /**
     * Test 3: Filament Admin Login page loads with 200 and contains valid Livewire script tag.
     */
    public function test_filament_admin_login_page_renders_valid_livewire_script(): void
    {
        $response = $this->get('/intadmin/login');

        $response->assertStatus(200);
        $this->assertTrue(
            str_contains($response->getContent(), 'livewire.js') ||
            str_contains($response->getContent(), 'livewire.min.js')
        );
    }

    /**
     * Test 4: .htaccess file contains exclusion for Livewire routes.
     */
    public function test_htaccess_excludes_livewire_from_static_404_rule(): void
    {
        $htaccess = file_get_contents(public_path('.htaccess'));

        $this->assertStringContainsString('RewriteCond %{REQUEST_URI} !^/livewire', $htaccess);
    }
}
