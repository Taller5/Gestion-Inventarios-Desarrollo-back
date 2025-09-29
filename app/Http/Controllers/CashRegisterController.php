<?php

namespace App\Http\Controllers;

use App\Models\CashRegister;
use Illuminate\Http\Request;

class CashRegisterController extends Controller
{
    // Abrir caja
    public function open(Request $request)
    {
        $validated = $request->validate([
            'sucursal_id' => 'required|exists:branches,sucursal_id',
            'user_id' => 'required|exists:users,id',
            'opening_amount' => 'required|numeric|min:0',
        ]);

        // Crear caja sin limitar a una por sucursal
        $register = CashRegister::create([
            'sucursal_id' => $validated['sucursal_id'],
            'user_id' => $validated['user_id'],
            'opening_amount' => $validated['opening_amount'],
            'opened_at' => now(),
        ]);

        return response()->json([
            'message' => 'Caja abierta correctamente',
            'data' => $register->load(['branch', 'user'])
        ], 201);
    }

    // Cerrar caja
    public function close(Request $request, $id)
    {
        $validated = $request->validate([
            'closing_amount' => 'required|numeric|min:0',
        ]);

        $register = CashRegister::findOrFail($id);
        $register->update([
            'closing_amount' => $validated['closing_amount'],
            'closed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Caja cerrada correctamente',
            'data' => $register->load(['branch', 'user'])
        ]);
    }

    // Listar todas las cajas
    public function index()
    {
        return CashRegister::with(['branch', 'user'])
            ->orderBy('opened_at', 'desc')
            ->get();
    }

    // Mostrar una caja específica
    public function show($id)
    {
        return CashRegister::with(['branch', 'user'])->findOrFail($id);
    }
}
