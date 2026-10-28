<?php

namespace App\Http\Controllers\Admin\Keuangan\PenerimaanSiswa;

use App\Http\Controllers\Controller;
use App\Models\mst_kelas;
use App\Models\mst_sekolah;
use App\Models\mst_tagihan;
use App\Models\mst_thn_aka;
use App\Models\u_akun;
use App\Models\scctbill;
use App\Models\scctbill_detail;
use App\Models\scctcust;
use App\Models\sccttran;
use App\Models\User;
use App\Support\CacheHandler;
use App\Support\FilterHandler;
use App\Support\MetodeBayarHelper;
use App\Support\MultiVa;
use App\Support\SchoolScope;
use App\Support\TagihanPaymentReversal;
use Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DataPenerimaanController extends Controller
{
    public ?string $sekolah = null;
    private string $title = 'Keuangan';
    private string $mainTitle = 'Data Pembayaran';
    private string $cacheKey = 'data penerimaan';
    private array $allowedFilters = [
        'dari_tanggal' => 'scctbill.PAIDDT_start',
        'sampai_tanggal' => 'scctbill.PAIDDT_end',
        'tahun_akademik' => 'scctbill.BTA',
        'post' => 'scctbill.BILLNM',
        'kelas' => 'scctcust.DESC02',
        'nama' => 'scctcust.NMCUST',
        'nis' => 'scctcust.NOCUST',
        'sekolah' => 'scctcust.CODE01',
        'angkatan' => 'scctcust.DESC04',
        'periode_mulai' => 'scctbill.BILLAC_start',
        'periode_akhir' => 'scctbill.BILLAC_end',
        'bank' => 'sccttran.FIDBANK',
    ];

    public function __construct()
    {
        $key = Str::slug($this->cacheKey) . '_cache_version';

        Cache::add($key, 1);

        $this->middleware(function ($request, $next) {
            if (\Illuminate\Support\Facades\Auth::check()) {
                $user = Auth::user();
                $this->sekolah = $user->sekolah ?? $user->unit ?? null;
            }
            return $next($request);
        });
    }

    public function index()
    {
        $data['title'] = $this->title;
        $data['mainTitle'] = $this->mainTitle;
        $data['columnsUrl'] = $this->columnsUrl();
        $data['datasUrl'] = $this->datasUrl();
        $data['post'] = mst_tagihan::select(['tagihan'])->orderBy('urut')->get();
        $data['thn_aka'] = mst_thn_aka::getMstThnAkaAttributes();
        $data['kelas'] = mst_kelas::getMstKelasAttributes($this->sekolah);
        $data['tanda_tangan'] = User::getTandaTanganBase64();
        $data['sekolah'] = mst_sekolah::when($this->sekolah, function ($query) {
            $query->where(function ($q) {
                $q->where("CODE01", $this->sekolah)
                    ->orWhere("DESC01", $this->sekolah);
            });
        })->get();
//        dd($data['tanda_tangan']);
        $scctbillModel = new scctbill();
        $allowedBanks = [
            '1140000' => 'Manual Cash',
            '1140002' => 'Manual SALDO',
            '1140001' => 'Manual BMI',
            '1140003' => 'Transfer Bank Lain',
            '6' => 'ANDROID',
        ];
        $data['bank'] = array_intersect_key($allowedBanks, $scctbillModel->metodeBayar)
            ?: $allowedBanks;

        return view('admin.keuangan.penerimaan_siswa.data_penerimaan', $data);
    }

    private function columnsUrl(): string
    {
        return route('admin.keuangan.penerimaan-siswa.data-penerimaan.get-column');
    }

    private function datasUrl(): string
    {
        return route('admin.keuangan.penerimaan-siswa.data-penerimaan.get-data');
    }

    public function getColumn()
    {
        return [
            [
                'data' => 'detail_trx',
                'name' => '+',
                'orderable' => false,
                'dataVal' => false,
                'columnType' => 'button',
                'className' => 'text-center exclude-selection',
                'excludeFromSelection' => true,
                'button' => 'action',
                'buttonText' => '+',
                'buttonClass' => 'btn btn-sm btn-primary btn-detail-trx',
                'buttonLink' => '#',
                'noCaption' => false,
                'exportable' => false,
                'duplicate' => false,
            ],
            ['data' => null, 'name' => 'no', 'columnType' => 'row', 'exportable' => true],
            ['data' => 'nocust', 'name' => 'NIS', 'searchable' => true, 'orderable' => true, 'exportable' => true],
            ['data' => 'nmcust', 'name' => 'NAMA', 'searchable' => true, 'orderable' => true, 'exportable' => true],
            ['data' => 'CODE02', 'name' => 'Unit', 'searchable' => true, 'orderable' => true, 'exportable' => true],
            ['data' => 'DESC02', 'name' => 'Kelas', 'searchable' => true, 'orderable' => true, 'exportable' => true],
            ['data' => 'DESC03', 'name' => 'Kelompok', 'searchable' => true, 'orderable' => true, 'exportable' => true],
            ['data' => 'BILLNM', 'name' => 'Nama Tagihan', 'searchable' => true, 'orderable' => true, 'exportable' => true],
            ['data' => 'BILLAM', 'name' => 'Nominal Bayar', 'searchable' => true, 'orderable' => true, 'columnType' => 'currency', 'className' => 'text-end', 'exportable' => true],
            ['data' => 'FIDBANK', 'name' => 'Metode', 'columnType' => 'custom_code_tagihan', 'searchable' => true, 'orderable' => true, 'exportable' => true],
            ['data' => 'PAIDDT', 'name' => 'Tanggal Bayar', 'columnType' => 'timestamp', 'searchable' => true, 'orderable' => true, 'exportable' => true],
            ['data' => 'BTA', 'name' => 'Tahun AKA', 'searchable' => true, 'orderable' => true, 'exportable' => true],
            [
                'data' => 'delete',
                'name' => '',
                'orderable' => false,
                'dataVal' => false,
                'columnType' => 'button',
                'className' => 'text-center exclude-selection',
                'excludeFromSelection' => true,
                'button' => 'action',
                'buttonText' => 'Reversal',
                'buttonClass' => 'btn btn-sm btn-warning btn-reversal',
                'buttonLink' => '#modal-delete',
                'buttonIcon' => 'ri-arrow-go-back-line me-2',
                'exportable' => false,
                'duplicate' => false,
            ],
        ];
    }

    public function getData(Request $request)
    {
        try {
            return $this->buildGetDataResponse($request);
        } catch (\Throwable $e) {
            Log::error('data-penerimaan.getData.failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'draw' => (int) $request->get('draw'),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'message' => 'Gagal memuat data. Silakan coba lagi.',
            ], 200);
        }
    }

    private function buildGetDataResponse(Request $request)
    {
        $draw = $request->get('draw');
        $start = $request->get("start");
        $rowperpage = $request->get("length");
        $columnIndex_arr = $request->get('order', []);
        $columnName_arr = $request->get('columns', []);
        $order_arr = $request->get('order', []);
        $search_arr = $request->get('search', []);
        $searchValue = $search_arr['value'] ?? '';

        $columnName = 'scctbill.PAIDDT';
        $columnSortOrder = 'desc';

        if (!empty($order_arr)) {
            $columnIndex = $columnIndex_arr[0]['column'] ?? null;
            if (
                $columnIndex !== null &&
                !empty($columnName_arr[$columnIndex]['data']) &&
                !in_array($columnName_arr[$columnIndex]['data'], ['no', 'AA', 'detail_trx', 'delete'], true)
            ) {
                $columnName = $this->resolveOrderColumn($columnName_arr[$columnIndex]['data']);
                $columnSortOrder = $order_arr[0]['dir'] ?? 'desc';
            }
        }

        $filters = [];
        $filterQuery = null;
        $androidBankFilter = null; // null | only | exclude

        $filter = FilterHandler::resolveFilters($request->input('filter'), $this->allowedFilters);
        if ($this->sekolah !== null) {
            $filter = array_merge($filter, [
                'scctcust.CODE01' => $this->sekolah,
            ]);
        }

        if ($filter) {
            foreach ($filter as $key => $val) {
                switch ($key) {
                    case 'sccttran.TRXDATE_start':
                    case 'scctbill.PAIDDT_start':
                        try {
                            $date = Carbon::createFromFormat('d-m-Y', (string) $val)->startOfDay();
                            $filters[] = ['scctbill.PAIDDT', '>=', $date];
                        } catch (\Throwable $e) {
                            // ignore invalid date filter
                        }
                        break;
                    case 'sccttran.TRXDATE_end':
                    case 'scctbill.PAIDDT_end':
                        try {
                            $date = Carbon::createFromFormat('d-m-Y', (string) $val)->endOfDay();
                            $filters[] = ['scctbill.PAIDDT', '<=', $date];
                        } catch (\Throwable $e) {
                            // ignore invalid date filter
                        }
                        break;
                    case 'scctbill.BILLAC_start':
                        $filters[] = ['scctbill.BILLAC', '>=', $val];
                        break;
                    case 'scctbill.BILLAC_end':
                        $filters[] = ['scctbill.BILLAC', '<=', $val];
                        break;
                    case 'scctcust.DESC02':
                        $val = explode("~~", $val);
                        if (count($val) == 3) {
                            $filters[] = ['scctcust.CODE02', '=', $val[0]];
                            $filters[] = ['scctcust.DESC02', '=', $val[1]];
                            $filters[] = ['scctcust.DESC03', '=', $val[2]];
                        }
                        break;
                    case 'scctbill.BILLNM':
                        if (is_array($val)) {
                            $billNames = array_values(array_filter(
                                $val,
                                fn ($item) => !is_null($item) && $item !== '' && strtolower((string) $item) !== 'all'
                            ));
                            if (!empty($billNames)) {
                                $filters[] = ['scctbill.BILLNM', 'in', $billNames];
                            }
                        } else {
                            $name = trim((string) $val);
                            if ($name !== '' && strtolower($name) !== 'all') {
                                $filters[] = ['scctbill.BILLNM', '=', $name];
                            }
                        }
                        break;
                    case 'scctcust.NMCUST':
                    case 'scctcust.nmcust':
                        $name = trim((string) $val);
                        if ($name !== '') {
                            $sanitized = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $name);
                            $filters[] = ['scctcust.NMCUST', 'like', '%' . $sanitized . '%'];
                        }
                        break;
                    case 'scctcust.NOCUST':
                    case 'scctcust.nocust':
                        $nis = trim((string) $val);
                        if ($nis !== '') {
                            $sanitized = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $nis);
                            $filters[] = ['scctcust.NOCUST', 'like', '%' . $sanitized . '%'];
                        }
                        break;
                    case 'sccttran.FIDBANK':
                        if ((string) $val === MetodeBayarHelper::ANDROID_FIDBANK) {
                            $androidBankFilter = 'only';
                        } elseif ((string) $val === '1140003') {
                            // Transfer Bank Lain: ambil dari scctbill.FIDBANK
                            $filters[] = ['scctbill.FIDBANK', '=', '1140003'];
                            $androidBankFilter = 'exclude';
                        } else {
                            // Manual Cash/BMI/SALDO: filter dari scctbill.FIDBANK
                            $filters[] = ['scctbill.FIDBANK', '=', $val];
                            $androidBankFilter = 'exclude';
                        }
                        break;
                    default:
                        ($key) && $filters[] = [$key, '=', $val];
                        break;
                }
            };

            if (!empty($filters)) {
                $filterQuery = function ($query) use ($filters) {
                    foreach ($filters as $filter) {
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
                };
            }
        }

        $whereAny = [
            'scctcust.nmcust',
            'scctcust.nocust',
            'scctcust.NUM2ND',
            'scctbill.BILLNM',
        ];

        $select = [
            'scctbill.AA',
            'scctbill.CUSTID',
            'scctbill.BILLNM',
            'scctbill.BILLAM as BILLAM_TOTAL',
            'scctbill.BILLPAID',
            'scctbill.BILLAC',
            'scctbill.PAIDST',
            'scctbill.PAIDDT',
            'scctbill.BTA',
            'scctbill.TRANSNO as BILL_TRANSNO',
            'scctbill.NOREFF as BILL_NOREFF',
            'scctbill.FIDBANK as BILL_FIDBANK',
            'scctcust.nocust',
            'scctcust.nmcust',
            'scctcust.CODE02',
            'scctcust.DESC02',
            'scctcust.DESC03',
            'scctcust.NUM2ND',
            'scctcust.GENUS',
            DB::raw('NULL as GENUS1'),
        ];
        MultiVa::pushBillVaSelect($select);

        $query = $this->lunasBillBaseQuery()
            ->when(!blank($searchValue), function ($query) use ($whereAny, $searchValue) {
                $query->where(function ($q) use ($whereAny, $searchValue) {
                    $sanitizeSearch = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $searchValue);
                    foreach ($whereAny as $column) {
                        $q->orWhere($column, 'like', '%' . $sanitizeSearch . '%');
                    }
                });
            })
            ->where(function ($query) use ($filterQuery) {
                if ($filterQuery) {
                    $filterQuery($query);
                }
            })
            ->when($androidBankFilter === 'only', function ($q) {
                MetodeBayarHelper::applyAndroidBankFilter($q);
            })
            ->when($androidBankFilter === 'exclude', function ($q) {
                MetodeBayarHelper::excludeMobileNoreff($q);
            });

        $cacheKey = CacheHandler::cacheKey($this->cacheKey, 'data_tagihan_lunas_count', $filter, $searchValue ?? '');

        $totalRecords = $this->total();

        $totalRecordswithFilter =
            Cache::remember(
                $cacheKey,
                now()->addMinutes(10),
                fn() => (clone $query)->count()
            );

        $totalNominalKey = CacheHandler::cacheKey($this->cacheKey, 'data_tagihan_lunas_sum', $filter, $searchValue ?? '');
        $totalNominal = (int) Cache::remember(
            $totalNominalKey,
            now()->addMinutes(10),
            function () use ($query) {
                return (int) (clone $query)->selectRaw(
                    'COALESCE(SUM(CAST(COALESCE(scctbill.BILLPAID, scctbill.BILLAM, 0) AS SIGNED)), 0) as total_nominal'
                )->value('total_nominal');
            }
        );

        $records = (clone $query)->orderBy($columnName, $columnSortOrder)
            ->select($select)
            ->skip($start)
            ->take($rowperpage)
            ->get()
            ->map(function ($item) {
                $billId = $item->AA;
                $nominalBayar = (int) ($item->BILLPAID ?? 0);
                if ($nominalBayar <= 0) {
                    $nominalBayar = (int) ($item->BILLAM_TOTAL ?? 0);
                }

                $item->item_id = $billId;
                $item->BILLAM = $nominalBayar;
                $item->FIDBANK = MetodeBayarHelper::resolveDisplayFidBank(
                    $item->BILL_FIDBANK ?? $item->FIDBANK ?? null,
                    $item->BILL_NOREFF ?? null
                );
                $item->NOREFF = $item->BILL_NOREFF ?? null;
                $item->delete = $billId && $nominalBayar > 0;
                $item->detail_trx = (bool) $billId;
                $item->NOCUST = $item->nocust;
                $item->NMCUST = $item->nmcust;

                return $item;
            })->toArray();

        $response = array(
            "draw" => intval($draw),
            "recordsTotal" => $totalRecords ?? 0,
            "recordsFiltered" => $totalRecordswithFilter ?? 0,
            "data" => $records ?? [],
            "totals" => [
                "billam" => [
                    "location" => 8,
                    "value" => $totalNominal,
                    "columnType" => "currency",
                ],
            ],
        );
        return response()->json($response);
    }

    public function getTransLog($id, Request $request)
    {
        $custId = $request->input('custid');
        $billTransNo = $request->input('bill_transno');
        $billName = $request->input('billnm');

        if (blank($custId)) {
            $bill = scctbill::query()
                ->where('AA', $id)
                ->select(['CUSTID', 'TRANSNO', 'BILLNM'])
                ->first();
            if ($bill) {
                $custId = $bill->CUSTID;
                $billTransNo = $billTransNo ?: $bill->TRANSNO;
                $billName = $billName ?: $bill->BILLNM;
            }
        }

        $logs = $this->getTransactionLogsForBill($custId, $id, $billTransNo, $billName);

        if (empty($logs)) {
            $logs = sccttran::query()
                ->where('BILLID', $id)
                ->orderBy('TRXDATE', 'desc')
                ->get(['TRXDATE', 'METODE', 'DEBET', 'KREDIT', 'FIDBANK', 'NOREFF', 'TRANSNO'])
                ->map(function ($trx) {
                    return [
                        'trxdate' => $trx->TRXDATE
                            ? Carbon::parse($trx->TRXDATE)->format('d-m-Y H:i:s')
                            : null,
                        'metode' => $trx->METODE,
                        'debet' => (int) ($trx->DEBET ?? 0),
                        'kredit' => (int) ($trx->KREDIT ?? 0),
                        'fidbank' => $trx->FIDBANK,
                        'noreff' => $trx->NOREFF,
                        'transno' => $trx->TRANSNO,
                    ];
                })
                ->values()
                ->all();
        }

        return response()->json(['logs' => $logs], 200);
    }

    public function getTransLogsBulk(Request $request)
    {
        $bills = $request->input('bills', []);
        if (!is_array($bills) || empty($bills)) {
            return response()->json(['logs' => []], 200);
        }

        $allLogs = [];
        foreach ($bills as $bill) {
            if (!is_array($bill)) {
                continue;
            }

            $aa = $bill['aa'] ?? $bill['AA'] ?? null;
            if (blank($aa)) {
                continue;
            }

            $custId = $bill['custid'] ?? $bill['CUSTID'] ?? null;
            $billTransNo = $bill['bill_transno'] ?? $bill['TRANSNO'] ?? null;
            $billName = $bill['billnm'] ?? $bill['BILLNM'] ?? null;

            $logs = $this->getTransactionLogsForBill($custId, $aa, $billTransNo, $billName);
            foreach ($logs as $log) {
                if ((int) ($log['debet'] ?? 0) <= 0 && (int) ($log['kredit'] ?? 0) <= 0) {
                    continue;
                }

                $allLogs[] = array_merge($log, [
                    'bill_aa' => $aa,
                    'billnm' => $billName,
                ]);
            }
        }

        usort($allLogs, function ($a, $b) {
            return strcmp((string) ($b['trxdate'] ?? ''), (string) ($a['trxdate'] ?? ''));
        });

        return response()->json(['logs' => $allLogs], 200);
    }

    private function getTransactionLogsForBill($custId, $aa, $billTransNo = null, $billName = null): array
    {
        if (blank($aa)) {
            return [];
        }

        try {
            $primaryLogs = sccttran::query()
                ->where('BILLID', $aa)
                ->orderBy('TRXDATE', 'desc')
                ->get(['TRXDATE', 'METODE', 'DEBET', 'KREDIT', 'FIDBANK', 'NOREFF', 'TRANSNO']);

            $logsCollection = $primaryLogs;
            if ($logsCollection->isEmpty()) {
                $logsCollection = sccttran::query()
                    ->where(function ($q) use ($billTransNo, $billName) {
                        if (!blank($billTransNo) && (string) $billTransNo !== '-') {
                            $q->orWhere('TRANSNO', $billTransNo);
                        }
                        if (!blank($billName)) {
                            $q->orWhereRaw('UPPER(TRIM(BILLTARGET)) = UPPER(TRIM(?))', [$billName]);
                        }
                    })
                    ->when(!blank($custId), function ($q) use ($custId) {
                        $q->where('CUSTID', $custId);
                    })
                    ->orderBy('TRXDATE', 'desc')
                    ->get(['TRXDATE', 'METODE', 'DEBET', 'KREDIT', 'FIDBANK', 'NOREFF', 'TRANSNO']);
            }

            return $logsCollection
                ->map(function ($trx) {
                    return [
                        'trxdate' => $trx->TRXDATE
                            ? Carbon::parse($trx->TRXDATE)->format('d-m-Y H:i:s')
                            : null,
                        'metode' => $trx->METODE,
                        'debet' => (int) ($trx->DEBET ?? 0),
                        'kredit' => (int) ($trx->KREDIT ?? 0),
                        'fidbank' => $trx->FIDBANK,
                        'noreff' => $trx->NOREFF,
                        'transno' => $trx->TRANSNO,
                    ];
                })
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function total(): int
    {
        $key = Str::slug($this->cacheKey);

        return Cache::remember(
            "{$key}:total_all_data",
            now()->addMinutes(10),
            fn () => $this->lunasBillBaseQuery()->count()
        );
    }

    private function notReversedTranScope(): \Closure
    {
        return function ($query) {
            $query->where(function ($q) {
                $q->whereNull('sccttran.isreversal')
                    ->orWhere('sccttran.isreversal', 0)
                    ->orWhere('sccttran.isreversal', '0');
            });
        };
    }

    private function lunasBillBaseQuery()
    {
        $query = scctbill::query()
            ->leftJoin('scctcust', 'scctcust.CUSTID', '=', 'scctbill.CUSTID')
            ->whereIn('scctbill.PAIDST', [1, '1'])
            ->where('scctbill.FSTSBolehBayar', 1);
        SchoolScope::applyGender($query, 'scctcust.CODE04');

        return $query;
    }

    private function resolveOrderColumn(string $column): string
    {
        return match ($column) {
            'nocust' => 'scctcust.nocust',
            'nmcust' => 'scctcust.nmcust',
            'CODE02' => 'scctcust.CODE02',
            'DESC02' => 'scctcust.DESC02',
            'DESC03' => 'scctcust.DESC03',
            'BILLNM' => 'scctbill.BILLNM',
            'BILLAM' => 'scctbill.BILLPAID',
            'FIDBANK' => 'scctbill.FIDBANK',
            'PAIDDT' => 'scctbill.PAIDDT',
            'BTA' => 'scctbill.BTA',
            default => str_contains($column, '.') ? $column : 'scctbill.' . $column,
        };
    }

    public function destroy($id, Request $request)
    {
        $custId = $request->input('user_id') ?? $request->input('custid');

        $tagihan = scctbill::where('AA', $id)
            ->when($custId, fn ($q) => $q->where('CUSTID', $custId))
            ->first();

        if (!$tagihan) {
            return response()->json(['message' => 'Tagihan tidak ditemukan!'], 422);
        }

        if (!app(TagihanPaymentReversal::class)->hasBillPayments($tagihan)) {
            return response()->json(['message' => 'Pembayaran tidak ditemukan atau sudah di-reversal!'], 422);
        }

        $custId = (string) $tagihan->CUSTID;
        $aa = (string) $tagihan->AA;
        $username = (string) (Auth::user()->username ?? Auth::id() ?? 'system');

        Log::info('data-penerimaan.destroy.start', [
            'aa' => $aa,
            'custid' => $custId,
            'username' => $username,
            'fidbank' => $tagihan->FIDBANK,
            'billnm' => $tagihan->BILLNM,
            'billpaid' => $tagihan->BILLPAID,
        ]);

        try {
            app(TagihanPaymentReversal::class)->reverseLastPayment($tagihan, $request);

            Cache::increment(Str::slug($this->cacheKey) . '_cache_version');

            Log::info('data-penerimaan.destroy.success', [
                'aa' => $aa,
                'custid' => $custId,
            ]);

            return response()->json(['message' => 'Reversal berhasil!'], 200);
        } catch (\Throwable $e) {
            Log::error('data-penerimaan.destroy.failed', [
                'aa' => $aa,
                'custid' => $custId,
                'fidbank' => $tagihan->FIDBANK,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Pembatalan pembayaran tagihan gagal dilakukan!',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /** Manual Pembayaran teller/cash — sama dengan bank yang pakai FROM TELLER di sccttran. */
    private const TELLER_FIDBANKS = ['1140000', '1140001', '1140003', '1140004', '1140005', '1200001', '1200002'];

    private const SALDO_FIDBANK = '1140002';

    private function normalizeFidBank(?string $fidBank): string
    {
        return preg_replace('/\D/', '', (string) $fidBank);
    }

    /** Cash/teller: reset scctbill + insert REVERSAL. Saldo/VA lewat CancelPaymentSaldo. */
    private function shouldCancelAsTellerPayment(scctbill $tagihan): bool
    {
        $fidBank = $this->normalizeFidBank($tagihan->FIDBANK ?? '');

        if ($fidBank === self::SALDO_FIDBANK) {
            return false;
        }

        if (in_array($fidBank, self::TELLER_FIDBANKS, true)) {
            return true;
        }

        return sccttran::query()
            ->where('BILLID', $tagihan->AA)
            ->where('CUSTID', $tagihan->CUSTID)
            ->whereRaw('UPPER(TRIM(METODE)) = ?', ['FROM TELLER'])
            ->exists();
    }

    private function resolvePaidAmount(scctbill $tagihan): int
    {
        $billPaid = (int) ($tagihan->BILLPAID ?? 0);
        if ($billPaid > 0) {
            return $billPaid;
        }

        $fromTeller = (int) sccttran::query()
            ->where('BILLID', $tagihan->AA)
            ->where('CUSTID', $tagihan->CUSTID)
            ->whereRaw('UPPER(TRIM(METODE)) = ?', ['FROM TELLER'])
            ->sum('DEBET');

        if ($fromTeller > 0) {
            return $fromTeller;
        }

        return max(0, (int) ($tagihan->BILLAM ?? 0));
    }

    private function cancelCashPayment(scctbill $tagihan, string $username): void
    {
        $billAm = (int) ($tagihan->BILLAM ?? 0);
        $nominalBayar = $this->resolvePaidAmount($tagihan);

        $this->clearTellerPaymentKredit($tagihan);

        if ($nominalBayar > 0) {
            $this->insertReversalTransaction($tagihan, $nominalBayar, 'REVERSAL', $username);
        }

        $tagihan->update([
            'PAIDST' => 0,
            'PAIDDT' => null,
            'PAIDDT_ACTUAL' => null,
            'BILLPAID' => 0,
            'PAYMENTLEFT' => $billAm,
            'INSTALLMENT' => 0,
        ]);
    }

    /** Kosongkan KREDIT baris FROM TELLER sebelum insert REVERSAL. */
    private function clearTellerPaymentKredit(scctbill $tagihan): void
    {
        sccttran::query()
            ->where('BILLID', $tagihan->AA)
            ->where('CUSTID', $tagihan->CUSTID)
            ->whereRaw('UPPER(TRIM(METODE)) = ?', ['FROM TELLER'])
            ->where('KREDIT', '>', 0)
            ->update(['KREDIT' => 0]);
    }

    private function cancelSaldoOrVaPayment(scctbill $tagihan, string $custId, string $aa, string $username, Request $request): void
    {
        $userId = $this->resolveCyberKeyUserId();
        $hostname = $this->resolveClientHostname($request);
        $billCd = (string) ($tagihan->BILLCD ?? '');

        try {
            $this->callCancelPaymentSaldo($custId, $aa, $billCd, $userId, $hostname);

            Log::info('data-penerimaan.cancel.procedure_ok', [
                'custid' => $custId,
                'aa' => $aa,
                'billcd' => $billCd,
                'users' => $userId,
                'hostname' => $hostname,
            ]);
        } catch (\Throwable $e) {
            Log::error('data-penerimaan.cancel.procedure_failed', [
                'custid' => $custId,
                'aa' => $aa,
                'fidbank' => $tagihan->FIDBANK,
                'billnm' => $tagihan->BILLNM,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            DB::connection('DATA_MYSQL')->transaction(function () use ($tagihan, $username, $e) {
                if ($this->shouldCancelAsTellerPayment($tagihan)) {
                    Log::warning('data-penerimaan.cancel.fallback_cash', [
                        'aa' => $tagihan->AA,
                        'reason' => $e->getMessage(),
                    ]);
                    $this->cancelCashPayment($tagihan, $username);

                    return;
                }

                if ($this->isCancelPaymentSaldoMissing($e)) {
                    Log::warning('data-penerimaan.cancel.fallback_saldo_manual', [
                        'aa' => $tagihan->AA,
                    ]);
                    $this->reverseSaldoPaymentManually($tagihan, $username);

                    return;
                }

                throw $e;
            });
        }
    }

    private function isCancelPaymentSaldoMissing(\Throwable $e): bool
    {
        $message = $e->getMessage();

        return stripos($message, 'CancelPaymentSaldo') !== false
            && (
                stripos($message, 'does not exist') !== false
                || stripos($message, 'doesn\'t exist') !== false
                || stripos($message, 'unknown procedure') !== false
                || stripos($message, '1305') !== false
            );
    }

    private function callCancelPaymentSaldo(string $custId, string $aa, string $billCd, string $userId, string $hostname): void
    {
        Log::info('data-penerimaan.cancel.call_procedure', [
            'procedure' => 'CancelPaymentSaldo',
            'custid' => $custId,
            'aa' => $aa,
            'billcd' => $billCd,
            'users' => $userId,
            'hostname' => $hostname,
        ]);

        $pdo = DB::connection('DATA_MYSQL')->getPdo();
        $stmt = $pdo->prepare('CALL CancelPaymentSaldo(?, ?, ?, ?, ?)');
        $stmt->execute([$custId, $aa, $billCd, $userId, $hostname]);

        do {
            $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } while ($stmt->nextRowset());
    }

    private function resolveCyberKeyUserId(): string
    {
        $user = Auth::user();

        if ($user === null) {
            return '';
        }

        return (string) ($user->urut ?? Auth::id() ?? '');
    }

    private function resolveClientHostname(Request $request): string
    {
        return Str::limit((string) ($request->ip() ?? ''), 250, '');
    }

    /** Fallback jika procedure CancelPaymentSaldo gagal / tidak ada. */
    private function reverseSaldoPaymentManually(scctbill $tagihan, string $username): void
    {
        $billAm = (int) ($tagihan->BILLAM ?? 0);
        $nominalBayar = $this->resolvePaidAmount($tagihan);

        if ($nominalBayar > 0) {
            $this->insertReversalTransaction($tagihan, $nominalBayar, 'JURNAL SALDO', $username);
        }

        $tagihan->update([
            'PAIDST' => 0,
            'PAIDDT' => null,
            'PAIDDT_ACTUAL' => null,
            'BILLPAID' => 0,
            'PAYMENTLEFT' => $billAm,
            'INSTALLMENT' => 0,
        ]);
    }

    private function insertReversalTransaction(scctbill $tagihan, int $nominalBayar, string $metode, string $username): void
    {
        $lastInstallment = (int) sccttran::query()
            ->where('BILLID', $tagihan->AA)
            ->where('CUSTID', $tagihan->CUSTID)
            ->max('INSTALLMENT');

        $payload = [
            'CUSTID' => $tagihan->CUSTID,
            'METODE' => $metode,
            'TRXDATE' => now(),
            'NOREFF' => 'REVERSAL',
            'FIDBANK' => (string) ($tagihan->FIDBANK ?? ''),
            'DEBET' => 0,
            'KREDIT' => $nominalBayar,
            'REFFBANK' => MultiVa::resolveFromBill($tagihan),
            'BILLID' => $tagihan->AA,
            'BILLTARGET' => $tagihan->BILLNM,
            'INSTALLMENT' => $lastInstallment,
            'TRANSNO' => $tagihan->TRANSNO ?? $username,
            'isreversal' => 1,
        ];

        try {
            sccttran::create($payload);
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'isreversal') === false
                && stripos($e->getMessage(), 'Unknown column') === false) {
                throw $e;
            }

            unset($payload['isreversal']);
            sccttran::create($payload);
        }
    }

    public function cetak(Request $request)
    {
        ini_set('max_execution_time', 300);
//        $pdf = Pdf::loadView('cetak.data-penerimaan')->setPaper('a4', 'landscape');
//        return $pdf->download('rekap-tagihan.pdf');

        try {
            $filters = [];
            $filterQuery = null;

            $filter = $request->input('filter');
            if ($filter) {
                if (isset($request->filter['dari_tanggal']) && $request->filter['dari_tanggal'] != null
                    && preg_match('/^\d{2}-\d{2}-\d{4}$/', $request->filter['dari_tanggal']) &&
                    isset($request->filter['sampai_tanggal']) && $request->filter['sampai_tanggal'] != null
                    && preg_match('/^\d{2}-\d{2}-\d{4}$/', $request->filter['sampai_tanggal'])
                ) {
                    foreach ($filter as $key => $val) {
                        if (strtolower($val) != 'all' && $val !== null && $val !== '') {
                            $colName = match ($key) {
                                'dari_tanggal', 'sampai_tanggal' => 'scctbill.PAIDDT',
                                'tahun_akademik' => 'scctbill.BTA',
                                'post' => 'scctbill.BILLNM',
                                'kelas' => 'scctcust.DESC02',
                                'nama' => 'scctcust.NMCUST',
                                'nis' => 'scctcust.NOCUST',
                                'sekolah' => 'scctcust.CODE01',
                                'angkatan' => 'scctcust.DESC04',
                                'periode_mulai', 'periode_akhir' => 'scctbill.BILLAC',
                                default => null
                            };
                            if (in_array($key, ['dari_tanggal', 'sampai_tanggal']) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $val)) {
                                if ($key == 'dari_tanggal') {
                                    $date = Carbon::createFromFormat('d-m-Y', $val)->startOfDay();
                                } else {
                                    $date = Carbon::createFromFormat('d-m-Y', $val)->endOfDay();
                                }

                                if ($date && $colName) {
                                    $operator = $key === 'dari_tanggal' ? '>=' : '<=';
                                    $filters[] = [$colName, $operator, $date];
                                }
                            } elseif (in_array($key, ['periode_mulai', 'periode_akhir']) && preg_match('/^\d{6}$/', $val)) {
                                $operator = $key === 'periode_mulai' ? '>=' : '<=';
                                $filters[] = [$colName, $operator, $val];
                            } elseif (in_array($key, ['nama', 'nis'])) {
                                ($colName) && $filters[] = [$colName, 'like', '%' . $val . '%'];
                            } elseif ($key === 'kelas') {
                                $parts = explode('~~', (string) $val);
                                if (count($parts) === 3) {
                                    $filters[] = ['scctcust.CODE02', '=', $parts[0]];
                                    $filters[] = ['scctcust.DESC02', '=', $parts[1]];
                                    $filters[] = ['scctcust.DESC03', '=', $parts[2]];
                                }
                            } else {
                                ($colName) && $filters[] = [$colName, '=', $val];
                            }
                        }
                    }

                    if (!empty($filters)) {
                        $filterQuery = function ($query) use ($filters) {
                            foreach ($filters as $filter) {
                                if (count($filter) === 3) {
                                    $query->where($filter[0], $filter[1], $filter[2]);
                                } elseif (count($filter) === 4) {
                                    if ($filter[3] == 'whereBetween') {
                                        $query->whereBetween($filter[0], [$filter[1], $filter[2]]);
                                    } else {
                                        $query->{$filter[3]}($filter[0], $filter[1], $filter[2]);
                                    }
                                }
                            }
                        };
                    }


                    $posts = mst_tagihan::select('urut', 'tagihan', 'kode')->get()
                        ->map(function ($item) use ($filterQuery) {
                            $item->tagihans = scctbill::leftJoin('scctcust', 'scctcust.CUSTID', 'scctbill.CUSTID')
                                ->select([
                                    'scctcust.nmcust',
                                    'scctcust.nocust',
                                    'scctbill.AA',
                                    'scctbill.BILLNM',
                                    'scctbill.BILLAM',
                                    'scctbill.PAIDST',
                                    'scctbill.PAIDDT',
                                    'scctbill.BTA',
                                    'scctbill.FIDBANK',
                                    'scctbill.FUrutan',
                                    'scctcust.CODE02',
                                    'scctcust.DESC02',
                                ])
                                ->where('scctbill.BILLNM', $item->tagihan)
                                ->where('scctbill.PAIDST', 1)
                                ->where('scctbill.PAIDDT', '!=', null)
                                ->where(function ($query) use ($filterQuery) {
                                    if ($filterQuery) {
                                        $filterQuery($query);
                                    }
                                })
                                ->orderBy('scctbill.CUSTID', 'desc')
                                ->orderBy('scctbill.BTA', 'desc')
                                ->orderBy('scctbill.PAIDDT', 'desc')
                                ->get()
                                ->toArray();;

                            return $item;
                        });
                } else {
                    return response()->json(['message' => 'Tanggal transaksi tidak valid', 'error' => 'Tanggal transaksi tidak valid'], 422);
                }
            }

//            dd($posts[0]['tagihan']);
//            return  view('pdf.data_penerimaan.rekap_penerimaan', ['posts' => $posts]);

//            $view = view('cetak.data-penerimaan', compact('posts'))->render();
//            return response()->json(['html' => $view]);

            if ($posts) {
                $pdf = Pdf::loadView('cetak.data-penerimaan', ['posts' => $posts])->setPaper('a4', 'landscape');
                return $pdf->download('rekap-tagihan.pdf');
            } else {
                return response()->json(['message' => 'Data Kosong'], 422);
            }
        } catch (\Exception $e) {
            return response()->json(['message' => 'Tidak dapat mencetak rekap', 'error' => $e->getMessage(), 'e' => $e], 422);
        }
    }

    public function cetakNew(Request $request)
    {
        try {
            $filter = $request->input('filter', []);
            $dariTanggal = $filter['dari_tanggal'] ?? null;
            $sampaiTanggal = $filter['sampai_tanggal'] ?? null;

            if (
                !$dariTanggal || !$sampaiTanggal ||
                !preg_match('/^\d{2}-\d{2}-\d{4}$/', $dariTanggal) ||
                !preg_match('/^\d{2}-\d{2}-\d{4}$/', $sampaiTanggal)
            ) {
                return response()->json(['message' => 'Tanggal transaksi tidak valid'], 422);
            }

            $tanggalMulai = Carbon::createFromFormat('d-m-Y', $dariTanggal)->startOfDay();
            $tanggalSelesai = Carbon::createFromFormat('d-m-Y', $sampaiTanggal)->endOfDay();

            $query = scctbill_detail::query()
                ->from('scctbill_detail as a')
                ->join('scctbill as b', function ($join) {
                    $join->on('a.BILLCD', '=', 'b.BILLCD')
                        ->on('a.CUSTID', '=', 'b.CUSTID');
                })
                ->join('scctcust as d', 'b.CUSTID', '=', 'd.CUSTID')
                ->leftJoin((new u_akun())->getTable() . ' as c', 'a.KodePost', '=', 'c.KodeAkun')
                ->where('b.PAIDST', 1)
                ->where('b.FSTSBolehBayar', 1)
                ->whereBetween('b.PAIDDT', [$tanggalMulai, $tanggalSelesai])
                ->when(!blank($filter['nis'] ?? null), function ($q) use ($filter) {
                    $nis = trim((string)$filter['nis']);
                    $q->where(function ($sub) use ($nis) {
                        $sub->where('d.NOCUST', 'like', "%{$nis}%")
                            ->orWhere('d.NUM2ND', 'like', "%{$nis}%");
                    });
                })
                ->when(!blank($filter['nama'] ?? null), function ($q) use ($filter) {
                    $q->where('d.NMCUST', 'like', '%' . trim((string)$filter['nama']) . '%');
                })
                ->when(!blank($filter['tahun_akademik'] ?? null) && strtolower((string)$filter['tahun_akademik']) !== 'all', function ($q) use ($filter) {
                    $q->where('b.BTA', trim((string)$filter['tahun_akademik']));
                })
                ->when(!blank($filter['angkatan'] ?? null) && strtolower((string)$filter['angkatan']) !== 'all', function ($q) use ($filter) {
                    $q->where('d.DESC04', trim((string)$filter['angkatan']));
                })
                ->when(!blank($filter['bank'] ?? null) && strtolower((string)$filter['bank']) !== 'all', function ($q) use ($filter) {
                    $bank = trim((string) $filter['bank']);
                    if ($bank === MetodeBayarHelper::ANDROID_FIDBANK) {
                        $q->where(function ($sub) {
                            $sub->where('b.FIDBANK', MetodeBayarHelper::ANDROID_FIDBANK)
                                ->orWhereRaw('UPPER(TRIM(COALESCE(b.NOREFF, ""))) = ?', ['MOBILE']);
                        });
                        return;
                    }
                    $q->where('b.FIDBANK', $bank)
                        ->whereRaw('UPPER(TRIM(COALESCE(b.NOREFF, ""))) <> ?', ['MOBILE']);
                })
                ->when(!blank($filter['post'] ?? null), function ($q) use ($filter) {
                    $post = $filter['post'];
                    if (is_array($post)) {
                        $post = collect($post)->filter(fn($item) => !blank($item) && strtolower((string)$item) !== 'all')->values()->all();
                        if (!empty($post)) {
                            $q->whereIn('b.BILLNM', $post);
                        }
                        return;
                    }
                    if (strtolower((string)$post) !== 'all') {
                        $q->where('b.BILLNM', trim((string)$post));
                    }
                })
                ->when($this->sekolah, function ($q) {
                    $q->where('d.CODE01', $this->sekolah);
                })
                ->when(!blank($filter['sekolah'] ?? null) && strtolower((string)$filter['sekolah']) !== 'all', function ($q) use ($filter) {
                    $q->where('d.CODE01', trim((string)$filter['sekolah']));
                })
                ->when(!blank($filter['kelas'] ?? null) && strtolower((string)$filter['kelas']) !== 'all', function ($q) use ($filter) {
                    $parts = explode('~~', trim((string)$filter['kelas']));
                    if (count($parts) === 3) {
                        $q->where('d.CODE02', $parts[0])
                            ->where('d.DESC02', $parts[1])
                            ->where('d.DESC03', $parts[2]);
                    }
                })
                ->when(!blank($filter['periode_mulai'] ?? null) && preg_match('/^\d{6}$/', (string)$filter['periode_mulai']), function ($q) use ($filter) {
                    $q->where('b.BILLAC', '>=', (string)$filter['periode_mulai']);
                })
                ->when(!blank($filter['periode_akhir'] ?? null) && preg_match('/^\d{6}$/', (string)$filter['periode_akhir']), function ($q) use ($filter) {
                    $q->where('b.BILLAC', '<=', (string)$filter['periode_akhir']);
                })
                ->select([
                    'a.KodePost',
                    DB::raw('SUM(a.BILLAM) as total_tagihan'),
                    DB::raw("CONCAT(d.DESC02, ' ', d.DESC03) as kelas"),
                    'd.DESC03',
                    'd.NOCUST',
                    'd.NMCUST',
                    'b.AA',
                    DB::raw('NULL as GetWisma'),
                    'b.FIDBANK',
                    'c.NamaAkun',
                    DB::raw("DATE_FORMAT(b.PAIDDT, '%Y-%m-%d %H:%i:%s') as PAIDDT"),
                    'd.DESC04 as angkatan',
                    'd.NUM2ND',
                    'b.BILLAC',
                    'b.BILLNM',
                    'b.BTA',
                ])
                ->groupBy([
                    'a.KodePost',
                    'd.DESC02',
                    'd.DESC03',
                    'd.DESC04',
                    'd.NOCUST',
                    'd.NMCUST',
                    'd.NUM2ND',
                    'b.AA',
                    'b.FIDBANK',
                    'c.NamaAkun',
                    'b.PAIDDT',
                    'b.BILLAC',
                    'b.BILLNM',
                    'b.BTA',
                ])
                ->orderBy('b.PAIDDT')
                ->orderBy('a.KodePost');

            $rows = $query->get();
            if ($rows->isEmpty()) {
                return response()->json(['message' => 'Data Kosong'], 422);
            }

            return response()->json(['data' => $rows], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Tidak dapat mencetak rekap', 'error' => $e->getMessage(), 'e' => $e], 422);
        }
    }

    public function cetakPembayaran(Request $request)
    {
        $tagihans = scctbill::where('AA', $request->id_tagihan)->get();
        $siswa = scctcust::where('CISTID', $tagihans[0]->CUSTID)->first();
        if ($siswa && $tagihans) {
            $siswa = $request->session()->get('siswa_tagihan_baru_dibayar');
            $pdf = Pdf::loadView('pdf.kuitansi', ['tagihans' => $tagihans, 'siswa' => $siswa]);
            return $pdf->download('bukti-pembayaran - ' . $siswa->nama . ' - ' . $siswa->nis . '.pdf');
        } else {
            return response()->json(['message' => 'Silakhan Lakukan pembayaran terlebih dahulu'], 422);
        }
    }

    public function cetakKartuSiswa(Request $request)
    {
        $custid = $request->input('custid');
        if (!$custid) {
            return response()->json(['message' => 'Siswa tidak ditemukan'], 422);
        }

        $siswa = scctcust::where('CUSTID', $custid)->first();
        if (!$siswa) {
            return response()->json(['message' => 'Siswa tidak ditemukan'], 422);
        }

        $tagihans = scctbill::query()
            ->leftJoin('scctcust', 'scctcust.CUSTID', '=', 'scctbill.CUSTID')
            ->where('scctbill.CUSTID', $custid)
            ->where('scctbill.PAIDST', 1)
            ->where('scctbill.FSTSBolehBayar', 1)
            ->whereNotNull('scctbill.PAIDDT')
            ->when(!blank($request->input('filter.tahun_akademik')) && strtolower((string)$request->input('filter.tahun_akademik')) !== 'all', function ($q) use ($request) {
                $q->where('scctbill.BTA', $request->input('filter.tahun_akademik'));
            })
            ->when(!blank($request->input('filter.post')), function ($q) use ($request) {
                $post = $request->input('filter.post');
                if (is_array($post)) {
                    $post = collect($post)->filter(fn($item) => !blank($item) && strtolower((string)$item) !== 'all')->values()->all();
                    if (!empty($post)) {
                        $q->whereIn('scctbill.BILLNM', $post);
                    }
                    return;
                }
                if (strtolower((string)$post) !== 'all') {
                    $q->where('scctbill.BILLNM', $post);
                }
            })
            ->when(!blank($request->input('filter.bank')) && strtolower((string)$request->input('filter.bank')) !== 'all', function ($q) use ($request) {
                $bank = trim((string) $request->input('filter.bank'));
                if ($bank === MetodeBayarHelper::ANDROID_FIDBANK) {
                    $q->where(function ($sub) {
                        $sub->where('scctbill.FIDBANK', MetodeBayarHelper::ANDROID_FIDBANK)
                            ->orWhereRaw('UPPER(TRIM(COALESCE(scctbill.NOREFF, ""))) = ?', ['MOBILE']);
                    });
                    return;
                }
                $q->where('scctbill.FIDBANK', $bank)
                    ->whereRaw('UPPER(TRIM(COALESCE(scctbill.NOREFF, ""))) <> ?', ['MOBILE']);
            })
            ->when(!blank($request->input('filter.dari_tanggal')) && preg_match('/^\d{2}-\d{2}-\d{4}$/', (string)$request->input('filter.dari_tanggal')), function ($q) use ($request) {
                $startDate = Carbon::createFromFormat('d-m-Y', (string)$request->input('filter.dari_tanggal'))->startOfDay();
                $q->where('scctbill.PAIDDT', '>=', $startDate);
            })
            ->when(!blank($request->input('filter.sampai_tanggal')) && preg_match('/^\d{2}-\d{2}-\d{4}$/', (string)$request->input('filter.sampai_tanggal')), function ($q) use ($request) {
                $endDate = Carbon::createFromFormat('d-m-Y', (string)$request->input('filter.sampai_tanggal'))->endOfDay();
                $q->where('scctbill.PAIDDT', '<=', $endDate);
            })
            ->select(MultiVa::filterBillSelect([
                'scctbill.BILLNM',
                'scctbill.BILLAC',
                'scctbill.BILLAM',
                'scctbill.BTA',
                'scctbill.PAIDDT',
                'scctbill.PAIDST',
                'scctbill.FIDBANK',
                'scctbill.FUrutan',
                'scctbill.va',
                'scctbill.isINSTALLABLE',
                'scctcust.NOCUST',
            ]))
            ->orderBy('scctbill.FUrutan', 'asc')
            ->orderBy('scctbill.PAIDDT', 'desc')
            ->get()
            ->map(function ($item) {
                $item->NOVA = MultiVa::formatNoVaFromBill($item->NOCUST ?? null, $item);

                return $item;
            })
            ->values();

        if ($tagihans->isEmpty()) {
            return response()->json(['message' => 'Data pembayaran tidak ditemukan'], 422);
        }

        return response()->json([
            'siswa' => $siswa,
            'tagihans' => $tagihans,
        ]);
    }
}
