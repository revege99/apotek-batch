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
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name')->unique();
            $table->decimal('markup_percentage', 5, 2)->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('customer_group_id')->nullable()->constrained('customer_groups')->nullOnDelete();
            $table->string('name')->index();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('payment_method')->constrained('customers')->nullOnDelete();
            $table->foreignId('customer_group_id')->nullable()->after('customer_id')->constrained('customer_groups')->nullOnDelete();
            $table->decimal('customer_group_markup_percentage', 5, 2)->default(0)->after('customer_phone');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 14, 2)->default(0)->after('quantity');
            $table->decimal('markup_percentage', 5, 2)->default(0)->after('unit_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['unit_cost', 'markup_percentage']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_group_id');
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn('customer_group_markup_percentage');
        });

        Schema::dropIfExists('customers');
        Schema::dropIfExists('customer_groups');
    }
};
