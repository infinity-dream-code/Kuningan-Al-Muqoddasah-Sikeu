<?php

namespace App\Http\Controllers\Admin\MasterData;

use App\Http\Controllers\Controller;
use App\Imports\MasterData\ImportDataSiswa;
use App\Models\mst_kelas;
use App\Models\mst_sekolah;
use App\Models\mst_thn_aka;
use App\Models\scctcust;
use App\Models\ValidationMessage;
use App\Support\InputSiswaProcedure;
use App\Support\SchoolScope;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use Maatwebsite\Excel\Validators\ValidationException;

class ExportImportDataController extends Controller
{
    public string $title = 'Master Data';
    public string $mainTitle = 'Export Import Data';
    public string $dataTitle = 'Export Import Data';
    public string $cacheKey = 'import_data_siswa';

    public function index()
    {
        $data['title'] = $this->title;
        $data['mainTitle'] = $this->mainTitle;
        $data['dataTitle'] = $this->dataTitle;
        $data['columnsUrl'] = route('admin.master-data.export-import-data.get-column');
        $data['datasUrl'] = route('admin.master-data.export-import-data.get-data');
        $schoolCode = SchoolScope::codeFromUser();
        $data['sekolah'] = mst_sekolah::query()
            ->select(['CODE01', 'DESC01'])
            ->when($schoolCode, fn ($q) => $q->where('CODE01', $schoolCode))
            ->orderBy('DESC01')
            ->get();

        return view('admin.master_data.export_import_data.index', $data);
    }

    public function getColumn()
    {
        return [
            ['data' => null, 'name' => 'no', 'className' => 'text-center', 'columnType' => 'row'],
            ['data' => 'nis', 'name' => 'NIS', 'searchable' => false, 'orderable' => false],
            ['data' => 'nodaftar', 'name' => 'No Pend', 'searchable' => false, 'orderable' => false],
//            ['data' => 'NOVA', 'name' => 'NO VA'],
            ['data' => 'name', 'name' => 'NAMA', 'searchable' => false, 'orderable' => false],
            ['data' => 'status', 'name' => 'Status', 'searchable' => true, 'orderable' => true, 'columnType' => 'importstatus'],
            ['data' => 'keterangan', 'name' => 'Keterangan', 'searchable' => true, 'orderable' => true],
            ['data' => 'unit', 'name' => 'Unit', 'searchable' => false, 'orderable' => false],
            ['data' => 'kelas', 'name' => 'Kelas', 'searchable' => false, 'orderable' => false],
            ['data' => 'kelompok', 'name' => 'Kelompok', 'searchable' => false, 'orderable' => false],
            ['data' => 'angkatan', 'name' => 'Angkatan', 'searchable' => false, 'orderable' => false],
            ['data' => 'gender', 'name' => 'Jenis Kelamin', 'searchable' => false, 'orderable' => false],
            ['data' => 'ortu', 'name' => 'Ortu / Wali', 'searchable' => false, 'orderable' => false],
            ['data' => 'alamat', 'name' => 'Alamat', 'searchable' => false, 'orderable' => false],
        ];
    }

