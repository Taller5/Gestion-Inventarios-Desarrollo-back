<?php

namespace App\Http\Controllers;
use Illuminate\Routing\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
     public function index(Request $request)
    {
        // Devuelve todos los productos con su bodega y lotes
        return response()->json(
            Product::with('bodega', 'lotes')->get()
        );
    }

    public function show($id)
    {
        $product = Product::with('bodega', 'lotes')->findOrFail($id);
        return response()->json($product);
    }

   public function store(Request $request)
{
    $data = $request->validate([
        'codigo_producto' => 'required|unique:products,codigo_producto',
        'nombre_producto' => 'required',
        'categoria' => 'required|string',
        'descripcion' => 'nullable',
        'stock' => 'required|integer|min:0',
        'precio_compra' => 'required|numeric|min:0',
        'precio_venta' => 'required|numeric|min:0',
        'bodega_id' => 'required|exists:warehouses,bodega_id',
    ]);

    $product = Product::create($data);
    $product->load('bodega', 'lotes');

    return response()->json($product, 201);
}


    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $data = $request->validate([
            'nombre_producto' => 'sometimes|required',
            'categoria' => 'sometimes|required|string',
            'descripcion' => 'nullable',
            'stock' => 'sometimes|required|integer|min:0',
            'precio_compra' => 'sometimes|required|numeric|min:0',
            'precio_venta' => 'sometimes|required|numeric|min:0',
            'bodega_id' => 'sometimes|required|exists:warehouses,bodega_id',
        ]);
        $product->update($data);
        return response()->json($product);
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();
        return response()->json(null, 204);
    }
}
