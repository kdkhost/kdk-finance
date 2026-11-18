<?php

use App\Models\AppSetting;
use App\Models\LicenseCatalogItem;
use App\Models\LicenseService;
use App\Services\IDriveE2PanelService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            $monthlyCatalog = LicenseCatalogItem::query()
                ->where('provider', IDriveE2PanelService::PROVIDER)
                ->where('billing_cycle', LicenseService::CYCLE_MONTHLY)
                ->orderBy('price')
                ->first();

            LicenseService::query()
                ->where('provider', IDriveE2PanelService::PROVIDER)
                ->each(function (LicenseService $svc) use ($monthlyCatalog): void {
                    $svc->unit_price = '5.00';
                    $svc->panel_price = '6.00';
                    $svc->billing_cycle = LicenseService::CYCLE_MONTHLY;
                    $svc->panel_billing_cycle = LicenseService::CYCLE_MONTHLY;
                    if ($monthlyCatalog) {
                        $svc->license_catalog_item_id = $monthlyCatalog->id;
                    }
                    if ($svc->next_due_date && $svc->next_due_date->greaterThan(now()->addMonths(2))) {
                        $svc->next_due_date = now()->addMonthNoOverflow()->startOfDay();
                        $svc->panel_next_due_date = $svc->next_due_date;
                    }
                    $svc->save();
                });

            AppSetting::set('idrivee2_default_cycle', 'monthly', 'idrivee2');
            AppSetting::set('idrivee2_default_price', '6.00', 'idrivee2');
        } catch (\Throwable) {
            // Se tabela ainda não existir, prossegue sem travar migrações
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
