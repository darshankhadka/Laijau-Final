<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Filament\Pages\AccountingDashboard;
use App\Filament\Pages\BankReconciliationPage;
use App\Filament\Pages\HrmDashboard;
use App\Filament\Pages\InventoryDashboard;
use App\Filament\Pages\OfflineSales;
use App\Models\Accounting\JournalEntry;
use App\Models\Hrm\Employee;
use App\Models\Hrm\PayrollRun;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Panel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleHierarchyTest extends TestCase
{
    use DatabaseTransactions;

    protected User $superAdmin;
    protected User $storeManager;
    protected User $accountant;
    protected User $hrManager;
    protected User $warehouseManager;
    protected User $cashier;
    protected User $salesRep;
    protected User $viewer;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        // Super Admin
        $this->superAdmin = User::factory()->create([
            'email' => 'superadmin_' . uniqid() . '@example.com',
            'role' => 'super_admin',
        ]);
        $this->superAdmin->assignRole(Role::where('name', 'Super Admin')->where('guard_name', 'admin')->first());

        // Store Manager
        $this->storeManager = User::factory()->create([
            'email' => 'storemanager_' . uniqid() . '@example.com',
            'role' => 'store_manager',
        ]);
        $this->storeManager->assignRole(Role::where('name', 'Store Manager')->where('guard_name', 'admin')->first());

        // Accountant
        $this->accountant = User::factory()->create([
            'email' => 'accountant_' . uniqid() . '@example.com',
            'role' => 'accountant',
        ]);
        $this->accountant->assignRole(Role::where('name', 'Accountant')->where('guard_name', 'admin')->first());

        // HR Manager
        $this->hrManager = User::factory()->create([
            'email' => 'hrmanager_' . uniqid() . '@example.com',
            'role' => 'hr_manager',
        ]);
        $this->hrManager->assignRole(Role::where('name', 'HR / Payroll Manager')->where('guard_name', 'admin')->first());

        // Warehouse Manager
        $this->warehouseManager = User::factory()->create([
            'email' => 'warehouse_' . uniqid() . '@example.com',
            'role' => 'warehouse_manager',
        ]);
        $this->warehouseManager->assignRole(Role::where('name', 'Warehouse Manager')->where('guard_name', 'admin')->first());

        // Cashier
        $this->cashier = User::factory()->create([
            'email' => 'cashier_' . uniqid() . '@example.com',
            'role' => 'cashier',
        ]);
        $this->cashier->assignRole(Role::where('name', 'Cashier')->where('guard_name', 'admin')->first());

        // Sales Representative
        $this->salesRep = User::factory()->create([
            'email' => 'salesrep_' . uniqid() . '@example.com',
            'role' => 'sales_rep',
        ]);
        $this->salesRep->assignRole(Role::where('name', 'Sales Representative')->where('guard_name', 'admin')->first());

        // Viewer
        $this->viewer = User::factory()->create([
            'email' => 'viewer_' . uniqid() . '@example.com',
            'role' => 'viewer',
        ]);
        $this->viewer->assignRole(Role::where('name', 'Viewer')->where('guard_name', 'admin')->first());

        // Customer
        $this->customer = User::factory()->create([
            'email' => 'customer_' . uniqid() . '@example.com',
            'role' => 'customer',
        ]);
        $this->customer->assignRole(Role::where('name', 'Customer')->where('guard_name', 'web')->first());
    }

    public function test_super_admin_has_universal_access(): void
    {
        $this->assertTrue(Gate::forUser($this->superAdmin)->allows('viewAny', JournalEntry::class));
        $this->assertTrue(Gate::forUser($this->superAdmin)->allows('create', JournalEntry::class));
        $this->assertTrue(Gate::forUser($this->superAdmin)->allows('viewAny', PayrollRun::class));
        $this->assertTrue(Gate::forUser($this->superAdmin)->allows('create', Product::class));
        $this->assertTrue(Gate::forUser($this->superAdmin)->allows('delete', Order::class));
    }

    public function test_store_manager_can_manage_commerce_but_forbidden_on_accounting_and_payroll(): void
    {
        // Allowed: Commerce
        $this->assertTrue(Gate::forUser($this->storeManager)->allows('viewAny', Product::class));
        $this->assertTrue(Gate::forUser($this->storeManager)->allows('create', Product::class));
        $this->assertTrue(Gate::forUser($this->storeManager)->allows('viewAny', Order::class));

        // Forbidden: Accounting & Payroll
        $this->assertFalse(Gate::forUser($this->storeManager)->allows('viewAny', JournalEntry::class));
        $this->assertFalse(Gate::forUser($this->storeManager)->allows('create', JournalEntry::class));
        $this->assertFalse(Gate::forUser($this->storeManager)->allows('viewAny', PayrollRun::class));
        $this->assertFalse(Gate::forUser($this->storeManager)->allows('create', PayrollRun::class));

        // Pages
        $this->actingAs($this->storeManager, 'admin');
        $this->assertFalse(AccountingDashboard::canAccess());
        $this->assertFalse(BankReconciliationPage::canAccess());
        $this->assertFalse(HrmDashboard::canAccess());
    }

    public function test_accountant_can_manage_accounting_but_forbidden_on_catalog_mutation(): void
    {
        // Allowed: Accounting
        $this->assertTrue(Gate::forUser($this->accountant)->allows('viewAny', JournalEntry::class));
        $this->assertTrue(Gate::forUser($this->accountant)->allows('create', JournalEntry::class));
        $this->assertTrue(Gate::forUser($this->accountant)->allows('post', JournalEntry::class));

        // Forbidden: Product catalog mutation
        $this->assertFalse(Gate::forUser($this->accountant)->allows('create', Product::class));
        $this->assertFalse(Gate::forUser($this->accountant)->allows('delete', Product::class));

        // Forbidden: Payroll creation
        $this->assertFalse(Gate::forUser($this->accountant)->allows('create', PayrollRun::class));

        // Pages
        $this->actingAs($this->accountant, 'admin');
        $this->assertTrue(AccountingDashboard::canAccess());
        $this->assertTrue(BankReconciliationPage::canAccess());
        $this->assertFalse(HrmDashboard::canAccess());
    }

    public function test_hr_manager_can_manage_workforce_but_forbidden_on_accounting_and_catalog(): void
    {
        // Allowed: HRM
        $this->assertTrue(Gate::forUser($this->hrManager)->allows('viewAny', Employee::class));
        $this->assertTrue(Gate::forUser($this->hrManager)->allows('viewAny', PayrollRun::class));
        $this->assertTrue(Gate::forUser($this->hrManager)->allows('calculate', PayrollRun::class));

        // Forbidden: Accounting & Commerce
        $this->assertFalse(Gate::forUser($this->hrManager)->allows('viewAny', JournalEntry::class));
        $this->assertFalse(Gate::forUser($this->hrManager)->allows('create', Product::class));

        // Pages
        $this->actingAs($this->hrManager, 'admin');
        $this->assertTrue(HrmDashboard::canAccess());
        $this->assertFalse(AccountingDashboard::canAccess());
    }

    public function test_cashier_can_operate_pos_but_cannot_modify_products_or_accounting(): void
    {
        // Allowed: POS & viewing product
        $this->assertTrue(Gate::forUser($this->cashier)->allows('view', Product::class));

        // Forbidden: Product mutation & Accounting
        $this->assertFalse(Gate::forUser($this->cashier)->allows('create', Product::class));
        $this->assertFalse(Gate::forUser($this->cashier)->allows('delete', Product::class));
        $this->assertFalse(Gate::forUser($this->cashier)->allows('viewAny', JournalEntry::class));

        // Pages
        $this->actingAs($this->cashier, 'admin');
        $this->assertTrue(OfflineSales::canAccess());
        $this->assertFalse(AccountingDashboard::canAccess());
        $this->assertFalse(HrmDashboard::canAccess());
    }

    public function test_sales_representative_can_view_and_create_orders_but_cannot_delete(): void
    {
        $this->assertTrue(Gate::forUser($this->salesRep)->allows('view', Order::class));
        $this->assertTrue(Gate::forUser($this->salesRep)->allows('create', Order::class));
        $this->assertFalse(Gate::forUser($this->salesRep)->allows('delete', Order::class));
        $this->assertFalse(Gate::forUser($this->salesRep)->allows('viewAny', JournalEntry::class));
    }

    public function test_viewer_is_strictly_read_only(): void
    {
        // Allowed: View
        $this->assertTrue(Gate::forUser($this->viewer)->allows('viewAny', Product::class));
        $this->assertTrue(Gate::forUser($this->viewer)->allows('view', Product::class));

        // Forbidden: Create, Update, Delete
        $this->assertFalse(Gate::forUser($this->viewer)->allows('create', Product::class));
        $this->assertFalse(Gate::forUser($this->viewer)->allows('update', Product::class));
        $this->assertFalse(Gate::forUser($this->viewer)->allows('delete', Product::class));
    }

    public function test_customer_cannot_access_filament_admin_panel(): void
    {
        $panel = new Panel();
        $this->assertFalse($this->customer->canAccessPanel($panel));
    }
}
