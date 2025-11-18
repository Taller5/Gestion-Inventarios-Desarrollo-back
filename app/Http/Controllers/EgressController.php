<?php

namespace App\Http\Controllers;

use App\Models\Egress;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Product;

class EgressController extends Controller
{
    public function index()
    {
        return response()->json(
            Egress::with(['producto', 'bodegaOrigen', 'bodegaDestino'])->get()
        );
    }

    public function show($id)
    {
        $egreso = Egress::with(['producto', 'bodegaOrigen', 'bodegaDestino'])->findOrFail($id);
        return response()->json($egreso);
    }

public function store(Request $request)
{
    $data = $request->validate([
        'codigo_producto' => 'required|exists:products,codigo_producto',
        'cantidad' => 'required|integer|min:1',
        'motivo' => 'required|string',
        'descripcion' => 'nullable|string',
        'bodega_origen_id' => 'required|exists:warehouses,bodega_id',
        'bodega_destino_id' => 'nullable|exists:warehouses,bodega_id',
    ]);

    // Buscar producto en la bodega de origen
    $producto = Product::where('codigo_producto', $data['codigo_producto'])
        ->where('bodega_id', $data['bodega_origen_id'])
        ->first();

    if (!$producto) {
        return response()->json([
            'error' => true,
            'message' => 'El producto no existe en la bodega de origen.'
        ], 400);
    }

    if ($producto->stock < $data['cantidad']) {
        return response()->json([
            'error' => true,
            'message' => 'Stock insuficiente para realizar el egreso.'
        ], 400);
    }

    // Si hay bodega destino, sumar stock
    if (!empty($data['bodega_destino_id'])) {
        $productoDestino = Product::where('codigo_producto', $data['codigo_producto'])
            ->where('bodega_id', $data['bodega_destino_id'])
            ->first();

        if ($productoDestino) {
            $productoDestino->stock += $data['cantidad'];
            $productoDestino->save();
        }
    }

    // Asignar fecha actual automáticamente
    $data['fecha'] = now();

    // Crear registro del egreso
    $egreso = Egress::create($data);

    return response()->json($egreso, 201);
}


    public function destroy($id)
    {
        $egreso = Egress::findOrFail($id);
        $egreso->delete();
        return response()->json(null, 204);
    }
}
