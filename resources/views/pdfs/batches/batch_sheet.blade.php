@if (\App\Services\JayadhityaPlant2787BatchSheet::matches($batch))
    @include('pdfs.batches.jayadhitya_plant2787')
@else
    @include('pdfs.batches.jayadhitya_groups')
@endif
