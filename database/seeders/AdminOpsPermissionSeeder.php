<?php

namespace App\Core\AdminOps\database\seeders;

use App\Core\RBAC\Support\SeedsPermissions;
use Illuminate\Database\Seeder;

class AdminOpsPermissionSeeder extends Seeder
{
    use SeedsPermissions;

    public function run(): void
    {
        $this->seedPermissions([
            'dashboard_read',
        ]);
    }
}
