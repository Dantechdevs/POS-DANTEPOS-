@php
    $cbid = (int) request()->session()->get('user.business_id');
    $cr = \App\Services\MpesaCredentials::hasOwn($cbid);
    $crow = $cr['row'];
    $cmine = old('_mpesa_cred') === '1';
    $cv = fn ($k, $d) => $cmine ? old($k, $d) : $d;
    $cbUrl = ! empty($crow->callback_secret) ? url('/mpesa/callback/'.$crow->callback_secret) : null;
    $saved = 'Saved. Leave blank to keep';
@endphp

<div style="margin-top:25px;">
    @component('components.widget', ['class' => 'box-primary', 'title' => 'M-Pesa Daraja credentials (STK push)'])
        @if ($errors->any() && $cmine)
            <div class="alert alert-danger"><ul style="margin:0;padding-left:18px;">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul></div>
        @endif
        <p class="text-muted">From your Safaricom Daraja app. Secrets are stored encrypted and are never shown again.</p>
        <form method="POST" action="{{ route('dantepos.payments.mpesa.credentials') }}" autocomplete="off">
            @csrf
            <input type="hidden" name="_mpesa_cred" value="1">
            <div class="row">
                <div class="col-sm-4 form-group">
                    <label>Environment</label>
                    <select class="form-control" name="daraja_env">
                        <option value="sandbox" {{ $cv('daraja_env', $crow->daraja_env ?? 'sandbox') === 'sandbox' ? 'selected' : '' }}>Sandbox (testing)</option>
                        <option value="production" {{ $cv('daraja_env', $crow->daraja_env ?? 'sandbox') === 'production' ? 'selected' : '' }}>Production (live)</option>
                    </select>
                </div>
                <div class="col-sm-4 form-group">
                    <label>STK shortcode</label>
                    <input type="text" class="form-control" name="stk_shortcode" maxlength="7" value="{{ $cv('stk_shortcode', $crow->stk_shortcode ?? '') }}">
                    <p class="help-block">The shortcode your Daraja app pushes from. For a till this can differ from the till number customers pay.</p>
                </div>
                <div class="col-sm-4 form-group">
                    <label>STK type</label>
                    <select class="form-control" name="transaction_type">
                        <option value="CustomerBuyGoodsOnline" {{ $cv('transaction_type', $crow->transaction_type ?? 'CustomerBuyGoodsOnline') === 'CustomerBuyGoodsOnline' ? 'selected' : '' }}>Till (Buy Goods)</option>
                        <option value="CustomerPayBillOnline" {{ $cv('transaction_type', $crow->transaction_type ?? '') === 'CustomerPayBillOnline' ? 'selected' : '' }}>Paybill</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-4 form-group">
                    <label>Consumer key</label>
                    <input type="password" class="form-control" name="consumer_key" autocomplete="new-password" placeholder="{{ $cr['key'] ? $saved : '' }}">
                </div>
                <div class="col-sm-4 form-group">
                    <label>Consumer secret</label>
                    <input type="password" class="form-control" name="consumer_secret" autocomplete="new-password" placeholder="{{ $cr['secret'] ? $saved : '' }}">
                </div>
                <div class="col-sm-4 form-group">
                    <label>Passkey</label>
                    <input type="password" class="form-control" name="passkey" autocomplete="new-password" placeholder="{{ $cr['passkey'] ? $saved : '' }}">
                </div>
            </div>
            @if ($cbUrl)
                <div class="form-group">
                    <label>Callback URL (give this to Safaricom)</label>
                    <input type="text" class="form-control" value="{{ $cbUrl }}" readonly onclick="this.select()">
                    <p class="help-block">It must be a public https address. Safaricom cannot reach 127.0.0.1.</p>
                    <label><input type="checkbox" name="regenerate_callback" value="1"> Generate a new callback URL (the old one stops working)</label>
                </div>
            @else
                <p class="text-muted">A callback URL is created when you save.</p>
            @endif
            <button type="submit" class="btn btn-primary btn-sm">Save Daraja details</button>
        </form>
    @endcomponent
</div>
