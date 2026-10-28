@extends('layouts.admin_new')
@section('style')
    <link rel="stylesheet" href="{{asset('main/libs/datatables-bs5/datatables.bootstrap5.css')}}">
    <link rel="stylesheet" href="{{asset('main/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
    <link rel="stylesheet" href="{{asset('main/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
    <link rel="stylesheet" href="{{asset('main/libs/select2/select2.min.css')}}">
@endsection
@section('content')
    <h3 class="page-heading d-flex text-gray-900 fw-bold flex-column justify-content-center my-0">
        {{($dataTitle??($mainTitle??($title??'')))}}
    </h3>
    <ul class="breadcrumb breadcrumb-style2">
        <li class="breadcrumb-item">
            <a href="{{ route('admin.index') }}" class="text-hover-primary">Beranda</a>
        </li>
        @isset($title)
            <li class="breadcrumb-item">{{ $title }}</li>
        @endisset
        @isset($mainTitle)
            <li class="breadcrumb-item active">{{ $mainTitle }}</li>
        @endisset
    </ul>

    <div class="card">
        <div class="card-header header-elements">
            <h5 class="mb-0 me-2">{{($dataTitle??$mainTitle)}}</h5>
            <div class="card-header-elements ms-auto">
                <div class="w-100">
                    <div class="row">
                        <div class="d-flex justify-content-center justify-content-md-end gap-4">
                            <button type="button" class="btn btn-outline-secondary" onclick="dataReload('main_table')" title="Refresh">
                                <span class="ri-refresh-line me-2"></span>
                                Refresh
                            </button>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                    data-bs-target="#modal-create" title="Tambah Tagihan">
                                <span class="ri-add-line me-2"></span>
                                Add
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-datatable table-responsive text-nowrap">
            <table class="table table-sm table-bordered table-hover" id="main_table">
                <thead class="table-light"></thead>
                <tbody></tbody>
            </table>
        </div>
        <div class="card-footer">
            <small class="text-muted">
                Nama tagihan tidak bisa diubah. VA dan status cicil hanya berlaku untuk tagihan yang dibuat setelah ini.
            </small>
        </div>
    </div>
@endsection

