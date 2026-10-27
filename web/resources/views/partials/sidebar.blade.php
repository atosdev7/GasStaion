<nav class="mt-2">
    <ul class="nav nav-pills nav-sidebar nav-child-indent flex-column" data-widget="treeview" role="menu" data-accordion="false">
        @php
            // a page that is an item of the menu itself (in any group) keeps only that item current; the prefix rule below is for pages that are not
            $exact = false;
            foreach (config('menu') as $any) {
                foreach ($any['items'] as $item) {
                    if ((is_null($item['permission']) || auth()->user()->can($item['permission'])) && Route::has($item['route']) && (request()->routeIs($item['route']) || (!empty($item['also']) && request()->routeIs(...$item['also'])))) {
                        $exact = true;
                    }
                }
            }
        @endphp
        @foreach (config('menu') as $group)
            @php
                $items = array_values(array_filter($group['items'], function ($item) {
                    return (is_null($item['permission']) || auth()->user()->can($item['permission'])) && (empty($item['unless']) || !auth()->user()->can($item['unless']));
                }));
                $isActive = function ($item) use ($exact) {
                    if (!Route::has($item['route'])) {
                        return false;
                    }
                    if (request()->routeIs($item['route'])) {
                        return true;
                    }
                    // other pages that belong to this item (the sync status is a tab of POS)
                    if (!empty($item['also']) && request()->routeIs(...$item['also'])) {
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
