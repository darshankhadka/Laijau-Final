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
        if (!Schema::hasTable('crm_leads')) {
            Schema::create('crm_leads', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('contact_name');
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->string('channel')->default('whatsapp'); // 'whatsapp', 'showroom', 'concierge_form', 'instagram', 'phone', 'referral'
                $table->string('stage')->default('new_inquiry'); // 'new_inquiry', 'consultation', 'measurements_sampling', 'quotation_proposal', 'converted', 'closed_lost'
                $table->decimal('estimated_value', 12, 2)->default(0.00);
                $table->string('currency', 3)->default('npr');
                $table->string('priority')->default('medium'); // 'urgent', 'high', 'medium', 'low'
                $table->string('assigned_to')->nullable();
                $table->date('event_date')->nullable();
                $table->text('bespoke_notes')->nullable();
                $table->text('internal_notes')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_leads');
    }
};
