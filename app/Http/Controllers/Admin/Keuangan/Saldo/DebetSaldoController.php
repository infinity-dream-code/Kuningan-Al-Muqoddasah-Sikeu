<?php

namespace App\Http\Controllers\Admin\Keuangan\Saldo;

use App\Http\Controllers\Controller;
use App\Support\MultiVa;
use App\Support\SchoolScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Debet Saldo: keluarkan sisa saldo VA siswa.
 * Tulis ke sccttran (DEBET) + scctcashout dalam satu transaksi.
 */
class DebetSaldoController extends Controller
{
    public const METODE = 'DEBET SALDO';
    public const FIDBANK = 'CASH';
    public const KDCHANNEL = '11';

    private string $title = 'Keuangan';
    private string $mainTitle = 'Saldo';
    private string $dataTitle = 'Debet Saldo';

    public function index()
    {
        return view('admin.keuangan.saldo.debet_saldo.index', [
            'title' => $this->title,
            'mainTitle' => $this->mainTitle,
            'dataTitle' => $this->dataTitle,
            'columnsUrl' => route('admin.keuangan.saldo.debet-saldo.get-column'),
            'datasUrl' => route('admin.keuangan.saldo.debet-saldo.get-data'),
            'searchUrl' => route('admin.keuangan.saldo.debet-saldo.siswa-search'),
            'detailUrl' => route('admin.keuangan.saldo.debet-saldo.siswa-detail'),
            'storeUrl' => route('admin.keuangan.saldo.debet-saldo.store'),
        ]);
    }

