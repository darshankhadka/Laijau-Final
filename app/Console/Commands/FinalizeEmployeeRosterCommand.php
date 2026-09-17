<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class FinalizeEmployeeRosterCommand extends Command
{
    protected $signature = 'laijau:finalize-employees';

    protected $description = 'Finalize the authoritative employee roster with precise roles, permissions, credentials, and HRM integration';

    public function handle(): int
    {
        $this->info('========================================================================');
        $this->info('  LAIJAU ERP — AUTHORITATIVE EMPLOYEE ROSTER & RBAC FINALIZATION');
        $this->info('========================================================================');

        // Step 1: Run RolePermissionSeeder to ensure all roles & permissions exist
        $this->info("\n[Step 1] Synchronizing Spatie RBAC Roles & Permissions...");
        $seeder = new RolePermissionSeeder();
        $seeder->run();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->info("  ✓ Synced Super Admin, Executive, Operations Manager, Sales Representative, and Cashier roles.");

        // Step 2: Ensure HRM Departments & Positions exist
        $this->info("\n[Step 2] Provisioning HRM Departments & Positions...");
        $deptMgmt = DB::table('hrm_departments')->where('code', 'MGMT')->value('id');
        if (!$deptMgmt) {
            $deptMgmt = DB::table('hrm_departments')->insertGetId([
                'name' => 'Management & Strategy',
                'code' => 'MGMT',
                'location' => 'Kathmandu HQ, Nepal',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $deptShowroom = DB::table('hrm_departments')->where('code', 'SHOWROOM')->value('id');
        if (!$deptShowroom) {
            $deptShowroom = DB::table('hrm_departments')->insertGetId([
                'name' => 'Laijau Showroom',
                'code' => 'SHOWROOM',
                'location' => 'Laijau Showroom, Kathmandu, Nepal',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $positions = [
            'CEO' => ['title' => 'Chief Executive Officer (CEO)', 'code' => 'EXEC-CEO', 'dept' => $deptMgmt],
            'COO' => ['title' => 'Chief Operating Officer (COO)', 'code' => 'EXEC-COO', 'dept' => $deptMgmt],
            'IT' => ['title' => 'IT Lead & System Administrator', 'code' => 'TECH-IT', 'dept' => $deptMgmt],
            'MGR' => ['title' => 'Showroom & Operations Manager', 'code' => 'OPS-MGR', 'dept' => $deptShowroom],
            'SALES' => ['title' => 'Showroom Sales Executive', 'code' => 'SALES-EXEC', 'dept' => $deptShowroom],
            'CASHIER' => ['title' => 'Showroom Cashier & POS Operator', 'code' => 'POS-CASH', 'dept' => $deptShowroom],
        ];

        $posMap = [];
        foreach ($positions as $key => $pData) {
            $pId = DB::table('hrm_positions')->where('code', $pData['code'])->value('id');
            if (!$pId) {
                $pId = DB::table('hrm_positions')->insertGetId([
                    'department_id' => $pData['dept'],
                    'title' => $pData['title'],
                    'code' => $pData['code'],
                    'employment_type' => 'full_time',
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $posMap[$key] = $pId;
        }
        $this->info("  ✓ HRM Departments and Positions aligned.");

        // Step 3: Employee Definitions
        $staffRoster = [
            [
                'emp_no' => 'LJ-EMP-001',
                'name' => 'Darshan Jung khadka',
                'first_name' => 'Darshan Jung',
                'last_name' => 'Khadka',
                'designation' => 'IT',
                'role_desc' => 'Full Access',
                'contact_no' => '9861294332',
                'email' => 'admin@laijau.com',
                'role_code' => 'super_admin',
                'spatie_roles' => ['Super Admin'],
                'password' => null, // DO NOT CHANGE PASSWORD
                'dept_id' => $deptMgmt,
                'pos_id' => $posMap['IT'],
            ],
            [
                'emp_no' => 'LJ-EMP-002',
                'name' => 'Anup Bohara',
                'first_name' => 'Anup',
                'last_name' => 'Bohara',
                'designation' => 'CEO',
                'role_desc' => 'Except Settings',
                'contact_no' => '9851172441',
                'email' => 'anup@laijau.com',
                'role_code' => 'executive',
                'spatie_roles' => ['Executive'],
                'password' => 'Laijau@Anup2026#',
                'dept_id' => $deptMgmt,
                'pos_id' => $posMap['CEO'],
            ],
            [
                'emp_no' => 'LJ-EMP-003',
                'name' => 'Neha Niraula',
                'first_name' => 'Neha',
                'last_name' => 'Niraula',
                'designation' => 'COO',
                'role_desc' => 'Except Settings',
                'contact_no' => '9763596168',
                'email' => 'neha@laijau.com',
                'role_code' => 'executive',
                'spatie_roles' => ['Executive'],
                'password' => 'Laijau@Neha2026#',
                'dept_id' => $deptMgmt,
                'pos_id' => $posMap['COO'],
            ],
            [
                'emp_no' => 'LJ-EMP-004',
                'name' => 'Upasna Shrestha',
                'first_name' => 'Upasna',
                'last_name' => 'Shrestha',
                'designation' => 'Manager',
                'role_desc' => 'Except Settings and delete access',
                'contact_no' => '9808966032',
                'email' => 'upashna@laijau.com',
                'role_code' => 'operations_manager',
                'spatie_roles' => ['Operations Manager', 'Store Manager'],
                'password' => 'Laijau@Upasna2026#',
                'dept_id' => $deptShowroom,
                'pos_id' => $posMap['MGR'],
            ],
            [
                'emp_no' => 'LJ-EMP-005',
                'name' => 'Babita bhujel',
                'first_name' => 'Babita',
                'last_name' => 'Bhujel',
                'designation' => 'Manager',
                'role_desc' => 'Except Settings and delete access',
                'contact_no' => '9842578957',
                'email' => 'babita@laijau.com',
                'role_code' => 'operations_manager',
                'spatie_roles' => ['Operations Manager', 'Store Manager'],
                'password' => 'Laijau@Babita2026#',
                'dept_id' => $deptShowroom,
                'pos_id' => $posMap['MGR'],
            ],
            [
                'emp_no' => 'LJ-EMP-006',
                'name' => 'Yogesh Khatri',
                'first_name' => 'Yogesh',
                'last_name' => 'Khatri',
                'designation' => 'Sales',
                'role_desc' => 'Except Settings and delete access',
                'contact_no' => '9745390311',
                'email' => 'yogesh@laijau.com',
                'role_code' => 'sales_rep',
                'spatie_roles' => ['Sales Representative'],
                'password' => 'Laijau@Yogesh2026#',
                'dept_id' => $deptShowroom,
                'pos_id' => $posMap['SALES'],
            ],
            [
                'emp_no' => 'LJ-EMP-007',
                'name' => 'Ishor Ghimire',
                'first_name' => 'Ishor',
                'last_name' => 'Ghimire',
                'designation' => 'Sales',
                'role_desc' => 'Except Settings and delete access',
                'contact_no' => '9848854963',
                'email' => 'ishor@laijau.com',
                'role_code' => 'sales_rep',
                'spatie_roles' => ['Sales Representative'],
                'password' => 'Laijau@Ishor2026#',
                'dept_id' => $deptShowroom,
                'pos_id' => $posMap['SALES'],
            ],
            [
                'emp_no' => 'LJ-EMP-008',
                'name' => 'Showroom Cashier',
                'first_name' => 'Showroom',
                'last_name' => 'Cashier',
                'designation' => 'Cashier',
                'role_desc' => 'POS only',
                'contact_no' => '9843512095',
                'email' => 'cashier@laijau.com',
                'role_code' => 'cashier',
                'spatie_roles' => ['Cashier'],
                'password' => 'Laijau@Cashier2026#',
                'dept_id' => $deptShowroom,
                'pos_id' => $posMap['CASHIER'],
            ],
        ];

        $this->info("\n[Step 3] Provisioning Employee User Accounts & Roles...");
        $tableRows = [];

        foreach ($staffRoster as $staff) {
            /** @var User|null $user */
            $user = User::where('email', $staff['email'])->first();

            if ($user) {
                $user->name = $staff['name'];
                $user->phone = $staff['contact_no'];
                $user->role = $staff['role_code'];
                $user->is_active = true;

                // Only update password if explicitly specified (never touch admin@laijau.com)
                if ($staff['password'] !== null) {
                    $user->password = Hash::make($staff['password']);
                }
                $user->save();
            } else {
                $userData = [
                    'name' => $staff['name'],
                    'email' => $staff['email'],
                    'phone' => $staff['contact_no'],
                    'role' => $staff['role_code'],
                    'is_active' => true,
                    'country' => 'NP',
                    'city' => 'Kathmandu',
                    'address' => 'Kathmandu, Nepal',
                ];

                if ($staff['password'] !== null) {
                    $userData['password'] = Hash::make($staff['password']);
                } else {
                    $userData['password'] = Hash::make('Laijau@2026Secure!');
                }

                $user = User::create($userData);
            }

            // Sync Spatie Roles across both admin and web guards
            foreach (['admin', 'web'] as $guard) {
                $rolesToAssign = [];
                foreach ($staff['spatie_roles'] as $roleName) {
                    $roleObj = Role::where('name', $roleName)->where('guard_name', $guard)->first();
                    if ($roleObj) {
                        $rolesToAssign[] = $roleObj;
                    }
                }
                if (!empty($rolesToAssign)) {
                    $user->syncRoles($rolesToAssign);
                }
            }

            // Provision or update HRM Employee record
            $empRecord = DB::table('hrm_employees')->where('email', $staff['email'])->first();
            $empData = [
                'employee_number' => $staff['emp_no'],
                'user_id' => $user->id,
                'first_name' => $staff['first_name'],
                'last_name' => $staff['last_name'],
                'email' => $staff['email'],
                'phone' => $staff['contact_no'],
                'department_id' => $staff['dept_id'],
                'position_id' => $staff['pos_id'],
                'branch_location' => 'Kathmandu Showroom (Durbar Marg)',
                'employment_type' => 'full_time',
                'status' => 'active',
                'hire_date' => '2026-01-01',
                'country' => 'NP',
                'city' => 'Kathmandu',
                'updated_at' => now(),
            ];

            if ($empRecord) {
                DB::table('hrm_employees')->where('id', $empRecord->id)->update($empData);
            } else {
                $empData['created_at'] = now();
                DB::table('hrm_employees')->insert($empData);
            }

            $tableRows[] = [
                $staff['name'],
                $staff['designation'],
                $staff['role_desc'],
                $staff['contact_no'],
                $staff['email'],
                $staff['password'] ?? '[PRESERVED UNCHANGED]',
            ];
        }

        $this->table(
            ['Staff Name', 'Designation', 'Roles', 'CONTACT NO', 'Email', 'Credentials / Password'],
            $tableRows
        );

        $this->info("\n========================================================================");
        $this->info("  AUTHORITATIVE EMPLOYEE ROSTER SUCCESSFULLY COMMITTED TO LAIJAU ERP");
        $this->info("========================================================================");

        return self::SUCCESS;
    }
}
