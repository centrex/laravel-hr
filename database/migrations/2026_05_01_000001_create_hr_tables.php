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
        $connection = config('hr.drivers.database.connection', config('database.default'));

        Schema::connection($connection)->create($prefix . 'departments', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('manager_id');
            $table->index('is_active');
        });

        Schema::connection($connection)->create($prefix . 'designations', function (Blueprint $table) use ($prefix): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->foreignId('department_id')->nullable()->constrained($prefix . 'departments')->nullOnDelete();
            $table->text('description')->nullable();
            $table->decimal('salary_min', 18, 2)->default(0);
            $table->decimal('salary_max', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['department_id', 'is_active']);
        });

        Schema::connection($connection)->create($prefix . 'employees', function (Blueprint $table) use ($prefix): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->foreignId('department_id')->nullable()->constrained($prefix . 'departments')->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained($prefix . 'designations')->nullOnDelete();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->string('employment_type')->default('full_time');
            $table->string('status')->default('active');
            $table->date('joining_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->decimal('monthly_salary', 18, 2)->default(0);
            $table->string('currency', 3)->default('BDT');
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->nullableMorphs('payroll_profile');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('manager_id')->references('id')->on($prefix . 'employees')->nullOnDelete();
            $table->index(['department_id', 'status']);
            $table->index(['designation_id', 'status']);
            $table->index('is_active');
        });

        Schema::connection($connection)->create($prefix . 'leave_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedInteger('annual_allowance')->default(0);
            $table->boolean('is_paid')->default(true);
            $table->boolean('requires_approval')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($connection)->create($prefix . 'leave_requests', function (Blueprint $table) use ($prefix): void {
            $table->id();
            $table->foreignId('employee_id')->constrained($prefix . 'employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained($prefix . 'leave_types')->restrictOnDelete();
            $table->date('starts_at');
            $table->date('ends_at');
            $table->decimal('days', 8, 2);
            $table->string('status')->default('pending');
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'status']);
            $table->index(['starts_at', 'ends_at']);
        });

        Schema::connection($connection)->create($prefix . 'attendances', function (Blueprint $table) use ($prefix): void {
            $table->id();
            $table->foreignId('employee_id')->constrained($prefix . 'employees')->cascadeOnDelete();
            $table->date('work_date');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->decimal('worked_hours', 8, 2)->default(0);
            $table->string('status')->default('present');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'work_date']);
            $table->index(['work_date', 'status']);
        });
    }

    public function down(): void
    {
        $prefix = config('hr.table_prefix', 'hr_');
        $connection = config('hr.drivers.database.connection', config('database.default'));

        Schema::connection($connection)->dropIfExists($prefix . 'attendances');
        Schema::connection($connection)->dropIfExists($prefix . 'leave_requests');
        Schema::connection($connection)->dropIfExists($prefix . 'leave_types');
        Schema::connection($connection)->dropIfExists($prefix . 'employees');
        Schema::connection($connection)->dropIfExists($prefix . 'designations');
        Schema::connection($connection)->dropIfExists($prefix . 'departments');
    }
};
