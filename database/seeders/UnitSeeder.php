<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Unit;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['unidMedida' => 'cm', 'descripcion' => 'centímetro'],
            ['unidMedida' => 'G', 'descripcion' => 'Gramo'],
            ['unidMedida' => 'Gal', 'descripcion' => 'Galón'],
            ['unidMedida' => 'Kg', 'descripcion' => 'Kilogramo'],
            ['unidMedida' => 'L', 'descripcion' => 'litro'],
            ['unidMedida' => 'Ln', 'descripcion' => 'pulgada'],
            ['unidMedida' => 'M', 'descripcion' => 'Metro'],
            ['unidMedida' => 'mL', 'descripcion' => 'mililitro'],
            ['unidMedida' => 'Mm', 'descripcion' => 'Milímetro'],
            ['unidMedida' => 'Os', 'descripcion' => 'Otro tipo de servicio'],
            ['unidMedida' => 'Oz', 'descripcion' => 'Onzas'],
            ['unidMedida' => 'Sp', 'descripcion' => 'Servicios Profesionales'],
            ['unidMedida' => 'Unid', 'descripcion' => 'Unidad'],
        ];

        foreach ($units as $u) {
            Unit::updateOrCreate(
                ['unidMedida' => $u['unidMedida']],
                ['descripcion' => $u['descripcion']]
            );
        }
    }
}
