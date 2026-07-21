{{-- Sortable column header. Params: key, label, sort (active key), dir (active dir); optional width. --}}
@php
    $active = ($sort ?? null) === $key;
    $nextDir = $active && ($dir ?? 'asc') === 'asc' ? 'desc' : 'asc';
    $query = array_merge(request()->query(), ['sort' => $key, 'dir' => $nextDir]);
    unset($query['page']);
    $icon = !$active ? 'fa-sort text-muted' : ($dir === 'asc' ? 'fa-sort-up' : 'fa-sort-down');
@endphp
<th @isset($width) style="width:{{ $width }}" @endisset>
    <a href="{{ url()->current().'?'.http_build_query($query) }}" style="color:inherit;text-decoration:none;white-space:nowrap">
        {{ $label }} <i class="fas {{ $icon }} ml-1" @if($active) style="color:var(--brand,#2563eb)" @endif></i>
    </a>
</th>
