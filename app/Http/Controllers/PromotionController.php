<?php

namespace App\Http\Controllers;

use App\Models\Promotion;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;


class PromotionController extends Controller
{
    /**
     * Listar todas las promociones con sus productos
     */
    public function index()
    {
        $promotions = Promotion::with(['products' => function($q) {
            $q->select('products.id', 'products.nombre_producto');
        }])->get();

        // Aplanamos los productos
        $promotions->transform(function ($promotion) {
            $promotion->products = $promotion->products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'nombre_producto' => $product->nombre_producto,
                    'cantidad' => $product->pivot->cantidad,
                    'descuento' => $product->pivot->descuento,
                ];
            });
            return $promotion;
        });

        return response()->json($promotions);
    }

    /**
     * Crear promoción y asociar productos
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'tipo' => 'required|in:porcentaje,fijo,combo',
            'valor' => 'nullable|numeric',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'activo' => 'boolean',
            'products' => 'nullable|array',
            'products.*.product_id' => 'required|integer|exists:products,id',
            'products.*.cantidad' => 'nullable|integer|min:1',
            'products.*.descuento' => 'nullable|numeric|min:0',
            'business_id' => 'nullable|integer|exists:businesses,negocio_id',
            'branch_id' => 'nullable|integer|exists:branches,sucursal_id',
        ]);

        DB::beginTransaction();
        try {
            $promotion = Promotion::create($validated);

            if (!empty($validated['products'])) {
                $syncData = [];
                foreach ($validated['products'] as $p) {
                    $syncData[$p['product_id']] = [
                        'cantidad' => $p['cantidad'] ?? 1,
                        'descuento' => $p['descuento'] ?? 0,
                    ];
                }
                $promotion->products()->sync($syncData);
            }

            DB::commit();
            return response()->json($promotion->load('products'), 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error al crear la promoción',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener una promoción específica con sus productos
     */
    public function show($id)
    {
        $promotion = Promotion::with('products')->findOrFail($id);

        $promotion->products = $promotion->products->map(function ($product) {
            return [
                'id' => $product->id,
                'nombre_producto' => $product->nombre_producto,
                'cantidad' => $product->pivot->cantidad,
                'descuento' => $product->pivot->descuento,
            ];
        });

        return response()->json($promotion);
    }

    /**
     * Actualizar promoción y sus productos
     *//**
 * Actualizar promoción y sus productos
 */
public function update(Request $request, $id)
{
    $promotion = Promotion::findOrFail($id);

    $validated = $request->validate([
        'nombre' => 'sometimes|string|max:255',
        'descripcion' => 'nullable|string',
        'tipo' => 'sometimes|in:porcentaje,fijo,combo',
        'valor' => 'nullable|numeric',
        'fecha_inicio' => 'sometimes|date',
        'fecha_fin' => 'sometimes|date|after_or_equal:fecha_inicio',
        'activo' => 'boolean',
        'products' => 'nullable|array',
        'products.*.product_id' => 'required|integer|exists:products,id',
        'products.*.cantidad' => 'nullable|integer|min:0',
        'products.*.descuento' => 'nullable|numeric|min:0',
        'business_id' => 'nullable|integer|exists:businesses,negocio_id',
        'branch_id' => 'nullable|integer|exists:branches,sucursal_id',
    ]);

    DB::beginTransaction();
    try {
        // Actualizar datos generales
        $promotion->update($validated);

        // Actualizar productos y stock
        if (isset($validated['products'])) {
            $syncData = [];
            foreach ($validated['products'] as $p) {
                $syncData[$p['product_id']] = [
                    'cantidad' => $p['cantidad'] ?? 0, // si no viene, 0
                    'descuento' => $p['descuento'] ?? 0,
                ];
            }
            $promotion->products()->sync($syncData);
        }

        // 🔹 Detectar si la promoción debe estar activa según stock
        $activo = true;
        foreach ($promotion->products as $prod) {
            if (($prod->pivot->cantidad ?? 0) <= 0) {
                $activo = false; // si algún producto tiene 0 stock, desactivar
                break;
            }
        }
        $promotion->activo = $activo;
        $promotion->save();

        DB::commit();
        return response()->json($promotion->load('products'));

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'error' => 'Error al actualizar la promoción',
            'details' => $e->getMessage()
        ], 500);
    }
}


    /**
     * Eliminar promoción
     */
    public function destroy($id)
    {
        $promotion = Promotion::findOrFail($id);
        $promotion->delete();

        return response()->json(['message' => 'Promoción eliminada correctamente']);
    }

    /**
     * Obtener solo los productos de una promoción específica
     */
    public function products($promotionId)
    {
        $promotion = Promotion::findOrFail($promotionId);

        $products = $promotion->products->map(function ($product) {
            return [
                'id' => $product->id,
                'nombre_producto' => $product->nombre_producto,
                'cantidad' => $product->pivot->cantidad,
                'descuento' => $product->pivot->descuento,
            ];
        });

        return response()->json($products);
    }

     /**
     * Obtener promociones activas según negocio y sucursal
     */
