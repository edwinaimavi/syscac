<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call([
            RoleSeeder::class,
            LateFeePermissionSeeder::class,
            ReportPermissionSeeder::class,
            AdministrativeFundPermissionSeeder::class,
        ]);

        User::firstOrCreate([
            'email' => 'edwin.3acp@gmail.com',
        ], [
            'name' => 'Edwin Cigueñas',
            'password' => bcrypt('12345678'),
        ])->assignRole('Administrador');
        User::factory(3)->create();

        $this->call([
          
        ]);
    }
}
