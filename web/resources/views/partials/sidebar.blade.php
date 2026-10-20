<nav class="mt-2">
    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
        @foreach (config('menu') as $group)
            @php
                $items = array_values(array_filter($group['items'], function ($item) {
                    return is_null($item['permission']) || auth()->user()->can($item['permission']);
                }));
                $exact = count(array_filter($items, function ($item) {
                    return Route::has($item['route']) && request()->routeIs($item['route']);
                })) > 0;
                $isActive = function ($item) use ($exact) {
                    if (!Route::has($item['route'])) {
                        return false;
                    }
                    if (request()->routeIs($item['route'])) {
                        return true;
                    }
                    // a page of the same section (an edit or detail page) keeps its menu item current, unless a sibling is the page itself
                    $prefix = explode('.', $item['route'])[0];
                    return !$exact && request()->routeIs($prefix . '.*');
                };
                $open = count(array_filter($items, $isActive)) > 0;
            @endphp

            @if (count($items) > 0)
                @if (!empty($group['single']))
                    @php($item = $items[0])
                    <li class="nav-item">
                        <a href="{{ Route::has($item['route']) ? route($item['route']) : '#' }}" class="nav-link {{ $isActive($item) ? 'active' : '' }}">
                            <i class="nav-icon fas {{ $group['icon'] }}"></i>
                            <p>{{ __('menu.' . $group['label']) }}</p>
                        </a>
                    </li>
                @else
                    <li class="nav-item has-treeview {{ $open ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ $open ? 'active' : '' }}">
                            <i class="nav-icon fas {{ $group['icon'] }}"></i>
                            <p>{{ __('menu.' . $group['label']) }}<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            @foreach ($items as $item)
                                <li class="nav-item">
                                    <a href="{{ Route::has($item['route']) ? route($item['route']) : '#' }}" class="nav-link {{ $isActive($item) ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>{{ __('menu.' . $item['label']) }}</p>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endif
            @endif
        @endforeach
    </ul>
</nav>
