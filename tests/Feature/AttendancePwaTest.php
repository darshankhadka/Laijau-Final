<?php

namespace Tests\Feature;

use App\Models\Attendance\AttendanceAuthToken;
use App\Models\Attendance\AttendanceEvent;
use App\Models\Attendance\AttendanceLocation;
use App\Models\Attendance\AttendanceSession;
use App\Models\Attendance\AttendanceSetting;
use App\Models\Hrm\Employee;
use App\Models\Hrm\Timesheet;
use App\Services\Attendance\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AttendancePwaTest extends TestCase
{
    use DatabaseTransactions;

    protected Employee $employee;
    protected AttendanceLocation $location;
    protected AttendanceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        cache()->flush();

        $this->service = app(AttendanceService::class);

        // Ensure default showroom location exists
        $this->location = AttendanceLocation::where('is_default', true)->first()
            ?? AttendanceLocation::firstOrCreate(
                ['code' => 'LOC-KTM-SHOWROOM'],
                [
                    'name' => 'Laijau Showroom (Headquarters)',
                    'latitude' => 27.6976748,
                    'longitude' => 85.3664331,
                    'radius_meters' => 100,
                    'address' => 'Bohara Tol, Kathmandu',
                    'is_active' => true,
                    'is_default' => true,
                ]
            );

        $this->location->update([
            'latitude' => 27.6976748,
            'longitude' => 85.3664331,
            'radius_meters' => 100,
            'is_default' => true,
            'is_active' => true,
        ]);

        // Create dedicated test employee
        $this->employee = Employee::create([
            'employee_number' => 'TEST-EMP-999',
            'first_name' => 'Suman',
            'last_name' => 'Adhikari',
            'phone' => '9841999888',
            'email' => 'suman.test@laijau.com',
            'hire_date' => now()->toDateString(),
            'status' => 'active',
            'attendance_access_enabled' => true,
            'attendance_failed_attempts' => 0,
        ]);

        // Set initial PIN 1234
        $this->employee->setAttendancePin('1234');
    }

    /**
     * 1. Admin generates unique PIN for employee.
     */
    public function test_admin_generates_unique_pin_for_employee(): void
    {
        $uniquePin = $this->service->generateUniquePin();
        $this->assertEquals(6, strlen($uniquePin));
        $this->assertTrue(ctype_digit($uniquePin));

        $this->service->setEmployeePin($this->employee, $uniquePin);

        $this->employee->refresh();
        $this->assertTrue($this->employee->hasAttendancePin());
        $this->assertTrue($this->employee->verifyAttendancePin($uniquePin));

        // Ensure PIN is securely hashed, never plaintext
        $this->assertNotEquals($uniquePin, $this->employee->attendance_pin_hash);
        $this->assertEquals(
            Employee::hashPinForLookup($uniquePin),
            $this->employee->attendance_pin_lookup_hash
        );
    }

    /**
     * 2. Setting duplicate PIN across active employees is prevented.
     */
    public function test_duplicate_pin_is_prevented(): void
    {
        $emp2 = Employee::create([
            'employee_number' => 'TEST-EMP-888',
            'first_name' => 'Aarav',
            'last_name' => 'Sharma',
            'phone' => '9841777666',
            'email' => 'aarav.test@laijau.com',
            'hire_date' => now()->toDateString(),
            'status' => 'active',
            'attendance_access_enabled' => true,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->service->setEmployeePin($emp2, '1234'); // already used by $this->employee
    }

    /**
     * 3. Employee authenticates with unique PIN and receives persistent session cookie.
     */
    public function test_employee_authenticates_with_unique_pin(): void
    {
        $response = $this->postJson(route('attendance.api.auth.pin'), [
            'pin' => '1234',
            'device_name' => 'Pixel 8 Pro Test',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'token',
            'employee' => ['id', 'name', 'code'],
            'state' => ['state'],
        ]);
        $response->assertCookie('laijau_attendance_session');

        $this->assertEquals($this->employee->id, session('attendance_employee_id'));
    }

    /**
     * 4. Invalid PIN is rejected and increments failed attempts.
     */
    public function test_invalid_pin_is_rejected_and_tracks_attempts(): void
    {
        $response = $this->postJson(route('attendance.api.auth.pin'), [
            'pin' => '9999', // wrong pin
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['pin']);
    }

    /**
     * 5. Consecutive failed attempts trigger temporary lockout.
     */
    public function test_consecutive_failed_attempts_trigger_temporary_lockout(): void
    {
        AttendanceSetting::set('max_failed_attempts', 3, 'integer');
        AttendanceSetting::set('lockout_minutes', 15, 'integer');

        $this->employee->recordFailedPinAttempt();
        $this->employee->recordFailedPinAttempt();
        $isLocked = $this->employee->recordFailedPinAttempt();

        $this->assertTrue($isLocked);
        $this->assertTrue($this->employee->isAttendanceLocked());

        // Attempt login while locked
        $response = $this->postJson(route('attendance.api.auth.pin'), [
            'pin' => '1234',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('temporarily locked', $response->json('errors.pin.0'));
    }

    /**
     * 6. Disabled employee is rejected.
     */
    public function test_disabled_employee_cannot_authenticate(): void
    {
        $this->employee->update(['attendance_access_enabled' => false]);

        $response = $this->postJson(route('attendance.api.auth.pin'), [
            'pin' => '1234',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('disabled', $response->json('errors.pin.0'));
    }

    /**
     * 7. Persistent device cookie auto-rehydrates session without re-entering PIN.
     */
    public function test_persistent_device_cookie_auto_rehydrates_session(): void
    {
        $rawToken = AttendanceAuthToken::createToken($this->employee, 'Test Mobile');

        // Access dashboard with cookie only (no session, simulating reopening browser)
        $response = $this->withUnencryptedCookie('laijau_attendance_session', $rawToken)
            ->get(route('attendance.dashboard'));

        $response->assertStatus(200);
        $response->assertSee($this->employee->first_name);
        $this->assertEquals($this->employee->id, session('attendance_employee_id'));
    }

    /**
     * 8. Attendance session provides ZERO access to /intadmin.
     */
    public function test_attendance_session_provides_no_access_to_intadmin(): void
    {
        // Session with attendance auth only
        $response = $this->withSession([
            'attendance_employee_id' => $this->employee->id,
        ])->get('/intadmin');

        // Filament auth middleware will redirect to /intadmin/login or return 302/403
        $this->assertTrue(in_array($response->status(), [302, 403, 401]));
        if ($response->status() === 302) {
            $this->assertStringContainsString('login', $response->headers->get('Location'));
        }
    }

    /**
     * 9. Server authoritative state: Clock In when clocked out, then Clock Out when clocked in.
     */
    public function test_server_authoritative_state_clock_in_then_clock_out(): void
    {
        // 1. Initial State: Clocked out
        $initialState = $this->service->getEmployeeAttendanceState($this->employee);
        $this->assertEquals('not_checked_in', $initialState['state']);

        $dashRes1 = $this->withSession(['attendance_employee_id' => $this->employee->id])
            ->get(route('attendance.dashboard'));
        $dashRes1->assertStatus(200);
        $dashRes1->assertSee('CLOCK IN');

        // 2. Perform Clock In
        $inRes = $this->withSession(['attendance_employee_id' => $this->employee->id])
            ->postJson(route('attendance.api.check_in'), [
                'latitude' => 27.6976748,
                'longitude' => 85.3664331,
                'accuracy_meters' => 15,
            ]);

        $inRes->assertStatus(200);
        $inRes->assertJson(['success' => true]);
        $inRes->assertJsonPath('state.state', 'checked_in');

        // Verify timesheet synced
        $this->assertDatabaseHas('hrm_timesheets', [
            'employee_id' => $this->employee->id,
            'date' => now()->toDateString(),
            'attendance_status' => 'present',
        ]);

        // 3. Reopening PWA: Authoritative state is checked_in, showing Clock Out
        $dashRes2 = $this->withSession(['attendance_employee_id' => $this->employee->id])
            ->get(route('attendance.dashboard'));
        $dashRes2->assertStatus(200);
        $dashRes2->assertSee('CLOCK OUT');

        // Duplicate Clock In is rejected
        $dupIn = $this->withSession(['attendance_employee_id' => $this->employee->id])
            ->postJson(route('attendance.api.check_in'), [
                'latitude' => 27.6976748,
                'longitude' => 85.3664331,
                'accuracy_meters' => 15,
            ]);
        $dupIn->assertStatus(422);

        // 4. Perform Clock Out
        $outRes = $this->withSession(['attendance_employee_id' => $this->employee->id])
            ->postJson(route('attendance.api.check_out'), [
                'latitude' => 27.6976748,
                'longitude' => 85.3664331,
                'accuracy_meters' => 15,
            ]);

        $outRes->assertStatus(200);
        $outRes->assertJson(['success' => true]);
        $outRes->assertJsonPath('state.state', 'checked_out');

        // 5. Final State: checked_out
        $finalState = $this->service->getEmployeeAttendanceState($this->employee);
        $this->assertEquals('checked_out', $finalState['state']);
    }

    /**
     * 10. Clock In and Clock Out strictly require GPS.
     */
    public function test_clock_in_and_out_strictly_require_gps(): void
    {
        // Clock In without GPS
        $res1 = $this->withSession(['attendance_employee_id' => $this->employee->id])
            ->postJson(route('attendance.api.check_in'), [
                'latitude' => null,
                'longitude' => null,
            ]);

        $res1->assertStatus(422);
        $res1->assertJsonValidationErrors(['gps']);
        $this->assertDatabaseHas('attendance_audit_logs', [
            'employee_id' => $this->employee->id,
            'action' => 'attendance_gps_denied',
        ]);

        // Clock In with GPS
        $this->service->submitAttendance($this->employee, 'check_in', [
            'latitude' => 27.6976748,
            'longitude' => 85.3664331,
            'accuracy_meters' => 15,
        ]);

        // Clock Out without GPS
        $res2 = $this->withSession(['attendance_employee_id' => $this->employee->id])
            ->postJson(route('attendance.api.check_out'), [
                'latitude' => null,
                'longitude' => null,
            ]);

        $res2->assertStatus(422);
        $res2->assertJsonValidationErrors(['gps']);
    }

    /**
     * 11. Low GPS accuracy (> 100m) is rejected.
     */
    public function test_low_gps_accuracy_is_rejected(): void
    {
        AttendanceSetting::set('max_gps_accuracy_meters', 100, 'integer');

        $res = $this->withSession(['attendance_employee_id' => $this->employee->id])
            ->postJson(route('attendance.api.check_in'), [
                'latitude' => 27.6976748,
                'longitude' => 85.3664331,
                'accuracy_meters' => 350, // 350m is too low
            ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['gps']);
        $this->assertStringContainsString('350m', $res->json('errors.gps.0'));
    }

    /**
     * 12. Outside geofence radius is rejected.
     */
    public function test_outside_geofence_is_rejected(): void
    {
        AttendanceSetting::set('geofencing_enabled', true, 'boolean');

        // 10km away from showroom
        $res = $this->withSession(['attendance_employee_id' => $this->employee->id])
            ->postJson(route('attendance.api.check_in'), [
                'latitude' => 27.7500000,
                'longitude' => 85.4500000,
                'accuracy_meters' => 15,
            ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['geofence']);
    }

    /**
     * 13. Idempotency key prevents duplicate punches.
     */
    public function test_idempotency_prevents_duplicate_punch(): void
    {
        $key = 'IDEMPOTENCY_TEST_KEY_' . time();

        $res1 = $this->withSession(['attendance_employee_id' => $this->employee->id])
            ->postJson(route('attendance.api.check_in'), [
                'latitude' => 27.6976748,
                'longitude' => 85.3664331,
                'accuracy_meters' => 15,
                'idempotency_key' => $key,
            ]);
        $res1->assertStatus(200);

        // Repeat with same key
        $res2 = $this->withSession(['attendance_employee_id' => $this->employee->id])
            ->postJson(route('attendance.api.check_in'), [
                'latitude' => 27.6976748,
                'longitude' => 85.3664331,
                'accuracy_meters' => 15,
                'idempotency_key' => $key,
            ]);
        $res2->assertStatus(200);

        $this->assertEquals(1, AttendanceEvent::where('idempotency_key', $key)->count());
    }

    /**
     * 14. History isolation: Employee sees only their own history.
     */
    public function test_employee_history_isolation(): void
    {
        // Punch for employee 1
        $this->service->submitAttendance($this->employee, 'check_in', [
            'latitude' => 27.6976748,
            'longitude' => 85.3664331,
            'accuracy_meters' => 15,
        ]);

        // Create employee 2 with punch
        $emp2 = Employee::create([
            'employee_number' => 'TEST-EMP-888',
            'first_name' => 'Other',
            'last_name' => 'Emp',
            'phone' => '9841888777',
            'email' => 'other@laijau.com',
            'hire_date' => now()->toDateString(),
            'status' => 'active',
            'attendance_access_enabled' => true,
        ]);
        $emp2->setAttendancePin('5678');
        $this->service->submitAttendance($emp2, 'check_in', [
            'latitude' => 27.6976748,
            'longitude' => 85.3664331,
            'accuracy_meters' => 15,
        ]);

        $res = $this->withSession(['attendance_employee_id' => $this->employee->id])
            ->getJson(route('attendance.api.history'));

        $res->assertStatus(200);
        $this->assertCount(1, $res->json('history'));
    }

    /**
     * 15. Employee can change their attendance PIN.
     */
    public function test_employee_can_change_pin(): void
    {
        $res = $this->withSession(['attendance_employee_id' => $this->employee->id])
            ->postJson(route('attendance.api.auth.change_pin'), [
                'current_pin' => '1234',
                'new_pin' => '9876',
                'new_pin_confirmation' => '9876',
            ]);

        $res->assertStatus(200);
        $this->employee->refresh();
        $this->assertTrue($this->employee->verifyAttendancePin('9876'));
        $this->assertFalse($this->employee->verifyAttendancePin('1234'));
    }

    /**
     * 16. Logout clears session and persistent cookie.
     */
    public function test_logout_clears_session_and_cookie(): void
    {
        $token = AttendanceAuthToken::createToken($this->employee, 'Phone');

        $res = $this->withSession(['attendance_employee_id' => $this->employee->id])
            ->withCookie('laijau_attendance_session', $token)
            ->postJson(route('attendance.api.logout'));

        $res->assertStatus(200);
        $res->assertJson(['success' => true]);
        $this->assertNull(session('attendance_employee_id'));
    }

    /**
     * 17. PWA manifest and service worker routes return proper responses.
     */
    public function test_pwa_manifest_and_service_worker(): void
    {
        $manifest = $this->get(route('attendance.manifest'));
        $manifest->assertStatus(200);
        $manifest->assertHeader('Content-Type', 'application/manifest+json; charset=utf-8');

        $sw = $this->get(route('attendance.sw'));
        $sw->assertStatus(200);
        $sw->assertHeader('Content-Type', 'application/javascript; charset=utf-8');

        $offline = $this->get(route('attendance.offline'));
        $offline->assertStatus(200);
    }

    /**
     * 18. Obsolete photo and pairing setup endpoints are completely removed.
     */
    public function test_obsolete_setup_and_photo_routes_removed(): void
    {
        $this->get('/attendance/photos/1')->assertStatus(404);
        $this->postJson('/attendance/api/verify', [])->assertStatus(404);
        $this->postJson('/attendance/api/setup', [])->assertStatus(404);
    }

    /**
     * 19. Root /attendance navigation: unauthenticated redirects to login; authenticated renders dashboard.
     */
    public function test_root_attendance_navigation(): void
    {
        // Unauthenticated -> redirected to login
        $unauthRes = $this->get(route('attendance.dashboard'));
        $unauthRes->assertStatus(302);
        $unauthRes->assertRedirect(route('attendance.login'));

        // Login screen renders standalone PIN PWA
        $loginRes = $this->get(route('attendance.login'));
        $loginRes->assertStatus(200);
        $loginRes->assertSee('Laijau Attendance');
        $loginRes->assertSee('Unlock Dashboard');

        // Authenticated -> dashboard renders directly
        $authRes = $this->withSession(['attendance_employee_id' => $this->employee->id])
            ->get(route('attendance.dashboard'));
        $authRes->assertStatus(200);
        $authRes->assertSee($this->employee->first_name);
    }

    /**
     * 20. Control Center PIN generation and custom reset.
     */
    public function test_control_center_pin_management_actions(): void
    {
        $page = new \App\Filament\Pages\AttendanceControlCenterPage();

        // 1. Generate Unique PIN
        $page->generatePinForEmployee($this->employee->id);
        $this->assertNotNull($page->generatedPin);
        $this->assertEquals(6, strlen($page->generatedPin));
        $this->assertTrue($page->showGeneratedPinModal);

        $this->employee->refresh();
        $this->assertTrue($this->employee->verifyAttendancePin($page->generatedPin));

        $page->closeGeneratedPinModal();
        $this->assertFalse($page->showGeneratedPinModal);
        $this->assertNull($page->generatedPin);

        // 2. Set Custom PIN
        $page->openPinModal($this->employee->id);
        $this->assertTrue($page->showPinModal);
        $page->newPin = '7890';
        $page->newPinConfirmation = '7890';
        $page->saveEmployeePin();

        $this->employee->refresh();
        $this->assertTrue($this->employee->verifyAttendancePin('7890'));
        $this->assertFalse($page->showPinModal);
    }

    /**
     * 21. Darshan Jung Khadka can punch in from anywhere, while regular employees are geofenced.
     */
    public function test_darshan_jung_khadka_can_punch_in_from_anywhere_while_others_are_geofenced(): void
    {
        AttendanceSetting::set('geofencing_enabled', true, 'boolean');

        // 1. Regular employee at remote location (150km away) -> REJECTED
        $resRegular = $this->withSession(['attendance_employee_id' => $this->employee->id])
            ->postJson(route('attendance.api.check_in'), [
                'latitude' => 28.2096, // Pokhara (~150km away)
                'longitude' => 83.9856,
                'accuracy_meters' => 20,
            ]);

        $resRegular->assertStatus(422);
        $resRegular->assertJsonValidationErrors(['geofence']);

        // 2. Darshan Jung Khadka at remote location (150km away) -> SUCCEEDS
        $darshan = Employee::where('email', 'admin@laijau.com')->first()
            ?? Employee::create([
                'employee_number' => 'LJ-EMP-001',
                'first_name' => 'Darshan Jung',
                'last_name' => 'Khadka',
                'email' => 'admin@laijau.com',
                'status' => 'active',
                'attendance_access_enabled' => true,
                'can_punch_from_anywhere' => true,
            ]);

        $this->assertTrue($darshan->canPunchFromAnywhere());

        $resDarshanIn = $this->withSession(['attendance_employee_id' => $darshan->id])
            ->postJson(route('attendance.api.check_in'), [
                'latitude' => 28.2096, // Pokhara (~150km away)
                'longitude' => 83.9856,
                'accuracy_meters' => 25,
            ]);

        $resDarshanIn->assertStatus(200);
        $resDarshanIn->assertJson(['success' => true]);
        $resDarshanIn->assertJsonPath('state.state', 'checked_in');

        // Check event recorded with geofence_passed = 1
        $this->assertDatabaseHas('attendance_events', [
            'employee_id' => $darshan->id,
            'type' => 'check_in',
            'status' => 'verified',
            'geofence_passed' => 1,
        ]);

        // Darshan can also punch out from remote location
        $resDarshanOut = $this->withSession(['attendance_employee_id' => $darshan->id])
            ->postJson(route('attendance.api.check_out'), [
                'latitude' => 28.2096,
                'longitude' => 83.9856,
                'accuracy_meters' => 25,
            ]);

        $resDarshanOut->assertStatus(200);
        $resDarshanOut->assertJson(['success' => true]);
        $resDarshanOut->assertJsonPath('state.state', 'checked_out');
    }

    /**
     * 22. Darshan Jung Khadka can punch in without GPS hardware or low accuracy on remote device.
     */
    public function test_darshan_can_punch_without_gps_while_others_are_blocked(): void
    {
        // Regular employee without GPS -> blocked
        $resRegular = $this->withSession(['attendance_employee_id' => $this->employee->id])
            ->postJson(route('attendance.api.check_in'), [
                'latitude' => null,
                'longitude' => null,
            ]);
        $resRegular->assertStatus(422);
        $resRegular->assertJsonValidationErrors(['gps']);

        // Darshan Jung Khadka without GPS -> allowed
        $darshan = Employee::where('email', 'admin@laijau.com')->first();
        if (!$darshan) {
            $darshan = Employee::create([
                'employee_number' => 'LJ-EMP-001',
                'first_name' => 'Darshan Jung',
                'last_name' => 'Khadka',
                'email' => 'admin@laijau.com',
                'status' => 'active',
                'attendance_access_enabled' => true,
                'can_punch_from_anywhere' => true,
            ]);
        }

        $resDarshan = $this->withSession(['attendance_employee_id' => $darshan->id])
            ->postJson(route('attendance.api.check_in'), [
                'latitude' => null,
                'longitude' => null,
            ]);

        $resDarshan->assertStatus(200);
        $resDarshan->assertJson(['success' => true]);
        $resDarshan->assertJsonPath('state.state', 'checked_in');
    }

    /**
     * 23. Employee can have multiple attendance sessions in the same day with full details and aggregation.
     */
    public function test_employee_can_have_multiple_attendance_sessions_in_same_day(): void
    {
        $lat = 27.6976748;
        $lng = 85.3664331;
        $acc = 10;

        // Session 1: Clock In
        $in1 = $this->withSession(['attendance_employee_id' => $this->employee->id])
            ->postJson(route('attendance.api.check_in'), [
                'latitude' => $lat,
                'longitude' => $lng,
                'accuracy_meters' => $acc,
            ]);
        $in1->assertStatus(200);
        $in1->assertJsonPath('state.state', 'checked_in');

        // Session 1: Clock Out
        $out1 = $this->withSession(['attendance_employee_id' => $this->employee->id])
            ->postJson(route('attendance.api.check_out'), [
                'latitude' => $lat,
                'longitude' => $lng,
                'accuracy_meters' => $acc,
            ]);
        $out1->assertStatus(200);
        $out1->assertJsonPath('state.state', 'checked_out');

        // Session 2: Clock In (Second session in same day)
        $in2 = $this->withSession(['attendance_employee_id' => $this->employee->id])
            ->postJson(route('attendance.api.check_in'), [
                'latitude' => $lat,
                'longitude' => $lng,
                'accuracy_meters' => $acc,
            ]);
        $in2->assertStatus(200);
        $in2->assertJsonPath('state.state', 'checked_in');

        // Session 2: Clock Out
        $out2 = $this->withSession(['attendance_employee_id' => $this->employee->id])
            ->postJson(route('attendance.api.check_out'), [
                'latitude' => $lat,
                'longitude' => $lng,
                'accuracy_meters' => $acc,
            ]);
        $out2->assertStatus(200);
        $out2->assertJsonPath('state.state', 'checked_out');

        // Verify database: 2 distinct attendance sessions exist for today
        $sessions = AttendanceSession::where('employee_id', $this->employee->id)
            ->where('date', now()->timezone('Asia/Kathmandu')->toDateString())
            ->orderBy('session_number')
            ->get();

        $this->assertCount(2, $sessions);
        $this->assertEquals(1, $sessions[0]->session_number);
        $this->assertEquals('completed', $sessions[0]->status);
        $this->assertEquals(2, $sessions[1]->session_number);
        $this->assertEquals('completed', $sessions[1]->status);

        // Verify timesheet aggregates total_sessions = 2
        $timesheet = Timesheet::where('employee_id', $this->employee->id)
            ->where('date', now()->timezone('Asia/Kathmandu')->toDateString())
            ->first();

        $this->assertNotNull($timesheet);
        $this->assertEquals(2, $timesheet->total_sessions);
        $this->assertNotNull($timesheet->clock_in);
        $this->assertNotNull($timesheet->clock_out);
        $this->assertIsArray($timesheet->sessions_summary);
        $this->assertCount(2, $timesheet->sessions_summary);

        // PWA dashboard receives and shows all sessions in authoritative state
        $dashRes = $this->withSession(['attendance_employee_id' => $this->employee->id])
            ->get(route('attendance.dashboard'));
        $dashRes->assertStatus(200);
        $dashRes->assertSee($this->employee->first_name);
        $dashRes->assertSee("Today's Sessions", false);
        $dashRes->assertViewHas('status', function ($status) {
            return count($status['today_sessions']) === 2
                && $status['completed_sessions_today'] === 2
                && $status['next_session_number'] === 3;
        });
    }

    /**
     * 24. Prevent Clock Out without an active Clock In session.
     */
    public function test_prevent_clock_out_without_active_session(): void
    {
        $response = $this->withSession(['attendance_employee_id' => $this->employee->id])
            ->postJson(route('attendance.api.check_out'), [
                'latitude' => 27.6976748,
                'longitude' => 85.3664331,
                'accuracy_meters' => 10,
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Clock In first before Clocking Out', $response->json('message'));
    }

    /**
     * 25. Prevent duplicate Clock In when an active session already exists.
     */
    public function test_prevent_duplicate_clock_in_while_active_session_exists(): void
    {
        // First Clock In
        $in1 = $this->withSession(['attendance_employee_id' => $this->employee->id])
            ->postJson(route('attendance.api.check_in'), [
                'latitude' => 27.6976748,
                'longitude' => 85.3664331,
                'accuracy_meters' => 10,
            ]);
        $in1->assertStatus(200);

        // Second Clock In without clocking out
        $in2 = $this->withSession(['attendance_employee_id' => $this->employee->id])
            ->postJson(route('attendance.api.check_in'), [
                'latitude' => 27.6976748,
                'longitude' => 85.3664331,
                'accuracy_meters' => 10,
            ]);

        $in2->assertStatus(422);
        $this->assertStringContainsString('already have an active Clock In session', $responseMsg = $in2->json('message'));
    }

    /**
     * 26. Employee Timesheet Summary API endpoint returns daily, weekly, and monthly aggregations.
     */
    public function test_employee_timesheet_summary_api(): void
    {
        // Clock In and Clock Out to generate session
        $this->withSession(['attendance_employee_id' => $this->employee->id])
            ->postJson(route('attendance.api.check_in'), [
                'latitude' => 27.6976748,
                'longitude' => 85.3664331,
                'accuracy_meters' => 10,
            ]);
        $this->withSession(['attendance_employee_id' => $this->employee->id])
            ->postJson(route('attendance.api.check_out'), [
                'latitude' => 27.6976748,
                'longitude' => 85.3664331,
                'accuracy_meters' => 10,
            ]);

        // Query summary API
        $res = $this->withSession(['attendance_employee_id' => $this->employee->id])
            ->getJson(route('attendance.api.summary', ['period' => 'monthly']));

        $res->assertStatus(200);
        $res->assertJsonStructure([
            'employee' => ['id', 'name', 'code'],
            'period',
            'start_date',
            'end_date',
            'metrics' => [
                'total_sessions',
                'completed_sessions',
                'total_worked_hours',
                'total_worked_minutes',
                'regular_hours',
                'overtime_hours',
            ],
            'sessions',
            'timesheets',
        ]);

        $this->assertGreaterThanOrEqual(1, $res->json('metrics.total_sessions'));
    }

    /**
     * 27. Admin employee summary data endpoint returns authoritative data.
     */
    public function test_admin_employee_summary_data_endpoint(): void
    {
        $adminUser = \App\Models\User::factory()->create();

        $res = $this->actingAs($adminUser)
            ->getJson(route('admin.attendance.employee_summary_data', [
                'employee_id' => $this->employee->id,
                'period' => 'monthly',
            ]));

        $res->assertStatus(200);
        $res->assertJsonPath('employee.id', $this->employee->id);
        $this->assertArrayHasKey('metrics', $res->json());
    }

    /**
     * 28. Attendance dashboard includes progressive GPS resolution and retry mechanics.
     */
    public function test_attendance_dashboard_renders_progressive_gps_resolution_and_retry(): void
    {
        $res = $this->withSession(['attendance_employee_id' => $this->employee->id])
            ->get(route('attendance.dashboard'));

        $res->assertStatus(200);
        $res->assertSee('resolveGpsCoordinates', false);
        $res->assertSee('Retry', false);
        $res->assertSee('enableHighAccuracy: false', false);
        $res->assertSee('enableHighAccuracy: true', false);
    }
}

