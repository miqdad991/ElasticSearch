<aside class="sidebar">
    <div class="sidebar__menu-group">
        <ul class="sidebar_nav">
            <li class="menu-title"><span>{{ __('nav.analytics') }}</span></li>
            <li>
                <a href="{{ url('/') }}" class="{{ request()->is('/') ? 'active' : '' }}">
                    <span data-feather="globe" class="nav-icon"></span>
                    <span class="menu-text">{{ __('nav.overview') }}</span>
                </a>
            </li>
            <li>
                <a href="{{ url('/select-project') }}" class="{{ request()->is('select-project*') ? 'active' : '' }}">
                    <span data-feather="folder" class="nav-icon"></span>
                    <span class="menu-text">{{ __('nav.select_project') }}</span>
                </a>
            </li>
            <li>
                <a href="{{ url('/admin/sync-status') }}" class="{{ request()->is('admin/sync-status*') ? 'active' : '' }}">
                    <span data-feather="activity" class="nav-icon"></span>
                    <span class="menu-text">{{ __('nav.sync_status') }}</span>
                </a>
            </li>

            <li class="menu-title"><span>{{ __('nav.dashboards') }}</span></li>
            <li>
                <a href="{{ url('/work-orders') }}" class="{{ request()->is('work-orders*') ? 'active' : '' }}">
                    <span data-feather="clipboard" class="nav-icon"></span>
                    <span class="menu-text">{{ __('nav.work_orders') }}</span>
                </a>
            </li>
            <li>
                <a href="{{ url('/properties') }}" class="{{ request()->is('properties*') ? 'active' : '' }}">
                    <span data-feather="home" class="nav-icon"></span>
                    <span class="menu-text">{{ __('nav.properties') }}</span>
                </a>
            </li>
            <li>
                <a href="{{ url('/assets') }}" class="{{ request()->is('assets*') ? 'active' : '' }}">
                    <span data-feather="package" class="nav-icon"></span>
                    <span class="menu-text">{{ __('nav.assets') }}</span>
                </a>
            </li>
            <li>
                <a href="{{ url('/users') }}" class="{{ request()->is('users*') ? 'active' : '' }}">
                    <span data-feather="users" class="nav-icon"></span>
                    <span class="menu-text">{{ __('nav.users') }}</span>
                </a>
            </li>
            <li>
                <a href="{{ url('/billing') }}" class="{{ request()->is('billing*') ? 'active' : '' }}">
                    <span data-feather="dollar-sign" class="nav-icon"></span>
                    <span class="menu-text">{{ __('nav.billing') }}</span>
                </a>
            </li>
            <li>
                <a href="{{ url('/contracts') }}" class="{{ request()->is('contracts*') ? 'active' : '' }}">
                    <span data-feather="file-text" class="nav-icon"></span>
                    <span class="menu-text">{{ __('nav.contracts') }}</span>
                </a>
            </li>

            <li class="menu-title"><span>{{ __('nav.builder') }}</span></li>
            <li>
                <a href="{{ route('dashboard.builder.select') }}" class="{{ request()->is('dashboard-builder*') ? 'active' : '' }}">
                    <span data-feather="sliders" class="nav-icon"></span>
                    <span class="menu-text">{{ __('nav.dashboard_builder') }}</span>
                </a>
            </li>

            <li class="menu-title"><span>{{ __('nav.management') }}</span></li>
            <li>
                <a href="{{ url('/mc-workorders') }}" class="{{ request()->is('mc-workorders*') ? 'active' : '' }}">
                    <span data-feather="tool" class="nav-icon"></span>
                    <span class="menu-text">{{ __('nav.mc_workorders') }}</span>
                </a>
            </li>
            <li>
                <a href="{{ url('/mc-following') }}" class="{{ request()->is('mc-following*') ? 'active' : '' }}">
                    <span data-feather="check-circle" class="nav-icon"></span>
                    <span class="menu-text">{{ __('nav.mc_following') }}</span>
                </a>
            </li>
            <li>
                <a href="{{ url('/mc-dashboard2') }}" class="{{ request()->is('mc-dashboard2*') ? 'active' : '' }}">
                    <span data-feather="grid" class="nav-icon"></span>
                    <span class="menu-text">{{ __('nav.mc_dashboard2') }}</span>
                </a>
            </li>
            <li>
                <a href="{{ url('/mc-following2') }}" class="{{ request()->is('mc-following2*') ? 'active' : '' }}">
                    <span data-feather="layers" class="nav-icon"></span>
                    <span class="menu-text">{{ __('nav.mc_following2') }}</span>
                </a>
            </li>
        </ul>
    </div>
</aside>
