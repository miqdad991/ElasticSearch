<aside class="sidebar">
    <div class="sidebar__menu-group">
        <ul class="sidebar_nav">
            <li class="menu-title"><span>Analytics</span></li>
            <li>
                <a href="{{ url('/') }}" class="{{ request()->is('/') ? 'active' : '' }}">
                    <span data-feather="globe" class="nav-icon"></span>
                    <span class="menu-text">Overview</span>
                </a>
            </li>
            <li>
                <a href="{{ url('/select-project') }}" class="{{ request()->is('select-project*') ? 'active' : '' }}">
                    <span data-feather="folder" class="nav-icon"></span>
                    <span class="menu-text">Select Project</span>
                </a>
            </li>
            <li>
                <a href="{{ url('/admin/sync-status') }}" class="{{ request()->is('admin/sync-status*') ? 'active' : '' }}">
                    <span data-feather="activity" class="nav-icon"></span>
                    <span class="menu-text">Sync Status</span>
                </a>
            </li>

            <li class="menu-title"><span>Dashboards</span></li>
            <li>
                <a href="{{ url('/work-orders') }}" class="{{ request()->is('work-orders*') ? 'active' : '' }}">
                    <span data-feather="clipboard" class="nav-icon"></span>
                    <span class="menu-text">Work Orders</span>
                </a>
            </li>
            <li>
                <a href="{{ url('/properties') }}" class="{{ request()->is('properties*') ? 'active' : '' }}">
                    <span data-feather="home" class="nav-icon"></span>
                    <span class="menu-text">Properties</span>
                </a>
            </li>
            <li>
                <a href="{{ url('/assets') }}" class="{{ request()->is('assets*') ? 'active' : '' }}">
                    <span data-feather="package" class="nav-icon"></span>
                    <span class="menu-text">Assets</span>
                </a>
            </li>
            <li>
                <a href="{{ url('/users') }}" class="{{ request()->is('users*') ? 'active' : '' }}">
                    <span data-feather="users" class="nav-icon"></span>
                    <span class="menu-text">Users</span>
                </a>
            </li>
            <li>
                <a href="{{ url('/billing') }}" class="{{ request()->is('billing*') ? 'active' : '' }}">
                    <span data-feather="dollar-sign" class="nav-icon"></span>
                    <span class="menu-text">Billing</span>
                </a>
            </li>
            <li>
                <a href="{{ url('/contracts') }}" class="{{ request()->is('contracts*') ? 'active' : '' }}">
                    <span data-feather="file-text" class="nav-icon"></span>
                    <span class="menu-text">Contracts</span>
                </a>
            </li>

            <li class="menu-title"><span>Management</span></li>
            <li>
                <a href="{{ url('/mc-workorders') }}" class="{{ request()->is('mc-workorders*') ? 'active' : '' }}">
                    <span data-feather="tool" class="nav-icon"></span>
                    <span class="menu-text">MC Workorders</span>
                </a>
            </li>
            <li>
                <a href="{{ url('/mc-following') }}" class="{{ request()->is('mc-following*') ? 'active' : '' }}">
                    <span data-feather="check-circle" class="nav-icon"></span>
                    <span class="menu-text">MC Following</span>
                </a>
            </li>
        </ul>
    </div>
</aside>
