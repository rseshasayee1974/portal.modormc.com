<?php

/*
Author: ragul-onemodo
Created: 2026-11-05 15:15:00 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mm_sales_order_email_deliveries')) {
            Schema::create('mm_sales_order_email_deliveries', function (Blueprint $table) {
                $table->engine('InnoDB');
                $table->id();
                $table->unsignedBigInteger('sales_order_id');
                $table->string('recipient', 100);
                $table->string('status', 12)->default('pending');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->dateTime('available_at');
                $table->dateTime('sent_at')->nullable();
                $table->timestamps();
            });
        }

        // MySQL DDL can leave the table behind when a later constraint fails.
        if (! Schema::hasIndex('mm_sales_order_email_deliveries', 'so_email_order_recipient_unique')) {
            Schema::table('mm_sales_order_email_deliveries', function (Blueprint $table) {
                $table->unique(['sales_order_id', 'recipient'], 'so_email_order_recipient_unique');
            });
        }
        if (! Schema::hasIndex('mm_sales_order_email_deliveries', 'so_email_pending_idx')) {
            Schema::table('mm_sales_order_email_deliveries', function (Blueprint $table) {
                $table->index(['status', 'available_at', 'id'], 'so_email_pending_idx');
            });
        }

        // Legacy MyISAM sales-order tables cannot be referenced by an InnoDB FK.
        $connection = Schema::getConnection();
        if (in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            $parent = $connection->selectOne(
                'SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
                [$connection->getDatabaseName(), $connection->getTablePrefix().'mm_sales_orders']
            );
            if (strtolower($parent->engine ?? '') === 'myisam') {
                return;
            }
        }

        $foreignKeys = Schema::getForeignKeys('mm_sales_order_email_deliveries');
        if (! collect($foreignKeys)->contains(fn (array $key) => $key['columns'] === ['sales_order_id'])) {
            Schema::table('mm_sales_order_email_deliveries', function (Blueprint $table) {
                $table->foreign('sales_order_id')->references('id')->on('mm_sales_orders')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_sales_order_email_deliveries');
    }
};