    public function getData(Request $request)
    {
        $draw = $request->get('draw');
        $start = $request->get('start');
        $rowperpage = $request->get('length');

        $columnName_arr = $request->get('columns');
        $search_arr = $request->get('search');

        $defaultColumn = 'scctcust.nocust';
        $defaultOrder = 'asc';

        if ($request->has('order')) {
            $columnIndex_arr = $request->get('order');
            $columnIndex = $columnIndex_arr[0]['column'];
            $columnSortOrder = $columnIndex_arr[0]['dir'];
        } else {
            $columnIndex = $defaultColumn;
            $columnSortOrder = $defaultOrder;
        }

        $columnName = $columnName_arr[$columnIndex]['data'];
        $searchValue = $search_arr['value'];

        if (!$columnName || $columnName == 'no') {
            $columnName = $defaultColumn;
            $columnSortOrder = $defaultOrder;
        }

        $filters = [];
        $filterQuery = null;

        $cachedData = collect(Cache::get($this->cacheKey) ?? []);
        $paginatedData = $cachedData->slice($start, $rowperpage)->values();


        $nisList = collect($cachedData)->pluck('nis')->toArray();
        $nisCount = count($cachedData);

        $whereAny = [
            'scctcust.NMCUST',
            'scctcust.NOCUST',
        ];

        $select = array_unique(array_merge($whereAny, [
            'scctcust.NUM2ND',
            'scctcust.CODE02',
            'scctcust.DESC02',
            'scctcust.DESC03',
            'scctcust.DESC04',

        ]));

        $records = collect($paginatedData)->map(function ($item) {
            $nis = $item['nis'];
            return [
                'nis' => $nis,
                'nodaftar' => $item['nodaftar'] ?? null,
                'name' => $item['nama'] ?? null,
                'unit' => $item['unit'] ?? null,
                'kelas' => $item['kelas'] ?? null,
                'kelompok' => $item['kelompok'] ?? null,
                'angkatan' => $item['angkatan'] ?? null,
                'gender' => $item['gender'] ?? null,
                'ortu' => $item['ortu'] ?? $item['genus'] ?? null,
                'alamat' => $item['alamat'] ?? null,
                'status' => $item['status'] ?? 0,
                'keterangan' => $item['keterangan'],
            ];
        });

        $response = array(
            'draw' => intval($draw),
            'recordsTotal' => $nisCount,
            'recordsFiltered' => $nisCount,
            'data' => $records,
        );
        return response()->json($response);
    }

    public function store(Request $request)
    {
        $request->validate(
            [
                'fileImport' => ['required', 'mimes:xls,xlsx', 'max:1024']
            ],
            ValidationMessage::messages(),
            ValidationMessage::attributes()
        );

        $file = $request->fileImport;

        try {
            $headingsData = (new HeadingRowImport)->toArray($file);
            $requiredColumns = [
                'nama', 'unit', 'kelas', 'kelompok', 'angkatan',
            ];

            $conditionalColumns = ['nis', 'nodaftar'];
            if (empty($headingsData) || !isset($headingsData[0][0])) throw new \Exception ('Tidak dapat membaca judul kolom dari file. Pastikan file memiliki header yang sesuai.');
            $headings = $headingsData[0][0];
            $headings = array_map('strtolower', $headings);
            $missingColumns = [];
            $hasNis = in_array('nis', $headings);
            $hasNodaftar = in_array('nodaftar', $headings);

            if (!$hasNis && !$hasNodaftar) {
                $missingColumns[] = 'NIS / NODAFTAR';
            }
            foreach ($requiredColumns as $column) if (!in_array($column, $headings)) $missingColumns[] = $column;

            if (!empty($missingColumns)) {
                $formattedMissingColumns = strtoupper(str_replace('_', ' ', implode(', ', $missingColumns)));
                $formattedRequiredColumns = strtoupper(str_replace('_', ' ', implode(', ', array_merge($requiredColumns, $conditionalColumns))));
                throw new Exception (
                    "Kolom $formattedMissingColumns tidak ditemukan.<br><hr>
                               pastikan kolom berikut ada dan terisi pada file import yang akan diproses: $formattedRequiredColumns. <br>
                               Catatan: NIS atau NODAFTAR wajib salah satu terisi."
                );
            }

            Cache::forget($this->cacheKey);
            Excel::import(new ImportDataSiswa(), $file);

            $data = Cache::get($this->cacheKey) ?? [];
            $invalidCount = collect($data)->where('status', 0)->count();
            $message = 'Sukses, data siswa telah diimport, silahkan periksa kembali';
            if ($invalidCount > 0) {
                $message .= " ({$invalidCount} baris perlu diperbaiki, lihat kolom Keterangan)";
            }

            return response()->json(['message' => $message, 'data' => $data], 200);
        } catch (ValidationException $e) {
            $errorMessages = $e->errors();
            $errorMessage = $errorMessages['error'][0] ?? 'Terjadi kesalahan saat melakukan import data.';
            return response()->json(['message' => $errorMessage, 'error' => $errorMessages], 422);
        } catch (Exception $e) {
            $error = $e->getMessage();
            return response()->json(['message' => "Gagal!<br> tidak dapat melakukan $this->mainTitle.<hr> $error", 'error' => $error], 422);
        }
    }

