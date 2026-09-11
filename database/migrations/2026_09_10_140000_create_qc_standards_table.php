<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('mm_qc_standards')) {
            Schema::create('mm_qc_standards', function (Blueprint $table) {
                $table->id();
                $table->foreignId('plant_id')->nullable()->constrained('mm_plants')->onDelete('cascade');
                $table->string('code', 50);
                $table->string('name', 255);
                $table->string('organization', 100)->default('BIS');
                $table->string('edition_year', 20)->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->auditColumns();

                $table->index(['plant_id', 'is_active']);
                $table->index('code');
            });

            // Seed standard references
            $standards = [
                ['code' => 'IS 516', 'name' => 'Method of Tests for Strength of Concrete', 'organization' => 'BIS', 'edition_year' => '2021', 'description' => 'Determination of compressive, flexural and tensile splitting strength of concrete specimens.'],
                ['code' => 'IS 456', 'name' => 'Plain and Reinforced Concrete - Code of Practice', 'organization' => 'BIS', 'edition_year' => '2000', 'description' => 'General requirements and acceptance criteria for plain and reinforced concrete.'],
                ['code' => 'IS 4031 (Part 1)', 'name' => 'Fineness by Dry Sieving', 'organization' => 'BIS', 'edition_year' => '1996', 'description' => 'Determination of fineness of cement.'],
                ['code' => 'IS 4031 (Part 3)', 'name' => 'Soundness by Le-Chatelier', 'organization' => 'BIS', 'edition_year' => '1988', 'description' => 'Determination of soundness of cement.'],
                ['code' => 'IS 4031 (Part 4)', 'name' => 'Standard Consistency', 'organization' => 'BIS', 'edition_year' => '1988', 'description' => 'Determination of standard consistency of hydraulic cement.'],
                ['code' => 'IS 4031 (Part 5)', 'name' => 'Initial and Final Setting Time', 'organization' => 'BIS', 'edition_year' => '1988', 'description' => 'Determination of initial and final setting times of cement.'],
                ['code' => 'IS 4031 (Part 6)', 'name' => 'Compressive Strength of Mortar Cubes', 'organization' => 'BIS', 'edition_year' => '1988', 'description' => 'Determination of compressive strength of mortar cubes.'],
                ['code' => 'IS 2386', 'name' => 'Methods of Test for Aggregates for Concrete', 'organization' => 'BIS', 'edition_year' => '1963', 'description' => 'Particle size, shape, specific gravity, and mechanical properties of aggregates.'],
                ['code' => 'IS 1199', 'name' => 'Methods of Sampling and Analysis of Concrete', 'organization' => 'BIS', 'edition_year' => '1959', 'description' => 'Procedures for sampling and testing fresh concrete.'],
                ['code' => 'ASTM C39', 'name' => 'Compressive Strength of Cylindrical Concrete Specimens', 'organization' => 'ASTM', 'edition_year' => '2021', 'description' => 'Standard test method for compressive strength of cylindrical concrete specimens.'],
            ];

            $now = now();
            foreach ($standards as $std) {
                DB::table('mm_qc_standards')->insertOrIgnore(array_merge($std, [
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_qc_standards');
    }
};
