<?php


namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            ['role_name' => 'administrador', 'description' => 'Administrador del sistema'],
            ['role_name' => 'supervisor', 'description' => 'Supervisor de operaciones'],
            ['role_name' => 'bodeguero', 'description' => 'Encargado de bodega'],
            ['role_name' => 'vendedor', 'description' => 'Vendedor'],
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }
    }
}