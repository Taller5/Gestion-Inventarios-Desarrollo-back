<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BusinessController extends Controller
{
    /**
     * Display the specified business.
     */
    
    public function index()
    {
        $businesses = Business::all();
        return response()->json($businesses); // Devuelve [] si no hay negocios
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre_legal' => 'required|string|max:255',
            'nombre_comercial' => 'required|string|max:255',
            'tipo_identificacion' => 'required|string|max:255',
            'numero_identificacion' => 'required|string|unique:businesses,numero_identificacion',
            'margen_ganancia' => 'nullable|numeric',
            'descripcion' => 'nullable|string',
            'telefono' => 'required|string|max:20',
            'email' => 'required|email|unique:businesses,email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errores' => $validator->errors()], 400);
        }

        $business = Business::create($validator->validated());

        return response()->json($business, 201); 
    }


    public function show($id)
    {
        $business = Business::find($id);

        if (!$business) {
            return response()->json(['error' => 'Negocio no encontrado'], 404);
        }

        return response()->json($business);
    }

    /**
     * Update the specified business in storage.
     */
    public function update(Request $request, $id)
    {
        $business = Business::find($id);

        if (!$business) {
            return response()->json(['error' => 'Negocio no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre_legal' => 'sometimes|required|string|max:255',
            'nombre_comercial' => 'sometimes|required|string|max:255',
            'tipo_identificacion' => 'required|string|max:255',
            'numero_identificacion' => 'sometimes|required|string|unique:businesses,numero_identificacion,' . $id . ',negocio_id',
            'margen_ganancia' => 'nullable|numeric',
            'descripcion' => 'nullable|string',
            'telefono' => 'sometimes|required|string|max:20',
            'email' => 'sometimes|required|email|unique:businesses,email,' . $id . ',negocio_id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errores' => $validator->errors()], 400);
        }

        $business->update($validator->validated());
        return response()->json($business);
    }

    /**
     * Remove the specified business from storage.
     */
    public function destroy($id)
    {
        $business = Business::find($id);

        if (!$business) {
            return response()->json(['error' => 'Negocio no encontrado'], 404);
        }

        $business->delete();
        return response()->json(['mensaje' => 'Negocio eliminado correctamente'], 204);
    }
}
