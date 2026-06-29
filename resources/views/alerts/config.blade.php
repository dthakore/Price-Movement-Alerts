@extends('layouts.app')
@section('title', 'Alert Configuration')
@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <style>
        .select2-container .select2-selection--multiple {
            min-height: 35px;
            font-size: 15px;
        }
        .select2-selection__choice {
            font-size: 12px;
        }
        .action-btns i {
            cursor: pointer;
            font-size: 1.1rem;
        }
        .action-btns i:hover {
            opacity: 0.7;
        }
    </style>
@endpush
@section('content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(auth()->user()->isAdmin())
    <div class="mb-3 text-end">
        <a href="{{ route('sync-exchange-info') }}" class="btn btn-success btn-sm">
            <i class="bi bi-diagram-3 me-1"></i> Sync Exchange Symbols
        </a>
    </div>
        <div class="card shadow-sm mb-4">
{{--        <div class="card-header fw-bold">Create New Alert</div>--}}
        <div class="card-body">
            <form method="POST" action="{{ route('alerts.config.store') }}">
                @csrf

                <div class="row g-3">
                        <input type="hidden" name="user_id" value="{{ auth()->id() }}">

                    <div class="col-md-3">
                        <label class="form-label">Notify Users</label>

                        <select name="notify_users[]"
                                class="form-select user-select"
                                multiple>

                            @foreach($users as $user)
                                <option value="{{ $user->id }}">
                                    {{ $user->name }} ({{ $user->email }})
                                </option>
                            @endforeach

                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Symbol Source</label>
                        <select name="symbol_source" id="symbol_source" class="form-select">
                            <option value="1" selected>Auto (From Binance)</option>
                            <option value="2">Manual</option>
                        </select>
                    </div>

{{--                    <div class="col-md-3" id="symbols_input_wrapper" style="display: none">--}}
{{--                        <label class="form-label">Symbols</label>--}}
{{--                        <input type="text"--}}
{{--                               name="symbols"--}}
{{--                               class="form-control text-uppercase"--}}
{{--                               placeholder='["BTCUSDT","ETHUSDT"]'>--}}
{{--                    </div>--}}

                    <div class="col-md-3" id="symbols_input_wrapper" style="display:none">
                        <label class="form-label">Symbols</label>

                        <select name="symbols[]" id="symbols_select" class="form-select form-control " multiple>
                            @foreach($symbols as $symbol)
                                <option value="{{ $symbol }}">{{ $symbol }}</option>
                            @endforeach
                        </select>
                    </div>


                    <div class="col-md-1">
                        <label class="form-label">Move %</label>
                        <input type="number" step="0.1" name="percentage" class="form-control" placeholder="3">
                    </div>

                    <div class="col-md-1">
                        <label class="form-label">Direction</label>
                        <select name="direction" class="form-select">
                            <option value="1">UP</option>
                            <option value="0">DOWN</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Time Duration</label>
                        <select name="time_duration" class="form-select">
                            @for($i=1;$i<=24;$i++)
                                <option value="{{ $i }}">{{ $i }} Hour</option>
                            @endfor
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Frequency</label>
                        <select name="frequency_minutes" class="form-select">
                            <option value="5">5 Min</option>
                            <option value="10" selected>10 Min</option>
                            <option value="15">15 Min</option>
                        </select>
                    </div>
<div class="col-md-2">
   <label class="form-label">Type</label>
    <select name="type" id="type" class="form-control">
        <option value="">Select Type</option>
        <option value="normal">Normal</option>
        <option value="high reversion">High Reversion</option>
    </select>
</div>


<div class="col-md-2" id="reversion_section" style="display:none;">
    <label class="form-label">Reversion %</label>
    <input
        type="number"
        step="0.01"
        name="reversion_percentage"
        id="reversion_percentage"
        class="form-control">
</div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button class="btn btn-primary w-100">
                            <i class="bi bi-plus-circle"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    @endif
    <div class="card shadow-sm">
        <div class="card-header fw-bold">Your Alert Rules</div>

        <table class="table table-hover mb-0 align-middle">
            <thead>
            <tr>
{{--                @if(auth()->user()->isAdmin())--}}
{{--                    <th>User</th>--}}
{{--                @endif--}}
                <th>Notify Users</th>
                <th>Symbols</th>
                <th>Move %</th>
                <th>Direction</th>
                <th>Time (hrs)</th>
                <th>Frequency</th>
                <th>Status</th>
                <th class="text-center">Actions</th>
            </tr>
            </thead>

            <tbody>
            @foreach($configs as $c)
                <tr id="row-{{ $c->id }}">
                    <td>

                        {{-- ADMIN VIEW --}}
                        @if(auth()->user()->isAdmin())

                            <span class="view">
            {{ $c->notifyUsers->pluck('name')->implode(', ') }}
        </span>

                            <select class="form-select form-select-sm edit d-none user-edit-select" multiple>
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}"
                                            @selected($c->notifyUsers->contains('id',$u->id))>
                                        {{ $u->name }}
                                    </option>
                                @endforeach
                            </select>

                            {{-- USER VIEW --}}
                        @else

                            <div class="form-check form-switch">
                                <input
                                    type="checkbox"
                                    class="form-check-input user-notify-toggle"
                                    data-config="{{ $c->id }}"
                                    data-user="{{ auth()->id() }}"
                                    @checked($c->notifyUsers->contains('id',auth()->id()))
                                >
                            </div>

                        @endif

                    </td>

                    {{-- Symbols --}}
                    <td>
    <span class="view">
    {{ $c->symbol_source == 1 ? 'AUTO (Binance)' : implode(', ', $c->symbols ?? []) }}
