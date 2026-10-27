{{-- The tabs of the POS menu item: the Smart210 devices and their sync status --}}
<ul class="nav nav-tabs mb-3" id="pos-tabs">
    @can('devices.view')
        <li class="nav-item"><a class="nav-link {{ request()->routeIs('devices.*') ? 'active' : '' }}" href="{{ route('devices.index') }}">{{ __('menu.pos_devices') }}</a></li>
    @endcan
    @can('sync.view')
        <li class="nav-item"><a class="nav-link {{ request()->routeIs('sync.*') ? 'active' : '' }}" href="{{ route('sync.index') }}">{{ __('menu.sync') }}</a></li>
    @endcan
</ul>
