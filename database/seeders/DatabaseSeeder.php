<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeds roles/permissions and the approved website content (pages, menus,
     * legacy .html redirects). No user accounts or passwords are seeded:
     * create the first administrator with `php artisan cms:create-admin`.
     */
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            ContentSeeder::class,
        ]);
    }
}
