@extends('layouts.admin_new')
@section('style')
    <link rel="stylesheet" href="{{asset('main/libs/select2/select2.css')}}">
    <link rel="stylesheet" href="{{asset('main/libs/bootstrap-datepicker/bootstrap-datepicker.css')}}">
    <link rel="stylesheet" href="{{asset('main/libs/datatables-bs5/datatables.bootstrap5.css')}}">
    <link rel="stylesheet" href="{{asset('main/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
    <style>
        .siswa-search-wrap .select2-container { width: 100% !important; }
        .select2-container--open .select2-dropdown { z-index: 9999; }
    </style>
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

    <form class="mainForm" id="bayar-form" action="#">
        <div class="card mb-6">
            <div class="card-header header-elements">
                <h5 class="mb-0 me-2">{{$mainTitle}}</h5>
            </div>
            <div class="card-body py-0">
                <div class="row">
                    <div class="col-12">
                        <div class="mb-5 siswa-search-wrap">
                            <label class="required form-label" for="siswa">
                                Siswa
                            </label>
                            <select class="form-select" id="siswa" name="siswa"
                                    data-control="select2-ajax-siswa"
                                    data-placeholder="Masukkan NIS / No. Pendaftaran / Nama Siswa">
                            </select>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="mb-5">
                            <label class="form-label" for="tahun_pelajaran">
                                Tahun Pelajaran
                            </label>
                            <select class="form-select" id="tahun_pelajaran"
                                    name="filter[tahun_pelajaran]"
                                    data-control="select2"
                                    data-placeholder="Pilih Tahun Pelajaran">
                                <option value="all">Semua</option>
                                @isset($thn_aka)
                                    @foreach($thn_aka as $item)
                                        <option value="{{$item->thn_aka}}">{{$item->thn_aka}}</option>
                                    @endforeach
                                @else
                                    <option>data kosong</option>
                                @endisset
                            </select>
                        </div>
                    </div>
                    <div class="{{ \App\Support\MultiVa::closeEnabled() ? 'col-md-6' : 'col-md-12' }}">
                        <div class="mb-5">
                            <label class="form-label" for="saldo_open">
                                {{ \App\Support\MultiVa::closeEnabled() ? 'Saldo VA Open (93)' : 'Saldo VA (Open)' }}
                            </label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text">Rp. </span>
                                <input readonly type="text" id="saldo_open" name="saldo_open"
                                       placeholder="Saldo VA"
                                       class="form-control"/>
                            </div>
                        </div>
                    </div>
                    @if(\App\Support\MultiVa::closeEnabled())
                    <div class="col-md-6">
                        <div class="mb-5">
                            <label class="form-label" for="saldo_close">
                                Saldo VA Close (94)
                            </label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text">Rp. </span>
                                <input readonly type="text" id="saldo_close" name="saldo_close"
                                       placeholder="Saldo Close"
                                       class="form-control"/>
                            </div>
                        </div>
                    </div>
                    @endif
                    <div class="col-12">
                        <div class="mb-5 d-none">
                            <label class="required form-label" for="saldo">
                                Saldo
                            </label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text">Rp. </span>
                                <input readonly type="text" id="saldo" name="saldo"
                                       placeholder="Saldo"
                                       class="form-control"/>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="mb-5">
                            <label class="form-label" for="tanggal">Tanggal Bayar</label>
                            <input type="text" id="tanggal" name="tanggal" placeholder="tanggal/bulan/tahun"
                                   class="form-control"/>
                        </div>
                        <div class="mb-5">
                            <label class="form-label" for="bank">Bank</label>
                            <select type="text" id="bank" name="bank" class="form-select"
                                    data-control="select2" data-placeholder="Bank">
                                <option value="1140000">Manual Cash</option>
                                <option value="1140001">Manual BMI</option>
                                <option value="1140002">Manual Saldo</option>
                                <option value="1140003">Transfer Bank Lain</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="mb-5">
                            <label class="form-label" for="total_tagihan">Total Tagihan</label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text">Rp. </span>
                                <input readonly type="text" id="total_tagihan" name="total_tagihan"
                                       placeholder="Total Tagihan"
                                       class="form-control formattedNumber"/>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer border-0 pt-0">
                <div class="d-flex">
                    <div class="ms-auto gap-6">
                        <button type="reset" class="btn btn-primary d-none">
                            <span class="ri-search-line me-2"></span>
                            Reset
                        </button>
                        <button type="button" class="btn btn-primary cari-tagihan">
                            <span class="ri-search-line me-2"></span>
                            Cari
                        </button>
                    </div>
                </div>
            </div>
            <div class="table-responsive text-nowrap">
                <table class="table table-sm table-bordered table-hover" id="main_table_2">
                    <thead class="table-light"></thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="card-footer border-0">
                <div class="w-100">
                    <div class="row">
                        <div class="d-flex justify-content-center justify-content-md-end gap-4">
                            <button type="button" class="btn btn-facebook" id="cetak-kuitansi">
                                <span class="ri-printer-line me-2"></span>
                                Cetak Kuitansi
                            </button>
                            <button type="button" class="btn btn-danger cetak-tagihan">
                                <span class="ri-file-pdf-2-line me-2"></span>
                                Pratinjau
                            </button>
                            <button type="submit" class="btn btn-success btn-bayar">
                                <span class="ri-cash-line me-2"></span>
                                Bayar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <form id="form-edit-nova" class="mainForm">
        <div class="modal modal-blur fade" id="modal-edit-nova" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Nomor VA</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body py-4">
                        <input type="hidden" id="edit_nova_custid" name="custid" value="">
                        <div class="mb-3">
                            <label class="form-label required" for="edit_nova_nis">NIS (untuk generate VA)</label>
                            <input type="text" class="form-control numberOnly" id="edit_nova_nis" name="nocust" required>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Preview Nomor VA</label>
                            <input type="text" class="form-control" id="edit_nova_preview" readonly>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div class="w-100">
                            <div class="row">
                                <div class="col">
                                    <button type="button" class="btn btn-outline-secondary w-100" data-bs-dismiss="modal">Batal</button>
                                </div>
                                <div class="col">
                                    <button type="submit" class="btn btn-primary w-100">Simpan</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@section('script')
    <script src="{{asset('main/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
    <script src="{{asset('main/libs/bootstrap-datepicker/bootstrap-datepicker.js')}}"></script>
    <script src="{{asset('js/helper/formattedNumber.min.js')}}"></script>
    <script src="{{asset('js/datatableCustom/Datatable-0-4.min.js')}}"></script>
    <script src="{{asset('main/libs/select2/select2.js')}}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.12/pdfmake.min.js"
            integrity="sha512-axXaF5grZBaYl7qiM6OMHgsgVXdSLxqq0w7F4CQxuFyrcPmn0JfnqsOtYHUun80g6mRRdvJDrTCyL8LQqBOt/Q=="
            crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.12/vfs_fonts.min.js"
            integrity="sha512-EFlschXPq/G5zunGPRSYqazR1CMKj0cQc8v6eMrQwybxgIbhsfoO5NAMQX3xFDQIbFlViv53o7Hy+yCWw6iZxA=="
            crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <script type="text/javascript">
        $(function () {
            let formClass = $('.mainForm');
            const select2Els = $("[data-control='select2']");
            const csrfToken = $('meta[name="csrf-token"]').attr('content');
            let selectedSiswaData = null;
            let select2Param = '';

            let dtOptions = {
                tableId: 'main_table_2',
                formId: 'bayar-form',
                columnUrl: '{{($columnsUrl??null)}}',
                dataUrl: '{{($datasUrl??null)}}',
                dataColumns: [],
                thead: true,
                tfoot: true,
                paging: false,
                searching: false,
                fixedHeader: false,
                pageLength: 5,
                lengthMenu: [5, 25, 50, 75, 100],
                info: false,
                scrollX: false,
                serverSide: false,
                select: 'multi',
                scrollY: false,
                retrieve: true
            };

            let currentDate = new Date();
            let day = currentDate.getDate().toString().padStart(2, '0');
            let month = (currentDate.getMonth() + 1).toString().padStart(2, '0');
            let year = currentDate.getFullYear();
            let formattedDate = day + '-' + month + '-' + year;

            function resetSiswaSearch() {
                selectedSiswaData = null;
                $('[data-control="select2-ajax-siswa"]').val(null).trigger('change');
            }

            function initSiswaSelect2Ajax() {
                const $siswaAjax = $('[data-control="select2-ajax-siswa"]');

                if (!$siswaAjax.length || typeof $.fn.select2 !== 'function') {
                    setTimeout(initSiswaSelect2Ajax, 200);
                    return;
                }

                if ($siswaAjax.hasClass('select2-hidden-accessible')) {
                    $siswaAjax.select2('destroy');
                }

                $siswaAjax.select2({
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $(document.body),
                    placeholder: $siswaAjax.data('placeholder') || 'Masukkan NIS / No. Pendaftaran / Nama Siswa',
                    ajax: {
                        url: '{{ route('admin.master-data.data-siswa.get-siswa-select2') }}',
                        dataType: 'json',
                        delay: 300,
                        data: function (params) {
                            select2Param = params.term;
                            return { term: params.term };
                        },
                        processResults: function (data) {
                            return { results: Array.isArray(data) ? data : [] };
                        },
                        cache: true
                    },
                    language: {
                        inputTooShort: function () {
                            return 'Masukkan NIS atau No. Pendaftaran atau Nama Siswa';
                        },
                        noResults: function () {
                            let w = $.isNumeric(select2Param) ? 'NIS' : 'Nama';
                            return 'Siswa dengan ' + w + ': <span class="bg-label-danger"><b>' + select2Param + '</b></span> tidak ditemukan!';
                        },
                        searching: function () {
                            return 'Mencari Siswa ......';
                        }
                    },
                    escapeMarkup: function (markup) {
                        return markup;
                    },
                    minimumInputLength: 3,
                }).on('select2:selecting', function (e) {
                    if (e.params.args.data.id === '') {
                        e.preventDefault();
                    }
                }).on('select2:select', function (e) {
                    selectedSiswaData = e.params.data || null;
                }).on('select2:clear', function () {
                    selectedSiswaData = null;
                });
            }

            if (select2Els.length && typeof $.fn.select2 === 'function') {
                select2Els.each(function () {
                    let $this = $(this);
                    $this.wrap('<div class="position-relative"></div>').select2({
                        placeholder: $this.data('placeholder') || 'Pilih',
                        dropdownParent: $this.parent(),
                        language: {
                            noResults: function () {
                                return 'Tidak ditemukan data yang sesuai!';
                            }
                        }
                    });
                });
            }

            initSiswaSelect2Ajax();

            $("#tanggal").datepicker({
                format: 'dd-mm-yyyy',
                autoclose: true,
            }).datepicker('update', formattedDate);

            function clearErrorMessages(formId) {
                const form = document.querySelector('#' + formId);
                form.querySelectorAll('.invalid-feedback').forEach(function (el) { el.textContent = ''; });
                form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
            }

            formClass.on('submit', function (e) {
                e.preventDefault();
                loadingAlert();
                const formId = $(this).attr('id');
                let url = '{{route('admin.keuangan.manual-pembayaran.store')}}';
                let data = $(this).serialize();

                try {
                    const table = $('#' + dtOptions.tableId).DataTable();
                    const selectedIndexes = table.rows({ selected: true }).indexes().toArray();
                    let Siswa = $('#siswa').val();

                    if (!Siswa) {
                        warningAlert('Silahkan pilih siswa');
                        return;
                    }

                    const checkedTagihan = $('#' + dtOptions.tableId + ' input[name="tagihan[post][]"]:checked').length;
                    if (selectedIndexes.length < 1 && checkedTagihan < 1) {
                        warningAlert('Silahkan pilih tagihan yang akan dibayar');
                        return;
                    }

                    clearErrorMessages(formId);

                    $.ajax({
                        url: url,
                        type: 'POST',
                        data: data,
                        datatype: 'json',
                        headers: { 'X-CSRF-TOKEN': csrfToken },
                    }).done(function (responses) {
                        const thisForm = document.getElementById(formId);
                        thisForm.reset();
                        resetSiswaSearch();
                        select2Els.each(function () { $(this).trigger('change'); });
                        $("#tanggal").datepicker('update', formattedDate);
                        $('#' + dtOptions.tableId).DataTable().clear().draw();
                        AlertPrint(responses.message);
                    }).fail(function (xhr) {
                        if (xhr.status === 422) {
                            errorAlert(xhr.responseJSON.message);
                            const errors = JSON.parse(xhr.responseText).error;
                            if (errors) processErros(errors);
                        } else if (xhr.status === 419) {
                            // 419 dipulihkan diam-diam oleh handler global (refresh token + retry)
                        } else if (xhr.status === 500) {
                            errorAlert('Tidak dapat terhubung ke server, Silahkan periksa koneksi internet anda');
                        } else if (xhr.status === 403) {
                            errorAlert('Anda tidak memiliki izin untuk mengakses halaman ini');
                        } else if (xhr.status === 404) {
                            errorAlert('Halaman tidak ditemukan');
                        } else {
                            errorAlert('Terjadi kesalahan, silahkan coba memuat ulang halaman');
                        }
                    });
                } catch (e) {
                    errorAlert('terjadi error pada halaman, silahkan muat ulang');
                }
            });

            $('.cari-tagihan').on('click', function () {
                let Siswa = $('#siswa').val();
                if (Siswa) {
                    getSaldoSiswa('tarik-siswa', Siswa);
                    dataReload(dtOptions.tableId);
                    $('#total_tagihan').val('');
                } else {
                    warningAlert('Silahkan Pilih Siswa!');
                }
            });

            $(document).on('click', '.cetak-tagihan', function () { printTagihan(); });
            $(document).on('click', '.test-tagihan', function () { AlertPrint(); });

            if (dtOptions.dataUrl && dtOptions.columnUrl) {
                getDT(dtOptions);
            }

            const getNominalInput = function (rowNode) { return $(rowNode).find('input.nominal-bayar-input'); };

            const parseNominal = function (value) {
                if (!value) return 0;
                return parseInt(String(value).replace(/\./g, ''), 10) || 0;
            };

            const formatNominal = function (value) {
                const amount = parseNominal(value);
                return amount > 0 ? amount.toLocaleString('id-ID') : '';
            };

            const configureNominalInput = function (input, rowData, fillDefault) {
                fillDefault = fillDefault || false;
                const sisaBayar = Number(rowData.sisa_bayar !== undefined ? rowData.sisa_bayar : rowData.BILLAM) || 0;
                const canCicil = Number(rowData.can_cicil !== undefined ? rowData.can_cicil : 0) === 1;

                input.prop('disabled', false).prop('readonly', false).attr('required', true);
                input.removeAttr('max min');
                input.attr('data-sisa-bayar', sisaBayar);
                input.attr('title', canCicil ? 'Tagihan ini dapat dicicil' : 'Tagihan ini tidak dapat dicicil, pembayaran harus lunas');

                const current = parseNominal(input.val());
                if (fillDefault && current <= 0 && sisaBayar > 0) {
                    input.val(formatNominal(sisaBayar));
                }
            };

            const updateTotalTagihan = function () {
                const table = $('#' + dtOptions.tableId).DataTable();
                let totalTagihan = 0;

                table.rows().every(function () {
                    const rowNode = this.node();
                    const checkbox = $(rowNode).find('input[name="tagihan[post][]"]');
                    const input = getNominalInput(rowNode);
                    if (checkbox.prop('checked') && input.length) {
                        totalTagihan += parseNominal(input.val());
                    }
                });

                $('input[name=total_tagihan]').val(totalTagihan ? totalTagihan.toLocaleString('id-ID') : '');
            };

            const isRowSelected = function (rowNode, checkbox) {
                return checkbox.prop('checked') || $(rowNode).hasClass('selected');
            };

            const syncNominalBayarInputs = function () {
                const table = $('#' + dtOptions.tableId).DataTable();

                table.rows().every(function () {
                    const rowNode = this.node();
                    const rowData = this.data();
                    const checkbox = $(rowNode).find('input[name="tagihan[post][]"]');
                    const input = getNominalInput(rowNode);
                    const isSelected = isRowSelected(rowNode, checkbox);

                    if (!input.length) return;

                    if (isSelected) {
                        if (!checkbox.prop('checked')) checkbox.prop('checked', true);
                        configureNominalInput(input, rowData, true);
                    } else {
                        checkbox.prop('checked', false);
                        input.prop('disabled', true).val('').removeAttr('required');
                    }
                });

                updateTotalTagihan();
            };

            const activateNominalRow = function (inputEl, fillDefault) {
                fillDefault = fillDefault || false;
                const rowNode = $(inputEl).closest('tr');
                const checkbox = rowNode.find('input[name="tagihan[post][]"]');
                const table = $('#' + dtOptions.tableId).DataTable();
                const rowData = table.row(rowNode).data();

                checkbox.prop('checked', true);
                if (!rowNode.hasClass('selected')) table.row(rowNode).select();

                configureNominalInput($(inputEl), rowData, fillDefault);
                updateTotalTagihan();
            };

            $('#' + dtOptions.tableId)
                .on('init.dt draw.dt', syncNominalBayarInputs)
                .on('mousedown click', 'td.exclude-selection, input.nominal-bayar-input', function (e) {
                    e.stopPropagation();
                    const input = $(this).is('input.nominal-bayar-input')
                        ? $(this)
                        : $(this).find('input.nominal-bayar-input');
                    if (input.length) {
                        activateNominalRow(input[0], true);
                        setTimeout(function () { input.trigger('focus'); }, 0);
                    }
                })
                .on('focus', 'input.nominal-bayar-input', function () {
                    activateNominalRow(this, false);
                })
                .on('change', 'input[name="tagihan[post][]"]', function () {
                    const table = $('#' + dtOptions.tableId).DataTable();
                    const row = table.row($(this).closest('tr'));
                    if (this.checked) { row.select(); } else { row.deselect(); }
                    syncNominalBayarInputs();
                })
                .on('select.dt', function (e, dt, type, indexes) {
                    if (type !== 'row') return;
                    indexes.forEach(function (idx) {
                        $(dt.row(idx).node()).find('input[name="tagihan[post][]"]').prop('checked', true);
                    });
                    syncNominalBayarInputs();
                })
                .on('deselect.dt', function (e, dt, type, indexes) {
                    if (type !== 'row') return;
                    indexes.forEach(function (idx) {
                        $(dt.row(idx).node()).find('input[name="tagihan[post][]"]').prop('checked', false);
                    });
                    syncNominalBayarInputs();
                })
                .on('input', 'input.nominal-bayar-input', function () {
                    const sisaBayar = Number($(this).attr('data-sisa-bayar')) || 0;
                    let amount = parseNominal($(this).val());
                    if (sisaBayar > 0 && amount > sisaBayar) {
                        amount = sisaBayar;
                        $(this).val(formatNominal(amount));
                    }
                    updateTotalTagihan();
                })
                .on('blur', 'input.nominal-bayar-input', function () {
                    const amount = parseNominal($(this).val());
                    $(this).val(amount > 0 ? formatNominal(amount) : '');
                    updateTotalTagihan();
                });

            function AlertPrint(Message) {
                Message = Message || 'Tagihan sukses dibayar, apakah anda ingin mencetak tagihan?';
                Swal.fire({
                    html: Message,
                    icon: 'success',
                    buttonsStyling: false,
                    showCancelButton: true,
                    confirmButtonText: 'Cetak Bukti Bayar',
                    cancelButtonText: 'Tutup',
                    customClass: {
                        confirmButton: 'btn btn-outline-success',
                        cancelButton: 'btn btn-outline-secondary'
                    },
                }).then(function (result) {
                    if (result.value) printPaidTagihan();
                });
            }

            async function printPaidTagihan() {
                loadingAlert('Membuat Kartu Siswa');
                let url = '{{route('admin.keuangan.manual-pembayaran.cetak-tagihan-dibayar')}}';

                try {
                    const response = await fetch(url, {
                        method: 'GET',
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
                    });

                    if (!response.ok) throw await buildHttpError(response);

                    const result = await response.json();
                    if (!result || !result.tagihans || !result.tagihans.length) throw createError('Data Tagihan Kosong', 422);

                    Swal.close();
                    const data = await generateKuitansi(result);
                    if (!data || !data.data) throw createError('Gagal membuat kuitansi', 422);

                    await generatePdf('KUITANSI', data.data, data.unit || false);
                } catch (error) {
                    if (error.status === 422) {
                        errorAlert(error.message);
                        if (error.error || error.errors) processErros(error.error || error.errors);
                    } else {
                        const errorMessages = {
                            401: 'Sesi anda sudah habis 🙏 <br>Silahkan muat ulang halaman untuk melanjutkan!',
                            403: 'Anda tidak memiliki izin untuk mengakses halaman ini 😖',
                            404: 'Halaman yang dituju tidak ditemukan 🧐',
                            405: 'Metode tidak valid 🧐 <br>silahkan muat ulang halaman dan coba lagi!',
                            419: 'Sesi anda sudah habis 🙏 <br>Silahkan muat ulang halaman untuk melanjutkan!',
                            429: 'Terlalu banyak permintaan akses <br>silahkan tunggu beberapa saat 🙏',
                        };
                        errorAlert(errorMessages[error.status] || 'Terjadi kesalahan, silahkan coba memuat ulang halaman');
                    }
                }
            }

            async function printTagihan() {
                loadingAlert();
                const table = $('#' + dtOptions.tableId).DataTable();
                const selectedRows = table.rows({ selected: true }).data().toArray();
                let Siswa = $('#siswa').val();
                const selectedSiswa = selectedSiswaData || $('#siswa').select2('data')[0];

                if (!Siswa) { warningAlert('Silahkan pilih siswa'); return; }
                if (selectedRows.length < 1) { warningAlert('Silahkan pilih tagihan yang akan dicetak'); return; }

                const data = [];
                data['siswa'] = selectedSiswa;
                data['tagihans'] = selectedRows;

                const generatedBody = await generatePDFTagihan(data);
                const pdf = await generatePdf('Tagihan Siswa', generatedBody, selectedSiswa.CODE02 || false);

                if (pdf) { successAlert('Sukses, Rekap telah dicetak'); } else { Swal.close(); }
            }

            function getSaldoSiswa(target, siswa) {
                loadingAlert();
                $.ajax({
                    url: '{{route('admin.keuangan.saldo.saldo-virtual-account.get-saldo')}}',
                    type: 'get',
                    datatype: 'json',
                    data: { 'siswa': siswa },
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                }).done(function (response) {
                    const data = typeof response === 'object' && response !== null ? response : {};
                    const formatSaldo = (raw) => {
                        let saldo = parseInt(String(raw ?? 0).replace(/\./g, ''), 10) || 0;
                        return saldo.toLocaleString('id-ID');
                    };
                    $('#saldo_open').val(formatSaldo(data.saldo_open ?? data.saldo_93 ?? 0));
                    if ($('#saldo_close').length) {
                        $('#saldo_close').val(formatSaldo(data.saldo_close ?? data.saldo_94 ?? 0));
                    }
                    $('#saldo').val(formatSaldo(data.saldo ?? data.saldo_open ?? 0));
                    Swal.close();
                }).fail(function (xhr) {
                    if (xhr.status === 422) {
                        errorAlert('Data tidak ditemukan');
                    } else if (xhr.status === 419) {
                        // 419 dipulihkan diam-diam oleh handler global (refresh token + retry)
                    } else if (xhr.status === 500) {
                        errorAlert('Gagal memuat saldo siswa. Silahkan coba lagi.');
                    } else if (xhr.status === 403) {
                        errorAlert('Anda tidak memiliki izin untuk mengakses halaman ini');
                    } else if (xhr.status === 404) {
                        errorAlert('Halaman tidak ditemukan');
                    } else {
                        errorAlert('Terjadi kesalahan, silahkan coba memuat ulang halaman');
                    }
                });
            }

            function processErros(errors) {
                for (const key in errors) {
                    if (!errors.hasOwnProperty(key)) continue;
                    const value = errors[key];
                    const field = $('[name=' + key + ']');
                    const errorMessage = value[0];

                    function applyInvalidClasses(element, container) {
                        element.addClass('is-invalid');
                        container.addClass('is-invalid');
                        let errorFeedback = container.siblings('.invalid-feedback');
                        if (errorFeedback.length === 0) {
                            $('<div>', { class: 'invalid-feedback', role: 'alert', text: errorMessage }).insertAfter(container);
                        } else {
                            errorFeedback.html(errorMessage);
                        }
                    }

                    if (field.hasClass('select2-hidden-accessible')) {
                        applyInvalidClasses(field, field.siblings('.select2-container'));
                    } else {
                        if (field.parent().hasClass('input-group')) {
                            applyInvalidClasses(field, field.parent());
                        } else {
                            applyInvalidClasses(field, field);
                        }
                    }

                    if (key === 'password') {
                        const confirmField = $('[name=' + key + '_confirmation]');
                        applyInvalidClasses(confirmField, confirmField);
                    }
                }
            }

            pdfMake.fonts = {
                Times: {
                    normal: 'https://cdn.jsdelivr.net/npm/@canvas-fonts/times-new-roman@1.0.4/Times New Roman.ttf',
                    bold: 'https://cdn.jsdelivr.net/npm/@canvas-fonts/times-new-roman-bold@1.0.4/Times New Roman Bold.ttf',
                    italics: 'https://cdn.jsdelivr.net/npm/@canvas-fonts/times-new-roman-italic@1.0.4/Times New Roman Italic.ttf',
                    bolditalics: 'https://cdn.jsdelivr.net/npm/@canvas-fonts/times-new-roman-bold@1.0.4/Times New Roman Bold.ttf'
                },
                Roboto: {
                    normal: 'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.66/fonts/Roboto/Roboto-Regular.ttf',
                    bold: 'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.66/fonts/Roboto/Roboto-Medium.ttf',
                    italics: 'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.66/fonts/Roboto/Roboto-Italic.ttf',
                    bolditalics: 'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.66/fonts/Roboto/Roboto-MediumItalic.ttf'
                },
            };

            const instansi = {
                nama_instansi: "{{ config('app.nama_instansi') }}",
                nama_sub_1: "{{ config('app.nama_sub_instansi_1') }}",
                nama_sub_2: "{{ config('app.nama_sub_instansi_2') }}",
                akreditasi: "{{ config('app.akreditasi') }}",
                alamat: "{{ config('app.alamat') }}",
                kontak: {
                    telepon: "{{ config('app.telepon') }}",
                    email: "{{ config('app.email') }}",
                    website: "{{ config('app.website') }}"
                }
            };
            const headerLogo = "{{ base64_encode(file_get_contents(public_path(config('app.logo')))) }}";
            const tandaTangan = @json($tanda_tangan);
            const userName = "{{ Auth::user()->name }}";
            const domisili = "{{ config('app.domisili') }}";
            const showVA = function (nis, tagihans) {
                if (typeof novaFromBills === 'function') return novaFromBills(nis, tagihans);
                if (typeof showVABoth === 'function') return showVABoth(nis);
                return '';
            };

            const modalEditNova = new bootstrap.Modal(document.getElementById('modal-edit-nova'));

            function getContentWidth(pageSize, orientation, margins) {
                pageSize = pageSize || 'A4';
                orientation = orientation || 'portrait';
                margins = margins || [30, 30, 30, 30];
                const sizes = { A4: [595.28, 841.89], A3: [841.89, 1190.55], LETTER: [612, 792], LEGAL: [612, 1008] };
                const size = sizes[String(pageSize).toUpperCase()] || sizes.A4;
                const pageW = orientation === 'landscape' ? size[1] : size[0];
                return pageW - margins[0] - margins[2];
            }

            async function getLogoUnit(unit) {
                unit = unit || false;
                const fallbackLogo = 'data:image/jpeg;base64,' + "{{ base64_encode(file_get_contents(public_path(config('app.logo')))) }}";
                try {
                    if (!unit) throw 'error';
                    const cacheKey = 'logo_unit_' + unit;
                    const cachedLogo = localStorage.getItem(cacheKey);
                    if (cachedLogo) return cachedLogo;

                    const params = new URLSearchParams();
                    params.append('unit', unit);
                    const response = await fetch('{{ route('admin.master-data.get-logo') }}?' + params.toString(), {
                        method: 'GET',
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
                    });
                    if (!response.ok) throw 'error';
                    const result = await response.json();
                    if (!result.data) throw 'error';
                    localStorage.setItem(cacheKey, result.data);
                    return result.data;
                } catch (e) {
                    return fallbackLogo;
                }
            }

            function formatMetodePembayaran(data, noreff) {
                if (String(noreff || '').trim().toLowerCase() === 'mobile') {
                    return 'ANDROID';
                }
                const descriptions = {
                    '1140000': 'Manual Cash', '1140001': 'Manual BMI', '1140002': 'Manual SALDO',
                    '1140003': 'Transfer Bank Lain', '1140004': 'INFAQ', '1140005': 'Transfer Bank BRI',
                    '1200001': 'Loket Manual - Beasiswa', '1200002': 'Loket Manual - Potongan',
                    '1': 'H2H VA BMI - ATM', '2': 'H2H VA BMI - Teller', '3': 'H2H VA BMI - IBANK',
                    '4': 'H2H VA BMI - EDC', '5': 'H2H VA BMI - MOBILE', '6': 'ANDROID',
                    'null': 'Nomor VA', '': 'Nomor VA'
                };
                return descriptions[String(data)] || data;
            }

            async function generatePdf(title, bodyContent, unit_logo) {
                unit_logo = unit_logo || false;
                try {
                    let logo = 'data:image/jpeg;base64,' + headerLogo;
                    if (unit_logo) logo = await getLogoUnit(unit_logo);

                    const orientation = 'portrait';
                    const pageMargins = [20, 20, 20, 20];
                    const tanggalNow = new Date().toLocaleDateString('id-ID', {
                        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
                    });
                    const availableWidth = getContentWidth('A4', orientation, pageMargins);

                    const headerTable = {
                        alignment: 'center',
                        table: {
                            widths: [60, '*'],
                            body: [[
                                logo ? { image: logo, width: 60, alignment: 'center' } : '',
                                {
                                    stack: [
                                        instansi.nama_sub_1 ? { text: instansi.nama_sub_1.toUpperCase(), style: 'headerSmall' } : '',
                                        instansi.nama_sub_2 ? { text: instansi.nama_sub_2.toUpperCase(), style: 'headerSmall' } : '',
                                        { text: instansi.nama_instansi.toUpperCase(), style: 'headerBig' },
                                        instansi.akreditasi ? { text: instansi.akreditasi, style: 'headerSmall' } : '',
                                        instansi.alamat ? { text: instansi.alamat, style: 'headerSmall' } : '',
                                        { text: 'Telp: ' + (instansi.kontak.telepon || '-') + ' | Email: ' + (instansi.kontak.email || '-') + ' | Web: ' + (instansi.kontak.website || '-'), style: 'headerSmall' }
                                    ],
                                    alignment: 'center'
                                }
                            ]]
                        },
                        layout: 'noBorders'
                    };

                    const footer = {
                        columns: [
                            { text: '', width: '*' },
                            {
                                stack: [
                                    { text: domisili + ', ' + tanggalNow, margin: [0, 10, 0, 0], alignment: 'center' },
                                    tandaTangan ? { image: tandaTangan, width: 100, alignment: 'center' } : {},
                                    { text: userName, alignment: 'center' }
                                ],
                                width: 'auto'
                            }
                        ]
                    };

                    const content = [
                        headerTable,
                        {
                            margin: [0, 5, 0, 5],
                            canvas: [
                                { type: 'line', x1: 0, y1: 0, x2: availableWidth, y2: 0, lineWidth: 2 },
                                { type: 'line', x1: 0, y1: 3, x2: availableWidth, y2: 3, lineWidth: 0.5, lineColor: '#888' }
                            ]
                        },
                        { text: title, style: 'title', margin: [0, 5, 0, 5] }
                    ].concat(bodyContent).concat([footer]);

                    const docDefinition = {
                        pageSize: 'A4',
                        pageOrientation: orientation,
                        pageMargins: pageMargins,
                        content: content,
                        styles: {
                            headerBig: { fontSize: 16, bold: true, alignment: 'center' },
                            headerSmall: { fontSize: 12, alignment: 'center' },
                            title: { fontSize: 14, bold: true, alignment: 'center' },
                            subTitle: { fontSize: 12, bold: true },
                            tableHeader: { bold: true, fillColor: '#ededed', alignment: 'center' },
                            small: { fontSize: 9, alignment: 'center' },
                            tableFont: { fontSize: 5 }
                        },
                        defaultStyle: { font: 'Times' }
                    };

                    pdfMake.createPdf(docDefinition).open();
                    successAlert('File telah didownload <br><p><span class="badge badge-dot bg-danger me-1"></span> Cek pada menu unduhan browser anda untuk memeriksa!</p>');
                } catch (e) {
                    console.error('Error generating PDF:', e);
                    errorAlert(e.message);
                }
            }

            function formatTanggalBayar(value) {
                if (!value || value === '' || value === '0000-00-00 00:00:00') return '-';
                const parsed = new Date(value);
                if (Number.isNaN(parsed.getTime())) return '-';
                return parsed.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
            }

            async function generateKuitansi(data) {
                try {
                    const bodyContent = [];
                    let siswa = data.siswa;
                    const namaSiswa = siswa.NMCUST || siswa.nmcust || '-';
                    const paymentBank = data.bank || '';
                    const firstTagihan = data.tagihans && data.tagihans[0] ? data.tagihans[0] : {};
                    const fidBank = paymentBank || firstTagihan.FIDBANK || '';
                    const biayaLayanan = (data.biaya_layanan !== undefined && data.biaya_layanan !== null)
                        ? Number(data.biaya_layanan)
                        : (fidBank === '1140002' ? 0 : 2000);
                    let nocust = !siswa.NOCUST || siswa.NOCUST === '' || siswa.NOCUST === '-' ? false : siswa.NOCUST;
                    const uniqueMetode = [];
                    data.tagihans.forEach(function (item) {
                        const m = String(item.FIDBANK || paymentBank || '');
                        if (m && uniqueMetode.indexOf(m) === -1) uniqueMetode.push(m);
                    });
                    const metodeLabel = paymentBank
                        ? formatMetodePembayaran(paymentBank, firstTagihan.NOREFF)
                        : (uniqueMetode.length > 1 ? 'Beragam' : formatMetodePembayaran(fidBank, firstTagihan.NOREFF));
                    const ortu = siswa.GENUS || siswa.genus || '-';

                    const mainTable = [
                        [(nocust ? 'NIS ' : 'No. Pendaftaran'), ': ' + (nocust ? nocust : (siswa.NUM2ND || '-')), 'Unit', ': ' + (siswa.CODE02 || '-')],
                        [(nocust ? 'No. VA ' : '-'), ': ' + (nocust ? showVA(nocust, data.tagihans) : '-'), 'Kelas', ': ' + (siswa.DESC02 || '') + ' ' + (siswa.DESC03 || '')],
                        ['Nama ', ': ' + namaSiswa, 'Orang Tua', ': ' + ortu],
                        ['Metode Bayar', ': ' + metodeLabel, '', ''],
                    ];

                    bodyContent.push(
                        { table: { widths: ['15%', '35%', '15%', '35%'], body: mainTable }, layout: 'noBorders' },
                        { text: '', margin: [0, 5, 0, 5] }
                    );

                    const tableBody = [];
                    tableBody.push(['#', 'Nama Tagihan', 'Periode', 'Tagihan', 'Bayar', 'Metode', 'Tanggal Bayar'].map(function (h) { return { text: h, style: 'tableHeader' }; }));

                    let totalBayar = 0;
                    data.tagihans.forEach(function (item, index) {
                        const billAm = Number(item.BILLAM || 0);
                        const nominalBayar = Number(item.NOMINAL_BAYAR !== undefined ? item.NOMINAL_BAYAR : (item.BILLAM || 0));
                        const itemFidBank = item.FIDBANK || paymentBank || '';
                        const tanggalBayar = formatTanggalBayar(item.PAIDDT);
                        totalBayar += nominalBayar;
                        tableBody.push([
                            { text: index + 1, alignment: 'center' },
                            { text: item.BILLNM, alignment: 'left' },
                            { text: item.BTA, alignment: 'left' },
                            { text: formatRupiah(billAm), alignment: 'right' },
                            { text: formatRupiah(nominalBayar), alignment: 'right' },
                            { text: formatMetodePembayaran(itemFidBank, item.NOREFF), alignment: 'left' },
                            { text: tanggalBayar, alignment: 'left' },
                        ]);
                    });

                    if (biayaLayanan > 0) {
                        tableBody.push([
                            { colSpan: 4, text: 'Total Tagihan', alignment: 'right', style: 'tableHeader' }, {}, {}, {},
                            { text: formatRupiah(totalBayar), alignment: 'right' }, {}, {}
                        ]);
                        tableBody.push([
                            { colSpan: 4, text: 'Biaya Admin', alignment: 'right', style: 'tableHeader' }, {}, {}, {},
                            { text: formatRupiah(biayaLayanan), alignment: 'right' }, {}, {}
                        ]);
                    }

                    tableBody.push([
                        { colSpan: 4, text: 'Total', alignment: 'right', style: 'tableHeader' }, {}, {}, {},
                        { text: formatRupiah(totalBayar + biayaLayanan), alignment: 'right' }, {}, {}
                    ]);

                    bodyContent.push({
                        table: { widths: ['3%', '22%', '11%', '14%', '14%', '16%', '20%'], body: tableBody },
                        layout: {
                            fillColor: function (rowIndex) { return rowIndex === 0 ? '#ededed' : null; },
                            hLineWidth: function () { return 0.5; },
                            vLineWidth: function () { return 0.5; }
                        },
                        margin: [0, 0, 0, 10],
                        fontSize: 12
                    });

                    const forPdf = [];
                    forPdf['unit'] = siswa.CODE02;
                    forPdf['data'] = bodyContent;
                    return forPdf;
                } catch (e) {
                    console.log(e);
                }
            }

            async function generatePDFTagihan(data) {
                try {
                    const bodyContent = [];
                    let siswa = data.siswa;
                    let nocust = !siswa.NOCUST || siswa.NOCUST === '' || siswa.NOCUST === '-' ? false : siswa.NOCUST;
                    const ortu = siswa.GENUS || siswa.genus || '-';

                    const mainTable = [
                        [(nocust ? 'NIS ' : 'No. Pendaftaran'), ': ' + (nocust ? nocust : (siswa.NUM2ND || '-')), 'Unit', ': ' + (siswa.CODE02 || '-')],
                        [(nocust ? 'No. VA ' : '-'), ': ' + (nocust ? showVA(nocust, data.tagihans) : '-'), 'Kelas', ': ' + (siswa.DESC02 || '') + ' ' + (siswa.DESC03 || '')],
                        ['Nama ', ': ' + (siswa.NMCUST || '-'), 'Orang Tua', ': ' + ortu],
                        ['', '', '', ''],
                    ];

                    bodyContent.push(
                        { table: { widths: ['15%', '35%', '15%', '35%'], body: mainTable }, layout: 'noBorders' },
                        { text: '', margin: [0, 5, 0, 5] }
                    );

                    const tableBody = [];
                    tableBody.push(['#', 'Nama Tagihan', 'Periode', 'Tagihan'].map(function (h) { return { text: h, style: 'tableHeader' }; }));

                    let totalTagihan = 0;
                    data.tagihans.forEach(function (item, index) {
                        totalTagihan += item.BILLAM;
                        tableBody.push([
                            { text: index + 1, alignment: 'center' },
                            { text: item.BILLNM, alignment: 'left' },
                            { text: item.BTA, alignment: 'left' },
                            { text: formatRupiah(item.BILLAM), alignment: 'right' }
                        ]);
                    });

                    tableBody.push([
                        { colSpan: 3, text: 'Total Tagihan', alignment: 'right', style: 'tableHeader' }, {}, {},
                        { text: formatRupiah(totalTagihan), alignment: 'right' },
                    ]);

                    bodyContent.push({
                        table: { widths: ['3%', '27%', '20%', '50%'], body: tableBody },
                        layout: {
                            fillColor: function (rowIndex) { return rowIndex === 0 ? '#ededed' : null; },
                            hLineWidth: function () { return 0.5; },
                            vLineWidth: function () { return 0.5; }
                        },
                        margin: [0, 0, 0, 10],
                        fontSize: 12
                    });

                    return bodyContent;
                } catch (e) {
                    console.log(e);
                }
            }

            function formatRupiah(amount) {
                if (!amount) return 'Rp 0';
                return 'Rp. ' + amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            }

            function createError(message, status, extra) {
                extra = extra || {};
                const err = new Error(message);
                err.status = status;
                for (const k in extra) {
                    if (extra.hasOwnProperty(k)) err[k] = extra[k];
                }
                return err;
            }

            async function buildHttpError(response) {
                const status = response.status;
                const contentType = response.headers.get('content-type');
                let message = 'Request failed with status ' + status;
                let extra = {};
                try {
                    if (contentType && contentType.includes('application/json')) {
                        const data = await response.json();
                        message = data.message || message;
                        extra = data;
                    } else {
                        message = (await response.text()) || message;
                    }
                } catch (e) {}
                return createError(message, status, extra);
            }

            const cetak = document.getElementById('cetak-kuitansi');
            if (cetak) {
                cetak.addEventListener('click', function (e) {
                    e.preventDefault();
                    printPaidTagihan();
                });
            }

            $(document).on('click', '.btn-edit-nova', function () {
                const btn = $(this);
                $('#edit_nova_custid').val(btn.data('custid') || '');
                const nis = String(btn.data('nis') || '').trim();
                $('#edit_nova_nis').val(nis);
                const siswaData = selectedSiswaData || $('#siswa').select2('data')[0] || {};
                const unit = siswaData.CODE02 || '';
                $('#edit_nova_preview').val(nis ? showVA(nis) : '');
                modalEditNova.show();
            });

            $('#edit_nova_nis').on('input', function () {
                const nis = $(this).val().trim();
                $('#edit_nova_preview').val(nis ? showVA(nis) : '');
            });

            const formEditNova = document.getElementById('form-edit-nova');
            if (formEditNova) {
                formEditNova.addEventListener('submit', function (e) {
                    e.preventDefault();
                    loadingAlert('Menyimpan nomor VA...');
                    const formData = new FormData(this);
                    formData.append('_token', csrfToken);
                    fetch('{{ route('admin.keuangan.manual-pembayaran.update-nocust') }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken },
                        body: formData,
                    }).then(async function (res) {
                        const data = await res.json().catch(function () { return {}; });
                        if (!res.ok) throw { status: res.status, message: data.message || 'Gagal menyimpan' };
                        return data;
                    }).then(function (data) {
                        Swal.close();
                        successAlert(data.message);
                        modalEditNova.hide();
                        dataReload(dtOptions.tableId);
                    }).catch(function (err) {
                        Swal.close();
                        errorAlert(err.message || 'Gagal menyimpan nomor VA');
                    });
                });
            }
        });
    </script>

    {!! ($modalLink??'') !!}
@endsection
