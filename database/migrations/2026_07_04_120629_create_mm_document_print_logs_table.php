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
                Schema::create('mm_document_print_logs', function (Blueprint $table) {
                    $table->id();
                    $table->string('document_type'); // e.g., 'invoice'
                    $table->unsignedBigInteger('document_id');
                    $table->unsignedBigInteger('user_id');
                    $table->string('action'); // e.g., 'download', 'print'
                    $table->timestamps();
                });
            }

            /**
             * Reverse the migrations.
             */
            public function down(): void
            {
                Schema::dropIfExists('mm_document_print_logs');
            }
        };
