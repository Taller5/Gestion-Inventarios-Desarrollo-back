<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BusinessController extends Controller
{
    /**
     * Display a listing of the businesses.
     */
    public function index()
    {
        $businesses = Business::all();
        return response()->json($businesses);
    }

    /**
     * Store a newly created business in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'telefono' => 'required|string|max:20',
            'email' => 'required|email|unique:businesses,email',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $business = Business::create($request->all());
        return response()->json($business, 201);
    }

    /**
     * Display the specified business.
     */
    public function show($id)
    {
        $business = Business::findOrFail($id);
        return response()->json($business);
    }

    /**
     * Update the specified business in storage.
     */
    public function update(Request $request, $id)
    {
        $business = Business::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|required|string|max:255',
            'descripcion' => 'nullable|string',
            'telefono' => 'sometimes|required|string|max:20',
            'email' => 'sometimes|required|email|unique:businesses,email,' . $id . ',negocio_id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $business->update($request->all());
        return response()->json($business);
    }

    /**
     * Remove the specified business from storage.
     */
    public function destroy($id)
    {
        $business = Business::findOrFail($id);
        $business->delete();
        return response()->json(null, 204);
    }
}
