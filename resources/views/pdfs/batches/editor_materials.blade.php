{{--
Author: ragul-onemodo
Created: 2026-11-28 11:44:35 Asia/Calcutta (UTC+05:30)
--}}
<table aria-label="Materials breakdown and tolerances" style="width:100%;border-collapse:collapse;table-layout:fixed;font-size:10pt;text-align:center;overflow-wrap:anywhere">
    <colgroup><col style="width:19%">@foreach ($materialsDisplay['columns'] as $column)@unless ($loop->first)<col>@endunless @endforeach</colgroup>
    <thead><tr>
        @foreach ($materialsDisplay['columns'] as $column)
            <th scope="col" style="padding:4px 2px;border-bottom:1px solid">{{ $column }}</th>
        @endforeach
    </tr></thead>
    <tbody>
        @foreach ($materialsDisplay['rows'] as $row)
            <tr style="break-inside:avoid">
                <th scope="row" colspan="{{ count($row) === 1 ? count($materialsDisplay['columns']) : 1 }}" style="text-align:left;padding:3px 2px;border-bottom:1px solid #ddd">{{ $row[0] }}</th>
                @foreach (array_slice($row, 1) as $cell)
                    <td style="padding:3px 2px;border-bottom:1px solid #ddd">{{ $cell }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
