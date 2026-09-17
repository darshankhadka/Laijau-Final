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
        // 1. Additive enhancements to crm_leads
        Schema::table('crm_leads', function (Blueprint $table) {
            if (!Schema::hasColumn('crm_leads', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('crm_leads', 'product_id')) {
                $table->foreignId('product_id')->nullable()->after('customer_id')->constrained('products')->nullOnDelete();
            }
            if (!Schema::hasColumn('crm_leads', 'order_id')) {
                $table->foreignId('order_id')->nullable()->after('product_id')->constrained('orders')->nullOnDelete();
            }
            if (!Schema::hasColumn('crm_leads', 'assigned_staff_id')) {
                $table->foreignId('assigned_staff_id')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('crm_leads', 'follow_up_date')) {
                $table->dateTime('follow_up_date')->nullable()->after('event_date');
            }
            if (!Schema::hasColumn('crm_leads', 'follow_up_notes')) {
                $table->text('follow_up_notes')->nullable()->after('follow_up_date');
            }
            if (!Schema::hasColumn('crm_leads', 'lost_reason')) {
                $table->string('lost_reason')->nullable()->after('internal_notes');
            }
            if (!Schema::hasColumn('crm_leads', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('lost_reason');
            }
        });

        // 2. Additive enhancements to contact_messages
        Schema::table('contact_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('contact_messages', 'phone')) {
                $table->string('phone')->nullable()->after('email');
            }
            if (!Schema::hasColumn('contact_messages', 'inquiry_type')) {
                $table->string('inquiry_type')->default('general')->after('subject');
            }
            if (!Schema::hasColumn('contact_messages', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->after('inquiry_type')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('contact_messages', 'order_id')) {
                $table->foreignId('order_id')->nullable()->after('customer_id')->constrained('orders')->nullOnDelete();
            }
            if (!Schema::hasColumn('contact_messages', 'product_id')) {
                $table->foreignId('product_id')->nullable()->after('order_id')->constrained('products')->nullOnDelete();
            }
            if (!Schema::hasColumn('contact_messages', 'assigned_staff_id')) {
                $table->foreignId('assigned_staff_id')->nullable()->after('product_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('contact_messages', 'priority')) {
                $table->string('priority')->default('medium')->after('status');
            }
            if (!Schema::hasColumn('contact_messages', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable()->after('reply_notes');
            }
        });

        // 3. Create crm_activities audit timeline table
        if (!Schema::hasTable('crm_activities')) {
            Schema::create('crm_activities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('crm_lead_id')->constrained('crm_leads')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('type'); // stage_change, note, call, whatsapp, follow_up, conversion, order_linked
                $table->text('description');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['crm_lead_id', 'created_at']);
                $table->index('type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_activities');

        Schema::table('contact_messages', function (Blueprint $table) {
            $cols = ['resolved_at', 'priority', 'assigned_staff_id', 'product_id', 'order_id', 'customer_id', 'inquiry_type', 'phone'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('contact_messages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('crm_leads', function (Blueprint $table) {
            $cols = ['closed_at', 'lost_reason', 'follow_up_notes', 'follow_up_date', 'assigned_staff_id', 'order_id', 'product_id', 'customer_id'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('crm_leads', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
