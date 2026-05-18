<x-mail::message>
# Plant Connectivity Update

The monitoring system has detected a change in connectivity for a plant PLC.

**Plant:** {{ $plant->name }} ({{ $plant->code }})
**IP Address:** {{ $plant->plc_ip }}
**Status:** {{ $status === 'ONLINE' ? '✅ ONLINE' : '❌ OFFLINE' }}
**Checked At:** {{ $timestamp }}

@if($status === 'OFFLINE')
The plant PLC is currently unreachable via the network. This may affect real-time production tracking. Please check the network connection at the site.
@else
Connectivity to the plant PLC has been restored.
@endif

<x-mail::button :url="config('app.url')">
Open Portal
</x-mail::button>

Thanks,<br>
{{ config('app.name') }} Infrastructure Monitor
</x-mail::message>
