<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use App\Models\Product;

class BatchController extends Controller
{
    public function index(Request $request)
    {
        // Devuelve todos los lotes con su producto
        return response()->json(Batch::with('producto')->get());
    }

    public function show($id)
    {
        $lote = Batch::with('producto')->findOrFail($id);
        return response()->json($lote);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'codigo_producto' => 'required|exists:products,codigo_producto',
            'numero_lote' => 'required',
            'cantidad' => 'required|integer|min:0',
            'proveedor' => 'required',
            'fecha_entrada' => 'required|date',
            'fecha_vencimiento' => 'required|date',
            'fecha_salida_lote' => 'nullable|date',
            'descripcion' => 'nullable',
            'nombre' => 'required'
        ]);

        $lote = Batch::create($data);

        // Actualizar stock del producto
        $producto = Product::where('codigo_producto', $data['codigo_producto'])->first();
        if ($producto) {
            $producto->stock += $data['cantidad'];
            $producto->save();
        }

        return response()->json($lote, 201);
    }

    public function update(Request $request, $id)
    {
        $lote = Batch::findOrFail($id);

        $data = $request->validate([
            'numero_lote' => 'sometimes|required',
            'cantidad' => 'sometimes|required|integer|min:0',
            'proveedor' => 'sometimes|required',
            'fecha_entrada' => 'sometimes|required|date',
            'fecha_vencimiento' => 'sometimes|required|date',
            'fecha_salida_lote' => 'nullable|date',
            'descripcion' => 'nullable',
            'nombre' => 'sometimes|required'
        ]);

    $producto = Product::where('codigo_producto', $lote->codigo_producto)->first();

        if ($producto && isset($data['cantidad'])) {
            // Guardar cantidad vieja
            $cantidadVieja = $lote->cantidad;

            // Ajustar stock basado en diferencia
            $producto->stock = $producto->stock - $cantidadVieja + $data['cantidad'];
            $producto->save();
        }

        $lote->update($data);

        return response()->json($lote);
    }

    public function destroy($id)
    {
        $lote = Batch::findOrFail($id);

        // Reducir stock del producto
    $producto = Product::where('codigo_producto', $lote->codigo_producto)->first();
        if ($producto) {
            $producto->stock -= $lote->cantidad;
            if ($producto->stock < 0) $producto->stock = 0;
            $producto->save();
        }

        $lote->delete();
        return response()->json(null, 204);
    }
}
