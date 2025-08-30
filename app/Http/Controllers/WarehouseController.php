<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WarehouseController extends Controller
{
    /**
     * Display a listing of the warehouses.
     */
    public function index()
    {
        $warehouses = Warehouse::with('branch.business')->get();
        return response()->json($warehouses);
    }

    /**
     * Store a newly created warehouse in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sucursal_id' => 'required|exists:branches,sucursal_id',
            'codigo' => 'required|string|max:50|unique:warehouses,codigo',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $warehouse = Warehouse::create($request->all());
        return response()->json($warehouse->load('branch.business'), 201);
    }

    /**
     * Display the specified warehouse.
     */
    public function show($id)
    {
        $warehouse = Warehouse::with('branch.business')->findOrFail($id);
        return response()->json($warehouse);
    }

    /**
     * Update the specified warehouse in storage.
     */
    public function update(Request $request, $id)
    {
        $warehouse = Warehouse::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'sucursal_id' => 'sometimes|required|exists:branches,sucursal_id',
            'codigo' => 'sometimes|required|string|max:50|unique:warehouses,codigo,' . $id . ',bodega_id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $warehouse->update($request->all());
        return response()->json($warehouse->load('branch.business'));
    }

    /**
     * Remove the specified warehouse from storage.
     */
    public function destroy($id)
    {
        $warehouse = Warehouse::findOrFail($id);
        $warehouse->delete();
        return response()->json(null, 204);
    }
}
