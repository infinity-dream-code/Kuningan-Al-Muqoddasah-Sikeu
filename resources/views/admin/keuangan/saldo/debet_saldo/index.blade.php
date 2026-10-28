@extends('layouts.admin_new')
@section('style')
    <link rel="stylesheet" href="{{asset('main/libs/datatables-bs5/datatables.bootstrap5.css')}}">
    <link rel="stylesheet" href="{{asset('main/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
    <style>
        #siswaList {
            display: none;
            z-index: 1050;
            max-height: 260px;
            overflow: auto;
            top: calc(100% + 2px);
        }
    </style>
@endsection
@section('content')
    <h3 class="page-heading d-flex text-gray-900 fw-bold flex-column justify-content-center my-0">
        {{ $dataTitle }}
    </h3>
    <ul class="breadcrumb breadcrumb-style2">
        <li class="breadcrumb-item">
            <a href="{{route('admin.index')}}" class="text-hover-primary">Beranda</a>
        </li>
        <li class="breadcrumb-item">{{ $title }}</li>
        <li class="breadcrumb-item">{{ $mainTitle }}</li>
        <li class="breadcrumb-item active">{{ $dataTitle }}</li>
    </ul>

    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Keluarkan Saldo Siswa</h5>
                </div>
                <div class="card-body">
                    <form id="debetForm" autocomplete="off">
                        <input type="hidden" name="custid" id="custid">

                        <div class="mb-3 position-relative">
                            <label for="siswaSearch" class="form-label">Cari Siswa (NIS / Nama)</label>
                            <input type="text" class="form-control" id="siswaSearch" placeholder="Ketik minimal 3 karakter">
                            <div id="siswaList" class="list-group position-absolute w-100 shadow border bg-white"></div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">NIS</label>
                                <input type="text" class="form-control" id="nisBox" readonly>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Nama</label>
                                <input type="text" class="form-control" id="namaBox" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Kelas</label>
                                <input type="text" class="form-control" id="kelasBox" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Kelompok</label>
                                <input type="text" class="form-control" id="kelompokBox" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Sisa Saldo</label>
                                <input type="text" class="form-control fw-bold text-primary" id="saldoBox" value="0" readonly>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="nominalInput" class="form-label">Nominal Debet</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="text" class="form-control fw-bold" id="nominalInput" inputmode="numeric" value="0" disabled>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="keteranganInput" class="form-label">Keterangan</label>
                                <input type="text" class="form-control" name="keterangan" id="keteranganInput" maxlength="255" disabled>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap justify-content-end gap-3">
                            <button type="button" class="btn btn-outline-secondary" id="btnReset">
                                <span class="ri-reset-left-line me-2"></span>Reset
                            </button>
                            <button type="button" class="btn btn-outline-primary" id="btnSemua" disabled>
                                <span class="ri-wallet-3-line me-2"></span>Debet Semua Saldo
                            </button>
                            <button type="submit" class="btn btn-danger" id="btnSimpan" disabled>
                                <span class="ri-save-line me-2"></span>Simpan Debet
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Riwayat Debet Siswa</h5>
                </div>
                <div class="table-responsive" style="max-height: 420px;">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light sticky-top">
                        <tr>
                            <th>Tanggal</th>
                            <th>No Transaksi</th>
                            <th class="text-end">Nominal</th>
                            <th>Teller</th>
                            <th>Keterangan</th>
                        </tr>
                        </thead>
                        <tbody id="riwayatBody">
                        <tr><td colspan="5" class="text-center text-muted py-3">Pilih siswa terlebih dahulu.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Semua Riwayat Debet Saldo</h5>
        </div>
        <div class="card-datatable table-responsive text-nowrap">
            <table class="table table-sm table-bordered table-hover" id="main_table">
                <thead class="table-light"></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
@endsection

