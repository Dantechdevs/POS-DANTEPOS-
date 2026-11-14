<?php

namespace App\Http\Controllers;

use App\Models\DanteBankAccount;
use App\Services\PaymentChannelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DantePaymentChannelsController extends Controller
{
    private function businessId(): int
    {
        if (! auth()->user()->can('dantepos.settings')) {
            abort(403, 'Unauthorized action.');
        }

        return (int) request()->session()->get('user.business_id');
    }

    private function clean($value, int $max): ?string
    {
        $value = mb_substr(trim(strip_tags((string) $value)), 0, $max);

        return $value === '' ? null : $value;
    }

    public function saveChannel(Request $request, string $channel)
    {
        $bid = $this->businessId();
        if (! isset(PaymentChannelService::LABELS[$channel])) {
            abort(404);
        }

        $label = PaymentChannelService::LABELS[$channel];
        $enabled = $request->boolean('is_enabled');
        $mobile = in_array($channel, ['airtel_money', 'equitel'], true);

        $rules = [
            'business_name' => ['nullable', 'string', 'max:80'],
            'account_number' => ['nullable', 'string', 'max:40'],
            'account_reference' => ['nullable', 'string', 'max:60'],
            'instructions' => ['nullable', 'string', 'max:500'],
        ];

        if ($mobile) {
            $request->merge(['account_number' => preg_replace('/[\s\-]+/', '', (string) $request->input('account_number'))]);
            $rules['account_number'][] = 'regex:/^(?:\+?254|0)[17]\d{8}$/';
        } elseif ($channel === 'pesalink') {
            $rules['account_number'][] = 'regex:/^[A-Za-z0-9\-\/ ]{5,30}$/';
        }

        if ($enabled) {
            if ($channel === 'card') {
                $rules['instructions'] = ['required', 'string', 'max:500'];
            } else {
                $rules['account_number'][0] = 'required';
            }
            if ($channel === 'pesalink') {
                $rules['account_reference'] = ['required', 'string', 'max:60'];
            }
        }

        $request->validate($rules, [
            'account_number.required' => "Enter the number before switching {$label} on.",
            'account_number.regex' => $mobile
                ? 'Enter a valid Kenyan mobile number, for example 0733123456.'
                : 'Use only letters, numbers and dashes in the account number (5 to 30 characters).',
            'instructions.required' => "Add payment instructions before switching {$label} on.",
            'account_reference.required' => 'Enter the bank name before switching Pesalink on.',
        ]);

        $data = [
            'is_enabled' => $enabled ? 1 : 0,
            'business_name' => $this->clean($request->input('business_name'), 80),
            'account_number' => $this->clean($request->input('account_number'), 40),
            'account_reference' => $this->clean($request->input('account_reference'), 60),
            'instructions' => $this->clean($request->input('instructions'), 500),
            'updated_at' => now(),
        ];

        $q = DB::table('dantepos_payment_channels')->where('business_id', $bid)->where('channel', $channel);
        if ($q->exists()) {
            $q->update($data);
        } else {
            DB::table('dantepos_payment_channels')->insert($data + [
                'business_id' => $bid, 'channel' => $channel, 'created_at' => now(),
            ]);
        }

        return back()->with('dantepos_msg', "{$label} saved.");
    }

    private function bankPayload(Request $request, int $bid, ?int $ignoreId = null): array
    {
        $request->validate([
            'bank_name' => ['required', Rule::in(array_merge(PaymentChannelService::BANKS, ['Other']))],
            'other_bank_name' => ['nullable', 'string', 'max:100', 'required_if:bank_name,Other'],
            'branch_name' => ['nullable', 'string', 'max:100'],
            'account_name' => ['required', 'string', 'max:120'],
            'account_number' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-\/ ]+$/'],
            'branch_code' => ['nullable', 'string', 'max:20'],
            'swift_code' => ['nullable', 'string', 'regex:/^[A-Za-z0-9]{8,11}$/'],
            'currency' => ['required', Rule::in(['KES', 'USD', 'EUR', 'GBP'])],
            'instructions' => ['nullable', 'string', 'max:500'],
        ], [
            'account_number.regex' => 'Use only letters, numbers and dashes in the account number.',
            'swift_code.regex' => 'A SWIFT code has 8 to 11 letters and numbers.',
            'other_bank_name.required_if' => 'Enter the bank name.',
        ]);

        $bank = $request->input('bank_name') === 'Other'
            ? $this->clean($request->input('other_bank_name'), 100)
            : $request->input('bank_name');
        $number = $this->clean($request->input('account_number'), 40);

        $duplicate = DanteBankAccount::where('business_id', $bid)
            ->where('bank_name', $bank)
            ->where('account_number', $number)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['account_number' => 'This bank account is already added.']);
        }

        $swift = $this->clean($request->input('swift_code'), 11);

        return [
            'bank_name' => $bank,
            'branch_name' => $this->clean($request->input('branch_name'), 100),
            'account_name' => $this->clean($request->input('account_name'), 120),
            'account_number' => $number,
            'branch_code' => $this->clean($request->input('branch_code'), 20),
            'swift_code' => $swift ? strtoupper($swift) : null,
            'currency' => $request->input('currency'),
            'instructions' => $this->clean($request->input('instructions'), 500),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    public function storeBank(Request $request)
    {
        $bid = $this->businessId();
        DanteBankAccount::create($this->bankPayload($request, $bid) + ['business_id' => $bid]);

        return back()->with('dantepos_msg', 'Bank account added.');
    }

    public function updateBank(Request $request, $id)
    {
        $bid = $this->businessId();
        $bank = DanteBankAccount::where('business_id', $bid)->findOrFail($id);
        $bank->update($this->bankPayload($request, $bid, (int) $bank->id));

        return back()->with('dantepos_msg', 'Bank account updated.');
    }

    public function toggleBank($id)
    {
        $bid = $this->businessId();
        $bank = DanteBankAccount::where('business_id', $bid)->findOrFail($id);
        $bank->update(['is_active' => ! $bank->is_active]);

        return back()->with('dantepos_msg', $bank->is_active ? 'Bank account activated.' : 'Bank account deactivated.');
    }

    public function deleteBank($id)
    {
        $bid = $this->businessId();
        DanteBankAccount::where('business_id', $bid)->findOrFail($id)->delete();

        return back()->with('dantepos_msg', 'Bank account deleted.');
    }
}
