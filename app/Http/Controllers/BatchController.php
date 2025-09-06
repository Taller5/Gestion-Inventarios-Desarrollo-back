<?php

namespace App\Http\Controllers;
use App\Models\Batch;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;

class BatchController extends Controller
{
     public function index(Request $request)
    {
        // Devuelve todos los lotes con su producto y bodega
        return response()->json(
            Batch::with('producto')->get()
        );
    }

    public function show($id)
    {
       $lote = Batch::with('producto')->findOrFail($id);
        return response()->json($lote);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'codigo' => 'required|exists:products,codigo',
            'numero_lote' => 'required',
            'cantidad' => 'required|integer|min:0',
            'proveedor' => 'required',
            'fecha_entrada' => 'required|date',
            'fecha_salida' => 'required|date',
            'fecha_salida_lote' => 'nullable|date',
            'descripcion' => 'nullable',
            'nombre' => 'required'
        ]);
        $lote = Batch::create($data);
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
            'fecha_salida' => 'sometimes|required|date',
            'fecha_salida_lote' => 'nullable|date',
            'descripcion' => 'nullable',
            'nombre' => 'sometimes|required'
        ]);
        $lote->update($data);
        return response()->json($lote);
    }

    public function destroy($id)
    {
        $lote = Batch::findOrFail($id);
        $lote->delete();
        return response()->json(null, 204);
    }
}
