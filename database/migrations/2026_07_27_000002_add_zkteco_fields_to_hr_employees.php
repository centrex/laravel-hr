<?php

declare(strict_types = 1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.table_prefix', 'hr_');
        $connection = config('hr.drivers.database.connection') ?? config('database.default');
        $schema = Schema::connection($connection);

        $schema->table($prefix . 'employees', function (Blueprint $table) use ($prefix, $schema): void {
            if (!$schema->hasColumn($prefix . 'employees', 'zkteco_device_id')) {
                $table->foreignId('zkteco_device_id')->nullable()->after('sbu_code')
                    ->constrained($prefix . 'zkteco_devices')->nullOnDelete();
            }

            if (!$schema->hasColumn($prefix . 'employees', 'zkteco_user_id')) {
                // The device-local enrolled user id — not globally unique, only unique per device
                // (two branches' devices can both enroll a "1").
                $table->string('zkteco_user_id', 20)->nullable()->after('zkteco_device_id');
                $table->unique(['zkteco_device_id', 'zkteco_user_id']);
            }
        });
    }

    public function down(): void
    {
        $prefix = config('hr.table_prefix', 'hr_');
        $connection = config('hr.drivers.database.connection') ?? config('database.default');
        $schema = Schema::connection($connection);

        $schema->table($prefix . 'employees', function (Blueprint $table) use ($prefix, $schema): void {
            if ($schema->hasColumn($prefix . 'employees', 'zkteco_user_id')) {
                $table->dropUnique([$prefix . 'employees_zkteco_device_id_zkteco_user_id_unique']);
                $table->dropColumn('zkteco_user_id');
            }

            if ($schema->hasColumn($prefix . 'employees', 'zkteco_device_id')) {
                $table->dropConstrainedForeignId('zkteco_device_id');
            }
        });
    }
};
