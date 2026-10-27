<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            // Ubah dulu tipe kolom ke VARCHAR agar data lama bisa di-update tanpa truncation
            DB::statement("ALTER TABLE users MODIFY COLUMN role VARCHAR(30) DEFAULT 'admin_rt'");
            DB::statement("UPDATE users SET role = 'admin_rw' WHERE role IN ('rw', 'rt_rw')");
            DB::statement("UPDATE users SET role = 'admin_rt' WHERE role = 'rt'");
            DB::statement("UPDATE users SET role = 'superadmin' WHERE role = 'desa'");
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('superadmin', 'admin', 'admin_rw', 'admin_rt') DEFAULT 'admin_rt'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('superadmin', 'admin_rw', 'admin_rt') DEFAULT 'admin_rt'");
        }
    }
};
