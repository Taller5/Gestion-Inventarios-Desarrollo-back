<?php

namespace App\Http\Controllers;

use App\Models\IAHistory;
use Illuminate\Http\Request;

class IAHistoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = IAHistory::query();

        // Filtrar por tipo como enum
        $type = request()->query('type');
        if ($type && in_array($type, ['diario', 'anual'])) {
            $query->where('type', $type);
        }

        $histories = $query->orderBy('created_at', 'desc')->get();

        return response()->json($histories);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'type' => 'required|string|in:diario,anual', // enum validación
            'product_id' => 'required|integer|exists:products,id',
            'future_price' => 'required|numeric',
            'promotion_active' => 'required|integer|in:0,1',
            'history' => 'required|array',
        ]);

        $history = IAHistory::create([
            'user_id' => $request -> user_id,
            'type' => $request -> type,
            'product_id' => $request -> product_id,
            'future_price' => $request -> future_price,
            'promotion_active' => $request -> promotion_active,
            'history' => $request -> history,
        ]);

        return response()->json($history, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $type) //type
    {
        if (!in_array($type, ['diario', 'anual'])) {
            return response()->json(['error' => 'Tipo inválido'], 400);
        }

        $histories = IAHistory::where('type', $type)->orderBy('created_at', 'desc')->get();
        return response()->json($histories);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $history = IAHistory::findOrFail($id);
        $history->delete();

        return response()->json(['message' => 'Registro eliminado correctamente.']);
    }
}
