<?php

namespace App\Http\Controllers;

use App\Services\AfricasTalkingSms;
use App\Services\GiftCardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GiftCardController extends Controller
{
    private function bid(Request $r): int
    {
        return (int) $r->session()->get('user.business_id');
    }

    private function manage(): void
    {
        if (! auth()->user()->can('giftcard.manage')) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function card(Request $r, $id)
    {
        $c = DB::table('gift_cards')->where('business_id', $this->bid($r))->where('id', $id)->first();
        abort_if(! $c, 404);

        return $c;
    }

    private function flash(bool $ok, string $msg)
    {
        return back()->with('status', ['success' => $ok ? 1 : 0, 'msg' => $msg]);
    }

    public function index(Request $request)
    {
        $this->manage();
        $bid = $this->bid($request);

        $q = DB::table('gift_cards')->where('business_id', $bid);
        if ($s = trim((string) $request->input('q'))) {
            $norm = GiftCardService::normalize($s);
            $q->where(function ($w) use ($s, $norm) {
                $w->where('code', 'like', '%'.$norm.'%')
                  ->orWhere('customer_name', 'like', '%'.$s.'%')
                  ->orWhere('customer_phone', 'like', '%'.$s.'%');
            });
        }
        $cards = $q->orderByDesc('id')->limit(200)->get();

        $live = DB::table('gift_cards')->where('business_id', $bid)->where('status', 'active')
            ->where(function ($w) {
                $w->whereNull('expires_at')->orWhere('expires_at', '>=', date('Y-m-d'));
            });
        $liability = (clone $live)->sum('balance');
        $activeCount = (clone $live)->count();

        return view('giftcards.index', compact('cards', 'liability', 'activeCount'));
    }

    public function store(Request $request)
    {
        $this->manage();

        $data = $request->validate([
            'amount' => 'required|numeric|min:1|max:1000000',
            'customer_name' => 'nullable|string|max:120',
            'customer_phone' => 'nullable|string|max:20',
            'expires_at' => 'nullable|date|after_or_equal:today',
            'received_via' => 'nullable|string|max:20',
        ]);

        $bid = $this->bid($request);
        $amount = round((float) $data['amount'], 2);
        $code = GiftCardService::generate();

        $id = DB::transaction(function () use ($bid, $code, $amount, $data) {
            $id = DB::table('gift_cards')->insertGetId([
                'business_id' => $bid,
                'code' => $code,
                'initial_amount' => $amount,
                'balance' => $amount,
                'status' => 'active',
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
                'issued_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            GiftCardService::log($id, $bid, 'issue', $amount, $amount, ['note' => 'Paid via '.($data['received_via'] ?? 'cash')]);

            return $id;
        });

        $msg = 'Gift card '.GiftCardService::format($code).' issued for KSh '.number_format($amount, 2).'.';
        if ($request->boolean('send_sms') && ! empty($data['customer_phone'])) {
            $msg .= ' '.$this->sendSms($request, DB::table('gift_cards')->find($id));
        }

        return redirect('gift-cards/'.$id)->with('status', ['success' => 1, 'msg' => $msg]);
    }

    public function show(Request $request, $id)
    {
        $this->manage();
        $card = $this->card($request, $id);
        $ledger = DB::table('gift_card_transactions as g')
            ->leftJoin('users as u', 'u.id', '=', 'g.user_id')
            ->leftJoin('transactions as t', 't.id', '=', 'g.transaction_id')
            ->where('g.gift_card_id', $card->id)
            ->orderByDesc('g.id')
            ->get(['g.*', 'u.first_name', 't.invoice_no']);

        $wa = null;
        if ($card->customer_phone && ($p = AfricasTalkingSms::normalizePhone($card->customer_phone))) {
            $wa = 'https://wa.me/'.ltrim($p, '+').'?text='.rawurlencode($this->message($request, $card));
        }

        return view('giftcards.show', compact('card', 'ledger', 'wa'));
    }

    public function topup(Request $request, $id)
    {
        $this->manage();
        $card = $this->card($request, $id);
        $data = $request->validate(['amount' => 'required|numeric|min:1|max:1000000', 'received_via' => 'nullable|string|max:20']);

        if ($card->status !== 'active') {
            return $this->flash(false, 'This gift card is voided.');
        }

        $amount = round((float) $data['amount'], 2);
        DB::transaction(function () use ($card, $amount, $data) {
            $locked = DB::table('gift_cards')->where('id', $card->id)->lockForUpdate()->first();
            $new = round((float) $locked->balance + $amount, 4);
            DB::table('gift_cards')->where('id', $card->id)->update(['balance' => $new, 'updated_at' => now()]);
            GiftCardService::log($card->id, $card->business_id, 'topup', $amount, $new, ['note' => 'Paid via '.($data['received_via'] ?? 'cash')]);
        });

        return $this->flash(true, 'Added KSh '.number_format($amount, 2).' to the card.');
    }

    public function void(Request $request, $id)
    {
        $this->manage();
        $card = $this->card($request, $id);

        if ($card->status !== 'active') {
            return $this->flash(false, 'Already voided.');
        }

        DB::transaction(function () use ($card, $request) {
            $locked = DB::table('gift_cards')->where('id', $card->id)->lockForUpdate()->first();
            DB::table('gift_cards')->where('id', $card->id)->update(['status' => 'void', 'balance' => 0, 'updated_at' => now()]);
            GiftCardService::log($card->id, $card->business_id, 'void', -(float) $locked->balance, 0, ['note' => $request->input('note') ?: 'Voided']);
        });

        return $this->flash(true, 'Gift card voided. Refund the customer by hand if needed.');
    }

    public function sms(Request $request, $id)
    {
        $this->manage();
        $card = $this->card($request, $id);

        return $this->flash(true, $this->sendSms($request, $card));
    }

    /** JSON balance check used by the POS screen. */
    public function check(Request $request)
    {
        $u = auth()->user();
        if (! ($u->can('sell.create') || $u->can('direct_sell.access') || $u->can('giftcard.manage'))) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $code = GiftCardService::normalize($request->input('code'));
        $card = DB::table('gift_cards')->where('business_id', $this->bid($request))->where('code', $code)->first();

        if ($msg = GiftCardService::unusable($card, 0.01)) {
            return response()->json(['success' => false, 'message' => $card ? $msg : 'Gift card not found.']);
        }

        return response()->json([
            'success' => true,
            'balance' => (float) $card->balance,
            'expires' => $card->expires_at ? date('d/m/Y', strtotime($card->expires_at)) : null,
        ]);
    }

    private function message(Request $request, $card): string
    {
        $biz = $request->session()->get('business.name') ?: 'our shop';

        return 'You have a '.$biz.' gift card worth KSh '.number_format($card->balance, 2)
            .'. Code: '.GiftCardService::format($card->code)
            .($card->expires_at ? '. Valid until '.date('d/m/Y', strtotime($card->expires_at)) : '')
            .'. Show this code at the till to redeem.';
    }

    private function sendSms(Request $request, $card): string
    {
        if (! $card->customer_phone) {
            return 'No phone number on this card.';
        }
        $sms = app(AfricasTalkingSms::class);
        if (! $sms->configured()) {
            return 'SMS not sent: Africa\'s Talking is not configured.';
        }
        $r = $sms->send($card->customer_phone, $this->message($request, $card));

        return $r['ok'] ? 'SMS sent.' : 'SMS failed: '.$r['error'];
    }
}
