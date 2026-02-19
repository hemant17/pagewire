<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('page_contents', function (Blueprint $table) {
            $table->foreignId('global_section_id')
                ->nullable()
                ->constrained('global_sections')
                ->nullOnDelete()
                ->after('page_id');
            $table->boolean('is_global_override')->default(false)->after('global_section_id');
        });
    }

    public function down(): void
    {
        Schema::table('page_contents', function (Blueprint $table) {
            $table->dropForeign(['global_section_id']);
            $table->dropColumn(['global_section_id', 'is_global_override']);
        });
    }
};
