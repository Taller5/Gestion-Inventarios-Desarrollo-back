<?php

namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
class UserController extends Controller
{
    public function index()
    {
        $users = User::all();
        return view('users.index', compact('users'));
    }

    public function create()
    {
        $roles = ['administrador', 'supervisor', 'bodeguero', 'vendedor'];
        $statuses = ['activo', 'inactivo'];
        return view('users.create', compact('roles', 'statuses'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'phone' => 'nullable',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'role' => 'required|in:administrador,supervisor,bodeguero,vendedor',
            'status' => 'required|in:activo,inactivo',
            'profile_photo' => 'nullable|string',
        ]);

        User::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'status' => $request->status,
            'profile_photo' => $request->profile_photo,
        ]);

        return redirect()->route('users.index')->with('success', 'Usuario creado correctamente.');
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        $roles = ['administrador', 'supervisor', 'bodeguero', 'vendedor'];
        $statuses = ['activo', 'inactivo'];
        return view('users.edit', compact('user', 'roles', 'statuses'));
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'required',
            'phone' => 'nullable',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => 'required|in:administrador,supervisor,bodeguero,vendedor',
            'status' => 'required|in:activo,inactivo',
            'profile_photo' => 'nullable|string',
        ]);

        $user->name = $request->name;
        $user->phone = $request->phone;
        $user->email = $request->email;
        $user->role = $request->role;
        $user->status = $request->status;
        $user->profile_photo = $request->profile_photo;

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return redirect()->route('users.index')->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return redirect()->route('users.index')->with('success', 'Usuario eliminado correctamente.');
    }
}
