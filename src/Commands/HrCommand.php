<?php

declare(strict_types = 1);

namespace Centrex\Hr\Commands;

use Illuminate\Console\Command;

class HrCommand extends Command
{
    public $signature = 'hr:install';

    public $description = 'Show HR package installation guidance';

    public function handle(): int
    {
        $this->info('Publish HR config and migrations, then run migrate:');
        $this->line('php artisan vendor:publish --tag=hr-config');
        $this->line('php artisan vendor:publish --tag=hr-migrations');
        $this->line('php artisan migrate');

        return self::SUCCESS;
    }
}
