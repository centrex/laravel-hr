<?php

declare(strict_types = 1);

namespace Centrex\Hr\Commands;

use Illuminate\Console\Command;

class HrCommand extends Command
{
    public $signature = 'laravel-hr';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
