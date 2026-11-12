<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_log', function (Blueprint $table) {
            if (! Schema::hasColumn('audit_log', 'detail')) {
                $table->longText('detail')->nullable()->after('id_record');
            }
        });
    }

    public function down(): void
    {
        Schema::table('audit_log', function (Blueprint $table) {
            if (Schema::hasColumn('audit_log', 'detail')) {
                $table->dropColumn('detail');
            }
        });
    }
};

