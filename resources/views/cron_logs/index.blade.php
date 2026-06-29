@extends('layouts.app')

@section('title', 'Scheduler Monitor')

@push('styles')
    <style>
        table td, table th { font-size: 13px; white-space: nowrap; }
        .cron-label { font-size: 13px; font-weight: 600; }
        .cron-sub   { font-size: 11px; color: #6c757d; font-family: monospace; }
        .filter-bar .form-select, .filter-bar .form-control { font-size: 13px; }
    </style>
@endpush
@php
$cronMeta = [
    'alerts:check'       => ['label' => 'Price Alerts Check', 'exchange' => null, 'icon' => 'bi-bell',           'color' => 'text-danger'],
    'funnel_alert:check' => ['label' => 'Funnel Alert Check', 'exchange' => null, 'icon' => 'bi-funnel',         'color' => 'text-purple'],
    'volume:alerts'      => ['label' => 'Volume Spike Check', 'exchange' => null, 'icon' => 'bi-activity',       'color' => 'text-warning'],
    'exchange:sync'      => ['label' => 'Exchange Sync',      'exchange' => null, 'icon' => 'bi-cloud-download', 'color' => 'text-secondary'],
];

function cronLabel(array $meta, string $key): string {
    $m = $meta[$key] ?? null;
    if (!$m) return $key;
    $exchange = $m['exchange'] ? " <span class='badge bg-light text-dark border ms-1' style='font-size:10px'>{$m['exchange']}</span>" : '';
    return "<span class='cron-label {$m['color']}'><i class='bi {$m['icon']} me-1'></i>{$m['label']}{$exchange}</span>
            <div class='cron-sub'>{$key}</div>";
}
@endphp

@section('content')
<div class="container-fluid mt-4">
    <div class="card p-3">

        {{-- Header --}}
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h4 class="mb-0"><i class="bi bi-calendar2-check me-2 text-primary"></i>Scheduler Monitor</h4>
                <small class="text-muted">Cron job execution history &amp; performance</small>
            </div>
            <form action="{{ route('cron-logs.clear') }}" method="POST" class="d-inline"
                  data-confirm="Clear all cron logs?">
                @csrf @method('DELETE')
                <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Clear All</button>
            </form>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- Filter Bar --}}
        <form method="GET" action="{{ route('cron-logs.index') }}" class="filter-bar row g-2 mb-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold mb-1" style="font-size:12px">Cron Job</label>
                <select name="job" class="form-select form-select-sm">
                    <option value="">All Jobs</option>
                    @foreach($cronMeta as $key => $m)
                        <option value="{{ $key }}" @selected(request('job') === $key)>{{ $m['label'] }}{{ $m['exchange'] ? ' ('.$m['exchange'].')' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold mb-1" style="font-size:12px">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                    <option value="failed"    @selected(request('status') === 'failed')>Failed</option>
                    <option value="running"   @selected(request('status') === 'running')>Running</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold mb-1" style="font-size:12px">From Date</label>
                <input type="date" name="from_date" value="{{ request('from_date') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold mb-1" style="font-size:12px">To Date</label>
                <input type="date" name="to_date" value="{{ request('to_date') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-search me-1"></i>Filter
                </button>
            </div>
            <div class="col-md-1">
                <a href="{{ route('cron-logs.index') }}" class="btn btn-outline-secondary btn-sm w-100">
                    <i class="bi bi-x-circle me-1"></i>Reset
                </a>
            </div>
            <div class="col-md-1 text-end pt-3">
                <span class="badge bg-primary fs-6">{{ $logs->total() }}</span>
                <small class="text-muted d-block" style="font-size:11px">total rows</small>
            </div>
        </form>

        {{-- Table --}}
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle w-100">
                <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Cron Job</th>
                    <th>Status</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Duration</th>
                    <th>Alerts</th>
                    <th>Error</th>
                </tr>
                </thead>
                <tbody>
                @forelse($logs as $log)
                    @php
                        $statusClass = match($log->status) {
                            'completed' => 'bg-success',
                            'failed'    => 'bg-danger',
                            default     => 'bg-primary',
                        };
                    @endphp
                    <tr>
                        <td>{{ $log->id }}</td>
                        <td>{!! cronLabel($cronMeta, $log->cron_job) !!}</td>
                        <td><span class="badge {{ $statusClass }}">{{ ucfirst($log->status) }}</span></td>
                        <td>{{ $log->start_time?->format('d M Y, H:i:s') ?? '-' }}</td>
                        <td>{{ $log->end_time?->format('d M Y, H:i:s') ?? '-' }}</td>
                        <td>
                            @if($log->duration)
                                <span class="badge bg-secondary">{{ $log->duration }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if(!is_null($log->alerts_processed))
                                <span class="badge bg-info text-dark">{{ $log->alerts_processed }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($log->error)
                                <span class="text-danger small" title="{{ $log->error }}">
                                    {{ Str::limit($log->error, 60) }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No records found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
            <small class="text-muted">
                Showing {{ $logs->firstItem() ?? 0 }}–{{ $logs->lastItem() ?? 0 }} of {{ $logs->total() }} records
            </small>
            {{ $logs->links() }}
        </div>

    </div>
</div>
@endsection
