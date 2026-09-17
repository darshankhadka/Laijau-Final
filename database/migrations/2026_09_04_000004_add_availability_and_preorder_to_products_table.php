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
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'availability_status')) {
                $table->string('availability_status', 50)->default('available')->after('is_active');
            }
            if (!Schema::hasColumn('products', 'allow_preorder')) {
                $table->boolean('allow_preorder')->default(false)->after('availability_status');
            }
            if (!Schema::hasColumn('products', 'preorder_expected_dispatch')) {
                $table->string('preorder_expected_dispatch', 100)->nullable()->default('15–20 days')->after('allow_preorder');
            }
            if (!Schema::hasColumn('products', 'preorder_limit')) {
                $table->unsignedInteger('preorder_limit')->nullable()->after('preorder_expected_dispatch');
            }
            if (!Schema::hasColumn('products', 'preorder_count')) {
                $table->unsignedInteger('preorder_count')->default(0)->after('preorder_limit');
            }
            if (!Schema::hasColumn('products', 'preorder_price_npr')) {
                $table->decimal('preorder_price_npr', 10, 2)->nullable()->after('preorder_count');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'is_preorder')) {
                $table->boolean('is_preorder')->default(false)->after('unit_price');
            }
            if (!Schema::hasColumn('order_items', 'preorder_dispatch_note')) {
                $table->string('preorder_dispatch_note', 150)->nullable()->after('is_preorder');
            }
        });

        if (!Schema::hasTable('restock_requests')) {
            Schema::create('restock_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('email');
                $table->string('phone', 50)->nullable();
                $table->string('size', 50)->nullable();
                $table->string('color', 50)->nullable();
                $table->string('status', 30)->default('pending'); // pending, notified, cancelled
                $table->timestamp('notified_at')->nullable();
                $table->timestamps();

                $table->index(['product_id', 'status']);
                $table->index(['email', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restock_requests');

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['is_preorder', 'preorder_dispatch_note']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'availability_status',
                'allow_preorder',
                'preorder_expected_dispatch',
                'preorder_limit',
                'preorder_count',
                'preorder_price_npr',
            ]);
        });
    }
};
