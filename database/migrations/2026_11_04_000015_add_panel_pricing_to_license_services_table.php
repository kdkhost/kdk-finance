<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('license_services', function (Blueprint $table) {
            $table->string('panel_service_id', 40)->nullable()->after('license_catalog_item_id');
            $table->decimal('panel_price', 10, 2)->nullable()->after('panel_service_id');
            $table->string('panel_currency', 3)->nullable()->after('panel_price');
            $table->string('panel_billing_cycle', 20)->nullable()->after('panel_currency');
            $table->date('panel_next_due_date')->nullable()->after('panel_billing_cycle');
            $table->string('panel_status', 20)->nullable()->after('panel_next_due_date');
            $table->timestamp('panel_synced_at')->nullable()->after('panel_status');

            $table->index('panel_service_id');
        });
    }

    public function down(): void
    {
        Schema::table('license_services', function (Blueprint $table) {
            $table->dropIndex(['panel_service_id']);
            $table->dropColumn([
                'panel_service_id',
                'panel_price',
                'panel_currency',
                'panel_billing_cycle',
                'panel_next_due_date',
                'panel_status',
                'panel_synced_at',
            ]);
        });
    }
};
