<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_vault_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('group');
            $table->string('key');
            $table->text('value');
            $table->string('instance')->nullable();
            $table->string('description')->nullable();
            $table->timestamp('last_rotated_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->foreignId('rotated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['group', 'key', 'instance']);
            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_vault_credentials');
    }
};
