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
        return response()->json($users);
        //return view('users.index', compact('users'));
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

       $user = User::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'status' => $request->status,
            'profile_photo' => $request->profile_photo,
        ]);

          return response()->json($user, 201);
       // return redirect()->route('users.index')->with('success', 'Usuario creado correctamente.');
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
        return response()->json($user);
       // return redirect()->route('users.index')->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
         return response()->json(['message' => 'Usuario eliminado correctamente']);
        //return redirect()->route('users.index')->with('success', 'Usuario eliminado correctamente.');
    }

    public function show($id)
{
    $user = User::with('role')->find($id);
    if (!$user) {
        return response()->json(['message' => 'Usuario no encontrado'], 404);
    }
    return response()->json($user);
}

public function updatePassword(Request $request, $id)
{
    $user = User::findOrFail($id);

    $request->validate([
        'current_password' => 'required',
        'new_password' => 'required|min:8|confirmed',
    ]);

    if (!Hash::check($request->current_password, $user->password)) {
        return response()->json(['message' => 'La contraseña actual es incorrecta'], 422);
    }

    $user->password = Hash::make($request->new_password);
    $user->save();

    return response()->json(['message' => 'Contraseña actualizada correctamente']);
}

public function updateProfilePhoto(Request $request, $id)
{
    $user = User::findOrFail($id);

    $request->validate([
        'profile_photo' => 'required|image|mimes:jpg,jpeg,png|max:2048',
    ]);

    if ($request->hasFile('profile_photo')) {
        $file = $request->file('profile_photo');
        $filename = uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move(public_path('profile_photos'), $filename);
        $user->profile_photo = 'profile_photos/' . $filename;
        $user->save();
    }

    return response()->json(['message' => 'Foto de perfil actualizada', 'profile_photo' => $user->profile_photo]);
}
}


