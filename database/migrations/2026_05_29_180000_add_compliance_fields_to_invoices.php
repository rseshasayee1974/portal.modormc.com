<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mm_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('mm_invoices', 'einvoice_irn')) {
                $table->string('einvoice_irn', 64)->nullable()->after('einvoice_status');
                $table->string('einvoice_ack_no', 20)->nullable()->after('einvoice_irn');
                $table->dateTime('einvoice_ack_date')->nullable()->after('einvoice_ack_no');
                $table->text('einvoice_qr_code')->nullable()->after('einvoice_ack_date');
                $table->string('eway_bill_no', 20)->nullable()->after('einvoice_qr_code');
                $table->dateTime('eway_bill_date')->nullable()->after('eway_bill_no');
                $table->dateTime('eway_bill_valid_until')->nullable()->after('eway_bill_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mm_invoices', function (Blueprint $table) {
            $table->dropColumn([
                'einvoice_irn', 'einvoice_ack_no', 'einvoice_ack_date', 'einvoice_qr_code',
                'eway_bill_no', 'eway_bill_date', 'eway_bill_valid_until'
            ]);
        });
    }
};