    public function siswaSearch(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 3) {
            return response()->json(['rows' => []]);
        }

        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q) . '%';

        $query = DB::connection('DATA_MYSQL')
            ->table('scctcust')
            ->where(function ($w) use ($like) {
                $w->where('scctcust.NOCUST', 'like', $like)
                    ->orWhere('scctcust.NUM2ND', 'like', $like)
                    ->orWhere('scctcust.NMCUST', 'like', $like);
            });
        SchoolScope::applyStudent($query, 'scctcust');

        $rows = $query->orderBy('scctcust.NMCUST')
            ->limit(15)
            ->get(['scctcust.CUSTID', 'scctcust.NOCUST', 'scctcust.NUM2ND', 'scctcust.NMCUST', 'scctcust.DESC02', 'scctcust.CODE02'])
            ->map(fn ($row) => [
                'custid' => (int) $row->CUSTID,
                'nis' => $this->nisOf($row),
                'nama' => trim((string) $row->NMCUST),
                'kelas' => trim((string) ($row->DESC02 ?? '')),
                'unit' => trim((string) ($row->CODE02 ?? '')),
            ])
            ->values();

        return response()->json(['rows' => $rows]);
    }

    public function siswaDetail(Request $request): JsonResponse
    {
        $siswa = $this->findSiswa((int) $request->query('custid', 0));
        if (!$siswa) {
            return response()->json(['message' => 'Siswa tidak ditemukan.'], 404);
        }

        return response()->json([
            'custid' => (int) $siswa->CUSTID,
            'nis' => $this->nisOf($siswa),
            'nama' => trim((string) $siswa->NMCUST),
            'kelas' => trim((string) ($siswa->DESC02 ?? '')),
            'unit' => trim((string) ($siswa->CODE02 ?? '')),
            'saldo' => MultiVa::custSaldo($siswa->CUSTID, MultiVa::OPEN),
            'riwayat' => $this->riwayatSiswa((int) $siswa->CUSTID),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'custid' => ['required', 'integer', 'min:1'],
            'nominal' => ['required', 'integer', 'min:1'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ], [
            'custid.required' => 'Pilih siswa terlebih dahulu.',
            'custid.min' => 'Pilih siswa terlebih dahulu.',
            'nominal.required' => 'Nominal harus diisi.',
            'nominal.min' => 'Nominal harus lebih dari 0.',
        ]);

        $custid = (int) $validated['custid'];
        $nominal = (int) $validated['nominal'];
        $keterangan = trim((string) ($validated['keterangan'] ?? ''));

        if (!$this->findSiswa($custid)) {
            return response()->json(['message' => 'Siswa tidak ditemukan.'], 404);
        }

        $teller = mb_substr($this->tellerName(), 0, 15);
        $conn = DB::connection('DATA_MYSQL');

        try {
            $result = $conn->transaction(function () use ($conn, $custid, $nominal, $keterangan, $teller) {
                $conn->table('scctcust')->where('CUSTID', $custid)->lockForUpdate()->first(['CUSTID']);

                $saldo = MultiVa::custSaldo($custid, MultiVa::OPEN);
                if ($nominal > $saldo) {
                    return ['ok' => false, 'saldo' => $saldo];
                }

                $now = now();
                $transNo = $this->nextTransNo($now);

                $tran = [
                    'CUSTID' => $custid,
                    'TRXDATE' => $now->format('Y-m-d H:i:s'),
                    'DEBET' => $nominal,
                    'KREDIT' => 0,
                    'METODE' => self::METODE,
                    'FIDBANK' => self::FIDBANK,
                    'REFFBANK' => MultiVa::OPEN,
                    'KDCHANNEL' => self::KDCHANNEL,
                    'NOREFF' => $transNo,
                    'TRANSNO' => $transNo,
                ];
                $cashout = [
                    'CUSTID' => $custid,
                    'TanggalKeluar' => $now->format('Y-m-d H:i:s'),
                    'Teller' => $teller,
                    'BILLAM' => (string) $nominal,
                    'TRANSNO' => $transNo,
                    'FIDBANK' => self::FIDBANK,
                ];
                if ($keterangan !== '') {
                    if ($this->hasColumn('sccttran', 'Keterangan')) {
                        $tran['Keterangan'] = $keterangan;
                    }
                    if ($this->hasColumn('scctcashout', 'Keterangan')) {
                        $cashout['Keterangan'] = $keterangan;
                    }
                }

                $conn->table('sccttran')->insert($tran);
                $conn->table('scctcashout')->insert($cashout);

                return ['ok' => true, 'transno' => $transNo];
            });
        } catch (\Throwable $e) {
            Log::error('debet-saldo.store failed', ['custid' => $custid, 'message' => $e->getMessage()]);

            return response()->json(['message' => 'Gagal menyimpan debet saldo: ' . $e->getMessage()], 500);
        }

        if (!$result['ok']) {
            return response()->json([
                'message' => 'Saldo tidak cukup. Sisa saldo: Rp ' . number_format($result['saldo'], 0, ',', '.'),
                'saldo' => $result['saldo'],
            ], 422);
        }

        return response()->json([
            'message' => 'Debet saldo berhasil. No. Transaksi: ' . $result['transno'],
            'transno' => $result['transno'],
            'saldo' => MultiVa::custSaldo($custid, MultiVa::OPEN),
            'riwayat' => $this->riwayatSiswa($custid),
        ]);
    }

    public function getColumn()
    {
        return [
            ['data' => null, 'columnType' => 'row', 'name' => 'No', 'exportable' => true],
            ['data' => 'TanggalKeluar', 'name' => 'Tanggal', 'orderable' => true, 'columnType' => 'timestamp', 'exportable' => true],
            ['data' => 'TRANSNO', 'name' => 'No Transaksi', 'searchable' => true, 'orderable' => true, 'exportable' => true],
            ['data' => 'NOCUST', 'name' => 'NIS', 'searchable' => true, 'orderable' => true, 'exportable' => true],
            ['data' => 'NMCUST', 'name' => 'Nama', 'searchable' => true, 'orderable' => true, 'exportable' => true],
            ['data' => 'BILLAM', 'name' => 'Nominal', 'orderable' => true, 'className' => 'dt-right', 'columnType' => 'currency', 'exportable' => true],
            ['data' => 'Teller', 'name' => 'Teller', 'searchable' => true, 'orderable' => true, 'exportable' => true],
        ];
    }

    public function getData(Request $request): JsonResponse
    {
        $draw = (int) $request->get('draw');
        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);
        $columns = $request->get('columns', []);
        $order = $request->get('order', []);
        $searchValue = trim((string) ($request->get('search')['value'] ?? ''));

        $sortable = [
            'TanggalKeluar' => 'co.TanggalKeluar',
            'TRANSNO' => 'co.TRANSNO',
            'NOCUST' => 'c.NOCUST',
            'NMCUST' => 'c.NMCUST',
            'BILLAM' => DB::raw('CAST(co.BILLAM AS SIGNED)'),
            'Teller' => 'co.Teller',
        ];
        $orderColumn = 'co.TanggalKeluar';
        $orderDir = 'desc';
        if (!empty($order)) {
            $requested = $columns[$order[0]['column'] ?? -1]['data'] ?? null;
            if ($requested && isset($sortable[$requested])) {
                $orderColumn = $sortable[$requested];
                $orderDir = ($order[0]['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
            }
        }

        $base = DB::connection('DATA_MYSQL')
            ->table('scctcashout as co')
            ->leftJoin('scctcust as c', 'c.CUSTID', '=', 'co.CUSTID');
        SchoolScope::apply($base, 'c');

        $totalRecords = (clone $base)->count();

        if ($searchValue !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $searchValue) . '%';
            $base->where(function ($q) use ($like) {
                $q->where('co.TRANSNO', 'like', $like)
                    ->orWhere('c.NOCUST', 'like', $like)
                    ->orWhere('c.NUM2ND', 'like', $like)
                    ->orWhere('c.NMCUST', 'like', $like)
                    ->orWhere('co.Teller', 'like', $like);
            });
        }

        $totalFiltered = (clone $base)->count();
        $totalNominal = (int) (clone $base)->sum(DB::raw('CAST(co.BILLAM AS SIGNED)'));

        $records = $base->orderBy($orderColumn, $orderDir)
            ->orderByDesc('co.urut')
            ->skip($start)
            ->take($length > 0 ? $length : 10)
            ->get(['co.TanggalKeluar', 'co.TRANSNO', 'co.BILLAM', 'co.Teller', 'c.NOCUST', 'c.NUM2ND', 'c.NMCUST'])
            ->map(function ($row) {
                $row->NOCUST = $this->nisOf($row);
                $row->BILLAM = (int) trim((string) $row->BILLAM);
                $row->Teller = trim((string) $row->Teller);
                unset($row->NUM2ND);

                return $row;
            });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalFiltered,
            'data' => $records,
            'totals' => [
                'nominal' => ['location' => 5, 'value' => $totalNominal, 'columnType' => 'currency'],
            ],
        ]);
    }

    private function findSiswa(int $custid): ?object
    {
        if ($custid <= 0) {
            return null;
        }

        $query = DB::connection('DATA_MYSQL')
            ->table('scctcust')
            ->where('scctcust.CUSTID', $custid);
        SchoolScope::applyStudent($query, 'scctcust');

        return $query->first(['scctcust.CUSTID', 'scctcust.NOCUST', 'scctcust.NUM2ND', 'scctcust.NMCUST', 'scctcust.DESC02', 'scctcust.CODE02']);
    }

    private function riwayatSiswa(int $custid): Collection
    {
        return DB::connection('DATA_MYSQL')
            ->table('scctcashout')
            ->where('CUSTID', $custid)
            ->orderByDesc('TanggalKeluar')
            ->orderByDesc('urut')
            ->limit(50)
            ->get(['TanggalKeluar', 'TRANSNO', 'BILLAM', 'Teller'])
            ->map(fn ($row) => [
                'tanggal' => $row->TanggalKeluar ? Carbon::parse($row->TanggalKeluar)->format('d-m-Y H:i') : '-',
                'transno' => trim((string) $row->TRANSNO),
                'nominal' => (int) trim((string) $row->BILLAM),
                'teller' => trim((string) $row->Teller),
            ]);
    }

    /** Format TRANSNO: Ymd + 5 digit urut harian (berdasarkan scctcashout). */
    private function nextTransNo(Carbon $date): string
    {
        $prefix = $date->format('Ymd');
        $last = DB::connection('DATA_MYSQL')
            ->table('scctcashout')
            ->where('TRANSNO', 'like', $prefix . '%')
            ->whereRaw('CHAR_LENGTH(TRIM(TRANSNO)) = 13')
            ->max('TRANSNO');

        $seq = $last ? ((int) substr(trim($last), 8)) + 1 : 1;

        return $prefix . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    private function nisOf(object $row): string
    {
        $nis = trim((string) ($row->NOCUST ?? ''));

        return ($nis !== '' && $nis !== '-') ? $nis : trim((string) ($row->NUM2ND ?? ''));
    }

    private function tellerName(): string
    {
        $user = Auth::user();
        $name = trim((string) ($user->users ?? ''));

        return $name !== '' ? $name : 'ADMIN';
    }

    private function hasColumn(string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (!array_key_exists($key, $cache)) {
            try {
                $cache[$key] = Schema::connection('DATA_MYSQL')->hasColumn($table, $column);
            } catch (\Throwable $e) {
                $cache[$key] = false;
            }
        }

        return $cache[$key];
    }
}
