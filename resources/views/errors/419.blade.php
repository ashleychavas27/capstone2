<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Session expired · {{ config('app.name', 'Dental Clinic') }}</title>
    <link rel="icon" href="{{ asset('logo.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: #FAF7F5;
            color: #736261;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .panel {
            background: #fff;
            border: 1px solid #B4B1B2;
            border-radius: .9rem;
            max-width: 460px;
            width: 100%;
            padding: 2rem 1.75rem;
            text-align: center;
        }
        .icon-badge {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: #FAF7F5;
            border: 1px solid #B4B1B2;
            color: #C89B27;
            font-size: 1.6rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }
        h1 {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 1.4rem;
            color: #736261;
            margin-bottom: .5rem;
        }
        p { font-size: .9rem; margin-bottom: 1.5rem; }
        .btn-primary {
            --bs-btn-bg: #C89B27;
            --bs-btn-border-color: #C89B27;
            --bs-btn-hover-bg: #AE8520;
            --bs-btn-hover-border-color: #AE8520;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="panel">
        <div class="icon-badge"><i class="bi bi-clock-history"></i></div>
        <h1>Your session has expired</h1>
        <p class="text-muted">
            For your security, this page stopped accepting changes after a period of inactivity.
            Please sign in again to continue.
        </p>
        <a href="{{ route('login') }}" class="btn btn-primary w-100">
            <i class="bi bi-box-arrow-in-right me-1"></i>Go to Login
        </a>
    </div>
</body>
</html>