</span>


                        <select class="form-select form-select-sm edit d-none symbol-source-select">
                            <option value="1" @selected($c->symbol_source == 1)>Auto (Binance)</option>
                            <option value="2" @selected($c->symbol_source == 2)>Manual</option>
                        </select>

{{--                        <input type="text"--}}
{{--                               class="form-control form-control-sm edit d-none symbols-input mt-1"--}}
{{--                               value="{{ $c->symbols }}"--}}
{{--                               @if($c->symbol_source == 1) style="display:none" @endif>--}}
{{--                        @if($c->symbol_source == 2)--}}
                        <select multiple class="form-select form-select-sm edit d-none symbols-input select2">
                            @foreach($symbols as $symbol)
                                <option value="{{ $symbol }}"
                                        @selected(in_array($symbol, $c->symbols ?? []))>
                                    {{ $symbol }}
                                </option>
                            @endforeach
                        </select>
{{--                        @endif--}}
                    </td>

                    {{-- Percentage --}}
                    <td>
                        <span class="view">{{ $c->percentage }}%</span>
                        <input type="number" step="0.1" class="form-control form-control-sm edit d-none"
                               value="{{ $c->percentage }}">
                    </td>

                    {{-- Direction --}}
                    <td>
                        <span class="view">{{ $c->direction ? 'UP' : 'DOWN' }}</span>
                        <select class="form-select form-select-sm edit d-none">
                            <option value="1" @selected($c->direction)>UP</option>
                            <option value="0" @selected(!$c->direction)>DOWN</option>
                        </select>
                    </td>

                    {{-- Time --}}
                    <td>
                        <span class="view">{{ $c->time_duration }}</span>
                        <select class="form-select form-select-sm edit d-none">
                            @for($i=1;$i<=24;$i++)
                                <option value="{{ $i }}" @selected($c->time_duration== $i . " Hour" )>
                                    {{ $i }} Hour
                                </option>
                            @endfor
                        </select>
                    </td>

                    {{-- Frequency --}}
                    <td>
                        <span class="view">{{ $c->frequency_minutes }} min</span>
                        <select class="form-select form-select-sm edit d-none">
                            <option value="5" @selected($c->frequency_minutes==5)>5 Min</option>
                            <option value="10" @selected($c->frequency_minutes==10)>10 Min</option>
                            <option value="15" @selected($c->frequency_minutes==15)>15 Min</option>
                        </select>
                    </td>

                    {{-- Status toggle --}}
                    <td>
                            @if(auth()->user()->isAdmin())
                            <div class="form-check form-switch">
                                <input class="form-check-input status-toggle"
                                   type="checkbox"
                                   data-id="{{ $c->id }}"
                                   @checked($c->is_active)>
                            </div>
                        @else
                            <span class ='{{$c->is_active == 1 ? 'text-success' : 'text-danger'}}'>{{$c->is_active == 1 ? "Enable" : "Disable"}}</span>
                            @endif
                    </td>

                    {{-- Actions --}}
                    <td class="text-center action-btns">

                        {{-- View Notifications --}}
                        <a href="{{ route('alerts.config.notifications', $c->id) }}"
                           title="View Notifications" style="text-decoration: none">
                            <i class="bi bi-eye text-info"></i>
                        </a>
                        @if(auth()->user()->isAdmin())
                        <i class="bi bi-pencil-square text-primary edit-btn"></i>
                        <i class="bi bi-check-square text-success d-none save-btn" data-id="{{ $c->id }}"></i>
                        <i class="bi bi-x-square text-secondary d-none cancel-btn"></i>

                        <form method="POST"
                              action="{{ route('alerts.config.destroy', $c) }}"
                              class="d-inline"
                              data-confirm="Delete this alert?">
                            @csrf
                            @method('DELETE')
                            <button class="btn p-0 border-0 bg-transparent">
                                <i class="bi bi-trash text-danger"></i>
                            </button>
                        </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

