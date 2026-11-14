@php
    $bid = (int) request()->session()->get('user.business_id');
    $channels = $channels ?? \App\Services\PaymentChannelService::channels($bid);
    $banks = $banks ?? \App\Services\PaymentChannelService::banks($bid);
    $bankList = \App\Services\PaymentChannelService::BANKS;
    $fieldMap = \App\Services\PaymentChannelService::FIELDS;
    $maxLen = ['business_name' => 80, 'account_number' => 40, 'account_reference' => 60];
    $st = ['active' => ['success', 'Active'], 'inactive' => ['default', 'Inactive'], 'not_configured' => ['warning', 'Not configured']];
    $bankEditId = (int) old('_bank_id');
@endphp

<style>
    .dante-switch{position:relative;display:inline-block;width:46px;height:24px;vertical-align:middle;margin:0 8px 0 0}
    .dante-switch input{opacity:0;width:0;height:0}
    .dante-switch > span{position:absolute;inset:0;background:#cbd5e1;border-radius:24px;transition:.2s;cursor:pointer}
    .dante-switch > span:before{content:"";position:absolute;height:18px;width:18px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.2s}
    .dante-switch input:checked + span{background:#16a34a}
    .dante-switch input:checked + span:before{transform:translateX(22px)}
    .dante-switch-row{font-weight:600;margin-bottom:12px;cursor:pointer}
    .dante-chips .label{display:inline-block;margin:0 6px 6px 0;font-size:12px;padding:6px 10px}
</style>

<div style="margin-top:25px;">
    <h3 class="tw-font-bold">Kenyan payment channels
        <small>Bank accounts, mobile money and online payments</small>
    </h3>

    @if ($errors->any() && ! old('_bank_form'))
        <div class="alert alert-danger" id="dante_channel_errors">
            <ul style="margin:0;padding-left:18px;">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="dante-chips">
        <span class="label label-primary">Bank accounts: {{ $banks->where('is_active', true)->count() }} active</span>
        @foreach ($channels as $c)
            <span class="label label-{{ $st[$c['status']][0] }}">{{ $c['label'] }}: {{ $st[$c['status']][1] }}</span>
        @endforeach
    </div>

    <p class="text-muted">
        These details are what customers are told to pay into. They do not take payments or confirm them by themselves.
        To let cashiers record a sale with Airtel Money, Equitel or Pesalink, enable a Custom payment method in the table above and rename it.
    </p>

    @component('components.widget', ['class' => 'box-primary', 'title' => 'Bank payments'])
        <p>
            <button type="button" class="btn btn-primary btn-sm js-bank-add" data-toggle="modal" data-target="#dante_bank_modal">Add bank account</button>
        </p>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Bank</th><th>Account name</th><th>Account number</th><th>Branch</th>
                        <th>Currency</th><th>Status</th><th style="width:240px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($banks as $b)
                    <tr>
                        <td>{{ $b->bank_name }}</td>
                        <td>{{ $b->account_name }}</td>
                        <td>{{ $b->account_number }}</td>
                        <td>{{ $b->branch_name ?: '-' }}</td>
                        <td>{{ $b->currency }}</td>
                        <td>
                            @if ($b->is_active)<span class="label label-success">Active</span>
                            @else<span class="label label-default">Inactive</span>@endif
                        </td>
                        <td>
                            <button type="button" class="btn btn-xs btn-info js-bank-edit" data-toggle="modal" data-target="#dante_bank_modal"
                                data-action="{{ route('dantepos.payments.bank.update', $b->id) }}"
                                data-bank="{{ $b->toJson() }}">Edit</button>
                            <form method="POST" action="{{ route('dantepos.payments.bank.toggle', $b->id) }}" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn btn-xs btn-default">{{ $b->is_active ? 'Deactivate' : 'Activate' }}</button>
                            </form>
                            <form method="POST" action="{{ route('dantepos.payments.bank.delete', $b->id) }}" style="display:inline;"
                                onsubmit="return confirm('Delete this bank account? This cannot be undone.');">
                                @csrf
                                <button type="submit" class="btn btn-xs btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">No bank accounts yet. Add the accounts customers can pay into.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    @endcomponent

    <div class="row">
        @foreach ($channels as $key => $c)
            @php $mine = old('_channel') === $key; @endphp
            <div class="col-md-6">
                @component('components.widget', ['class' => 'box-solid', 'title' => $c['label']])
                    <form method="POST" action="{{ route('dantepos.payments.channel', $key) }}">
                        @csrf
                        <input type="hidden" name="_channel" value="{{ $key }}">
                        <p><span class="label label-{{ $st[$c['status']][0] }}">{{ $st[$c['status']][1] }}</span></p>
                        <label class="dante-switch-row">
                            <span class="dante-switch"><input type="checkbox" name="is_enabled" value="1" {{ ($mine ? (bool) old('is_enabled') : $c['is_enabled']) ? 'checked' : '' }}><span></span></span>
                            Enabled
                        </label>
                        @foreach ($fieldMap[$key] as $field => $fieldLabel)
                            <div class="form-group">
                                <label>{{ $fieldLabel }}</label>
                                <input type="text" class="form-control" name="{{ $field }}" value="{{ $mine ? old($field, '') : $c[$field] }}" maxlength="{{ $maxLen[$field] ?? 80 }}">
                            </div>
                        @endforeach
                        <div class="form-group">
                            <label>Payment instructions shown to customers</label>
                            <textarea class="form-control" name="instructions" rows="3" maxlength="500">{{ $mine ? old('instructions', '') : $c['instructions'] }}</textarea>
                        </div>
                        @if ($key === 'card')
                            <p class="text-muted small">Card payments need an account with a card gateway before customers can pay by card online. This only controls what is shown.</p>
                        @elseif ($key === 'pesalink')
                            <p class="text-muted small">Pesalink works only if the customer's bank supports it.</p>
                        @endif
                        <button type="submit" class="btn btn-primary btn-sm">Save {{ $c['label'] }}</button>
                    </form>
                @endcomponent
            </div>
        @endforeach
    </div>
</div>

<div class="modal fade" id="dante_bank_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" id="dante_bank_form"
                action="{{ $bankEditId ? route('dantepos.payments.bank.update', $bankEditId) : route('dantepos.payments.bank.store') }}"
                data-add-url="{{ route('dantepos.payments.bank.store') }}">
                @csrf
                <input type="hidden" name="_bank_form" value="1">
                <input type="hidden" name="_bank_id" id="dante_bank_id" value="{{ $bankEditId ?: '' }}">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="dante_bank_title">{{ $bankEditId ? 'Edit bank account' : 'Add bank account' }}</h4>
                </div>
                <div class="modal-body">
                    @if (old('_bank_form') && $errors->any())
                        <div class="alert alert-danger">
                            <ul style="margin:0;padding-left:18px;">
                                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                            </ul>
                        </div>
                    @endif
                    <div class="form-group">
                        <label for="dante_bank_name">Bank</label>
                        <select class="form-control" id="dante_bank_name" name="bank_name" required>
                            @foreach ($bankList as $bn)<option value="{{ $bn }}" {{ old('bank_name') === $bn ? 'selected' : '' }}>{{ $bn }}</option>@endforeach
                            <option value="Other" {{ old('bank_name') === 'Other' ? 'selected' : '' }}>Other bank</option>
                        </select>
                    </div>
                    <div class="form-group" id="dante_other_bank_wrap" style="display:none;">
                        <label for="dante_other_bank">Bank name</label>
                        <input type="text" class="form-control" id="dante_other_bank" name="other_bank_name" value="{{ old('other_bank_name') }}" maxlength="100">
                    </div>
                    <div class="form-group">
                        <label for="dante_branch_name">Branch name (optional)</label>
                        <input type="text" class="form-control" id="dante_branch_name" name="branch_name" value="{{ old('branch_name') }}" maxlength="100">
                    </div>
                    <div class="form-group">
                        <label for="dante_account_name">Account name</label>
                        <input type="text" class="form-control" id="dante_account_name" name="account_name" value="{{ old('account_name') }}" maxlength="120" required>
                    </div>
                    <div class="form-group">
                        <label for="dante_account_number">Account number</label>
                        <input type="text" class="form-control" id="dante_account_number" name="account_number" value="{{ old('account_number') }}" maxlength="40" required>
                    </div>
                    <div class="row">
                        <div class="col-sm-6 form-group">
                            <label for="dante_branch_code">Branch code (optional)</label>
                            <input type="text" class="form-control" id="dante_branch_code" name="branch_code" value="{{ old('branch_code') }}" maxlength="20">
                        </div>
                        <div class="col-sm-6 form-group">
                            <label for="dante_swift_code">SWIFT code (optional)</label>
                            <input type="text" class="form-control" id="dante_swift_code" name="swift_code" value="{{ old('swift_code') }}" maxlength="11">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="dante_currency">Currency</label>
                        <select class="form-control" id="dante_currency" name="currency">
                            @foreach (['KES', 'USD', 'EUR', 'GBP'] as $cur)<option value="{{ $cur }}" {{ old('currency', 'KES') === $cur ? 'selected' : '' }}>{{ $cur }}</option>@endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="dante_bank_instructions">Payment instructions (optional)</label>
                        <textarea class="form-control" id="dante_bank_instructions" name="instructions" rows="3" maxlength="500">{{ old('instructions') }}</textarea>
                    </div>
                    <label><input type="checkbox" id="dante_bank_active" name="is_active" value="1" {{ old('_bank_form') ? (old('is_active') ? 'checked' : '') : 'checked' }}> Active</label>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save bank account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('dante_bank_form');
    if (!form) { return; }
    var addUrl = form.getAttribute('data-add-url');
    var sel = document.getElementById('dante_bank_name');
    var otherWrap = document.getElementById('dante_other_bank_wrap');
    var title = document.getElementById('dante_bank_title');
    function syncOther() { otherWrap.style.display = sel.value === 'Other' ? '' : 'none'; }
    function setVal(id, v) { document.getElementById(id).value = (v === null || v === undefined) ? '' : v; }
    sel.addEventListener('change', syncOther);

    document.querySelectorAll('.js-bank-add').forEach(function (btn) {
        btn.addEventListener('click', function () {
            form.reset();
            form.action = addUrl;
            title.textContent = 'Add bank account';
            form.querySelectorAll('input[type=text], textarea').forEach(function (el) { el.value = ''; });
            sel.selectedIndex = 0;
            setVal('dante_currency', 'KES');
            setVal('dante_bank_id', '');
            document.getElementById('dante_bank_active').checked = true;
            var box = form.querySelector('.modal-body .alert');
            if (box) { box.style.display = 'none'; }
            syncOther();
        });
    });

    document.querySelectorAll('.js-bank-edit').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var b = JSON.parse(btn.getAttribute('data-bank'));
            form.reset();
            form.action = btn.getAttribute('data-action');
            title.textContent = 'Edit bank account';
            setVal('dante_bank_id', b.id);
            var known = Array.prototype.some.call(sel.options, function (o) { return o.value === b.bank_name; });
            sel.value = known ? b.bank_name : 'Other';
            setVal('dante_other_bank', known ? '' : b.bank_name);
            setVal('dante_branch_name', b.branch_name);
            setVal('dante_account_name', b.account_name);
            setVal('dante_account_number', b.account_number);
            setVal('dante_branch_code', b.branch_code);
            setVal('dante_swift_code', b.swift_code);
            setVal('dante_currency', b.currency);
            setVal('dante_bank_instructions', b.instructions);
            document.getElementById('dante_bank_active').checked = !!b.is_active;
            var box = form.querySelector('.modal-body .alert');
            if (box) { box.style.display = 'none'; }
            syncOther();
        });
    });

    syncOther();
    var reopen = {!! old('_bank_form') && $errors->any() ? 'true' : 'false' !!};
    if (reopen) {
        window.addEventListener('load', function () { if (window.jQuery) { jQuery('#dante_bank_modal').modal('show'); } });
    }
    var err = document.getElementById('dante_channel_errors');
    if (err) { err.scrollIntoView({ block: 'center' }); }
})();
</script>
