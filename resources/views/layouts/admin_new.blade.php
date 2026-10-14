<!DOCTYPE html>
<html
    lang="en"
    class="light-style layout-navbar-fixed layout-menu-fixed layout-compact"
    dir="ltr"
    data-theme="theme-default"
    data-assets-path="{{asset('')}}"
    data-template="vertical-menu-template">
<head>
    <meta charset="utf-8"/>
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0"/>

    <title>@yield('title', config('app.name')) - {{ config('app.name') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}"/>
    <meta name="description" content="Core system ICT "/>

    <!-- Favicon -->
    @include('partials.favicon')

    <!-- Icons -->

    <!-- Menu waves for no-customizer fix -->
    {{--    <link rel="stylesheet" href="{{asset('main/libs/node-waves/node-waves.css')}}"/>--}}

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="{{asset('main/libs/perfect-scrollbar/perfect-scrollbar.css')}}"/>
    <link rel="stylesheet" href="{{asset('main/libs/sweetalert2/sweetalert2.css')}}"/>
    <link rel="stylesheet" href="{{asset('main/libs/spinkit/spinkit.css')}}"/>

    @hasSection('filepond')
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/filepond@4.32.7/dist/filepond.min.css"
              integrity="sha256-R/TKiFR8YXiqvCSFSm3ek/rIjgEoFS5PpaAMkv/brg4=" crossorigin="anonymous">

        <link rel="stylesheet" href="{{asset('libs/filepond/dist/custom.css')}}">
    @endif
    @php use Carbon\Carbon; @endphp

    @hasSection('datatable')
        <link rel="stylesheet" href="{{asset('main/libs/datatables-bs5/datatables.bootstrap5.css')}}?v=20260610-row-border">
        @hasSection('datatable-responsive')
            <link rel="stylesheet" href="{{asset('main/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
        @endif
        @hasSection('datatable-buttons')
            <link rel="stylesheet" href="{{asset('main/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
        @endif
    @endif

    @hasSection('bootstrap-datepicker')
        <link rel="stylesheet" href="{{asset('main/libs/bootstrap-datepicker/bootstrap-datepicker.css')}}">
    @endif

    @hasSection('bootstrap-daterangepicker')
        <link rel="stylesheet" href="{{asset('main/libs/bootstrap-daterangepicker/bootstrap-daterangepicker.css')}}">
    @endif

    @hasSection('select2')
        <link rel="stylesheet" href="{{asset('main/libs/select2/select2.css')}}">
        <link rel="stylesheet" href="{{asset('main/libs/select2/select2-bootstrap.css')}}">
    @endif

    <!-- Page CSS -->
    @yield('style')

    <!-- Core CSS -->
    {{--    <link rel="stylesheet" href="{{asset('css/demo.css')}}"/>--}}
    <link rel="stylesheet" href="{{asset('main/css/status.min.css')}}"/>

    <link rel="stylesheet" href="{{asset('main/css/core.min.css')}}" class="template-customizer-core-css"/>
    <link rel="stylesheet" href="{{asset('main/css/theme-default.css')}}" class="template-customizer-theme-css"/>

    <style>
        [class^="ri-"], [class*=" ri-"] {
            font-size: 18px;
            line-height: 1;
            vertical-align: middle
        }

        .form-fieldset {
            padding: 1rem;
            margin-bottom: 1rem;
            background: var(--bs-body-bg);
            border: var(--bs-border-width) var(--bs-border-style) var(--bs-border-color);
            border-radius: 0.625rem;
            /*box-shadow: 0 .25rem .875rem 0 rgba(16, 17, 33, .26);*/
        }

        .modal-blur {
            -webkit-backdrop-filter: blur(4px);
            backdrop-filter: blur(4px);
        }

        .transparent-swal2 .swal2-popup {
            background-color: transparent !important; /* Make dialog background transparent */
            box-shadow: none !important; /* Remove box-shadow */
        }

        .swal2-container.transparent-swal2 {
            background-color: rgba(48, 51, 78, .3) !important;
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }

        .table_dt_no {
            width: 25px !important;
            max-width: 25px !important;
            min-width: 25px !important;
        }
    </style>
    <!-- Helpers -->
    <script src="{{asset('main/js/helpers.js')}}"></script>
    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
    <!--? Template customizer: To hide customizer set displayCustomizer value false in config.js.  -->
    <script src="{{asset('main/js/template-customizer.min.js')}}"></script>
    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
    <script src="{{asset('js/config.js')}}"></script>

</head>

<body>
<!-- Layout wrapper -->
<div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
        <!-- Menu -->
        @include('layouts.adminMenu_new')

        <!-- / Menu -->

        <!-- Layout container -->
        <div class="layout-page">
            <!-- Navbar -->

            <nav
                class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
                id="layout-navbar">
                <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                    <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                        <i class="ri-menu-fill ri-xl"></i>
                    </a>
                </div>

                <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                    <div class="navbar-nav align-items-center d-xs-none">
                        <span class="d-none d-md-inline-block">
                            <span>{{config('app.name')}} - </span>
                            <span>{{ Carbon::now()->locale('id_ID')->translatedFormat('l, d F Y') }} - </span>
                            <span id="clock"></span>
                        </span>
                    </div>

                    <ul class="navbar-nav flex-row align-items-center ms-auto">

                        <!-- Style Switcher -->
                        <li class="nav-item dropdown-style-switcher dropdown me-1 me-xl-0">
                            <a class="nav-link btn btn-text-secondary rounded-pill btn-icon dropdown-toggle hide-arrow"
                               href="javascript:void(0);" data-bs-toggle="dropdown">
                                <i class='ri-xl'></i>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end dropdown-styles">
                                <li>
                                    <a class="dropdown-item" href="javascript:void(0);" data-theme="light">
                                        <span class="align-middle"><i class='ri-sun-line ri-xl me-3'></i>Terang</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="javascript:void(0);" data-theme="dark">
                                        <span class="align-middle"><i
                                                class="ri-moon-clear-line ri-xl me-3"></i>Gelap</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="javascript:void(0);" data-theme="system">
                                        <span class="align-middle"><i
                                                class="ri-computer-line ri-xl me-3"></i>Sistem</span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <!-- / Style Switcher-->

                        <!-- User -->
                        <li class="nav-item navbar-dropdown dropdown-user dropdown">
                            <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);"
                               data-bs-toggle="dropdown">
                                <div class="avatar avatar-online">
                                    <img src="{{asset(config('app.logo'))}}" alt class="rounded-circle">
                                </div>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="#">
                                        <div class="d-flex">
                                            <div class="flex-shrink-0 me-2">
                                                <div class="avatar avatar-online">
                                                    <img src="{{asset(config('app.logo'))}}" alt
                                                         class="rounded-circle">
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <span class="fw-medium d-block small">{{config('app.name')}}</span>
                                                <small class="text-muted">admin</small>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                                <li>
                                    <div class="dropdown-divider"></div>
                                </li>
                                {{--                                <li>--}}
                                {{--                                    <a class="dropdown-item" href="#">--}}
                                {{--                                        <i class="ri-user-3-line ri-xl me-3"></i><span--}}
                                {{--                                            class="align-middle">Profil</span>--}}
                                {{--                                    </a>--}}
                                {{--                                </li>--}}
                                <li>
                                    <div class="d-grid px-4 pt-2 pb-1">
                                        <a href="{{route('logout')}}" class="btn btn-sm btn-danger d-flex"  onclick="event.preventDefault();
                                            document.getElementById('logout-form').submit();">
                                            <small class="align-middle">Logout</small>
                                            <i class="ri-logout-box-r-line ms-2 ri-16px"></i>
                                        </a>

                                        <form id="logout-form" action="{{ route('logout') }}" method="POST"
                                              class="d-none">
                                            @csrf
                                        </form>
                                    </div>
                                </li>
                            </ul>
                        </li>                        <!--/ User -->
                    </ul>
                </div>
            </nav>

            <!-- / Navbar -->

            <!-- Content wrapper -->
            <div class="content-wrapper">
                <!-- Content -->
                <div class="flex-grow-1 container-p-y container-fluid">
                    @yield('content')
                </div>
                <!-- / Content -->

                <!-- Footer -->
                <footer class="content-footer footer bg-footer-theme">
                    <div class="container-xxl">
                        <div
                            class="footer-container d-flex align-items-center justify-content-center py-3 flex-md-row flex-column">
                            <div class="mb-2 mb-md-0 text-center">
                                Â©
                                <script>
                                    document.write(new Date().getFullYear());
                                </script>
                                , made with <span class="text-danger"><i class="tf-icons ri ri-heart-3-fill"></i></span>
                                by
                                <a href="{{route('admin.index')}}" target="_blank"
                                   class="footer-link fw-medium">PT. Inovasi Cipta Teknologi</a>
                            </div>
                        </div>
                    </div>
                </footer>
                <!-- / Footer -->

                <button type="button" href="#" id="backToTopBtn" class="btn btn-lg px-3 back-to-top " role="button"
                        aria-label="Ke Atas" title="Ke Atas">
                    <i class="ri ri-arrow-up-line ri-2x"></i>
                </button>

                <div class="content-backdrop fade"></div>
            </div>
            <!-- Content wrapper -->
        </div>
        <!-- / Layout page -->
    </div>

    <!-- Drag Target Area To SlideIn Menu On Small Screens -->
    <div class="drag-target"></div>
