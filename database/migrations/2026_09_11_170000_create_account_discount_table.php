<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mm_account_discount', function (Blueprint $table) {
            $table->integer('id', true);
            $table->enum('primary_type', ['Sales', 'Purchase']);
            $table->enum('value_type', ['percent', 'amount']);
            $table->decimal('value', 17, 2);
            $table->decimal('amount', 17, 2);
            $table->integer('journal_id');
            $table->integer('account_id')->nullable();
            $table->integer('partner_id');
            $table->integer('move_id')->nullable();
            $table->date('date');
            $table->text('note')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->integer('plant_id');
            $table->dateTime('created_at');
            $table->integer('created_by');
            $table->dateTime('modified_at');
            $table->integer('modified_by');
            $table->index(['partner_id', 'journal_id'], 'patron_index');
            $table->index('date', 'date_index');
            $table->index('journal_id', 'journal_id');
            $table->index(['plant_id', 'date'], 'plant_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_account_discount');
    }
};
