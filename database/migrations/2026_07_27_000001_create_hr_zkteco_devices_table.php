<?php

declare(strict_types = 1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.table_prefix', 'hr_');
        $connection = config('hr.drivers.database.connection') ?? config('database.default');

        Schema::connection($connection)->create($prefix . 'zkteco_devices', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('ip_address', 45);
            $table->unsignedInteger('port')->default(4370);
            // Device comm key/password, if the device has one configured — not a secret worth encrypting further.
            $table->unsignedInteger('comm_key')->nullable();
            // Which branch/business-unit this device belongs to — same convention as sbu_code on hr_employees.
            $table->string('sbu_code', 50)->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        $prefix = config('hr.table_prefix', 'hr_');
        $connection = config('hr.drivers.database.connection') ?? config('database.default');

        Schema::connection($connection)->dropIfExists($prefix . 'zkteco_devices');
    }
};
