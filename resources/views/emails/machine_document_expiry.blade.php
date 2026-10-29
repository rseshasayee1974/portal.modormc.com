{{--
Author: ragul-onemodo
Created: 2026-10-29 14:21:49 Asia/Calcutta (UTC+05:30)
--}}
<x-mail::message>
# Machine Document Expiry Reminders

**Plant:** {{ $plantName }}<br>
**Checked on:** {{ $checkedDate }}

The following machine documents are approaching expiry or have expired. Please renew them and update the validity dates in the portal.

<x-mail::table>
| Machine / Registration | Document | Valid Until | Status |
|:-----------------------|:---------|:------------|:-------|
@foreach($documents as $document)
| {{ $document['registration'] }} | {{ $document['document_type'] }} | {{ $document['expiry_label'] }} | {{ $document['status'] }} |
@endforeach
</x-mail::table>

Reminders are sent 30, 15, 7 and 1 day before expiry, on expiry, then weekly while overdue. Renewing a document updates its reminder schedule automatically.

<x-mail::button :url="route('machines.index')">
Open Machines
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
