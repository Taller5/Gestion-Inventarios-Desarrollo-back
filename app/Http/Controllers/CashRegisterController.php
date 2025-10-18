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

        $register = CashRegister::create([
            'sucursal_id' => $validated['sucursal_id'],
            'user_id' => $validated['user_id'],
            'opening_amount' => $validated['opening_amount'],
            'available_amount' => $validated['opening_amount'], // monto inicial disponible
            'opened_at' => now(),
        ]);

        return response()->json([
            'message' => 'Caja abierta correctamente',
            'data' => $register->load(['branch', 'user'])
        ], 201);
    }

    // Cerrar caja
    public function close($id)
    {
        $register = CashRegister::findOrFail($id);

        // Al cerrar, el monto final es lo que quedó disponible
        $register->update([
            'closing_amount' => $register->available_amount,
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
public function active($sucursalId)
{
    $cajas = CashRegister::where('sucursal_id', $sucursalId)
        ->whereNull('closed_at')
        ->latest('opened_at')
        ->get();

    return response()->json([
        'cajas' => $cajas
    ]);
}

// Actualizar caja con venta en efectivo
public function addCashSale(Request $request)
{
    $validated = $request->validate([
        'id' => 'required|exists:cash_registers,id', // <- se pide id de la caja
        'amount_received' => 'required|numeric|min:0',
        'change_given' => 'required|numeric|min:0',
    ]);

    $caja = CashRegister::find($validated['id']);

    // Verificar que esté abierta
    if (!$caja || $caja->closed_at) {
        return response()->json([
            'message' => 'La caja seleccionada no está activa.'
        ], 400);
    }

    // Calcular ingreso neto
    $netIncome = $validated['amount_received'] - $validated['change_given'];
    $caja->available_amount = ($caja->available_amount ?? 0) + $netIncome;
    $caja->save();

    return response()->json([
        'message' => 'Caja actualizada correctamente',
        'data' => $caja->load(['branch', 'user'])
    ]);
}

}
