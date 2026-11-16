@php
    $mbid = (int) request()->session()->get('user.business_id');
    $mp = \App\Services\MpesaSettingsService::get($mbid);
    $mine = old('_mpesa') === '1';
    $v = fn ($k) => $mine ? old($k, '') : $mp[$k];
@endphp

<div style="margin-top:25px;">
    @component('components.widget', ['class' => 'box-primary', 'title' => 'M-Pesa receiving details and manual payments'])
        @if ($errors->any() && $mine)
            <div class="alert alert-danger">
                <ul style="margin:0;padding-left:18px;">
                    @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
        @endif
        <form method="POST" action="{{ route('dantepos.payments.mpesa') }}">
            @csrf
            <input type="hidden" name="_mpesa" value="1">
            <div class="row">
                <div class="col-sm-4 form-group">
                    <label>Account type</label>
                    <select class="form-control" name="account_type">
                        <option value="till" {{ $v('account_type') === 'till' ? 'selected' : '' }}>Till (Buy Goods)</option>
                        <option value="paybill" {{ $v('account_type') === 'paybill' ? 'selected' : '' }}>Paybill</option>
                    </select>
                </div>
                <div class="col-sm-4 form-group">
                    <label>Till number</label>
                    <input type="text" class="form-control" name="till_number" value="{{ $v('till_number') }}" maxlength="7">
                </div>
                <div class="col-sm-4 form-group">
                    <label>Paybill number</label>
                    <input type="text" class="form-control" name="shortcode" value="{{ $v('shortcode') }}" maxlength="7">
                </div>
            </div>
            <div class="form-group">
                <label>Note shown to customers (optional)</label>
                <input type="text" class="form-control" name="account_hint" value="{{ $v('account_hint') }}" maxlength="60">
            </div>

            <label class="dante-switch-row">
                <span class="dante-switch"><input type="checkbox" name="manual_enabled" value="1" {{ ($mine ? (bool) old('manual_enabled') : $mp['manual_enabled']) ? 'checked' : '' }}><span></span></span>
                Allow M-Pesa payments without STK confirmation
            </label>
            <div class="form-group" style="max-width:320px;">
                <label>Maximum per unconfirmed payment (KSh)</label>
                <input type="number" step="0.01" min="0" max="1000000" class="form-control" name="manual_unverified_cap" value="{{ $v('manual_unverified_cap') }}">
                <p class="help-block">Larger M-Pesa payments still need a confirmed STK push. Use 0 to allow none.</p>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Save M-Pesa settings</button>
        </form>
    @endcomponent
</div>
