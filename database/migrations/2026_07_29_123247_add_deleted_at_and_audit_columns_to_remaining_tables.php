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
        if (Schema::hasTable('mm_account_default_settings')) {
            Schema::table('mm_account_default_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_account_default_settings', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_account_default_settings', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_accounts')) {
            Schema::table('mm_accounts', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_accounts', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_accounts', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_account_types')) {
            Schema::table('mm_account_types', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_account_types', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_account_types', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_addresses')) {
            Schema::table('mm_addresses', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_addresses', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_addresses', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_address_relation')) {
            Schema::table('mm_address_relation', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_address_relation', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_address_relation', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_address_types')) {
            Schema::table('mm_address_types', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_address_types', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_address_types', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('ai_conversations')) {
            Schema::table('ai_conversations', function (Blueprint $table) {
                if (!Schema::hasColumn('ai_conversations', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('ai_conversations', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('ai_messages')) {
            Schema::table('ai_messages', function (Blueprint $table) {
                if (!Schema::hasColumn('ai_messages', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('ai_messages', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_modules')) {
            Schema::table('mm_modules', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_modules', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_modules', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_attendances')) {
            Schema::table('mm_attendances', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_attendances', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_attendances', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_bank_account_types')) {
            Schema::table('mm_bank_account_types', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_bank_account_types', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_bank_account_types', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_bank_statement_lines')) {
            Schema::table('mm_bank_statement_lines', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_bank_statement_lines', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_bank_statement_lines', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_batches')) {
            Schema::table('mm_batches', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_batches', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_batches', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_batch_materials')) {
            Schema::table('mm_batch_materials', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_batch_materials', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_batch_materials', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_batch_sheet_field_dictionary')) {
            Schema::table('mm_batch_sheet_field_dictionary', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_batch_sheet_field_dictionary', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_batch_sheet_field_dictionary', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_batch_sheet_templates')) {
            Schema::table('mm_batch_sheet_templates', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_batch_sheet_templates', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_batch_sheet_templates', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_batch_sheet_uploads')) {
            Schema::table('mm_batch_sheet_uploads', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_batch_sheet_uploads', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_batch_sheet_uploads', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_billings')) {
            Schema::table('mm_billings', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_billings', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_billings', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_concrete_grades')) {
            Schema::table('mm_concrete_grades', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_concrete_grades', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_concrete_grades', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_concrete_grade_items')) {
            Schema::table('mm_concrete_grade_items', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_concrete_grade_items', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_concrete_grade_items', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_concrete_quality_tests')) {
            Schema::table('mm_concrete_quality_tests', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_concrete_quality_tests', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_concrete_quality_tests', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_contacts')) {
            Schema::table('mm_contacts', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_contacts', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_contacts', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_contact_relation')) {
            Schema::table('mm_contact_relation', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_contact_relation', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_contact_relation', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_contact_types')) {
            Schema::table('mm_contact_types', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_contact_types', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_contact_types', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_countries')) {
            Schema::table('mm_countries', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_countries', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_countries', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_currencies')) {
            Schema::table('mm_currencies', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_currencies', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_currencies', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_custom_settings')) {
            Schema::table('mm_custom_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_custom_settings', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_custom_settings', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_customer_pos')) {
            Schema::table('mm_customer_pos', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_customer_pos', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_customer_pos', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_customer_po_items')) {
            Schema::table('mm_customer_po_items', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_customer_po_items', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_customer_po_items', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_customer_po_item_pump_rates')) {
            Schema::table('mm_customer_po_item_pump_rates', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_customer_po_item_pump_rates', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_customer_po_item_pump_rates', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_dashboard_alerts')) {
            Schema::table('mm_dashboard_alerts', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_dashboard_alerts', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_dashboard_alerts', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_departments')) {
            Schema::table('mm_departments', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_departments', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_departments', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_designations')) {
            Schema::table('mm_designations', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_designations', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_designations', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_dispatches')) {
            Schema::table('mm_dispatches', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_dispatches', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_dispatches', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_dispatch_financials')) {
            Schema::table('mm_dispatch_financials', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_dispatch_financials', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_dispatch_financials', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_dispatch_payments')) {
            Schema::table('mm_dispatch_payments', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_dispatch_payments', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_dispatch_payments', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_dispatch_statuses')) {
            Schema::table('mm_dispatch_statuses', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_dispatch_statuses', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_dispatch_statuses', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('document_chunks')) {
            Schema::table('document_chunks', function (Blueprint $table) {
                if (!Schema::hasColumn('document_chunks', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('document_chunks', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_drivers')) {
            Schema::table('mm_drivers', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_drivers', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_drivers', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_einvoice_auth')) {
            Schema::table('mm_einvoice_auth', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_einvoice_auth', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_einvoice_auth', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_employee_leave_balances')) {
            Schema::table('mm_employee_leave_balances', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_employee_leave_balances', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_employee_leave_balances', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_employee_salary_structures')) {
            Schema::table('mm_employee_salary_structures', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_employee_salary_structures', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_employee_salary_structures', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_employee_shifts')) {
            Schema::table('mm_employee_shifts', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_employee_shifts', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_employee_shifts', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_entities')) {
            Schema::table('mm_entities', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_entities', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_entities', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_entity_addresses')) {
            Schema::table('mm_entity_addresses', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_entity_addresses', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_entity_addresses', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_entity_bank_accounts')) {
            Schema::table('mm_entity_bank_accounts', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_entity_bank_accounts', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_entity_bank_accounts', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_entity_contacts')) {
            Schema::table('mm_entity_contacts', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_entity_contacts', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_entity_contacts', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_entity_invoices')) {
            Schema::table('mm_entity_invoices', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_entity_invoices', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_entity_invoices', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_entity_subscriptions')) {
            Schema::table('mm_entity_subscriptions', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_entity_subscriptions', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_entity_subscriptions', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_entity_taxes')) {
            Schema::table('mm_entity_taxes', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_entity_taxes', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_entity_taxes', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_entity_types')) {
            Schema::table('mm_entity_types', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_entity_types', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_entity_types', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_entity_users')) {
            Schema::table('mm_entity_users', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_entity_users', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_entity_users', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_expenses')) {
            Schema::table('mm_expenses', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_expenses', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_expenses', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_expense_types')) {
            Schema::table('mm_expense_types', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_expense_types', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_expense_types', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_features')) {
            Schema::table('mm_features', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_features', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_features', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_geofences')) {
            Schema::table('mm_geofences', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_geofences', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_geofences', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_gps_devices')) {
            Schema::table('mm_gps_devices', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_gps_devices', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_gps_devices', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_images')) {
            Schema::table('mm_images', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_images', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_images', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_invoices')) {
            Schema::table('mm_invoices', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_invoices', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_invoices', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_invoice_items')) {
            Schema::table('mm_invoice_items', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_invoice_items', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_invoice_items', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_invoice_payments')) {
            Schema::table('mm_invoice_payments', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_invoice_payments', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_invoice_payments', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_invoice_statuses')) {
            Schema::table('mm_invoice_statuses', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_invoice_statuses', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_invoice_statuses', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_journal_entries')) {
            Schema::table('mm_journal_entries', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_journal_entries', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_journal_entries', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_journal_entry_lines')) {
            Schema::table('mm_journal_entry_lines', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_journal_entry_lines', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_journal_entry_lines', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_leave_applications')) {
            Schema::table('mm_leave_applications', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_leave_applications', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_leave_applications', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_leave_types')) {
            Schema::table('mm_leave_types', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_leave_types', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_leave_types', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_ledgers')) {
            Schema::table('mm_ledgers', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_ledgers', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_ledgers', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_machines')) {
            Schema::table('mm_machines', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_machines', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_machines', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_machine_documents')) {
            Schema::table('mm_machine_documents', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_machine_documents', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_machine_documents', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_machine_emi_payments')) {
            Schema::table('mm_machine_emi_payments', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_machine_emi_payments', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_machine_emi_payments', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_machine_loans')) {
            Schema::table('mm_machine_loans', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_machine_loans', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_machine_loans', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_machine_service')) {
            Schema::table('mm_machine_service', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_machine_service', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_machine_service', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_machine_tracker')) {
            Schema::table('mm_machine_tracker', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_machine_tracker', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_machine_tracker', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_machine_types')) {
            Schema::table('mm_machine_types', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_machine_types', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_machine_types', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_main_group_master')) {
            Schema::table('mm_main_group_master', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_main_group_master', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_main_group_master', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_machine_maintanence_lines')) {
            Schema::table('mm_machine_maintanence_lines', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_machine_maintanence_lines', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_machine_maintanence_lines', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_machine_maintanence_request')) {
            Schema::table('mm_machine_maintanence_request', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_machine_maintanence_request', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_machine_maintanence_request', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_menus')) {
            Schema::table('mm_menus', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_menus', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_menus', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_mix_designs')) {
            Schema::table('mm_mix_designs', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_mix_designs', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_mix_designs', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_mix_design_items')) {
            Schema::table('mm_mix_design_items', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_mix_design_items', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_mix_design_items', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_module')) {
            Schema::table('mm_module', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_module', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_module', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('notification_emails')) {
            Schema::table('notification_emails', function (Blueprint $table) {
                if (!Schema::hasColumn('notification_emails', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('notification_emails', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_order_taxes')) {
            Schema::table('mm_order_taxes', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_order_taxes', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_order_taxes', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_party_rates')) {
            Schema::table('mm_party_rates', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_party_rates', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_party_rates', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_patrons')) {
            Schema::table('mm_patrons', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_patrons', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_patrons', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_patron_bank_accounts')) {
            Schema::table('mm_patron_bank_accounts', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_patron_bank_accounts', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_patron_bank_accounts', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_payments')) {
            Schema::table('mm_payments', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_payments', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_payments', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_payment_allocations')) {
            Schema::table('mm_payment_allocations', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_payment_allocations', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_payment_allocations', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_payment_gateways')) {
            Schema::table('mm_payment_gateways', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_payment_gateways', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_payment_gateways', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_payment_methods')) {
            Schema::table('mm_payment_methods', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_payment_methods', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_payment_methods', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_payment_statuses')) {
            Schema::table('mm_payment_statuses', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_payment_statuses', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_payment_statuses', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_payment_transactions')) {
            Schema::table('mm_payment_transactions', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_payment_transactions', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_payment_transactions', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_payroll_periods')) {
            Schema::table('mm_payroll_periods', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_payroll_periods', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_payroll_periods', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_payslips')) {
            Schema::table('mm_payslips', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_payslips', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_payslips', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_payslip_items')) {
            Schema::table('mm_payslip_items', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_payslip_items', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_payslip_items', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_personnels')) {
            Schema::table('mm_personnels', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_personnels', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_personnels', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_personnel_contacts')) {
            Schema::table('mm_personnel_contacts', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_personnel_contacts', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_personnel_contacts', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_personnel_patron_rels')) {
            Schema::table('mm_personnel_patron_rels', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_personnel_patron_rels', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_personnel_patron_rels', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_petty_cash')) {
            Schema::table('mm_petty_cash', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_petty_cash', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_petty_cash', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_petty_cash_items')) {
            Schema::table('mm_petty_cash_items', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_petty_cash_items', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_petty_cash_items', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_plans')) {
            Schema::table('mm_plans', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_plans', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_plans', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_plan_features')) {
            Schema::table('mm_plan_features', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_plan_features', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_plan_features', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_plants')) {
            Schema::table('mm_plants', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_plants', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_plants', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_print_templates')) {
            Schema::table('mm_print_templates', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_print_templates', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_print_templates', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_print_template_settings')) {
            Schema::table('mm_print_template_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_print_template_settings', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_print_template_settings', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_products')) {
            Schema::table('mm_products', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_products', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_products', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_product_categories')) {
            Schema::table('mm_product_categories', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_product_categories', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_product_categories', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_product_units')) {
            Schema::table('mm_product_units', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_product_units', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_product_units', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_public_document_links')) {
            Schema::table('mm_public_document_links', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_public_document_links', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_public_document_links', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_purchase_orders')) {
            Schema::table('mm_purchase_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_purchase_orders', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_purchase_orders', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_purchase_order_items')) {
            Schema::table('mm_purchase_order_items', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_purchase_order_items', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_purchase_order_items', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_quantity')) {
            Schema::table('mm_quantity', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_quantity', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_quantity', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_quotations')) {
            Schema::table('mm_quotations', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_quotations', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_quotations', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_quotation_items')) {
            Schema::table('mm_quotation_items', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_quotation_items', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_quotation_items', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_quotation_item_pump_rates')) {
            Schema::table('mm_quotation_item_pump_rates', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_quotation_item_pump_rates', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_quotation_item_pump_rates', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('rag_documents')) {
            Schema::table('rag_documents', function (Blueprint $table) {
                if (!Schema::hasColumn('rag_documents', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('rag_documents', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_report_schedules')) {
            Schema::table('mm_report_schedules', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_report_schedules', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_report_schedules', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_salary_components')) {
            Schema::table('mm_salary_components', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_salary_components', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_salary_components', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_salary_revisions')) {
            Schema::table('mm_salary_revisions', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_salary_revisions', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_salary_revisions', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_sales_orders')) {
            Schema::table('mm_sales_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_sales_orders', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_sales_orders', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_sales_order_items')) {
            Schema::table('mm_sales_order_items', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_sales_order_items', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_sales_order_items', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_sales_order_operations')) {
            Schema::table('mm_sales_order_operations', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_sales_order_operations', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_sales_order_operations', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_shifts')) {
            Schema::table('mm_shifts', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_shifts', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_shifts', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_sites')) {
            Schema::table('mm_sites', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_sites', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_sites', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_state_codes')) {
            Schema::table('mm_state_codes', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_state_codes', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_state_codes', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_statutory_configs')) {
            Schema::table('mm_statutory_configs', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_statutory_configs', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_statutory_configs', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_stock_exhaust')) {
            Schema::table('mm_stock_exhaust', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_stock_exhaust', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_stock_exhaust', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_stock_exhaust_lines')) {
            Schema::table('mm_stock_exhaust_lines', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_stock_exhaust_lines', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_stock_exhaust_lines', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_subscription_statuses')) {
            Schema::table('mm_subscription_statuses', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_subscription_statuses', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_subscription_statuses', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_taxes')) {
            Schema::table('mm_taxes', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_taxes', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_taxes', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_terms_condition')) {
            Schema::table('mm_terms_condition', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_terms_condition', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_terms_condition', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_truck_empty_weights')) {
            Schema::table('mm_truck_empty_weights', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_truck_empty_weights', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_truck_empty_weights', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_users')) {
            Schema::table('mm_users', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_users', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_users', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('mm_voucher_types')) {
            Schema::table('mm_voucher_types', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_voucher_types', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('mm_voucher_types', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('mm_account_default_settings')) {
            Schema::table('mm_account_default_settings', function (Blueprint $table) {
                if (Schema::hasColumn('mm_account_default_settings', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_account_default_settings', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_accounts')) {
            Schema::table('mm_accounts', function (Blueprint $table) {
                if (Schema::hasColumn('mm_accounts', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_accounts', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_account_types')) {
            Schema::table('mm_account_types', function (Blueprint $table) {
                if (Schema::hasColumn('mm_account_types', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_account_types', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_addresses')) {
            Schema::table('mm_addresses', function (Blueprint $table) {
                if (Schema::hasColumn('mm_addresses', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_addresses', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_address_relation')) {
            Schema::table('mm_address_relation', function (Blueprint $table) {
                if (Schema::hasColumn('mm_address_relation', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_address_relation', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_address_types')) {
            Schema::table('mm_address_types', function (Blueprint $table) {
                if (Schema::hasColumn('mm_address_types', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_address_types', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('ai_conversations')) {
            Schema::table('ai_conversations', function (Blueprint $table) {
                if (Schema::hasColumn('ai_conversations', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('ai_conversations', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('ai_messages')) {
            Schema::table('ai_messages', function (Blueprint $table) {
                if (Schema::hasColumn('ai_messages', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('ai_messages', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_modules')) {
            Schema::table('mm_modules', function (Blueprint $table) {
                if (Schema::hasColumn('mm_modules', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_modules', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_attendances')) {
            Schema::table('mm_attendances', function (Blueprint $table) {
                if (Schema::hasColumn('mm_attendances', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_attendances', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_bank_account_types')) {
            Schema::table('mm_bank_account_types', function (Blueprint $table) {
                if (Schema::hasColumn('mm_bank_account_types', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_bank_account_types', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_bank_statement_lines')) {
            Schema::table('mm_bank_statement_lines', function (Blueprint $table) {
                if (Schema::hasColumn('mm_bank_statement_lines', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_bank_statement_lines', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_batches')) {
            Schema::table('mm_batches', function (Blueprint $table) {
                if (Schema::hasColumn('mm_batches', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_batches', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_batch_materials')) {
            Schema::table('mm_batch_materials', function (Blueprint $table) {
                if (Schema::hasColumn('mm_batch_materials', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_batch_materials', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_batch_sheet_field_dictionary')) {
            Schema::table('mm_batch_sheet_field_dictionary', function (Blueprint $table) {
                if (Schema::hasColumn('mm_batch_sheet_field_dictionary', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_batch_sheet_field_dictionary', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_batch_sheet_templates')) {
            Schema::table('mm_batch_sheet_templates', function (Blueprint $table) {
                if (Schema::hasColumn('mm_batch_sheet_templates', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_batch_sheet_templates', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_batch_sheet_uploads')) {
            Schema::table('mm_batch_sheet_uploads', function (Blueprint $table) {
                if (Schema::hasColumn('mm_batch_sheet_uploads', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_batch_sheet_uploads', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_billings')) {
            Schema::table('mm_billings', function (Blueprint $table) {
                if (Schema::hasColumn('mm_billings', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_billings', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_concrete_grades')) {
            Schema::table('mm_concrete_grades', function (Blueprint $table) {
                if (Schema::hasColumn('mm_concrete_grades', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_concrete_grades', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_concrete_grade_items')) {
            Schema::table('mm_concrete_grade_items', function (Blueprint $table) {
                if (Schema::hasColumn('mm_concrete_grade_items', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_concrete_grade_items', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_concrete_quality_tests')) {
            Schema::table('mm_concrete_quality_tests', function (Blueprint $table) {
                if (Schema::hasColumn('mm_concrete_quality_tests', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_concrete_quality_tests', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_contacts')) {
            Schema::table('mm_contacts', function (Blueprint $table) {
                if (Schema::hasColumn('mm_contacts', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_contacts', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_contact_relation')) {
            Schema::table('mm_contact_relation', function (Blueprint $table) {
                if (Schema::hasColumn('mm_contact_relation', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_contact_relation', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_contact_types')) {
            Schema::table('mm_contact_types', function (Blueprint $table) {
                if (Schema::hasColumn('mm_contact_types', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_contact_types', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_countries')) {
            Schema::table('mm_countries', function (Blueprint $table) {
                if (Schema::hasColumn('mm_countries', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_countries', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_currencies')) {
            Schema::table('mm_currencies', function (Blueprint $table) {
                if (Schema::hasColumn('mm_currencies', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_currencies', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_custom_settings')) {
            Schema::table('mm_custom_settings', function (Blueprint $table) {
                if (Schema::hasColumn('mm_custom_settings', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_custom_settings', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_customer_pos')) {
            Schema::table('mm_customer_pos', function (Blueprint $table) {
                if (Schema::hasColumn('mm_customer_pos', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_customer_pos', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_customer_po_items')) {
            Schema::table('mm_customer_po_items', function (Blueprint $table) {
                if (Schema::hasColumn('mm_customer_po_items', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_customer_po_items', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_customer_po_item_pump_rates')) {
            Schema::table('mm_customer_po_item_pump_rates', function (Blueprint $table) {
                if (Schema::hasColumn('mm_customer_po_item_pump_rates', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_customer_po_item_pump_rates', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_dashboard_alerts')) {
            Schema::table('mm_dashboard_alerts', function (Blueprint $table) {
                if (Schema::hasColumn('mm_dashboard_alerts', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_dashboard_alerts', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_departments')) {
            Schema::table('mm_departments', function (Blueprint $table) {
                if (Schema::hasColumn('mm_departments', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_departments', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_designations')) {
            Schema::table('mm_designations', function (Blueprint $table) {
                if (Schema::hasColumn('mm_designations', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_designations', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_dispatches')) {
            Schema::table('mm_dispatches', function (Blueprint $table) {
                if (Schema::hasColumn('mm_dispatches', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_dispatches', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_dispatch_financials')) {
            Schema::table('mm_dispatch_financials', function (Blueprint $table) {
                if (Schema::hasColumn('mm_dispatch_financials', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_dispatch_financials', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_dispatch_payments')) {
            Schema::table('mm_dispatch_payments', function (Blueprint $table) {
                if (Schema::hasColumn('mm_dispatch_payments', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_dispatch_payments', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_dispatch_statuses')) {
            Schema::table('mm_dispatch_statuses', function (Blueprint $table) {
                if (Schema::hasColumn('mm_dispatch_statuses', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_dispatch_statuses', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('document_chunks')) {
            Schema::table('document_chunks', function (Blueprint $table) {
                if (Schema::hasColumn('document_chunks', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('document_chunks', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_drivers')) {
            Schema::table('mm_drivers', function (Blueprint $table) {
                if (Schema::hasColumn('mm_drivers', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_drivers', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_einvoice_auth')) {
            Schema::table('mm_einvoice_auth', function (Blueprint $table) {
                if (Schema::hasColumn('mm_einvoice_auth', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_einvoice_auth', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_employee_leave_balances')) {
            Schema::table('mm_employee_leave_balances', function (Blueprint $table) {
                if (Schema::hasColumn('mm_employee_leave_balances', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_employee_leave_balances', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_employee_salary_structures')) {
            Schema::table('mm_employee_salary_structures', function (Blueprint $table) {
                if (Schema::hasColumn('mm_employee_salary_structures', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_employee_salary_structures', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_employee_shifts')) {
            Schema::table('mm_employee_shifts', function (Blueprint $table) {
                if (Schema::hasColumn('mm_employee_shifts', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_employee_shifts', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_entities')) {
            Schema::table('mm_entities', function (Blueprint $table) {
                if (Schema::hasColumn('mm_entities', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_entities', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_entity_addresses')) {
            Schema::table('mm_entity_addresses', function (Blueprint $table) {
                if (Schema::hasColumn('mm_entity_addresses', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_entity_addresses', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_entity_bank_accounts')) {
            Schema::table('mm_entity_bank_accounts', function (Blueprint $table) {
                if (Schema::hasColumn('mm_entity_bank_accounts', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_entity_bank_accounts', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_entity_contacts')) {
            Schema::table('mm_entity_contacts', function (Blueprint $table) {
                if (Schema::hasColumn('mm_entity_contacts', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_entity_contacts', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_entity_invoices')) {
            Schema::table('mm_entity_invoices', function (Blueprint $table) {
                if (Schema::hasColumn('mm_entity_invoices', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_entity_invoices', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_entity_subscriptions')) {
            Schema::table('mm_entity_subscriptions', function (Blueprint $table) {
                if (Schema::hasColumn('mm_entity_subscriptions', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_entity_subscriptions', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_entity_taxes')) {
            Schema::table('mm_entity_taxes', function (Blueprint $table) {
                if (Schema::hasColumn('mm_entity_taxes', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_entity_taxes', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_entity_types')) {
            Schema::table('mm_entity_types', function (Blueprint $table) {
                if (Schema::hasColumn('mm_entity_types', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_entity_types', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_entity_users')) {
            Schema::table('mm_entity_users', function (Blueprint $table) {
                if (Schema::hasColumn('mm_entity_users', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_entity_users', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_expenses')) {
            Schema::table('mm_expenses', function (Blueprint $table) {
                if (Schema::hasColumn('mm_expenses', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_expenses', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_expense_types')) {
            Schema::table('mm_expense_types', function (Blueprint $table) {
                if (Schema::hasColumn('mm_expense_types', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_expense_types', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_features')) {
            Schema::table('mm_features', function (Blueprint $table) {
                if (Schema::hasColumn('mm_features', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_features', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_geofences')) {
            Schema::table('mm_geofences', function (Blueprint $table) {
                if (Schema::hasColumn('mm_geofences', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_geofences', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_gps_devices')) {
            Schema::table('mm_gps_devices', function (Blueprint $table) {
                if (Schema::hasColumn('mm_gps_devices', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_gps_devices', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_images')) {
            Schema::table('mm_images', function (Blueprint $table) {
                if (Schema::hasColumn('mm_images', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_images', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_invoices')) {
            Schema::table('mm_invoices', function (Blueprint $table) {
                if (Schema::hasColumn('mm_invoices', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_invoices', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_invoice_items')) {
            Schema::table('mm_invoice_items', function (Blueprint $table) {
                if (Schema::hasColumn('mm_invoice_items', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_invoice_items', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_invoice_payments')) {
            Schema::table('mm_invoice_payments', function (Blueprint $table) {
                if (Schema::hasColumn('mm_invoice_payments', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_invoice_payments', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_invoice_statuses')) {
            Schema::table('mm_invoice_statuses', function (Blueprint $table) {
                if (Schema::hasColumn('mm_invoice_statuses', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_invoice_statuses', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_journal_entries')) {
            Schema::table('mm_journal_entries', function (Blueprint $table) {
                if (Schema::hasColumn('mm_journal_entries', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_journal_entries', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_journal_entry_lines')) {
            Schema::table('mm_journal_entry_lines', function (Blueprint $table) {
                if (Schema::hasColumn('mm_journal_entry_lines', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_journal_entry_lines', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_leave_applications')) {
            Schema::table('mm_leave_applications', function (Blueprint $table) {
                if (Schema::hasColumn('mm_leave_applications', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_leave_applications', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_leave_types')) {
            Schema::table('mm_leave_types', function (Blueprint $table) {
                if (Schema::hasColumn('mm_leave_types', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_leave_types', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_ledgers')) {
            Schema::table('mm_ledgers', function (Blueprint $table) {
                if (Schema::hasColumn('mm_ledgers', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_ledgers', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_machines')) {
            Schema::table('mm_machines', function (Blueprint $table) {
                if (Schema::hasColumn('mm_machines', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_machines', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_machine_documents')) {
            Schema::table('mm_machine_documents', function (Blueprint $table) {
                if (Schema::hasColumn('mm_machine_documents', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_machine_documents', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_machine_emi_payments')) {
            Schema::table('mm_machine_emi_payments', function (Blueprint $table) {
                if (Schema::hasColumn('mm_machine_emi_payments', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_machine_emi_payments', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_machine_loans')) {
            Schema::table('mm_machine_loans', function (Blueprint $table) {
                if (Schema::hasColumn('mm_machine_loans', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_machine_loans', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_machine_service')) {
            Schema::table('mm_machine_service', function (Blueprint $table) {
                if (Schema::hasColumn('mm_machine_service', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_machine_service', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_machine_tracker')) {
            Schema::table('mm_machine_tracker', function (Blueprint $table) {
                if (Schema::hasColumn('mm_machine_tracker', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_machine_tracker', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_machine_types')) {
            Schema::table('mm_machine_types', function (Blueprint $table) {
                if (Schema::hasColumn('mm_machine_types', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_machine_types', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_main_group_master')) {
            Schema::table('mm_main_group_master', function (Blueprint $table) {
                if (Schema::hasColumn('mm_main_group_master', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_main_group_master', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_machine_maintanence_lines')) {
            Schema::table('mm_machine_maintanence_lines', function (Blueprint $table) {
                if (Schema::hasColumn('mm_machine_maintanence_lines', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_machine_maintanence_lines', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_machine_maintanence_request')) {
            Schema::table('mm_machine_maintanence_request', function (Blueprint $table) {
                if (Schema::hasColumn('mm_machine_maintanence_request', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_machine_maintanence_request', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_menus')) {
            Schema::table('mm_menus', function (Blueprint $table) {
                if (Schema::hasColumn('mm_menus', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_menus', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_mix_designs')) {
            Schema::table('mm_mix_designs', function (Blueprint $table) {
                if (Schema::hasColumn('mm_mix_designs', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_mix_designs', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_mix_design_items')) {
            Schema::table('mm_mix_design_items', function (Blueprint $table) {
                if (Schema::hasColumn('mm_mix_design_items', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_mix_design_items', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_module')) {
            Schema::table('mm_module', function (Blueprint $table) {
                if (Schema::hasColumn('mm_module', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_module', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('notification_emails')) {
            Schema::table('notification_emails', function (Blueprint $table) {
                if (Schema::hasColumn('notification_emails', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('notification_emails', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_order_taxes')) {
            Schema::table('mm_order_taxes', function (Blueprint $table) {
                if (Schema::hasColumn('mm_order_taxes', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_order_taxes', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_party_rates')) {
            Schema::table('mm_party_rates', function (Blueprint $table) {
                if (Schema::hasColumn('mm_party_rates', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_party_rates', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_patrons')) {
            Schema::table('mm_patrons', function (Blueprint $table) {
                if (Schema::hasColumn('mm_patrons', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_patrons', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_patron_bank_accounts')) {
            Schema::table('mm_patron_bank_accounts', function (Blueprint $table) {
                if (Schema::hasColumn('mm_patron_bank_accounts', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_patron_bank_accounts', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_payments')) {
            Schema::table('mm_payments', function (Blueprint $table) {
                if (Schema::hasColumn('mm_payments', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_payments', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_payment_allocations')) {
            Schema::table('mm_payment_allocations', function (Blueprint $table) {
                if (Schema::hasColumn('mm_payment_allocations', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_payment_allocations', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_payment_gateways')) {
            Schema::table('mm_payment_gateways', function (Blueprint $table) {
                if (Schema::hasColumn('mm_payment_gateways', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_payment_gateways', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_payment_methods')) {
            Schema::table('mm_payment_methods', function (Blueprint $table) {
                if (Schema::hasColumn('mm_payment_methods', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_payment_methods', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_payment_statuses')) {
            Schema::table('mm_payment_statuses', function (Blueprint $table) {
                if (Schema::hasColumn('mm_payment_statuses', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_payment_statuses', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_payment_transactions')) {
            Schema::table('mm_payment_transactions', function (Blueprint $table) {
                if (Schema::hasColumn('mm_payment_transactions', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_payment_transactions', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_payroll_periods')) {
            Schema::table('mm_payroll_periods', function (Blueprint $table) {
                if (Schema::hasColumn('mm_payroll_periods', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_payroll_periods', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_payslips')) {
            Schema::table('mm_payslips', function (Blueprint $table) {
                if (Schema::hasColumn('mm_payslips', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_payslips', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_payslip_items')) {
            Schema::table('mm_payslip_items', function (Blueprint $table) {
                if (Schema::hasColumn('mm_payslip_items', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_payslip_items', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_personnels')) {
            Schema::table('mm_personnels', function (Blueprint $table) {
                if (Schema::hasColumn('mm_personnels', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_personnels', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_personnel_contacts')) {
            Schema::table('mm_personnel_contacts', function (Blueprint $table) {
                if (Schema::hasColumn('mm_personnel_contacts', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_personnel_contacts', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_personnel_patron_rels')) {
            Schema::table('mm_personnel_patron_rels', function (Blueprint $table) {
                if (Schema::hasColumn('mm_personnel_patron_rels', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_personnel_patron_rels', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_petty_cash')) {
            Schema::table('mm_petty_cash', function (Blueprint $table) {
                if (Schema::hasColumn('mm_petty_cash', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_petty_cash', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_petty_cash_items')) {
            Schema::table('mm_petty_cash_items', function (Blueprint $table) {
                if (Schema::hasColumn('mm_petty_cash_items', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_petty_cash_items', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_plans')) {
            Schema::table('mm_plans', function (Blueprint $table) {
                if (Schema::hasColumn('mm_plans', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_plans', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_plan_features')) {
            Schema::table('mm_plan_features', function (Blueprint $table) {
                if (Schema::hasColumn('mm_plan_features', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_plan_features', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_plants')) {
            Schema::table('mm_plants', function (Blueprint $table) {
                if (Schema::hasColumn('mm_plants', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_plants', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_print_templates')) {
            Schema::table('mm_print_templates', function (Blueprint $table) {
                if (Schema::hasColumn('mm_print_templates', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_print_templates', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_print_template_settings')) {
            Schema::table('mm_print_template_settings', function (Blueprint $table) {
                if (Schema::hasColumn('mm_print_template_settings', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_print_template_settings', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_products')) {
            Schema::table('mm_products', function (Blueprint $table) {
                if (Schema::hasColumn('mm_products', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_products', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_product_categories')) {
            Schema::table('mm_product_categories', function (Blueprint $table) {
                if (Schema::hasColumn('mm_product_categories', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_product_categories', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_product_units')) {
            Schema::table('mm_product_units', function (Blueprint $table) {
                if (Schema::hasColumn('mm_product_units', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_product_units', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_public_document_links')) {
            Schema::table('mm_public_document_links', function (Blueprint $table) {
                if (Schema::hasColumn('mm_public_document_links', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_public_document_links', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_purchase_orders')) {
            Schema::table('mm_purchase_orders', function (Blueprint $table) {
                if (Schema::hasColumn('mm_purchase_orders', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_purchase_orders', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_purchase_order_items')) {
            Schema::table('mm_purchase_order_items', function (Blueprint $table) {
                if (Schema::hasColumn('mm_purchase_order_items', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_purchase_order_items', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_quantity')) {
            Schema::table('mm_quantity', function (Blueprint $table) {
                if (Schema::hasColumn('mm_quantity', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_quantity', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_quotations')) {
            Schema::table('mm_quotations', function (Blueprint $table) {
                if (Schema::hasColumn('mm_quotations', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_quotations', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_quotation_items')) {
            Schema::table('mm_quotation_items', function (Blueprint $table) {
                if (Schema::hasColumn('mm_quotation_items', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_quotation_items', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_quotation_item_pump_rates')) {
            Schema::table('mm_quotation_item_pump_rates', function (Blueprint $table) {
                if (Schema::hasColumn('mm_quotation_item_pump_rates', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_quotation_item_pump_rates', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('rag_documents')) {
            Schema::table('rag_documents', function (Blueprint $table) {
                if (Schema::hasColumn('rag_documents', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('rag_documents', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_report_schedules')) {
            Schema::table('mm_report_schedules', function (Blueprint $table) {
                if (Schema::hasColumn('mm_report_schedules', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_report_schedules', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_salary_components')) {
            Schema::table('mm_salary_components', function (Blueprint $table) {
                if (Schema::hasColumn('mm_salary_components', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_salary_components', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_salary_revisions')) {
            Schema::table('mm_salary_revisions', function (Blueprint $table) {
                if (Schema::hasColumn('mm_salary_revisions', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_salary_revisions', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_sales_orders')) {
            Schema::table('mm_sales_orders', function (Blueprint $table) {
                if (Schema::hasColumn('mm_sales_orders', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_sales_orders', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_sales_order_items')) {
            Schema::table('mm_sales_order_items', function (Blueprint $table) {
                if (Schema::hasColumn('mm_sales_order_items', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_sales_order_items', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_sales_order_operations')) {
            Schema::table('mm_sales_order_operations', function (Blueprint $table) {
                if (Schema::hasColumn('mm_sales_order_operations', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_sales_order_operations', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_shifts')) {
            Schema::table('mm_shifts', function (Blueprint $table) {
                if (Schema::hasColumn('mm_shifts', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_shifts', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_sites')) {
            Schema::table('mm_sites', function (Blueprint $table) {
                if (Schema::hasColumn('mm_sites', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_sites', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_state_codes')) {
            Schema::table('mm_state_codes', function (Blueprint $table) {
                if (Schema::hasColumn('mm_state_codes', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_state_codes', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_statutory_configs')) {
            Schema::table('mm_statutory_configs', function (Blueprint $table) {
                if (Schema::hasColumn('mm_statutory_configs', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_statutory_configs', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_stock_exhaust')) {
            Schema::table('mm_stock_exhaust', function (Blueprint $table) {
                if (Schema::hasColumn('mm_stock_exhaust', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_stock_exhaust', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_stock_exhaust_lines')) {
            Schema::table('mm_stock_exhaust_lines', function (Blueprint $table) {
                if (Schema::hasColumn('mm_stock_exhaust_lines', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_stock_exhaust_lines', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_subscription_statuses')) {
            Schema::table('mm_subscription_statuses', function (Blueprint $table) {
                if (Schema::hasColumn('mm_subscription_statuses', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_subscription_statuses', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_taxes')) {
            Schema::table('mm_taxes', function (Blueprint $table) {
                if (Schema::hasColumn('mm_taxes', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_taxes', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_terms_condition')) {
            Schema::table('mm_terms_condition', function (Blueprint $table) {
                if (Schema::hasColumn('mm_terms_condition', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_terms_condition', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_truck_empty_weights')) {
            Schema::table('mm_truck_empty_weights', function (Blueprint $table) {
                if (Schema::hasColumn('mm_truck_empty_weights', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_truck_empty_weights', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_users')) {
            Schema::table('mm_users', function (Blueprint $table) {
                if (Schema::hasColumn('mm_users', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_users', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

        if (Schema::hasTable('mm_voucher_types')) {
            Schema::table('mm_voucher_types', function (Blueprint $table) {
                if (Schema::hasColumn('mm_voucher_types', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('mm_voucher_types', 'deleted_by')) {
                    $table->dropColumn('deleted_by');
                }
            });
        }

    }
};
