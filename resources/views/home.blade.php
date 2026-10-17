@extends('adminlte::page')

@section('title', 'Dashboard')

@section('content_header')
    <h1>Dashboard</h1>
@stop

@section('content')
    <p>Bienvenido al panel de administración del sistema de certificados.</p>

    @php
        $roleName = auth()->user()->role->name;
    @endphp

    <div class="row">

        {{-- Tarjeta de Resoluciones (Root y Admin) --}}
        @if(in_array($roleName, ['Root', 'Administrador']))
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>...</h3>
                        <p>Resoluciones</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <a href="{{ route('resolutions.index') }}" class="small-box-footer">
                        Gestionar <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
        @endif

        {{-- Tarjeta de Cursos (Todos los roles del sistema) --}}
        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>...</h3>
                    <p>Cursos</p>
                </div>
                <div class="icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <a href="{{ route('courses.index') }}" class="small-box-footer">
                    Gestionar <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>

        {{-- Tarjeta de Usuarios Finales (Solo Root) --}}
        @if($roleName === 'Root')
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3>...</h3>
                        <p>Usuarios Finales</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <a href="{{ route('users.index') }}" class="small-box-footer">
                        Gestionar <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
        @endif

        {{-- Tarjeta de Certificados (Todos los roles del sistema) --}}
        <div class="col-lg-3 col-6">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h3>...</h3>
                    <p>Certificados</p>
                </div>
                <div class="icon">
                    <i class="fas fa-certificate"></i>
                </div>
                <a href="{{ route('certificates.index') }}" class="small-box-footer">
                    Gestionar <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>
    </div>
@stop