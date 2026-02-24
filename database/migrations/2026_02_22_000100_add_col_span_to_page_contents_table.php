<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('page_contents', function (Blueprint $table) {
            if (! Schema::hasColumn('page_contents', 'col_span')) {
                // 12-column grid width for rendering (12 = full width).
                $table->unsignedTinyInteger('col_span')->default(12)->after('section_name');
                $table->index('col_span');
            }
        });
    }

    public function down(): void
    {
        Schema::table('page_contents', function (Blueprint $table) {
            if (Schema::hasColumn('page_contents', 'col_span')) {
                $table->dropIndex(['col_span']);
                $table->dropColumn('col_span');
            }
        });
    }
};

