<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;
use App\Models\Warehouse;

class ProductsTableSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::pluck('nombre')->toArray();
        $warehouses = Warehouse::pluck('bodega_id')->toArray();

        if (empty($categories) || empty($warehouses)) {
            $this->command->error("No hay categorías o bodegas disponibles.");
            return;
        }

        $total = 2000;
        $batchSize = 500;
        $productsBatch = [];
        $nombresUsados = [];

        // Lista base de productos por categoría
        $productosPorCategoria = [
            "Accesorios" => ["Bolso", "Reloj", "Collar", "Cartera"],
            "Automotriz" => ["Aceite de motor", "Limpiaparabrisas", "Batería", "Filtro de aire"],
            "Bebidas" => ["Jugo", "Refresco", "Agua mineral", "Cerveza"],
            "Calzado" => ["Zapatillas", "Botas", "Sandalias", "Zapatos"],
            "Carnes y Pescados" => ["Pechuga de pollo", "Carne de res", "Salmón", "Cerdo"],
            "Cocina y Utensilios" => ["Sartén", "Olla", "Cuchillo", "Batidora"],
            "Cuidado Personal" => ["Shampoo", "Jabón", "Crema", "Cepillo de dientes"],
            "Deportes y Aire Libre" => ["Balón", "Raqueta", "Casco", "Mochila"],
            "Ferretería" => ["Martillo", "Taladro", "Pintura", "Tornillos"],
            "Frutas y Verduras" => ["Manzana", "Banano", "Lechuga", "Tomate"],
            "Juguetes" => ["Rompecabezas", "Muñeca", "Set de LEGO", "Carrito"],
            "Lácteos y Huevos" => ["Leche", "Queso fresco", "Yogur", "Huevos"],
            "Libros y Papelería" => ["Cuaderno", "Bolígrafo", "Lápices", "Agenda"],
            "Limpieza del Hogar" => ["Detergente", "Limpiador", "Esponja", "Desinfectante"],
            "Mascotas" => ["Comida para perros", "Arena para gatos", "Juguete para gatos", "Collar antipulgas"],
            "Medicamentos" => ["Paracetamol", "Ibuprofeno", "Jarabe", "Vitamina C"],
            "Panadería y Pastelería" => ["Pan", "Croissant", "Bollo", "Pastel"],
            "Ropa" => ["Camiseta", "Pantalón", "Sudadera", "Vestido"],
            "Snacks y Dulces" => ["Chocolate", "Galletas", "Papas fritas", "Caramelos"],
            "Electrodomésticos" => ["Refrigerador", "Microondas", "Lavadora", "Licuadora"],
            "Electrónica" => ["Smartphone", "Laptop", "Televisor", "Auriculares"],
        ];

        $rangosPorCategoria = [
            "Accesorios" => [5000, 30000],
            "Automotriz" => [3000, 80000],
            "Bebidas" => [500, 3000],
            "Calzado" => [15000, 60000],
            "Carnes y Pescados" => [2500, 12000],
            "Cocina y Utensilios" => [4000, 50000],
            "Cuidado Personal" => [1000, 20000],
            "Deportes y Aire Libre" => [5000, 80000],
            "Ferretería" => [1000, 70000],
            "Frutas y Verduras" => [500, 4000],
            "Juguetes" => [3000, 40000],
            "Lácteos y Huevos" => [800, 6000],
            "Libros y Papelería" => [500, 15000],
            "Limpieza del Hogar" => [1000, 8000],
            "Mascotas" => [2000, 25000],
            "Medicamentos" => [800, 20000],
            "Panadería y Pastelería" => [500, 8000],
            "Ropa" => [8000, 50000],
            "Snacks y Dulces" => [500, 5000],
            "Electrodomésticos" => [20000, 250000],
            "Electrónica" => [15000, 400000],
        ];

        // Variaciones para generar nombres únicos
        $marcas = ["Tico", "Premium", "Del Valle", "Global", "Eco", "Max", "Ultra", "Selecta", "San José", "CR", "Nature", "Smart"];
        $presentaciones = ["500ml", "1L", "2L", "kg", "250g", "Caja", "Pack", "Bolsa", "Unidad", "Edición especial"];

        for ($i = 1; $i <= $total; $i++) {
            $categoria = $categories[array_rand($categories)];
            $bodega = $warehouses[array_rand($warehouses)];

            $base = $productosPorCategoria[$categoria][array_rand($productosPorCategoria[$categoria])];
            $marca = $marcas[array_rand($marcas)];
            $presentacion = $presentaciones[array_rand($presentaciones)];

            $nombre = "$base $marca $presentacion";

            // Asegurar que el nombre no se repita
            while (isset($nombresUsados[$nombre])) {
                $marca = $marcas[array_rand($marcas)];
                $presentacion = $presentaciones[array_rand($presentaciones)];
                $nombre = "$base $marca $presentacion";
            }
            $nombresUsados[$nombre] = true;

            $codigo = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $base), 0, 3))
                    . str_pad($i, 6, '0', STR_PAD_LEFT);

            $descripcion = "Producto de la categoría $categoria.";

            $stock = rand(0, 500);

            [$min, $max] = $rangosPorCategoria[$categoria];
            $precio_compra = rand($min, $max);
            $precio_venta = round($precio_compra * rand(115, 180) / 100);

            $productsBatch[] = [
                'codigo_producto' => $codigo,
                'nombre_producto' => $nombre,
                'categoria'       => $categoria,
                'descripcion'     => $descripcion,
                'stock'           => $stock,
                'precio_compra'   => $precio_compra,
                'precio_venta'    => $precio_venta,
                'bodega_id'       => $bodega,
                'created_at'      => now(),
                'updated_at'      => now(),
            ];

            if (count($productsBatch) >= $batchSize) {
                Product::insert($productsBatch);
                $productsBatch = [];
                $this->command->info("Insertados $i productos...");
            }
        }

        if (!empty($productsBatch)) {
            Product::insert($productsBatch);
        }

        $this->command->info("Seeder completado: $total productos únicos insertados.");
    }
}
