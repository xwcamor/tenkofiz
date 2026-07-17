@extends('layouts.app')
@section('titulo', 'Enrolamiento Facial')
@section('contenido')
<div class="row">
    <div class="col-md-7">
        <div class="card card-success card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-camera"></i> {{ $empleado->nombre_completo }} — DNI {{ $empleado->dni }}</h3>
            </div>
            <div class="card-body text-center">
                <div class="position-relative mx-auto" style="max-width:480px">
                    <video id="video" autoplay muted playsinline class="rounded border w-100"></video>
                    <canvas id="overlay" class="position-absolute w-100 h-100" style="top:0;left:0"></canvas>
                </div>
                <div id="estado" class="alert alert-info mt-3">Cargando modelos de reconocimiento facial...</div>
                <button id="btnCapturar" class="btn btn-success btn-lg" disabled>
                    <i class="fas fa-id-badge"></i> Capturar y enrolar rostro
                </button>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-info-circle"></i> Indicaciones</h3></div>
            <div class="card-body">
                <ul class="mb-0">
                    <li>Mire de frente a la cámara, con buena iluminación.</li>
                    <li>Retire lentes oscuros, gorra o mascarilla.</li>
                    <li>Debe detectarse <strong>un solo rostro</strong>.</li>
                    <li>Se guarda un vector matemático de 128 valores, <strong>no la fotografía</strong>.</li>
                </ul>
            </div>
        </div>
        @if($empleado->tieneRostro())
            <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Ya tiene un rostro enrolado; capturar de nuevo lo reemplazará.</div>
        @endif
    </div>
</div>
@endsection
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.13/dist/face-api.min.js"></script>
<script>
    window.ENROLAR_URL = "{{ route('empleados.descriptor', $empleado) }}";
    window.CSRF = "{{ csrf_token() }}";
    window.INDEX_URL = "{{ route('empleados.index') }}";
</script>
<script src="{{ asset('js/enrolar.js') }}?v={{ @filemtime(public_path('js/enrolar.js')) ?: 1 }}"></script>
@endpush
