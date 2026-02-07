@component('mail::message')
# Report: {{ $reportTitle }}

Your report ({{ $format }}) is attached. This report was sent by email because it exceeded the direct download limit.

The attachment contains the full data for the selected period.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
