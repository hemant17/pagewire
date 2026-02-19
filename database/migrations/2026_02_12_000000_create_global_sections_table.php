<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('global_sections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('section_name');
            $table->json('content');
            $table->foreignId('created_by')
                ->nullable()
                ->constrained(table: config('pagewire.user_table', 'users'))
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained(table: config('pagewire.user_table', 'users'))
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_sections');
    }
};
