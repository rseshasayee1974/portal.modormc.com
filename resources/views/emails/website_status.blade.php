<x-mail::message>
# Website Status Update

The monitoring system has detected a status change for your website.

**URL:** {{ $url }}
**Status:** {{ $status === 'UP' ? '✅ ONLINE' : '❌ OFFLINE' }}
**Checked At:** {{ $timestamp }}

@if($status === 'DOWN')
The website is currently unreachable. Please check your server or hosting provider immediately.
@else
The website is back online and responding normally.
@endif

<x-mail::button :url="'https://' . $url">
Visit Website
</x-mail::button>

Thanks,<br>
{{ config('app.name') }} Monitoring System
</x-mail::message>
