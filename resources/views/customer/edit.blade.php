
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
    <form action="{{ route('users.update', $user->id) }}" method="POST">
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
            <label>Email</label>
            <input type="email" name="email" class="form-control" value="{{ $user->email }}" required>
        </div>
        <div class="mb-3">
            <label>Nueva Contraseña (opcional)</label>
            <input type="password" name="password" class="form-control">
        </div>
        <div class="mb-3">
            <label>Rol</label>
            <select name="role" class="form-control" required>
                @foreach($roles as $role)
                    <option value="{{ $role }}" {{ $user->role == $role ? 'selected' : '' }}>{{ ucfirst($role) }}</option>
                @endforeach
            </select>
        </div>
        <div class="mb-3">
            <label>Estado</label>
            <select name="status" class="form-control" required>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" {{ $user->status == $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="mb-3">
            <label>Foto de perfil (nombre de archivo)</label>
            <input type="text" name="profile_photo" class="form-control" value="{{ $user->profile_photo }}">
        </div>
        <button class="btn btn-success">Actualizar</button>
    </form>
</div>
@endsection