@endsection
@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>

        // CREATE FORM toggle
        document.getElementById('symbol_source')?.addEventListener('change', function () {
            document.getElementById('symbols_input_wrapper')
                .style.display = this.value == 2 ? 'block' : 'none';
        });

        // INLINE EDIT toggle
        document.querySelectorAll('.symbol-source-select').forEach(select => {
            select.addEventListener('change', function () {
                const row = this.closest('td');
                const symbolsInput = row.querySelector('.symbols-input');

                symbolsInput.style.display = this.value == 2 ? 'block' : 'none';
            });
        });
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const row = btn.closest('tr');

                // Toggle view/edit
                row.querySelectorAll('.view').forEach(e => e.classList.add('d-none'));
                row.querySelectorAll('.edit').forEach(e => e.classList.remove('d-none'));

                const source = row.querySelector('.symbol-source-select').value;
                const select = $(row).find('.symbols-input');

                // Initialize Select2 ONLY if Manual
                if (source == 2) {
                    select.show();

                    if (!select.hasClass('select2-hidden-accessible')) {
                        select.select2({
                            width: '100%',
                            placeholder: 'Select symbols',
                            allowClear: true
                        });
                    }

                    select.next('.select2-container').show();
                }

                row.querySelector('.save-btn').classList.remove('d-none');
                row.querySelector('.cancel-btn').classList.remove('d-none');
                btn.classList.add('d-none');
            });
        });

        $(document).ready(function () {

            $('#symbols_select').select2({
                width: '100%',
                placeholder: 'Select symbols'
            });

            $('.user-select').select2({
                width: '100%',
                placeholder: 'Select Users'
            });

        });


        $('.edit-btn').on('click', function () {
            const row = $(this).closest('tr');
            const source = row.find('.symbol-source-select').val();
            const userSelect = $(row).find('.user-edit-select');

            if(userSelect.length){

                if(!userSelect.hasClass('select2-hidden-accessible')){
                    userSelect.select2({
                        width:'100%',
                        placeholder:'Select users'
                    });
                }

            }
            toggleSymbols(row, source);
        });

        function toggleSymbols(row, source) {
            const select = row.find('.symbols-input');

            if (source == 2) {
                select.show();

                if (!select.hasClass('select2-hidden-accessible')) {
                    select.select2({
                        width: '100%',
                        placeholder: 'Select symbols',
                        allowClear: true
                    });
                }

                select.next('.select2-container').show();
            } else {
                select.hide();
                select.next('.select2-container').hide();
            }
        }

        $('.symbol-source-select').on('change', function () {
            toggleSymbols($(this).closest('tr'), $(this).val());
        });


        document.querySelectorAll('.cancel-btn').forEach(btn => {
            btn.addEventListener('click', () => location.reload());
        });

            document.querySelectorAll('.save-btn').forEach(btn => {
            btn.addEventListener('click', function () {

                const row = this.closest('tr');
                const id  = row.id.replace('row-', '');

                const inputs = row.querySelectorAll('.edit');
                const userSelect = row.querySelector('.user-edit-select');
                const symbolsSelect = row.querySelector('.symbols-input');
                const isActiveCheckbox = row.querySelector('.status-toggle');

                const data = {
                    symbol_source: inputs[1].value,
                    symbols: symbolsSelect
                        ? [Array.from(symbolsSelect.selectedOptions).map(o => o.value).join(',')]
                        : [],
                    notify_users: userSelect
                        ? Array.from(userSelect.selectedOptions).map(o => o.value)
                        : [],
                    percentage: inputs[3].value,
                    direction: inputs[4].value,
                    time_duration: inputs[5].value,
                    frequency_minutes: inputs[6].value,
                    is_active: isActiveCheckbox && isActiveCheckbox.checked ? 1 : 0,
                    _token: document.querySelector('meta[name="csrf-token"]').content,
                    _method: 'PUT'
                };

                fetch(`/alerts/config/${id}`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json'
                    },
                    body: new URLSearchParams(data)
                })
                    .then(res => res.json())
                    .then(resp => {
                        if (resp.status) {
                            showToast('Alert updated successfully', 'success');
                            setTimeout(() => location.reload(), 800);
                        } else {
                            showToast('Update failed', 'danger');
                        }
                    })
                    .catch(() => showToast('Server error', 'danger'));
            });
        });

        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-bg-${type} border-0 show position-fixed top-0 end-0 m-3`;
            toast.style.zIndex = 9999;

            toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto"></button>
        </div>
    `;

            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        $('.user-notify-toggle').on('change', function(){

            const configId = $(this).data('config');
            const userId   = $(this).data('user');
            const enabled  = $(this).is(':checked') ? 1 : 0;

            fetch(`/alerts/config/user-toggle`,{

                method:'POST',

                headers:{
                    'Content-Type':'application/json',
                    'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content
                },

                body:JSON.stringify({
                    alert_configuration_id:configId,
                    user_id:userId,
                    enabled:enabled
                })

            })
                .then(res=>res.json())
                .then(resp=>{
                    if(resp.status){
                        showToast('Notification preference updated');
                    }
                });

        });

        $(document).ready(function() {

    $('#type').change(function() {

        if ($(this).val() == 'high reversion') {

            $('#reversion_section').show();

            $('#reversion_percentage').prop('required', true);

        } else {

            $('#reversion_section').hide();

            $('#reversion_percentage').prop('required', false);

            $('#reversion_percentage').val('');
        }
    });

});
    </script>


@endpush
