<?php

declare(strict_types = 1);

namespace Centrex\Hr;

use Centrex\Hr\Commands\HrCommand;
use Centrex\Hr\Http\Livewire\Entities\{EntityFormPage, EntityIndexPage};
use Centrex\Hr\Http\Livewire\HrDashboard;
use Illuminate\Support\Facades\{Blade, Gate};
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class HrServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'laravel-hr');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'hr');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->registerViteDirective();

        if ((bool) config('hr.web_enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        }

        if ((bool) config('hr.api_enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        }

        $this->app->booted(function (): void {
            $this->registerLivewireComponents();
        });
        $this->registerGates();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/config.php' => config_path('hr.php'),
            ], 'hr-config');

            $this->publishes([
                __DIR__ . '/../database/migrations/' => database_path('migrations'),
            ], 'hr-migrations');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/hr'),
            ], 'hr-views');

            $this->commands([HrCommand::class]);
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'hr');

        $this->app->singleton(Hr::class, fn (): Hr => new Hr());
        $this->app->alias(Hr::class, 'hr');
        $this->app->alias(Hr::class, 'laravel-hr');
    }

    protected function registerGates(): void
    {
        $abilities = [
            'hr.employees.view',
            'hr.employees.manage',
            'hr.departments.view',
            'hr.departments.manage',
            'hr.leave.view',
            'hr.leave.request',
            'hr.leave.approve',
            'hr.attendance.view',
            'hr.attendance.manage',
        ];

        foreach ($abilities as $ability) {
            if (!Gate::has($ability)) {
                Gate::define($ability, function ($user): bool {
                    if (Gate::has('hr-admin') && Gate::forUser($user)->check('hr-admin')) {
                        return true;
                    }

                    if (method_exists($user, 'hasRole')) {
                        return $user->hasRole($this->normalizeAdminRoles(config('hr.admin_roles', [])));
                    }

                    return false;
                });
            }
        }
    }

    private function normalizeAdminRoles(array|string|null $roles): array
    {
        if (is_array($roles)) {
            return array_values(array_filter(array_map('strval', $roles)));
        }

        if (!is_string($roles) || trim($roles) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $roles))));
    }

    private function registerLivewireComponents(): void
    {
        if (!class_exists(Livewire::class) || !$this->app->bound('livewire.finder')) {
            return;
        }

        Livewire::component('hr-dashboard', HrDashboard::class);
        Livewire::component('hr-entity-index', EntityIndexPage::class);
        Livewire::component('hr-entity-form', EntityFormPage::class);
    }

    private function registerViteDirective(): void
    {
        Blade::directive('hrVite', fn (): string => sprintf(
            '<?php echo \\Centrex\\TallUi\\Support\\PackageVite::render(%s, %s, %s); ?>',
            var_export(dirname(__DIR__), true),
            var_export('hr.hot', true),
            var_export(['resources/js/app.js'], true),
        ));
    }
}
