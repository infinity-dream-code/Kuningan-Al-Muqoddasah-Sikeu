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
                $va = MultiVa::resolveFromBill($tagihan);
                if ($va === null) {
                    throw new RuntimeException(
                        'Tagihan belum memiliki VA Open/Close. Set tipe VA di Master Tagihan.'
                    );
                }
                $this->callBuilderPaymentBill(
                    $aa,
                    $nominal,
                    MultiVa::paymentFunction($va),
                    MultiVa::paymentFunctionLegacy($va)
                );

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
            if ($e instanceof RuntimeException) {
                throw $e;
            }

            // Function MultiVA butuh kolom VA / tabel sm_topup → fallback PHP untuk cash.
            if ($fidBank !== self::SALDO_FIDBANK && $this->shouldPhpFallback($e)) {
                Log::warning('manual-payment.builder.php_fallback', [
                    'aa' => $aa,
                    'fidbank' => $fidBank,
                    'reason' => $e->getMessage(),
                ]);
                $this->payBillViaPhp($tagihan, $nominal, $fidBank, $paidAt, $transno);

                return;
            }

            throw $e;
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

        $useMultiVa = MultiVa::closeEnabled() && MultiVa::billVaColumn() !== null;
        $primary = $useMultiVa ? MultiVa::cashPaymentFunction() : 'BuilderPaymentCash';
        $fallback = $useMultiVa ? 'BuilderPaymentCash' : null;

        Log::info('manual-payment.builder.call', array_merge([
            'function' => $primary,
            'use_multiva' => $useMultiVa,
        ], $context));

        try {
            $result = $this->invokeStoredFunction($primary, $params);
            $this->assertBuilderResult($primary, $result, $context);
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($fallback === null) {
                throw $e;
            }

            // MultiVA gagal (kolom VA / DEFINER / dll) → coba function non-MultiVA.
            Log::warning('manual-payment.builder.fallback_legacy', [
                'failed' => $primary,
                'fallback' => $fallback,
                'aa' => $aa,
                'reason' => $e->getMessage(),
            ]);
            $result = $this->invokeStoredFunction($fallback, $params);
            $this->assertBuilderResult($fallback, $result, $context);
        }
    }

    /** BuilderPaymentBill_MultiVAPerTagihan93|94 (aa, nominal) */
    private function callBuilderPaymentBill(
        string $aa,
        int $nominal,
        string $functionName = 'BuilderPaymentBill',
        ?string $fallbackName = null
    ): void {
        Log::info('manual-payment.builder.call', [
            'function' => $functionName,
            'aa' => $aa,
            'nominal' => $nominal,
        ]);

        try {
            $result = $this->invokeStoredFunction($functionName, [$aa, $nominal]);
        } catch (\Throwable $e) {
            if ($fallbackName === null || !$this->isMissingRoutine($e, $functionName)) {
                throw $e;
            }

            Log::info('manual-payment.builder.fallback_legacy', [
                'missing' => $functionName,
                'fallback' => $fallbackName,
                'aa' => $aa,
            ]);
            $functionName = $fallbackName;
            $result = $this->invokeStoredFunction($functionName, [$aa, $nominal]);
        }

        $this->assertBuilderResult($functionName, $result, [
            'aa' => $aa,
            'nominal' => $nominal,
        ]);
    }

    /** Fallback tanpa stored function: update scctbill + insert sccttran. */
    private function payBillViaPhp(
        scctbill $tagihan,
        int $nominal,
        string $fidBank,
        string $paidAt,
        ?string $transno
    ): void {
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
            ->update([
                'BILLPAID' => $newBillPaid,
                'PAYMENTLEFT' => $newPaymentLeft,
                'PAIDDT' => $paidDt,
                'PAIDDT_ACTUAL' => now()->format('Y-m-d H:i:s'),
                'FIDBANK' => $fidBank,
                'NOREFF' => 'TELLER',
                'PAIDST' => $isFullyPaid ? 1 : 0,
                'INSTALLMENT' => $newInstallment,
                'TRANSNO' => $transno,
            ]);

        $tran = [
            'CUSTID' => (int) $tagihan->CUSTID,
            'METODE' => 'FROM TELLER',
            'TRXDATE' => now()->format('Y-m-d H:i:s'),
            'NOREFF' => 'TELLER',
            'FIDBANK' => $fidBank,
            'KDCHANNEL' => '',
            'DEBET' => $nominal,
            'KREDIT' => $nominal,
            'REFFBANK' => MultiVa::OPEN,
            'TRANSNO' => $transno ?: (string) (Auth::user()->users ?? 'TELLER'),
            'BILLID' => (string) $tagihan->AA,
            'BILLTARGET' => (string) ($tagihan->BILLNM ?? ''),
            'INSTALLMENT' => (string) $newInstallment,
        ];

        DB::connection('DATA_MYSQL')->table('sccttran')->insert($tran);

        Log::info('manual-payment.builder.php_ok', [
            'aa' => $tagihan->AA,
            'nominal' => $nominal,
            'fidbank' => $fidBank,
            'transno' => $transno,
        ]);
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

    private function isMissingRoutine(\Throwable $e, string $routine): bool
    {
        $message = $e->getMessage();

        return stripos($message, $routine) !== false
            && (
                stripos($message, 'does not exist') !== false
                || stripos($message, "doesn't exist") !== false
                || stripos($message, 'unknown procedure') !== false
                || stripos($message, 'unknown function') !== false
                || stripos($message, '1305') !== false
            );
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

    /** Error yang aman diatasi dengan bayar cash via PHP (tanpa ubah function DB). */
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
            || str_contains($message, '1449'); // definer user missing
    }
}
