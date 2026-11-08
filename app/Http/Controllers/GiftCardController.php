<?php

namespace App\Http\Controllers;

use App\Services\AfricasTalkingService;
use App\Services\GiftCardService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GiftCardController extends Controller
{
    private const PHONE_REGEX = '/^(?:254|0)?([17]\d{8})$/';

    protected $cards;

    public function __construct(GiftCardService $cards)
    {
        $this->cards = $cards;
    }

    private function requireManage(): void
    {
        if (! auth()->user()->can('giftcard.manage')) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function requireView(): void
    {
        $u = auth()->user();

        if (! $u->can('giftcard.manage') && ! $u->can('sell.create') && ! $u->can('direct_sell.access')) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function bid(Request $request): int
    {
        return (int) $request->session()->get('user.business_id');
    }

    private function ok(string $msg)
    {
        return back()->with('status', ['success' => 1, 'msg' => $msg]);
    }

    private function fail(string $msg)
    {
        return back()->with('status', ['success' => 0, 'msg' => $msg]);
    }

    private function phone(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw);

        return preg_match(self::PHONE_REGEX, $digits, $m) ? '254'.$m[1] : null;
    }

    public function index(Request $request)
    {
        $this->requireManage();
        $bid = $this->bid($request);
        $today = now()->toDateString();

        $active = function ($q) use ($today) {
            $q->where('status', 'active')->where('balance', '>', 0)
                ->where(function ($w) use ($today) {
                    $w->whereNull('expires_at')->orWhere('expires_at', '>=', $today);
                });
        };

        $tiles = [
            'outstanding' => (float) DB::table('gift_cards')->where('business_id', $bid)->where($active)->sum('balance'),
            'expired_value' => (float) DB::table('gift_cards')->where('business_id', $bid)->where('status', 'active')
                ->where('balance', '>', 0)->whereNotNull('expires_at')->where('expires_at', '<', $today)->sum('balance'),
            'redeemed_today' => max(0, -1 * (float) DB::table('gift_card_transactions')->where('business_id', $bid)
                ->whereIn('type', ['redeem', 'refund'])->whereDate('created_at', $today)->sum('amount')),
        ];

        $soldToday = DB::table('gift_cards')->where('business_id', $bid)->whereDate('created_at', $today)
            ->groupBy('issued_via')->selectRaw('issued_via, SUM(initial_amount) as total, COUNT(*) as n')->get();

        $query = DB::table('gift_cards')->where('business_id', $bid);
        $status = (string) $request->input('status');

        if ($status === 'active') {
            $query->where($active);
        } elseif ($status === 'used') {
            $query->where('status', 'active')->where('balance', '<=', 0);
        } elseif ($status === 'expired') {
            $query->where('status', 'active')->where('balance', '>', 0)->whereNotNull('expires_at')->where('expires_at', '<', $today);
        } elseif ($status === 'void') {
            $query->where('status', 'void');
        }

        $term = trim((string) $request->input('q'));

        if ($term !== '') {
            $key = $this->cards->normalise($term);

            $query->where(function ($w) use ($term, $key) {
                if ($key !== '') {
                    $w->orWhere('code_key', 'like', '%'.$key.'%');
                }
                $w->orWhere('recipient_name', 'like', '%'.$term.'%')->orWhere('recipient_phone', 'like', '%'.$term.'%');
            });
        }

        $cards = $query->orderByDesc('id')->limit(200)->get();
        $svc = $this->cards;
        $methods = GiftCardService::ISSUE_METHODS;
        $methodSlot = $this->cards->method();

        return view('giftcards.index', compact('tiles', 'soldToday', 'cards', 'svc', 'methods', 'methodSlot', 'term', 'status'));
    }

    public function store(Request $request)
    {
        $this->requireManage();

        $request->validate([
            'amount' => 'required|numeric|min:1|max:1000000',
            'quantity' => 'required|integer|min:1|max:100',
            'issued_via' => 'required|in:'.implode(',', array_keys(GiftCardService::ISSUE_METHODS)),
            'expires_at' => 'nullable|date|after:today',
            'recipient_name' => 'nullable|string|max:100',
            'recipient_phone' => 'nullable|string|max:20',
            'note' => 'nullable|string|max:255',
        ]);

        $quantity = (int) $request->input('quantity');
        $phone = $request->filled('recipient_phone') ? $this->phone($request->input('recipient_phone')) : null;

        if ($request->filled('recipient_phone') && ! $phone) {
            return back()->withErrors(['recipient_phone' => 'Enter a valid Safaricom number, for example 0712345678.'])->withInput();
        }

        if ($phone && $quantity > 1) {
            return back()->withErrors(['recipient_phone' => 'A phone number can only be used when issuing one card.'])->withInput();
        }

        $bid = $this->bid($request);
        $userId = auth()->id();

        $created = DB::transaction(function () use ($request, $quantity, $bid, $userId, $phone) {
            $out = [];

            for ($i = 0; $i < $quantity; $i++) {
                $out[] = $this->cards->issue($bid, round((float) $request->input('amount'), 2), [
                    'issued_via' => $request->input('issued_via'),
                    'expires_at' => $request->input('expires_at'),
                    'recipient_name' => $request->input('recipient_name'),
                    'recipient_phone' => $phone,
                    'note' => $request->input('note'),
                ], $userId);
            }

            return $out;
        });

        $msg = $quantity.' gift card(s) issued.';

        if ($phone && $request->boolean('send_sms')) {
            $error = $this->sendCodeSms($created[0], $bid);
            $msg .= $error ? ' The SMS could not be sent: '.$error : ' The code was sent by SMS.';
        }

        if ($quantity > 1) {
            $ids = implode(',', array_map(fn ($c) => $c->id, $created));

            return redirect('gift-cards/print?ids='.$ids)->with('status', ['success' => 1, 'msg' => $msg]);
        }

        return redirect('gift-cards/'.$created[0]->id)->with('status', ['success' => 1, 'msg' => $msg]);
    }

    public function show(Request $request, $id)
    {
        $this->requireManage();
        $bid = $this->bid($request);

        $card = DB::table('gift_cards')->where('business_id', $bid)->where('id', $id)->first();
        abort_if(! $card, 404);

        $ledger = DB::table('gift_card_transactions as g')
            ->leftJoin('transactions as t', 't.id', '=', 'g.transaction_id')
            ->leftJoin('users as u', 'u.id', '=', 'g.user_id')
            ->where('g.gift_card_id', $card->id)
            ->orderByDesc('g.id')
            ->get(['g.*', 't.invoice_no', DB::raw("CONCAT_WS(' ', u.first_name, u.last_name) as user_name")]);

        $status = $this->cards->status($card);
        $methods = GiftCardService::ISSUE_METHODS;

        return view('giftcards.show', compact('card', 'ledger', 'status', 'methods'));
    }

    public function print(Request $request)
    {
        $this->requireManage();
        $bid = $this->bid($request);

        $ids = array_slice(array_filter(array_map('intval', explode(',', (string) $request->input('ids')))), 0, 100);
        abort_if(empty($ids), 404);

        $cards = DB::table('gift_cards')->where('business_id', $bid)->whereIn('id', $ids)->orderBy('id')->get();
        abort_if($cards->isEmpty(), 404);

        $business = DB::table('business')->where('id', $bid)->value('name');
        $currency = $request->session()->get('currency.symbol', 'KSh');

        return view('giftcards.print', compact('cards', 'business', 'currency'));
    }

    public function balance(Request $request)
    {
        $this->requireView();

        return view('giftcards.balance');
    }

    public function check(Request $request)
    {
        $this->requireView();
        $key = $this->cards->normalise((string) $request->input('code'));

        if ($key === '') {
            return response()->json(['success' => false, 'message' => 'Enter the gift card code.']);
        }

        $card = $this->cards->find($this->bid($request), $key);

        if (! $card) {
            return response()->json(['success' => false, 'message' => 'No gift card with that code.']);
        }

        $status = $this->cards->status($card);

        return response()->json([
            'success' => true,
            'code' => $card->code,
            'status' => $status,
            'balance' => number_format((float) $card->balance, 2),
            'expires' => $card->expires_at ? Carbon::parse($card->expires_at)->format('d/m/Y') : null,
            'usable' => $status === 'active',
        ]);
    }

    public function adjust(Request $request, $id)
    {
        $this->requireManage();

        $request->validate([
            'amount' => 'required|numeric|not_in:0|min:-1000000|max:1000000',
            'reason' => 'required|string|max:200',
        ]);

        try {
            $balance = $this->cards->adjust($this->bid($request), (int) $id, round((float) $request->input('amount'), 2), 'Manual: '.$request->input('reason'), auth()->id());
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }

        return $this->ok('Balance updated. New balance: KSh '.number_format($balance, 2));
    }

    public function void(Request $request, $id)
    {
        $this->requireManage();
        $request->validate(['reason' => 'required|string|max:200']);

        try {
            $this->cards->void($this->bid($request), (int) $id, 'Cancelled: '.$request->input('reason'), auth()->id());
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }

        return $this->ok('Gift card cancelled and its balance set to zero.');
    }

    public function extend(Request $request, $id)
    {
        $this->requireManage();
        $request->validate(['expires_at' => 'nullable|date']);

        $updated = DB::table('gift_cards')->where('business_id', $this->bid($request))->where('id', $id)
            ->update(['expires_at' => $request->input('expires_at') ?: null, 'updated_at' => now()]);

        return $updated ? $this->ok('Expiry date updated.') : $this->fail('Gift card not found.');
    }

    public function sms(Request $request, $id)
    {
        $this->requireManage();
        $bid = $this->bid($request);

        $card = DB::table('gift_cards')->where('business_id', $bid)->where('id', $id)->first();
        abort_if(! $card, 404);

        if (! $card->recipient_phone) {
            return $this->fail('This card has no recipient phone number.');
        }

        $error = $this->sendCodeSms($card, $bid);

        return $error ? $this->fail('The SMS could not be sent: '.$error) : $this->ok('The code was sent by SMS.');
    }

    private function sendCodeSms($card, int $bid): ?string
    {
        try {
            $business = (string) DB::table('business')->where('id', $bid)->value('name');

            $text = substr($business, 0, 24).': you have a KSh '.number_format((float) $card->balance, 0).' gift card. Code: '.$card->code.'.'
                .($card->expires_at ? ' Valid until '.Carbon::parse($card->expires_at)->format('d/m/Y').'.' : '')
                .' Show this code at the till.';

            $result = app(AfricasTalkingService::class)->send($card->recipient_phone, preg_replace('/[^\x20-\x7E]/', '', $text));

            return ($result['sent'] ?? 0) > 0 ? null : 'Africa\'s Talking did not accept the message.';
        } catch (\Throwable $e) {
            Log::error('Gift card SMS failed: '.$e->getMessage());

            return $e->getMessage();
        }
    }
}
