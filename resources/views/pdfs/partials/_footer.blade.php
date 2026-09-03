{{-- resources/views/pdfs/partials/_footer.blade.php
     Plant Name + POWERED BY plant name — shared across all PDF templates
--}}
@php
    $footerPlant = $plant 
        ?? ($data['plant'] ?? null)
        ?? (session('active_plant_id') ? \App\Models\Plant::find(session('active_plant_id')) : null)
        ?? \App\Models\Plant::first();
    
    $footerPlantName = is_array($footerPlant) 
        ? ($footerPlant['name'] ?? $footerPlant['plant_name'] ?? '') 
        : ($footerPlant?->name ?? $footerPlant?->plant_name ?? ($data['company']['name'] ?? ''));
@endphp
<div class="powered-footer">
    <div class="pf-left">
        @if(!empty($footerPlantName))
            <span class="pf-plant" style="font-weight: 700; color: #334155; text-transform: none;">{{ $footerPlantName }}</span>
            <span style="margin: 0 4px; color: #cbd5e1;">&bull;</span>
        @endif
        Generated on {{ now()->format('d M Y, h:i A') }}
    </div>
    <div class="pf-right">
        Powered by : <span class="pf-brand">{{ !empty($footerPlantName) ? $footerPlantName : 'onemodo.com' }}</span>
    </div>
</div>

