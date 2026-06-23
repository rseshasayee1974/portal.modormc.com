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
        Schema::disableForeignKeyConstraints();

        // 1. Drop foreign keys referencing mm_sales_orders or mm_work_orders
        if (Schema::hasTable('mm_sales_order_items')) {
            try {
                Schema::table('mm_sales_order_items', function (Blueprint $table) {
                    $table->dropForeign(['sales_order_id']);
                });
            } catch (\Exception $e) {}
        }
        
        if (Schema::hasTable('mm_work_orders')) {
            try {
                Schema::table('mm_work_orders', function (Blueprint $table) {
                    $table->dropForeign(['sales_order_id']);
                });
            } catch (\Exception $e) {}
        }

        if (Schema::hasTable('mm_work_order_logs')) {
            try {
                Schema::table('mm_work_order_logs', function (Blueprint $table) {
                    $table->dropForeign(['work_order_id']);
                });
            } catch (\Exception $e) {}
        }

        if (Schema::hasTable('mm_work_order_operations')) {
            try {
                Schema::table('mm_work_order_operations', function (Blueprint $table) {
                    $table->dropForeign(['work_order_id']);
                });
            } catch (\Exception $e) {}
        }

        if (Schema::hasTable('mm_dispatches')) {
            try {
                Schema::table('mm_dispatches', function (Blueprint $table) {
                    $table->dropForeign(['sales_order_id']);
                });
            } catch (\Exception $e) {}
        }

        // 2. Rename tables
        if (Schema::hasTable('mm_sales_orders') && !Schema::hasTable('mm_customer_pos')) {
            Schema::rename('mm_sales_orders', 'mm_customer_pos');
        }
        if (Schema::hasTable('mm_sales_order_items') && !Schema::hasTable('mm_customer_po_items')) {
            Schema::rename('mm_sales_order_items', 'mm_customer_po_items');
        }
        if (Schema::hasTable('mm_work_orders') && !Schema::hasTable('mm_sales_orders')) {
            Schema::rename('mm_work_orders', 'mm_sales_orders');
        }
        if (Schema::hasTable('mm_work_order_logs') && !Schema::hasTable('mm_sales_order_logs')) {
            Schema::rename('mm_work_order_logs', 'mm_sales_order_logs');
        }
        if (Schema::hasTable('mm_work_order_operations') && !Schema::hasTable('mm_sales_order_operations')) {
            Schema::rename('mm_work_order_operations', 'mm_sales_order_operations');
        }

        // 3. Rename columns and add constraints
        if (Schema::hasTable('mm_customer_po_items')) {
            try {
                Schema::table('mm_customer_po_items', function (Blueprint $table) {
                    if (Schema::hasColumn('mm_customer_po_items', 'sales_order_id')) {
                        $table->renameColumn('sales_order_id', 'customer_po_id');
                    }
                });
            } catch (\Exception $e) {}
            
            try {
                Schema::table('mm_customer_po_items', function (Blueprint $table) {
                    $table->foreign('customer_po_id')->references('id')->on('mm_customer_pos')->cascadeOnDelete();
                });
            } catch (\Exception $e) {}
        }

        if (Schema::hasTable('mm_sales_orders')) {
            try {
                Schema::table('mm_sales_orders', function (Blueprint $table) {
                    if (Schema::hasColumn('mm_sales_orders', 'sales_order_id')) {
                        $table->renameColumn('sales_order_id', 'customer_po_id');
                    }
                });
            } catch (\Exception $e) {}
            
            try {
                Schema::table('mm_sales_orders', function (Blueprint $table) {
                    $table->foreign('customer_po_id')->references('id')->on('mm_customer_pos')->nullOnDelete();
                });
            } catch (\Exception $e) {}
        }

        if (Schema::hasTable('mm_sales_order_logs')) {
            try {
                Schema::table('mm_sales_order_logs', function (Blueprint $table) {
                    if (Schema::hasColumn('mm_sales_order_logs', 'work_order_id')) {
                        $table->renameColumn('work_order_id', 'sales_order_id');
                    }
                });
            } catch (\Exception $e) {}
            
            try {
                Schema::table('mm_sales_order_logs', function (Blueprint $table) {
                    $table->foreign('sales_order_id')->references('id')->on('mm_sales_orders')->cascadeOnDelete();
                });
            } catch (\Exception $e) {}
        }

        if (Schema::hasTable('mm_sales_order_operations')) {
            try {
                Schema::table('mm_sales_order_operations', function (Blueprint $table) {
                    if (Schema::hasColumn('mm_sales_order_operations', 'work_order_id')) {
                        $table->renameColumn('work_order_id', 'sales_order_id');
                    }
                });
            } catch (\Exception $e) {}
            
            try {
                Schema::table('mm_sales_order_operations', function (Blueprint $table) {
                    $table->foreign('sales_order_id')->references('id')->on('mm_sales_orders')->cascadeOnDelete();
                });
            } catch (\Exception $e) {}
        }

        if (Schema::hasTable('mm_batches')) {
            try {
                Schema::table('mm_batches', function (Blueprint $table) {
                    if (Schema::hasColumn('mm_batches', 'work_order_id')) {
                        $table->renameColumn('work_order_id', 'sales_order_id');
                    }
                });
            } catch (\Exception $e) {}
        }

        if (Schema::hasTable('mm_dispatches')) {
            try {
                Schema::table('mm_dispatches', function (Blueprint $table) {
                    if (Schema::hasColumn('mm_dispatches', 'work_order_id')) {
                        $table->renameColumn('work_order_id', 'sales_order_id');
                    }
                    if (Schema::hasColumn('mm_dispatches', 'sales_order_id')) {
                        $table->renameColumn('sales_order_id', 'customer_po_id');
                    }
                });
            } catch (\Exception $e) {}
            
            try {
                Schema::table('mm_dispatches', function (Blueprint $table) {
                    $table->foreign('customer_po_id')->references('id')->on('mm_customer_pos')->cascadeOnDelete();
                });
            } catch (\Exception $e) {}
        }

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        if (Schema::hasTable('mm_customer_po_items')) {
            try {
                Schema::table('mm_customer_po_items', function (Blueprint $table) {
                    $table->dropForeign(['customer_po_id']);
                });
            } catch (\Exception $e) {}
        }
        if (Schema::hasTable('mm_sales_orders')) {
            try {
                Schema::table('mm_sales_orders', function (Blueprint $table) {
                    $table->dropForeign(['customer_po_id']);
                });
            } catch (\Exception $e) {}
        }
        if (Schema::hasTable('mm_sales_order_logs')) {
            try {
                Schema::table('mm_sales_order_logs', function (Blueprint $table) {
                    $table->dropForeign(['sales_order_id']);
                });
            } catch (\Exception $e) {}
        }
        if (Schema::hasTable('mm_sales_order_operations')) {
            try {
                Schema::table('mm_sales_order_operations', function (Blueprint $table) {
                    $table->dropForeign(['sales_order_id']);
                });
            } catch (\Exception $e) {}
        }
        if (Schema::hasTable('mm_dispatches')) {
            try {
                Schema::table('mm_dispatches', function (Blueprint $table) {
                    $table->dropForeign(['customer_po_id']);
                });
            } catch (\Exception $e) {}
        }

        if (Schema::hasTable('mm_customer_po_items')) {
            try {
                Schema::table('mm_customer_po_items', function (Blueprint $table) {
                    if (Schema::hasColumn('mm_customer_po_items', 'customer_po_id')) {
                        $table->renameColumn('customer_po_id', 'sales_order_id');
                    }
                });
            } catch (\Exception $e) {}
        }
        if (Schema::hasTable('mm_sales_orders')) {
            try {
                Schema::table('mm_sales_orders', function (Blueprint $table) {
                    if (Schema::hasColumn('mm_sales_orders', 'customer_po_id')) {
                        $table->renameColumn('customer_po_id', 'sales_order_id');
                    }
                });
            } catch (\Exception $e) {}
        }
        if (Schema::hasTable('mm_sales_order_logs')) {
            try {
                Schema::table('mm_sales_order_logs', function (Blueprint $table) {
                    if (Schema::hasColumn('mm_sales_order_logs', 'sales_order_id')) {
                        $table->renameColumn('sales_order_id', 'work_order_id');
                    }
                });
            } catch (\Exception $e) {}
        }
        if (Schema::hasTable('mm_sales_order_operations')) {
            try {
                Schema::table('mm_sales_order_operations', function (Blueprint $table) {
                    if (Schema::hasColumn('mm_sales_order_operations', 'sales_order_id')) {
                        $table->renameColumn('sales_order_id', 'work_order_id');
                    }
                });
            } catch (\Exception $e) {}
        }
        if (Schema::hasTable('mm_batches')) {
            try {
                Schema::table('mm_batches', function (Blueprint $table) {
                    if (Schema::hasColumn('mm_batches', 'sales_order_id')) {
                        $table->renameColumn('sales_order_id', 'work_order_id');
                    }
                });
            } catch (\Exception $e) {}
        }
        if (Schema::hasTable('mm_dispatches')) {
            try {
                Schema::table('mm_dispatches', function (Blueprint $table) {
                    if (Schema::hasColumn('mm_dispatches', 'sales_order_id')) {
                        $table->renameColumn('sales_order_id', 'work_order_id');
                    }
                    if (Schema::hasColumn('mm_dispatches', 'customer_po_id')) {
                        $table->renameColumn('customer_po_id', 'sales_order_id');
                    }
                });
            } catch (\Exception $e) {}
        }

        if (Schema::hasTable('mm_customer_pos') && !Schema::hasTable('mm_sales_orders')) {
            Schema::rename('mm_customer_pos', 'mm_sales_orders');
        }
        if (Schema::hasTable('mm_customer_po_items') && !Schema::hasTable('mm_sales_order_items')) {
            Schema::rename('mm_customer_po_items', 'mm_sales_order_items');
        }
        if (Schema::hasTable('mm_sales_orders') && !Schema::hasTable('mm_work_orders')) {
            Schema::rename('mm_sales_orders', 'mm_work_orders');
        }
        if (Schema::hasTable('mm_sales_order_logs') && !Schema::hasTable('mm_work_order_logs')) {
            Schema::rename('mm_sales_order_logs', 'mm_work_order_logs');
        }
        if (Schema::hasTable('mm_sales_order_operations') && !Schema::hasTable('mm_work_order_operations')) {
            Schema::rename('mm_sales_order_operations', 'mm_work_order_operations');
        }

        if (Schema::hasTable('mm_sales_order_items')) {
            try {
                Schema::table('mm_sales_order_items', function (Blueprint $table) {
                    $table->foreign('sales_order_id')->references('id')->on('mm_sales_orders')->cascadeOnDelete();
                });
            } catch (\Exception $e) {}
        }
        if (Schema::hasTable('mm_work_orders')) {
            try {
                Schema::table('mm_work_orders', function (Blueprint $table) {
                    $table->foreign('sales_order_id')->references('id')->on('mm_sales_orders')->nullOnDelete();
                });
            } catch (\Exception $e) {}
        }
        if (Schema::hasTable('mm_work_order_logs')) {
            try {
                Schema::table('mm_work_order_logs', function (Blueprint $table) {
                    $table->foreign('work_order_id')->references('id')->on('mm_work_orders')->cascadeOnDelete();
                });
            } catch (\Exception $e) {}
        }
        if (Schema::hasTable('mm_work_order_operations')) {
            try {
                Schema::table('mm_work_order_operations', function (Blueprint $table) {
                    $table->foreign('work_order_id')->references('id')->on('mm_work_orders')->cascadeOnDelete();
                });
            } catch (\Exception $e) {}
        }
        if (Schema::hasTable('mm_dispatches')) {
            try {
                Schema::table('mm_dispatches', function (Blueprint $table) {
                    $table->foreign('sales_order_id')->references('id')->on('mm_sales_orders')->cascadeOnDelete();
                });
            } catch (\Exception $e) {}
        }

        Schema::enableForeignKeyConstraints();
    }
};
