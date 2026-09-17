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
        if (!Schema::hasTable('module_settings')) {
            Schema::create('module_settings', function (Blueprint $table) {
                $table->id();
                $table->string('module', 50)->index();
                $table->string('group', 50)->index();
                $table->string('key', 100);
                $table->longText('value')->nullable();
                $table->string('value_type', 30)->default('string');
                $table->longText('default_value')->nullable();
                $table->text('description')->nullable();
                $table->text('validation_rules')->nullable();
                $table->json('options')->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_enabled')->default(true)->index();
                $table->boolean('is_public')->default(false)->index();
                $table->boolean('is_sensitive')->default(false);
                $table->boolean('is_editable')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['module', 'key'], 'idx_module_key_unique');
                $table->index(['module', 'group'], 'idx_module_group');
            });
        }

        if (!Schema::hasTable('module_setting_audit_logs')) {
            Schema::create('module_setting_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('module_setting_id')->nullable()->constrained('module_settings')->nullOnDelete();
                $table->string('module', 50)->index();
                $table->string('group', 50)->nullable();
                $table->string('key', 100)->index();
                $table->longText('old_value')->nullable();
                $table->longText('new_value')->nullable();
                $table->string('action', 50)->default('updated');
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('user_email', 255)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();

                $table->index(['module', 'key'], 'idx_audit_module_key');
            });
        }

        if (!Schema::hasTable('module_statuses')) {
            Schema::create('module_statuses', function (Blueprint $table) {
                $table->id();
                $table->string('module', 50)->unique();
                $table->boolean('is_enabled')->default(true)->index();
                $table->text('disabled_reason')->nullable();
                $table->dateTime('disabled_at')->nullable();
                $table->foreignId('disabled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_setting_audit_logs');
        Schema::dropIfExists('module_settings');
        Schema::dropIfExists('module_statuses');
    }
};
