<link rel="stylesheet" href="{{ asset('vendor_assets/css/bootstrap/bootstrap.css') }}">
<link rel="stylesheet" href="{{ asset('vendor_assets/css/fontawesome.css') }}">
<link rel="stylesheet" href="{{ asset('vendor_assets/css/line-awesome.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendor_assets/css/select2.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendor_assets/css/jquery.mCustomScrollbar.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/style.css') }}">
<link rel="stylesheet" href="{{ asset('css/scss/new-style.css') }}">
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
<link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet" />
<style>
    html { height: 100%; overflow: hidden !important; }
    body { height: 100vh !important; overflow-x: hidden !important; overflow-y: auto !important; }
    .sidebar { overflow-x: hidden !important; overflow-y: auto !important; }
    .header-top { height: 75px; }
    .header-top .navbar, .header-top .navbar-left, .header-top .navbar-right {
        height: 75px; display: flex; align-items: center;
    }
    .header-top .navbar-brand img { max-height: 40px; }
    .main-content, .main-content > .contents, .contents, .container-fluid {
        overflow: visible !important; height: auto !important; max-height: none !important;
    }
    .main-content { min-height: 100vh; }
    .contents { padding-bottom: 2rem; }
    .sidebar, .contents, body, html { scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent; }
    .sidebar::-webkit-scrollbar, .contents::-webkit-scrollbar, body::-webkit-scrollbar { width: 6px; height: 6px; }
    .sidebar::-webkit-scrollbar-thumb, .contents::-webkit-scrollbar-thumb, body::-webkit-scrollbar-thumb {
        background: #cbd5e1; border-radius: 3px;
    }
    .sidebar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>
