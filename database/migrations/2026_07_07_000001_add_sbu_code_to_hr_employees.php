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
            // Free-text business-unit/company tag — same convention as sbu_code on
            // laravel-accounting's JournalEntry/Invoice and laravel-inventory's documents.
            // Lets one HR install span multiple companies (e.g. a group of sister companies)
            // and have payroll postings segregated by entity once synced to laravel-payroll.
            if (!$schema->hasColumn($prefix . 'employees', 'sbu_code')) {
                $table->string('sbu_code', 50)->nullable()->after('department_id')->index();
            }
        });
    }

    public function down(): void
    {
        $prefix = config('hr.table_prefix', 'hr_');
        $connection = config('hr.drivers.database.connection') ?? config('database.default');
        $schema = Schema::connection($connection);

        $schema->table($prefix . 'employees', function (Blueprint $table) use ($prefix, $schema): void {
            if ($schema->hasColumn($prefix . 'employees', 'sbu_code')) {
                $table->dropColumn('sbu_code');
            }
        });
    }
};
