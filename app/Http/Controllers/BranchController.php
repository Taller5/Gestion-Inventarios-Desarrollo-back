<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BranchController extends Controller
{
    /**
     * Display a listing of the branches.
     */
    public function index()
    {
        $branches = Branch::with('business')->get();
        return response()->json($branches);
    }

    /**
     * Store a newly created branch in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'negocio_id' => 'required|exists:businesses,negocio_id',
            'nombre' => 'required|string|max:255',
            'provincia' => 'required|string|max:100',
            'canton' => 'required|string|max:100',
            'telefono' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $branch = Branch::create($request->all());
        return response()->json($branch->load('business'), 201);
    }

    /**
     * Display the specified branch.
     */
    public function show($id)
    {
        $branch = Branch::with('business')->findOrFail($id);
        return response()->json($branch);
    }

    /**
     * Update the specified branch in storage.
     */
    public function update(Request $request, $id)
    {
        $branch = Branch::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'negocio_id' => 'sometimes|required|exists:businesses,negocio_id',
            'nombre' => 'sometimes|required|string|max:255',
            'provincia' => 'sometimes|required|string|max:100',
            'canton' => 'sometimes|required|string|max:100',
            'telefono' => 'sometimes|required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $branch->update($request->all());
        return response()->json($branch->load('business'));
    }

    /**
     * Remove the specified branch from storage.
     */
    public function destroy($id)
    {
        $branch = Branch::findOrFail($id);
        $branch->delete();
        return response()->json(null, 204);
    }
}
