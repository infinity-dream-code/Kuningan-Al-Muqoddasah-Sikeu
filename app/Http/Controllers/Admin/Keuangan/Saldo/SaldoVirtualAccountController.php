<?php

namespace App\Http\Controllers\Admin\Keuangan\Saldo;

use App\Exports\SaldoVirtualAccountDetailExport;
use App\Http\Controllers\Controller;
use App\Models\mst_kelas;
use App\Models\mst_sekolah;
use App\Models\mst_thn_aka;
use App\Models\scctcust;
use App\Models\sccttran;
use App\Support\MultiVa;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Mockery\Exception;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SaldoVirtualAccountController extends Controller
{
    public ?string $sekolah = null;
    public string $datasUrl = '';
    public string $detailDatasUrl = '';
    public string $columnsUrl = '';
    private string $title = "Saldo";
    private string $mainTitle = 'Saldo';
    private string $dataTitle = 'Saldo VA Open';
    private string $showTitle = 'Detail Saldo VA Open';
    private string $cacheKey = 'saldo_virtual_account';
    protected string $reffBank = MultiVa::OPEN;
    protected string $routeBase = 'admin.keuangan.saldo.saldo-virtual-account';

    /** Pembayaran manual cash — tidak masuk saldo/jurnal VA. */
    private const FIDBANK_MANUAL_CASH = '1140000';

    /** Tampilkan semua transaksi sccttran kecuali manual cash (1140000). */
    private function excludeManualCashScope($query, string $fidBankColumn = 'FIDBANK')
    {
        return $query->where(function ($q) use ($fidBankColumn) {
            $q->whereNull($fidBankColumn)
                ->orWhereRaw("TRIM(COALESCE(CAST({$fidBankColumn} AS CHAR), '')) = ''")
                ->orWhereRaw("TRIM(COALESCE(CAST({$fidBankColumn} AS CHAR), '')) != ?", [self::FIDBANK_MANUAL_CASH]);
        });
    }

    private array $allowedFilters = [
        'kelas' => 'scctcust.DESC02',
        'sekolah' => 'scctcust.CODE01',
        'siswa' => 'scctcust.nmcust',
        'angkatan' => 'scctcust.DESC04',
    ];

    private function resolveScopedSchoolCodes(): array
    {
        if (blank($this->sekolah)) {
            return [];
        }

        return [trim((string) $this->sekolah)];
    }

    private function applyFilterQuery($query, array $filters): void
    {
        foreach ($filters as $filter) {
            if (($filter[0] ?? null) === 'whereRaw') {
                $query->whereRaw($filter[1], $filter[2] ?? []);
                continue;
            }
            if (count($filter) === 3) {
                if (($filter[1] ?? null) === 'in' && is_array($filter[2] ?? null)) {
                    $query->whereIn($filter[0], $filter[2]);
                } else {
                    $query->where($filter[0], $filter[1], $filter[2]);
                }
            } elseif (count($filter) === 4) {
                if ($filter[3] == 'whereBetween') {
                    $query->whereBetween($filter[0], [$filter[1], $filter[2]]);
                } else {
                    $query->{$filter[3]}($filter[0], $filter[1], $filter[2]);
                }
            }
        }
    }

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (Auth::check()) {
                $this->sekolah = Auth::user()->sekolah;
            }
            return $next($request);
        });

        $this->title = 'Keuangan';
        $this->mainTitle = 'Saldo';
        $this->dataTitle = MultiVa::saldoPageTitle($this->reffBank);
        $this->showTitle = 'Detail ' . $this->dataTitle;
        $this->cacheKey = 'saldo_va_' . $this->reffBank;

        $this->datasUrl = $this->namedRoute('get-data');
        $this->detailDatasUrl = '';
        $this->columnsUrl = $this->namedRoute('get-column');
    }

    protected function namedRoute(string $name, mixed $params = []): string
    {
        return route($this->routeBase . '.' . $name, $params);
    }

    protected function saldoQuery()
    {
        return MultiVa::saldoQuery($this->reffBank);
    }

    protected function transQuery()
    {
        return MultiVa::transQuery($this->reffBank);
    }

    protected function transListQuery()
    {
        $view = MultiVa::resolveExistingView([
            MultiVa::transView($this->reffBank),
            'v_trans_saldo',
            'v_trans_saldo_multivapertagihan93',
            'v_trans_saldo_multivapertagihan1',
            'v_trans_saldo_multivapertagihan94',
            'v_trans_saldo_multivapertagihan2',
            'v_trans_saldo_va',
        ]);

        if ($view) {
            return DB::connection('DATA_MYSQL')
                ->table($view . ' as trx')
                ->leftJoin('scctcust as c', 'c.NOCUST', '=', 'trx.NOCUST')
                ->select([
                    'trx.TRXDATE as TRXDATE',
                    'trx.KETERANGAN as KETERANGAN',
                    'trx.DEBET as DEBET',
                    'trx.KREDIT as KREDIT',
                    'trx.NOCUST as NOCUST',
                    'trx.NUM2ND as NUM2ND',
                    'trx.NOREFF as NOREFF',
                    'c.NMCUST as NMCUST',
                    'c.CODE01 as CODE01',
                    'c.CODE02 as CODE02',
                    'c.DESC02 as DESC02',
                    'c.DESC03 as DESC03',
                ]);
        }

        return DB::connection('DATA_MYSQL')
            ->table('sccttran as trx')
            ->leftJoin('scctcust as c', 'c.CUSTID', '=', 'trx.CUSTID')
            ->where(function ($q) {
                $q->whereNull('trx.FIDBANK')
                    ->orWhereRaw("TRIM(COALESCE(CAST(trx.FIDBANK AS CHAR), '')) = ''")
                    ->orWhereRaw("TRIM(COALESCE(CAST(trx.FIDBANK AS CHAR), '')) != ?", ['1140000']);
            })
            ->select([
                'trx.TRXDATE as TRXDATE',
                DB::raw('COALESCE(trx.METODE, trx.NOREFF, "") as KETERANGAN'),
                'trx.DEBET as DEBET',
                'trx.KREDIT as KREDIT',
                'c.NOCUST as NOCUST',
                'c.NUM2ND as NUM2ND',
                'trx.NOREFF as NOREFF',
                'c.NMCUST as NMCUST',
                'c.CODE01 as CODE01',
                'c.CODE02 as CODE02',
                'c.DESC02 as DESC02',
                'c.DESC03 as DESC03',
            ]);
    }

    protected function applySiswaToTrans($query, ?scctcust $siswa, string $nocustCol = 'NOCUST', string $num2ndCol = 'NUM2ND')
    {
        if (!$siswa) {
            return $query;
        }

        if ($siswa->NOCUST) {
            return $query->where($nocustCol, $siswa->NOCUST);
        }

        return $query->where($num2ndCol, $siswa->NUM2ND ?? '');
    }

    protected function formatNova(mixed $nis): string
    {
        return scctcust::formatVA(MultiVa::prefix($this->reffBank), $nis);
    }

    public function index()
    {
        $schoolCodes = $this->resolveScopedSchoolCodes();

        $data['thn_aka'] = mst_thn_aka::getMstThnAkaAttributes();
        $data['sekolah'] = mst_sekolah::select(['CODE01', 'DESC01'])
            ->when(!empty($schoolCodes), function ($query) use ($schoolCodes) {
                $query->whereIn('CODE01', $schoolCodes);
            })
            ->orderBy('DESC01')
            ->get();
        $data['kelas'] = mst_kelas::dropdownQuery($this->sekolah)
            ->orderByRaw("CASE WHEN jenjang REGEXP '^[0-9]+$' THEN 0 ELSE 1 END, jenjang")
            ->orderByRaw("CASE WHEN kelas REGEXP '^[0-9]+$' THEN 0 ELSE 1 END, kelas")
            ->get();
        $data['title'] = $this->title;
        $data['mainTitle'] = $this->mainTitle;
        $data['dataTitle'] = $this->dataTitle;
        //        $data['showTitle'] = $this->showTitle;
        $data['columnsUrl'] = $this->namedRoute('get-column');
        $data['datasUrl'] = $this->namedRoute('get-data');
        $data['dataTransaksiUrl'] = $this->namedRoute('data-transaksi.index');

        return view('admin.keuangan.saldo.saldo_virtual_account.index', $data);
    }

    public function show($id)
    {
        try {
            $data['title'] = $this->title;
            $data['mainTitle'] = $this->mainTitle;
            $data['dataTitle'] = $this->dataTitle;
            $data['showTitle'] = $this->showTitle;
            $data['indexUrl'] = $this->namedRoute('index');
            $data['columnsUrl'] = $this->namedRoute('transaksi.get-column');
            $data['datasUrl'] = $this->namedRoute('transaksi.get-data', ['CUSTID' => $id]);
            $data['exportTransaksiUrl'] = $this->namedRoute('export', ['id' => $id]);

            $data['siswa'] = scctcust::find($id);

            if ($data['siswa']) {
                if ($data['siswa']->NOCUST && $data['siswa']->NOCUST != '-') {
                    $NOVA = $this->formatNova($data['siswa']->NOCUST);
                } else {
                    $NOVA = $this->formatNova($data['siswa']->NUM2ND);
                }
                $data['siswa']->NOVA = $NOVA;

                $trxQuery = $this->applySiswaToTrans($this->transQuery(), $data['siswa']);
                $data['totalKredit'] = (int) (clone $trxQuery)->sum('KREDIT');
                $data['totalDebet'] = (int) (clone $trxQuery)->sum('DEBET');
//                $data['siswa']-> = $NOVA;
            } else {
                throw new Exception('Siswa tidak ditemukan');
            }

            return view('admin.keuangan.saldo.saldo_virtual_account.show', $data);
        } catch (\Exception $e) {
            return redirect()->route($this->routeBase . '.index')->with('error', 'Siswa tidak ditemukan!');
        }
    }

    public function exportTransaksi(Request $request): BinaryFileResponse
    {
        $siswaInput = trim((string) $request->query('siswa', ''));
        if ($siswaInput === '') {
            abort(422, 'Masukkan NIS/Nama siswa di filter terlebih dahulu.');
        }

        $siswa = $this->resolveCustFromSiswaInput($siswaInput);
        if (!$siswa) {
            abort(404, 'Siswa tidak ditemukan.');
        }

        return $this->exportDetail($siswa->CUSTID);
    }

    public function exportDetail($id): BinaryFileResponse
    {
        $siswa = scctcust::query()->where('CUSTID', $id)->first();
        if (!$siswa) {
            abort(404, 'Siswa tidak ditemukan');
        }

        $transactions = $this->getCustTransactions($id);
        $totalKredit = (int) $transactions->sum('KREDIT');
        $totalDebet = (int) $transactions->sum('DEBET');
        $saldo = $totalKredit - $totalDebet;

        if ($siswa->NOCUST && $siswa->NOCUST != '-') {
            $nova = $this->formatNova($siswa->NOCUST);
        } else {
            $nova = $this->formatNova($siswa->NUM2ND);
        }

        $nis = preg_replace('/\D/', '', (string) ($siswa->NOCUST ?? $siswa->nocust ?? $siswa->CUSTID));
        $filename = 'transaksi-saldo-va-' . ($nis !== '' ? $nis : $siswa->CUSTID) . '-' . date('Ymd-His') . '.xlsx';

        return Excel::download(
            new SaldoVirtualAccountDetailExport(
                [
                    'nis' => (string) ($siswa->NOCUST ?? $siswa->nocust ?? '-'),
                    'nama' => (string) ($siswa->NMCUST ?? $siswa->nmcust ?? '-'),
                    'unit' => (string) ($siswa->CODE02 ?? '-'),
                    'kelas' => (string) ($siswa->DESC02 ?? '-'),
                    'kelompok' => (string) ($siswa->DESC03 ?? '-'),
                    'nova' => (string) ($nova ?? '-'),
                ],
                $transactions,
                $totalDebet,
                $totalKredit,
                $saldo,
            ),
            $filename
        );
    }

    private function resolveCustFromSiswaInput(string $input): ?scctcust
    {
        $query = scctcust::query();
        $scopedCodes = $this->resolveScopedSchoolCodes();
        if (!empty($scopedCodes)) {
            $query->whereIn('CODE01', $scopedCodes);
        }

        if (ctype_digit($input)) {
            $byNis = (clone $query)->where('NOCUST', $input)->first();
            if ($byNis) {
                return $byNis;
            }

            return (clone $query)->where('CUSTID', $input)->first();
        }

        $matches = (clone $query)
            ->where('NMCUST', 'like', '%' . $input . '%')
            ->limit(2)
            ->get();

        if ($matches->count() === 1) {
            return $matches->first();
        }

        if ($matches->count() > 1) {
            abort(422, 'Data siswa tidak unik. Gunakan NIS untuk export transaksi.');
        }

        return null;
    }

    private function getCustTransactions(string|int $custId)
    {
        $siswa = scctcust::query()->where('CUSTID', $custId)->first();
        $query = $this->applySiswaToTrans($this->transQuery(), $siswa);

        return $query->orderBy('TRXDATE', 'desc')
            ->get()
            ->map(function ($item) {
                $item->METODE = $item->KETERANGAN ?? $item->METODE ?? null;
                return $item;
            });
    }

    public function getColumn(Request $request)
    {
        return [
            ['data' => null, 'name' => 'no', 'columnType' => 'row', 'exportable' => true],
            ['data' => 'NOCUST', 'name' => 'NIS', 'searchable' => true, 'orderable' => true, 'exportable' => true],
            ['data' => 'NOVA', 'name' => 'NO VA', 'exportable' => true],
            ['data' => 'NMCUST', 'name' => 'NAMA', 'searchable' => true, 'orderable' => true, 'exportable' => true],
            ['data' => 'CODE02', 'name' => 'Unit', 'searchable' => true, 'orderable' => true, 'exportable' => true],
            ['data' => 'DESC02', 'name' => 'Kelas', 'searchable' => true, 'orderable' => true, 'exportable' => true],
            ['data' => 'DESC03', 'name' => 'Kelompok', 'searchable' => true, 'orderable' => true, 'exportable' => true],
            ['data' => 'NUM2ND', 'name' => 'No Pendaftaran', 'searchable' => true, 'orderable' => true, 'exportable' => true],
            ['data' => 'DESC04', 'name' => 'Angkatan', 'searchable' => true, 'orderable' => true, 'exportable' => true],
            ['data' => 'saldo', 'name' => 'Saldo', 'orderable' => true, 'columnType' => 'currency', 'className' => 'text-end', 'exportable' => true],
            [
                'data' => 'print',
                'name' => '',
                'columnType' => 'button',
                'className' => 'text-center',
                'button' => 'link',
                'buttonLink' => $this->namedRoute('show', ':id'),
                'buttonText' => 'Detail Transaksi',
                'noCaption' => true,
                'buttonClass' => 'btn btn-sm btn-primary btn-icon btn-print-tagihan',
                'buttonIcon' => 'ri-profile-line',
                'exportable' => false,
            ],
        ];
    }

    public function getData(Request $request)
    {
        try {
            return $this->buildGetDataResponse($request);
        } catch (\Throwable $e) {
            Log::error('saldo-va.getData.failed', [
                'reff' => $this->reffBank,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'draw' => (int) $request->get('draw'),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'message' => 'Gagal memuat data saldo. Silakan coba lagi.',
            ], 200);
        }
    }

    private function buildGetDataResponse(Request $request)
    {
        $draw = $request->get('draw');
        $start = $request->get("start");
        $rowperpage = $request->get("length");

        $columnName_arr = $request->get('columns');
        $search_arr = $request->get('search');

        $defaultColumn = 'NOCUST';
        $defaultOrder = 'asc';

        if ($request->has('order') && !empty($request->get('order'))) {
            $columnIndex_arr = $request->get('order');
            $columnIndex = $columnIndex_arr[0]['column'] ?? 0;
            $columnSortOrder = $columnIndex_arr[0]['dir'] ?? $defaultOrder;
            $columnName = $columnName_arr[$columnIndex]['data'] ?? $defaultColumn;
        } else {
            $columnName = $defaultColumn;
            $columnSortOrder = $defaultOrder;
        }

        $searchValue = $search_arr['value'] ?? '';

        if (!$columnName || $columnName == 'no' || $columnName === 'NOVA' || $columnName === 'print') {
            $columnName = $defaultColumn;
            $columnSortOrder = $defaultOrder;
        }

        $columnName = match ($columnName) {
            'saldo', 'SALDO' => 'SALDO',
            'NOCUST' => 'NOCUST',
            'NMCUST' => 'NMCUST',
            'CODE02' => 'CODE02',
            'DESC02' => 'DESC02',
            'DESC03' => 'DESC03',
            'NUM2ND' => 'NUM2ND',
            'DESC04' => 'DESC04',
            'CUSTID' => 'CUSTID',
            default => 'NOCUST',
        };

        $query = $this->saldoQuery();

        $filter = $request->input('filter');
        if ($filter) {
            foreach ($filter as $key => $val) {
                if (strtolower((string) $val) == 'all' || $val === null || $val === '') {
                    continue;
                }
                if ($key == 'siswa') {
                    if (is_numeric($val)) {
                        $query->where('NOCUST', 'like', $val);
                    } else {
                        $query->where('NMCUST', 'like', '%' . $val . '%');
                    }
                } elseif ($key == 'kelas') {
                    $query->where('CODE03', '=', $val);
                } elseif ($key === 'sekolah') {
                    $query->where('CODE01', '=', trim((string) $val));
                } elseif ($key == 'saldo_positif' && (string) $val === '1') {
                    $query->where('SALDO', '>', 0);
                } elseif ($key == 'angkatan') {
                    $query->where('DESC04', '=', $val);
                }
            }
        }

        $scopedCodes = $this->resolveScopedSchoolCodes();
        if (!empty($scopedCodes)) {
            $query->whereIn('CODE01', $scopedCodes);
        }

        if (!blank($searchValue)) {
            $sanitizeSearch = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $searchValue);
            $query->where(function ($q) use ($sanitizeSearch) {
                $q->where('NMCUST', 'like', '%' . $sanitizeSearch . '%')
                    ->orWhere('NOCUST', 'like', '%' . $sanitizeSearch . '%')
                    ->orWhere('NUM2ND', 'like', '%' . $sanitizeSearch . '%');
            });
        }

        $totalQuery = $this->saldoQuery()
            ->when(!empty($scopedCodes), fn ($q) => $q->whereIn('CODE01', $scopedCodes));
        $totalRecords = (int) $totalQuery->count();
        $totalRecordswithFilter = (int) (clone $query)->count();

        $pageQuery = (clone $query)->orderBy($columnName, $columnSortOrder)->skip($start);
        if ((int) $rowperpage > 0) {
            $pageQuery->take($rowperpage);
        }

        $records = $pageQuery
            ->get()
            ->map(function ($item) {
                $item->item_id = $item->CUSTID;
                $item->print = true;
                $item->saldo = $item->SALDO ?? 0;
                if ($item->NOCUST && $item->NOCUST != '-') {
                    $item->NOVA = $this->formatNova($item->NOCUST);
                } else {
                    $item->NOVA = $this->formatNova($item->NUM2ND);
                }
                unset($item->CUSTID);
                return $item;
            })->toArray();

        return response()->json([
            "draw" => intval($draw),
            "recordsTotal" => $totalRecords,
            "recordsFiltered" => $totalRecordswithFilter,
            "data" => $records,
        ]);
    }

    public function getColumnTran()
    {
        return [
            ['data' => null, 'columnType' => 'row', 'name' => 'No', 'exportable' => true],
            ['data' => 'METODE', 'name' => 'Metode', 'orderable' => true, 'exportable' => true],
            ['data' => 'TRXDATE', 'name' => 'Tanggal Transaksi', 'orderable' => true, 'columnType' => 'timestamp', 'exportable' => true],
            ['data' => 'DEBET', 'name' => 'Debet', 'orderable' => true, 'className' => 'dt-right', 'columnType' => 'currency', 'exportable' => true],
            ['data' => 'KREDIT', 'name' => 'Kredit', 'orderable' => true, 'className' => 'dt-right', 'columnType' => 'currency', 'exportable' => true],
            ['data' => 'NOREFF', 'name' => 'No Ref', 'orderable' => true, 'exportable' => true],
        ];
    }

    public function getDataTran(Request $request)
    {
        $custid = $request->input('CUSTID');
        $siswa = $custid ? scctcust::query()->where('CUSTID', $custid)->first() : null;

        $draw = $request->get('draw');
        $start = $request->get("start");
        $rowperpage = $request->get("length");

        $columnName_arr = $request->get('columns');
        $search_arr = $request->get('search');

        $defaultColumn = 'TRXDATE';
        $defaultOrder = 'desc';

        if ($request->has('order') && !empty($request->get('order'))) {
            $columnIndex_arr = $request->get('order');
            $columnIndex = $columnIndex_arr[0]['column'] ?? 0;
            $columnSortOrder = $columnIndex_arr[0]['dir'] ?? $defaultOrder;
            $columnName = $columnName_arr[$columnIndex]['data'] ?? $defaultColumn;
        } else {
            $columnName = $defaultColumn;
            $columnSortOrder = $defaultOrder;
        }

        $searchValue = $search_arr['value'] ?? '';

        $columnName = match ($columnName) {
            'METODE', 'KETERANGAN' => 'KETERANGAN',
            'DEBET' => 'DEBET',
            'KREDIT' => 'KREDIT',
            'NOREFF' => 'NOREFF',
            'NOCUST' => 'NOCUST',
            'no', '', null => 'TRXDATE',
            default => 'TRXDATE',
        };

        $query = $this->applySiswaToTrans($this->transQuery(), $siswa);

        if (!blank($searchValue)) {
            $sanitizeSearch = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $searchValue);
            $query->where(function ($q) use ($sanitizeSearch) {
                $q->where('KETERANGAN', 'like', '%' . $sanitizeSearch . '%')
                    ->orWhere('NOREFF', 'like', '%' . $sanitizeSearch . '%')
                    ->orWhere('NOCUST', 'like', '%' . $sanitizeSearch . '%');
            });
        }

        $totalRecords = (clone $query)->count();
        $totalRecordswithFilter = $totalRecords;

        $pageQuery = (clone $query)->orderBy($columnName, $columnSortOrder)->skip($start);
        if ((int) $rowperpage > 0) {
            $pageQuery->take($rowperpage);
        }

        $records = $pageQuery
            ->get()
            ->map(function ($item) {
                $item->METODE = $item->KETERANGAN ?? null;
                return $item;
            })
            ->toArray();

        $totalKredit = 0;
        $totalDebet = 0;
        if ($siswa) {
            $sumQuery = $this->applySiswaToTrans($this->transQuery(), $siswa);
            $totalKredit = (int) (clone $sumQuery)->sum('KREDIT');
            $totalDebet = (int) (clone $sumQuery)->sum('DEBET');
        }

        $response = [
            "draw" => intval($draw),
            "recordsTotal" => $totalRecords,
            "recordsFiltered" => $totalRecordswithFilter,
            "data" => $records,
        ];

        if ($custid) {
            $response['totals'] = [
                'kredit' => ['location' => 4, 'value' => $totalKredit, 'columnType' => 'currency'],
                'debet' => ['location' => 3, 'value' => $totalDebet, 'columnType' => 'currency'],
            ];
        }

        return response()->json($response);
    }

    public function resolveCustSaldo(string|int|null $custId): int
    {
        return MultiVa::custSaldo($custId, $this->reffBank);
    }

    public function getSaldo(Request $request)
    {
        try {
            $custId = $request->input('siswa');
            $open = MultiVa::custSaldo($custId, MultiVa::OPEN);
            $payload = [
                'saldo' => $open,
                'saldo_open' => $open,
                'saldo_93' => $open,
            ];

            if (MultiVa::closeEnabled()) {
                $close = MultiVa::custSaldo($custId, MultiVa::CLOSE);
                $payload['saldo_close'] = $close;
                $payload['saldo_94'] = $close;
            }

            return response()->json($payload);
        } catch (\Throwable $e) {
            Log::error('getSaldo failed', [
                'siswa' => $request->input('siswa'),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'saldo' => 0,
                'saldo_open' => 0,
                'saldo_93' => 0,
                'message' => 'Saldo tidak dapat dimuat.',
            ]);
        }
    }

    public function transaksiIndex()
    {
        $data['title'] = $this->title;
        $data['mainTitle'] = $this->dataTitle;
        $data['pageTitle'] = 'Data Transaksi';
        $data['indexUrl'] = $this->namedRoute('index');
        $data['columnsUrl'] = $this->namedRoute('data-transaksi.get-column');
        $data['datasUrl'] = $this->namedRoute('data-transaksi.get-data');

        return view('admin.keuangan.saldo.saldo_virtual_account.data_transaksi', $data);
    }

    public function getColumnDataTransaksi()
    {
        return [
            ['data' => null, 'name' => 'no', 'columnType' => 'row', 'exportable' => true],
            ['data' => 'NOCUST', 'name' => 'NIS', 'searchable' => true, 'orderable' => true, 'exportable' => true],
            ['data' => 'NOVA', 'name' => 'No VA', 'exportable' => true],
            ['data' => 'NMCUST', 'name' => 'Nama', 'searchable' => true, 'orderable' => true, 'exportable' => true],
            ['data' => 'TRXDATE', 'name' => 'Tanggal', 'orderable' => true, 'columnType' => 'timestamp', 'exportable' => true],
            ['data' => 'METODE', 'name' => 'Metode', 'orderable' => true, 'exportable' => true],
            ['data' => 'DEBET', 'name' => 'Debet', 'orderable' => true, 'className' => 'text-end', 'columnType' => 'currency', 'exportable' => true],
            ['data' => 'KREDIT', 'name' => 'Kredit', 'orderable' => true, 'className' => 'text-end', 'columnType' => 'currency', 'exportable' => true],
            ['data' => 'NOREFF', 'name' => 'No Ref', 'orderable' => true, 'exportable' => true],
            ['data' => 'CODE02', 'name' => 'Unit', 'orderable' => true, 'exportable' => true],
            ['data' => 'DESC02', 'name' => 'Kelas', 'orderable' => true, 'exportable' => true],
            ['data' => 'DESC03', 'name' => 'Kelompok', 'orderable' => true, 'exportable' => true],
        ];
    }

    public function getDataDataTransaksi(Request $request)
    {
        $draw = (int) $request->get('draw');
        $start = (int) $request->get('start', 0);
        $rowperpage = (int) $request->get('length', 25);

        $columnName_arr = $request->get('columns', []);
        $search_arr = $request->get('search', []);
        $searchValue = $search_arr['value'] ?? '';

        $defaultColumn = 'trx.TRXDATE';
        $defaultOrder = 'desc';
        $columnName = $defaultColumn;
        $columnSortOrder = $defaultOrder;

        if ($request->has('order') && !empty($request->get('order'))) {
            $order_arr = $request->get('order');
            $columnIndex = $order_arr[0]['column'] ?? 0;
            $columnSortOrder = $order_arr[0]['dir'] ?? $defaultOrder;
            $requestedData = $columnName_arr[$columnIndex]['data'] ?? null;
            if ($requestedData && $requestedData !== 'no') {
                $columnName = match ($requestedData) {
                    'NOCUST' => 'trx.NOCUST',
                    'NMCUST', 'CODE02', 'DESC02', 'DESC03' => 'c.' . $requestedData,
                    'NOVA' => 'trx.NOCUST',
                    'METODE' => 'trx.KETERANGAN',
                    'TRXDATE' => 'trx.TRXDATE',
                    'DEBET' => 'trx.DEBET',
                    'KREDIT' => 'trx.KREDIT',
                    'NOREFF' => 'trx.NOREFF',
                    default => 'trx.TRXDATE',
                };
            }
        }

        $query = $this->transListQuery();

        $filter = $request->input('filter', []);
        foreach ($filter as $key => $val) {
            if ($val === null || $val === '' || strtolower((string) $val) === 'all') {
                continue;
            }

            if (in_array($key, ['dari_tanggal', 'sampai_tanggal'], true) && preg_match('/^\d{2}-\d{2}-\d{4}$/', (string) $val)) {
                $date = Carbon::createFromFormat('d-m-Y', $val);
                if ($date) {
                    $query->where(
                        'trx.TRXDATE',
                        $key === 'dari_tanggal' ? '>=' : '<=',
                        $key === 'dari_tanggal' ? $date->copy()->startOfDay() : $date->copy()->endOfDay()
                    );
                }
            }
        }

        $schoolCodes = $this->resolveScopedSchoolCodes();
        if (!empty($schoolCodes)) {
            $query->whereIn('c.CODE01', $schoolCodes);
        }

        if (!blank($searchValue)) {
            $sanitizeSearch = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $searchValue);
            $query->where(function ($q) use ($sanitizeSearch) {
                $q->where('trx.NOCUST', 'like', '%' . $sanitizeSearch . '%')
                    ->orWhere('trx.NUM2ND', 'like', '%' . $sanitizeSearch . '%')
                    ->orWhere('trx.NOREFF', 'like', '%' . $sanitizeSearch . '%')
                    ->orWhere('trx.KETERANGAN', 'like', '%' . $sanitizeSearch . '%')
                    ->orWhere('c.NMCUST', 'like', '%' . $sanitizeSearch . '%');
            });
        }

        $totalRecords = (int) $this->transQuery()->count();
        $totalRecordswithFilter = (clone $query)->count();

        $records = (clone $query)
            ->orderBy($columnName, $columnSortOrder)
            ->skip($start)
            ->take($rowperpage > 0 ? $rowperpage : 25)
            ->get()
            ->map(function ($item) {
                $item->METODE = $item->KETERANGAN ?? null;
                if ($item->NOCUST && $item->NOCUST != '-') {
                    $item->NOVA = $this->formatNova($item->NOCUST);
                } else {
                    $item->NOVA = $this->formatNova($item->NUM2ND);
                }

                return $item;
            })
            ->toArray();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalRecordswithFilter,
            'data' => $records,
        ]);
    }
}
