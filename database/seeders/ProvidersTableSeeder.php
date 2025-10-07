<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Provider;
use Faker\Factory as Faker;

class ProvidersTableSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('es_CR'); // Faker con localización costarricense
        $total = 500;
        $batchSize = 100;
        $providersBatch = [];

        $prefixes = [
            'Distribuidora', 'Comercial', 'Importadora', 'Exportadora', 'Suministros',
            'Alimentos', 'Tecnología', 'Ferretería', 'Farmacéutica', 'Mayorista',
            'Grupo', 'Corporación', 'Super', 'Servicios', 'Industrias'
        ];

        $suffixes = [
            'Del Valle', 'San José', 'Costa Rica', 'Tropical', 'La Central', 'Global',
            'Soluciones', 'Premium', 'Económica', 'Verde', 'Nacional', 'El Amigo',
            'Express', 'Integral', 'Universal', 'Al Día', 'Moderna', 'Selecta'
        ];

        $domains = [
            'tropical.com', 'grupocr.com', 'puraempresa.com', 
            'negocioslatam.com', 'costaricasupply.com', 'serviciosglobal.com'
        ];

        $estados = ['Activo', 'Inactivo'];

        for ($i = 1; $i <= $total; $i++) {
            $name = $faker->randomElement($prefixes) . ' ' . $faker->randomElement($suffixes);
            $contact = $faker->firstName . ' ' . $faker->lastName;

            $domain = $faker->randomElement($domains);
            $email = strtolower(str_replace(' ', '.', $contact)) . '@' . $domain;

            // Generar teléfono realista de 8 dígitos
            $phone = $faker->numberBetween(20000000, 89999999);

            $state = $faker->randomElement($estados);

            $providersBatch[] = [
                'name'    => $name,
                'contact' => $contact,
                'email'   => $email,
                'phone'   => $phone,
                'state'   => $state,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($providersBatch) >= $batchSize) {
                Provider::insert($providersBatch);
                $providersBatch = [];
                $this->command->info("Insertados $i proveedores...");
            }
        }

        if (!empty($providersBatch)) {
            Provider::insert($providersBatch);
        }

        $this->command->info("Seeder completado: $total proveedores insertados.");
    }
}
