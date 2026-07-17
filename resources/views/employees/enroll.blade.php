@extends('layouts.app')
@section('title', __('Face Enrollment'))
@section('content')
<div class="row">
    <div class="col-md-7">
        <div class="card card-success card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-camera"></i> {{ $employee->full_name }} — {{ __('Document') }} {{ $employee->document_number }}</h3>
            </div>
            <div class="card-body text-center">
                <div class="position-relative mx-auto" style="max-width:480px">
                    <video id="video" autoplay muted playsinline class="rounded border w-100"></video>
                    <canvas id="overlay" class="position-absolute w-100 h-100" style="top:0;left:0"></canvas>
                </div>
                <div id="status" class="alert alert-info mt-3">{{ __('Loading facial recognition models...') }}</div>

                {{-- Data protection: consent is mandatory before enrolling biometric data --}}
                <div class="custom-control custom-checkbox text-left border rounded p-3 pl-5 mb-3 {{ $employee->hasBiometricConsent() ? 'bg-light' : '' }}">
                    <input type="checkbox" class="custom-control-input" id="consentCheck" @checked($employee->hasBiometricConsent()) @disabled($employee->hasBiometricConsent())>
                    <label class="custom-control-label" for="consentCheck">
                        {{ __('The employee declares that they have been informed and consent to the processing of their biometric data (a 128-value mathematical vector of the face, not the photograph) for the sole purpose of attendance control, in accordance with the personal data protection law.') }}
                    </label>
                    @if($employee->hasBiometricConsent())
                        <small class="text-success d-block mt-1"><i class="fas fa-check-circle"></i> {{ __('Consent accepted on :date.', ['date' => to_user_tz($employee->biometric_consent_at)->format('d/m/Y H:i')]) }}</small>
                    @endif
                </div>

                <button id="captureBtn" class="btn btn-success btn-lg" disabled>
                    <i class="fas fa-id-badge"></i> {{ __('Capture and enroll face') }}
                </button>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-info-circle"></i> {{ __('Instructions') }}</h3></div>
            <div class="card-body">
                <ul class="mb-0">
                    <li>{{ __('Look straight at the camera, with good lighting.') }}</li>
                    <li>{{ __('Remove sunglasses, cap or mask.') }}</li>
                    <li>{!! __('Exactly <strong>one face</strong> must be detected.') !!}</li>
                    <li>{!! __('A 128-value mathematical vector is stored, <strong>not the photograph</strong>.') !!}</li>
                </ul>
            </div>
        </div>
        @if($employee->hasFace())
            <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> {{ __('A face is already enrolled; capturing again will replace it.') }}</div>
        @endif
    </div>
</div>
@endsection
@push('scripts')
<script src="{{ vendor_asset('vendor/faceapi/face-api.min.js', 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.13/dist/face-api.min.js') }}"></script>
<script>
    window.ENROLL_URL = "{{ route('employees.descriptor', $employee) }}";
    window.CSRF = "{{ csrf_token() }}";
    window.INDEX_URL = "{{ route('employees.index') }}";
    window.ENROLL_I18N = {
        loadingModels: @json(__('Loading facial recognition models...')),
        startingCamera: @json(__('Models loaded. Starting camera...')),
        cameraReady: @json(__('Camera ready. Look straight ahead and press the button: :count captures will be taken.')),
        cameraError: @json(__('Error: :message. Check the camera and that the models are in /public/models.')),
        capturingSample: @json(__('Capturing sample :current of :total... move your head slightly between captures.')),
        noFaceInSample: @json(__('No face was detected in sample :current. Move closer, improve the lighting and try again.')),
        saving: @json(__('Saving to the database...')),
        rejected: @json(__('The server rejected the enrollment:')),
        redirecting: @json(__('Redirecting...')),
        connectionError: @json(__('Communication error:')),
        consentRequired: @json(__('You must accept the biometric data consent before enrolling.')),
    };
</script>
<script src="{{ asset('js/enroll.js') }}?v={{ @filemtime(public_path('js/enroll.js')) ?: 1 }}"></script>
@endpush
