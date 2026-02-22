<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            if (! Schema::hasColumn('pages', 'is_home')) {
                $table->boolean('is_home')->default(false)->after('is_published');
                $table->index('is_home');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            if (Schema::hasColumn('pages', 'is_home')) {
                $table->dropIndex(['is_home']);
                $table->dropColumn('is_home');
            }
        });
    }
};

