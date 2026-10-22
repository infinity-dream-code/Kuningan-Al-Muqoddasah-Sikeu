<?php

namespace App\Http\Controllers\Admin\Keuangan;

use App\Http\Controllers\Controller;
use App\Models\mst_tagihan;
use App\Models\scctbill;
use App\Models\scctcust;
use App\Models\sccttran;
use App\Models\User;
use App\Models\ValidationMessage;
use App\Support\ManualPaymentBuilder;
use App\Support\MetodeBayarHelper;
use App\Support\MultiVa;
use App\Support\SchoolScope;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;
use function Laravel\Prompts\error;
use function PHPUnit\Framework\throwException;

class ManualPembayaranController extends Controller
{
    public string $title = 'Keuangan';
    public string $mainTitle = 'Manual Pembayaran';
    public string $dataTitle = 'Manual Pembayaran';
    public string $showTitle = 'Detail Manual Pembayaran';

    public string $cacheKey = 'pembayaran_manual';

    public function __construct()
    {
        $this->datasUrl = route('admin.keuangan.manual-pembayaran.get-data');
        $this->columnsUrl = route('admin.keuangan.manual-pembayaran.get-column');
    }

    public function getColumn()
    {
        return [
            [
                'data' => 'item_id',
                'name' => 'no',
                'className' => 'text-center',
                'columnType' => 'checkbox',
                'selectName' => 'tagihan[post]',
                'selectClass' => 'scctbill',
            ],
            ['data' => 'nocust', 'name' => 'NIS', 'searchable' => true, 'orderable' => true],
            ['data' => 'NUM2ND', 'name' => 'NO. DAFTAR', 'searchable' => true, 'orderable' => true],
            ['data' => 'kelas_label', 'name' => 'Kelas', 'searchable' => true, 'orderable' => false],
            ['data' => 'NOVA', 'name' => 'NO. VA', 'searchable' => true, 'orderable' => false, 'columnType' => 'nova_edit'],
            ['data' => 'nmcust', 'name' => 'NAMA', 'searchable' => true, 'orderable' => true],
            ['data' => 'BILLNM', 'name' => 'Nama Tagihan', 'searchable' => true, 'orderable' => true],
            ['data' => 'BILLAC', 'name' => 'Periode', 'searchable' => true, 'orderable' => true, 'columnType' => 'periode'],
            ['data' => 'BILLAM', 'name' => 'Tagihan', 'searchable' => true, 'orderable' => true, 'columnType' => 'currency', 'className' => 'text-end'],
            ['data' => 'PAYMENTLEFT', 'name' => 'Sisa Tagihan', 'searchable' => true, 'orderable' => true, 'columnType' => 'currency', 'className' => 'text-end'],
            [
                'data' => null,
                'name' => 'Nominal Bayar',
                'columnType' => 'input',
                'inputType' => 'text',
                'inputClass' => 'form-control bg-body nominal-bayar-input',
                'inputName' => 'tagihan[nominal_bayar][]',
                'inputPlaceholder' => 'nominal bayar',
                'excludeFromSelection' => true,
            ],
        ];
    }

