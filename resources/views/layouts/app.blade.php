<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="description" content="" />
        <meta name="keywords" content="">
        <meta name="author" content="colorlib" />
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', 'Admindek')</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <!-- Favicon icon -->
        <link rel="icon" href="{{ asset('images/favicon.ico') }}" type="image/x-icon">
        <!-- Google font-->
        <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700,800" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css?family=Quicksand:500,700" rel="stylesheet">
        <!-- Required Fremwork -->
        <link rel="stylesheet" type="text/css" href="{{ asset('bower_components/bootstrap/css/bootstrap.min.css') }}">
        <!-- waves.css -->
        <link rel="stylesheet" href="{{ asset('pages/waves/css/waves.min.css') }}" type="text/css" media="all">
        <!-- feather icon -->
        <link rel="stylesheet" type="text/css" href="{{ asset('icon/feather/css/feather.css') }}">
        <!-- font-awesome-n -->
        <link rel="stylesheet" type="text/css" href="{{ asset('css/font-awesome-n.min.css') }}">
        <!-- Chartlist chart css -->
        <link rel="stylesheet" href="{{ asset('bower_components/chartist/css/chartist.css') }}" type="text/css" media="all">
        <!-- Style.css -->
        <link rel="stylesheet" type="text/css" href="{{ asset('css/style.css') }}">
        <link rel="stylesheet" type="text/css" href="{{ asset('css/widget.css' )}}">
        <!-- Custom css -->
        <link rel="stylesheet" type="text/css" href="{{ asset('css/custom.css') }}">
        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('styles')
    </head>
    <body class="font-sans antialiased">
        <!-- [ Pre-loader ] start -->
        <div class="loader-bg">
            <div class="loader-bar"></div>
        </div>
        <!-- [ Pre-loader ] end -->
        <div id="pcoded" class="pcoded">
            <div class="pcoded-overlay-box"></div>
            <div class="pcoded-container navbar-wrapper">
                @include('partials.header')
                <div class="pcoded-main-container">
                    <div class="pcoded-wrapper">
                        <!-- [ navigation menu ] start -->
                        @include('layouts.navigation')
                        <!-- [ navigation menu ] end -->
                        <div class="pcoded-content px-4 py-4">
                            @yield('content')
                        </div>
                    </div>
                </div>                
            </div>
        </div>
        <script type="text/javascript" src="{{ asset('bower_components/jquery/js/jquery.min.js') }}"></script>
        <script type="text/javascript" src="{{ asset('bower_components/jquery-ui/js/jquery-ui.min.js') }}"></script>
        <script type="text/javascript" src="{{ asset('bower_components/popper.js/js/popper.min.js') }}"></script>
        <script type="text/javascript" src="{{ asset('bower_components/bootstrap/js/bootstrap.min.js') }}"></script>
        <!-- waves js -->
        <script src="{{ asset('pages/waves/js/waves.min.js') }}"></script>
        <!-- jquery slimscroll js -->
        <script type="text/javascript" src="{{ asset('bower_components/jquery-slimscroll/js/jquery.slimscroll.js') }}"></script>
        <!-- Float Chart js -->
        <!--script src="{{ asset('pages/chart/float/jquery.flot.js') }}"></script>
        <script src="{{ asset('pages/chart/float/jquery.flot.categories.js') }}"></script>
        <script src="{{ asset('pages/chart/float/curvedLines.js') }}"></script>
        <script src="{{ asset('pages/chart/float/jquery.flot.tooltip.min.js') }}"></script-->
        <!-- Chartlist charts -->
        <!--script src="{{ asset('bower_components/chartist/js/chartist.js') }}"></script-->
        <!-- amchart js -->
        <!--script src="{{ asset('pages/widget/amchart/amcharts.js') }}"></script>
        <script src="{{ asset('pages/widget/amchart/serial.js') }}"></script>
        <script src="{{ asset('pages/widget/amchart/light.js') }}"></script-->
        <!-- Custom js -->
        <script src="{{ asset('js/pcoded.min.js') }}"></script>
        <script src="{{ asset('js/vertical/vertical-layout.min.js') }}"></script>
        <!--script type="text/javascript" src="{{ asset('pages/dashboard/custom-dashboard.min.js') }}"></script-->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert/dist/sweetalert.min.js"></script>
        <script type="text/javascript" src="{{ asset('js/script.min.js') }}"></script>
        @stack('scripts')
    </body>
</html>
