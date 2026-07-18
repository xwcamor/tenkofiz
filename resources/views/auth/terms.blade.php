<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Terms and conditions') }} | {{ __('Facial Attendance') }}</title>
    <link rel="stylesheet" href="{{ vendor_asset('vendor/fontawesome/css/all.min.css', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ vendor_asset('vendor/bootstrap4/bootstrap.min.css', 'https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css') }}">
    <link href="{{ vendor_asset('vendor/inter/inter.css', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap') }}" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', system-ui, sans-serif;
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(1200px 600px at 15% -10%, #1d3a5f 0%, transparent 55%),
                        radial-gradient(1000px 500px at 110% 110%, #14487e 0%, transparent 50%),
                        #0f1b2d;
            padding: 1.5rem 1rem;
        }
        .terms-card {
            width: 100%;
            max-width: 760px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 24px 64px rgba(0, 0, 0, .35);
            padding: 2rem;
        }
        .terms-body {
            max-height: 46vh;
            overflow-y: auto;
            border: 1px solid #e3e8ef;
            border-radius: 10px;
            padding: 1.1rem 1.25rem;
            font-size: .87rem;
            color: #333c48;
            background: #fafbfd;
        }
        .terms-body h6 { font-weight: 700; margin-top: 1rem; }
        .terms-body h6:first-child { margin-top: 0; }
    </style>
</head>
<body>
<div class="terms-card">
    <div class="text-center mb-3">
        <div style="width:52px;height:52px;border-radius:14px;background:#e8f1fc;color:#2a78d6;display:flex;align-items:center;justify-content:center;font-size:1.5rem;margin:0 auto .75rem"><i class="fas fa-file-contract"></i></div>
        <h4 class="mb-1">{{ __('Terms and conditions of use') }}</h4>
        <p class="text-muted mb-0" style="font-size:.85rem">{{ __('Version :version — you must read and accept them to use the system.', ['version' => $version]) }}</p>
    </div>

    <div class="terms-body mb-3">
        <h6>1. {{ __('Purpose of the system') }}</h6>
        <p>{{ __('This platform is an attendance-control tool (facial recognition, document marking, schedules, reports). It is provided as a technological means of record-keeping; it does not replace the labor, accounting or legal obligations of the company that uses it.') }}</p>

        <h6>2. {{ __('Responsibility for the data (data controller)') }}</h6>
        <p>{{ __('The company (workspace) that registers employees, faces, marks and documents is the CONTROLLER of that personal data and is responsible for having the legal basis to process it, informing its employees, obtaining the required consents and attending to their rights (access, rectification, deletion). The platform acts solely as a processing tool on behalf of the company.') }}</p>

        <h6>3. {{ __('Biometric data') }}</h6>
        <p>{{ __('Facial enrollment stores a mathematical vector of the face (not the photograph) and requires the employee\'s express consent, which the system records (date and time). Evidence photos taken when marking by document are kept temporarily and purged automatically. The company must use these capabilities in accordance with its local personal-data protection law (e.g. Law 29733 in Peru).') }}</p>

        <h6>4. {{ __('Proper use and credentials') }}</h6>
        <p>{{ __('Your account is personal and non-transferable: you are responsible for what is done with it and for keeping your password safe. It is forbidden to use the system to process data of people outside the company, to attempt to access other companies\' data or to circumvent security controls (tokens, device pairing, permissions).') }}</p>

        <h6>5. {{ __('Availability and limitation of liability') }}</h6>
        <p>{{ __('The system is provided "as is". The provider does not guarantee uninterrupted availability nor is it liable for indirect damages, data loss caused by misuse, force majeure, or labor/administrative decisions the company makes based on the reports. Facial recognition is a support mechanism and may produce occasional errors; disputed marks should be reviewed with the stored evidence.') }}</p>

        <h6>6. {{ __('Audit record') }}</h6>
        <p>{{ __('The system records security events (sign-ins, marks with IP and device, changes with audit trail) precisely to protect both the company and its employees in the event of disputes.') }}</p>

        <h6>7. {{ __('Acceptance') }}</h6>
        <p>{{ __('By accepting, you declare that you have read and understood these terms and that you use the system on behalf of the company that granted you access. Your acceptance is recorded with date, time, IP address and terms version. If the terms change, you will be asked to accept them again.') }}</p>
    </div>

    <form method="POST" action="{{ route('logout') }}" id="logoutForm">@csrf</form>
    <form method="POST" action="{{ route('terms.accept') }}">
        @csrf
        <div class="custom-control custom-checkbox mb-3">
            <input type="checkbox" class="custom-control-input @error('accept') is-invalid @enderror" id="acceptTerms" name="accept" value="1">
            <label class="custom-control-label" for="acceptTerms">{{ __('I have read and accept the terms and conditions of use.') }}</label>
            @error('accept')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="d-flex justify-content-between align-items-center">
            <button type="submit" form="logoutForm" class="btn btn-outline-secondary">{{ __('Log out') }}</button>
            <button class="btn btn-primary px-4"><i class="fas fa-check"></i> {{ __('Accept and continue') }}</button>
        </div>
    </form>
</div>
</body>
</html>
