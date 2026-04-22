<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'OpenSearch Dashboard')</title>

    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    @include('layouts.partials._styles')
    <style>@yield('styles')</style>

    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon/favicon-16x16.png') }}">
</head>

<body class="layout-light side-menu">
    @include('layouts.partials._header')

    <main class="main-content">
        @include('layouts.partials._aside')

        <div class="contents">
            <div class="container-fluid">
                @yield('content')
            </div>
        </div>

        @include('layouts.partials._footer')
    </main>

    @include('layouts.partials._scripts')
    @yield('scripts')
</body>
</html>
