<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    // Listar todos los clientes
    public function index()
    {
        $customers = Customer::all();
        return response()->json($customers);
    }

    // Crear un cliente nuevo
    public function store(Request $request)
    {
        $request->validate([
            'name'            => 'required|string|max:255',
            'identity_number' => 'required|string|unique:customers,identity_number',
            'phone'           => 'nullable|string|max:20',
            'email'           => 'required|email|unique:customers,email',
        ]);

        $customer = Customer::create($request->only(['name','identity_number','phone','email']));

        return response()->json($customer, 201);
    }

    // Mostrar un cliente por ID
    public function show($id)
    {
        $customer = Customer::find($id);
        if (!$customer) {
            return response()->json(['message' => 'Cliente no encontrado'], 404);
        }
        return response()->json($customer);
    }

    // Editar cliente
    public function update(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);

        $request->validate([
            'name'            => 'required|string|max:255',
            'identity_number' => 'required|string|unique:customers,identity_number,' . $id . ',customer_id',
            'phone'           => 'nullable|string|max:20',
            'email'           => 'required|email|unique:customers,email,' . $id . ',customer_id',
        ]);

        $customer->update($request->only(['name','identity_number','phone','email']));

        return response()->json($customer);
    }

    // Eliminar cliente
    public function destroy($id)
    {
        $customer = Customer::findOrFail($id);
        $customer->delete();

        return response()->json(['message' => 'Cliente eliminado correctamente']);
    }

    // Recuperar "identification/email" temporal opcional
    public function recoverIdentity(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $customer = Customer::where('email', $request->email)->first();

        if (!$customer) {
            return response()->json(['message' => 'Cliente no encontrado'], 404);
        }

        // Generar número de identidad temporal (solo ejemplo)
        $temporaryIdentity = Str::random(8);
        $customer->identity_number = $temporaryIdentity;
        $customer->save();

        return response()->json([
            'customer_id'       => $customer->customer_id,
            'name'              => $customer->name,
            'email'             => $customer->email,
            'temporaryIdentity' => $temporaryIdentity,
        ]);
    }
}
