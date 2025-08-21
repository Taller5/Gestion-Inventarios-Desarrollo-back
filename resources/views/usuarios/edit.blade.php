

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
    <form action="{{ route('usuarios.update', $usuario->id_usuario) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label>Nombre</label>
            <input type="text" name="nombre" class="form-control" value="{{ $usuario->nombre }}" required>
        </div>
        <div class="mb-3">
            <label>Teléfono</label>
            <input type="text" name="telefono" class="form-control" value="{{ $usuario->telefono }}">
        </div>
        <div class="mb-3">
            <label>Correo</label>
            <input type="email" name="correo" class="form-control" value="{{ $usuario->correo }}" required>
        </div>
        <div class="mb-3">
            <label>Nueva Contraseña (opcional)</label>
            <input type="password" name="contrasena" class="form-control">
        </div>
        <div class="mb-3">
            <label>Rol</label>
            <select name="id_rol" class="form-control" required>
                @foreach($roles as $rol)
                    <option value="{{ $rol->id_rol }}" {{ $usuario->id_rol == $rol->id_rol ? 'selected' : '' }}>{{ ucfirst($rol->nombre_rol) }}</option>
                @endforeach
            </select>
        </div>
        <div class="mb-3">
            <label>Estado</label>
            <select name="activo" class="form-control" required>
                <option value="1" {{ $usuario->activo ? 'selected' : '' }}>Activo</option>
                <option value="0" {{ !$usuario->activo ? 'selected' : '' }}>Inactivo</option>
            </select>
        </div>
        <button class="btn btn-success">Actualizar</button>
    </form>
</div>
@endsection