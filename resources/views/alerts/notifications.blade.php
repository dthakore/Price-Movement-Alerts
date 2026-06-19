@extends('layouts.app')
@section('title', 'Alert Notifications')
@push('styles')
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/fixedheader/3.4.1/css/fixedHeader.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/fixedcolumns/4.3.0/css/fixedColumns.dataTables.min.css">
    <style>
        .child-table {
            margin-left: 40px;
            border-left: 3px solid #0d6efd;
            width: 100% !important;
            /*table-layout: fixed;*/
            font-size: 12px;
            background: #fbfbfd;
        }
        td.child {
            overflow-x: auto;
            padding: 10px !important;
            background: #fafbff;
        }
        .card {
            border-radius: 12px;
        }
        table.dataTable tbody tr {
            font-size: 13px;
        }
        table.dataTable thead th {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6c757d;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        .table td {
            vertical-align: middle;
        }
        .badge-soft-success {
            background: rgba(25, 135, 84, 0.1);
            color: #198754;
        }
        .badge-soft-danger {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        .badge-soft-warning {
            background: rgba(255, 193, 7, 0.15);
            color: #b58105;
        }
        .expand-btn {
            border: none;
            background: #eef2ff;
            color: #4f46e5;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            font-weight: bold;
        }
        .expand-btn:hover {
            background: #e0e7ff;
        }
        .table-hover tbody tr:hover {
            background-color: #f5f7ff;
        }
        .tooltip-inner {
            font-size: 12px;
            padding: 6px 10px;
        }
    </style>
@endpush

@section('content')
    <div class="container">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">
                <i class="bi bi-bell me-2"></i>
                Notifications for Alert #{{ $config->id }}
            </h4>
            <div class="d-flex gap-2">
                <form action="{{ route('alerts.notifications.clear', $config->id) }}"
                        method="POST"
                        data-confirm="Are you sure you want to clear all notifications?">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-danger">
                        <i class="bi bi-trash3 me-1"></i> Clear All
                    </button>
                </form>
                <a href="{{ route('alerts.config.index') }}"
                    class="btn btn-sm btn-secondary">
                    ← Back
                </a>
            </div>
        </div>
        <div class="card shadow-sm">
            <ul class="nav nav-tabs" id="alertTabs">
                <li class="nav-item">
                    <button class="nav-link active" data-type="price_alert">
                        Price Alerts
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-type="funnel_alert">
                        Funnel Alerts
                    </button>
                </li>
            </ul>
            <div class="card-body card p-3 p-md-4">
                <table id="notificationTable" class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th></th>
                        <th>#</th>
                        <th>Symbol</th>
                        <th>Direction</th>
                        <th>From -> To Price</th>
                        <th>High</th>
                        <th>Low</th>
                        <th>Volume</th>
                        <th>Move %</th>
                        <th>Z Score <br/> 24H / 2D / 3D</th>
                        <th>R3 %</th>
                        <th>R5 %</th>
                        <th>R15 %</th>
                        <th>Candle</th>
                        <th>Open Time</th>
                        <th>Triggered At</th>
                    </tr>
                    </thead>
                    <tbody>
                    {{-- PRICE ALERT (Parent rows) --}}
                    @foreach($parents as $n)
                        <tr data-source="price_alert" data-id="{{ $n->id }}">
                            <td class="text-center">
                                @if(isset($notifications[$n->id]))
                                    <button class="expand-btn toggle-child">+</button>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $n->id }}</td>
                            <td><div class="fw-semibold">{{ $n->symbol }}</div></td>
                            <td>
                                <span class="badge {{ $n->performance > 0 ? 'badge-soft-success' : 'badge-soft-danger' }}">
                                    {{ $n->performance > 0 ? 'UP' : 'DOWN' }}
                                </span>
                            </td>
                            <td>{{ $n->price_from }} → {{ $n->price_to }}</td>
                            <td>{{ $n->high }}</td>
                            <td>{{ $n->low }}</td>
                            <td>{{ $n->volume ? round($n->volume, 2) : '' }}</td>
                            <td class="{{ $n->performance > 0 ? 'text-success' : 'text-danger' }}">
                                {{ $n->performance }}%
                            </td>
                            <td>{{ round($n->z_score_1d,2) }} / {{ round($n->z_score_2d,2) }} / {{ round($n->z_score_3d,2) }}</td>
                            <td>{{ $n->r3 ? round($n->r3,2).'%' : '-' }}</td>
                            <td>{{ $n->r5 ? round($n->r5,2).'%' : '-' }}</td>
                            <td>{{ $n->r15 ? round($n->r15,2).'%' : '-' }}</td>
                            <td>
                                @if($n->candle_label)
                                    <span class="badge {{ $n->candle_badge_class }}">
                                        {{ $n->candle_label }}
                                    </span>
                                @endif
                            </td>
                            <td data-bs-toggle="tooltip" title="{{ $n->open_time }}">{{ $n->open_timestamp }}</td>
                            <td data-bs-toggle="tooltip" title="{{ $n->full_time }}">
                                {{ $n->pretty_time }}
                            </td>
                        </tr>
                    @endforeach
                    {{-- FUNNEL ALERT --}}
                    @foreach($funnelLogs as $n)
                        <tr data-source="funnel_alert">
                            <td></td>
                            <td class="text-muted small">{{ $n->id }}</td>
                            <td><div class="fw-semibold">{{ $n->symbol }}</div></td>
                            <td>
                                <span class="badge {{ $n->performance > 0 ? 'badge-soft-success' : 'badge-soft-danger' }}">
                                    {{ $n->performance > 0 ? 'UP' : 'DOWN' }}
                                </span>
                            </td>
                            <td>{{ $n->price_from }} → {{ $n->price_to }}</td>
                            <td>{{ $n->high }}</td>
                            <td>{{ $n->low }}</td>
                            <td>{{ $n->volume ? round($n->volume, 2) : '' }}</td>
                            <td class="{{ $n->performance > 0 ? 'text-success' : 'text-danger' }}">
                                {{ $n->performance }}%
                            </td>
                            <td>{{ round($n->z_score_1d,2) }} / {{ round($n->z_score_2d,2) }} / {{ round($n->z_score_3d,2) }}</td>
                            <td>{{ $n->r3 ? round($n->r3,2).'%' : '-' }}</td>
                            <td>{{ $n->r5 ? round($n->r5,2).'%' : '-' }}</td>
                            <td>{{ $n->r15 ? round($n->r15,2).'%' : '-' }}</td>
                            <td>
                                @if($n->candle_label)
                                    <span class="badge {{ $n->candle_badge_class }}">
                                        {{ $n->candle_label }}
                                    </span>
                                @endif
                            </td>
                            <td data-bs-toggle="tooltip" title="{{ $n->open_time }}">{{ $n->open_timestamp }}</td>
                            <td data-bs-toggle="tooltip" title="{{ $n->full_time }}">
                                {{ $n->pretty_time }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    {{-- jQuery (REQUIRED FIRST) --}}
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    {{-- DataTables Core --}}
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    {{-- Responsive --}}
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    {{-- Buttons --}}
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
    {{-- Export Dependencies --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/fixedheader/3.4.1/js/dataTables.fixedHeader.min.js"></script>
    <script src="https://cdn.datatables.net/fixedcolumns/4.3.0/js/dataTables.fixedColumns.min.js"></script>
    <script>
        $(document).ready(function () {
            const childMap = @json($notifications);

            initTooltips(); // initial load

            let currentTab = 'price_alert';

            $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {

                const rowNode = settings.aoData[dataIndex].nTr;
                const source = $(rowNode).data('source');

                if (currentTab === 'price_alert') {
                    return source === 'price_alert';
                }

                if (currentTab === 'funnel_alert') {
                    return source === 'funnel_alert';
                }

                return true;
            });

            var table = $('#notificationTable').DataTable({
                // responsive: true,
                scrollX: true,
                autoWidth: false,
                scrollY: '500px',
                scrollCollapse: true,
                fixedHeader: true,
                fixedColumns: {
                    leftColumns: 1 // freeze first column
                },
                processing: true,
                order: [[1, 'desc']],
                language: {
                    emptyTable: "No notifications generated yet"
                },
                lengthMenu: [
                    [10, 25, 50, 100, -1],
                    ['10', '25', '50', '100', 'All']
                ],
                pageLength: 10,
                dom:
                    "<'row align-items-center mb-3'<'col-md-5 d-flex align-items-center'Bl><'col-md-7 d-flex justify-content-md-end'f>>" +
                    "<'row'<'col-12'tr>>" +
                    "<'row mt-3'<'col-md-5'i><'col-md-7 d-flex justify-content-end'p>>",
                buttons: [
                    {
                        extend: 'excel',
                        className: 'btn btn-success btn-sm'
                    },
                    {
                        extend: 'csv',
                        className: 'btn btn-primary btn-sm'
                    },
                    {
                        extend: 'pdf',
                        className: 'btn btn-danger btn-sm'
                    },
                    {
                        extend: 'print',
                        className: 'btn btn-secondary btn-sm'
                    }
                ],
                columnDefs: [
                    { className: 'text-nowrap', targets: '_all' }
                ],
            });

            $('#alertTabs .nav-link').on('click', function () {
                $('#alertTabs .nav-link').removeClass('active');
                $(this).addClass('active');

                currentTab = $(this).data('type');

                table.draw(); // redraw table with filter
            });

            function formatChildRows(children) {
                let html = `
                    <div class="table-responsive">
                        <table class="table child-table table-hover">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Symbol</th>
                            <th>Direction</th>
                            <th>Price</th>
                            <th>High</th>
                            <th>Low</th>
                            <th>Volume</th>
                            <th>Move</th>
                            <th>Z Score</th>
                            <th>R3</th>
                            <th>R5</th>
                            <th>R15</th>
                            <th>Candle</th>
                            <th>Open Time</th>
                            <th>Time</th>
                        </tr>
                    </thead><tbody>
                `;

                children.forEach(c => {
                    let directionBadge = c.performance > 0
                        ? '<span class="badge bg-success">UP</span>'
                        : '<span class="badge bg-danger">DOWN</span>';

                    let recipients = '';

                    if (c.notificationUsers && c.notificationUsers.length) {
                        c.notificationUsers.forEach(nu => {
                            recipients += `
                                <span class="badge bg-${nu.email_sent ? 'success' : 'danger'} me-1">
                                    ${nu.user.name}
                                </span>
                            `;
                        });
                    } else {
                        recipients = '-';
                    }

                    let highDiff = c.price_to && c.high ? 
                        ((parseFloat(c.high) - parseFloat(c.price_to)) / parseFloat(c.price_to) * 100).toFixed(2)
                        : 0;
                    let lowDiff = c.price_to && c.low ? 
                        ((parseFloat(c.low) - parseFloat(c.price_to)) / parseFloat(c.price_to) * 100).toFixed(2)
                        : 0;

                    html += `
                        <tr>
                            <td class="text-muted small">${c.id}</td>
                            <td><strong>${c.symbol}</strong></td>
                            <td>
                                <span class="badge ${c.performance > 0 ? 'badge-soft-success' : 'badge-soft-danger'}">
                                    ${c.performance > 0 ? 'UP' : 'DOWN'}
                                </span>
                            </td>
                            <td>
                                <div>${c.price_from}→ ${c.price_to}</div>
                            </td>
                            <td>${c.high || ''}
                                <span class="${highDiff >= 0 ? 'text-success' : 'text-danger'} fw-semibold">${highDiff ? '('+highDiff+'%)' : ''}</span>
                            </td>
                            <td>${c.low || ''} 
                                <span class="${lowDiff >= 0 ? 'text-success' : 'text-danger'} fw-semibold">${lowDiff ? '('+lowDiff+'%)' : ''}</span>
                            </td>
                            <td>${c.volume ? parseFloat(c.volume).toFixed(2) : ''}</td>
                            <td class="${c.performance > 0 ? 'text-success' : 'text-danger'} fw-semibold">
                                ${c.performance}%
                            </td>
                            <td>
                                <div>${parseFloat(c.z_score_1d).toFixed(2)} / ${parseFloat(c.z_score_2d).toFixed(2)} / ${parseFloat(c.z_score_3d).toFixed(2)}</div>
                            </td>
                            <td>${c.r3 ? parseFloat(c.r3).toFixed(2) +'%' : '-'}</td>
                            <td>${c.r5 ? parseFloat(c.r5).toFixed(2) +'%' : '-'}</td>
                            <td>${c.r15 ? parseFloat(c.r15).toFixed(2) +'%' : '-'}</td>
                            <td>${c.candle_label ? `<span class="badge ${c.candle_badge_class}">${c.candle_label}</span>` : ''}</td>
                            <td data-bs-toggle="tooltip" title="${c.open_time ? c.open_time : ''}">${c.open_timestamp ? c.open_timestamp : ''}</td>
                        <td>
                            <div
                                data-bs-toggle="tooltip"
                                title="${c.full_time}">
                                ${c.pretty_time}
                            </div>
                        </td>
                        </tr>
                    `;
                });

                html += '</tbody></table></div>';

                return html;
            }
            // Toggle Child Rows
            $('#notificationTable tbody').on('click', '.toggle-child', function () {
                const tr = $(this).closest('tr');
                const row = table.row(tr);
                const id = tr.data('id');

                if (row.child.isShown()) {
                    row.child.hide();
                    $(this).text('+');
                } else {
                    const children = childMap[id] || [];
                    row.child(formatChildRows(children)).show();
                    $(row.child()).hide().slideDown();
                    initTooltips();
                    $(this).html('−');
                }
            });

            function initTooltips() {
                $('[data-bs-toggle="tooltip"]').tooltip();
            }
        });
    </script>
@endpush


