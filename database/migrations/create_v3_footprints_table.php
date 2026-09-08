<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $table_name = config('footprints.table_name');

        if (!$table_name) {
            Log::warning('Footprints table name is not defined, skipping migration...');
            return;
        }

        Schema::create($table_name, function (Blueprint $table) {
            $table->id();

            // Request Group
            $table->string('request_id')->nullable()->index();
            $table->string('app_name')->nullable();
            $table->string('app_environment')->nullable();

            $table->string('request_method', 20)->index();
            $table->text('request_uri')->nullable();
            $table->text('request_url')->nullable();
            $table->timestamp('request_time')->nullable();

            $table->json('request_headers')->nullable();
            $table->json('request_body')->nullable();

            // Response Group
            $table->unsignedSmallInteger('response_status_code')->nullable();
            $table->boolean('response_success')->default(false);

            $table->json('response_headers')->nullable();
            $table->longText('response_body')->nullable();

            $table->decimal('duration_ms', 15, 3)->nullable();

            $table->ipAddress('client_ip')->nullable();
            $table->ipAddress('host_ip')->nullable();
            $table->string('host_name')->nullable();

            // User Data
            $table->string('user_id')->nullable()->index();
            $table->string('user_type')->nullable();

            // Exception
            $table->text('exception_message')->nullable();

            $table->timestamps();

            // Useful indexes
            $table->index('request_time');
            $table->index('response_status_code');
            $table->index(['app_name', 'app_environment']);
        });
    }

    public function down(): void
    {
        $table_name = config('footprints.table_name');

        if (!$table_name) {
            Log::warning('Footprints table name is not defined, skipping migration...');
            return;
        }

        Schema::dropIfExists($table_name);
    }
};
