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

    // Verificar si el usuario ya tiene una caja abierta con saldo disponible
    $activeRegister = CashRegister::where('user_id', $validated['user_id'])
        ->whereNull('closed_at')            // aún no cerrada
        ->where('available_amount', '>', 0) // descarta cajas vacías
        ->first();

    if ($activeRegister) {
        return response()->json([
            'message' => 'El usuario ya tiene una caja abierta,no puede abrir otra.',
            'data' => $activeRegister->load(['branch', 'user'])
        ], 400); // Bad Request
    }

    // Crear la nueva caja
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

public function createEmpty(Request $request)
{
    $validated = $request->validate([
        'sucursal_id' => 'required|exists:branches,sucursal_id',
        'user_id' => 'required|exists:users,id',
    ]);

    $register = CashRegister::create([
        'sucursal_id' => $validated['sucursal_id'],
        'user_id' => $validated['user_id'],
        'opening_amount' => 0,
        'available_amount' => 0,
        'closing_amount' => 0,
        'opened_at' => now(), // opcional, si quieres marcar que se abrió "vacía"
        'closed_at' => null,
    ]);

    return response()->json([
        'message' => 'Caja creada correctamente, pendiente de apertura.',
        'data' => $register->load('branch')
    ], 201);
}




    // Cerrar caja
// Cerrar caja
public function close(Request $request, $id)
{
    $validated = $request->validate([
        'user_id' => 'required|exists:users,id',
    ]);

    $register = CashRegister::findOrFail($id);

    //  Verificar si la caja está abierta
    if ($register->closed_at) {
        return response()->json([
            'message' => 'La caja ya fue cerrada anteriormente.'
        ], 400);
    }

    //  Validar que el usuario que intenta cerrar sea el mismo que la abrió
    if ($register->user_id !== (int) $validated['user_id']) {
        return response()->json([
            'message' => 'No tienes permiso para cerrar esta caja. Solo el usuario que la abrió puede hacerlo.'
        ], 403);
    }

    //  Al cerrar, el monto final es lo que quedó disponible
    $register->update([
        'closing_amount' => $register->available_amount,
        'closed_at' => now(),
    ]);

    return response()->json([
        'message' => 'Caja cerrada correctamente.',
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

public function activeUserBox($sucursalId, $userId)
{
    $caja = CashRegister::where('sucursal_id', $sucursalId)
        ->where('user_id', $userId)
        ->whereNull('closed_at')
        ->where(function ($query) {
            $query->where('opening_amount', '>', 0)
                  ->orWhere('available_amount', '>', 0)
                  ->orWhere('closing_amount', '>', 0);
        })
        ->latest('opened_at')
        ->first(); // devuelve la caja activa con datos

    return response()->json([
        'caja' => $caja // null si no tiene caja activa con datos
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
public function reopen(Request $request, $id)
{
    $validated = $request->validate([
        'user_id' => 'required|exists:users,id',
    ]);

    $caja = CashRegister::findOrFail($id);

    // Verificar que la caja esté cerrada
    if (is_null($caja->closed_at)) {
        return response()->json([
            'message' => 'No se puede reabrir una caja que ya está abierta.'
        ], 400);
    }

    // Verificar si el usuario ya tiene una caja activa en esta sucursal
    $activeCaja = CashRegister::where('user_id', $validated['user_id'])
        ->where('sucursal_id', $caja->sucursal_id)
        ->whereNull('closed_at')
        ->first();

    if ($activeCaja) {
        return response()->json([
            'message' => 'El usuario ya tiene una caja activa en esta sucursal.'
        ], 400);
    }

    // Reabrir caja: solo cambia el usuario y la marca como abierta de nuevo
    $caja->update([
        'user_id' => $validated['user_id'],
        'opened_at' => now(),
        'closed_at' => null,
        'closing_amount' => null, // opcional, se limpia el cierre previo
    ]);

    return response()->json([
        'message' => 'Caja reabierta correctamente con el nuevo usuario.',
        'data' => $caja->load(['branch', 'user'])
    ]);
}


}
