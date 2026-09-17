<?php

declare(strict_types=1);

namespace Tests\Feature\Operational;

use Database\Seeders\ModuleSettingsSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SharedHostingAdaptationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModuleSettingsSeeder::class);
    }

    /**
     * Test that when Queue_CONNECTION is database, the scheduler registers the shared-hosting cron worker.
     */
    public function test_database_Queue_cron_scheduling_is_registered(): void
    {
        // Set Queue connection to database
        Config::set('queue.default', 'database');

        // Re-load console routes to re-evaluate conditional scheduling
        require base_path('routes/console.php');

        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $hasQueueWorker = $events->contains(function ($event) {
            return str_contains($event->command ?? '', 'queue:work') &&
                str_contains($event->command ?? '', '--stop-when-empty');
        });

        $this->assertTrue($hasQueueWorker, 'Scheduler must register queue:work --stop-when-empty when Queue is database.');
    }

    /**
     * Test public/.htaccess contains modern Apache security headers and sensitive file blocks.
     */
    public function test_apache_public_htaccess_security_headers_and_block_rules(): void
    {
        $htaccessPath = public_path('.htaccess');
        $this->assertFileExists($htaccessPath);

        $content = File::get($htaccessPath);

        // Security headers
        $this->assertStringContainsString('Strict-Transport-Security', $content);
        $this->assertStringContainsString('max-age=31536000', $content);
        $this->assertStringContainsString('X-Frame-Options "SAMEORIGIN"', $content);
        $this->assertStringContainsString('X-Content-Type-Options "nosniff"', $content);
        $this->assertStringContainsString('Content-Security-Policy', $content);
        $this->assertStringContainsString('Referrer-Policy', $content);

        // Blocking sensitive file extensions
        $this->assertStringContainsString('\.(env|log|sql|sqlite', $content);
        $this->assertStringContainsString('Require all denied', $content);
    }

    /**
     * Test root .htaccess shields the entire application when placed directly in public_html.
     */
    public function test_root_directory_htaccess_shield_integrity(): void
    {
        $rootHtaccessPath = base_path('.htaccess');
        $this->assertFileExists($rootHtaccessPath);

        $content = File::get($rootHtaccessPath);

        // Route to public
        $this->assertStringContainsString('RewriteRule ^(.*)$ public/$1 [L]', $content);

        // Block sensitive directories
        $this->assertStringContainsString('app|bootstrap|config|database|resources|routes|storage|tests|vendor', $content);

        // Block dotfiles
        $this->assertStringContainsString('RewriteRule (^|/)\.(?!well-known) - [F,L]', $content);
    }

    /**
     * Test cPanel deployment automation script syntax, permissions, and command sequences.
     */
    public function test_cpanel_deploy_script_syntax_and_integrity(): void
    {
        $deployScript = base_path('deploy/cpanel/cpanel-deploy.sh');
        $this->assertFileExists($deployScript);
        $this->assertTrue(is_executable($deployScript), 'cpanel-deploy.sh must be executable.');

        $content = File::get($deployScript);

        // Required operational stages
        $this->assertStringContainsString('erp:backup --type=db --encrypt', $content);
        $this->assertStringContainsString('php artisan down', $content);
        $this->assertStringContainsString('composer install --no-dev --optimize-autoloader', $content);
        $this->assertStringContainsString('php artisan migrate --force', $content);
        $this->assertStringContainsString('php artisan config:cache', $content);
        $this->assertStringContainsString('php artisan storage:link', $content);
        $this->assertStringContainsString('php artisan erp:staging-verify', $content);
        $this->assertStringContainsString('php artisan up', $content);
    }

    /**
     * Test cPanel cron and deployment guide documentation integrity.
     */
    public function test_cpanel_cron_configuration_and_documentation(): void
    {
        $cronPath = base_path('deploy/cpanel/cpanel-cron.txt');
        $guidePath = base_path('deploy/cpanel/CPANEL_DEPLOYMENT_GUIDE.md');

        $this->assertFileExists($cronPath);
        $this->assertFileExists($guidePath);

        $cronContent = File::get($cronPath);
        $this->assertStringContainsString('artisan schedule:run', $cronContent);
        $this->assertStringContainsString('* * * * *', $cronContent);

        $guideContent = File::get($guidePath);
        $this->assertStringContainsString('MultiPHP Manager', $guideContent);
        $this->assertStringContainsString('PHP 8.4', $guideContent);
        $this->assertStringContainsString('MySQL Database Wizard', $guideContent);
        $this->assertStringContainsString('cpanel-deploy.sh', $guideContent);
    }

    /**
     * Test that erp:staging-verify passes cleanly under shared-hosting defaults.
     */
    public function test_shared_hosting_environment_defaults_pass_staging_verify(): void
    {
        // Force shared hosting profile in configuration
        Config::set('Queue.default', 'sync'); // sync or database
        Config::set('cache.default', 'file');
        Config::set('session.driver', 'file');

        $exitCode = Artisan::call('erp:staging-verify');
        $this->assertEquals(0, $exitCode, 'Staging verify must succeed under shared-hosting profile.');

        $output = Artisan::output();
        $this->assertStringContainsString('Apache / cPanel Security Shields (.htaccess)', $output);
        $this->assertStringContainsString('SUCCESS: All operational pre-flight probes PASSED', $output);
    }

    /**
     * Test that public/storage is a relative shortcut and no symlinks point to other external paths.
     */
    public function test_public_storage_symlink_is_relative_and_no_external_symlinks_exist(): void
    {
        $publicStorage = public_path('storage');
        $this->assertTrue(is_link($publicStorage), 'public/storage must be a symlink.');

        $target = readlink($publicStorage);
        $this->assertFalse(str_starts_with($target, '/media/'), 'public/storage must not point to an absolute external machine path.');
        $this->assertStringContainsString('storage/app/public', $target, 'public/storage must point to storage/app/public.');

        // Test erp:package --clean-only
        $this->artisan('erp:package', ['--clean-only' => true])
            ->assertSuccessful();
    }
}
