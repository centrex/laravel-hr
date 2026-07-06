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
        $schema = Schema::connection($connection);

        $schema->table($prefix . 'employees', function (Blueprint $table) use ($prefix, $schema): void {
            // Links this HR employee to the system user, for self-service check-in/leave
            // requests tied to the logged-in auth user. No FK constraint since the users
            // table may live on a different connection.
            if (!$schema->hasColumn($prefix . 'employees', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('code');
            }
        });
    }

    public function down(): void
    {
        $prefix = config('hr.table_prefix', 'hr_');
        $connection = config('hr.drivers.database.connection') ?? config('database.default');
        $schema = Schema::connection($connection);

        $schema->table($prefix . 'employees', function (Blueprint $table) use ($prefix, $schema): void {
            if ($schema->hasColumn($prefix . 'employees', 'user_id')) {
                $table->dropColumn('user_id');
            }
        });
    }
};
