<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guards = ['admin', 'web'];

        $entities = [
            'Accounting' => [
                'JournalEntry' => ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'Post', 'Reverse'],
                'AccountingInvoice' => ['ViewAny', 'View', 'Create', 'Update', 'Delete'],
                'Account' => ['ViewAny', 'View', 'Create', 'Update', 'Delete'],
            ],
            'HRM' => [
                'Employee' => ['ViewAny', 'View', 'Create', 'Update', 'Delete'],
                'PayrollRun' => ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'Calculate', 'Approve'],
                'ExpenseClaim' => ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'Approve'],
                'LeaveRequest' => ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'Approve'],
                'Timesheet' => ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'Approve'],
                'Department' => ['ViewAny', 'View', 'Create', 'Update', 'Delete'],
                'RecruitmentJob' => ['ViewAny', 'View', 'Create', 'Update', 'Delete'],
            ],
            'Inventory' => [
                'PurchaseOrder' => ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'Approve'],
                'StockAdjustment' => ['ViewAny', 'View', 'Create', 'Update', 'Delete'],
                'StockTransfer' => ['ViewAny', 'View', 'Create', 'Update', 'Delete'],
                'StockCount' => ['ViewAny', 'View', 'Create', 'Update', 'Delete'],
                'StockReservation' => ['ViewAny', 'View', 'Create', 'Update', 'Delete'],
                'StockLevel' => ['ViewAny', 'View', 'Create', 'Update', 'Delete'],
                'StockMovement' => ['ViewAny', 'View', 'Create', 'Update'],
                'Warehouse' => ['ViewAny', 'View', 'Create', 'Update', 'Delete'],
                'Supplier' => ['ViewAny', 'View', 'Create', 'Update', 'Delete'],
            ],
            'Commerce' => [
                'Order' => ['ViewAny', 'View', 'Create', 'Update', 'Delete'],
                'Product' => ['ViewAny', 'View', 'Create', 'Update', 'Delete'],
                'Category' => ['ViewAny', 'View', 'Create', 'Update', 'Delete'],
                'Collection' => ['ViewAny', 'View', 'Create', 'Update', 'Delete'],
                'Coupon' => ['ViewAny', 'View', 'Create', 'Update', 'Delete'],
                'ShippingMethod' => ['ViewAny', 'View', 'Create', 'Update', 'Delete'],
                'User' => ['ViewAny', 'View', 'Create', 'Update', 'Delete'],
                'CrmLead' => ['ViewAny', 'View', 'Create', 'Update', 'Delete'],
                'ContactMessage' => ['ViewAny', 'View', 'Create', 'Update', 'Delete'],
                'RestockRequest' => ['ViewAny', 'View', 'Create', 'Update', 'Delete'],
                'ProductAttribute' => ['ViewAny', 'View', 'Create', 'Update', 'Delete'],
                'OfflineSale' => ['ViewAny', 'View', 'Create', 'Update', 'Delete'],
            ],
        ];

        // 1. Create all permissions across both guards
        $allPermissions = [];
        foreach ($entities as $domain => $models) {
            foreach ($models as $model => $actions) {
                foreach ($actions as $action) {
                    $permName = "{$action}:{$model}";
                    $allPermissions[] = $permName;
                    foreach ($guards as $guard) {
                        Permission::firstOrCreate(['name' => $permName, 'guard_name' => $guard]);
                    }
                }
            }
        }

        // Page permissions
        $pages = [
            'page_AccountingDashboard',
            'page_BankReconciliationPage',
            'page_VatReportingPage',
            'page_HrmDashboard',
            'page_InventoryDashboard',
            'page_InventoryReportsPage',
            'page_InventoryValuationPage',
            'page_OfflineSales',
            'page_CrmKanban',
            'page_ManageSettings',
            'page_ModuleSettings',
        ];

        foreach ($pages as $page) {
            $allPermissions[] = $page;
            foreach ($guards as $guard) {
                Permission::firstOrCreate(['name' => $page, 'guard_name' => $guard]);
            }
        }

        // 2. Define Roles and assign permissions
        $roleDefinitions = [
            'Super Admin' => $allPermissions,
            'Workspace Admin' => $allPermissions,
            'Executive' => array_values(array_filter($allPermissions, function ($p) {
                // Full operational access across business domains except system settings and user security
                return !in_array($p, ['page_ManageSettings', 'page_ModuleSettings', 'Delete:User', 'Create:User', 'Update:User'], true);
            })),
            'Operations Manager' => array_values(array_filter(
                array_merge(
                    $this->getDomainPermissions($entities['Commerce']),
                    $this->getDomainPermissions($entities['Inventory']),
                    ['page_CrmKanban', 'page_OfflineSales', 'page_InventoryDashboard', 'page_InventoryReportsPage', 'page_InventoryValuationPage']
                ),
                fn($p) => !str_starts_with($p, 'Delete:') && !in_array($p, ['page_ManageSettings', 'page_ModuleSettings'], true)
            )),
            'Store Manager' => array_values(array_filter(
                array_merge(
                    $this->getDomainPermissions($entities['Commerce']),
                    ['ViewAny:StockLevel', 'View:StockLevel', 'ViewAny:StockMovement', 'View:StockMovement', 'ViewAny:StockReservation', 'View:StockReservation'],
                    ['page_CrmKanban', 'page_OfflineSales', 'page_InventoryDashboard', 'page_InventoryReportsPage', 'page_InventoryValuationPage']
                ),
                fn($p) => !str_starts_with($p, 'Delete:') && !in_array($p, ['page_ManageSettings', 'page_ModuleSettings'], true)
            )),
            'Cashier' => [
                'ViewAny:OfflineSale', 'View:OfflineSale', 'Create:OfflineSale', 'Update:OfflineSale',
                'ViewAny:Product', 'View:Product',
                'ViewAny:StockLevel', 'View:StockLevel',
                'page_OfflineSales',
            ],
            'Accountant' => array_merge(
                $this->getDomainPermissions($entities['Accounting']),
                ['ViewAny:Order', 'View:Order', 'ViewAny:PurchaseOrder', 'View:PurchaseOrder', 'ViewAny:OfflineSale', 'View:OfflineSale'],
                ['page_AccountingDashboard', 'page_BankReconciliationPage', 'page_VatReportingPage', 'page_InventoryValuationPage', 'page_InventoryReportsPage']
            ),
            'HR / Payroll Manager' => array_merge(
                $this->getDomainPermissions($entities['HRM']),
                ['page_HrmDashboard']
            ),
            'Warehouse Manager' => array_merge(
                $this->getDomainPermissions($entities['Inventory']),
                ['ViewAny:Product', 'View:Product'],
                ['page_InventoryDashboard', 'page_InventoryReportsPage', 'page_InventoryValuationPage']
            ),
            'Sales Representative' => [
                'ViewAny:OfflineSale', 'View:OfflineSale', 'Create:OfflineSale', 'Update:OfflineSale',
                'ViewAny:Order', 'View:Order', 'Create:Order',
                'ViewAny:Product', 'View:Product',
                'ViewAny:StockLevel', 'View:StockLevel',
                'ViewAny:User', 'View:User',
                'ViewAny:CrmLead', 'View:CrmLead', 'Create:CrmLead', 'Update:CrmLead',
                'ViewAny:ContactMessage', 'View:ContactMessage', 'Create:ContactMessage', 'Update:ContactMessage',
                'page_OfflineSales',
                'page_CrmKanban',
            ],
            'Support Agent' => [
                'ViewAny:CrmLead', 'View:CrmLead', 'Create:CrmLead', 'Update:CrmLead',
                'ViewAny:ContactMessage', 'View:ContactMessage', 'Create:ContactMessage', 'Update:ContactMessage',
                'ViewAny:User', 'View:User',
                'ViewAny:Product', 'View:Product',
                'page_CrmKanban',
            ],
            'Viewer' => array_filter($allPermissions, fn($p) => str_starts_with($p, 'ViewAny:') || str_starts_with($p, 'View:')),
            'Customer' => [],
        ];

        foreach ($roleDefinitions as $roleName => $perms) {
            foreach ($guards as $guard) {
                $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => $guard]);
                if (!empty($perms)) {
                    $role->syncPermissions(
                        Permission::where('guard_name', $guard)->whereIn('name', $perms)->get()
                    );
                }
            }
        }

        // Forget cached permissions once more
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function getDomainPermissions(array $domainModels): array
    {
        $perms = [];
        foreach ($domainModels as $model => $actions) {
            foreach ($actions as $action) {
                $perms[] = "{$action}:{$model}";
            }
        }
        return $perms;
    }
}