    public function validateData(Request $request)
    {
        $rules = [
            'metode' => ['required', 'in:1,2,3,4'],
        ];
        if (in_array($request->metode, ['1', '2'], true)) {
            $rules['sekolah'] = ['required', 'string'];
        }

        $request->validate(
            $rules,
            ValidationMessage::messages(),
            ValidationMessage::attributes()
        );

        $data = Cache::get($this->cacheKey);
        if (is_null($data) || (is_array($data) && empty($data))) {
            return response()->json(['message' => 'Tidak ada data yang dapat diproses, silahkan upload file terlebih dahulu'], 422);
        }

        $sekolah = null;
        if (in_array($request->metode, ['1', '2'], true)) {
            $sekolah = mst_sekolah::where('CODE01', $request->sekolah)->first();
            if (!$sekolah) {
                return response()->json(['message' => 'Sekolah tidak ditemukan, silahkan pilih sekolah yang valid'], 422);
            }
        }

        try {
            DB::connection('DATA_MYSQL')->beginTransaction();

            if ($request->metode == '1') {
                $invalidCount = collect($data)->where('status', 0)->count();
                if ($invalidCount > 0) {
                    DB::connection('DATA_MYSQL')->rollBack();

                    return response()->json([
                        'message' => "Ada {$invalidCount} baris bermasalah. Perbaiki data di kolom Keterangan terlebih dahulu.",
                    ], 422);
                }

                $rows = array_filter($data, fn ($item) => !empty($item['nis'] ?? null));
                if (empty($rows)) {
                    DB::connection('DATA_MYSQL')->rollBack();

                    return response()->json(['message' => 'Tidak ada baris dengan NIS yang dapat disimpan'], 422);
                }

                $saved = 0;
                foreach ($rows as $item) {
                    $item = $this->normalizeImportItem($item);
                    $nis = (string) ($item['nis'] ?? '');
                    if ($nis === '' || strlen($nis) > 15) {
                        continue;
                    }

                    $thnAka = mst_thn_aka::where('thn_aka', $item['angkatan'])->first();
                    $kelas = mst_kelas::findForImport($item['unit'], $item['kelas'], $item['kelompok']);

                    if (!$thnAka || !$kelas) {
                        DB::connection('DATA_MYSQL')->rollBack();

                        return response()->json([
                            'message' => 'Silahkan periksa kembali kelas/tahun akademik siswa',
                            'nis' => $nis,
                            'thn_aka' => $thnAka,
                            'kelas' => $kelas,
                        ], 422);
                    }

                    InputSiswaProcedure::call(
                        $nis,
                        (string) ($item['nama'] ?? ''),
                        $kelas,
                        $sekolah,
                        (string) ($item['angkatan'] ?? ''),
                        $item['alamat'] ?? null,
                        $item['gender'] ?? null,
                        $this->resolveOrtuForDb($item),
                    );

                    $saved++;
                }

                if ($saved === 0) {
                    DB::connection('DATA_MYSQL')->rollBack();

                    return response()->json(['message' => 'Tidak ada data siswa yang berhasil diproses'], 422);
                }
            } elseif ($request->metode == '2') {
                $rows = array_filter($data, fn ($item) => !empty($item['nodaftar'] ?? null));

                foreach ($rows as $item) {
                    $item = $this->normalizeImportItem($item);
                    $lookupKey = $item['nodaftar'] ?? '';

                    if (strlen($lookupKey) > 10) {
                        continue;
                    }

                    $existingCust = scctcust::where('NUM2ND', $item['nodaftar'])->first();
                    $thnAka = mst_thn_aka::where('thn_aka', $item['angkatan'])->first();
                    $kelas = mst_kelas::findForImport($item['unit'], $item['kelas'], $item['kelompok']);

                    if (!$thnAka || !$kelas) {
                        Log::warning('export_import_data.validateData.missing_reference', [
                            'nis' => $item['nis'] ?? null,
                            'nodaftar' => $item['nodaftar'] ?? null,
                            'angkatan' => $item['angkatan'] ?? null,
                            'unit' => $item['unit'] ?? null,
                            'kelas' => $item['kelas'] ?? null,
                            'kelompok' => $item['kelompok'] ?? null,
                            'thn_aka_found' => (bool) $thnAka,
                            'kelas_found' => (bool) $kelas,
                            'sekolah' => $sekolah?->CODE01,
                        ]);

                        DB::connection('DATA_MYSQL')->rollBack();

                        return response()->json([
                            'message' => 'Silahkan periksa kembali kelas/thn_aka siswa',
                            'thn_aka' => $thnAka,
                            'kelas' => $kelas,
                        ], 422);
                    }

                    if (!$existingCust) {
                        if (!empty($item['nis'])) {
                            $existingNis = scctcust::where('NOCUST', $item['nis'])->first();
                            if ($existingNis) {
                                DB::connection('DATA_MYSQL')->rollBack();

                                return response()->json(['message' => 'Gagal, siswa dengan NIS :' . $item['nis'] . ' sudah ada!'], 422);
                            }
                        }

                        scctcust::create($this->buildScctcustPayload($item, $sekolah, $kelas, $thnAka));
                    } else {
                        $existingCust->update($this->buildScctcustPayload(
                            $item,
                            $sekolah,
                            $kelas,
                            $thnAka,
                            true,
                            $existingCust,
                        ));
                    }
                }
            } elseif ($request->metode == '3') {
                $rows = array_filter($data, fn ($item) => !empty($item['nis'] ?? null));

                foreach ($rows as $item) {
                    $item = $this->normalizeImportItem($item);
                    if (strlen((string) ($item['nis'] ?? '')) > 10) {
                        continue;
                    }

                    $existingCust = scctcust::where('NOCUST', $item['nis'])->first();
                    $kelas = mst_kelas::findForImport($item['unit'], $item['kelas'], $item['kelompok']);

                    if ($existingCust && $kelas) {
                        $existingCust->update([
                            'CODE02' => $kelas->unit,
                            'DESC02' => $kelas->jenjang,
                            'CODE03' => $kelas->id,
                            'DESC03' => $kelas->kelas,
                        ]);
                    }
                }
            } elseif ($request->metode == '4') {
                $rows = array_filter($data, fn ($item) => !empty($item['nodaftar'] ?? null) && !empty($item['nis'] ?? null));

                foreach ($rows as $item) {
                    $item = $this->normalizeImportItem($item);
                    if (strlen((string) ($item['nodaftar'] ?? '')) > 10) {
                        continue;
                    }
                    if (strlen((string) ($item['nis'] ?? '')) > 10) {
                        continue;
                    }

                    $existingNis = scctcust::where('NOCUST', $item['nis'])->first();
                    if ($existingNis) {
                        DB::connection('DATA_MYSQL')->rollBack();

                        return response()->json(['message' => 'Gagal, NIS :' . $item['nis'] . ' sudah ada!'], 422);
                    }

                    $existingCust = scctcust::where('NUM2ND', $item['nodaftar'])->first();
                    if ($existingCust && in_array(trim((string) $existingCust->NOCUST), ['', '-'], true)) {
                        $existingCust->update([
                            'NOCUST' => $item['nis'],
                        ]);
                    }
                }
            }

            DB::connection('DATA_MYSQL')->commit();
            Cache::forget($this->cacheKey);

            return response()->json(['message' => 'Sukses, data siswa telah disimpan, silahkan periksa kembali'], 200);
        } catch (\Throwable $e) {
            DB::connection('DATA_MYSQL')->rollBack();

            Log::error('export_import_data.validateData.failed', [
                'metode' => $request->metode,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $response = ['message' => 'Gagal, data tidak dapat disimpan'];
            if (config('app.debug')) {
                $response['error'] = $e->getMessage();
            }

            return response()->json($response, 422);
        }
    }

    public function clearData()
    {
        Cache::forget($this->cacheKey);
        return response()->json(['message' => 'Data dibersihkan'], 200);
    }

    private function normalizeImportItem(array $item): array
    {
        $item['nis'] = isset($item['nis']) && $item['nis'] !== '' && $item['nis'] !== null
            ? (string) $item['nis']
            : null;
        $item['nodaftar'] = isset($item['nodaftar']) && $item['nodaftar'] !== '' && $item['nodaftar'] !== null
            ? (string) $item['nodaftar']
            : null;
        $item['gender'] = SchoolScope::parseGender($item['gender'] ?? $item['jenis_kelamin'] ?? $item['jk'] ?? null);

        return $item;
    }

    /** Nama ortu/wali utama (kolom ortu / genus / ayah di Excel). */
    private function resolveOrtuForDb(array $item): ?string
    {
        $ortu = trim((string) ($item['ortu'] ?? $item['genus'] ?? $item['ayah'] ?? ''));

        return $ortu !== '' ? $ortu : null;
    }

    /** Nama ortu kedua — hanya untuk file lama (kolom ibu). */
    private function resolveOrtuSecondForDb(array $item): ?string
    {
        $second = trim((string) ($item['ibu'] ?? ''));

        return $second !== '' ? $second : null;
    }

    private function resolveSekolahForImport(?string $unit, ?mst_kelas $kelas): ?mst_sekolah
    {
        $unit = trim((string) $unit);

        if ($unit !== '') {
            $byUnit = mst_sekolah::query()
                ->where(function ($query) use ($unit) {
                    $query->where('DESC01', 'like', '%' . $unit . '%')
                        ->orWhere('CODE01', $unit)
                        ->orWhereRaw('UPPER(TRIM(DESC01)) = ?', [strtoupper($unit)]);
                })
                ->first();

            if ($byUnit) {
                return $byUnit;
            }
        }

        if (!$kelas) {
            return null;
        }

        $kelasUnit = trim((string) ($kelas->unit ?? ''));

        return mst_sekolah::query()
            ->where(function ($query) use ($kelasUnit) {
                $query->where('DESC01', 'like', '%' . $kelasUnit . '%')
                    ->orWhere('CODE01', $kelasUnit)
                    ->orWhereRaw('UPPER(TRIM(DESC01)) = ?', [strtoupper($kelasUnit)]);
            })
            ->first();
    }

    private function buildScctcustPayload(
        array $item,
        mst_sekolah $unit,
        mst_kelas $kelas,
        mst_thn_aka $thn_aka,
        bool $metodeByNodaftar = false,
        ?scctcust $existingCust = null,
    ): array {
        $payload = [
            'NOCUST' => $item['nis'] ?? '-',
            'NMCUST' => $item['nama'],
            'NUM2ND' => $item['nodaftar'] ?? '-',
            'STCUST' => 1,
            'CODE01' => $unit->CODE01,
            'DESC01' => $unit->DESC01,
            'CODE02' => $kelas->unit,
            'DESC02' => $kelas->jenjang,
            'CODE03' => $kelas->id,
            'DESC03' => $kelas->kelas,
            'CODE04' => SchoolScope::parseGender($item['gender'] ?? null),
            'DESC04' => $thn_aka->thn_aka,
            'DESC05' => $item['alamat'] ?? null,
            'GENUS' => $this->resolveOrtuForDb($item),
            'LastUpdate' => Carbon::now(),
        ];

        if ($existingCust) {
            $payload['NOCUST'] = $metodeByNodaftar ? ($item['nis'] ?? $existingCust->NOCUST) : $existingCust->NOCUST;
            $payload['NUM2ND'] = $metodeByNodaftar
                ? $existingCust->NUM2ND
                : ($item['nodaftar'] ?? $existingCust->NUM2ND ?? '-');

            return $payload;
        }

        $payload['CUSTID'] = scctcust::nextCustId();

        return $payload;
    }
}
