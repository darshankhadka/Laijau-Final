<?php

namespace Tests\Feature;

use App\Models\Attendance\AttendanceDevice;
use App\Models\Attendance\AttendanceEvent;
use App\Models\Attendance\AttendanceLocation;
use App\Models\Attendance\AttendanceSetting;
use App\Models\Hrm\Employee;
use App\Models\Hrm\Timesheet;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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

        Storage::fake('local');
        cache()->flush();

        $this->service = app(AttendanceService::class);

        // Ensure default location exists
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
     * 1. First-time setup succeeds with valid Employee ID + Phone + PIN.
     */
    public function test_first_time_setup_succeeds_with_valid_credentials(): void
    {
        $dummyBase64 = 'data:image/jpeg;base64,' . base64_encode('fake-jpg-content');

        $response = $this->postJson(route('attendance.api.setup'), [
            'employee_number' => 'TEST-EMP-999',
            'phone' => '9841999888',
            'pin' => '1234',
            'device_name' => 'iPhone 15 Test',
            'platform' => 'iOS',
            'browser' => 'Mobile Safari',
            'photo' => $dummyBase64,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'device_token', 'employee']);
        $response->assertCookie('laijau_attendance_device');

        $this->assertDatabaseHas('attendance_devices', [
            'employee_id' => $this->employee->id,
            'device_name' => 'iPhone 15 Test',
            'is_active' => true,
        ]);
    }

    /**
     * 2. First-time setup rejects invalid Employee ID.
     */
    public function test_first_time_setup_rejects_invalid_employee_id(): void
    {
        $response = $this->postJson(route('attendance.api.setup'), [
            'employee_number' => 'INVALID-ID-000',
            'phone' => '9841999888',
            'pin' => '1234',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['employee_number']);
    }

    /**
     * 3. First-time setup rejects invalid Phone number.
     */
    public function test_first_time_setup_rejects_invalid_phone(): void
    {
        $response = $this->postJson(route('attendance.api.setup'), [
            'employee_number' => 'TEST-EMP-999',
            'phone' => '9800000000',
            'pin' => '1234',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['phone']);
    }

    /**
     * 4. First-time setup rejects invalid PIN and increments failed attempts.
     */
    public function test_first_time_setup_rejects_invalid_pin_and_tracks_attempts(): void
    {
        $this->assertEquals(0, $this->employee->attendance_failed_attempts);

        $response = $this->postJson(route('attendance.api.setup'), [
            'employee_number' => 'TEST-EMP-999',
            'phone' => '9841999888',
            'pin' => '9999', // wrong pin
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['pin']);

        $this->employee->refresh();
        $this->assertEquals(1, $this->employee->attendance_failed_attempts);
    }

    /**
     * 5. PIN is never stored in plaintext and verifies using Hash::check.
     */
    public function test_pin_is_securely_hashed_and_not_plaintext(): void
    {
        $this->assertNotEquals('1234', $this->employee->attendance_pin_hash);
        $this->assertTrue(Hash::check('1234', $this->employee->attendance_pin_hash));
        $this->assertTrue($this->employee->verifyAttendancePin('1234'));
        $this->assertFalse($this->employee->verifyAttendancePin('0000'));
    }

    /**
     * 6. Consecutive failed attempts trigger temporary lockout.
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
    }

    /**
     * 7. Subsequent PIN authentication on registered device succeeds.
     */
    public function test_subsequent_pin_authentication_on_registered_device(): void
    {
        $reg = $this->service->registerDevice($this->employee, [
            'device_name' => 'Registered Phone',
            'platform' => 'Android',
        ]);

        $device = $reg['device'];
        $rawToken = $reg['device_token'];

        $response = $this->withHeader('X-Device-Token', $rawToken)
            ->postJson(route('attendance.api.auth.pin'), [
                'pin' => '1234',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertEquals($this->employee->id, session('attendance_employee_id'));
        $this->assertEquals($device->id, session('attendance_device_id'));
    }

    /**
     * 8. Revoked device is prevented from authenticating.
     */
    public function test_revoked_device_cannot_authenticate(): void
    {
        $reg = $this->service->registerDevice($this->employee, ['device_name' => 'Old Phone']);
        $device = $reg['device'];
        $rawToken = $reg['device_token'];

        // Revoke device
        $device->revoke(null, 'Test revocation');

        $response = $this->withHeader('X-Device-Token', $rawToken)
            ->postJson(route('attendance.api.auth.pin'), [
                'pin' => '1234',
            ]);

        $response->assertStatus(403);
    }

    /**
     * 9. Disabled employee is prevented from authenticating.
     */
    public function test_disabled_employee_access_rejected(): void
    {
        $this->employee->update(['attendance_access_enabled' => false]);

        $response = $this->postJson(route('attendance.api.setup'), [
            'employee_number' => 'TEST-EMP-999',
            'phone' => '9841999888',
            'pin' => '1234',
        ]);

        $response->assertStatus(422);
    }

    /**
     * 10. Successful Check In with GPS and photo.
     */
    public function test_successful_check_in_with_gps_and_photo(): void
    {
        $reg = $this->service->registerDevice($this->employee, ['device_name' => 'Test Phone']);
        $device = $reg['device'];
        $rawToken = $reg['device_token'];

        $dummyBase64 = 'data:image/jpeg;base64,' . base64_encode('check-in-photo');

        $response = $this->withSession([
            'attendance_employee_id' => $this->employee->id,
            'attendance_device_id' => $device->id,
        ])->withCookie('laijau_attendance_device', $rawToken)
          ->postJson(route('attendance.api.check_in'), [
              'latitude' => 27.6976748, // Exactly at showroom
              'longitude' => 85.3664331,
              'accuracy_meters' => 15,
              'photo' => $dummyBase64,
              'verification_method' => 'pin',
          ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('attendance_events', [
            'employee_id' => $this->employee->id,
            'device_id' => $device->id,
            'type' => 'check_in',
            'status' => 'verified',
            'geofence_passed' => true,
        ]);

        // Verify timesheet synced
        $this->assertDatabaseHas('hrm_timesheets', [
            'employee_id' => $this->employee->id,
            'date' => now()->toDateString(),
            'attendance_status' => 'present',
        ]);
    }

    /**
     * 11. Duplicate check-in is rejected (cannot check in twice without check-out).
     */
    public function test_duplicate_check_in_is_rejected(): void
    {
        $reg = $this->service->registerDevice($this->employee, ['device_name' => 'Test Phone']);
        $device = $reg['device'];
        $dummyBase64 = 'data:image/jpeg;base64,' . base64_encode('photo');

        // First check in
        $this->service->submitAttendance($device, 'check_in', [
            'latitude' => 27.6976748,
            'longitude' => 85.3664331,
            'accuracy_meters' => 10,
        ], $dummyBase64);

        // Attempt second check in
        $response = $this->withSession([
            'attendance_employee_id' => $this->employee->id,
            'attendance_device_id' => $device->id,
        ])->postJson(route('attendance.api.check_in'), [
            'latitude' => 27.6976748,
            'longitude' => 85.3664331,
            'accuracy_meters' => 10,
            'photo' => $dummyBase64,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type']);
    }

    /**
     * 12. Successful Check Out after Check In.
     */
    public function test_successful_check_out_after_check_in(): void
    {
        $reg = $this->service->registerDevice($this->employee, ['device_name' => 'Test Phone']);
        $device = $reg['device'];
        $dummyBase64 = 'data:image/jpeg;base64,' . base64_encode('photo');

        // Check in
        $this->service->submitAttendance($device, 'check_in', [
            'latitude' => 27.6976748,
            'longitude' => 85.3664331,
            'accuracy_meters' => 10,
        ], $dummyBase64);

        // Check out
        $response = $this->withSession([
            'attendance_employee_id' => $this->employee->id,
            'attendance_device_id' => $device->id,
        ])->postJson(route('attendance.api.check_out'), [
            'latitude' => 27.6976748,
            'longitude' => 85.3664331,
            'accuracy_meters' => 10,
            'photo' => $dummyBase64,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('attendance_events', [
            'employee_id' => $this->employee->id,
            'type' => 'check_out',
            'status' => 'verified',
        ]);
    }

    /**
     * 13. Check Out before Check In is rejected.
     */
    public function test_check_out_before_check_in_is_rejected(): void
    {
        $reg = $this->service->registerDevice($this->employee, ['device_name' => 'Test Phone']);
        $device = $reg['device'];
        $dummyBase64 = 'data:image/jpeg;base64,' . base64_encode('photo');

        $response = $this->withSession([
            'attendance_employee_id' => $this->employee->id,
            'attendance_device_id' => $device->id,
        ])->postJson(route('attendance.api.check_out'), [
            'latitude' => 27.6976748,
            'longitude' => 85.3664331,
            'accuracy_meters' => 10,
            'photo' => $dummyBase64,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type']);
    }

    /**
     * 14. Missing or denied GPS permission blocks attendance, shows required message, and logs audit.
     */
    public function test_gps_permission_denied_or_missing_blocks_attendance(): void
    {
        $reg = $this->service->registerDevice($this->employee, ['device_name' => 'Test Phone']);
        $device = $reg['device'];
        $dummyBase64 = 'data:image/jpeg;base64,' . base64_encode('photo');

        // Attempt check-in with null / missing GPS coordinates
        $response = $this->withSession([
            'attendance_employee_id' => $this->employee->id,
            'attendance_device_id' => $device->id,
        ])->postJson(route('attendance.api.check_in'), [
            'latitude' => null,
            'longitude' => null,
            'photo' => $dummyBase64,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['gps']);
        $this->assertEquals(
            'Location permission is required to record attendance. Please enable Location access and try again.',
            $response->json('errors.gps.0')
        );

        // Verify rejected event created but NO verified attendance event
        $this->assertDatabaseHas('attendance_events', [
            'employee_id' => $this->employee->id,
            'status' => 'rejected',
        ]);
        $this->assertDatabaseMissing('attendance_events', [
            'employee_id' => $this->employee->id,
            'status' => 'verified',
        ]);
        $this->assertDatabaseMissing('hrm_timesheets', [
            'employee_id' => $this->employee->id,
        ]);

        // Verify audit log created for Admin visibility
        $this->assertDatabaseHas('attendance_audit_logs', [
            'employee_id' => $this->employee->id,
            'action' => 'attendance_gps_denied',
        ]);
    }

    /**
     * 15. Poor GPS accuracy (> 100 meters) is rejected with measured accuracy.
     */
    public function test_poor_gps_accuracy_is_rejected(): void
    {
        AttendanceSetting::set('max_gps_accuracy_meters', 100, 'integer');

        $reg = $this->service->registerDevice($this->employee, ['device_name' => 'Test Phone']);
        $device = $reg['device'];
        $dummyBase64 = 'data:image/jpeg;base64,' . base64_encode('photo');

        $response = $this->withSession([
            'attendance_employee_id' => $this->employee->id,
            'attendance_device_id' => $device->id,
        ])->postJson(route('attendance.api.check_in'), [
            'latitude' => 27.6976748,
            'longitude' => 85.3664331,
            'accuracy_meters' => 350, // 350m is terrible accuracy
            'photo' => $dummyBase64,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['gps']);
        $this->assertStringContainsString('350m', $response->json('errors.gps.0'));

        // Check that a rejected audit record was made
        $this->assertDatabaseHas('attendance_events', [
            'employee_id' => $this->employee->id,
            'status' => 'rejected',
            'accuracy_meters' => 350,
        ]);
        $this->assertDatabaseMissing('attendance_events', [
            'employee_id' => $this->employee->id,
            'status' => 'verified',
        ]);
    }

    /**
     * 16. Client GPS failure endpoint logs audit record for Admin visibility.
     */
    public function test_client_gps_failure_logging_creates_admin_audit_record(): void
    {
        $reg = $this->service->registerDevice($this->employee, ['device_name' => 'Test Phone']);
        $device = $reg['device'];

        $response = $this->withSession([
            'attendance_employee_id' => $this->employee->id,
            'attendance_device_id' => $device->id,
        ])->postJson(route('attendance.api.log_gps_failure'), [
            'action_type' => 'check_in',
            'error_type' => 'permission_denied',
            'message' => 'Location permission is required to record attendance. Please enable Location access and try again.',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'logged' => true]);

        $this->assertDatabaseHas('attendance_audit_logs', [
            'employee_id' => $this->employee->id,
            'action' => 'gps_failure_permission_denied',
        ]);
    }

    /**
     * 15. Outside Geofence radius is rejected.
     */
    public function test_outside_geofence_is_rejected(): void
    {
        AttendanceSetting::set('geofencing_enabled', true, 'boolean');

        $reg = $this->service->registerDevice($this->employee, ['device_name' => 'Test Phone']);
        $device = $reg['device'];
        $dummyBase64 = 'data:image/jpeg;base64,' . base64_encode('photo');

        // Location 5km away (Pokhara or distant KTM)
        $response = $this->withSession([
            'attendance_employee_id' => $this->employee->id,
            'attendance_device_id' => $device->id,
        ])->postJson(route('attendance.api.check_in'), [
            'latitude' => 27.7500000,
            'longitude' => 85.4500000,
            'accuracy_meters' => 15,
            'photo' => $dummyBase64,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['geofence']);
    }

    /**
     * 16. Showroom geofence radius is dynamically configurable and strictly enforced.
     */
    public function test_geofence_radius_is_configurable_and_enforced(): void
    {
        AttendanceSetting::set('geofencing_enabled', true, 'boolean');

        // Configure Showroom location with 27.6976748, 85.3664331 and 100m radius
        $this->location->update([
            'latitude' => 27.6976748,
            'longitude' => 85.3664331,
            'radius_meters' => 100,
            'is_default' => true,
            'is_active' => true,
        ]);

        $reg = $this->service->registerDevice($this->employee, ['device_name' => 'Test Phone']);
        $device = $reg['device'];
        $dummyBase64 = 'data:image/jpeg;base64,' . base64_encode('photo');

        // Coordinate ~150 meters north of showroom (27.6990200, 85.3664331 is approx 149m away)
        $outsideLat = 27.6990200;
        $outsideLon = 85.3664331;
        $dist = $this->location->calculateDistanceTo($outsideLat, $outsideLon);
        $this->assertGreaterThan(100, $dist);
        $this->assertLessThan(200, $dist);

        // 1. With 100m radius -> Rejected with 422
        $res1 = $this->withSession([
            'attendance_employee_id' => $this->employee->id,
            'attendance_device_id' => $device->id,
        ])->postJson(route('attendance.api.check_in'), [
            'latitude' => $outsideLat,
            'longitude' => $outsideLon,
            'accuracy_meters' => 10,
            'photo' => $dummyBase64,
        ]);
        $res1->assertStatus(422);
        $res1->assertJsonValidationErrors(['geofence']);

        // 2. Admin configures geofence radius to 250 meters
        $this->location->update(['radius_meters' => 250]);
        $this->assertTrue($this->location->fresh()->isWithinGeofence($outsideLat, $outsideLon));

        // 3. Same coordinate now succeeds with 200 OK
        $res2 = $this->withSession([
            'attendance_employee_id' => $this->employee->id,
            'attendance_device_id' => $device->id,
        ])->postJson(route('attendance.api.check_in'), [
            'latitude' => $outsideLat,
            'longitude' => $outsideLon,
            'accuracy_meters' => 10,
            'photo' => $dummyBase64,
        ]);
        $res2->assertStatus(200);
        $res2->assertJson(['success' => true]);
    }

    /**
     * 17. Idempotency key prevents duplicate attendance events.
     */
    public function test_idempotency_key_prevents_duplicate_attendance(): void
    {
        $reg = $this->service->registerDevice($this->employee, ['device_name' => 'Test Phone']);
        $device = $reg['device'];
        $dummyBase64 = 'data:image/jpeg;base64,' . base64_encode('photo');
        $key = 'IDEMPOTENCY_TEST_KEY_123';

        // First call
        $res1 = $this->withSession([
            'attendance_employee_id' => $this->employee->id,
            'attendance_device_id' => $device->id,
        ])->postJson(route('attendance.api.check_in'), [
            'latitude' => 27.6976748,
            'longitude' => 85.3664331,
            'accuracy_meters' => 10,
            'photo' => $dummyBase64,
            'idempotency_key' => $key,
        ]);
        $res1->assertStatus(200);

        // Repeated call with same idempotency key
        $res2 = $this->withSession([
            'attendance_employee_id' => $this->employee->id,
            'attendance_device_id' => $device->id,
        ])->postJson(route('attendance.api.check_in'), [
            'latitude' => 27.6976748,
            'longitude' => 85.3664331,
            'accuracy_meters' => 10,
            'photo' => $dummyBase64,
            'idempotency_key' => $key,
        ]);
        $res2->assertStatus(200);

        // Exactly one event created
        $this->assertEquals(1, AttendanceEvent::where('idempotency_key', $key)->count());
    }

    /**
     * 17. Employee data isolation: employee sees only their own history.
     */
    public function test_employee_sees_only_own_attendance_history(): void
    {
        $reg = $this->service->registerDevice($this->employee, ['device_name' => 'Test Phone']);
        $device = $reg['device'];
        $dummyBase64 = 'data:image/jpeg;base64,' . base64_encode('photo');

        // Check in employee 1
        $this->service->submitAttendance($device, 'check_in', [
            'latitude' => 27.6976748,
            'longitude' => 85.3664331,
            'accuracy_meters' => 10,
        ], $dummyBase64);

        // Create employee 2 with event
        $emp2 = Employee::create([
            'employee_number' => 'TEST-EMP-888',
            'first_name' => 'Other',
            'last_name' => 'Employee',
            'email' => 'other.emp@laijau.com',
            'phone' => '9841888777',
            'hire_date' => now()->toDateString(),
            'status' => 'active',
            'attendance_access_enabled' => true,
        ]);
        $reg2 = $this->service->registerDevice($emp2, ['device_name' => 'Phone 2']);
        $this->service->submitAttendance($reg2['device'], 'check_in', [
            'latitude' => 27.6976748,
            'longitude' => 85.3664331,
            'accuracy_meters' => 10,
        ], $dummyBase64);

        // Fetch history as employee 1
        $response = $this->withSession([
            'attendance_employee_id' => $this->employee->id,
            'attendance_device_id' => $device->id,
        ])->getJson(route('attendance.api.history'));

        $response->assertStatus(200);
        $history = $response->json('history');

        // Only 1 item in employee 1's history
        $this->assertCount(1, $history);
    }

    /**
     * 18. Persistent device authorization auto-rehydrates session without re-pairing.
     */
    public function test_persistent_device_authorization_auto_rehydrates_session(): void
    {
        $reg = $this->service->registerDevice($this->employee, ['device_name' => 'Test Phone']);
        $device = $reg['device'];
        $rawToken = $reg['device_token'];

        // Access dashboard with device cookie only (clean empty session, simulating reopened browser)
        $response = $this->withUnencryptedCookie('laijau_attendance_device', $rawToken)
            ->get(route('attendance.dashboard'));

        $response->assertStatus(200);
        $response->assertSee($this->employee->first_name);
        $this->assertEquals($this->employee->id, session('attendance_employee_id'));
        $this->assertEquals($device->id, session('attendance_device_id'));
    }

    /**
     * 19. Clocked in employee always sees checked_in state when reopening.
     */
    public function test_clocked_in_employee_sees_checked_in_state_on_reopening(): void
    {
        $reg = $this->service->registerDevice($this->employee, ['device_name' => 'Test Phone']);
        $device = $reg['device'];
        $rawToken = $reg['device_token'];

        // Clock in
        $this->service->submitAttendance($device, 'check_in', [
            'latitude' => 27.6976748,
            'longitude' => 85.3664331,
            'accuracy_meters' => 10,
        ]);

        // State check from API
        $statusRes = $this->withHeader('X-Device-Token', $rawToken)
            ->withUnencryptedCookie('laijau_attendance_device', $rawToken)
            ->getJson(route('attendance.api.status'));

        $statusRes->assertStatus(200);
        $statusRes->assertJsonPath('state.state', 'checked_in');
        $this->assertNotNull($statusRes->json('state.check_in_time'));

        // Dashboard view check
        $dashRes = $this->withUnencryptedCookie('laijau_attendance_device', $rawToken)
            ->get(route('attendance.dashboard'));
        $dashRes->assertStatus(200);
        $dashRes->assertSee('CHECK OUT');
    }

    /**
     * 20. PWA manifest and service worker routes return proper content types.
     */
    public function test_pwa_manifest_and_service_worker(): void
    {
        $manifestRes = $this->get(route('attendance.manifest'));
        $manifestRes->assertStatus(200);
        $manifestRes->assertHeader('Content-Type', 'application/manifest+json; charset=utf-8');
        $manifestRes->assertJson(['name' => 'Laijau Employee Attendance', 'display' => 'standalone']);

        $swRes = $this->get(route('attendance.sw'));
        $swRes->assertStatus(200);
        $swRes->assertHeader('Content-Type', 'application/javascript; charset=utf-8');
        $swRes->assertHeader('Service-Worker-Allowed', '/attendance');

        $offlineRes = $this->get(route('attendance.offline'));
        $offlineRes->assertStatus(200);
    }

    /**
     * 21. Invalid or out-of-range GPS coordinates are rejected and do not create attendance.
     */
    public function test_invalid_gps_coordinates_are_rejected(): void
    {
        $reg = $this->service->registerDevice($this->employee, ['device_name' => 'Test Phone']);
        $device = $reg['device'];
        $dummyBase64 = 'data:image/jpeg;base64,' . base64_encode('photo');

        // Latitude > 90 is physically impossible
        $response = $this->withSession([
            'attendance_employee_id' => $this->employee->id,
            'attendance_device_id' => $device->id,
        ])->postJson(route('attendance.api.check_in'), [
            'latitude' => 999.0,
            'longitude' => 85.3664331,
            'photo' => $dummyBase64,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['gps']);

        $this->assertDatabaseMissing('attendance_events', [
            'employee_id' => $this->employee->id,
            'status' => 'verified',
        ]);
    }

    /**
     * 22. Check-out also strictly enforces mandatory GPS and rejects missing coordinates.
     */
    public function test_check_out_strictly_requires_gps(): void
    {
        $reg = $this->service->registerDevice($this->employee, ['device_name' => 'Test Phone']);
        $device = $reg['device'];
        $dummyBase64 = 'data:image/jpeg;base64,' . base64_encode('photo');

        // Check in first with valid GPS
        $this->service->submitAttendance($device, 'check_in', [
            'latitude' => 27.6976748,
            'longitude' => 85.3664331,
            'accuracy_meters' => 15.0,
        ], $dummyBase64);

        // Attempt check-out without GPS coordinates
        $response = $this->withSession([
            'attendance_employee_id' => $this->employee->id,
            'attendance_device_id' => $device->id,
        ])->postJson(route('attendance.api.check_out'), [
            'latitude' => null,
            'longitude' => null,
            'photo' => $dummyBase64,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['gps']);
        $this->assertEquals(
            'Location permission is required to record attendance. Please enable Location access and try again.',
            $response->json('errors.gps.0')
        );

        // Confirm no verified check-out event was recorded
        $this->assertDatabaseMissing('attendance_events', [
            'employee_id' => $this->employee->id,
            'type' => 'check_out',
            'status' => 'verified',
        ]);
    }

    /**
     * 23. Valid employee verification (Step 1) returns identity without persisting a device.
     */
    public function test_valid_employee_verification_returns_identity_without_persisting_device(): void
    {
        $response = $this->postJson(route('attendance.api.verify'), [
            'employee_number' => 'TEST-EMP-999',
            'phone' => '9841999888',
            'pin' => '1234',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'employee' => [
                'id' => $this->employee->id,
                'name' => 'Suman Adhikari',
                'code' => 'TEST-EMP-999',
            ],
        ]);

        // Verify Step 1 NEVER creates an attendance device
        $this->assertEquals(0, AttendanceDevice::where('employee_id', $this->employee->id)->count());
        $this->assertNull(session('attendance_employee_id'));
    }

    /**
     * 24. Invalid employee verification (Step 1) rejects bad PIN and tracks failed attempts.
     */
    public function test_invalid_employee_verification_rejects_bad_pin(): void
    {
        $response = $this->postJson(route('attendance.api.verify'), [
            'employee_number' => 'TEST-EMP-999',
            'phone' => '9841999888',
            'pin' => '9999',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['pin']);

        $this->employee->refresh();
        $this->assertEquals(1, $this->employee->attendance_failed_attempts);
        $this->assertEquals(0, AttendanceDevice::where('employee_id', $this->employee->id)->count());
    }

    /**
     * 25. Pairing succeeds with credentials only without requiring photo.
     */
    public function test_pairing_succeeds_without_photo_and_persists_device(): void
    {
        $response = $this->postJson(route('attendance.api.setup'), [
            'employee_number' => 'TEST-EMP-999',
            'phone' => '9841999888',
            'pin' => '1234',
            'device_name' => 'iPhone Test',
            // No photo payload
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertCookie('laijau_attendance_device');

        $this->assertEquals(1, AttendanceDevice::where('employee_id', $this->employee->id)->where('is_active', true)->count());
    }

    /**
     * 26. Successful pairing persists device, saves selfie evidence, and returns employee identity.
     */
    public function test_successful_pairing_persists_device_and_returns_identity(): void
    {
        $dummyBase64 = 'data:image/jpeg;base64,' . base64_encode('selfie-evidence-jpg');

        $response = $this->postJson(route('attendance.api.setup'), [
            'employee_number' => 'TEST-EMP-999',
            'phone' => '9841999888',
            'pin' => '1234',
            'device_name' => 'Pixel 8 Pro',
            'platform' => 'Android',
            'browser' => 'Chrome',
            'photo' => $dummyBase64,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'employee' => [
                'id' => $this->employee->id,
                'name' => 'Suman Adhikari',
                'code' => 'TEST-EMP-999',
            ],
        ]);
        $response->assertCookie('laijau_attendance_device');

        $device = AttendanceDevice::where('employee_id', $this->employee->id)->first();
        $this->assertNotNull($device);
        $this->assertTrue($device->is_active);
        $this->assertEquals('Pixel 8 Pro', $device->device_name);
        $this->assertEquals($this->employee->id, session('attendance_employee_id'));
    }

    /**
     * 27. Failed pairing does not activate device or create session.
     */
    public function test_failed_pairing_does_not_activate_device(): void
    {
        $dummyBase64 = 'data:image/jpeg;base64,' . base64_encode('selfie-evidence-jpg');

        $response = $this->postJson(route('attendance.api.setup'), [
            'employee_number' => 'TEST-EMP-999',
            'phone' => '9841999888',
            'pin' => '0000', // incorrect pin
            'photo' => $dummyBase64,
        ]);

        $response->assertStatus(422);
        $this->assertEquals(0, AttendanceDevice::where('employee_id', $this->employee->id)->count());
        $this->assertNull(session('attendance_employee_id'));
    }

    /**
     * 28. Unauthorized attendance request is rejected.
     */
    public function test_unauthorized_attendance_request_rejected(): void
    {
        // No session, no cookie, no token
        $response = $this->postJson(route('attendance.api.check_in'), [
            'latitude' => 27.6976748,
            'longitude' => 85.3664331,
            'photo' => 'data:image/jpeg;base64,' . base64_encode('fake'),
        ]);

        // AttendanceEmployeeAuth middleware rejects unauthenticated requests
        $this->assertTrue(in_array($response->status(), [401, 403, 302]));
    }
}
