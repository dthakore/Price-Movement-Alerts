<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Price Movement Alerts')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet">
    @stack('styles')

    <style>
        body { background: #f5f6fa; min-height: 100vh; font-family: 'Nunito', sans-serif; }
        nav.navbar { background: #ffffffcc; backdrop-filter: blur(12px); border-bottom: 1px solid #e5e7eb; }
        .navbar-brand { font-weight: 700; color: #2563eb !important; }
        .nav-link { padding: 10px 12px !important; border-radius: 9px; font-weight: 500; color: #52525b !important; }
        .nav-link:hover, .nav-link.active { background: #eef2ff; color: #2563eb !important; }
        main { padding: 32px 40px; }
    </style>
</head>
<body>
@php
    use App\Models\AlertLog;
    use App\Models\AlertConfigurationUser;

    $alerts = [];
    $unreadAlertCount = 0;

    if (auth()->check()) {
        $subscribedConfigIds = AlertConfigurationUser::where('user_id', auth()->id())
            ->pluck('alert_configuration_id')
            ->all();

        $alerts = AlertLog::whereIn('alert_configuration_id', $subscribedConfigIds)
            ->latest()
            ->limit(10)
            ->get();

        $unreadAlertCount = AlertLog::whereIn('alert_configuration_id', $subscribedConfigIds)
            ->where('is_read', false)
            ->count();
    }
@endphp

<nav class="navbar navbar-expand-lg sticky-top shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand" href="{{ route('alerts.config.index') }}">
            <i class="bi bi-bar-chart-line-fill me-2"></i>Price Movement Alerts
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                @auth
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('alerts.config.*') ? 'active' : '' }}"
                           href="{{ route('alerts.config.index') }}">
                            <i class="bi bi-gear me-1"></i> Alert Config
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('volume-alerts.*') ? 'active' : '' }}"
                           href="{{ route('volume-alerts.index') }}">
                            <i class="bi bi-activity me-1"></i> Volume Alerts
                        </a>
                    </li>

                    <li class="nav-item dropdown me-2">
                        <a class="nav-link position-relative" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-bell fs-5"></i>
                            @if($unreadAlertCount > 0)
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    {{ $unreadAlertCount }}
                                </span>
                            @endif
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="width: 340px; max-height: 400px; overflow-y: auto">
                            <li class="dropdown-header fw-bold text-primary">Alerts</li>
                            @forelse($alerts as $alert)
                                <li>
                                    <a href="javascript:void(0)"
                                       class="dropdown-item small alert-item {{ !$alert->is_read ? 'fw-bold bg-light' : '' }}"
                                       data-id="{{ $alert->id }}">
                                        <div class="d-flex justify-content-between">
                                            <span>
                                                {{ $alert->symbol }}
                                                <span class="badge bg-{{ $alert->performance > 0 ? 'success' : 'danger' }}">
                                                    {{ $alert->performance }}%
                                                </span>
                                            </span>
                                            <small class="text-muted">{{ $alert->created_at->diffForHumans() }}</small>
                                        </div>
                                    </a>
                                </li>
                            @empty
                                <li class="dropdown-item text-center text-muted">No alerts found</li>
                            @endforelse
                        </ul>
                    </li>

                    @if(auth()->user()->isAdmin())
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('cron-logs.*') ? 'active' : '' }}"
                               href="{{ route('cron-logs.index') }}">
                                <i class="bi bi-calendar2-check me-1"></i> Scheduler
                            </a>
                        </li>
                    @endif

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i>{{ auth()->user()->name }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="{{ route('profile.edit') }}">
                                    <i class="bi bi-pencil-square me-1"></i> Profile
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="dropdown-item text-danger">
                                        <i class="bi bi-box-arrow-right me-1"></i> Logout
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                @else
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('login') }}">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Login
                        </a>
                    </li>
                @endauth
            </ul>
        </div>
    </div>
</nav>

<main class="container-fluid">
    @yield('content')
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.alert-item').forEach(item => {
            item.addEventListener('click', function () {
                fetch('/alerts/read', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ id: this.dataset.id })
                });
                this.classList.remove('fw-bold', 'bg-light');
            });
        });
    });
</script>
@stack('scripts')
</body>
</html>
