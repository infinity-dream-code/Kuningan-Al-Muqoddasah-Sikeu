@extends('layouts.admin_new')
@section('content')
    <h3 class="page-heading d-flex text-gray-900 fw-bold flex-column justify-content-center my-0">
        {{ $dataTitle }}
    </h3>
    <ul class="breadcrumb breadcrumb-style2">
        <li class="breadcrumb-item">
            <a href="{{ route('admin.index') }}" class="text-hover-primary">Beranda</a>
        </li>
        <li class="breadcrumb-item">{{ $title }}</li>
        <li class="breadcrumb-item active">{{ $dataTitle }}</li>
    </ul>

    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Ganti Password Akun {{ $username }}</h5>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    @if($errors->any())
                        <div class="alert alert-danger">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.ganti-password.update') }}" autocomplete="off">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="password_lama">Password Lama</label>
                            <input type="password" class="form-control" id="password_lama" name="password_lama" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password_baru">Password Baru</label>
                            <input type="password" class="form-control" id="password_baru" name="password_baru"
                                   minlength="6" required>
                            <small class="text-muted">Minimal 6 karakter.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password_baru_confirmation">Ulangi Password Baru</label>
                            <input type="password" class="form-control" id="password_baru_confirmation"
                                   name="password_baru_confirmation" minlength="6" required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <span class="ri-save-line me-2"></span>Simpan Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
