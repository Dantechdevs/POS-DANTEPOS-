@extends('layouts.app')
@section('title', 'DantePOS Settings')

@section('content')
<section class="content-header">
    <h1>DantePOS Settings <small>Rules, alerts and system health</small></h1>
</section>

<section class="content">
    {{-- System check --}}
    <div class="box {{ $counts['fail'] ? 'box-danger' : ($counts['warn'] ? 'box-warning' : 'box-success') }}">
        <div class="box-header with-border">
            <h3 class="box-title">System check</h3>
            <div class="box-tools">
                <span class="label label-danger">{{ $counts['fail'] }} failure(s)</span>
                <span class="label label-warning">{{ $counts['warn'] }} warning(s)</span>
                <span class="label label-success">{{ $counts['ok'] }} OK</span>
            </div>
        </div>
        <div class="box-body">
            <table class="table table-condensed">
                @foreach($lines as $l)
                    @if($l['level'] !== 'ok')
                    <tr><td style="width:80px"><span class="label label-{{ $l['level'] === 'fail' ? 'danger' : 'warning' }}">{{ strtoupper($l['level']) }}</span></td><td>{{ $l['text'] }}</td></tr>
                    @endif
                @endforeach
                @if($counts['fail'] + $counts['warn'] === 0)
                    <tr><td><i class="fa fa-check text-green"></i> Everything checked is fine.</td></tr>
                @endif
            </table>
            <details><summary>Show all {{ count($lines) }} checks</summary>
                <table class="table table-condensed" style="margin-top:8px">
                    @foreach($lines as $l)
                    <tr><td style="width:80px">{{ strtoupper($l['level']) }}</td><td>{{ $l['text'] }}</td></tr>
                    @endforeach
                </table>
            </details>
        </div>
    </div>

    {{-- Rules --}}
    <form method="POST" action="{{ url('/dantepos-settings') }}" id="settingsForm">
        @csrf
        <div class="box box-solid">
            <div class="box-header with-border"><h3 class="box-title">Selling rules</h3></div>
            <div class="box-body">
                <div class="form-group">
                    <label>Selling below cost</label>
                    @foreach(['off' => 'Off: not checked or logged', 'warn' => 'Warn: allowed, but logged', 'block' => 'Block: cashiers cannot sell below cost (managers with the override permission can)'] as $v => $t)
                        <div class="radio"><label><input type="radio" name="price_guard_mode" value="{{ $v }}" {{ old('price_guard_mode', $settings['price_guard_mode']) === $v ? 'checked' : '' }}> {{ $t }}</label></div>
                    @endforeach
                </div>
                <hr>
                <div class="form-group">
                    <label>Credit sales</label>
                    <input type="hidden" name="credit_require_phone" value="0">
                    <div class="checkbox"><label><input type="checkbox" name="credit_require_phone" value="1" {{ old('credit_require_phone', $settings['credit_require_phone']) ? 'checked' : '' }}> Customer must have a valid phone number</label></div>
                    <input type="hidden" name="credit_require_limit" value="0">
                    <div class="checkbox"><label><input type="checkbox" name="credit_require_limit" value="1" {{ old('credit_require_limit', $settings['credit_require_limit']) ? 'checked' : '' }}> Customer must have a credit limit (managers can override)</label></div>
                    <p class="help-block">Credit sales to the Walk-In customer are always blocked.</p>
                </div>
            </div>
        </div>

        <div class="box box-solid">
            <div class="box-header with-border"><h3 class="box-title">Alerts</h3></div>
            <div class="box-body">
                <div class="form-group">
                    <label for="owner_phones">Owner phone numbers</label>
                    <input type="text" name="owner_phones" id="owner_phones" class="form-control" placeholder="0712345678, 0722345678" value="{{ old('owner_phones', $settings['owner_phones']) }}">
                    <p class="help-block">Receive the daily sales summary and backup-failure alerts. Separate several numbers with commas.</p>
                </div>
            </div>
        </div>

        <button class="btn btn-primary btn-lg">Save settings</button>
    </form>

    {{-- Services --}}
    <div class="box box-solid" style="margin-top:20px">
        <div class="box-header with-border"><h3 class="box-title">Connected services</h3></div>
        <div class="box-body">
            <table class="table table-condensed">
                @foreach($services as $name => $status)
                <tr><td>{{ $name }}</td><td><span class="label label-{{ strpos($status, 'Not set') === 0 ? 'warning' : 'success' }}">{{ $status }}</span></td></tr>
                @endforeach
            </table>
            <p class="text-muted">Keys and passwords stay in the server's .env file and are never shown here. Only "Set" or "Not set" is displayed.</p>
        </div>
    </div>

    {{-- Test SMS --}}
    <div class="box box-solid">
        <div class="box-header with-border"><h3 class="box-title">Test SMS</h3></div>
        <div class="box-body">
            <form method="POST" action="{{ url('/dantepos-settings/sms-test') }}" class="form-inline" onsubmit="return confirm('Send a real SMS now? It uses SMS credit.');">
                @csrf
                <input type="text" name="test_phone" class="form-control" placeholder="0712345678" value="{{ old('test_phone') }}" required>
                <button class="btn btn-default"><i class="fa fa-comment"></i> Send test message</button>
            </form>
            <p class="help-block">Sends one real message through Africa's Talking and shows the provider's reply. Limited to 5 per hour.</p>
        </div>
    </div>

    {{-- Backup schedule --}}
    <div class="box box-solid">
        <div class="box-header with-border"><h3 class="box-title">Backup schedule</h3></div>
        <div class="box-body">
            <form method="POST" action="{{ url('/dantepos-settings/backup-schedule') }}">
                @csrf
                <input type="hidden" name="backup_enabled" value="0">
                <div class="checkbox"><label><input type="checkbox" name="backup_enabled" value="1" {{ old('backup_enabled', $settings['backup_enabled']) ? 'checked' : '' }}> Back up the database automatically every day</label></div>
                <div class="form-group" style="margin-top:10px">
                    <label for="backup_time">Time of day</label>
                    <input type="time" name="backup_time" id="backup_time" class="form-control" style="max-width:160px" value="{{ old('backup_time', $settings['backup_time']) }}">
                </div>
                <div class="form-group">
                    <label for="backup_keep_days">Keep backups for (days)</label>
                    <input type="number" name="backup_keep_days" id="backup_keep_days" class="form-control" style="max-width:160px" min="1" max="365" value="{{ old('backup_keep_days', $settings['backup_keep_days']) }}">
                    <p class="help-block">How long backup files are kept before they are deleted.</p>
                </div>
                <button class="btn btn-primary">Save schedule</button>
            </form>
        </div>
    </div>

    {{-- Backups --}}
    <div class="box box-solid">
        <div class="box-header with-border"><h3 class="box-title">Backups</h3></div>
        <div class="box-body">
            <table class="table table-condensed">
                <thead><tr><th>File</th><th>Size</th><th>Created</th></tr></thead>
                @forelse($backups as $b)
                    <tr><td>{{ $b['name'] }}</td><td>{{ $b['size'] }}</td><td>{{ $b['time'] }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-muted">No backups yet.</td></tr>
                @endforelse
            </table>
            <form method="POST" action="{{ url('/dantepos-settings/backup') }}" onsubmit="return confirm('Back up the database now? This takes a few seconds.');">
                @csrf
                <button class="btn btn-default"><i class="fa fa-database"></i> Back up now</button>
            </form>
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script>
    $('#settingsForm').on('submit', function (e) {
        if ($('input[name=price_guard_mode]:checked').val() === 'off' &&
            !confirm('Turn the below-cost check OFF? Sales below cost will not be flagged or logged.')) {
            e.preventDefault();
        }
    });
</script>
@endsection
