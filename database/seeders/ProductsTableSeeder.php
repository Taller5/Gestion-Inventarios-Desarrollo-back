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
        // Obtener categorías y bodegas existentes
        $categories = Category::pluck('nombre')->toArray();
        $warehouses = Warehouse::pluck('bodega_id')->toArray();

        if (empty($categories) || empty($warehouses)) {
            $this->command->error("No hay categorías o bodegas disponibles.");
            return;
        }

        $total = 5000; // cantidad de productos
        $batchSize = 500; // insert por batch para no saturar memoria
        $productsBatch = [];

        // Lista de nombres realistas por categoría
        $productosPorCategoria = [
            "Accesorios" => ["Bolsos de cuero", "Reloj de pulsera", "Collar de plata", "Cartera de mujer"],
            "Automotriz" => ["Aceite de motor 5W30", "Limpiaparabrisas", "Batería 12V", "Filtro de aire"],
            "Bebidas" => ["Jugo de naranja", "Coca-Cola 500ml", "Agua mineral", "Cerveza artesanal"],
            "Calzado" => ["Zapatillas deportivas", "Botas de cuero", "Sandalias", "Zapatos formales"],
            "Carnes y Pescados" => ["Pechuga de pollo", "Carne de res", "Salmón fresco", "Cerdo ahumado"],
            "Cocina y Utensilios" => ["Sartén antiadherente", "Olla a presión", "Cuchillo chef", "Batidora eléctrica"],
            "Cuidado Personal" => ["Shampoo hidratante", "Jabón de glicerina", "Crema facial", "Cepillo de dientes eléctrico"],
            "Deportes y Aire Libre" => ["Balón de fútbol", "Raqueta de tenis", "Casco ciclismo", "Mochila senderismo"],
            "Ferretería" => ["Martillo", "Taladro eléctrico", "Pintura acrílica", "Tornillos variados"],
            "Frutas y Verduras" => ["Manzana roja", "Banana", "Lechuga", "Tomate cherry"],
            "Juguetes" => ["Rompecabezas 100 piezas", "Muñeca articulada", "Set de LEGO", "Carrito a control remoto"],
            "Lácteos y Huevos" => ["Leche entera 1L", "Queso fresco", "Yogur natural", "Huevos de granja"],
            "Libros y Papelería" => ["Cuaderno universitario", "Bolígrafo azul", "Lápices de colores", "Agenda 2025"],
            "Limpieza del Hogar" => ["Detergente líquido", "Limpiador multiusos", "Esponja para platos", "Desinfectante en spray"],
            "Mascotas" => ["Comida para perros", "Arena para gatos", "Juguete para gatos", "Collar antipulgas"],
            "Medicamentos" => ["Paracetamol 500mg", "Ibuprofeno 200mg", "Jarabe para la tos", "Vitamina C 1000mg"],
            "Panadería y Pastelería" => ["Pan integral", "Croissant", "Bollos de canela", "Pastel de chocolate"],
            "Ropa" => ["Camiseta algodón", "Pantalón jean", "Sudadera con capucha", "Vestido de verano"],
            "Snacks y Dulces" => ["Chocolate amargo", "Galletas de avena", "Papas fritas", "Caramelos surtidos"],
            "Electrodomésticos" => ["Refrigerador 300L", "Microondas 20L", "Lavadora automática", "Licuadora de vaso"],
            "Electrónica" => ["Smartphone 128GB", "Laptop 16GB RAM", "Televisor 50 pulgadas", "Auriculares bluetooth"],
        ];

        for ($i = 1; $i <= $total; $i++) {

            // Seleccionar categoría y bodega aleatoria
            $categoria = $categories[array_rand($categories)];
            $bodega = $warehouses[array_rand($warehouses)];

            // Nombre de producto realista según categoría
            $nombreOpciones = $productosPorCategoria[$categoria] ?? ["Producto genérico"];
            $nombre = $nombreOpciones[array_rand($nombreOpciones)];

            // Código único
            $codigo = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $nombre), 0, 3))
                      . str_pad($i, 6, '0', STR_PAD_LEFT);

            // Descripción simple
            $descripcion = "Producto de la categoría $categoria";

            $stock = rand(0, 500);
            $precio_compra = round(rand(100, 10000) / 100, 2); // entre 1.00 y 100.00
            $precio_venta = round($precio_compra * rand(120, 200) / 100, 2); // +20% a +100%

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

            // Insertar en batch
            if (count($productsBatch) >= $batchSize) {
                Product::insert($productsBatch);
                $productsBatch = [];
                $this->command->info("Insertados $i productos...");
            }
        }

        // Insertar últimos productos
        if (!empty($productsBatch)) {
            Product::insert($productsBatch);
        }

        $this->command->info("Seeder completado: $total productos insertados.");
    }
}