@section('script')
    <script src="{{asset('main/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
    <script src="{{asset('js/datatableCustom/Datatable-0-4.js')}}?v=20260724-excel-total"></script>

    <script type="text/javascript">
        const debetUrls = {
            search: '{{ $searchUrl }}',
            detail: '{{ $detailUrl }}',
            store: '{{ $storeUrl }}',
        };

        let dtOptions = {
            tableId: 'main_table',
            columnUrl: '{{ $columnsUrl }}',
            dataUrl: '{{ $datasUrl }}',
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
            getDT(dtOptions);

            const $search = $('#siswaSearch');
            const $list = $('#siswaList');
            const $nominal = $('#nominalInput');
            let currentSaldo = 0;
            let searchTimer = null;

            const rupiah = (value) => new Intl.NumberFormat('id-ID').format(Number(value) || 0);
            const parseNominal = () => parseInt(String($nominal.val()).replace(/\D/g, ''), 10) || 0;
            const escapeHtml = (text) => $('<div>').text(text ?? '').html();

            function setFormEnabled(enabled) {
                $nominal.prop('disabled', !enabled);
                $('#keteranganInput').prop('disabled', !enabled);
                $('#btnSemua').prop('disabled', !enabled || currentSaldo <= 0);
                $('#btnSimpan').prop('disabled', !enabled || currentSaldo <= 0);
            }

            function renderRiwayat(rows) {
                const $body = $('#riwayatBody');
                if (!rows || rows.length === 0) {
                    $body.html('<tr><td colspan="5" class="text-center text-muted py-3">Belum ada debet saldo.</td></tr>');
                    return;
                }
                $body.html(rows.map(row => `
                    <tr>
                        <td>${escapeHtml(row.tanggal)}</td>
                        <td>${escapeHtml(row.transno)}</td>
                        <td class="text-end">${rupiah(row.nominal)}</td>
                        <td>${escapeHtml(row.teller)}</td>
                        <td>${escapeHtml(row.keterangan || '-')}</td>
                    </tr>`).join(''));
            }

            function setSaldo(saldo) {
                currentSaldo = Number(saldo) || 0;
                $('#saldoBox').val(rupiah(currentSaldo));
            }

            function resetForm() {
                $('#custid').val('');
                $search.val('');
                $('#nisBox, #namaBox, #kelasBox, #kelompokBox, #keteranganInput').val('');
                $nominal.val('0');
                setSaldo(0);
                setFormEnabled(false);
                $('#riwayatBody').html('<tr><td colspan="5" class="text-center text-muted py-3">Pilih siswa terlebih dahulu.</td></tr>');
            }

            function loadSiswa(custid) {
                $.get(debetUrls.detail, {custid: custid}).done(function (data) {
                    $('#custid').val(data.custid);
                    $search.val(data.nis + ' - ' + data.nama);
                    $('#nisBox').val(data.nis);
                    $('#namaBox').val(data.nama);
                    $('#kelasBox').val([data.unit, data.kelas].filter(Boolean).join(' - '));
                    $('#kelompokBox').val(data.kelompok || '');
                    setSaldo(data.saldo);
                    $nominal.val('0');
                    setFormEnabled(true);
                    renderRiwayat(data.riwayat);
                    if (currentSaldo <= 0) {
                        warningAlert('Saldo siswa ini kosong, tidak ada yang bisa didebet.');
                    }
                }).fail(function (xhr) {
                    errorAlert(xhr.responseJSON?.message || 'Gagal memuat data siswa.');
                });
            }

            $search.on('input', function () {
                clearTimeout(searchTimer);
                const q = $(this).val().trim();
                if (q.length < 3) {
                    $list.hide().empty();
                    return;
                }
                searchTimer = setTimeout(function () {
                    $.get(debetUrls.search, {q: q}).done(function (data) {
                        const rows = data.rows || [];
                        if (rows.length === 0) {
                            $list.html('<div class="list-group-item text-muted">Siswa tidak ditemukan.</div>').show();
                            return;
                        }
                        $list.html(rows.map(row => `
                            <button type="button" class="list-group-item list-group-item-action siswa-option" data-custid="${row.custid}">
                                <div class="fw-semibold">${escapeHtml(row.nis)} - ${escapeHtml(row.nama)}</div>
                                <small class="text-muted">${escapeHtml([row.unit, row.kelas, row.kelompok].filter(Boolean).join(' - '))}</small>
                            </button>`).join('')).show();
                    });
                }, 300);
            });

            $list.on('click', '.siswa-option', function () {
                $list.hide().empty();
                loadSiswa($(this).data('custid'));
            });

            $(document).on('click', function (e) {
                if (!$(e.target).closest('#siswaSearch, #siswaList').length) {
                    $list.hide();
                }
            });

            $nominal.on('input', function () {
                const value = parseNominal();
                $(this).val(value ? rupiah(value) : '');
            });

            $('#btnSemua').on('click', function () {
                $nominal.val(rupiah(currentSaldo));
            });

            $('#btnReset').on('click', resetForm);

            $('#debetForm').on('submit', function (e) {
                e.preventDefault();
                const custid = $('#custid').val();
                const nominal = parseNominal();

                if (!custid) {
                    warningAlert('Pilih siswa terlebih dahulu.');
                    return;
                }
                if (nominal <= 0) {
                    warningAlert('Nominal debet harus lebih dari 0.');
                    return;
                }
                if (nominal > currentSaldo) {
                    warningAlert('Nominal melebihi sisa saldo (Rp ' + rupiah(currentSaldo) + ').');
                    return;
                }

                Swal.fire({
                    html: `Debet saldo <b>${escapeHtml($('#namaBox').val())}</b> sebesar <b>Rp ${rupiah(nominal)}</b>?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, debet',
                    cancelButtonText: 'Batal',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-danger me-3',
                        cancelButton: 'btn btn-outline-secondary',
                    },
                }).then(function (result) {
                    if (!result.isConfirmed) {
                        return;
                    }
                    loadingAlert();
                    $('#btnSimpan').prop('disabled', true);
                    $.ajax({
                        url: debetUrls.store,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            custid: custid,
                            nominal: nominal,
                            keterangan: $('#keteranganInput').val(),
                        },
                    }).done(function (data) {
                        setSaldo(data.saldo);
                        $nominal.val('0');
                        $('#keteranganInput').val('');
                        renderRiwayat(data.riwayat);
                        setFormEnabled(true);
                        dataReFilter(dtOptions.tableId);
                        successAlert(data.message);
                    }).fail(function (xhr) {
                        if (xhr.status === 419 || xhr.status === 401) {
                            setTimeout(function () {
                                Swal.close();
                                loadSiswa(custid);
                                dataReFilter(dtOptions.tableId);
                            }, 1500);
                            return;
                        }
                        if (xhr.responseJSON?.saldo !== undefined) {
                            setSaldo(xhr.responseJSON.saldo);
                        }
                        setFormEnabled(true);
                        const errors = xhr.responseJSON?.errors;
                        errorAlert(errors ? Object.values(errors).flat().join('<br>') : (xhr.responseJSON?.message || 'Gagal menyimpan debet saldo.'));
                    });
                });
            });
        });
    </script>
@endsection
