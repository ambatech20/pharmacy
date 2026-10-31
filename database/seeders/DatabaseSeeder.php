<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Local / development seed: the essentials every install needs, plus a
     * starter catalog of demo drugs and products.
     *
     * For a real deployment run ONLY the essentials, so the demo catalog is
     * not inserted into the live catalog:
     *
     *     php artisan db:seed --class=Database\Seeders\ProductionSeeder --force
     *
     * Idempotent: re-running creates no duplicates.
     */
    public function run()
    {
        // Shared essentials: main branch, chart of accounts, expense
        // categories, branding defaults and the first administrator.
        $this->call(ProductionSeeder::class);

        // Demo catalog for local development only.
        $this->call(CatalogSeeder::class);

        // The demo catalog still describes medical supplies and antiseptics as
        // products, the shape they had before the tax-relief move. Convert
        // them here so a fresh local install matches the live catalogue.
        app(\App\Services\CatalogueMoveService::class)->apply();
    }
}
