
@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Editar Usuario</h1>
    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <form action="{{ route('users.update', $user->user_id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label>Nombre</label>
            <input type="text" name="name" class="form-control" value="{{ $user->name }}" required>
        </div>
        <div class="mb-3">
            <label>Teléfono</label>
            <input type="text" name="phone" class="form-control" value="{{ $user->phone }}">
        </div>
        <div class="mb-3">
            <label>Correo</label>
            <input type="email" name="email" class="form-control" value="{{ $user->email }}" required>
        </div>
        <div class="mb-3">
            <label>Nueva Contraseña (opcional)</label>
            <input type="password" name="password" class="form-control">
        </div>
        <div class="mb-3">
            <label>Rol</label>
            <select name="role_id" class="form-control" required>
                @foreach($roles as $role)
                    <option value="{{ $role->role_id }}" {{ $user->role_id == $role->role_id ? 'selected' : '' }}>{{ ucfirst($role->role_name) }}</option>
                @endforeach
            </select>
        </div>
        <div class="mb-3">
            <label>Estado</label>
            <select name="active" class="form-control" required>
                <option value="1" {{ $user->active ? 'selected' : '' }}>Activo</option>
                <option value="0" {{ !$user->active ? 'selected' : '' }}>Inactivo</option>
            </select>
        </div>
        <button class="btn btn-success">Actualizar</button>
    </form>
</div>
@endsection