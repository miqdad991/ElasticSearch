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

                {{-- Language switcher --}}
                <li class="nav-flag-select">
                    @if (app()->isLocale('en'))
                        <a href="{{ route('lang.switch', 'ar') }}" class="fs-16">العربية</a>
                    @elseif (app()->isLocale('ar'))
                        <a href="{{ route('lang.switch', 'en') }}" class="fs-16">English</a>
                    @endif
                </li>

            </ul>
        </div>
    </nav>
</header>
