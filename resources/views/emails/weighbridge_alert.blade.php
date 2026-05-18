<x-mail::message>
# Weighbridge Connectivity Alert

A customer/operator is experiencing repeated problems capturing weight from the weighbridge.

**Alert Details:**
- **Issue:** Failed to get weight after 5 consecutive attempts.
**Detected At:** {{ $timestamp }}

### 🏢 Plant & Entity Details
- **Plant:** {{ $plant->name ?? 'N/A' }} ({{ $plant->code ?? 'N/A' }})
- **Entity:** {{ $plant->entity->name ?? 'N/A' }}
- **Location:** {{ $plant->latitude ?? 'N/A' }}, {{ $plant->longitude ?? 'N/A' }}

### 👤 User Information
- **Email:** {{ $user->email ?? 'N/A' }}
- **Username:** {{ $user->username ?? 'N/A' }}
- **Mobile:** {{ $user->mobile ?? 'Not Available' }}

@if($aiDiagnosis)
<x-mail::panel>
### 🤖 AI Technical Diagnosis
{{ $aiDiagnosis }}
</x-mail::panel>
@endif

@if(!empty($errorDetails))
### 💻 Technical Console Details
@foreach($errorDetails as $key => $value)
@if($key === 'stack')
**Stack Trace:**
<pre style="font-size: 9px; color: #666; background: #f9f9f9; padding: 10px; border-radius: 5px; overflow-x: auto;">
{{ $value }}
</pre>
@elseif($key === 'responseData')
**Local Service Response:**
<pre style="font-size: 10px; color: #d63384; background: #fff5f8; padding: 10px; border-radius: 5px;">
{{ is_array($value) || is_object($value) ? json_encode($value, JSON_PRETTY_PRINT) : $value }}
</pre>
@else
- **{{ ucfirst($key) }}:** {{ $value }}
@endif
@endforeach
@endif

This may indicate a hardware failure, local API service stoppage, or serial port disconnection.

Please investigate the local weighbridge service (Port 8089) or the serial connection.

Thanks,<br>
{{ config('app.name') }} Automated Support
</x-mail::message>
