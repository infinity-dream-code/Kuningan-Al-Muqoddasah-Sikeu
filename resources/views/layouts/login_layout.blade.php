<!DOCTYPE html>

<html
    lang="en"
    class="light-style layout-wide customizer-hide"
    dir="ltr"
    data-theme="theme-default"
    data-assets-path="{{asset('')}}"
    data-template="vertical-menu-template">
<head>
    <meta charset="utf-8"/>
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0"/>

    <title>@yield('title', config('app.name'))</title>

    <meta name="description" content=""/>

    <!-- Favicon -->
    @include('partials.favicon')
    <!-- Vendors CSS -->
    <link rel="stylesheet" href="{{asset('main/libs/perfect-scrollbar/perfect-scrollbar.css')}}"/>
    <link rel="stylesheet" href="{{asset('main/libs/sweetalert2/sweetalert2.css')}}"/>

    <!-- Page CSS -->
    @yield('style')

    <style>
        [class^="ri-"], [class*=" ri-"] {
            font-size: 18px;
            line-height: 1;
            vertical-align: middle
        }

        .transparent-swal2 .swal2-popup {
            background-color: transparent !important; /* Make dialog background transparent */
            box-shadow: none !important; /* Remove box-shadow */
        }

        .swal2-container.transparent-swal2 {
            background-color: rgba(0, 0, 0, 0.6); /* Adjust backdrop color and transparency */
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }
    </style>
    <!-- Core CSS -->
    {{--    <link rel="stylesheet" href="{{asset('css/demo.css')}}"/>--}}

    <link rel="stylesheet" href="{{asset('main/css/core.css')}}" class="template-customizer-core-css"/>
    <link rel="stylesheet" href="{{asset('main/css/theme-default.css')}}" class="template-customizer-theme-css"/>

    <!-- Helpers -->
    <script src="{{asset('main/js/helpers.js')}}"></script>
    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
    <!--? Template customizer: To hide customizer set displayCustomizer value false in config.js.  -->
    <script src="{{asset('main/js/template-customizer.min.js')}}"></script>
    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
    <script src="{{asset('js/config.js')}}"></script>


</head>

<body>
@yield('content')

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css"/>

<!-- Core JS -->
<!-- build:js assets/vendor/js/core.js -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"
        integrity="sha256-y3ibfOyBqlgBd+GzwFYQEVOZdNJD06HeDXihongBXKs=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/perfect-scrollbar@1.5.6/dist/perfect-scrollbar.min.js"
        integrity="sha256-B69LaJOkADtiChnrAMKFvAyqbzM3Thpr6EyGtViOFG8=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
        integrity="sha256-whL0tQWoY1Ku1iskqPFvmZ+CHsvmRWx/PIoEvIeWh4I=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/hammerjs@2.0.8/hammer.min.js"
        integrity="sha256-eVNjHw5UeU0jUqPPpZHAkU1z4U+QFBBY488WvueTm88=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.22.0/dist/sweetalert2.all.min.js"
        integrity="sha256-WjwoxFTbs4JzyDmrHgK4VgR+Dz7he8HYwCbocAlNn9k=" crossorigin="anonymous"></script>

<script src="{{asset('main/js/menu.js')}}"></script>
<script src="{{asset('js/main.js')}}"></script>

<script src="{{asset('js/alerts.js')}}"></script>
@yield('script')

<script>
    @if(session()->has('alert'))
        {!! session('alert') !!}
    @endif

</script>
<script>
    function updateClock() {
        let now = new Date();
        let hours = now.getHours();
        let minutes = now.getMinutes();
        let seconds = now.getSeconds();

        // document.getElementById('clock').innerHTML = hours.toString().padStart(2, '0') + ':' + minutes.toString().padStart(2, '0') + ':' + seconds.toString().padStart(2, '0');
    }

    // updateClock();
    // setInterval(updateClock, 1000);
</script>
<script>
    // Keep-Alive & CSRF Sync for Login Page
    window.pingKeepAlive = function (callback) {
        if (typeof $ === 'undefined') return;

        $.ajax({
            url: '{{ route('keep-alive') }}',
            type: 'GET',
            cache: false,
            dataType: 'json',
            success: function (data) {
                if (data && data.authenticated) {
                    window.location.replace('{{ route('login') }}');
                    return;
                }
                if (data && data.csrf_token) {
                    $('input[name="_token"]').val(data.csrf_token);
                }
                if (typeof callback === 'function') {
                    callback(data);
                }
            },
            error: function () {
                // Silently ignore ping errors
            }
        });
    };

    // Ping every 3 minutes while on login page
    setInterval(window.pingKeepAlive, 3 * 60 * 1000);

    // Refresh immediately when returning to tab or back from bfcache
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            window.pingKeepAlive();
        }
    });
    window.addEventListener('focus', function () {
        window.pingKeepAlive();
    });
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            window.location.reload();
        } else {
            window.pingKeepAlive();
        }
    });

    // Refresh on user interaction on login form
    let lastLoginPing = Date.now();
    function onLoginInteraction() {
        const now = Date.now();
        if (now - lastLoginPing > 3 * 60 * 1000) {
            lastLoginPing = now;
            window.pingKeepAlive();
        }
    }
    ['click', 'keydown'].forEach(function (evt) {
        window.addEventListener(evt, onLoginInteraction, { passive: true });
    });
</script>

</body>
</html>
