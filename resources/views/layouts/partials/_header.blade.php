<header class="header-top">
    <nav class="navbar navbar-light">
        <div class="navbar-left">
            <a href="#" class="sidebar-toggle">
                <img class="svg" src="{{ asset('img/svg/bars.svg') }}" alt="menu">
            </a>
            <a class="navbar-brand" href="{{ url('/') }}">
                <img src="{{ asset('img/OSOOL_logo_svg.svg') }}" alt="OpenSearch">
            </a>
        </div>
        <div class="navbar-right">
            <ul class="navbar-right__menu" style="display:flex;gap:.75rem;align-items:center;">
                @if (session('selected_project_id'))
                    <li style="display:flex;align-items:center;gap:.5rem;background:linear-gradient(90deg,#6366f1,#8b5cf6);color:#fff;padding:.3rem .75rem;border-radius:9999px;font-size:12px;font-weight:600;">
                        <span data-feather="briefcase" style="width:14px;height:14px;"></span>
                        <span>{{ session('selected_project_name') }}</span>
                        <form method="post" action="{{ url('/exit-project') }}" style="display:inline;margin:0;">
                            @csrf
                            <button type="submit" style="background:transparent;border:0;color:#fff;font-size:14px;line-height:1;padding:0 0 0 .4rem;cursor:pointer;" title="Exit project">✕</button>
                        </form>
                    </li>
                @endif
                <li><span class="badge badge-success">OpenSearch · Live</span></li>
            </ul>
        </div>
    </nav>
</header>
