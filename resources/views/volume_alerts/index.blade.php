@extends('layouts.app')

@section('title', 'Volume Spike Watchlist')

@push('styles')
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <style>
        table.dataTable thead th {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6c757d;
            background: #f8f9fa;
            white-space: nowrap;
        }
        table.dataTable tbody tr { font-size: 13px; }
        table.dataTable tbody td { vertical-align: middle; white-space: nowrap; padding: 6px 10px !important; }
        .table-hover tbody tr:hover { background-color: #f5f7ff; }

        /* group header row */
        thead tr.group-row th {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            padding: 4px 10px !important;
            border-bottom: none;
            color: #adb5bd;
        }
        thead tr.group-row th.grp-trigger  { color: #f0ad4e; background: #fffbf2; }
        thead tr.group-row th.grp-baseline { color: #0d6efd; background: #f0f5ff; }
        thead tr.group-row th.grp-signal   { color: #198754; background: #f0fff4; }
        thead tr.group-row th.grp-candles  { color: #6610f2; background: #f8f0ff; }

        /* ratio badge */
        .ratio-badge { font-size: 10.5px; font-weight: 700; padding: 2px 7px; border-radius: 4px; }

        /* buy % bar */
        .pct-bar { height: 6px; border-radius: 3px; }

        /* mini candle cell */
        .candle-cell { text-align: center !important; }

        /* last scan text */
        .last-scan-text { font-size: 12px; color: #6c757d; }

        .badge-live {
            display: inline-flex; align-items: center; gap: 5px;
            background: #d1f5e0; color: #0f5132;
            border: 1px solid #a3e6c1;
            font-size: 11px; font-weight: 700;
            padding: 3px 10px; border-radius: 20px;
        }
        .badge-live::before {
            content: '';
            display: inline-block;
            width: 7px; height: 7px;
            background: #198754;
            border-radius: 50%;
            animation: livepulse 1.5s infinite;
        }
        @keyframes livepulse {
            0%,100% { opacity:1; }
            50%      { opacity:0.3; }
        }

        .sym-base  { font-weight: 700; color: #212529; }
        .sym-quote { font-weight: 400; color: #6c757d; }
        .sym-sub   { font-size: 10.5px; color: #adb5bd; display: block; }
    </style>
@endpush

@section('content')
@php
    /* ── Helpers ──────────────────────────────────────────────── */
    function fmtVol(float $n): string {
        if ($n >= 1_000_000) return number_format($n / 1_000_000, 2) . 'M';
        if ($n >= 1_000)     return number_format($n / 1_000, 1) . 'K';
        return number_format($n, 2);
    }

    function fmtPrice(float $p): string {
        if ($p <= 0)       return '$0';
        if ($p < 0.00001)  return '$' . rtrim(rtrim(number_format($p, 10), '0'), '.');
        if ($p < 0.001)    return '$' . rtrim(rtrim(number_format($p, 8),  '0'), '.');
        if ($p < 1)        return '$' . rtrim(rtrim(number_format($p, 6),  '0'), '.');
        if ($p < 1000)     return '$' . number_format($p, 3);
        return '$' . number_format($p, 2);
    }

    function ratioBadgeClass(float $r): string {
        if ($r >= 12) return 'bg-danger text-white';
        if ($r >= 8)  return 'bg-warning text-dark';
        if ($r >= 6)  return 'bg-warning bg-opacity-50 text-dark';
        return 'bg-secondary bg-opacity-25 text-secondary';
    }

    function ratioTextClass(float $r): string {
        if ($r >= 12) return 'text-danger fw-bold';
        if ($r >= 8)  return 'text-warning fw-bold';
        if ($r >= 6)  return 'fw-bold';
        return 'text-secondary fw-bold';
    }

    /* ── Mini candlestick SVG ─────────────────────────────────── */
    function miniCandleSvg(array $c, float $rangeMin, float $rangeMax, bool $isHero = false): string {
        $w = $isHero ? 14 : 12;
        $h = 28;
        $range = $rangeMax - $rangeMin;
        if ($range <= 0) $range = 1;

        $isGreen = $c['close'] >= $c['open'];
        $color   = $isGreen ? '#198754' : '#dc3545';
        $pct     = fn(float $v) => max(0.0, min(1.0, ($v - $rangeMin) / $range));

        $highY  = round((1 - $pct($c['high']))  * $h, 1);
        $lowY   = round((1 - $pct($c['low']))   * $h, 1);
        $openY  = round((1 - $pct($c['open']))  * $h, 1);
        $closeY = round((1 - $pct($c['close'])) * $h, 1);

        $bodyTop = min($openY, $closeY);
        $bodyH   = max(abs($closeY - $openY), 1.5);
        $midX    = $w / 2;
        $bx      = round($w * 0.15, 1);
        $bw      = round($w * 0.7,  1);

        return '<svg width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '" style="display:block">'
            . '<line x1="' . $midX . '" y1="' . $highY . '" x2="' . $midX . '" y2="' . $lowY . '" stroke="' . $color . '" stroke-width="1"/>'
            . '<rect x="' . $bx . '" y="' . $bodyTop . '" width="' . $bw . '" height="' . $bodyH . '" fill="' . $color . '"/>'
            . '</svg>';
    }

    $lastScanText = $lastScan
        ? \Carbon\Carbon::parse($lastScan)->diffForHumans(null, true) . ' ago'
        : 'never';
@endphp

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card shadow-sm mb-3">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <span class="fw-bold">
                <i class="bi bi-lightning-charge-fill text-warning me-1"></i>
                Volume Spike Watchlist
            </span>
            <small class="text-muted ms-2">Scanner &middot; 15m candles &middot; Every 15 min</small>
        </div>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <span class="badge-live">LIVE</span>
            <span class="last-scan-text">
                <i class="bi bi-clock me-1"></i>Last scan: {{ $lastScanText }}
            </span>
            <form method="POST" action="{{ route('volume-alerts.clear') }}"
                  data-confirm="Clear all volume alerts and funnels?">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-trash3 me-1"></i> Clear All
                </button>
            </form>
        </div>
    </div>

    <div class="card-body p-3">
        <table id="volumeTable" class="table table-hover table-bordered align-middle mb-0 w-100">
            <thead>
                {{-- Group header row: 2 + 3 + 1 + 3 + 1 + 2 = 12 columns --}}
                <tr class="group-row">
                    <th colspan="2"></th>
                    <th colspan="3" class="grp-trigger text-center">
                        <i class="bi bi-triangle-fill me-1" style="font-size:8px;"></i> Trigger Candle
                    </th>
                    <th colspan="1" class="grp-baseline text-center">&bull; Baseline</th>
                    <th colspan="3" class="grp-signal text-center">
                        <i class="bi bi-triangle-fill me-1" style="font-size:8px;"></i> Signal
                    </th>
{{--                    <th colspan="5" class="grp-candles text-center">--}}
{{--                        <i class="bi bi-bar-chart-steps me-1"></i> Last 5 Candles (Oldest &rarr; Trigger)--}}
{{--                    </th>--}}
{{--                    <th colspan="2"></th>--}}
                    <th colspan="5"></th>
                </tr>
                {{-- Column header row --}}
                <tr>
                    <th>#</th>
                    <th>Symbol</th>
                    <th>Price</th>
                    <th>Buy Vol</th>
                    <th>Total Vol</th>
                    <th>Avg Buy Vol</th>
                    <th>Ratio</th>
                    <th>Buy %</th>
                    <th>Trades</th>
{{--                    <th class="candle-cell">C-4</th>--}}
{{--                    <th class="candle-cell">C-3</th>--}}
{{--                    <th class="candle-cell">C-2</th>--}}
{{--                    <th class="candle-cell">C-1</th>--}}
{{--                    <th class="candle-cell">C*</th>--}}
{{--                    <th>Candle</th>--}}
                    <th>Candle Open</th>
                    <th>Z_score_10h</th>
                    <th>Z_score_192h</th>
                    <th>Range %</th>
                    <th>Triggered</th>
                </tr>
            </thead>
            <tbody>
                @foreach($alerts as $alert)
                    @php
                        $candles  = $alert->last_5_candles ?? [];
                        $allHighs = array_column($candles, 'high');
                        $allLows  = array_column($candles, 'low');
                        $rangeMax = !empty($allHighs) ? max($allHighs) : ($alert->high_price ?: 1);
                        $rangeMin = !empty($allLows)  ? min($allLows)  : ($alert->low_price  ?: 0);
                        $isGreen  = $alert->close_price >= $alert->open_price;
                    @endphp
                    <tr>
                        <td class="text-muted small">{{ $loop->iteration }}</td>
                        <td>
                            <span class="sym-base">{{ substr($alert->symbol, 0, -4) }}</span><span class="sym-quote">{{ substr($alert->symbol, -4) }}</span>
                            <span class="sym-sub">Binance &middot; Futures</span>
                        </td>
                        <td class="fw-semibold">{{ fmtPrice($alert->close_price) }}</td>
                        <td>
                            {{ fmtVol($alert->buy_volume) }}
                            <span class="ratio-badge ms-1 {{ ratioBadgeClass($alert->volume_moment) }}">
                                {{ number_format($alert->volume_moment, 1) }}x
                            </span>
                        </td>
                        <td>{{ fmtVol($alert->current_volume) }}</td>
                        <td class="text-primary">{{ fmtVol($alert->avg_volume) }}</td>
                        <td>
                            <span class="{{ ratioTextClass($alert->volume_moment) }}">
                                {{ number_format($alert->volume_moment, 2) }}x
                            </span>
                        </td>
                        <td style="min-width:130px;">
                            <div class="d-flex align-items-center gap-2">
                                <div class="flex-grow-1 bg-light rounded" style="height:6px;">
                                    <div class="pct-bar bg-success"
                                         style="width:{{ min(100, $alert->buy_volume_percentage ?? 0) }}%"></div>
                                </div>
                                <span class="text-muted small" style="width:38px;text-align:right;">
                                    {{ number_format($alert->buy_volume_percentage ?? 0, 1) }}%
                                </span>
                            </div>
                        </td>
                        <td class="text-muted">{{ fmtVol($alert->trades) }}</td>

                        {{-- Last 5 candles --}}
{{--                        @if(count($candles) >= 5)--}}
{{--                            @foreach($candles as $ci => $candle)--}}
{{--                                <td class="candle-cell">--}}
{{--                                    {!! miniCandleSvg($candle, $rangeMin, $rangeMax, $ci === 4) !!}--}}
{{--                                </td>--}}
{{--                            @endforeach--}}
{{--                        @else--}}
{{--                            <td colspan="5" class="text-center text-muted small">—</td>--}}
{{--                        @endif--}}

{{--                        <td>--}}
{{--                            @if($isGreen)--}}
{{--                                <span class="badge bg-success-subtle text-success border border-success-subtle">Green</span>--}}
{{--                            @else--}}
{{--                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Red</span>--}}
{{--                            @endif--}}
{{--                        </td>--}}
                        <td class="text-muted small">
                            @if($alert->candle_open_time)
                            {{$alert->candle_open_time}}
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-muted small">{{$alert->z_score_last_10_candles}}</td>
                        <td class="text-muted small">{{$alert->z_score_last_192_candles}}</td>
                        <td class="text-muted small">{{$alert->range_percentage}} %</td>
                        <td class="text-muted small">
                            {{ $alert->created_at->format('H:i') }} UTC
                            <br><span style="font-size:10.5px;">{{ $alert->created_at->format('d M') }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
    {{-- jQuery (required first) --}}
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    {{-- DataTables core --}}
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    {{-- Responsive --}}
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    {{-- Buttons --}}
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
    {{-- Export dependencies --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

    <script>
        /* ── Render raw ms timestamps ───────────────────────────── */
        function renderTimestamps() {
            document.querySelectorAll('td[data-ms]').forEach(function (td) {
                const ms = parseInt(td.getAttribute('data-ms'));
                if (!ms) return;
                const d = new Date(ms);
                const pad = n => String(n).padStart(2, '0');
                const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                td.querySelector('.ts-time').textContent =
                    pad(d.getUTCHours()) + ':' + pad(d.getUTCMinutes());
                td.querySelector('.ts-date').textContent =
                    pad(d.getUTCDate()) + ' ' + months[d.getUTCMonth()] + ' ' + d.getUTCFullYear();
            });
        }

        $(document).ready(function () {
            $('#volumeTable').DataTable({
                // responsive: true,
                // autoWidth: false,
                autoWidth: false,
                scrollX: true,
                scrollY: '500px',
                order: [],
                pageLength: 50,
                lengthMenu: [
                    [10, 25, 50, 100, -1],
                    ['10', '25', '50', '100', 'All']
                ],
                dom:
                    "<'row align-items-center mb-3'<'col-md-5 d-flex align-items-center'Bl><'col-md-7 d-flex justify-content-md-end'f>>" +
                    "<'row'<'col-12'tr>>" +
                    "<'row mt-3'<'col-md-5'i><'col-md-7 d-flex justify-content-end'p>>",
                buttons: [
                    { extend: 'excel', className: 'btn btn-success btn-sm' },
                    { extend: 'csv',   className: 'btn btn-primary btn-sm' },
                    { extend: 'pdf',   className: 'btn btn-danger btn-sm'  },
                    { extend: 'print', className: 'btn btn-secondary btn-sm' }
                ],
                columnDefs: [
                    // { orderable: false, targets: [9, 10, 11, 12, 13] },  // candle SVG columns
                    { className: 'text-nowrap', targets: '_all' }
                ],
                language: {
                    emptyTable: 'No volume spikes recorded yet. The scanner runs every 15 minutes.'
                },
                drawCallback: function () {
                    renderTimestamps();
                }
            });

            renderTimestamps();
        });
    </script>
@endpush
