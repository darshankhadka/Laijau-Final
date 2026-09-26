<?php

namespace Tests\Feature;

use App\Models\Hrm\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AttendanceLoginScreenTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_attendance_login_screen_renders_with_mobile_responsive_elements(): void
    {
        $response = $this->get(route('attendance.login'));

        $response->assertStatus(200);

        // Header and branding
        $response->assertSee('Laijau Attendance');
        $response->assertSee('Enter your employee attendance PIN to sign in');

        // Button states: Idle & Loading
        $response->assertSee('Unlock Dashboard');
        $response->assertSee('att-arrow-icon', false);
        $response->assertSee('Authenticating...');
        $response->assertSee('att-spinner-icon', false);

        // Touch Keypad elements
        $response->assertSee('att-keypad-grid', false);
        $response->assertSee(':aria-label="\'Digit \' + digit"', false);
        $response->assertSee('Clear PIN');
        $response->assertSee('Delete last digit');

        // Responsive styles
        $response->assertSee('100dvh', false);
        $response->assertSee('att-pin-screen', false);
        $response->assertSee('max-height: 640px', false);
    }

    public function test_attendance_pin_authentication_api_endpoint(): void
    {
        $pin = '987654';
        $employee = Employee::create([
            'employee_number' => 'EMP-TEST-' . uniqid(),
            'first_name' => 'Ram',
            'last_name' => 'Bahadur',
            'phone' => '9801' . rand(100000, 999999),
            'email' => 'ram.' . uniqid() . '@laijau.com',
            'hire_date' => now()->toDateString(),
            'status' => 'active',
            'attendance_access_enabled' => true,
            'attendance_failed_attempts' => 0,
        ]);
        $employee->setAttendancePin('987654');

        // 1. Submit invalid PIN
        $badRes = $this->postJson('/attendance/api/auth/pin', [
            'pin' => '0000',
            'device_name' => 'Android (Chrome)',
        ]);
        $badRes->assertStatus(422);

        // 2. Submit valid PIN
        $goodRes = $this->postJson('/attendance/api/auth/pin', [
            'pin' => $pin,
            'device_name' => 'Android (Chrome)',
        ]);
        $goodRes->assertStatus(200);
        $goodRes->assertJsonStructure(['success', 'redirect', 'token', 'employee']);
        $this->assertEquals(route('attendance.dashboard'), $goodRes->json('redirect'));
    }

    public function test_content_security_policy_permits_jsdelivr_for_alpine_js(): void
    {
        $htaccessPath = public_path('.htaccess');
        $this->assertFileExists($htaccessPath);
        $content = file_get_contents($htaccessPath);

        // Verify Content-Security-Policy header configuration in .htaccess
        $this->assertStringContainsString('Header always set Content-Security-Policy', $content);

        // Verify script-src explicitly allows https://cdn.jsdelivr.net
        $this->assertMatchesRegularExpression("/script-src[^;]*https:\/\/cdn\.jsdelivr\.net/", $content);

        // Verify strict security directives are preserved and not disabled or weakened
        $this->assertStringContainsString("default-src 'self'", $content);
        $this->assertStringContainsString("style-src 'self'", $content);
        $this->assertStringContainsString("font-src 'self'", $content);
        $this->assertStringContainsString("img-src 'self'", $content);
        $this->assertStringContainsString("connect-src 'self'", $content);
        $this->assertStringContainsString("frame-src 'self'", $content);

        // Verify /attendance view references the Alpine CDN script matching the CSP rule
        $response = $this->get(route('attendance.login'));
        $response->assertSee('https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js', false);
    }
}
