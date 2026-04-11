<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_vault_access_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_id')->constrained('nawasara_vault_credentials')->cascadeOnDelete();
            $table->string('action');
            $table->string('accessor');
            $table->foreignId('accessor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['credential_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_vault_access_log');
    }
};
