<?php

namespace App\Console\Commands;

use Database\Seeders\RolePermissionsSeeder;
use Illuminate\Console\Command;

class SyncPermissionsAndRoles extends Command
{
    protected $signature = 'sync:permissions';

    protected $description = 'Sync permissions and roles';

    public function handle(): void
    {
        $seeder = new RolePermissionsSeeder();
        $seeder->run();
    }
}
