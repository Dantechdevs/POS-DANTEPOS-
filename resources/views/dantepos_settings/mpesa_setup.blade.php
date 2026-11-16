@php($mps = \App\Http\Controllers\DanteMpesaSetupController::viewData())
<a id="mpesa-setup"></a>
@component('components.widget', ['class' => 'box-primary', 'title' => 'M-Pesa setup (Till or PayBill)'])
    @if ($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $err)
                <div>{{ $err }}</div>
            @endforeach
        </div>
    @endif

    <p>
        <b>Status:</b>
        @if ($mps['state'] === 'not_set')
            <span class="label label-warning">Not set</span>
        @elseif ($mps['state'] === 'untested')
            <span class="label label-info">Saved, not tested</span>
        @elseif ($mps['state'] === 'accepted')
            <span class="label label-success">Keys accepted ({{ $mps['env'] }})</span>
        @else
            <span class="label label-danger">Test failed</span>
        @endif
        @if ($mps['test'])
            <small class="text-muted">Last test {{ $mps['test']['at'] ?? '' }}: {{ $mps['test']['msg'] ?? '' }}</small>
        @endif
    </p>
    <p class="text-muted">
        You need a Daraja app from Safaricom (consumer key and secret), plus the passkey and shortcode for your Till or PayBill.
        Each business saves its own details here, and they are stored encrypted.
    </p>

    <form method="POST" action="{{ route('dantepos.payments.mpesa.setup') }}" autocomplete="off">
        @csrf
        <div class="form-group">
            <label class="radio-inline"><input type="radio" name="account_type" value="till" {{ old('account_type', $mps['type']) === 'till' ? 'checked' : '' }}> Till number (Buy Goods)</label>
            <label class="radio-inline"><input type="radio" name="account_type" value="paybill" {{ old('account_type', $mps['type']) === 'paybill' ? 'checked' : '' }}> PayBill</label>
        </div>

        <div id="dante-mp-till" class="row">
            <div class="col-sm-6">
                <div class="form-group">
                    <label>Till number</label>
                    <input type="text" name="till_number" class="form-control" maxlength="7" inputmode="numeric" value="{{ old('till_number', $mps['till_number']) }}" placeholder="The number customers pay to">
                </div>
            </div>
            <div class="col-sm-6">
                <div class="form-group">
                    <label>Store / Head Office number</label>
                    <input type="text" name="stk_shortcode" class="form-control" maxlength="7" inputmode="numeric" value="{{ old('stk_shortcode', $mps['stk_shortcode']) }}" placeholder="Used by Safaricom for STK push">
                </div>
            </div>
        </div>

        <div id="dante-mp-paybill" class="row">
            <div class="col-sm-6">
                <div class="form-group">
                    <label>PayBill number</label>
                    <input type="text" name="shortcode" class="form-control" maxlength="7" inputmode="numeric" value="{{ old('shortcode', $mps['shortcode']) }}">
                </div>
            </div>
            <div class="col-sm-6">
                <div class="form-group">
                    <label>Account number rule (shown to staff)</label>
                    <input type="text" name="account_hint" class="form-control" maxlength="60" value="{{ old('account_hint', $mps['account_hint']) }}" placeholder="e.g. the invoice number">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-4">
                <div class="form-group">
                    <label>Environment</label>
                    <select name="daraja_env" class="form-control">
                        <option value="sandbox" {{ old('daraja_env', $mps['env']) === 'sandbox' ? 'selected' : '' }}>Sandbox (testing, no real money)</option>
                        <option value="live" {{ old('daraja_env', $mps['env']) === 'live' ? 'selected' : '' }}>Live (needs Safaricom go-live approval)</option>
                    </select>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="form-group">
                    <label>Consumer key</label>
                    <input type="password" name="consumer_key" class="form-control" autocomplete="new-password" placeholder="{{ $mps['has']['key'] ? 'Saved. Leave blank to keep it' : 'From your Daraja app' }}">
                </div>
            </div>
            <div class="col-sm-4">
                <div class="form-group">
                    <label>Consumer secret</label>
                    <input type="password" name="consumer_secret" class="form-control" autocomplete="new-password" placeholder="{{ $mps['has']['secret'] ? 'Saved. Leave blank to keep it' : 'From your Daraja app' }}">
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>Passkey</label>
            <input type="password" name="passkey" class="form-control" autocomplete="new-password" placeholder="{{ $mps['has']['passkey'] ? 'Saved. Leave blank to keep it' : 'The Lipa na M-Pesa Online passkey' }}">
        </div>

        @if ($mps['callback'])
            <div class="form-group">
                <label>Callback URL for this business</label>
                <input type="text" class="form-control" readonly value="{{ $mps['callback'] }}" onclick="this.select()">
                <p class="help-block">Safaricom can only reach a public HTTPS address. It will not reach a local address such as 127.0.0.1.</p>
                <label class="checkbox-inline"><input type="checkbox" name="regen_secret" value="1"> Create a new callback secret when saving</label>
            </div>
        @endif

        <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">Save M-Pesa setup</button>
    </form>

    <form method="POST" action="{{ route('dantepos.payments.mpesa.test') }}" style="margin-top:10px;">
        @csrf
        <button type="submit" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-primary" {{ ($mps['has']['key'] && $mps['has']['secret']) ? '' : 'disabled' }}>Test connection</button>
        <small class="text-muted">Checks the consumer key, secret and environment only. The passkey and shortcode are proven by the first real STK push.</small>
    </form>
@endcomponent
<script>
(function () {
    function sync() {
        var t = document.querySelector('input[name="account_type"]:checked');
        var till = !t || t.value === 'till';
        document.getElementById('dante-mp-till').style.display = till ? '' : 'none';
        document.getElementById('dante-mp-paybill').style.display = till ? 'none' : '';
    }
    document.querySelectorAll('input[name="account_type"]').forEach(function (el) { el.addEventListener('change', sync); });
    sync();
})();
</script>
