@extends('layouts.admin_new')
@section('title',$dataTitle??$mainTitle??$title??'')
@section('style')
    <link rel="stylesheet" href="{{asset('main/libs/datatables-bs5/datatables.bootstrap5.css')}}">
    <link rel="stylesheet" href="{{asset('main/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
    <link rel="stylesheet" href="{{asset('main/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
    <link rel="stylesheet" href="{{asset('main/libs/select2/select2.min.css')}}">
    <style>
        .input-locked {
            background-color: #fff3cd !important;
            border-color: #ffe69c !important;
        }
    </style>
@endsection
@section('content')
    <h3 class="page-heading d-flex text-gray-900 fw-bold flex-column justify-content-center my-0">
        @if(isset($dataTitle) && isset($mainTitle) && $mainTitle != $dataTitle)
            {{$mainTitle .' - '.$dataTitle}}
        @else
            {{$mainTitle??$title??''}}
        @endif
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
        <div class="card-header header-elements">
            <div class="card-title">
                <h5 class="mb-0 me-2">{{($dataTitle??$mainTitle)}}</h5>
            </div>
            <div class="card-header-elements ms-auto">
                <div class="d-flex justify-content-center justify-content-md-end gap-4">
                </div>
            </div>
        </div>
        <div class="card-body">
            <form id="filterForm">
                <fieldset class="form-fieldset">
                    <h5>Filter</h5>
                    <div class="row">
                        <div class="row mb-4">
                            <label class="col-sm-2 col-form-label" for="filter[angkatan]">
                                Angkatan Siswa
                            </label>
                            <div class="col-sm-10">
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
                        <div class="row mb-4">
                            <label class="col-sm-2 col-form-label" for="filter[sekolah]">
                                Sekolah
                            </label>
                            <div class="col-sm-10">
                                <select class="form-select" id="filter[sekolah]" name="filter[sekolah]"
                                        data-control="select2" data-placeholder="Pilih Sekolah">
                                    <option value="all">Semua</option>
                                    @isset($sekolah)
                                        @foreach($sekolah as $item)
                                            <option
                                                value="{{$item->CODE01}}">{{$item->DESC01}}</option>
                                        @endforeach
                                    @else
                                        <option>data kosong</option>
                                    @endisset
                                </select>
                            </div>
                        </div>
                        <div class="row mb-4">
                            <label class="col-sm-2 col-form-label" for="filter[kelas]">
                                Kelas
                            </label>
                            <div class="col-sm-10">
                                <select class="form-select" id="filter[kelas]" name="filter[kelas]"
                                        data-control="select2" data-placeholder="Pilih Kelas">
                                    <option value="all">Semua</option>
                                    @isset($kelas)
                                        @foreach($kelas as $item)
                                            <option
                                                value="{{$item->unit}}~~{{$item->jenjang}}~~{{$item->kelas}}">{{$item->unit}}
                                                - {{$item->jenjang}} {{$item->kelas}}</option>
                                        @endforeach
                                    @else
                                        <option>data kosong</option>
                                    @endisset
                                </select>
                            </div>
                        </div>
                        <div class="row mb-4">
                            <label class="col-sm-2 col-form-label" for="filter[siswa]">
                                Siswa
                            </label>
                            <div class="col-sm-10">
                                <input class="form-control" id="filter[siswa]" name="filter[siswa]"
                                       placeholder="Masukkan NIS/NAMA Siswa" data-placeholder="Pilih siswa">
                            </div>
                        </div>
                        <div class="row mb-4">
                            <label class="col-sm-2 col-form-label" for="filter[ayah]">
                                Nama Ayah
                            </label>
                            <div class="col-sm-10">
                                <input class="form-control" id="filter[ayah]" name="filter[ayah]"
                                       placeholder="Masukkan Nama Ayah">
                            </div>
                        </div>
                        <div class="row mb-4">
                            <label class="col-sm-2 col-form-label" for="filter[ibu]">
                                Nama Ibu
                            </label>
                            <div class="col-sm-10">
                                <input class="form-control" id="filter[ibu]" name="filter[ibu]"
                                       placeholder="Masukkan Nama Ibu">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="d-flex justify-content-center justify-content-md-end gap-4">
                            <button type="reset" class="btn btn-secondary">
                                <span class="ri-reset-left-line me-2"></span>
                                Reset
                            </button>
                            <button type="submit" class="btn btn-primary">
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
    <form id="form-edit-siswa">
        <div class="modal modal-blur fade" id="modal-edit-siswa" tabindex="-1" role="dialog" aria-hidden="true"
             data-bs-backdrop="static">
            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-status bg-info"></div>
                    <div class="modal-header ">
                        <div class="modal-title">
                            Edit Data Siswa
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning py-2 mb-3">
                            Field berwarna kuning tidak dapat diedit.
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">NIS</label>
                                <input type="text" readonly class="form-control form-control-sm input-locked"
                                       id="edit_siswa-nocust" name="nis">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">No. Pendaftaran</label>
                                <input type="text" readonly class="form-control form-control-sm input-locked"
                                       id="edit_siswa-num2nd" name="no_pendaftaran">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Nama</label>
                                <input type="text" readonly class="form-control form-control-sm input-locked"
                                       id="edit_siswa-nmcust" name="nama">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Unit</label>
                                <input type="text" readonly class="form-control form-control-sm input-locked"
                                       id="edit_siswa-code02" name="unit">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Kelas</label>
                                <input type="text" readonly class="form-control form-control-sm input-locked"
                                       id="edit_siswa-desc02" name="kelas">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Kelompok</label>
                                <input type="text" readonly class="form-control form-control-sm input-locked"
                                       id="edit_siswa-desc03" name="kelompok">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Angkatan</label>
                                <input type="text" readonly class="form-control form-control-sm input-locked"
                                       id="edit_siswa-desc04" name="angkatan">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Jenis Kelamin / Gender</label>
                                <select class="form-select form-select-sm" id="edit_siswa-code04" name="gender" required>
                                    <option value="">Pilih</option>
                                    <option value="L">Laki-laki</option>
                                    <option value="P">Perempuan</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Internal / Eksternal</label>
                                <input type="text" class="form-control form-control-sm"
                                       id="edit_siswa-eksint" name="eksint">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Alamat</label>
                                <textarea class="form-control form-control-sm" id="edit_siswa-alamat" name="alamat" rows="2"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nama Ayah</label>
                                <input type="text" class="form-control form-control-sm"
                                       id="edit_siswa-genus" name="ayah">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nama Ibu</label>
                                <input type="text" class="form-control form-control-sm"
                                       id="edit_siswa-genus1" name="ibu">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Nomor Telpon</label>
                                <input type="text" class="form-control form-control-sm"
                                       id="edit_siswa-genuscontact" name="nomor_telpon">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Asrama</label>
                                <input type="text" class="form-control form-control-sm"
                                       id="edit_siswa-getwisma" name="asrama">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select class="form-select form-select-sm" id="edit_siswa-stcust" name="stcust">
                                    <option value="1">Aktif</option>
                                    <option value="0">Nonaktif</option>
                                </select>
                            </div>
                        </div>
                        <input type="hidden" id="edit_siswa-item_id" name="item_id" value="">
                    </div>
                    <div class="modal-footer ">
                        <div class="w-100">
                            <div class="row">
                                <div class="col">
                                    <input type="reset" class="btn btn-outline-secondary w-100" value="Batal"
                                           data-bs-dismiss="modal">
                                </div>
                                <div class="col">
                                    <input type="submit" value="Simpan Perubahan"
                                           class="btn btn-info w-100">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <form id="form-reset-login-android">
        <div class="modal modal-blur fade" id="modal-reset-login-android" tabindex="-1" role="dialog" aria-hidden="true"
             data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-status bg-danger"></div>
                    <div class="modal-header ">
                        <div class="modal-title">
                            Reset Login Android
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row  text-capitalize text-center">
                            <span class="ri-android-fill text-success ri-5x"></span>
                            <h4>Reset Login Android siswa?</h4>
                        </div>
                        <div class="row px-5">
                            <fieldset class="form-fieldset">
                                <div class="mb-3 row">
                                    <label for="nocust" class="col-sm-4 col-form-label form-label-sm">NIS</label>
                                    <div class="col">
                                        <input type="text" readonly class="form-control  form-control-sm"
                                               id="reset_login-nocust"
                                               name="nocust">
                                    </div>
                                </div>
                                <div class="mb-3 row">
                                    <label for="nmcust" class="col-sm-4 col-form-label form-label-sm">Nama Siswa</label>
                                    <div class="col-sm-8">
                                        <input type="text" readonly class="form-control form-control-sm"
                                               id="reset_login-nmcust"
                                               name="nmcust">
                                    </div>
                                </div>
                                <div class="mb-3 row">
                                    <label for="nmcust" class="col-sm-4 col-form-label form-label-sm">Kelas</label>
                                    <div class="col-sm-8">
                                        <input type="text" readonly class="form-control form-control-sm"
                                               id="reset_login-desc02"
                                               name="desc02">
                                    </div>
                                </div>
                                <div class="mb-3 row">
                                    <label for="nmcust" class="col-sm-4 col-form-label form-label-sm">Kelompok</label>
                                    <div class="col-sm-8">
                                        <input type="text" readonly class="form-control form-control-sm"
                                               id="reset_login-desc03"
                                               name="desc03">
                                    </div>
                                </div>
                                <div class="mb-3 row">
                                    <label for="nmcust" class="col-sm-4 col-form-label form-label-sm">Angkatan</label>
                                    <div class="col-sm-8">
                                        <input type="text" readonly class="form-control form-control-sm"
                                               id="reset_login-desc04"
                                               name="desc04">
                                    </div>
                                </div>
                            </fieldset>
                            <input type="hidden" id="reset_login_item_id" name="item_id" value="">
                            <input type="hidden" id="reset_login_custid" name="custid" value="">
                        </div>
                    </div>
                    <div class="modal-footer ">
                        <div class="w-100">
                            <div class="row">
                                <div class="col">
                                    <input type="reset" class="btn btn-outline-secondary w-100" value="Batal"
                                           data-bs-dismiss="modal">
                                </div>
                                <div class="col">
                                    <input id="submit-reset-login" type="submit" value="Reset"
                                           class="btn btn-whatsapp w-100">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <form id="form-edit-status-siswa">
        <div class="modal modal-blur fade" id="modal-edit-status-siswa" tabindex="-1" role="dialog" aria-hidden="true"
             data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-status bg-danger"></div>
                    <div class="modal-header ">
                        <div class="modal-title">
                           Edit Status Siswa
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row  text-capitalize text-center">
                            <h4>Edit Status Siswa?</h4>
                        </div>
                        <div class="row px-5">
                            <fieldset class="form-fieldset">
                                <div class="mb-3 row">
                                    <label for="nocust" class="col-sm-4 col-form-label form-label-sm">NIS</label>
                                    <div class="col">
                                        <input type="text" readonly class="form-control  form-control-sm"
                                               id="edit_status_siswa-nocust"
                                               name="nocust">
                                    </div>
                                </div>
                                <div class="mb-3 row">
                                    <label for="nmcust" class="col-sm-4 col-form-label form-label-sm">Nama Siswa</label>
                                    <div class="col-sm-8">
                                        <input type="text" readonly class="form-control form-control-sm"
                                               id="edit_status_siswa-nmcust"
                                               name="nmcust">
                                    </div>
                                </div>
                                <div class="mb-3 row">
                                    <label for="nmcust" class="col-sm-4 col-form-label form-label-sm">Unit / Kelas / Kelompok</label>
                                    <div class="col-sm-8">
                                        <input type="text" readonly class="form-control form-control-sm"
                                               id="edit_status_siswa-kelas_kelompok"
                                               name="kelas_kelompok">
                                    </div>
                                </div>
                                <div class="mb-3 row">
                                    <label for="nmcust" class="col-sm-4 col-form-label form-label-sm">Angkatan</label>
                                    <div class="col-sm-8">
                                        <input type="text" readonly class="form-control form-control-sm"
                                               id="edit_status_siswa-desc04"
                                               name="desc04">
                                    </div>
                                </div>
                                <div class="mb-3 row">
                                    <label for="nmcust" class="col-sm-4 col-form-label form-label-sm">Status</label>
                                    <div class="col-sm-8">
                                        <select type="text" readonly class="form-select form-select-sm"
                                                id="edit_status_siswa-stcust"
                                                name="stcust">
                                            <option value="0">Nonaktif</option>
                                            <option value="1">Aktif</option>
                                        </select>
                                    </div>
                                </div>
                            </fieldset>
                            <input type="hidden" id="edit_status_siswa_item_id" name="item_id" value="">
                            <input type="hidden" id="edit_status_siswa_custid" name="custid" value="">
                        </div>
                    </div>
                    <div class="modal-footer ">
                        <div class="w-100">
                            <div class="row">
                                <div class="col">
                                    <input type="reset" class="btn btn-outline-secondary w-100" value="Batal"
                                           data-bs-dismiss="modal">
                                </div>
                                <div class="col">
                                    <input type="submit" value="Edit Status"
                                           class="btn btn-warning w-100">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>


    <script src="{{asset('main/libs/datatables-bs5/datatables-bootstrap5.min.js')}}"></script>
    <script src="{{asset('js/datatableCustom/Datatable-0-4.min.js')}}"></script>
    <script src="{{asset('main/libs/select2/select2.min.js')}}"></script>
    <script src="{{asset('js/helper/errorInputHelper.min.js')}}"></script>

    <script type="text/javascript">
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const select2 = $(`[data-control='select2']`);

        let dtOptions = {
            tableId: 'main_table',
            formId: 'filterForm',
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
            info: false,
            scrollX: true,
            serverSide: true,
            select: false,
            scrollY: false,
            buttons: ['copy', 'excel', 'pdf', 'print'],
            pdfPageSize: 'A3',
            pdfOrientation: 'landscape',
            pdfFontSize: 5,
            pdfHeaderFontSize: 6,
            pdfMargins: [6, 8, 6, 8],
            pdfColumnWidths: {
                no: 18,
                nocust: 48,
                va_open: 72,
                @if(\App\Support\MultiVa::closeEnabled())
                va_close: 72,
                @endif
                va_saku: 72,
                NUM2ND: 48,
                nmcust: '*',
                CODE02: 28,
                DESC02: 32,
                DESC03: 32,
                DESC04: 38,
                CODE04: 24,
                DESC05: 55,
                GENUS: 55,
                NO_WA: 48,
                STCUST: 24,
            },
            printCustomize: function (win) {
                const style = `
                    @page { size: landscape; margin: 8mm; }
                    table { width: 100% !important; table-layout: fixed !important; }
                    th, td { white-space: normal !important; word-break: break-all !important; font-size: 9px !important; padding: 3px !important; }
                `;
                $(win.document.head).append(`<style>${style}</style>`);
            }
        };

        const modals = [
            {modalId: 'modal-edit-siswa', formId: 'form-edit-siswa'},
            {modalId: 'modal-reset-login-android', formId: 'form-reset-login-android'},
            {modalId: 'modal-edit-status-siswa', formId: 'form-edit-status-siswa'}
        ];

        const modalInstances = {};

        modals.forEach(({modalId, formId, inputs}) => {
            const modalElement = document.getElementById(modalId);
            const modal = new bootstrap.Modal(modalElement);

            modalInstances[modalId] = modal;

            modalElement.addEventListener('hide.bs.modal', () => {
                const form = document.getElementById(formId);
                form?.reset();
                clearErrorMessages(formId);
            });

            modalElement.addEventListener('show.bs.modal', function (e) {
                if (formId !== 'form-create') {
                    const button = event.relatedTarget;
                    const row = DT[`${dtOptions.tableId}`].row($(button).closest('tr'));
                    fillFormValue(formId, row);
                }
            });

            document.getElementById(formId).addEventListener('submit', async function (e) {
                e.preventDefault();
                let request = false;
                let options = {};
                if (formId === 'form-reset-login-android') {
                    loadingAlert('Memproses reset login android...')
                    const formData = new FormData(this);
                    const itemId = formData.get('item_id');
                    if (!itemId) {
                        errorAlert('data tidak valid!');
                        return;
                    }
                    let url = '{{route('admin.master-data.data-siswa.reset-login-android',':id')}}'
                    url = url.replace(':id', itemId)
                    request = new Request(
                        url, {
                            method: "POST",
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                            }, body: formData
                        });
                } else if (formId === 'form-edit-siswa') {
                    loadingAlert('Mengubah data siswa...!')
                    const formData = new FormData(this);
                    const itemId = formData.get('item_id');
                    if (!itemId) {
                        errorAlert('data tidak valid!');
                        return;
                    }
                    formData.append('_method', 'PUT');
                    let url = '{{route('admin.master-data.data-siswa.update',':id')}}'
                    url = url.replace(':id', itemId)
                    request = new Request(
                        url, {
                            method: "POST",
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                            }, body: formData
                        });
                } else if (formId === 'form-edit-status-siswa') {
                    loadingAlert('Mengubah status siswa...!')
                    const formData = new FormData(this);
                    const itemId = formData.get('item_id');
                    if (!itemId) {
                        errorAlert('data tidak valid!');
                        return;
                    }
                    let url = '{{route('admin.master-data.data-siswa.set-status-siswa',':id')}}'
                    url = url.replace(':id', itemId)
                    request = new Request(
                        url, {
                            method: "POST",
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                            }, body: formData
                        });
                }

                if (!request) return;
                const processForm = await submitForm(request);

                if (processForm) {
                    const message = processForm.message ?? "Sukses";
                    if (formId !== 'form-reset-login-android') {
                        dataReload(dtOptions.tableId);
                    }
                    successAlert(message);
                    modal.hide();
                }
            });
        });


        function updateFilterWindowLocation(form) {
            let baseUrl = window.location.origin + window.location.pathname;
            let queryParams = $.param($(`#${form}`).serializeArray().reduce(function (acc, curr) {
                if (curr.value !== '') {
                    acc[curr.name] = curr.value;
                }
                return acc;
            }, {}));
            let newUrl = baseUrl + '?' + queryParams;
            window.history.pushState(null, '', newUrl);
        }


        function normalizeGender(value) {
            const raw = String(value ?? '').toUpperCase().replace(/[\s\-_]/g, '');
            if (['L', '1', 'LAKI', 'LAKILAKI', 'MALE', 'PRIA', 'COWOK'].includes(raw)) {
                return 'L';
            }
            if (['P', '0', 'PEREMPUAN', 'WANITA', 'FEMALE', 'CEWEK'].includes(raw)) {
                return 'P';
            }
            return '';
        }

        function fillFormValue(id, rowEl) {
            const rowData = DT[`${dtOptions.tableId}`].row(rowEl).data();
            Object.entries(rowData).forEach(([key, value]) => {
                let input = document.querySelector(`#${id} [name="${key.toLowerCase()}"]`);
                if (input) {
                    input.value = value;
                }
            });

            if (id === 'form-edit-siswa') {
                const setValue = (selector, value) => {
                    const el = document.querySelector(selector);
                    if (el) {
                        el.value = value ?? '';
                    }
                };

                const pickValue = (...candidates) => {
                    for (const candidate of candidates) {
                        if (candidate !== undefined && candidate !== null && candidate !== '') {
                            return candidate;
                        }
                    }
                    return '';
                };

                // Mapping eksplisit agar field tetap terisi walau nama key backend berbeda.
                setValue('#form-edit-siswa [name="nis"]', pickValue(rowData.nis, rowData.nocust, rowData.NOCUST));
                setValue('#form-edit-siswa [name="no_pendaftaran"]', pickValue(rowData.no_pendaftaran, rowData.num2nd, rowData.NUM2ND));
                setValue('#form-edit-siswa [name="nama"]', pickValue(rowData.nama, rowData.nmcust, rowData.NMCUST));
                setValue('#form-edit-siswa [name="ayah"]', pickValue(rowData.ayah, rowData.genus, rowData.GENUS));
                setValue('#form-edit-siswa [name="ibu"]', pickValue(rowData.ibu, rowData.genus1, rowData.GENUS1));
                setValue('#form-edit-siswa [name="nomor_telpon"]', pickValue(rowData.nomor_telpon, rowData.genuscontact, rowData.GENUSContact));
                setValue('#form-edit-siswa [name="asrama"]', pickValue(rowData.asrama, rowData.getwisma, rowData.GetWisma));
                setValue('#form-edit-siswa [name="stcust"]', pickValue(rowData.stcust, rowData.STCUST, 0));
                setValue('#form-edit-siswa [name="unit"]', pickValue(rowData.code02, rowData.CODE02));
                setValue('#form-edit-siswa [name="kelas"]', pickValue(rowData.desc02, rowData.DESC02));
                setValue('#form-edit-siswa [name="kelompok"]', pickValue(rowData.desc03, rowData.DESC03));
                setValue('#form-edit-siswa [name="angkatan"]', pickValue(rowData.angkatan, rowData.desc04, rowData.DESC04));
                setValue('#form-edit-siswa [name="gender"]', normalizeGender(pickValue(rowData.gender, rowData.code04, rowData.CODE04)));
                setValue('#form-edit-siswa [name="alamat"]', pickValue(rowData.alamat, rowData.desc05, rowData.DESC05));
                setValue('#form-edit-siswa [name="eksint"]', pickValue(rowData.eksint, rowData.eksternalinternal, rowData.EksternalInternal));
                setValue('#form-edit-siswa [name="item_id"]', rowData.item_id ?? '');
            }

            if (id === 'form-edit-status-siswa') {
                const unit = rowData.code02 ?? rowData.CODE02 ?? '';
                const kelas = rowData.desc02 ?? rowData.DESC02 ?? '';
                const kelompok = rowData.desc03 ?? rowData.DESC03 ?? '';
                const kelasKelompok = [unit, kelas, kelompok].filter(Boolean).join(' - ');
                const kelasKelompokInput = document.querySelector('#form-edit-status-siswa [name="kelas_kelompok"]');
                if (kelasKelompokInput) {
                    kelasKelompokInput.value = kelasKelompok;
                }
            }
        }

        async function submitForm(request, options = {}) {
            const controller = new AbortController();
            const timeout = options.timeout || 30000;

            const timeoutId = setTimeout(() => controller.abort(), timeout);

            try {
                const response = await fetch(request, {
                    ...options,
                    signal: controller.signal
                });

                clearTimeout(timeoutId);

                const contentType = response.headers.get('content-type');
                let data = null;

                if (contentType && contentType.includes('application/json')) {
                    data = await response.json();
                } else {
                    data = await response.text();
                }

                if (!response.ok) {
                    const error = new Error(data?.message || `Request failed (${response.status})`);
                    error.status = response.status;
                    error.data = data;
                    throw error;
                }

                return data;
            } catch (error) {
                clearTimeout(timeoutId);

                if (error.name === 'AbortError') {
                    errorAlert('Permintaan terlalu lama ⏳, silakan coba lagi.');
                    return false;
                }

                if (error.status === 422) {
                    errorAlert(error.message);

                    const errors = error.data?.errors || error.data;
                    if (errors) {
                        processErrors(errors);
                    }

                    return false;
                }

                const errorMessages = {
                    401: 'Sesi anda sudah habis 🙏 <br>Silahkan muat ulang halaman atau login kembali!',
                    403: 'Anda tidak memiliki izin untuk mengakses 😖',
                    404: 'Halaman tidak ditemukan 🧐',
                    405: 'Metode tidak valid 🧐 <br>Silakan coba lagi!',
                    419: 'Sesi anda sudah habis 🙏 <br>Silahkan login kembali!',
                    429: 'Terlalu banyak permintaan 🙏 <br>Tunggu beberapa saat!',
                };

                errorAlert(
                    errorMessages[error.status] ||
                    error.message ||
                    'Terjadi kesalahan, silakan muat ulang halaman'
                );

                return false;
            }
        }

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
                            updateFilterWindowLocation(dtOptions.formId);
                            dataReFilter(dtOptions.tableId);
                        }, 0)
                    });
                }
            }
            if (select2.length) {
                select2.each(function () {
                    let $this = $(this);
                    $this.wrap('<div class="position-relative"></div>').select2({
                        placeholder: 'Select value',
                        language: 'id',
                        dropdownParent: $this.parent()
                    });
                });
            }
        });

    </script>

    {!! ($modalLink??'') !!}
@endsection