</div>
<!-- / Layout wrapper -->

<!-- Core JS -->
<!-- build:js assets/vendor/js/core.js -->
{{--<script src="{{asset('main/libs/jquery/jquery.js')}}"></script>--}}
{{--<script src="{{asset('main/js/bootstrap.js')}}"></script>--}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"
        integrity="sha256-y3ibfOyBqlgBd+GzwFYQEVOZdNJD06HeDXihongBXKs=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/perfect-scrollbar@1.5.6/dist/perfect-scrollbar.min.js"
        integrity="sha256-B69LaJOkADtiChnrAMKFvAyqbzM3Thpr6EyGtViOFG8=" crossorigin="anonymous"></script>
<script src="{{asset('main/js/menu.js')}}"></script>
<!-- endbuild -->

<!-- Vendors JS -->
{{--<script src="{{asset('main/libs/sweetalert2/sweetalert2.js')}}"></script>--}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.22.0/dist/sweetalert2.all.min.js"
        integrity="sha256-WjwoxFTbs4JzyDmrHgK4VgR+Dz7he8HYwCbocAlNn9k=" crossorigin="anonymous"></script>
<!-- Main JS -->
<script src="{{asset('js/main.js')}}"></script>

<script src="{{asset('js/alerts.js')}}?v=20261010-no500alert"></script>
<script>
    window.APP_VA_OPEN = @json((string) config('app.va_open', '777723'));
    window.APP_VA_CLOSE = @json((string) config('app.va_close', '777723'));
    window.APP_VA_CLOSE_ENABLED = @json((bool) config('app.va_close_enabled', false));
    window.APP_VA_PREFIX = window.APP_VA_OPEN;
</script>
<script src="{{asset('js/va-format.js')}}?v=20260827-multiva"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css"/>

<style>
    .back-to-top {
        display: none !important;
        color: #30334e;
        background-color: rgba(231, 231, 255, 0.8);
        -webkit-backdrop-filter: saturate(180%) blur(6px);
        backdrop-filter: saturate(180%) blur(6px);
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: auto;
        transition: opacity 0.3s ease-in-out !important;
    }

    .dark-style .back-to-top {
        color: #e7e7ff;
        background-color: rgba(48, 51, 78, .8)
    }

</style>
<!-- Page JS -->

@if(session()->has('alert'))
    <script>
        {!! session('alert') !!}
    </script>
@endif

@if (session('error'))
    <script>
        console.log('error');
        errorAlert('{{ session('error') }}');
    </script>
@endif
<script>
    function updateClock() {
        let now = new Date();
        let hours = now.getHours();
        let minutes = now.getMinutes();
        let seconds = now.getSeconds();

        document.getElementById('clock').innerHTML = hours.toString().padStart(2, '0') + ':' + minutes.toString().padStart(2, '0') + ':' + seconds.toString().padStart(2, '0');
    }

    updateClock();
    setInterval(updateClock, 1000);

    function applyCsrfToken(token) {
        if (!token) {
            return;
        }
        $('meta[name="csrf-token"]').attr('content', token);
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': token
            }
        });
        $('input[name="_token"]').val(token);
    }

    let keepAliveInFlight = false;
    let keepAliveCallbacks = [];

    window.pingKeepAlive = function (callback) {
        if (typeof $ === 'undefined') return;
        if (typeof callback === 'function') {
            keepAliveCallbacks.push(callback);
        }
        if (keepAliveInFlight) {
            return;
        }

        keepAliveInFlight = true;
        $.ajax({
            url: '{{ route('keep-alive') }}',
            type: 'GET',
            cache: false,
            dataType: 'json',
            success: function (data) {
                if (data && data.csrf_token) {
                    applyCsrfToken(data.csrf_token);
                }
                const queued = keepAliveCallbacks.splice(0);
                queued.forEach(function (cb) { cb(data); });
            },
            error: function () {
                const queued = keepAliveCallbacks.splice(0);
                queued.forEach(function (cb) { cb(null); });
            },
            complete: function () {
                keepAliveInFlight = false;
            }
        });
    };

    setInterval(window.pingKeepAlive, 3 * 60 * 1000);

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            window.pingKeepAlive();
        }
    });
    window.addEventListener('focus', function () {
        window.pingKeepAlive();
    });
    window.addEventListener('pageshow', function () {
        window.pingKeepAlive();
    });

    let lastActivityPing = Date.now();
    function onUserActivity() {
        const now = Date.now();
        if (now - lastActivityPing > 2 * 60 * 1000) {
            lastActivityPing = now;
            window.pingKeepAlive();
        }
    }
    ['click', 'keydown', 'mousemove', 'scroll', 'touchstart'].forEach(function (evt) {
        window.addEventListener(evt, onUserActivity, { passive: true });
    });

    const originalFetch = window.fetch.bind(window);
    window.fetch = function (input, init) {
        init = init || {};
        return originalFetch(input, init).then(function (response) {
            if (response.status !== 419 || init._retried) {
                return response;
            }

            return new Promise(function (resolve) {
                window.pingKeepAlive(function (data) {
                    if (!data || !data.csrf_token) {
                        resolve(response);
                        return;
                    }

                    const retryInit = Object.assign({}, init, { _retried: true });
                    if (retryInit.headers instanceof Headers) {
                        retryInit.headers.set('X-CSRF-TOKEN', data.csrf_token);
                    } else {
                        retryInit.headers = Object.assign({}, retryInit.headers || {}, {
                            'X-CSRF-TOKEN': data.csrf_token
                        });
                    }
                    resolve(originalFetch(input, retryInit));
                });
            });
        });
    };

    document.addEventListener('DOMContentLoaded', function () {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        $(document).ajaxError(function (event, jqXHR, ajaxSettings) {
            const status = jqXHR.status;
            const canRetryAuth = (status === 419 || status === 401) && !ajaxSettings._retried;
            const canRetryServer = (status === 500 || status === 503) && !ajaxSettings._retried500
                && String(ajaxSettings.type || 'GET').toUpperCase() === 'GET';
            if (!canRetryAuth && !canRetryServer) {
                return;
            }
            // Silent recover once. No login redirect, no "Internal Server Error" spam.
            if (canRetryAuth) {
                ajaxSettings._retried = true;
            }
            if (canRetryServer) {
                ajaxSettings._retried500 = true;
            }
            window.pingKeepAlive(function (data) {
                if (canRetryAuth) {
                    if (!data || !data.csrf_token) {
                        return;
                    }
                    applyCsrfToken(data.csrf_token);
                    ajaxSettings.headers = ajaxSettings.headers || {};
                    ajaxSettings.headers['X-CSRF-TOKEN'] = data.csrf_token;
                    if (typeof ajaxSettings.data === 'string' && ajaxSettings.data.indexOf('_token=') !== -1) {
                        ajaxSettings.data = ajaxSettings.data.replace(/_token=[^&]*/, '_token=' + encodeURIComponent(data.csrf_token));
                    } else if (ajaxSettings.data && typeof FormData !== 'undefined' && ajaxSettings.data instanceof FormData) {
                        ajaxSettings.data.set('_token', data.csrf_token);
                    } else if (ajaxSettings.data && typeof ajaxSettings.data === 'object') {
                        ajaxSettings.data._token = data.csrf_token;
                    }
                }
                $.ajax(ajaxSettings);
            });
        });

        let backToTopButton = document.getElementById('backToTopBtn');

        window.addEventListener('scroll', function () {
            if (window.scrollY > 200) {
                backToTopButton.style.setProperty('display', 'block', 'important');
            } else {
                backToTopButton.style.setProperty('display', 'none', 'important');
            }
        });

        backToTopButton.addEventListener('click', function (e) {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: "smooth"
            });
        });
    })