    public function getData(Request $request)
    {
        $draw = $request->get('draw');
        $start = $request->get("start");
        $rowperpage = $request->get("length");

        if ($request->siswa) {
            $siswaScope = scctcust::where('CUSTID', $request->siswa)->first();
            if ($denied = SchoolScope::denyStudentMessage($siswaScope)) {
                return response()->json([
                    'draw' => intval($draw),
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0,
                    'data' => [],
                    'message' => $denied,
                ], 422);
            }

            $columnName_arr = $request->get('columns');
            $search_arr = $request->get('search');

            $defaultColumn = 'scctbill.created_at';
            $defaultOrder = 'desc';

            $order = $request->get('order', []);
            if (!empty($order)) {
                $columnIndex = (int)($order[0]['column'] ?? -1);
                $columns = $request->get('columns', []);

                if ($columnIndex >= 0 && isset($columns[$columnIndex])) {
                    $columnData = $columns[$columnIndex]['data'] ?? '';
                    if (!in_array($columnData, ['no', 'item_id'], true) && $columnData !== '') {
                        $columnName = $columnData;
                        $columnSortOrder = strtolower($order[0]['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
                    }
                }
            }

            $tahun_pelajaran = data_get($request->input('filter', []), 'tahun_pelajaran');

            $whereAny = [
                'scctcust.nmcust',
                'scctcust.nocust',
            ];

            $select = array_unique(array_merge($whereAny, MultiVa::filterBillSelect([
                'scctbill.AA',
                'scctbill.BILLNM',
                'scctbill.BILLAM',
                'scctbill.BILLPAID',
                'scctbill.PAYMENTLEFT',
                'scctbill.BILLAC',
                'scctbill.PAIDST',
                'scctbill.PAIDDT',
                'scctbill.BTA',
                'scctbill.FIDBANK',
                'scctbill.NOREFF',
                'scctbill.FUrutan',
                'scctbill.va',
                'scctbill.isINSTALLABLE',
                'scctcust.CUSTID',
                'scctcust.CODE02',
                'scctcust.DESC02',
                'scctcust.DESC03',
                'scctcust.NOCUST',
                'scctcust.NUM2ND',
                'scctcust.NMCUST',
            ])));

            $query = scctbill::leftJoin('scctcust', 'scctcust.CUSTID', 'scctbill.CUSTID')
                ->where('scctbill.CUSTID', $request->siswa)
                ->where('scctbill.PAIDST', '=', 0)
                ->where('scctbill.FSTSBolehBayar', '=', 1)
                ->tap(fn ($q) => SchoolScope::apply($q, 'scctcust'))
                ->when($tahun_pelajaran && $tahun_pelajaran != 'all', function ($query) use ($tahun_pelajaran) {
                    return $query->where('scctbill.BTA', '=', $tahun_pelajaran);
                })
            ->groupBy('scctbill.AA');

            $totalRecords = Cache::remember('total_tagihan_manual_bayar', 600, function () use ($query) {
                return $query->select('count(*) as allcount')->count();
            });

            $records = $query
                ->select($select)
                ->orderBy('scctbill.FUrutan', 'asc')
                ->get()
                ->map(function ($item) {
                    $item->item_id = $item->AA;
                    $item->nocust = $item->NOCUST;
                    $item->nmcust = $item->NMCUST;
                    $item->kelas_label = trim(($item->DESC02 ?? '') . ' ' . ($item->DESC03 ?? ''));
                    $nis = $item->NOCUST;
                    $vaSource = ($nis && $nis !== '-') ? $nis : $item->NUM2ND;
                    $item->NOVA = $vaSource
                        ? MultiVa::formatNoVaFromBill($vaSource, $item)
                        : '-';
                    $item->PAYMENTLEFT = $this->resolvePaymentLeft($item);
                    $item->sisa_bayar = $item->PAYMENTLEFT;
                    $item->can_cicil = mst_tagihan::canInstallment($item->BILLNM) ? 1 : 0;
                    $item->FIDBANK = MetodeBayarHelper::resolveDisplayFidBank(
                        $item->FIDBANK !== null ? (string) $item->FIDBANK : null,
                        $item->NOREFF !== null ? (string) $item->NOREFF : null
                    );
                    unset($item->AA);
                    return $item;
                })->toArray();
        }

        $response = array(
            "draw" => intval($draw),
            "recordsTotal" => $totalRecords ?? 0,
            "recordsFiltered" => $totalRecords ?? 0,
            "data" => $records ?? [],
        );
        return response()->json($response);
    }

    public function index()
    {
        $data['title'] = $this->title;
        $data['mainTitle'] = $this->mainTitle;
        $data['dataTitle'] = $this->dataTitle;
        $data['showTitle'] = $this->showTitle;
        $data['columnsUrl'] = $this->columnsUrl;
        $data['thn_aka'] = \App\Models\mst_thn_aka::select(['thn_aka'])
            ->whereNotNull('thn_aka')
            ->distinct()
            ->orderBy('thn_aka', 'desc')
            ->get();

        $data['datasUrl'] = $this->datasUrl;
//        $data['thn_aka'] = mst_thn_aka::where('thn_aka', '!=', null)->get();
//        $data['kelas'] = mst_kelas::get();
        $data['tanda_tangan'] = User::getTandaTanganBase64();

        return view('admin.keuangan.manual_pembayaran', $data);
    }

    public function getTagihan(Request $request)
    {
        if (!$request->siswa) {
            return response()->json(['message' => 'Silahkan periksa form anda'], 422);
        }

        $siswaScope = scctcust::where('CUSTID', $request->siswa)->first();
        if ($denied = SchoolScope::denyStudentMessage($siswaScope)) {
            return response()->json(['message' => $denied], 422);
        }

        $whereAny = [
            'scctcust.nmcust',
            'scctcust.nocust',
        ];

        $select = array_unique([...$whereAny,
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
        ]);

        $tagihan = scctbill::leftJoin('scctcust', 'scctcust.CUSTID', 'scctbill.CUSTID')
            ->where('scctbill.PAIDST', 0)
            ->select($select)
            ->where('scctbill.CUSTID', $request->siswa)
            ->tap(fn ($q) => SchoolScope::apply($q, 'scctcust'))
            ->orderBy('scctbill.PAIDST', 'asc')
            ->orderBy('scctbill.FUrutan', 'asc')
            ->get();
        return response()->json($tagihan);
    }

    public function store(Request $request)
    {
        Log::info('manual-pembayaran.store.start', [
            'route' => $request->path(),
            'user_id' => optional(Auth::user())->id,
            'siswa' => $request->input('siswa'),
            'bank' => $request->input('bank'),
            'tanggal' => $request->input('tanggal'),
            'post_count' => count((array)data_get($request->all(), 'tagihan.post', [])),
            'nominal_count' => count((array)data_get($request->all(), 'tagihan.nominal_bayar', [])),
        ]);

        $validator = Validator::make(
            $request->all(),
            [
                'tanggal' => ['required', 'regex:/^\d{2}-\d{2}-\d{4}$/'],
                'siswa' => ['required'],
                'bank' => ['required', 'in:1140000,1140001,1140002,1140003'],
                'tagihan.post' => ['required', 'array', 'min:1'],
                'tagihan.post.*' => ['required'],
                'tagihan.nominal_bayar' => ['required', 'array', 'min:1'],
                'tagihan.nominal_bayar.*' => ['required', 'regex:/^[0-9]+(\.[0-9]{3})*$/']

            ],
            ValidationMessage::messages(),
            ValidationMessage::attributes()
        );

        if ($validator->fails()) {
            Log::warning('manual-pembayaran.store.validation_failed', [
                'siswa' => $request->input('siswa'),
                'bank' => $request->input('bank'),
                'errors' => $validator->errors()->toArray(),
            ]);
            if ($validator->errors()->has('tagihan.nominal_bayar.*') || $validator->errors()->has('tagihan.post.*')) {
                return response()->json(['message' => 'Silahkan cek tagihan yang anda pilih,<br> pastikan telah mengisi nominal pembayaran'], 422);
            } else {
                return response()->json(['message' => $validator->errors()->first(), 'error' => $validator->errors()], 422);
            }
        }

        $posts = collect((array)data_get($request->all(), 'tagihan.post', []))
            ->filter(fn($item) => !is_null($item) && $item !== '')
            ->map(fn($item) => is_numeric($item) ? (int)$item : $item)
            ->values()
            ->all();

        if (empty($posts)) {
            return response()->json(['message' => 'Silahkan pilih tagihan yang akan dibayar'], 422);
        }

        $nominalBayar = [];
        $totalBayar = 0;
        foreach ($request->tagihan['nominal_bayar'] as $key => $value) {
            $nominalBayar[$key] = str_replace('.', '', $value);
            $totalBayar += $nominalBayar[$key];
        }

        $siswa = scctcust::where('CUSTID', $request->siswa)->first();
        if (!$siswa) {
            Log::warning('manual-pembayaran.store.siswa_not_found', [
                'siswa' => $request->input('siswa'),
            ]);
            return response()->json(['message' => 'Siswa tidak ditemukan'], 422);
        }
        if ($denied = SchoolScope::denyStudentMessage($siswa)) {
            return response()->json(['message' => $denied], 422);
        }

        $tagihans = scctbill::whereIn('AA', $posts)
            ->where('PAIDST', '=', 0)
            ->where('FSTSBolehBayar', '=', 1)
            ->orderBy('FUrutan', 'asc')
            ->get();

        $queriedIds = $tagihans->pluck('AA')->toArray();
        $missingIds = array_diff($posts, $queriedIds);
        if (!empty($missingIds)) {
            Log::warning('manual-pembayaran.store.tagihan_missing', [
                'siswa' => $request->input('siswa'),
                'missing_count' => count($missingIds),
                'missing_ids' => array_values($missingIds),
            ]);
            return response()->json([
                'error' => 'Tagihan tidak ditemukan, silahkan coba tekan tombol cari untuk memuat ulang tagihan yang ada',
            ], 422);
        }
        $tagihanForPrint = [];
        $paymentsForReceipt = [];
        $message = 'Tagihan sukses dibayar. <br> Total Bayar : Rp. ' . number_format($totalBayar, 0, ',', '.') . '.<br> Apakah anda ingin mencetak pembayaran tagihan?';

        $dateInput = $request->input('tanggal');
        $datetime = Carbon::createFromFormat('d-m-Y', $dateInput)
            ->setTimeFrom(Carbon::now());
        $formattedDate = $datetime->toDateTimeString();
        try {
            DB::beginTransaction();
            DB::connection('DATA_MYSQL')->beginTransaction();

            if ($request->bank == '1140002') {
                $needed = [MultiVa::OPEN => 0, MultiVa::CLOSE => 0];
                foreach ($tagihans as $item) {
                    $keyForSearch = array_search($item->AA, $posts);
                    $nominal = (int) $nominalBayar[$keyForSearch];
                    $va = MultiVa::resolveFromBill($item);
                    if ($va === null) {
                        $this->rollbackManualPaymentTransactions();
                        return response()->json([
                            'message' => 'Tagihan ' . ($item->BILLNM ?? '') . ' belum memiliki VA Open/Close. Set tipe VA di Master Tagihan.',
                        ], 422);
                    }
                    $needed[$va] += $nominal;
                }

                $sisaParts = [];
                foreach ($needed as $va => $amount) {
                    if ($amount <= 0) {
                        continue;
                    }
                    $saldo = MultiVa::custSaldo($request->siswa, $va);
                    if ($saldo < $amount) {
                        $this->rollbackManualPaymentTransactions();
                        return response()->json([
                            'message' => 'Saldo VA ' . MultiVa::optionLabel($va) . ' kurang.<br> saldo: Rp. '
                                . number_format($saldo, 0, ',', '.')
                                . '<br> kebutuhan: Rp. ' . number_format($amount, 0, ',', '.'),
                        ], 422);
                    }
                    $sisaParts[] = MultiVa::shortLabel($va) . ': Rp. ' . number_format($saldo - $amount, 0, ',', '.');
                }

                $message = 'Tagihan sukses dibayar.
                                <br> Total Bayar: Rp. ' . number_format($totalBayar, 0, ',', '.') . '.
                                <br> Sisa saldo: ' . implode(' | ', $sisaParts) . '.
                                <br> apakah anda ingin mencetak pembayaran tagihan?';
            }

            $transno = null;
            if (\in_array($request->input('bank'), ['1140000', '1140001', '1140003', '1200001', '1200002'])) {
                $last_date = DB::table('ref_invoicedate')->select(['last_date', 'last_number'])->first();
                if (!$last_date) {
                    DB::table('ref_invoicedate')->insert([
                        'last_date' => date('Y-m-d'),
                        'last_number' => 0,
                        'idx_static' => 1,
                    ]);
                    $last_date = DB::table('ref_invoicedate')
                        ->select(['last_date', 'last_number'])
                        ->where('idx_static', '=', 1)
                        ->first();
                } else {
                    if (!Carbon::parse($last_date->last_date)->isSameDay(Carbon::now())) {
                        DB::table('ref_invoicedate')
                            ->where('idx_static', '=', 1)
                            ->update([
                                'last_date' => date('Y-m-d'),
                                'last_number' => 0
                            ]);
                        $last_date = DB::table('ref_invoicedate')
                            ->select(['last_date', 'last_number'])
                            ->where('idx_static', '=', 1)
                            ->first();
                    }
                }

                $lastNumber = (int)($last_date->last_number ?? 0);
                $transno = date('Ym') . substr(config('app.nova'), 5, 2) . substr($request->input('bank'), -2) . date('d') . $lastNumber;
            }

            $paymentBuilder = app(ManualPaymentBuilder::class);

            foreach ($tagihans as $item) {
                $keyForSearch = array_search($item->AA, $posts);
                $nominal = (int) $nominalBayar[$keyForSearch];
                $paymentLeft = $this->resolvePaymentLeft($item);

                if ($nominal <= 0 && $paymentLeft > 0) {
                    $this->rollbackManualPaymentTransactions();
                    return response()->json(['message' => 'Nominal Pembayaran terlalu kecil'], 422);
                }
                if ($nominal > $paymentLeft) {
                    $this->rollbackManualPaymentTransactions();
                    $nominalTagihan = 'Rp. ' . number_format($paymentLeft, 0, ',', '.');
                    $nominalPembayaran = 'Rp. ' . number_format($nominal, 0, ',', '.');
                    return response()->json(['message' => "Nominal Pembayaran untuk tagihan terlalu besar! <br>
                            Sisa tagihan: {$nominalTagihan} <br>
                            Nominal Pembayaran: {$nominalPembayaran}
                    "], 422);
                }
                if ($nominal < $paymentLeft && !mst_tagihan::canInstallment($item->BILLNM)) {
                    $this->rollbackManualPaymentTransactions();
                    return response()->json([
                        'message' => "Tagihan {$item->BILLNM} tidak dapat dicicil. Pembayaran harus lunas (Rp. " . number_format($paymentLeft, 0, ',', '.') . ').',
                    ], 422);
                }

                $paymentBuilder->payBill(
                    $item,
                    $nominal,
                    (string) $request->input('bank'),
                    $formattedDate,
                    $transno,
                    $request
                );

                $tagihanForPrint[] = $item->AA;
                $paymentsForReceipt[(string) $item->AA] = [
                    'nominal' => $nominal,
                    'fidbank' => (string) $request->input('bank'),
                    'tanggal' => $formattedDate,
                ];

                /*
                // Legacy: update scctbill + insert sccttran langsung dari PHP (nonaktif — pakai BuilderPaymentCash_MultiVAPerTagihan / BuilderPaymentBill_MultiVAPerTagihan)
                $billAm = (int) $item->BILLAM;
                $billPaid = (int) ($item->BILLPAID ?? 0);
                $newBillPaid = $billPaid + $nominal;
                $newPaymentLeft = max(0, $billAm - $newBillPaid);
                $existingInstal = (int) sccttran::where('BILLID', $item->AA)->max('INSTALLMENT');
                $newInstallment = $existingInstal + 1;
                $isFullyPaid = $newPaymentLeft <= 0;

                $item->update([
                    'BILLPAID' => $newBillPaid,
                    'PAYMENTLEFT' => $newPaymentLeft,
                    'PAIDDT' => $formattedDate,
                    'PAIDDT_ACTUAL' => date('Y-m-d H:i:s'),
                    'FIDBANK' => $request->input('bank'),
                    'NOREFF' => 'TELLER',
                    'PAIDST' => $isFullyPaid ? 1 : 0,
                    'INSTALLMENT' => $newInstallment,
                    'TRANSNO' => $transno,
                ]);

                $bank = (string) $request->input('bank');
                sccttran::create([
                    'CUSTID' => $siswa->CUSTID,
                    'METODE' => 'FROM TELLER',
                    'TRXDATE' => now(),
                    'NOREFF' => 'TELLER',
                    'FIDBANK' => $bank,
                    'DEBET' => $nominal,
                    'KREDIT' => $nominal,
                    'BILLID' => $item->AA,
                    'BILLTARGET' => $item->BILLNM,
                    'INSTALLMENT' => $newInstallment,
                    'TRANSNO' => $transno ?? Auth::user()->username,
                ]);
                */
            }
            if ($transno) {
                DB::table('ref_invoicedate')
                    ->where('idx_static', '=', 1)
                    ->increment('last_number');
            }
            $request->session()->put('key', 'value');

            $request->session()->forget('siswa_tagihan_baru_dibayar');
            $request->session()->forget('tagihan_baru_dibayar');
            $request->session()->forget('manual_pembayaran_receipt');
            session(['siswa_tagihan_baru_dibayar' => $siswa]);
            session(['tagihan_baru_dibayar' => $tagihanForPrint]);
            session(['manual_pembayaran_receipt' => [
                'bank' => (string) $request->input('bank'),
                'tanggal' => $formattedDate,
                'payments' => $paymentsForReceipt,
            ]]);
            DB::connection('DATA_MYSQL')->commit();
            DB::commit();
            Log::info('manual-pembayaran.store.success', [
                'siswa' => $request->input('siswa'),
                'bank' => $request->input('bank'),
                'total_bayar' => $totalBayar,
                'tagihan_terbayar' => count($tagihanForPrint),
            ]);
            return response()->json(['message' => $message], 200);
        } catch (QueryException $e) {
            $this->rollbackManualPaymentTransactions();
            Log::error('manual-pembayaran.store.query_exception', [
                'siswa' => $request->input('siswa'),
                'bank' => $request->input('bank'),
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            $sqlMessage = $e->getMessage();
            $message = 'Tagihan gagal dibayar';
            if (
                stripos($sqlMessage, 'BuilderPayment') !== false
                || stripos($sqlMessage, '1305') !== false
                || stripos($sqlMessage, "doesn't exist") !== false
            ) {
                $message = 'Function pembayaran belum ada di database. Hubungi admin DB (BuilderPaymentCash / BuilderPaymentCash_MultiVAPerTagihan).';
            } elseif (config('app.debug')) {
                $message = 'Tagihan gagal dibayar: ' . $sqlMessage;
            }

            return response()->json(['message' => $message], 422);
        } catch (Throwable $e) {
            $this->rollbackManualPaymentTransactions();
            Log::error('manual-pembayaran.store.unhandled_exception', [
                'siswa' => $request->input('siswa'),
                'bank' => $request->input('bank'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // RuntimeException dari builder sudah pesan user-friendly — tampilkan apa adanya.
            $message = $e instanceof \RuntimeException
                ? $e->getMessage()
                : (config('app.debug')
                    ? ('Tagihan gagal dibayar: ' . $e->getMessage())
                    : 'Tagihan gagal dibayar');

            return response()->json(['message' => $message], 422);
        }
    }

    private function rollbackManualPaymentTransactions(): void
    {
        if (DB::connection('DATA_MYSQL')->transactionLevel() > 0) {
            DB::connection('DATA_MYSQL')->rollBack();
        }

        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }

    public function cetakPembayaran(Request $request)
    {
        if ($request->session()->has('tagihan_baru_dibayar')) {
            $tagihanForPrint = $request->session()->get('tagihan_baru_dibayar');
            $cust = $request->session()->get('siswa_tagihan_baru_dibayar')->CUSTID;
            $whereAny = [
                'scctcust.NMCUST',
                'scctcust.NOCUST',
                'scctcust.NUM2ND',
            ];

            $select = array_merge($whereAny, [
                'scctcust.CODE02',
                'scctcust.DESC02',
                'scctcust.DESC03',
                'scctcust.DESC04',
                'scctcust.GENUS',
            ]);

            $siswa = scctcust::where('CUSTID', $cust)->select($select)->first();

            $tagihans = scctbill::whereIn('AA', $tagihanForPrint)->where('CUSTID', $cust)->get();
            if ($tagihans->isEmpty()) {
                return response()->json(['message' => 'Tagihan Tidak Ditemukan'], 422);
            }

            $receipt = $request->session()->get('manual_pembayaran_receipt', []);
            $enrichedTagihans = $this->enrichTagihansForReceipt($tagihans, (string) $cust, $receipt);
            $receiptBank = $receipt['bank'] ?? ($enrichedTagihans[0]['FIDBANK'] ?? null);
            $biayaLayanan = $this->resolveBiayaLayanan($receiptBank);

            if ($request->boolean('pdf')) {
                $receiptMode = strtolower((string)$request->query('receipt_mode', 'manual'));
                $isNisLikeReceipt = $receiptMode === 'nis';

                $viewName = $isNisLikeReceipt ? 'pdf.kuitansi_with_2000' : 'pdf.kuitansi';
                $viewData = [
                    'tagihans' => collect($enrichedTagihans)->map(fn ($row) => (object) $row),
                    'siswa' => $siswa,
                    'nis' => $request->boolean('no_daftar'),
                    'biayaLayanan' => $biayaLayanan,
                    'fidbank' => $receiptBank,
                ];

                $pdf = Pdf::loadView($viewName, $viewData);

                return $pdf->stream('bukti-pembayaran.pdf');
            }

            return response()->json([
                'tagihans' => $enrichedTagihans,
                'siswa' => $siswa,
                'biaya_layanan' => $biayaLayanan,
                'bank' => $receiptBank,
            ], 200);
        } else {
            return response()->json(['message' => 'Silakhan Lakukan pembayaran terlebih dahulu'], 422);
        }
    }

    public function cetakTagihan(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'tanggal' => ['required'],
                'siswa' => ['required'],
//                'bank' => ['required', 'in:1140000,1140001,1140002,1140003,1140004,1140005,1200001,1200002'],
                'tagihan.post' => ['required', 'array', 'min:1'],
                'tagihan.post.*' => ['required'],
            ],
            ValidationMessage::messages(),
            ValidationMessage::attributes()
        );
        if ($validator->fails()) return response()->json(['message' => 'Silahkan periksa form anda', 'error' => $validator->errors()], 422);


        $whereAny = [
            'scctcust.NMCUST',
            'scctcust.NOCUST',
            'scctcust.NUM2ND',
        ];

        $select = array_unique(array_merge($whereAny, [
            'scctcust.CODE02',
            'scctcust.DESC02',
            'scctcust.DESC03',
            'scctcust.DESC04',

        ]));

        $posts = collect((array)data_get($request->all(), 'tagihan.post', []))
            ->filter(fn($item) => !is_null($item) && $item !== '')
            ->map(fn($item) => is_numeric($item) ? (int)$item : $item)
            ->values()
            ->all();

        if (empty($posts)) {
            return response()->json(['message' => 'Silahkan pilih tagihan yang akan dipratinjau'], 422);
        }

        $siswa = scctcust::where('scctcust.CUSTID', $request->siswa)
            ->select($select)
            ->first();

        if (!$siswa) return response()->json(['message' => 'Siswa tidak ditemukan'], 422);
        if ($denied = SchoolScope::denyStudentMessage($siswa)) {
            return response()->json(['message' => $denied], 422);
        }
        try {
            $tagihans = scctbill::leftJoin('scctcust', 'scctcust.CUSTID', 'scctbill.CUSTID')
                ->where('scctbill.CUSTID', $request->siswa)
                ->whereIn('scctbill.AA', $posts)
                ->select(['scctbill.AA',
                    'scctbill.BILLNM',
                    'scctbill.BILLAM',
                    'scctbill.BILLAC',
                    'scctbill.PAIDST',
                    'scctbill.PAIDDT',
                    'scctbill.BTA',
                    'scctbill.FIDBANK',
                    'scctbill.FUrutan',])
                ->get();

            if (!$tagihans) return response()->json(['message' => 'Tagihan Tidak Ditemukan'], 422);
            $isPreviewNoDaftar = filter_var($request->input('preview_by_nodaftar', false), FILTER_VALIDATE_BOOLEAN);
            $pdf = Pdf::loadView('export.tagihan_manual', [
                'tagihans' => $tagihans,
                'siswa' => $siswa,
                'nis' => $isPreviewNoDaftar,
            ]);
            return $pdf->download('tagihan-siswa.pdf');
        } catch (Throwable $e) {
            report($e);
            $message = config('app.debug')
                ? ('Gagal membuat pratinjau: ' . $e->getMessage())
                : 'Tagihan Tidak Ditemukan';

            return response()->json(['message' => $message], 422);
        }
    }

    public function updateNocust(Request $request)
    {
        $request->validate([
            'custid' => ['required'],
            'nocust' => ['required', 'regex:/^[0-9]+$/'],
        ], ValidationMessage::messages(), ValidationMessage::attributes());

        $siswa = scctcust::where('CUSTID', $request->custid)->first();
        if (!$siswa) {
            return response()->json(['message' => 'Siswa tidak ditemukan'], 422);
        }
        if ($denied = SchoolScope::denyStudentMessage($siswa)) {
            return response()->json(['message' => $denied], 422);
        }

        $nocust = trim((string) $request->nocust);
        $exists = scctcust::where('NOCUST', $nocust)
            ->where('CUSTID', '!=', $siswa->CUSTID)
            ->exists();
        if ($exists) {
            return response()->json(['message' => 'NIS / nomor VA sudah digunakan siswa lain'], 422);
        }

        try {
            $siswa->NOCUST = $nocust;
            $siswa->save();

            $payload = [
                'message' => 'Nomor VA berhasil diperbarui',
                'nocust' => $nocust,
                'nova' => MultiVa::formatNoVaBoth($nocust),
                'nova_open' => MultiVa::formatNoVa($nocust, MultiVa::OPEN),
            ];
            if (MultiVa::closeEnabled()) {
                $payload['nova_close'] = MultiVa::formatNoVa($nocust, MultiVa::CLOSE);
            }

            return response()->json($payload, 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Gagal memperbarui nomor VA', 'error' => $e->getMessage()], 422);
        }
    }

    private function enrichTagihansForReceipt($tagihans, string $custId, array $receipt = []): array
    {
        $receiptPayments = $receipt['payments'] ?? [];
        $receiptBank = $receipt['bank'] ?? null;
        $receiptDate = $receipt['tanggal'] ?? null;

        $billIds = $tagihans
            ->pluck('AA')
            ->map(fn ($aa) => (string) $aa)
            ->filter()
            ->values()
            ->all();

        $latestTransactions = collect();
        if ($billIds !== []) {
            $latestTransactions = sccttran::query()
                ->where('CUSTID', $custId)
                ->whereIn('BILLID', $billIds)
                ->whereRaw('CAST(COALESCE(DEBET, 0) AS SIGNED) > 0')
                ->orderByDesc('TRXDATE')
                ->get()
                ->groupBy(fn ($trx) => (string) $trx->BILLID)
                ->map(fn ($group) => $group->first());
        }

        return $tagihans->map(function ($tagihan) use ($receiptPayments, $receiptBank, $receiptDate, $latestTransactions) {
            $aa = (string) $tagihan->AA;
            $receiptPayment = $receiptPayments[$aa] ?? null;
            $latestTrx = $latestTransactions->get($aa);

            $fidBank = $receiptPayment['fidbank']
                ?? $latestTrx?->FIDBANK
                ?? $receiptBank
                ?? $tagihan->FIDBANK;

            // ANDROID hanya dari scctbill.NOREFF = Mobile; metode lain dari scctbill.FIDBANK
            $billNoreff = $tagihan->NOREFF ?? null;
            $billFidBank = $tagihan->FIDBANK ?? $fidBank;
            $fidBank = MetodeBayarHelper::resolveDisplayFidBank(
                $billFidBank !== null ? (string) $billFidBank : null,
                $billNoreff !== null ? (string) $billNoreff : null
            );

            $nominalBayar = (int) ($receiptPayment['nominal'] ?? $latestTrx?->DEBET ?? 0);
            if ($nominalBayar <= 0) {
                $nominalBayar = (int) ($tagihan->BILLPAID ?? $tagihan->BILLAM ?? 0);
            }

            $paidDt = $receiptPayment['tanggal']
                ?? $latestTrx?->TRXDATE
                ?? $tagihan->PAIDDT
                ?? $receiptDate;

            $row = $tagihan->toArray();
            $row['FIDBANK'] = $fidBank;
            $row['NOREFF'] = $billNoreff;
            $row['BILL_NOREFF'] = $billNoreff;
            $row['PAIDDT'] = $paidDt;
            $row['NOMINAL_BAYAR'] = $nominalBayar;
            $row['NOVA'] = MultiVa::formatNoVaFromBill(
                $tagihan->NOCUST ?? $row['NOCUST'] ?? null,
                $tagihan
            );

            return $row;
        })->values()->all();
    }

    private function resolvePaymentLeft(object $item): int
    {
        $billAm = (int) ($item->BILLAM ?? 0);
        $billPaid = (int) ($item->BILLPAID ?? 0);
        $paymentLeft = (int) ($item->PAYMENTLEFT ?? 0);

        if ($paymentLeft > 0) {
            return $paymentLeft;
        }

        $remaining = max(0, $billAm - $billPaid);
        if ($remaining > 0) {
            return $remaining;
        }

        if ((int) ($item->PAIDST ?? 0) === 0 && $billAm > 0) {
            return $billAm;
        }

        return 0;
    }

    /** Manual Cash/BMI dkk: 2000. Manual SALDO: 0. */
    private function resolveBiayaLayanan(?string $fidBank): int
    {
        $fidBank = preg_replace('/\D/', '', (string) $fidBank);

        if ($fidBank === '' || $fidBank === '1140002') {
            return 0;
        }

        return 2000;
    }
}
