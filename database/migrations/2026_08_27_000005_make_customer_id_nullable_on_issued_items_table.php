<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // If we are running on MySQL, safely look up and drop the existing foreign key
        if (DB::getDriverName() === 'mysql') {
            $fk = DB::selectOne("
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'issued_items'
                AND COLUMN_NAME = 'customer_id'
                AND REFERENCED_TABLE_NAME IS NOT NULL
                LIMIT 1
            ");

            if ($fk) {
                DB::statement("ALTER TABLE issued_items DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            }

            DB::statement("ALTER TABLE issued_items MODIFY customer_id BIGINT UNSIGNED NULL");
            DB::statement("ALTER TABLE issued_items ADD CONSTRAINT issued_items_customer_id_foreign
                FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL");
        } else {
            // Safe fallback for your local SQLite database
            Schema::table('issued_items', function (Blueprint $table) {
                $table->unsignedBigInteger('customer_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE issued_items DROP FOREIGN KEY issued_items_customer_id_foreign");
            DB::statement("ALTER TABLE issued_items MODIFY customer_id BIGINT UNSIGNED NOT NULL");
            DB::statement("ALTER TABLE issued_items ADD CONSTRAINT issued_items_customer_id_foreign
                FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE");
        } else {
            Schema::table('issued_items', function (Blueprint $table) {
                $table->unsignedBigInteger('customer_id')->nullable(false)->change();
            });
        }
    }
};
