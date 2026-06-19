<x-mail::message>
# Hello

This alert was triggered automatically.
@if (!empty($logs))
<x-mail::table>
    | Symbol      | Movement    | From         |  To          |
    |:----------- |:----------- | :----------- | :----------- |
    @foreach ($logs as $log)
        | {{ $log['symbol'] }} | {{ $log['performance'] }}% | {{ $log['price_from'] }} | {{ $log['price_to'] }} |
    @endforeach
</x-mail::table>
@endif

<x-mail::button url="{{ route('alerts.config.index') }}">
View Alerts
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}

<small>If you're having trouble clicking the button, open: <a href="{{ route('alerts.config.index') }}" target="_blank">{{ route('alerts.config.index') }}</a></small>
</x-mail::message>
