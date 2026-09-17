<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RealDataMigrationCommandsTest extends TestCase
{
    public function test_data_audit_command_executes_successfully(): void
    {
        $this->artisan('laijau:data-audit')
            ->assertSuccessful();

        $this->assertTrue(File::exists(storage_path('app/migration/audit_report.json')));
    }

    public function test_data_dry_run_command_executes_cleanly(): void
    {
        if (!File::exists(storage_path('app/migration/real_data_extracted/users.json'))) {
            $this->markTestSkipped('Raw extraction dataset not present in local test environment.');
        }

        $this->artisan('laijau:data-dry-run')
            ->assertSuccessful();
    }

    public function test_data_reconcile_command_verifies_zero_variance(): void
    {
        if (!File::exists(storage_path('app/migration/real_data_extracted/users.json'))) {
            $this->markTestSkipped('Raw extraction dataset not present in local test environment.');
        }

        $this->artisan('laijau:data-reconcile')
            ->assertSuccessful();

        $this->assertTrue(File::exists(storage_path('app/migration/reconciliation_report.json')));
    }
}
