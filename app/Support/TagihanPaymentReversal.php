<?php

namespace App\Support;

use App\Models\scctbill;
use App\Models\sccttran;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TagihanPaymentReversal
{
    private const CASH_FIDBANK = '1140000';
    private const SALDO_FIDBANK = '1140002';

    private const TELLER_FIDBANKS = ['1140000', '1140001', '1140003', '1140004', '1140005', '1200001', '1200002'];

    /** Batalkan pembayaran terakhir: scctbill + sccttran (cash/saldo PHP tidak mengandalkan procedure). */
    public function reverseLastPayment(scctbill $tagihan, Request $request): void
    {
        if (!$this->hasBillPayments($tagihan)) {
            return;
        }

        $last = $this->lastPaymentRow($tagihan);

        if ($this->isManualCashOrSaldo($tagihan, $last)) {
            $this->reverseManualInPhp($tagihan, $last);

            return;
        }

        $restore = $this->resolveSaldoRestore($tagihan);

        $this->clearLastPaymentKredit($tagihan);

        $this->callCancelPayment($tagihan, $restore, $request);

        $tagihan->refresh();

        $this->restoreMultiVaSaldo($tagihan, $restore);
    }

    /** Hapus tagihan: batalkan semua pembayaran dari yang terakhir. */
    public function reverseAllPayments(scctbill $tagihan, Request $request): void
    {
        $guard = 0;

        while ($this->hasBillPayments($tagihan) && $guard < 50) {
            $this->reverseLastPayment($tagihan, $request);
            $tagihan->refresh();
            $guard++;
        }
    }

    public function deleteUnpaidTagihan(scctbill $tagihan, Request $request): void
    {
        if (!$this->canDeleteUnpaidTagihan($tagihan)) {
            throw new RuntimeException(
                'Tagihan tidak dapat dihapus karena sudah ada pembayaran cicilan. Gunakan reversal di menu Data Tagihan.'
            );
        }

        $tagihan->update([
            'FSTSBolehBayar' => 0,
        ]);
    }

    public function canDeleteUnpaidTagihan(scctbill $tagihan): bool
    {
        if ((int) ($tagihan->INSTALLMENT ?? 0) > 0) {
            return false;
        }

        return !$this->hasBillPayments($tagihan);
    }

    public function applyWithoutPaymentScope($query, string $billAlias = 'scctbill')
    {
        return $query
            ->where(function ($q) use ($billAlias) {
                $q->whereNull("{$billAlias}.BILLPAID")
                    ->orWhere("{$billAlias}.BILLPAID", '<=', 0);
            })
            ->where(function ($q) use ($billAlias) {
                $q->whereNull("{$billAlias}.INSTALLMENT")
                    ->orWhere("{$billAlias}.INSTALLMENT", '<=', 0);
            })
            ->whereNotExists(function ($sub) use ($billAlias) {
                $sub->select(DB::raw(1))
                    ->from('sccttran')
                    ->whereColumn('sccttran.BILLID', "{$billAlias}.AA")
                    ->whereColumn('sccttran.CUSTID', "{$billAlias}.CUSTID")
                    ->where('sccttran.DEBET', '>', 0)
                    ->where(function ($q) {
                        $q->whereRaw('UPPER(TRIM(COALESCE(sccttran.METODE, ""))) IN (?, ?)', ['FROM TELLER', 'CASH'])
                            ->orWhere('sccttran.FIDBANK', self::SALDO_FIDBANK)
                            ->orWhereRaw("TRIM(COALESCE(CAST(sccttran.FIDBANK AS CHAR), '')) = ?", [self::CASH_FIDBANK]);
                    });
            });
    }

    public function hasBillPayments(scctbill $tagihan): bool
    {
        if ((int) ($tagihan->BILLPAID ?? 0) > 0) {
            return true;
        }

        return $this->paymentTransactionQuery($tagihan)->exists();
    }

    private function paymentTransactionQuery(scctbill $tagihan)
    {
        return sccttran::query()
            ->where('BILLID', $tagihan->AA)
            ->where('CUSTID', $tagihan->CUSTID)
            ->where('DEBET', '>', 0)
            ->where(function ($q) {
                $q->whereRaw('UPPER(TRIM(COALESCE(METODE, ""))) IN (?, ?)', ['FROM TELLER', 'CASH'])
                    ->orWhere('FIDBANK', self::SALDO_FIDBANK)
                    ->orWhereRaw("TRIM(COALESCE(CAST(FIDBANK AS CHAR), '')) = ?", [self::CASH_FIDBANK]);
            })
            ->where(function ($q) {
                $q->whereNull('isreversal')
                    ->orWhere('isreversal', 0)
                    ->orWhere('isreversal', '0');
            });
    }

    private function lastPaymentRow(scctbill $tagihan): ?sccttran
    {
        return $this->paymentTransactionQuery($tagihan)
            ->orderByDesc('INSTALLMENT')
            ->orderByDesc('urut')
            ->first();
    }

    /**
     * Saldo VA hanya kembali jika pembayaran terakhir memakai VA 93/94 (bukan cash).
     *
     * @return array{amount:int,reffbank:string,installment:int}|null
     */
    private function resolveSaldoRestore(scctbill $tagihan): ?array
    {
        $last = $this->lastPaymentRow($tagihan);
        if (!$last) {
            return null;
        }

        $fidBank = $this->normalizeFidBank($tagihan->FIDBANK ?? $last->FIDBANK ?? '');
        if ($this->isCashFidBank($fidBank) || $this->isCashMetode($last)) {
            return null;
        }

        $amount = (int) ($last->DEBET ?? 0);
        $reffBank = trim((string) ($last->REFFBANK ?? ''));
        if ($reffBank === '') {
            $reffBank = MultiVa::paymentReffBank(MultiVa::OPEN);
        }

        if ($amount <= 0) {
            return null;
        }

        return [
            'amount' => $amount,
            'reffbank' => $reffBank,
            'installment' => (int) ($last->INSTALLMENT ?? 0),
        ];
    }

    private function clearLastPaymentKredit(scctbill $tagihan): void
    {
        $lastInstallment = (int) $this->paymentTransactionQuery($tagihan)->max('INSTALLMENT');

        if ($lastInstallment <= 0) {
            return;
        }

        sccttran::query()
            ->where('BILLID', $tagihan->AA)
            ->where('CUSTID', $tagihan->CUSTID)
            ->where('INSTALLMENT', $lastInstallment)
            ->where(function ($q) {
                $q->whereRaw('UPPER(TRIM(COALESCE(METODE, ""))) IN (?, ?)', ['FROM TELLER', 'CASH'])
                    ->orWhere('FIDBANK', self::SALDO_FIDBANK)
                    ->orWhereRaw("TRIM(COALESCE(CAST(FIDBANK AS CHAR), '')) = ?", [self::CASH_FIDBANK]);
            })
            ->where('KREDIT', '>', 0)
            ->update(['KREDIT' => 0]);
    }

    private function callCancelPayment(scctbill $tagihan, ?array $restore, Request $request): void
    {
        $custId = (string) $tagihan->CUSTID;
        $aa = (string) $tagihan->AA;
        $billCd = (string) ($tagihan->BILLCD ?? '');
        $userId = $this->resolveCyberKeyUserId();
        $hostname = Str::limit((string) ($request->ip() ?? ''), 250, '');
        $reffBank = $restore['reffbank'] ?? MultiVa::OPEN;

        $candidates = array_values(array_unique(array_filter([
            MultiVa::cancelProcedure($reffBank),
            MultiVa::cancelProcedureUnsuffixed(),
            MultiVa::cancelProcedureLegacy($reffBank),
            'CancelPaymentSaldo',
        ])));

        foreach ($candidates as $procedureName) {
            try {
                $this->callNamedCancelProcedure($procedureName, $custId, $aa, $billCd, $userId, $hostname);

                return;
            } catch (Throwable $e) {
                if (!$this->isMissingRoutine($e, $procedureName)) {
                    throw $e;
                }

                Log::info('tagihan-payment.cancel.fallback_legacy', [
                    'missing' => $procedureName,
                    'aa' => $aa,
                ]);
            }
        }

        $this->fallbackReverseInPhp($tagihan, $restore);
    }

    private function fallbackReverseInPhp(scctbill $tagihan, ?array $restore): void
    {
        $this->reverseManualInPhp($tagihan, $this->lastPaymentRow($tagihan), $restore);
    }

    private function isManualCashOrSaldo(scctbill $tagihan, ?sccttran $last): bool
    {
        $fidBank = $this->normalizeFidBank($tagihan->FIDBANK ?? $last->FIDBANK ?? '');

        if ($this->isCashFidBank($fidBank) || $fidBank === self::SALDO_FIDBANK) {
            return true;
        }

        return $this->isCashMetode($last);
    }

    private function isCashMetode(?sccttran $last): bool
    {
        return strtoupper(trim((string) ($last->METODE ?? ''))) === 'CASH';
    }

    private function isCashFidBank(string $fidBank): bool
    {
        return in_array($fidBank, self::TELLER_FIDBANKS, true);
    }

    private function normalizeFidBank(mixed $fidBank): string
    {
        return preg_replace('/\D/', '', (string) $fidBank);
    }

    private function reverseManualInPhp(scctbill $tagihan, ?sccttran $last, ?array $restore = null): void
    {
        $amount = (int) ($last->DEBET ?? 0);
        if ($amount <= 0) {
            $amount = (int) ($tagihan->BILLPAID ?? 0);
        }

        $billPaid = max(0, (int) ($tagihan->BILLPAID ?? 0) - $amount);
        $installment = max(0, (int) ($tagihan->INSTALLMENT ?? 0) - 1);
        $billAm = (int) ($tagihan->BILLAM ?? 0);
        $fidBank = $this->normalizeFidBank($tagihan->FIDBANK ?? $last->FIDBANK ?? '');
        $isCash = $this->isCashFidBank($fidBank) || $this->isCashMetode($last);

        $payload = [
            'BILLPAID' => $billPaid,
            'PAYMENTLEFT' => max(0, $billAm - $billPaid),
            'INSTALLMENT' => $installment,
            'PAIDST' => $billPaid >= $billAm && $billAm > 0 ? 1 : 0,
        ];

        if ($billPaid <= 0) {
            $payload['PAIDST'] = 0;
            $payload['PAIDDT'] = null;
            $payload['FIDBANK'] = null;
            $payload['NOREFF'] = null;
        }

        $tagihan->update($payload);

        if ($last) {
            if ($isCash) {
                $this->voidPaymentRow($last);
            } else {
                if ((int) ($last->KREDIT ?? 0) > 0) {
                    sccttran::query()->where('urut', $last->urut)->update(['KREDIT' => 0]);
                    $last->KREDIT = 0;
                }

                $restore ??= [
                    'amount' => $amount,
                    'reffbank' => trim((string) ($last->REFFBANK ?? '')) ?: MultiVa::paymentReffBank(MultiVa::OPEN),
                    'installment' => (int) ($last->INSTALLMENT ?? 0),
                ];
                $this->restoreMultiVaSaldo($tagihan, $restore);
                $this->markRowReversed($last);
            }
        }

        Log::info('tagihan-payment.cancel.php_manual', [
            'aa' => $tagihan->AA,
            'amount' => $amount,
            'billpaid' => $billPaid,
            'cash' => $isCash,
            'tran_urut' => $last->urut ?? null,
        ]);
    }

    private function voidPaymentRow(sccttran $last): void
    {
        $payload = [
            'DEBET' => 0,
            'KREDIT' => 0,
        ];

        try {
            sccttran::query()->where('urut', $last->urut)->update($payload + ['isreversal' => 1]);
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'isreversal') === false
                && stripos($e->getMessage(), 'Unknown column') === false) {
                throw $e;
            }

            sccttran::query()->where('urut', $last->urut)->update($payload);
        }
    }

    private function markRowReversed(sccttran $last): void
    {
        try {
            sccttran::query()->where('urut', $last->urut)->update(['isreversal' => 1]);
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'isreversal') === false
                && stripos($e->getMessage(), 'Unknown column') === false) {
                throw $e;
            }

            $metode = trim((string) ($last->METODE ?? ''));
            sccttran::query()->where('urut', $last->urut)->update([
                'METODE' => trim('REVERSED ' . $metode),
            ]);
        }
    }

    private function callNamedCancelProcedure(
        string $procedure,
        string $custId,
        string $aa,
        string $billCd,
        string $userId,
        string $hostname
    ): void {
        Log::info('tagihan-payment.cancel.call_procedure', [
            'procedure' => $procedure,
            'custid' => $custId,
            'aa' => $aa,
            'billcd' => $billCd,
            'users' => $userId,
            'hostname' => $hostname,
        ]);

        $pdo = DB::connection('DATA_MYSQL')->getPdo();
        $stmt = $pdo->prepare("CALL {$procedure}(?, ?, ?, ?, ?)");
        $stmt->execute([$custId, $aa, $billCd, $userId, $hostname]);

        do {
            $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } while ($stmt->nextRowset());
    }

    private function isMissingRoutine(Throwable $e, string $routine): bool
    {
        $message = $e->getMessage();
        $code = (int) $e->getCode();

        if ($code === 1305 || stripos($message, '1305') !== false) {
            return true;
        }

        $missing = stripos($message, 'does not exist') !== false
            || stripos($message, "doesn't exist") !== false
            || stripos($message, 'unknown procedure') !== false;

        return $missing && (
            stripos($message, $routine) !== false
            || stripos($message, 'PROCEDURE') !== false
        );
    }

    /** View saldo 93/94 hanya menjumlah KREDIT-DEBET yang REFFBANK-nya 93/94. */
    private function restoreMultiVaSaldo(scctbill $tagihan, ?array $restore): void
    {
        if ($restore === null) {
            return;
        }

        $reffBank = $restore['reffbank'];
        $amount = $restore['amount'];

        if ($this->hasMatchingVaKredit($tagihan, $reffBank, $amount)) {
            return;
        }

        $patched = $this->patchRecentReversalReffBank($tagihan, $reffBank, $amount);
        if ($patched) {
            Log::info('tagihan-payment.cancel.patched_reffbank', [
                'aa' => $tagihan->AA,
                'reffbank' => $reffBank,
                'amount' => $amount,
            ]);

            return;
        }

        $this->insertSaldoRestoreRow($tagihan, $restore);

        Log::info('tagihan-payment.cancel.inserted_va_kredit', [
            'aa' => $tagihan->AA,
            'reffbank' => $reffBank,
            'amount' => $amount,
        ]);
    }

    private function hasMatchingVaKredit(scctbill $tagihan, string $reffBank, int $amount): bool
    {
        return sccttran::query()
            ->where('CUSTID', $tagihan->CUSTID)
            ->where('BILLID', $tagihan->AA)
            ->where('REFFBANK', $reffBank)
            ->where('KREDIT', '>=', $amount)
            ->where(function ($q) {
                $q->whereRaw('UPPER(TRIM(COALESCE(METODE, ""))) IN (?, ?)', ['REVERSAL', 'JURNAL SALDO'])
                    ->orWhere('isreversal', 1);
            })
            ->where('TRXDATE', '>=', now()->subMinutes(10))
            ->exists();
    }

    private function patchRecentReversalReffBank(scctbill $tagihan, string $reffBank, int $amount): bool
    {
        $row = sccttran::query()
            ->where('CUSTID', $tagihan->CUSTID)
            ->where('BILLID', $tagihan->AA)
            ->where('KREDIT', '>=', $amount)
            ->where(function ($q) {
                $q->whereRaw('UPPER(TRIM(COALESCE(METODE, ""))) IN (?, ?)', ['REVERSAL', 'JURNAL SALDO'])
                    ->orWhere('isreversal', 1);
            })
            ->where(function ($q) {
                $q->whereNull('REFFBANK')
                    ->orWhereRaw("TRIM(COALESCE(CAST(REFFBANK AS CHAR), '')) = ''")
                    ->orWhere('REFFBANK', '101');
            })
            ->orderByDesc('urut')
            ->first();

        if (!$row) {
            return false;
        }

        $row->REFFBANK = $reffBank;
        $row->save();

        return true;
    }

    private function insertSaldoRestoreRow(scctbill $tagihan, array $restore): void
    {
        $nextUrut = ((int) sccttran::query()->max('urut')) + 1;

        $payload = [
            'urut' => $nextUrut,
            'CUSTID' => $tagihan->CUSTID,
            'METODE' => 'JURNAL SALDO',
            'TRXDATE' => now(),
            'NOREFF' => 'REVERSAL',
            'FIDBANK' => self::SALDO_FIDBANK,
            'DEBET' => 0,
            'KREDIT' => $restore['amount'],
            'REFFBANK' => $restore['reffbank'],
            'BILLID' => $tagihan->AA,
            'BILLTARGET' => $tagihan->BILLNM,
            'INSTALLMENT' => $restore['installment'],
            'TRANSNO' => $tagihan->TRANSNO ?? 'REVERSAL',
            'isreversal' => 1,
        ];

        try {
            sccttran::create($payload);
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'isreversal') === false
                && stripos($e->getMessage(), 'Unknown column') === false) {
                throw $e;
            }

            unset($payload['isreversal']);
            sccttran::create($payload);
        }
    }

    private function resolveCyberKeyUserId(): string
    {
        $user = Auth::user();

        if ($user === null) {
            return '';
        }

        return (string) ($user->urut ?? Auth::id() ?? '');
    }
}
