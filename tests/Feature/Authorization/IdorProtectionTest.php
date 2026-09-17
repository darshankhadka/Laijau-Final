<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Models\Accounting\JournalEntry;
use App\Models\Hrm\Employee;
use App\Models\Hrm\ExpenseClaim;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IdorProtectionTest extends TestCase
{
    use DatabaseTransactions;

    protected User $superAdmin;
    protected User $workspaceAdmin;
    protected User $storeManager;
    protected User $accountant;
    protected User $hrManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->superAdmin = User::factory()->create([
            'email' => 'superadmin_' . uniqid() . '@example.com',
            'role' => 'super_admin',
        ]);
        $this->superAdmin->assignRole(Role::where('name', 'Super Admin')->where('guard_name', 'admin')->first());

        $this->workspaceAdmin = User::factory()->create([
            'email' => 'wsadmin_' . uniqid() . '@example.com',
            'role' => 'workspace_admin',
        ]);
        $this->workspaceAdmin->assignRole(Role::where('name', 'Workspace Admin')->where('guard_name', 'admin')->first());

        $this->storeManager = User::factory()->create([
            'email' => 'sm_' . uniqid() . '@example.com',
            'role' => 'store_manager',
        ]);
        $this->storeManager->assignRole(Role::where('name', 'Store Manager')->where('guard_name', 'admin')->first());

        $this->accountant = User::factory()->create([
            'email' => 'acc_' . uniqid() . '@example.com',
            'role' => 'accountant',
        ]);
        $this->accountant->assignRole(Role::where('name', 'Accountant')->where('guard_name', 'admin')->first());

        $this->hrManager = User::factory()->create([
            'email' => 'hrm_' . uniqid() . '@example.com',
            'role' => 'hr_manager',
        ]);
        $this->hrManager->assignRole(Role::where('name', 'HR / Payroll Manager')->where('guard_name', 'admin')->first());
    }

    public function test_non_super_admin_cannot_update_or_delete_super_admin(): void
    {
        // Workspace Admin cannot update Super Admin
        $this->assertFalse(Gate::forUser($this->workspaceAdmin)->allows('update', $this->superAdmin));
        $this->assertFalse(Gate::forUser($this->workspaceAdmin)->allows('delete', $this->superAdmin));

        // Store Manager cannot update or delete Super Admin
        $this->assertFalse(Gate::forUser($this->storeManager)->allows('update', $this->superAdmin));
        $this->assertFalse(Gate::forUser($this->storeManager)->allows('delete', $this->superAdmin));

        // Super Admin can update other admins
        $this->assertTrue(Gate::forUser($this->superAdmin)->allows('update', $this->workspaceAdmin));
    }

    public function test_user_cannot_delete_themselves(): void
    {
        // Non-super-admin cannot delete self
        $this->assertFalse(Gate::forUser($this->workspaceAdmin)->allows('delete', $this->workspaceAdmin));
        $this->assertFalse(Gate::forUser($this->storeManager)->allows('delete', $this->storeManager));

        // Super admin cannot delete own active account to prevent lockout
        $this->assertFalse(Gate::forUser($this->superAdmin)->allows('delete', $this->superAdmin));
    }

    public function test_claimant_cannot_approve_their_own_expense_claim(): void
    {
        $employee = Employee::create([
            'user_id' => $this->hrManager->id,
            'employee_number' => 'MED-TEST-' . uniqid(),
            'first_name' => 'HR',
            'last_name' => 'Manager',
            'email' => $this->hrManager->email,
            'hire_date' => now()->toDateString(),
            'status' => 'active',
            'hourly_rate_npr' => 250.00,
        ]);

        $claim = ExpenseClaim::create([
            'employee_id' => $employee->id,
            'claim_number' => 'UDL-TEST-' . uniqid(),
            'title' => 'Client Lunch Meeting',
            'amount_npr' => 450.00,
            'expense_date' => now()->toDateString(),
            'status' => 'submitted',
        ]);

        // HR Manager is an allowed approver for ExpenseClaim, but NOT for their own claim!
        $this->assertFalse(Gate::forUser($this->hrManager)->allows('approve', $claim));

        // Another approver (Accountant or Super Admin) can approve it
        $this->assertTrue(Gate::forUser($this->accountant)->allows('approve', $claim));
        $this->assertTrue(Gate::forUser($this->superAdmin)->allows('approve', $claim));
    }

    public function test_unauthorized_user_cannot_post_or_reverse_journal_entries(): void
    {
        $this->assertFalse(Gate::forUser($this->storeManager)->allows('post', JournalEntry::class));
        $this->assertFalse(Gate::forUser($this->storeManager)->allows('reverse', JournalEntry::class));

        $this->assertTrue(Gate::forUser($this->accountant)->allows('post', JournalEntry::class));
        $this->assertTrue(Gate::forUser($this->accountant)->allows('reverse', JournalEntry::class));
    }
}
