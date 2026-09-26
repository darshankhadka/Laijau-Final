<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create attendance_sessions table
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('timesheet_id')->nullable();
            $table->date('date');
            $table->unsignedInteger('session_number')->default(1);
            $table->string('status', 30)->default('open'); // 'open', 'completed'

            // Clock In details
            $table->timestamp('clock_in_at');
            $table->time('clock_in_time')->nullable();
            $table->unsignedBigInteger('clock_in_event_id')->nullable();
            $table->decimal('clock_in_latitude', 10, 7)->nullable();
            $table->decimal('clock_in_longitude', 10, 7)->nullable();
            $table->decimal('clock_in_accuracy', 8, 2)->nullable();
            $table->string('clock_in_location_name')->nullable();
            $table->text('clock_in_device_info')->nullable();
            $table->string('clock_in_ip', 45)->nullable();

            // Clock Out details
            $table->timestamp('clock_out_at')->nullable();
            $table->time('clock_out_time')->nullable();
            $table->unsignedBigInteger('clock_out_event_id')->nullable();
            $table->decimal('clock_out_latitude', 10, 7)->nullable();
            $table->decimal('clock_out_longitude', 10, 7)->nullable();
            $table->decimal('clock_out_accuracy', 8, 2)->nullable();
            $table->string('clock_out_location_name')->nullable();
            $table->text('clock_out_device_info')->nullable();
            $table->string('clock_out_ip', 45)->nullable();

            // Duration calculation
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->decimal('duration_hours', 6, 2)->default(0.00);
            $table->string('duration_formatted', 50)->default('0h 0m');

            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'date']);
            $table->index(['employee_id', 'status']);
            $table->index('date');
            $table->index('status');
            $table->foreign('employee_id')->references('id')->on('hrm_employees')->onDelete('cascade');
            $table->foreign('timesheet_id')->references('id')->on('hrm_timesheets')->onDelete('set null');
        });

        // 2. Add session_id to attendance_events
        Schema::table('attendance_events', function (Blueprint $table) {
            $table->unsignedBigInteger('session_id')->nullable()->after('employee_id')->index();
        });

        // 3. Add multi-session aggregation fields to hrm_timesheets
        Schema::table('hrm_timesheets', function (Blueprint $table) {
            $table->unsignedInteger('total_sessions')->default(0)->after('attendance_status');
            $table->unsignedInteger('total_worked_minutes')->default(0)->after('total_sessions');
            $table->decimal('total_worked_hours', 6, 2)->default(0.00)->after('total_worked_minutes');
            $table->json('sessions_summary')->nullable()->after('total_worked_hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hrm_timesheets', function (Blueprint $table) {
            $table->dropColumn(['total_sessions', 'total_worked_minutes', 'total_worked_hours', 'sessions_summary']);
        });

        Schema::table('attendance_events', function (Blueprint $table) {
            $table->dropColumn('session_id');
        });

        Schema::dropIfExists('attendance_sessions');
    }
};
