<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
    <div class="app-brand demo">
        <a href="{{route('admin.index')}}" class="app-brand-link">
              <span class="app-brand-logo demo">
                <span style="color: var(--bs-primary)">
                  <img width="50" height="50" src="{{asset(config('app.logo'))}}" alt="logo">
                </span>
              </span>
            <span class="app-brand-text demo menu-text fw-bold ms-2">{{ config('app.name') }}</span>
        </a>
        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
            <svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path
                    d="M11.4854 4.88844C11.0081 4.41121 10.2344 4.41121 9.75715 4.88844L4.51028 10.1353C4.03297 10.6126 4.03297 11.3865 4.51028 11.8638L9.75715 17.1107C10.2344 17.5879 11.0081 17.5879 11.4854 17.1107C11.9626 16.6334 11.9626 15.8597 11.4854 15.3824L7.96672 11.8638C7.48942 11.3865 7.48942 10.6126 7.96672 10.1353L11.4854 6.61667C11.9626 6.13943 11.9626 5.36568 11.4854 4.88844Z"
                    fill="currentColor"
                    fill-opacity="0.6"/>
                <path
                    d="M15.8683 4.88844L10.6214 10.1353C10.1441 10.6126 10.1441 11.3865 10.6214 11.8638L15.8683 17.1107C16.3455 17.5879 17.1192 17.5879 17.5965 17.1107C18.0737 16.6334 18.0737 15.8597 17.5965 15.3824L14.0778 11.8638C13.6005 11.3865 13.6005 10.6126 14.0778 10.1353L17.5965 6.61667C18.0737 6.13943 18.0737 5.36568 17.5965 4.88844C17.1192 4.41121 16.3455 4.41121 15.8683 4.88844Z"
                    fill="currentColor"
                    fill-opacity="0.38"/>
            </svg>
        </a>
    </div>

    <div class="menu-inner-shadow"></div>

    <ul class="menu-inner py-1">
        <li class="menu-item  {{ Request::is(['admin'])  ? 'active' : '' }}">
            <a href="{{route('admin.index')}}" class="menu-link">
                <i class="menu-icon ri ri-home-3-line"></i>
                <div data-i18n="Beranda">Beranda</div>
            </a>
        </li>
        <li class="menu-item {{ Request::is(['admin/master-data*']) ? 'active open' : '' }}">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon ri ri-database-2-line"></i>
                <div data-i18n="Master Data">Master Data</div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item {{ Request::is(['admin/master-data/master-kelas*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.master-data.master-kelas.index') }}" class="menu-link">
                        <div data-i18n="Master Kelas">Master Kelas</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/master-data/tahun-pelajaran*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.master-data.tahun-pelajaran.index') }}" class="menu-link">
                        <div data-i18n="Tahun Akademik">Tahun Akademik</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/master-data/master-tagihan*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.master-data.master-tagihan.index') }}" class="menu-link">
                        <div data-i18n="Master Tagihan">Master Tagihan</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/master-data/export-import-data*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.master-data.export-import-data.index') }}" class="menu-link">
                        <div data-i18n="Export Import Data">Export Import Data</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/master-data/data-siswa*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.master-data.data-siswa.index') }}" class="menu-link">
                        <div data-i18n="Data Siswa">Data Siswa</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/master-data/pindah-kelas*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.master-data.pindah-kelas.index') }}" class="menu-link">
                        <div data-i18n="Pindah Kelas">Pindah Kelas</div>
                    </a>
                </li>
            </ul>
        </li>
        <li class="menu-item {{ Request::is(['admin/keuangan*']) ? 'active open' : '' }}">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon ri ri-wallet-3-line"></i>
                <div data-i18n="Keuangan">Keuangan</div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item {{ Request::is(['admin/keuangan/tagihan-siswa*']) ? 'active open' : '' }}">
                    <a href="javascript:void(0)" class="menu-link menu-toggle">
                        <div data-i18n="Tagihan Siswa">Tagihan Siswa</div>
                    </a>
                    <ul class="menu-sub">
                        <li class="menu-item {{ Request::is(['admin/keuangan/tagihan-siswa/upload-tagihan-excel*']) ? 'active' : '' }}">
                            <a href="{{ route('admin.keuangan.tagihan-siswa.upload-tagihan-excel.index') }}" class="menu-link">
                                <div data-i18n="Buat Tagihan Excel">Buat Tagihan Excel</div>
                            </a>
                        </li>
                        <li class="menu-item {{ Request::is(['admin/keuangan/tagihan-siswa/data-tagihan*']) ? 'active' : '' }}">
                            <a href="{{ route('admin.keuangan.tagihan-siswa.data-tagihan.index') }}" class="menu-link">
                                <div data-i18n="Data Tagihan">Data Tagihan</div>
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="menu-item {{ Request::is(['admin/keuangan/manual-pembayaran*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.keuangan.manual-pembayaran.index') }}" class="menu-link">
                        <div data-i18n="Manual Pembayaran">Manual Pembayaran</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/keuangan/penerimaan-siswa/data-penerimaan*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.keuangan.penerimaan-siswa.data-penerimaan.index') }}" class="menu-link">
                        <div data-i18n="Data Tagihan Lunas">Data Tagihan Lunas</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/keuangan/penerimaan-siswa/rekap-penerimaan*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.keuangan.penerimaan-siswa.rekap-penerimaan.index') }}" class="menu-link">
                        <div data-i18n="Data Pembayaran">Data Pembayaran</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/keuangan/data-transfer-va*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.keuangan.data-transfer-va.index') }}" class="menu-link">
                        <div data-i18n="Data Transfer VA">Data Transfer VA</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/keuangan/saldo*']) ? 'active open' : '' }}">
                    <a href="javascript:void(0);" class="menu-link menu-toggle">
                        <div data-i18n="Saldo">Saldo</div>
                    </a>
                    <ul class="menu-sub">
                        <li class="menu-item {{ Request::is(['admin/keuangan/saldo/saldo-virtual-account*']) ? 'active' : '' }}">
                            <a href="{{ route('admin.keuangan.saldo.saldo-virtual-account.index') }}" class="menu-link">
                                <div data-i18n="Saldo VA">{{ \App\Support\MultiVa::closeEnabled() ? 'Saldo VA Open' : 'Saldo VA' }}</div>
                            </a>
                        </li>
                        @if(\App\Support\MultiVa::closeEnabled())
                        <li class="menu-item {{ Request::is(['admin/keuangan/saldo/saldo-va-close*']) ? 'active' : '' }}">
                            <a href="{{ route('admin.keuangan.saldo.saldo-va-close.index') }}" class="menu-link">
                                <div data-i18n="Saldo VA Close">Saldo VA Close</div>
                            </a>
                        </li>
                        @endif
                        <li class="menu-item {{ Request::is(['admin/keuangan/saldo/debet-saldo*']) ? 'active' : '' }}">
                            <a href="{{ route('admin.keuangan.saldo.debet-saldo.index') }}" class="menu-link">
                                <div data-i18n="Debet Saldo">Debet Saldo</div>
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="menu-item {{ Request::is(['admin/keuangan/hapus-tagihan*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.keuangan.hapus-tagihan.index') }}" class="menu-link">
                        <div data-i18n="Hapus Tagihan">Hapus Tagihan</div>
                    </a>
                </li>
            </ul>
        </li>
        {{-- Manual Input / Pengumuman / Wakaf / Rekap Data: disembunyikan untuk client ini --}}

        <li class="menu-item mt-auto pb-2">
            <a href="{{route('logout')}}" class="menu-link btn-danger text-white"  onclick="event.preventDefault();
                              document.getElementById('logout-form').submit();">
                <i class="menu-icon ri ri-logout-box-r-line"></i>
                <div data-i18n="Logout">
                    Logout
                </div>
            </a>
        </li>
    </ul>
</aside>
