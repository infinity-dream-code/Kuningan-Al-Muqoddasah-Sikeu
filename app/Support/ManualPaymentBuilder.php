<?php

namespace App\Support;

use App\Models\scctbill;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ManualPaymentBuilder
{
    private const SALDO_FIDBANK = '1140002';

    public function payBill(
        scctbill $tagihan,
        int $nominal,
        string $fidBank,
        string $paidAt,
        ?string $transno,
        Request $request
    ): void {
        $custId = (string) $tagihan->CUSTID;
        $aa = (string) $tagihan->AA;
        $billCd = (string) ($tagihan->BILLCD ?? '');
        $userId = $this->resolveCyberKeyUserId();

        try {
            if ($fidBank === self::SALDO_FIDBANK) {
                $va = MultiVa::resolveFromBill($tagihan) ?? MultiVa::OPEN;
                $this->callBuilderPaymentBill($aa, $nominal, $va);

                return;
            }

            $this->callBuilderPaymentCash(
                $custId,
                $fidBank,
                $userId,
                $this->formatPaymentDate($paidAt),
                $billCd,
                $aa,
                $nominal
            );
        } catch (\Throwable $e) {
            // Insufficient_Balance dari function (REFFBANK 1/2 kosong) → coba PHP pakai saldo view.
            $saldoInsufficient = $e instanceof RuntimeException
                && str_contains($e->getMessage(), 'tidak mencukupi');

            if ($e instanceof RuntimeException && !$saldoInsufficient) {
                throw $e;
            }

            if (!$saldoInsufficient && !$this->shouldPhpFallback($e)) {
                throw $e;
            }

            Log::warning('manual-payment.builder.php_fallback', [
                'aa' => $aa,
                'fidbank' => $fidBank,
                'reason' => $e->getMessage(),
            ]);

            if ($fidBank === self::SALDO_FIDBANK) {
                $this->payBillViaSaldoPhp($tagihan, $nominal, $paidAt, $transno);
            } else {
                $this->payBillViaCashPhp($tagihan, $nominal, $fidBank, $paidAt, $transno);
            }
        }
    }

    /**
     * BuilderPaymentCash_MultiVAPerTagihan membaca kolom scctbill.VA.
     * Client open-only tanpa kolom VA → pakai BuilderPaymentCash (pakai CODE01).
     */
    private function callBuilderPaymentCash(
        string $custId,
        string $fidBank,
        string $userId,
        string $paymentDate,
        string $billCd,
        string $aa,
        int $nominal
    ): void {
        $params = [
            $custId,
            $fidBank,
            $userId,
            $paymentDate,
            $billCd,
            $aa,
            $nominal,
        ];
        $context = [
            'custid' => $custId,
            'fidbank' => $fidBank,
            'users' => $userId,
            'date' => $paymentDate,
            'billcd' => $billCd,
            'aa' => $aa,
            'nominal' => $nominal,
        ];

        $candidates = [];
        if (MultiVa::closeEnabled() && MultiVa::billVaColumn() !== null) {
            $candidates[] = MultiVa::cashPaymentFunction();
        }
        $candidates[] = 'BuilderPaymentCash';

        $this->invokeWithFallbacks($candidates, $params, $context);
    }

    /**
     * Manual Saldo: DB punya BuilderPaymentBill_MultiVAPerTagihan1 / 2
     * (bukan 93/94), lalu fallback BuilderPaymentBill.
     */
    private function callBuilderPaymentBill(string $aa, int $nominal, string $va): void
    {
        $candidates = [
            MultiVa::paymentFunction($va),       // ...PerTagihan1 atau ...PerTagihan2
            MultiVa::paymentFunctionLegacy($va),
            'BuilderPaymentBill',
        ];

        $this->invokeWithFallbacks(
            $candidates,
            [$aa, $nominal],
            ['aa' => $aa, 'nominal' => $nominal, 'va' => $va]
        );
    }

    /** Coba daftar function berurutan sampai ada yang OK. */
    private function invokeWithFallbacks(array $candidates, array $params, array $context): void
    {
        $candidates = array_values(array_unique(array_filter($candidates)));
        $lastError = null;

        foreach ($candidates as $index => $functionName) {
            try {
                Log::info('manual-payment.builder.call', array_merge([
                    'function' => $functionName,
                    'attempt' => $index + 1,
                ], $context));

                $result = $this->invokeStoredFunction($functionName, $params);
                $this->assertBuilderResult($functionName, $result, $context);

                return;
            } catch (RuntimeException $e) {
                // Hasil bisnis (saldo kurang, dll) — jangan coba function lain.
                throw $e;
            } catch (\Throwable $e) {
                $lastError = $e;
                Log::warning('manual-payment.builder.candidate_failed', [
                    'function' => $functionName,
                    'reason' => $e->getMessage(),
                ]);
            }
        }

        throw $lastError ?? new RuntimeException('Pembayaran gagal diproses oleh sistem.');
    }

    /** Fallback Manual Cash: update scctbill + insert sccttran (net saldo 0). */
    private function payBillViaCashPhp(
        scctbill $tagihan,
        int $nominal,
        string $fidBank,
        string $paidAt,
        ?string $transno
    ): void {
        $meta = $this->applyBillPayment($tagihan, $nominal, $fidBank, $paidAt, $transno, 'TELLER');

        DB::connection('DATA_MYSQL')->table('sccttran')->insert([
            'CUSTID' => (int) $tagihan->CUSTID,
            'METODE' => 'CASH',
            'TRXDATE' => now()->format('Y-m-d H:i:s'),
            'NOREFF' => 'MNL',
            'FIDBANK' => $fidBank,
            'KDCHANNEL' => '11',
            'DEBET' => $nominal,
            'KREDIT' => $nominal,
            'REFFBANK' => MultiVa::OPEN,
            'TRANSNO' => $transno ?: (string) (Auth::user()->users ?? 'TELLER'),
            'BILLID' => (string) $tagihan->AA,
            'BILLTARGET' => (string) ($tagihan->BILLNM ?? ''),
            'INSTALLMENT' => (string) $meta['installment'],
        ]);

        Log::info('manual-payment.builder.php_cash_ok', [
            'aa' => $tagihan->AA,
            'nominal' => $nominal,
            'fidbank' => $fidBank,
        ]);
    }

    /**
     * Fallback Manual Saldo: update scctbill + DEBET sccttran (mengurangi saldo).
     * Saldo = SUM(KREDIT - DEBET).
     */
    private function payBillViaSaldoPhp(
        scctbill $tagihan,
        int $nominal,
        string $paidAt,
        ?string $transno
    ): void {
        $saldo = MultiVa::custSaldo($tagihan->CUSTID, MultiVa::OPEN);
        if ($nominal > $saldo) {
            throw new RuntimeException(
                'Saldo VA siswa tidak mencukupi. Saldo: Rp. '
                . number_format($saldo, 0, ',', '.')
                . ', kebutuhan: Rp. ' . number_format($nominal, 0, ',', '.')
            );
        }

        $meta = $this->applyBillPayment($tagihan, $nominal, self::SALDO_FIDBANK, $paidAt, $transno, 'MNL');

        DB::connection('DATA_MYSQL')->table('sccttran')->insert([
            'CUSTID' => (int) $tagihan->CUSTID,
            'METODE' => 'FROM TELLER',
            'TRXDATE' => now()->format('Y-m-d H:i:s'),
            'NOREFF' => 'TELLER',
            'FIDBANK' => self::SALDO_FIDBANK,
            'KDCHANNEL' => '',
            'DEBET' => $nominal,
            'KREDIT' => 0,
            // Samakan dengan function PerTagihan1 (REFFBANK='1')
            'REFFBANK' => MultiVa::paymentReffBank(MultiVa::OPEN),
            'TRANSNO' => $transno ?: (string) (Auth::user()->users ?? 'TELLER'),
            'BILLID' => (string) $tagihan->AA,
            'BILLTARGET' => (string) ($tagihan->BILLNM ?? ''),
            'INSTALLMENT' => (string) $meta['installment'],
        ]);

        Log::info('manual-payment.builder.php_saldo_ok', [
            'aa' => $tagihan->AA,
            'nominal' => $nominal,
            'saldo_sebelum' => $saldo,
        ]);
    }

    /** @return array{installment: int, fully_paid: bool} */
    private function applyBillPayment(
        scctbill $tagihan,
        int $nominal,
        string $fidBank,
        string $paidAt,
        ?string $transno,
        string $noreff
    ): array {
        $billAm = (int) $tagihan->BILLAM;
        $billPaid = (int) ($tagihan->BILLPAID ?? 0);
        $newBillPaid = $billPaid + $nominal;
        $newPaymentLeft = max(0, $billAm - $newBillPaid);
        $existingInstal = (int) DB::connection('DATA_MYSQL')
            ->table('sccttran')
            ->where('BILLID', $tagihan->AA)
            ->max('INSTALLMENT');
        $newInstallment = $existingInstal + 1;
        $isFullyPaid = $newPaymentLeft <= 0;
        $paidDt = Carbon::parse($paidAt)->format('Y-m-d H:i:s');

        DB::connection('DATA_MYSQL')->table('scctbill')
            ->where('AA', $tagihan->AA)
            ->where('CUSTID', $tagihan->CUSTID)
            ->update([
                'BILLPAID' => $newBillPaid,
                'PAYMENTLEFT' => $newPaymentLeft,
                'PAIDDT' => $isFullyPaid ? $paidDt : null,
                'PAIDDT_ACTUAL' => now()->format('Y-m-d H:i:s'),
                'FIDBANK' => $isFullyPaid ? $fidBank : null,
                'NOREFF' => $isFullyPaid ? $noreff : null,
                'PAIDST' => $isFullyPaid ? 1 : 0,
                'INSTALLMENT' => $newInstallment,
                'TRANSNO' => $transno,
            ]);

        return ['installment' => $newInstallment, 'fully_paid' => $isFullyPaid];
    }

    /** MySQL FUNCTION (fx) — pakai SELECT, bukan CALL (procedure). */
    private function invokeStoredFunction(string $functionName, array $params): string
    {
        $placeholders = implode(', ', array_fill(0, count($params), '?'));

        $rows = DB::connection('DATA_MYSQL')->select(
            "SELECT {$functionName}({$placeholders}) AS builder_result",
            $params
        );

        return trim((string) (($rows[0] ?? null)->builder_result ?? ''));
    }

    private function assertBuilderResult(string $functionName, string $result, array $context = []): void
    {
        if ($result === 'OK') {
            Log::info('manual-payment.builder.ok', array_merge(['function' => $functionName], $context));

            return;
        }

        Log::warning('manual-payment.builder.failed', array_merge([
            'function' => $functionName,
            'result' => $result,
        ], $context));

        throw new RuntimeException($this->mapBuilderResultMessage($result));
    }

    private function mapBuilderResultMessage(string $result): string
    {
        return match ($result) {
            'NOMINAL_SALAH_TAGIHAN_TIDAK_BOLEH_DICICIL' => 'Nominal pembayaran salah. Tagihan ini tidak boleh dicicil — harus dibayar lunas.',
            'MELEBIHI_TAGIHAN' => 'Nominal pembayaran melebihi sisa tagihan.',
            'Insufficient_Balance' => 'Saldo VA siswa tidak mencukupi.',
            'NOT_FOUND' => 'Tagihan tidak ditemukan atau sudah tidak bisa dibayar.',
            '' => 'Pembayaran gagal diproses oleh sistem (tidak ada respons dari database).',
            default => "Pembayaran gagal diproses oleh sistem ({$result}).",
        };
    }

    private function formatPaymentDate(string $paidAt): string
    {
        return Carbon::parse($paidAt)->format('Ymd');
    }

    private function resolveCyberKeyUserId(): string
    {
        $user = Auth::user();

        if ($user === null) {
            return '';
        }

        return (string) ($user->urut ?? Auth::id() ?? '');
    }

    private function isMissingAnyRoutine(\Throwable $e): bool
    {
        $message = $e->getMessage();

        return (
            stripos($message, 'does not exist') !== false
            || stripos($message, "doesn't exist") !== false
            || stripos($message, 'unknown procedure') !== false
            || stripos($message, 'unknown function') !== false
            || stripos($message, '1305') !== false
        ) && (
            stripos($message, 'BuilderPayment') !== false
            || stripos($message, 'FUNCTION') !== false
            || stripos($message, 'PROCEDURE') !== false
        );
    }

    private function shouldPhpFallback(\Throwable $e): bool
    {
        if ($this->isMissingAnyRoutine($e)) {
            return true;
        }

        $message = strtolower($e->getMessage());

        return str_contains($message, 'unknown column')
            || str_contains($message, "doesn't exist")
            || str_contains($message, 'does not exist')
            || str_contains($message, 'sm_topup')
            || str_contains($message, 'definer')
            || str_contains($message, 'access denied')
            || str_contains($message, '1449');
    }
}
