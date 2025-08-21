
@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Crear Usuario</h1>
    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <form action="{{ route('users.store') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label>Nombre</label>
            <input type="text" name="name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Teléfono</label>
            <input type="text" name="phone" class="form-control">
        </div>
        <div class="mb-3">
            <label>Correo</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Contraseña</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Rol</label>
            <select name="role_id" class="form-control" required>
                <option value="">Seleccione un rol</option>
                @foreach($roles as $role)
                    <option value="{{ $role->role_id }}">{{ ucfirst($role->role_name) }}</option>
                @endforeach
            </select>
        </div>
        <div class="mb-3">
            <label>Estado</label>
            <select name="active" class="form-control" required>
                <option value="1">Activo</option>
                <option value="0">Inactivo</option>
            </select>
        </div>
        <button class="btn btn-success">Guardar</button>
    </form>
</div>
@endsection