@section('script')
    <script src="{{asset('main/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
    <script src="{{asset('js/datatableCustom/Datatable-0-4.min.js')}}"></script>
    <script src="{{asset('js/helper/errorInputHelper.min.js')}}"></script>
    <script src="{{asset('main/libs/select2/select2.min.js')}}"></script>

    <script type="text/javascript">
        const dtOptions = {
            tableId: 'main_table',
            formId: false,
            columnUrl: '{{($columnsUrl??null)}}',
            dataUrl: '{{($datasUrl??null)}}',
            dataColumns: [],
            thead: true,
            tfoot: false,
            paging: true,
            searching: true,
            fixedHeader: false,
            pageLength: 10,
            lengthMenu: [10, 25, 50, 75, 100],
        };

        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        let modalEdit;

        document.addEventListener("DOMContentLoaded", function () {
            modalEdit = new bootstrap.Modal(document.getElementById('modal-edit'));

            if (dtOptions.dataUrl && dtOptions.columnUrl) {
                getDT(dtOptions);
            }

            document.querySelectorAll("[data-control='select2']").forEach(select => {
                let wrapper = document.createElement("div");
                wrapper.classList.add("position-relative");
                select.parentNode.insertBefore(wrapper, select);
                wrapper.appendChild(select);
                $(select).select2({
                    placeholder: "Pilih satu",
                    language: "id",
                    dropdownParent: $(wrapper)
                });
            });
        });
    </script>

    <form id="addForm" class="mainForm">
        <div class="modal modal-blur fade" id="modal-create" tabindex="-1" role="dialog" aria-hidden="true"
             data-bs-backdrop="static">
            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah Master Tagihan</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body py-4">
                        <fieldset class="form-fieldset">
                            <div class="mb-3">
                                <label class="form-label required" for="tagihan">Nama Tagihan</label>
                                <input type="text" class="form-control" name="tagihan" id="tagihan" autocomplete="off"
                                       placeholder="Contoh: SPP" required>
                                <div class="invalid-feedback" role="alert"><strong></strong></div>
                            </div>
                            @if(\App\Support\MultiVa::masterVaSelectable())
                            <div class="mb-3">
                                <label class="form-label required" for="VA">VA</label>
                                <select class="form-select" name="VA" id="VA" data-control="select2" required>
                                    <option value="93">93 - Open</option>
                                    <option value="94">94 - Close</option>
                                </select>
                                <div class="invalid-feedback" role="alert"><strong></strong></div>
                            </div>
                            @endif
                            <div class="mb-3">
                                <label class="form-label required" for="isINSTALLMENT">Status Cicil</label>
                                <select class="form-select" name="isINSTALLMENT" id="isINSTALLMENT"
                                        data-control="select2" required>
                                    <option value="1">Bisa dicicil</option>
                                    <option value="0">Tidak bisa dicicil</option>
                                </select>
                                <div class="invalid-feedback" role="alert"><strong></strong></div>
                            </div>
                        </fieldset>
                    </div>
                    <div class="modal-footer">
                        <div class="w-100">
                            <div class="row">
                                <div class="col">
                                    <input type="reset" value="Batal" class="btn btn-outline-secondary w-100"
                                           data-bs-dismiss="modal">
                                </div>
                                <div class="col">
                                    <input type="submit" value="Simpan Data" class="btn btn-primary w-100">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <form id="editForm" class="mainForm">
        <div class="modal modal-blur fade" id="modal-edit" tabindex="-1" role="dialog" aria-hidden="true"
             data-bs-backdrop="static">
            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Master Tagihan</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body py-4">
                        <fieldset class="form-fieldset">
                            <div class="mb-3">
                                <label class="form-label" for="edit_tagihan">Nama Tagihan</label>
                                <input type="text" class="form-control" id="edit_tagihan" autocomplete="off" readonly
                                       disabled>
                                <small class="text-muted">Nama tagihan tidak dapat diubah.</small>
                            </div>
                            @if(\App\Support\MultiVa::masterVaSelectable())
                            <div class="mb-3">
                                <label class="form-label required" for="edit_VA">VA</label>
                                <select class="form-select" name="VA" id="edit_VA" data-control="select2" required>
                                    <option value="93">93 - Open</option>
                                    <option value="94">94 - Close</option>
                                </select>
                                <div class="invalid-feedback" role="alert"><strong></strong></div>
                            </div>
                            @endif
                            <div class="mb-3">
                                <label class="form-label required" for="edit_isINSTALLMENT">Status Cicil</label>
                                <select class="form-select" name="isINSTALLMENT" id="edit_isINSTALLMENT"
                                        data-control="select2" required>
                                    <option value="1">Bisa dicicil</option>
                                    <option value="0">Tidak bisa dicicil</option>
                                </select>
                                <small class="text-muted">Berlaku untuk tagihan berikutnya, bukan tagihan yang sudah ada.</small>
                                <div class="invalid-feedback" role="alert"><strong></strong></div>
                            </div>
                        </fieldset>
                        <input type="hidden" id="edit_id" name="item_id" value="">
                    </div>
                    <div class="modal-footer">
                        <div class="w-100">
                            <div class="row">
                                <div class="col">
                                    <input type="reset" value="Batal" class="btn btn-outline-secondary w-100"
                                           data-bs-dismiss="modal">
                                </div>
                                <div class="col">
                                    <input type="submit" value="Simpan Perubahan" class="btn btn-primary w-100">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelector(`#${dtOptions.tableId} tbody`).addEventListener('click', function (e) {
                const rowEl = e.target.closest('tr');
                if (!rowEl) return;
                if (!e.target.closest('.btn-edit')) return;

                const rowData = DT[`${dtOptions.tableId}`].row(rowEl).data();
                document.getElementById('edit_tagihan').value = rowData.tagihan || '';
                document.getElementById('edit_id').value = rowData.item_id || '';
                const $editVa = $('#edit_VA');
                if ($editVa.length) {
                    const vaValue = String(rowData.VA ?? '').trim();
                    if (vaValue && $editVa.find('option').filter(function () {
                        return String($(this).val()) === vaValue;
                    }).length === 0) {
                        $editVa.append(new Option(vaValue, vaValue, true, true));
                    }
                    $editVa.val(vaValue || '').trigger('change');
                }
                $('#edit_isINSTALLMENT').val(String(Number(rowData.isINSTALLMENT ?? 0) === 1 ? 1 : 0)).trigger('change');
                modalEdit.show();
            });

            document.querySelectorAll(".mainForm").forEach(form => {
                form.addEventListener("submit", function (e) {
                    e.preventDefault();
                    loadingAlert();

                    const isEdit = form.id === 'editForm';
                    const formData = new FormData(this);
                    formData.append("_token", csrfToken);
                    if (isEdit) {
                        formData.append("_method", "PUT");
                    }

                    clearErrorMessages(form.id);
                    const itemId = document.getElementById('edit_id').value;
                    const url = isEdit
                        ? "{{ url('admin/master-data/master-tagihan') }}/" + itemId
                        : "{{ route('admin.master-data.master-tagihan.store') }}";

                    fetch(url, {
                        method: "POST",
                        headers: {'X-CSRF-TOKEN': csrfToken, 'X-HTTP-Method-Override': isEdit ? 'PUT' : 'POST'},
                        body: formData
                    })
                        .then(response => {
                            if (!response.ok) {
                                return response.json().then(err => {
                                    throw {status: response.status, error: err};
                                });
                            }
                            return response.json();
                        })
                        .then(data => {
                            document.getElementById(form.id).reset();
                            if (isEdit) {
                                if ($('#edit_VA').length) {
                                    $('#edit_VA').val('93').trigger('change');
                                }
                                $('#edit_isINSTALLMENT').val('1').trigger('change');
                            }
                            successAlert(data.message);
                            dataReload("main_table");
                            document.querySelector(`#${form.id} [data-bs-dismiss="modal"]`)?.click();
                        })
                        .catch(error => {
                            if (error.status === 422) {
                                const errors = error.error.error || error.error.errors;
                                errorAlert(error.error.message);
                                if (errors) processErrors(errors);
                            } else {
                                errorAlert("Terjadi kesalahan, silahkan coba memuat ulang halaman");
                            }
                        });
                });
            });
        });
    </script>
@endsection
