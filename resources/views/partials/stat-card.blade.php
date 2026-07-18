{{--
    Professional stat tile (design tokens, theme-aware). Replaces the saturated
    AdminLTE small-box. Usage:
    @include('partials.stat-card', ['label' => __('Days'), 'value' => 20, 'icon' => 'fa-calendar-check', 'chip' => 'chip-blue', 'sub' => '...'])
    chip = chip-blue | chip-green | chip-amber | chip-red | chip-slate
--}}
<div class="stat-card">
    <div>
        <div class="stat-label">{{ $label }}</div>
        <div class="stat-value">{{ $value }}</div>
        <div class="stat-sub">{!! $sub ?? '&nbsp;' !!}</div>
    </div>
    <div class="stat-chip {{ $chip ?? 'chip-blue' }}"><i class="fas {{ $icon }}"></i></div>
</div>