</script>

@hasSection('errorInputHelper')
    <script src="{{asset('js/helper/errorInputHelper.min.js')}}"></script>
@endif
@hasSection('momentjs')
    <script src="https://cdn.jsdelivr.net/npm/moment@2.30.1/moment.min.js"></script>
@endif

@hasSection('bootstrap-datepicker')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.10.0/dist/js/bootstrap-datepicker.min.js"
            integrity="sha256-iZp9dyOMJKPFdn1UMra9ZMhPZAlSGZUzdhqqEgijE+Q=" crossorigin="anonymous" defer></script>
@endif

@hasSection('bootstrap-daterangepicker')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-daterangepicker@3.1.0/daterangepicker.min.js" defer></script>
@endif

@hasSection('select2')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"
            integrity="sha256-AFAYEOkzB6iIKnTYZOdUf9FFje6lOTYdwRJKwTN5mks=" crossorigin="anonymous" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/id.js" defer></script>
@endif

@hasSection('datatable')
{{--    <script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.11/js/jquery.dataTables.min.js"--}}
{{--            integrity="sha256-ozFG+tjHIo3E3JAEPj5Q1Rzq2LImeurDKwqPO+ilK4Y=" crossorigin="anonymous" defer></script>--}}
{{--    <script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.11/js/dataTables.bootstrap5.min.js"--}}
{{--            integrity="sha256-3iXHrfSd4xzI1YyrooF0jG4OVwGiSAoU1+WdYwEwYZk=" crossorigin="anonymous" defer></script>--}}
    <script src="{{asset('main/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
    <script src="{{asset('js/datatableCustom/Datatable-0-4.min.js')}}?v=20260610-row-border" defer></script>

{{--    @hasSection('datatable-responsive')--}}
{{--        <script src="https://cdn.jsdelivr.net/npm/datatables.net-responsive@2.5.1/js/dataTables.responsive.min.js"--}}
{{--                integrity="sha256-LLmmrr9hYXrjHj5w9Q2Asxvom2ErkK3bWcnZ3bdQ5lE=" crossorigin="anonymous" defer></script>--}}
{{--        <script src="https://cdn.jsdelivr.net/npm/datatables.net-responsive-bs5@2.5.1/js/responsive.bootstrap5.min.js"--}}
{{--                integrity="sha256-fxJmiDt8K09elieA/J28vsEvSsmFgGUvoLsEgH/uUx4=" crossorigin="anonymous" defer></script>--}}
{{--    @endif--}}

{{--    @hasSection('datatable-select')--}}
{{--        <script src="https://cdn.jsdelivr.net/npm/datatables.net-select@1.6.2/js/dataTables.select.min.js"--}}
{{--                integrity="sha256-EahoU/AaV9Q11O9bRdXQ9r/qUi/I6UhMQte2hAVAPzg=" crossorigin="anonymous" defer></script>--}}
{{--        <script src="https://cdn.jsdelivr.net/npm/datatables.net-select-bs5@1.6.2/js/select.bootstrap5.min.js"--}}
{{--                integrity="sha256-Aha9/czvjvbiHnjkH+WVatxmHGPKsSffrkpk1f9Kw2A=" crossorigin="anonymous" defer></script>--}}
{{--        <script src="https://cdn.jsdelivr.net/npm/jquery-datatables-checkboxes@1.2.14/js/dataTables.checkboxes.min.js"--}}
{{--                integrity="sha256-0fpVa2zI7evUwBEHoZUbn4GsMQtXIvE4TXUMPz3hA50=" crossorigin="anonymous" defer></script>--}}
{{--    @endif--}}

{{--    @hasSection('datatable-buttons')--}}
{{--        <script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons@2.3.6/js/dataTables.buttons.min.js"--}}
{{--                integrity="sha256-XOiR0CeeG5rD5uACHdDcx3mjlFKSo1brzobDCeAdv18=" crossorigin="anonymous" defer></script>--}}
{{--        <script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons-bs5@2.3.6/js/buttons.bootstrap5.min.js"--}}
{{--                integrity="sha256-6gtqbO3KDs9qfc4P5XqrGZzqbTVfKZ9rZqug7qdM4vs=" crossorigin="anonymous" defer></script>--}}
{{--        <script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"--}}
{{--                integrity="sha256-rMfkFFWoB2W1/Zx+4bgHim0WC7vKRVrq6FTeZclH1Z4=" crossorigin="anonymous" defer></script>--}}
{{--        <script src="https://cdn.jsdelivr.net/npm/pdfmake@0.2.20/build/pdfmake.min.js"--}}
{{--                integrity="sha256-wv06XiTbCWyRObt9Jmdl3kM7C4ZtA7o1bC702a4aU7c=" crossorigin="anonymous" defer></script>--}}
{{--        <link rel="stylesheet" href="{{asset('main/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">--}}
{{--    @endif--}}

{{--    @hasSection('datatable-row-group')--}}
{{--        <script src="https://cdn.jsdelivr.net/npm/datatables.net-rowgroup@1.3.1/js/dataTables.rowGroup.min.js"--}}
{{--                integrity="sha256-II17PfUGfmM5g4TNj100OnoDitu3mRE7dkNB9d5lNKg=" crossorigin="anonymous" defer></script>--}}
{{--        <script src="https://cdn.jsdelivr.net/npm/datatables.net-rowgroup-bs5@1.3.1/js/rowGroup.bootstrap5.min.js"--}}
{{--                integrity="sha256-tTIgAuWvIg+N7pTyrXIDHiLLYxkMyprz91c+IhFsc3Y=" crossorigin="anonymous" defer></script>--}}
{{--    @endif--}}

{{--    @hasSection('datatable-fixed-columns')--}}
{{--        <script src="https://cdn.jsdelivr.net/npm/datatables.net-fixedcolumns@4.3.1/js/dataTables.fixedColumns.min.js"--}}
{{--                integrity="sha256-9g/RKjIQyATtXtSK/jdoSIsGcLE1vhZw/yRxVMrHvAI=" crossorigin="anonymous" defer></script>--}}
{{--        <script--}}
{{--            src="https://cdn.jsdelivr.net/npm/datatables.net-fixedcolumns-bs5@4.3.1/js/fixedColumns.bootstrap5.min.js"--}}
{{--            integrity="sha256-wFjO7w88Qc9nHptwSqp504v5zmpgLa16oozFHM9/9R0=" crossorigin="anonymous" defer></script>--}}
{{--    @endif--}}
@endif

@hasSection('filepond')
    <script
        src="https://cdn.jsdelivr.net/npm/filepond-plugin-file-validate-type@1.2.9/dist/filepond-plugin-file-validate-type.min.js"
        integrity="sha256-iNzotay9f+s57lx/JEVaRWbOsGGXZlPPZlSQ34OHx+A=" crossorigin="anonymous"></script>

    <script
        src="https://cdn.jsdelivr.net/npm/filepond-plugin-file-validate-size@2.2.8/dist/filepond-plugin-file-validate-size.min.js"
        integrity="sha256-XaHceW4NII52xG26aOw+77JG8yEk95GAZs8h7ZAv1xo=" crossorigin="anonymous"></script>

    <script src="https://cdn.jsdelivr.net/npm/filepond@4.32.7/dist/filepond.min.js"
            integrity="sha256-BRICH2AsAT7Vx36hU5PcHTuKBbusAU4j6fge+/dHO1M=" crossorigin="anonymous" defer></script>


    <script type="text/javascript" defer>
        let filePondElements = [];

        function initializeFilePond(id) {
            let inputElement = document.querySelector('input#' + id);
            filePondElements[id] = FilePond.create(inputElement, {
                credits: null,
                allowFileEncode: false,
                required: false,
                storeAsFile: true,
                acceptedFileTypes: [
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/wps-office.xlsx',
                    'application/wps-office.xls'
                ],
                labelIdle: 'Klik untuk membuka file manager, atau seret file ke dalam box ini.',
                allowFileTypeValidation: true,
                allowFileSizeValidation: true,
                labelMaxFileSizeExceeded: 'File terlalu besar',
                labelMaxFileSize: 'Ukuran maksimal file: {filesize}',
                labelFileTypeNotAllowed: 'Format file salah!',
                fileValidateTypeLabelExpectedTypes: 'file harus berformat .xls atau .xlsx',
                maxFileSize: 1024000,
            });
        }

        function resetFilePond(id) {
            filePondElements[id].removeFiles();
        }

        document.addEventListener('DOMContentLoaded', function (e) {
            FilePond.registerPlugin(
                FilePondPluginFileValidateType,
                FilePondPluginFileValidateSize,
            )
        });
    </script>
@endif
@yield('script')
</body>
</html>