/**
 * Obtener promociones activas según negocio
 */
/**
 * Obtener promociones activas según negocio
 */
public function activePromotions(Request $request)
{
    $businessId = $request->query('business_id');

    if (!$businessId) {
        return response()->json(['error' => 'El business_id es requerido'], 400);
    }

    // Buscar promociones activas solo por negocio
    $promotions = Promotion::with('products')
        ->where('business_id', $businessId)
        ->where('activo', true)
        ->get();

    return response()->json($promotions);
}




    /**
     * Obtener los productos de una promoción específica
     */
    public function promotionProducts($promotionId)
    {
        $promotion = Promotion::with('products')->findOrFail($promotionId);

        $products = $promotion->products->map(function ($product) {
            return [
                'id' => $product->id,
                'nombre_producto' => $product->nombre_producto,
                'cantidad' => $product->pivot->cantidad,
                'descuento' => $product->pivot->descuento,
            ];
        });

        return response()->json($products);
    }


public function applyPromotions(Request $request)
{
    DB::beginTransaction();

    try {
        $validated = $request->validate([
            'carrito' => 'required|array',
            'carrito.*.producto_id' => 'required|integer|exists:products,id',
            'carrito.*.cantidad' => 'required|integer|min:1',
            'business_id' => 'required|integer',
            'branch_id' => 'nullable|integer',
        ]);

        $today = Carbon::today();

        $promotions = Promotion::with(['products' => function ($q) {
            $q->select('products.id', 'products.nombre_producto')
              ->withPivot(['descuento', 'cantidad']);
        }])
        ->where('business_id', $validated['business_id'])
        ->where(function ($q) use ($validated) {
            $q->whereNull('branch_id')
              ->orWhere('branch_id', $validated['branch_id']);
        })
        ->whereDate('fecha_inicio', '<=', $today)
        ->whereDate('fecha_fin', '>=', $today)
        ->get();

        $carrito = collect($validated['carrito']);
        $carritoIds = $carrito->pluck('producto_id')->toArray();

        $carritoConPromos = $carrito->map(function ($item) use ($promotions, $carritoIds) {
            $productoId = $item['producto_id'];

            $promos = $promotions->filter(fn($p) => $p->products->contains('id', $productoId));

            if ($promos->isEmpty()) {
                return array_merge($item, [
                    'aplicada' => false,
                    'tipo' => null,
                    'descuento' => 0,
                    'promocion_id' => null,
                    'promocion_aplicada' => null,
                    'motivo_no_aplica' => 'Producto sin promoción activa',
                ]);
            }

            $mejorPromo = null;
            $descuentoFinal = 0;

            foreach ($promos as $promo) {
                $productosPromo = $promo->products->pluck('id')->toArray();

                if ($promo->tipo === 'combo') {
                    $todos = collect($productosPromo)->every(fn($id) => in_array($id, $carritoIds));
                    if (!$todos) continue;
                }

                $pivot = $promo->products->firstWhere('id', $productoId)?->pivot;
                $stock = $pivot->cantidad ?? 0;

                if ($stock <= 0) continue;

                $descuento = match($promo->tipo) {
                    'porcentaje', 'fijo' => $pivot->descuento ?? $promo->valor ?? 0,
                    'combo' => $pivot->descuento ?? ($promo->valor ? $promo->valor / max(count($productosPromo), 1) : 0),
                    default => 0,
                };

                if ($descuento > $descuentoFinal) {
                    $descuentoFinal = $descuento;
                    $mejorPromo = $promo;
                }
            }

            if (!$mejorPromo) {
                return array_merge($item, [
                    'aplicada' => false,
                    'tipo' => null,
                    'descuento' => 0,
                    'promocion_id' => null,
                    'promocion_aplicada' => null,
                    'motivo_no_aplica' => 'No hay stock o no cumple condiciones.',
                ]);
            }

            return array_merge($item, [
                'aplicada' => true,
                'tipo' => $mejorPromo->tipo,
                'descuento' => round($descuentoFinal, 2),
                'promocion_id' => $mejorPromo->id,
                'promocion_aplicada' => $mejorPromo->nombre,
            ]);
        });

        //  Solo actualizar stock de los productos, sin tocar "activo"
        foreach ($promotions as $promo) {
            foreach ($promo->products as $prod) {
                $itemCarrito = $carrito->firstWhere('producto_id', $prod->id);
                if ($itemCarrito) {
                    $stockActual = $prod->pivot->cantidad ?? 0;
                    $nuevoStock = max(0, $stockActual - $itemCarrito['cantidad']);
                    $promo->products()->updateExistingPivot($prod->id, ['cantidad' => $nuevoStock]);
                }
            }
        }

        DB::commit();

        $aplico = $carritoConPromos->contains(fn($i) => $i['aplicada'] && $i['descuento'] > 0);

        return response()->json([
            'success' => true,
            'aplico_alguna' => $aplico,
            'carrito' => $carritoConPromos->values(),
            'message' => $aplico
                ? 'Promociones aplicadas y stock actualizado correctamente.'
                : 'Ningún producto califica para promoción',
        ]);

    } catch (\Throwable $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'error' => 'Error al aplicar promociones o actualizar stock',
            'details' => $e->getMessage(),
        ], 500);
    }
}


