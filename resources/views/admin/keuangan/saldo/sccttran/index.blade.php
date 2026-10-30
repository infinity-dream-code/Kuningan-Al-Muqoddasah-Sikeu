@extends('layouts.admin_new')
@section('style')
    <link rel="stylesheet" href="{{asset('main/libs/select2/select2.css')}}">
    <link rel="stylesheet" href="{{asset('main/libs/datatables-bs5/datatables.bootstrap5.css')}}">
    <link rel="stylesheet" href="{{asset('main/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
    <link rel="stylesheet" href="{{asset('main/libs/bootstrap-datepicker/bootstrap-datepicker.css')}}">
@endsection
@section('content')
    <h3 class="page-heading d-flex text-gray-900 fw-bold flex-column justify-content-center my-0">
        {{($dataTitle??($mainTitle??($title??'')))}}
    </h3>
    <ul class="breadcrumb breadcrumb-style2">
        <li class="breadcrumb-item">
            <a href="{{route('admin.index')}}" class="text-hover-primary">Beranda</a>
        </li>
        @if(isset($title))
            <li class="breadcrumb-item">
                {{$title}}
            </li>
        @endif
        @if(isset($mainTitle))
            <li class="breadcrumb-item">
                {{$mainTitle}}
            </li>
        @endif
        @if(isset($dataTitle) && isset($mainTitle) && $mainTitle != $dataTitle)
            <li class="breadcrumb-item active">
                {{$dataTitle}}
            </li>
        @endif
    </ul>

    <div class="card">
        <div class="card-header">
            <div class="row mb-3">
                <h5 class="mb-0 me-2">{{($dataTitle??$mainTitle)}}</h5>
            </div>
        </div>
        <div class="card-body">
            <form id="rekapForm">
                <fieldset class="form-fieldset">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="mb-3 row">
                                <label for="filter_dari_tanggal"
                                       class="col-sm-4 col-form-label text-capitalize form-label">dari
                                    tanggal</label>
                                <div class="col">
                                    <input type="text" class="form-control form-control"
                                           placeholder="dari tanggal" id="filter_dari_tanggal"
                                           name="filter[dari_tanggal]">
                                </div>
                            </div>

                            <div class="mb-3 row">
                                <label for="filter_sampai_tanggal"
                                       class="col-sm-4 col-form-label text-capitalize form-label">sampai
                                    tanggal</label>
                                <div class="col">
                                    <input type="text" class="form-control form-control"
                                           placeholder="sampai tanggal" id="filter_sampai_tanggal"
                                           name="filter[sampai_tanggal]">
                                </div>
                            </div>

                            <div class="mb-3 row">
                                <label for="filter[sekolah]"
                                       class="col-sm-4 col-form-label text-capitalize form-label">sekolah</label>
                                <div class="col">
                                    <select class="form-select" id="filter[sekolah]"
                                            name="filter[sekolah]"
                                            data-control="select2"
                                            data-placeholder="Pilih Sekolah">
                                        <option value="all">Semua</option>
                                        @isset($sekolah)
                                            @foreach($sekolah as $item)
                                                <option value="{{$item->CODE01}}">{{$item->DESC01}}</option>
                                            @endforeach
                                        @else
                                            <option>data kosong</option>
                                        @endisset
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="mb-3 row">
                                <label for="filter[nis]" class="col-sm-4 col-form-label form-label">Nis</label>
                                <div class="col">
                                    <input type="text" class="form-control form-control"
                                           placeholder="Masukkan nis siswa" id="filter[nis]" name="filter[nis]">
                                </div>
                            </div>

                            <div class="mb-3 row">
                                <label for="filter[nama]" class="col-sm-4 col-form-label text-capitalize form-label">nama</label>
                                <div class="col">
                                    <input type="text" class="form-control form-control"
                                           placeholder="Masukkan nama siswa" id="filter[nama]" name="filter[nama]">
                                </div>
                            </div>
                            <div class="mb-3 row d-none">
                                <label class="col-sm-4 col-form-label form-label" for="filter[angkatan]">
                                    Angkatan Siswa
                                </label>
                                <div class="col">
                                    <select class="form-select" id="filter[angkatan]"
                                            name="filter[angkatan]"
                                            data-control="select2"
                                            data-placeholder="Pilih Angkatan Siswa">
                                        <option value="all">Semua</option>
                                        @isset($thn_aka)
                                            @foreach($thn_aka as $item)
                                                <option
                                                    value="{{$item->thn_aka}}">{{$item->thn_aka}}</option>
                                            @endforeach
                                        @else
                                            <option>data kosong</option>
                                        @endisset
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3 row">
                                <label class="col-sm-4 col-form-label form-label" for="filter[metode]">
                                    Metode
                                </label>
                                <div class="col">
                                    <select class="form-select" id="filter[metode]" name="filter[metode]"
                                            data-control="select2" data-placeholder="Pilih Metode">
                                        <option value="all">Semua</option>
                                        @isset($metodeOptions)
                                            @foreach($metodeOptions as $metode)
                                                <option value="{{ $metode }}">{{ $metode }}</option>
                                            @endforeach
                                        @endisset
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3 row">
                                <label class="col-sm-4 col-form-label form-label" for="filter[kelas]">
                                    Kelas
                                </label>
                                <div class="col">
                                    <select class="form-select" id="filter[kelas]" name="filter[kelas]"
                                            data-control="select2" data-placeholder="Pilih Kelas">
                                        <option value="all">Semua</option>
                                        @isset($kelas)
                                            @foreach($kelas as $item)
                                                <option
                                                    value="{{$item->id}}">{{$item->unit}}
                                                    - {{$item->jenjang}} {{$item->kelas}}</option>
                                            @endforeach
                                        @else
                                            <option>data kosong</option>
                                        @endisset
                                    </select>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="row pt-4">
                        <div class="d-flex justify-content-center flex-column flex-md-row justify-content-md-end gap-4">
{{--                            <button type="button" class="btn btn-google-plus btn-print-rekap">--}}
{{--                                <span class="ri-file-pdf-2-line me-2"></span>--}}
{{--                                Cetak PDF--}}
{{--                            </button>--}}
                            <button type="reset" class="btn btn-secondary" disabled>
                                <span class="ri-reset-left-line me-2"></span>
                                Reset
                            </button>
                            <button type="submit" class="btn btn-primary" disabled>
                                <span class="ri-search-line me-2"></span>
                                Cari
                            </button>
                        </div>
                    </div>
                </fieldset>
            </form>
        </div>
        <div class="card-datatable table-responsive text-nowrap">
            <table class="table table-sm table-bordered table-hover"
                   id="main_table">
                <thead class="table-light">

                </thead>
                <tbody>

                </tbody>
            </table>
        </div>
    </div>
