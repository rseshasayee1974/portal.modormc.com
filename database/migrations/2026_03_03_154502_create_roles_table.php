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
        Schema::create('mm_roles', function (Blueprint $table) {
            $table->id();

            // Unique identifier for programatic checks (e.g. 'super_admin')
            $table->string('code')->unique();
            
            // Display name (e.g. 'Super Administrator')
            $table->string('name');
            
            // Descriptive purpose of the role
            $table->text('description')->nullable();

            // Hierarchy level: higher values grant more visibility/power in the app
            $table->integer('level')->default(99);

            // True for roles that are critical to system operation (cannot be deleted via UI)
            $table->boolean('is_system')->default(false);

            // Operational status
            $table->enum('status', ['active', 'inactive'])->default('active');

            // Compatibility for permission packages (Spatie, etc.)
            $table->string('guard_name')->default('web');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['code', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_roles');
    }
};
