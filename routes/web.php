<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Auth::routes([
    "register" => false,
]);
Route::get("/", [AuthController::class, "index"])->name("index");
Route::get("/home", function () {
    return redirect()->route("admin.index");
});

Route::get("/reload-captcha", [AuthController::class, "reloadCaptcha"])->name("reload-captcha");
Route::get("/reload-math-captcha", [\App\Http\Controllers\Auth\LoginController::class, "reloadMathCaptcha"])->name("reload-math-captcha");
Route::match(["get", "post"], "/keep-alive", function (\Illuminate\Http\Request $request) {
    return response()->json([
        "status" => "ok",
        "authenticated" => \Illuminate\Support\Facades\Auth::check(),
        "csrf_token" => csrf_token(),
    ]);
})->name("keep-alive");

Route::prefix("admin")
    ->name("admin.")
    ->middleware(["auth", "check.roles:admin"])
    ->group(function () {
        Route::get("/", [AdminController::class, "index"])->name("index");

        Route::prefix("master-data")->name("master-data.")->group(function () {
            Route::get("get-logo", function (\Illuminate\Http\Request $request) {
                $path = public_path(config("app.logo"));
                $mime = "image/jpeg";
                if (!file_exists($path)) {
                    return response()->json(["data" => null], 404);
                }
                $data = "data:{$mime};base64," . base64_encode(file_get_contents($path));
                return response()->json(["data" => $data]);
            })->name("get-logo");

            Route::prefix("master-kelas")
                ->name("master-kelas.")
                ->controller(\App\Http\Controllers\Admin\MasterData\MasterKelasController::class)
                ->group(function () {
                    Route::get("get-data", "getData")->name("get-data");
                    Route::get("get-column", "getColumn")->name("get-column");
                    Route::resource("", \App\Http\Controllers\Admin\MasterData\MasterKelasController::class)->parameters(["" => "id"]);
                });

            Route::prefix("tahun-pelajaran")
                ->name("tahun-pelajaran.")
                ->controller(\App\Http\Controllers\Admin\MasterData\TahunPelajaranController::class)
                ->group(function () {
                    Route::get("get-data", "getData")->name("get-data");
                    Route::get("get-column", "getColumn")->name("get-column");
                });
            Route::resource("tahun-pelajaran", \App\Http\Controllers\Admin\MasterData\TahunPelajaranController::class)->names("tahun-pelajaran");

            Route::prefix("master-tagihan")
                ->name("master-tagihan.")
                ->controller(\App\Http\Controllers\Admin\MasterData\MasterTagihanController::class)
                ->group(function () {
                    Route::get("get-data", "getData")->name("get-data");
                    Route::get("get-column", "getColumn")->name("get-column");
                    Route::get("", "index")->name("index");
                    Route::post("", "store")->name("store");
                    Route::put("{id}", "update")->name("update");
                });

            Route::prefix("export-import-data")
                ->name("export-import-data.")
                ->controller(\App\Http\Controllers\Admin\MasterData\ExportImportDataController::class)
                ->group(function () {
                    Route::get("get-data", "getData")->name("get-data");
                    Route::get("get-column", "getColumn")->name("get-column");
                    Route::post("validate-data", "validateData")->name("validate-data");
                    Route::get("clear-data", "clearData")->name("clear-data");
                    Route::resource("", \App\Http\Controllers\Admin\MasterData\ExportImportDataController::class)->parameters(["" => "id"]);
                });

            Route::prefix("data-siswa")
                ->name("data-siswa.")
                ->controller(\App\Http\Controllers\Admin\MasterData\DataSiswaController::class)
                ->group(function () {
                    Route::get("get-data", "getData")->name("get-data");
                    Route::get("get-column", "getColumn")->name("get-column");
                    Route::get("get-siswa", "getSiswa")->name("get-siswa");
                    Route::get("get-siswa-select2", "getSiswaSelect2")->name("get-siswa-select2");
                    Route::post("reset-login-android/{id}", "ResetLoginAndroid")->name("reset-login-android");
                    Route::post("reset-login-android-bulk", "resetLoginAndroidBulk")->name("reset-login-android-bulk");
                    Route::post("set-status-siswa/{id}", "setStatusSiswa")->name("set-status-siswa");
                });
            Route::resource("data-siswa", \App\Http\Controllers\Admin\MasterData\DataSiswaController::class)->names("data-siswa");

            Route::prefix("setting-data-wa")
                ->name("setting-data-wa.")
                ->controller(\App\Http\Controllers\Admin\MasterData\SettingDataWaController::class)
                ->group(function () {
                    Route::get("get-data", "getData")->name("get-data");
                    Route::get("get-column", "getColumn")->name("get-column");
                    Route::post("validate-data", "validateData")->name("validate-data");
                    Route::get("clear-data", "clearData")->name("clear-data");
                    Route::resource("", \App\Http\Controllers\Admin\MasterData\SettingDataWaController::class)->parameters(["" => "id"]);
                });

            Route::prefix("pindah-kelas")
                ->name("pindah-kelas.")
                ->controller(\App\Http\Controllers\Admin\MasterData\PindahKelasController::class)
                ->group(function () {
                    Route::get("get-data", "getData")->name("get-data");
                    Route::get("get-column", "getColumn")->name("get-column");
                    Route::resource("", \App\Http\Controllers\Admin\MasterData\PindahKelasController::class)->parameters(["" => "id"]);
                });
        });

        Route::prefix("keuangan")->name("keuangan.")->group(function () {
            Route::controller(\App\Http\Controllers\Admin\Keuangan\ManualPembayaranController::class)
                ->prefix("manual-pembayaran")->name("manual-pembayaran.")->group(function () {
                    Route::get("get-data", "getData")->name("get-data");
                    Route::get("get-column", "getColumn")->name("get-column");
                    Route::get("get-tagihan", "getTagihan")->name("get-tagihan");
                    Route::get("cetak-tagihan", "cetakTagihan")->name("cetak-tagihan");
                    Route::get("cetak-tagihan-dibayar", "cetakPembayaran")->name("cetak-tagihan-dibayar");
                    Route::post("update-nocust", "updateNocust")->name("update-nocust");
                    Route::resource("", \App\Http\Controllers\Admin\Keuangan\ManualPembayaranController::class)->parameters(["" => "id"]);
                });

            Route::prefix("tagihan-siswa")->name("tagihan-siswa.")->group(function () {
                Route::prefix("data-tagihan")->name("data-tagihan.")->group(function () {
                    Route::controller(\App\Http\Controllers\Admin\Keuangan\TagihanSiswa\DataTagihanController::class)->group(function () {
                        Route::get("get-data", "getData")->name("get-data");
                        Route::get("get-column", "getColumn")->name("get-column");
                        Route::get("get-trans-log/{id}", "getTransLog")->name("get-trans-log");
                        Route::get("cetak-rekap", "cetak")->name("cetak-rekap");
                        Route::post("ubah-urutan/{id}", "ubahUrutan")->name("ubah-urutan");
                        Route::delete("hapus/{id}", "hapusTagihan")->name("hapus");
                        Route::get("cetak-kartu-siswa", "cetakKartuSiswa")->name("cetak-kartu-siswa");
                        Route::resource("", \App\Http\Controllers\Admin\Keuangan\TagihanSiswa\DataTagihanController::class)->parameters(["" => "id"]);
                    });
                });

                Route::prefix("upload-tagihan-excel")->name("upload-tagihan-excel.")->group(function () {
                    Route::controller(\App\Http\Controllers\Admin\Keuangan\TagihanSiswa\UploadTagihanExcelController::class)->group(function () {
                        Route::get("get-data", "getData")->name("get-data");
                        Route::get("get-column", "getColumn")->name("get-column");
                        Route::post("validate-excel", "validateExcel")->name("validate-excel");
                        Route::resource("", \App\Http\Controllers\Admin\Keuangan\TagihanSiswa\UploadTagihanExcelController::class)->parameters(["" => "id"]);
                    });
                });
            });

            Route::prefix("penerimaan-siswa")->name("penerimaan-siswa.")->group(function () {
                Route::prefix("data-penerimaan")->name("data-penerimaan.")->group(function () {
                    Route::controller(\App\Http\Controllers\Admin\Keuangan\PenerimaanSiswa\DataPenerimaanController::class)->group(function () {
                        Route::get("get-data", "getData")->name("get-data");
                        Route::get("get-column", "getColumn")->name("get-column");
                        Route::get("get-trans-log/{id}", "getTransLog")->name("get-trans-log");
                        Route::post("get-trans-logs-bulk", "getTransLogsBulk")->name("get-trans-logs-bulk");
                        Route::get("cetak-rekap", "cetak")->name("cetak-rekap");
                        Route::get("cetak-rekap-new", "cetakNew")->name("cetak-rekap-new");
                        Route::get("cetak-kartu-siswa", "cetakKartuSiswa")->name("cetak-kartu-siswa");
                        Route::get("cetak-tagihan-dibayar", "cetakPembayaran")->name("cetak-tagihan-dibayar");
                        Route::resource("", \App\Http\Controllers\Admin\Keuangan\PenerimaanSiswa\DataPenerimaanController::class)->parameters(["" => "id"]);
                    });
                });

                Route::prefix("rekap-penerimaan")->name("rekap-penerimaan.")->group(function () {
                    Route::controller(\App\Http\Controllers\Admin\Keuangan\PenerimaanSiswa\RekapPenerimaanController::class)->group(function () {
                        Route::get("get-data", "getData")->name("get-data");
                        Route::get("get-column", "getColumn")->name("get-column");
                        Route::get("cetak-rekap", "cetakRekapPenerimaan")->name("cetak-rekap");
                        Route::get("cetak-tagihan-dibayar", "cetakPembayaran")->name("cetak-tagihan-dibayar");
                        Route::get("cetak-kartu-siswa", "cetakKartuSiswa")->name("cetak-kartu-siswa");
                        Route::get("cetak-per-nis", "cetakPerNis")->name("cetak-per-nis");
                        Route::resource("", \App\Http\Controllers\Admin\Keuangan\PenerimaanSiswa\RekapPenerimaanController::class)->parameters(["" => "id"]);
                    });
                });
            });

            Route::prefix("data-transfer-va")
                ->name("data-transfer-va.")
                ->controller(\App\Http\Controllers\Admin\Keuangan\Saldo\SccttranController::class)
                ->group(function () {
                    Route::get("get-data", "getData")->name("get-data");
                    Route::get("get-column", "getColumn")->name("get-column");
                    Route::get("", "index")->name("index");
                });

            Route::prefix("saldo")->name("saldo.")->group(function () {
                $registerSaldoVa = function (string $prefix, string $name, string $controller) {
                    Route::controller($controller)
                        ->prefix($prefix)->name("{$name}.")->group(function () {
                            Route::get("get-data", "getData")->name("get-data");
                            Route::get("get-column", "getColumn")->name("get-column");
                            Route::get("get-saldo", "getSaldo")->name("get-saldo");
                            Route::get("export-transaksi", "exportTransaksi")->name("export-transaksi");
                            Route::get("{id}/export", "exportDetail")->name("export");
                            Route::prefix("data-transaksi")->name("data-transaksi.")->group(function () {
                                Route::get("", "transaksiIndex")->name("index");
                                Route::get("get-data", "getDataDataTransaksi")->name("get-data");
                                Route::get("get-column", "getColumnDataTransaksi")->name("get-column");
                            });
                            Route::prefix("transaksi")->name("transaksi.")->group(function () {
                                Route::get("get-data", "getDataTran")->name("get-data");
                                Route::get("get-column", "getColumnTran")->name("get-column");
                            });
                        });
                    Route::resource($prefix, $controller)->names($name);
                };

                $registerSaldoVa(
                    "saldo-virtual-account",
                    "saldo-virtual-account",
                    \App\Http\Controllers\Admin\Keuangan\Saldo\SaldoVirtualAccountController::class
                );
                $registerSaldoVa(
                    "saldo-va-close",
                    "saldo-va-close",
                    \App\Http\Controllers\Admin\Keuangan\Saldo\SaldoVaCloseController::class
                );

                Route::controller(\App\Http\Controllers\Admin\Keuangan\Saldo\DebetSaldoController::class)
                    ->prefix("debet-saldo")->name("debet-saldo.")->group(function () {
                        Route::get("", "index")->name("index");
                        Route::get("siswa-search", "siswaSearch")->name("siswa-search");
                        Route::get("siswa-detail", "siswaDetail")->name("siswa-detail");
                        Route::post("", "store")->name("store");
                        Route::get("get-data", "getData")->name("get-data");
                        Route::get("get-column", "getColumn")->name("get-column");
                    });
            });

            Route::prefix("hapus-tagihan")->name("hapus-tagihan.")->group(function () {
                Route::controller(\App\Http\Controllers\Admin\Keuangan\HapusTagihanController::class)->group(function () {
                    Route::get("get-data", "getData")->name("get-data");
                    Route::get("get-column", "getColumn")->name("get-column");
                    Route::post("hapus-jamak", "bulkDestroy")->name("hapus-jamak");
                    Route::resource("", \App\Http\Controllers\Admin\Keuangan\HapusTagihanController::class)->parameters(["" => "id"]);
                });
            });
        });

        Route::prefix("manual-input")->name("manual-input.")->group(function () {
            Route::controller(\App\Http\Controllers\Admin\ManualInput\EditManualController::class)
                ->prefix("edit-manual")->name("edit-manual.")->group(function () {
                    Route::get("get-siswa", "getSiswa")->name("get-siswa");
                    Route::get("get-tagihan", "getTagihan")->name("get-tagihan");
                    Route::get("get-detail-taighan", "getDetailTagihan")->name("get-detail-tagihan");
                    Route::put("edit-tagihan", "editTagihan")->name("edit-tagihan");
                    Route::post("copy-tagihan", "copyTagihan")->name("copy-tagihan");
                    Route::resource("", \App\Http\Controllers\Admin\ManualInput\EditManualController::class)->parameters(["" => "id"]);
                });
        });
    });
