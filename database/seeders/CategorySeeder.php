<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // Alimentos
            ['nombre' => 'Frutas y Verduras', 'descripcion' => 'Frutas frescas, verduras y hortalizas.'],
            ['nombre' => 'Carnes y Pescados', 'descripcion' => 'Carne de res, pollo, cerdo y pescados.'],
            ['nombre' => 'Panadería y Pastelería', 'descripcion' => 'Pan, bollería y pasteles.'],
            ['nombre' => 'Lácteos y Huevos', 'descripcion' => 'Leche, quesos, yogures y huevos.'],
            ['nombre' => 'Bebidas', 'descripcion' => 'Jugos, refrescos, agua y bebidas alcohólicas.'],
            ['nombre' => 'Snacks y Dulces', 'descripcion' => 'Chocolates, galletas, frituras y golosinas.'],

            // Limpieza y hogar
            ['nombre' => 'Limpieza del Hogar', 'descripcion' => 'Detergentes, desinfectantes y productos de limpieza.'],
            ['nombre' => 'Cuidado Personal', 'descripcion' => 'Jabones, shampoo, higiene y cuidado personal.'],
            ['nombre' => 'Cocina y Utensilios', 'descripcion' => 'Utensilios de cocina, ollas, sartenes y accesorios.'],

            // Electrónica y tecnología
            ['nombre' => 'Electrónica', 'descripcion' => 'Celulares, computadoras, televisores y accesorios.'],
            ['nombre' => 'Electrodomésticos', 'descripcion' => 'Refrigeradores, microondas, lavadoras, licuadoras.'],

            // Moda y accesorios
            ['nombre' => 'Ropa', 'descripcion' => 'Ropa para hombres, mujeres y niños.'],
            ['nombre' => 'Calzado', 'descripcion' => 'Zapatos, sandalias, botas y zapatillas.'],
            ['nombre' => 'Accesorios', 'descripcion' => 'Bolsos, carteras, relojes y joyería.'],

            // Ocio y entretenimiento
            ['nombre' => 'Juguetes', 'descripcion' => 'Juguetes y juegos para todas las edades.'],
            ['nombre' => 'Libros y Papelería', 'descripcion' => 'Libros, cuadernos, lápices y material escolar.'],
            ['nombre' => 'Deportes y Aire Libre', 'descripcion' => 'Equipos deportivos, ropa y accesorios para exteriores.'],

            // Otros
            ['nombre' => 'Mascotas', 'descripcion' => 'Alimentos y accesorios para perros, gatos y otras mascotas.'],
            ['nombre' => 'Automotriz', 'descripcion' => 'Accesorios y productos para vehículos.'],
            ['nombre' => 'Ferretería', 'descripcion' => 'Herramientas, pinturas y materiales de construcción.'],
        ];

        foreach ($categories as $cat) {
            // updateOrCreate evita duplicados y funciona con PK string
            Category::updateOrCreate(
                ['nombre' => $cat['nombre']], // condición única
                ['descripcion' => $cat['descripcion']]
            );
        }
    }
}