public function restorePromotionStock(Request $request)
{
    DB::beginTransaction();

    try {
        $validated = $request->validate([
            'carrito' => 'required|array',
            'carrito.*.producto_id' => 'required|integer|exists:products,id',
            'carrito.*.cantidad' => 'required|integer|min:1',
            'carrito.*.promocion_id' => 'nullable|integer|exists:promotions,id',
        ]);

        Log::info(' Restaurando promociones del carrito', ['carrito' => $validated['carrito']]);

        $carrito = collect($validated['carrito']);

        foreach ($carrito as $item) {
            if (empty($item['promocion_id'])) {
                Log::debug('⏭ Producto sin promoción, se omite', $item);
                continue;
            }

            $promoId = $item['promocion_id'];
            $productoId = $item['producto_id'];
            $cantidad = $item['cantidad'];

            $promo = Promotion::find($promoId);
            if (!$promo) {
                Log::warning(" Promoción no encontrada", ['promocion_id' => $promoId]);
                continue;
            }

            $pivotRecord = $promo->products()->where('product_id', $productoId)->first();
            if (!$pivotRecord) {
                Log::warning(" Producto no encontrado en la promoción", [
                    'promocion_id' => $promoId,
                    'producto_id' => $productoId
                ]);
                continue;
            }

            $pivot = $pivotRecord->pivot;
            $stockActual = $pivot->cantidad ?? 0;
            $nuevaCantidad = $stockActual + $cantidad;

            $promo->products()->updateExistingPivot($productoId, [
                'cantidad' => $nuevaCantidad
            ]);

            Log::info(' Stock restaurado en promoción', [
                'promocion_id' => $promoId,
                'producto_id' => $productoId,
                'stock_anterior' => $stockActual,
                'cantidad_restaurada' => $cantidad,
                'nuevo_stock' => $nuevaCantidad
            ]);
        }

        DB::commit();

        //  Limpiar carrito: se devuelve vacío al frontend
        Log::info(' Carrito limpiado después de restaurar promociones');

        return response()->json([
            'success' => true,
            'message' => 'Stock de promociones restaurado correctamente.',
            'carrito' => [] // ← limpia el carrito
        ]);
    } catch (\Throwable $e) {
        DB::rollBack();
        Log::error(' Error al restaurar stock de promociones', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'error' => 'Error al restaurar stock de promociones',
            'details' => $e->getMessage()
        ], 500);
    }
}





}
