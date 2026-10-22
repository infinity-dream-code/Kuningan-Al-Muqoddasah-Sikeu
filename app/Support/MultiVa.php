<?php

namespace App\Support;

use App\Models\mst_tagihan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class MultiVa
{
    public const OPEN = '93';
    public const CLOSE = '94';
    public const OPEN_PREFIX = '777723';
    public const CLOSE_PREFIX = '777723';
    public const LEGACY_OPEN_PREFIXES = ['7797793', '779793', '797793'];
    public const LEGACY_CLOSE_PREFIXES = ['7797794', '779794', '797794'];

    private static ?\Illuminate\Support\Collection $masters = null;

    public static function closeEnabled(): bool
    {
        return (bool) config('app.va_close_enabled', false);
    }

    /** Apakah mst_tagihan punya kolom VA (beberapa client open-only belum punya). */
    public static function masterHasVaColumn(): bool
    {
        static $resolved = false;
        static $has = false;

        if ($resolved) {
            return $has;
        }

        $resolved = true;

        try {
            $schema = Schema::connection('DATA_MYSQL');
            $has = $schema->hasColumn('mst_tagihan', 'VA') || $schema->hasColumn('mst_tagihan', 'va');
        } catch (\Throwable $e) {
            $has = false;
        }

        return $has;
    }

    /** UI/API boleh memilih Close hanya jika close enabled DAN kolom VA ada. */
    public static function masterVaSelectable(): bool
    {
        return self::closeEnabled() && self::masterHasVaColumn();
    }

    /**
     * Nama kolom VA di scctbill (va / VA), atau null jika belum ada di DB client.
     * Tanpa ini, SELECT scctbill.va memicu 500 di banyak halaman.
     */
    public static function billVaColumn(): ?string
    {
        static $resolved = false;
        static $column = null;

        if ($resolved) {
            return $column;
        }

        $resolved = true;

        try {
            $schema = Schema::connection('DATA_MYSQL');
            if ($schema->hasColumn('scctbill', 'va')) {
                $column = 'va';
            } elseif ($schema->hasColumn('scctbill', 'VA')) {
                $column = 'VA';
            }
        } catch (\Throwable $e) {
            $column = null;
        }

        return $column;
    }

    public static function billVaSelect(string $table = 'scctbill'): ?string
    {
        $column = self::billVaColumn();

        return $column ? "{$table}.{$column}" : null;
    }

    public static function pushBillVaSelect(array &$select, string $table = 'scctbill'): void
    {
        $expr = self::billVaSelect($table);
        if ($expr !== null) {
            $select[] = $expr;
        }
    }

    public static function billHasColumn(string $column): bool
    {
        static $cache = [];

        if (array_key_exists($column, $cache)) {
            return $cache[$column];
        }

        try {
            $cache[$column] = Schema::connection('DATA_MYSQL')->hasColumn('scctbill', $column);
        } catch (\Throwable $e) {
            $cache[$column] = false;
        }

        return $cache[$column];
    }

    /** Buang select kolom VA / isINSTALLABLE jika belum ada di DB (hindari 500). */
    public static function filterBillSelect(array $select): array
    {
        return array_values(array_filter($select, function ($expr) {
            if (!is_string($expr)) {
                return true;
            }

            if (preg_match('/\bscctbill\.(va|VA)\b/', $expr) || preg_match('/^(va|VA)$/', $expr)) {
                return self::billVaColumn() !== null;
            }

            if (stripos($expr, 'isINSTALLABLE') !== false) {
                return self::billHasColumn('isINSTALLABLE');
            }

            return true;
        }));
    }

    /** Siapkan payload insert/update scctbill agar aman di DB tanpa kolom va. */
    public static function sanitizeBillPayload(array $payload): array
    {
        $vaCol = self::billVaColumn();
        if ($vaCol === null) {
            unset($payload['va'], $payload['VA']);
        } else {
            $raw = $payload[$vaCol] ?? $payload['va'] ?? $payload['VA'] ?? null;
            unset($payload['va'], $payload['VA']);
            if (!self::closeEnabled()) {
                $payload[$vaCol] = self::OPEN;
            } else {
                $payload[$vaCol] = self::normalize($raw) ?? self::OPEN;
            }
        }

        if (!self::billHasColumn('isINSTALLABLE')) {
            unset($payload['isINSTALLABLE']);
        }

        return $payload;
    }

    public static function openPrefix(): string
    {
        $raw = preg_replace('/\D/', '', (string) config('app.va_open', self::OPEN_PREFIX));

        return $raw !== '' ? $raw : self::OPEN_PREFIX;
    }

    public static function closePrefix(): string
    {
        if (!self::closeEnabled()) {
            return self::openPrefix();
        }

        $raw = preg_replace('/\D/', '', (string) config('app.va_close', self::CLOSE_PREFIX));

        return $raw !== '' ? $raw : self::CLOSE_PREFIX;
    }

    public static function prefix(string $reffBank): string
    {
        if (!self::closeEnabled()) {
            return self::openPrefix();
        }

        return self::normalize($reffBank) === self::CLOSE
            ? self::closePrefix()
            : self::openPrefix();
    }

    public static function normalize(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $raw);
        // 1/93 = Open, 2/94 = Close (DB Muqoddasah pakai PerTagihan1 / PerTagihan2)
        if (in_array($digits, array_merge(['1', self::OPEN, self::openPrefix()], self::LEGACY_OPEN_PREFIXES), true)) {
            return self::OPEN;
        }
        if (in_array($digits, array_merge(['2', self::CLOSE, self::closePrefix()], self::LEGACY_CLOSE_PREFIXES), true)) {
            return self::CLOSE;
        }

        $upper = strtoupper($raw);
        if (in_array($upper, ['OPEN', '93 - OPEN', 'OPEN PAYMENT'], true)) {
            return self::OPEN;
        }
        if (in_array($upper, ['CLOSE', '94 - CLOSE', 'CLOSED'], true)) {
            return self::CLOSE;
        }

        return null;
    }

    public static function isInstallment(string $reffBank): int
    {
        return self::normalize($reffBank) === self::OPEN ? 1 : 0;
    }

    public static function shortLabel(mixed $value): string
    {
        return match (self::normalize($value)) {
            self::OPEN => 'Open',
            self::CLOSE => 'Close',
            default => '-',
        };
    }

    public static function resolveFromMaster(object $tagihan): ?string
    {
        if (!self::masterHasVaColumn() || !self::closeEnabled()) {
            return self::OPEN;
        }

        return self::normalize($tagihan->VA ?? $tagihan->va ?? null) ?? self::OPEN;
    }

    public static function masterOptionText(object $item): string
    {
        $name = trim((string) ($item->tagihan ?? ''));
        $raw = trim((string) ($item->VA ?? $item->va ?? ''));
        $va = self::normalize($raw);
        $suffix = match ($va) {
            self::OPEN => '93 - Open',
            self::CLOSE => '94 - Close',
            default => ($raw !== '' ? $raw : '-'),
        };

        return $name === '' ? $suffix : "{$name} ({$suffix})";
    }

    public static function optionLabel(string $reffBank): string
    {
        return match (self::normalize($reffBank)) {
            self::OPEN => '93 - Open',
            self::CLOSE => '94 - Close',
            default => $reffBank,
        };
    }

    public static function saldoPageTitle(string $reffBank): string
    {
        if (!self::closeEnabled()) {
            return 'Saldo VA';
        }

        return self::normalize($reffBank) === self::CLOSE
            ? 'Saldo VA Close'
            : 'Saldo VA Open';
    }

    public static function saldoView(string $reffBank): string
    {
        // Client open-only: view utama = v_saldo_va
        if (!self::closeEnabled()) {
            return 'v_saldo_va';
        }

        $va = self::normalize($reffBank) ?? self::OPEN;

        return "v_saldo_va_multivapertagihan{$va}";
    }

    public static function transView(string $reffBank): string
    {
        if (!self::closeEnabled()) {
            return 'v_trans_saldo';
        }

        $va = self::normalize($reffBank) ?? self::OPEN;

        return "v_trans_saldo_multivapertagihan{$va}";
    }

    public static function tableExists(string $table): bool
    {
        static $cache = [];

        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        try {
            $cache[$table] = Schema::connection('DATA_MYSQL')->hasTable($table);
        } catch (\Throwable $e) {
            $cache[$table] = false;
        }

        return $cache[$table];
    }

    public static function resolveExistingView(array $candidates): ?string
    {
        foreach ($candidates as $name) {
            $name = trim((string) $name);
            if ($name !== '' && self::tableExists($name)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Query saldo siswa.
     * Open-only: hitung dari sccttran (jangan pakai v_saldo_va — view server sering 500
     * karena GROUP BY / DEFINER; view tidak diubah, app yang adapt).
     */
    public static function saldoQuery(string $reffBank)
    {
        if (!self::closeEnabled()) {
            return self::fallbackSaldoQuery();
        }

        $view = self::resolveExistingView([
            self::saldoView($reffBank),
            'v_saldo_va_multivapertagihan93',
            'v_saldo_va_multivapertagihan1',
            'v_saldo_va_multivapertagihan94',
            'v_saldo_va_multivapertagihan2',
            'v_saldo_virtual_account',
        ]);

        if ($view) {
            return DB::connection('DATA_MYSQL')->table($view);
        }

        return self::fallbackSaldoQuery();
    }

    /** View transaksi saldo: v_trans_saldo (open-only) / multiVA / fallback. */
    public static function transQuery(string $reffBank)
    {
        $view = self::resolveExistingView([
            self::transView($reffBank),
            'v_trans_saldo',
            'v_trans_saldo_multivapertagihan93',
            'v_trans_saldo_multivapertagihan1',
            'v_trans_saldo_multivapertagihan94',
            'v_trans_saldo_multivapertagihan2',
            'v_trans_saldo_va',
        ]);

        if ($view) {
            return DB::connection('DATA_MYSQL')->table($view);
        }

        return self::fallbackTransQuery();
    }

    /** Fallback Saldo VA tanpa view multi-VA (exclude manual cash 1140000). */
    public static function fallbackSaldoQuery()
    {
        $conn = DB::connection('DATA_MYSQL');

        $agg = $conn->table('sccttran')
            ->select([
                'CUSTID',
                DB::raw('COALESCE(SUM(KREDIT), 0) - COALESCE(SUM(DEBET), 0) AS SALDO'),
            ])
            ->where(function ($q) {
                $q->whereNull('FIDBANK')
                    ->orWhereRaw("TRIM(COALESCE(CAST(FIDBANK AS CHAR), '')) = ''")
                    ->orWhereRaw("TRIM(COALESCE(CAST(FIDBANK AS CHAR), '')) != ?", ['1140000']);
            })
            ->groupBy('CUSTID');

        return $conn->table('scctcust')
            ->leftJoinSub($agg, 'trx', function ($join) {
                $join->on('trx.CUSTID', '=', 'scctcust.CUSTID');
            })
            ->select([
                'scctcust.CUSTID as CUSTID',
                'scctcust.NOCUST as NOCUST',
                'scctcust.NMCUST as NMCUST',
                'scctcust.CODE01 as CODE01',
                'scctcust.CODE02 as CODE02',
                'scctcust.CODE03 as CODE03',
                'scctcust.DESC02 as DESC02',
                'scctcust.DESC03 as DESC03',
                'scctcust.DESC04 as DESC04',
                'scctcust.NUM2ND as NUM2ND',
                DB::raw('COALESCE(trx.SALDO, 0) AS SALDO'),
            ]);
    }

    public static function fallbackTransQuery()
    {
        return DB::connection('DATA_MYSQL')
            ->table('sccttran')
            ->where(function ($q) {
                $q->whereNull('FIDBANK')
                    ->orWhereRaw("TRIM(COALESCE(CAST(FIDBANK AS CHAR), '')) = ''")
                    ->orWhereRaw("TRIM(COALESCE(CAST(FIDBANK AS CHAR), '')) != ?", ['1140000']);
            });
    }

    /**
     * Suffix function DB: PerTagihan1 (Open) / PerTagihan2 (Close).
     * Bukan 93/94 — sesuai function di kuningan_al_muqoddasah.
     */
    public static function paymentSuffix(string $reffBank): string
    {
        return self::normalize($reffBank) === self::CLOSE ? '2' : '1';
    }

    /** REFFBANK yang dipakai function PerTagihan1/2 saat cek/debet saldo. */
    public static function paymentReffBank(string $reffBank): string
    {
        return self::paymentSuffix($reffBank);
    }

    public static function paymentFunction(string $reffBank): string
    {
        return 'BuilderPaymentBill_MultiVAPerTagihan' . self::paymentSuffix($reffBank);
    }

    /** Nama lama sebelum rename (salah copas _BankBayar_). */
    public static function paymentFunctionLegacy(string $reffBank): string
    {
        return 'BuilderPaymentBill_BankBayar_MultiVAPerTagihan' . self::paymentSuffix($reffBank);
    }

    public static function cashPaymentFunction(): string
    {
        return 'BuilderPaymentCash_MultiVAPerTagihan';
    }

    public static function cancelProcedure(string $reffBank): string
    {
        return 'CancelPaymentSaldo_MultiVAPerTagihan';
    }

    /** Nama lama: CancelPaymentSaldo_MultiVAPerTagihan93 / 94 */
    public static function cancelProcedureLegacy(string $reffBank): string
    {
        $va = self::normalize($reffBank) ?? self::OPEN;

        return "CancelPaymentSaldo_MultiVAPerTagihan{$va}";
    }

    public static function vaForBillName(?string $billName, mixed $existingVa = null): ?string
    {
        $fromExisting = self::normalize($existingVa);
        if ($fromExisting) {
            return $fromExisting;
        }

        $billName = trim((string) $billName);
        if ($billName === '') {
            return null;
        }

        $mst = mst_tagihan::query()->where('tagihan', $billName)->first();

        return $mst ? self::resolveFromMaster($mst) : null;
    }

    public static function resolveFromBill(object $bill): ?string
    {
        if (!self::closeEnabled()) {
            return self::OPEN;
        }

        return self::vaForBillName(
            $bill->BILLNM ?? null,
            $bill->va ?? $bill->VA ?? null
        );
    }

    public static function requireFromMaster(mst_tagihan $tagihan): string
    {
        if (!self::masterHasVaColumn() || !self::closeEnabled()) {
            return self::OPEN;
        }

        $raw = trim((string) ($tagihan->VA ?? $tagihan->va ?? ''));
        if ($raw === '') {
            // Open-only / kolom VA kosong â†’ default Open, jangan throw 500.
            return self::OPEN;
        }

        return self::normalize($raw) ?? self::OPEN;
    }

    public static function custSaldo(string|int|null $custId, string $reffBank): int
    {
        if (blank($custId)) {
            return 0;
        }

        try {
            $row = self::saldoQuery($reffBank)
                ->where('CUSTID', $custId)
                ->first();

            return (int) ($row->SALDO ?? 0);
        } catch (\Throwable $e) {
            // View saldo rusak / tidak cocok schema → hitung dari sccttran.
            try {
                $row = self::fallbackSaldoQuery()
                    ->where('scctcust.CUSTID', $custId)
                    ->first();

                return (int) ($row->SALDO ?? 0);
            } catch (\Throwable $fallbackError) {
                return 0;
            }
        }
    }

    public static function masters(): \Illuminate\Support\Collection
    {
        if (self::$masters === null) {
            self::$masters = mst_tagihan::query()->orderBy('urut')->get();
        }

        return self::$masters;
    }

    public static function masterByBillName(?string $billName): ?object
    {
        $key = strtoupper(trim((string) $billName));
        if ($key === '') {
            return null;
        }

        return self::masters()->first(
            fn ($row) => strtoupper(trim((string) $row->tagihan)) === $key
        );
    }

    public static function masterByLike(string $prefix): ?object
    {
        $needle = strtoupper(trim($prefix, '%'));
        if ($needle === '') {
            return null;
        }

        return self::masters()->first(
            fn ($row) => str_starts_with(strtoupper(trim((string) $row->tagihan)), $needle)
        );
    }

    /**
     * Prefix bank: VA 93/94 (atau nomor lengkap) dulu, lalu cicil di master/tagihan.
     * VA lama (mis. 81) + non-cicil â†’ Close 797794; cicil â†’ Open 797793.
     */
    public static function prefixFromContext(mixed $va = null, mixed $isInstallment = null, ?string $billName = null): string
    {
        // Client open-only: semua tagihan pakai prefix Open (777723).
        if (!self::closeEnabled()) {
            return self::openPrefix();
        }

        $normalized = self::normalize($va);
        if ($normalized !== null) {
            return self::prefix($normalized);
        }

        $mst = $billName ? self::masterByBillName($billName) : null;
        if ($mst) {
            if (self::masterHasVaColumn()) {
                $normalized = self::normalize($mst->VA ?? $mst->va ?? null);
                if ($normalized !== null) {
                    return self::prefix($normalized);
                }
            }
            if ($isInstallment === null || $isInstallment === '') {
                $isInstallment = $mst->isINSTALLMENT ?? null;
            }
        }

        if ($isInstallment !== null && $isInstallment !== '') {
            return (int) $isInstallment === 1 ? self::openPrefix() : self::closePrefix();
        }

        return self::openPrefix();
    }

    public static function prefixFromMaster(?object $tagihan): string
    {
        if (!$tagihan) {
            return self::openPrefix();
        }

        return self::prefixFromContext(
            $tagihan->VA ?? $tagihan->va ?? null,
            $tagihan->isINSTALLMENT ?? null
        );
    }

    public static function formatNoVa(mixed $nis, mixed $va = null, mixed $isInstallment = null, ?string $billName = null): string
    {
        $nis = trim((string) $nis);
        if ($nis === '' || $nis === '-') {
            return '';
        }

        return \App\Models\scctcust::formatVA(
            self::prefixFromContext($va, $isInstallment, $billName),
            $nis
        );
    }

    public static function formatNoVaFromBill(mixed $nis, object $bill): string
    {
        $source = trim((string) $nis);
        if ($source === '' || $source === '-') {
            $source = trim((string) ($bill->NUM2ND ?? $bill->NOCUST ?? $bill->nocust ?? ''));
        }

        return self::formatNoVa(
            $source,
            $bill->va ?? $bill->VA ?? null,
            $bill->isINSTALLABLE ?? null,
            $bill->BILLNM ?? $bill->tagihan ?? null
        );
    }

    public static function formatNoVaFromBills(mixed $nis, mixed $bills): string
    {
        $formatted = collect($bills ?? [])
            ->map(function ($bill) use ($nis) {
                $bill = is_array($bill) ? (object) $bill : $bill;

                return is_object($bill) ? self::formatNoVaFromBill($nis, $bill) : '';
            })
            ->filter()
            ->unique()
            ->values();

        return $formatted->implode(' / ');
    }

    public static function formatNoVaBoth(mixed $nis): string
    {
        $open = self::formatNoVa($nis, self::OPEN);
        if (!self::closeEnabled()) {
            return $open;
        }

        $close = self::formatNoVa($nis, self::CLOSE);
        if ($open !== '' && $close !== '' && $open !== $close) {
            return $open . ' / ' . $close;
        }

        return $open !== '' ? $open : $close;
    }
}
