
@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Usuarios</h1>
    <a href="{{ route('users.create') }}" class="btn btn-primary mb-2">Crear Usuario</a>
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <table class="table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Teléfono</th>
                <th>Correo</th>
                <th>Rol</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $user)
            <tr>
                <td>{{ $user->name }}</td>
                <td>{{ $user->phone }}</td>
                <td>{{ $user->email }}</td>
                <td>{{ $user->role->role_name ?? '' }}</td>
                <td>{{ $user->active ? 'Activo' : 'Inactivo' }}</td>
                <td>
                    <a href="{{ route('users.edit', $user->user_id) }}" class="btn btn-warning btn-sm">Editar</a>
                    <form action="{{ route('users.destroy', $user->user_id) }}" method="POST" style="display:inline;">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar usuario?')">Eliminar</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection