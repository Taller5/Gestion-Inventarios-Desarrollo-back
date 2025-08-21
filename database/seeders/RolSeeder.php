<?php


namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Rol;

class RolSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            ['nombre_rol' => 'administrador', 'descripcion' => 'Administrador del sistema'],
            ['nombre_rol' => 'supervisor', 'descripcion' => 'Supervisor de operaciones'],
            ['nombre_rol' => 'bodeguero', 'descripcion' => 'Encargado de bodega'],
            ['nombre_rol' => 'vendedor', 'descripcion' => 'Vendedor'],
        ];

        foreach ($roles as $rol) {
            Rol::create($rol);
        }
    }
}