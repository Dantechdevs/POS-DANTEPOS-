<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Reads an M-Pesa statement CSV (downloaded from the Safaricom M-Pesa Business portal) and
 * verifies the manual payments whose receipt codes appear on it, with a matching amount.
 * Column names are matched loosely because Paybill and Till statements differ slightly.
 */
class MpesaStatementImporter
{
    /** @return array{matched:int,mismatch:int,not_found:int,rows:int,error:?string} */
    public static function import(string $path, int $businessId, int $userId): array
    {
        $result = ['matched' => 0, 'mismatch' => 0, 'not_found' => 0, 'rows' => 0, 'error' => null];


        $h = @fopen($path, 'r');
        if (! $h) {
            $result['error'] = 'Could not read the uploaded file.';

            return $result;
        }

        $first = fgets($h);
        rewind($h);
        $delim = substr_count((string) $first, ';') > substr_count((string) $first, ',') ? ';' : ',';

        $receiptCol = $amountCol = $statusCol = null;
        $lookup = [];

        while (($row = fgetcsv($h, 0, $delim)) !== false) {
            if ($receiptCol === null) {
                // Find the header row (statements often have a few title lines above it).
                foreach ($row as $idx => $cell) {
                    $c = strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $cell)));
                    if ($receiptCol === null && preg_match('/^receipt( no\.?| number)?$/', $c)) {
                        $receiptCol = $idx;
                    }
                    if ($amountCol === null && preg_match('/^(paid in|credit|amount)$/', $c)) {
                        $amountCol = $idx;
                    }
                    if ($statusCol === null && preg_match('/status/', $c)) {
                        $statusCol = $idx;
                    }
                }
                if ($receiptCol === null || $amountCol === null) {
                    $receiptCol = $amountCol = $statusCol = null;

                }

                continue;
            }

            $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($row[$receiptCol] ?? '')));
            if (strlen($code) !== 10) {
                continue;
            }

            $status = $statusCol !== null ? strtolower(trim((string) ($row[$statusCol] ?? ''))) : 'completed';
            if ($status !== '' && strpos($status, 'complete') === false) {
                continue;
            }

            $amount = (float) str_replace([',', ' '], '', (string) ($row[$amountCol] ?? 0));
            if ($amount > 0) {
                $lookup[$code] = ($lookup[$code] ?? 0) + $amount;
            }
            $result['rows']++;
        }
        fclose($h);

        if ($receiptCol === null && $result['rows'] === 0) {
            $result['error'] = 'Could not find the "Receipt No" and "Paid In" columns. Download the statement as CSV from the M-Pesa Business portal.';

            return $result;
        }

        $pending = DB::table('mpesa_transactions')
            ->where('business_id', $businessId)->where('source', 'manual')->where('status', 'manual')->get();


        foreach ($pending as $m) {
            $code = (string) $m->mpesa_receipt_number;

            if (! isset($lookup[$code])) {
                $result['not_found']++;

                continue;
            }

            if ($lookup[$code] + 0.99 >= (float) $m->amount) {
                self::mark($m->id, 'success', $userId, 'Verified from statement (KSh '.number_format($lookup[$code], 2).')');
                $result['matched']++;
            } else {
                self::mark($m->id, 'review', $userId, 'Amount mismatch: statement KSh '.number_format($lookup[$code], 2).', recorded KSh '.number_format((float) $m->amount, 2));
                $result['mismatch']++;
            }
        }

        return $result;
    }

    public static function mark(int $id, string $status, int $userId, string $note): void
    {
        DB::table('mpesa_transactions')->where('id', $id)->update([
            'status' => $status,
            'verified_at' => now(),
            'verified_by' => $userId,
            'verify_note' => $note,
            'result_desc' => $note,
            'updated_at' => now(),
        ]);
    }

}