@endsection

@section('script')
    <script src="{{asset('main/libs/select2/select2.js')}}"></script>
    <script src="{{asset('main/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
    <script src="{{asset('js/datatableCustom/Datatable-0-4.js')}}?v=20260724-excel-total"></script>
    <script src="{{asset('main/libs/moment/moment.js')}}"></script>
    <script src="{{asset('main/libs/bootstrap-datepicker/bootstrap-datepicker.js')}}"></script>

    <script type="text/javascript">
        const select2 = $(`[data-control='select2']`);

        let dtOptions = {
            tableId: 'main_table',
            formId: 'rekapForm',
            columnUrl: '{{($columnsUrl??null)}}',
            dataUrl: '{{($datasUrl??null)}}',
            dataColumns: [],
            thead: true,
            tfoot: true,
            paging: true,
            searching: true,
            fixedHeader: false,
            pageLength: 10,
            lengthMenu: [10, 25, 50, 75, 100],
            buttons: ["excel", "pdf", "print"],
            excelCurrencyTotal: true,
        };

        document.addEventListener("DOMContentLoaded", function () {
            if (dtOptions.dataUrl && dtOptions.columnUrl) {
                getDT(dtOptions);
                if (dtOptions.formId) {
                    let filterForm = $(`#${dtOptions.formId}`);
                    filterForm.on('submit', function (e) {
                        e.preventDefault();
                        dataReFilter(dtOptions.tableId);
                    });
                    filterForm.on('reset', function (e) {
                        setTimeout(function () {
                            dataReFilter(dtOptions.tableId);
                            const select2InForm = select2.filter(`#${dtOptions.formId} [data-control='select2']`);
                            if (select2InForm.length) {
                                select2InForm.each(function () {
                                    let $this = $(this);
                                    $this.trigger('change');
                                });
                            }
                        }, 0)
                    });
                }
            }

            {{--$(document).on('click', '.btn-print-rekap', function (e) {--}}
            {{--    loadingAlert();--}}
            {{--    let data = $(`#${dtOptions.formId}`).serialize();--}}
            {{--    if (data) {--}}
            {{--        const csrfToken = $('meta[name="csrf-token"]').attr('content')--}}

            {{--        let ajaxOptions = {--}}
            {{--            url: '{{route('admin.keuangan.penerimaan-siswa.data-penerimaan.cetak-rekap')}}',--}}
            {{--            type: 'get',--}}
            {{--            data: data,--}}
            {{--            datatype: 'json',--}}
            {{--            headers: {--}}
            {{--                'X-CSRF-TOKEN': csrfToken,--}}
            {{--            },--}}
            {{--            contentType: false,--}}
            {{--            processData: true,--}}
            {{--            cache: false,--}}
            {{--            xhrFields: {--}}
            {{--                responseType: 'blob'--}}
            {{--            },--}}
            {{--        }--}}
            {{--        $.ajax(ajaxOptions).done(function (response, status, xhr) {--}}
            {{--            try {--}}
            {{--                let blob = new Blob([response], {type: 'application/pdf'});--}}

            {{--                if (typeof window.navigator.msSaveBlob !== 'undefined') {--}}
            {{--                    window.navigator.msSaveBlob(blob, filename);--}}
            {{--                } else {--}}
            {{--                    let URL = window.URL || window.webkitURL;--}}
            {{--                    let previewUrl = URL.createObjectURL(blob);--}}
            {{--                    window.open(previewUrl, '_blank');--}}
            {{--                }--}}

            {{--            } catch (ex) {--}}
            {{--                console.log(ex);--}}
            {{--            }--}}
            {{--            successAlert('File tagihan terbuka pada tab baru');--}}
            {{--        }).fail(function (xhr) {--}}
            {{--            if (xhr.status === 422) {--}}
            {{--                errorAlert('Tidak dapat mencetak')--}}
            {{--            } else if (xhr.status === 419) {--}}
            {{--                errorAlert('Sesi anda telah habis, Silahkan Login Kembali');--}}
            {{--            } else if (xhr.status === 403) {--}}
            {{--                errorAlert('Anda tidak memiliki izin untuk mengakses halaman ini');--}}
            {{--            } else if (xhr.status === 404) {--}}
            {{--                errorAlert('Halaman tidak ditemukan');--}}
            {{--            } else if (xhr.status === 500) {--}}
            {{--                errorAlert('Terjadi kesalahan di server, silahkan coba lagi');--}}
            {{--            } else {--}}
            {{--                errorAlert('Terjadi kesalahan yang tidak diketahui, silahkan coba memuat ulang halaman');--}}
            {{--            }--}}
            {{--        })--}}
            {{--    }else {--}}
            {{--        warningAlert('Silahkan lengkapi filter untuk mencetak rekap penerimaan')--}}
            {{--    }--}}
            {{--});--}}


            if (select2.length) {
                select2.each(function () {
                    let $this = $(this);
                    // select2Focus($this);
                    $this.wrap('<div class="position-relative"></div>').select2({
                        placeholder: 'Select value',
                        dropdownParent: $this.parent(),
                        minimumResultsForSearch: 0
                    });
                });
            }

            const dariTanggal = $('#filter_dari_tanggal');
            const sampaiTanggal = $('#filter_sampai_tanggal');
            const periodeMulai = $('input[name="filter[periode_mulai]"]');
            const periodeAkhir = $('input[name="filter[periode_akhir]"]');

            dariTanggal.datepicker({
                format: "dd-mm-yyyy",
                autoclose: true
            }).on('changeDate', function (e) {
                sampaiTanggal.datepicker('setStartDate', e.date);
            });

            sampaiTanggal.datepicker({
                format: "dd-mm-yyyy",
                autoclose: true
            }).on('changeDate', function (e) {
                dariTanggal.datepicker('setEndDate', e.date);
            });

            periodeMulai.datepicker({
                format: "yyyymm",
                startView: "months",
                minViewMode: "months",
                autoclose: true
            }).on('changeDate', function (e) {
                periodeAkhir.datepicker('setStartDate', e.date);
            });

            periodeAkhir.datepicker({
                format: "yyyymm",
                startView: "months",
                minViewMode: "months",
                autoclose: true
            }).on('changeDate', function (e) {
                periodeMulai.datepicker('setEndDate', e.date);
            });
        });

    </script>

    {!! ($modalLink??'') !!}
@endsection
