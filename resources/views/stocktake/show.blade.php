@extends('layouts.app')
@section('title', 'Stocktake '.$st->ref_no)

@php
    $q = function ($v) {
        $s = rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
        return $s === '' ? '0' : $s;
    };
    $counting = $st->status === 'counting';
@endphp

@section('content')
<section class="content-header">
    <h1>
        Stocktake {{ $st->ref_no }}
        <small>{{ $location }} {{ $st->name ? '· '.$st->name : '' }}</small>
        @if ($st->status === 'applied')<span class="label label-success">Applied</span>
        @elseif ($st->status === 'cancelled')<span class="label label-default">Cancelled</span>
        @else<span class="label label-warning">Counting</span>@endif
    </h1>
</section>

<section class="content">
    <p><a href="{{ url('/stocktakes') }}">&larr; All stocktakes</a></p>

    <div class="row">
        <div class="col-md-3"><div class="well text-center"><small>Counted</small><h3><span id="sum-counted">{{ $summary['counted'] }}</span> / {{ $summary['total'] }}</h3></div></div>
        <div class="col-md-3"><div class="well text-center"><small>Short (missing)</small><h3 class="text-red">-<span id="sum-short">{{ $q($summary['short_qty']) }}</span></h3><small>KSh <span id="sum-short-value">{{ number_format($summary['short_value'], 2) }}</span> at cost</small></div></div>
        <div class="col-md-3"><div class="well text-center"><small>Over (extra found)</small><h3 class="text-green">+<span id="sum-over">{{ $q($summary['over_qty']) }}</span></h3><small>KSh <span id="sum-over-value">{{ number_format($summary['over_value'], 2) }}</span> at cost</small></div></div>
        <div class="col-md-3"><div class="well text-center"><small>Net difference at cost</small><h3>KSh <span id="sum-net">{{ number_format($summary['over_value'] - $summary['short_value'], 2) }}</span></h3></div></div>
    </div>

    @if ($applied)
        <div class="alert alert-success">
            Applied {{ substr($st->applied_at, 0, 16) }}.
            Shortages: {{ $applied['short_items'] }} item(s), KSh {{ number_format($applied['short_value'], 2) }}
            @if ($applied['adjustment_ref'])
                (stock adjustment <a href="{{ url('/stock-adjustments') }}">{{ $applied['adjustment_ref'] }}</a>)
            @endif.
            @if ($applied['over_booking_requested'])
                Overages: {{ $applied['over_items_booked'] }} item(s) added as opening stock, KSh {{ number_format($applied['over_value'], 2) }}.
            @elseif ($applied['over_items_found'] > 0)
                {{ $applied['over_items_found'] }} overage(s) were NOT booked.
            @endif
        </div>
    @endif

    @if ($counting)
    <div class="box box-solid">
        <div class="box-body">
            <div class="form-inline">
                <input type="text" id="scan" class="form-control" placeholder="Scan barcode / type SKU, press Enter" autocomplete="off" style="min-width:260px">
                <label style="margin:0 12px"><input type="checkbox" id="scan-mode"> Scan mode (+1 per scan)</label>
                <input type="text" id="filter" class="form-control" placeholder="Filter by name or SKU">
                <span id="scan-msg" style="margin-left:10px"></span>
            </div>
            <hr>
            <div class="form-inline">
                <form id="saveForm" method="POST" action="{{ url('/stocktakes/'.$st->id.'/counts') }}" style="display:inline">
                    @csrf
                    <input type="hidden" name="counts_json" id="counts_json">
                    <button type="button" id="saveBtn" class="btn btn-primary"><i class="fa fa-save"></i> Save counts</button>
                </form>
                <a class="btn btn-default" href="{{ url('/stocktakes/'.$st->id.'/export') }}"><i class="fa fa-download"></i> Count sheet (CSV)</a>
                <form method="POST" action="{{ url('/stocktakes/'.$st->id.'/import') }}" enctype="multipart/form-data" style="display:inline">
                    @csrf
                    <input type="file" name="csv_file" accept=".csv,.txt" class="form-control" style="display:inline-block;width:auto" required>
                    <button class="btn btn-default"><i class="fa fa-upload"></i> Import counts</button>
                </form>
            </div>
        </div>
    </div>
    @endif

    <div class="box box-solid">
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped" id="lines">
                <thead>
                    <tr>
                        <th>SKU</th><th>Product</th>
                        <th class="text-right">{{ $counting ? 'In system now' : 'In system' }}</th>
                        <th style="width:140px">Counted</th>
                        <th class="text-right">Difference</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($lines as $l)
                    @php
                        $expected = (float) ($l->current_qty ?? 0);
                        $countedStr = $l->counted_qty === null ? '' : $q($l->counted_qty);
                        $diff = $l->counted_qty === null ? null : ($st->status === 'applied' && $l->variance !== null ? (float) $l->variance : (float) $l->counted_qty - $expected);
                    @endphp
                    <tr data-line="{{ $l->id }}" data-sku="{{ $l->sku }}" data-name="{{ strtolower($l->name) }}" data-expected="{{ $expected }}" data-cost="{{ (float) $l->unit_cost }}">
                        <td>{{ $l->sku }}</td>
                        <td>{{ $l->name }}</td>
                        <td class="text-right">{{ $q($st->status === 'applied' ? $l->system_qty : $expected) }}</td>
                        <td>
                            @if ($counting)
                                <input type="number" step="any" min="0" class="form-control input-sm count-input" value="{{ $countedStr }}" data-orig="{{ $countedStr }}">
                            @else
                                {{ $countedStr }}
                            @endif
                        </td>
                        <td class="text-right diff {{ $diff !== null && $diff < 0 ? 'text-red' : '' }} {{ $diff !== null && $diff > 0 ? 'text-green' : '' }}">
                            @if ($diff !== null){{ $diff > 0 ? '+' : '' }}{{ $q($diff) }}@endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($counting)
    <div class="box box-danger">
        <div class="box-header with-border"><h3 class="box-title">Finish this stocktake</h3></div>
        <div class="box-body">
            <p>
                Applying sets stock to the quantities you counted, compared with what the system holds <b>at this moment</b>.
                Do it right after counting. Sales made while you were counting would otherwise be counted twice.
                Items you did not count are left unchanged.
            </p>
            <ul>
                <li>Shortages are booked as a <b>stock adjustment</b> (shown in Stock Adjustments).</li>
                <li>Overages are added as <b>opening stock</b> entries at the product's purchase price.</li>
            </ul>
            <form id="applyForm" method="POST" action="{{ url('/stocktakes/'.$st->id.'/apply') }}" style="display:inline">
                @csrf
                <label style="margin-right:12px"><input type="checkbox" name="book_overages" value="1" checked> Also add overages to stock</label>
                <button type="submit" class="btn btn-danger">Apply stocktake</button>
            </form>
            <form method="POST" action="{{ url('/stocktakes/'.$st->id.'/cancel') }}" style="display:inline" onsubmit="return confirm('Cancel this stocktake? No stock will be changed.');">
                @csrf
                <button class="btn btn-default">Cancel stocktake</button>
            </form>
        </div>
    </div>
    @endif
</section>
@endsection

@section('javascript')
@if ($counting)
<script>
$(function () {
    function fmt(n) { return (Math.round(n * 10000) / 10000).toString(); }
    function money(n) { return n.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }

    function recalc() {
        var counted = 0, shortQ = 0, overQ = 0, shortV = 0, overV = 0;
        $('#lines tbody tr').each(function () {
            var $r = $(this), v = $r.find('.count-input').val(), $d = $r.find('.diff');
            $d.removeClass('text-red text-green');
            if (v === '' || v === undefined) { $d.text(''); return; }
            counted++;
            var diff = parseFloat(v) - parseFloat($r.attr('data-expected'));
            var cost = parseFloat($r.attr('data-cost')) || 0;
            $d.text((diff > 0 ? '+' : '') + fmt(diff));
            if (diff < 0) { $d.addClass('text-red'); shortQ += -diff; shortV += -diff * cost; }
            else if (diff > 0) { $d.addClass('text-green'); overQ += diff; overV += diff * cost; }
        });
        $('#sum-counted').text(counted);
        $('#sum-short').text(fmt(shortQ)); $('#sum-short-value').text(money(shortV));
        $('#sum-over').text(fmt(overQ)); $('#sum-over-value').text(money(overV));
        $('#sum-net').text(money(overV - shortV));
    }

    function changedCounts() {
        var changed = {};
        $('#lines tbody tr').each(function () {
            var $in = $(this).find('.count-input');
            if ($in.val() !== $in.attr('data-orig')) { changed[$(this).attr('data-line')] = $in.val(); }
        });
        return changed;
    }

    $(document).on('input', '.count-input', recalc);

    $('#saveBtn').on('click', function () {
        var changed = changedCounts();
        if ($.isEmptyObject(changed)) { alert('No changes to save.'); return; }
        $('#counts_json').val(JSON.stringify(changed));
        $('#saveForm').trigger('submit');
    });

    $('#applyForm').on('submit', function (e) {
        if (!$.isEmptyObject(changedCounts())) {
            alert('You have unsaved counts. Click "Save counts" first.');
            e.preventDefault();
            return;
        }
        if (!confirm('Apply this stocktake? Stock will be changed to match your counts.')) { e.preventDefault(); }
    });

    $('#filter').on('keyup', function () {
        var t = $.trim($(this).val()).toLowerCase();
        $('#lines tbody tr').each(function () {
            var hay = String($(this).attr('data-name')) + ' ' + String($(this).attr('data-sku')).toLowerCase();
            $(this).toggle(hay.indexOf(t) !== -1);
        });
    });

    $('#scan').on('keydown', function (e) {
        if (e.key !== 'Enter') { return; }
        e.preventDefault();
        var code = $.trim($(this).val()).toLowerCase();
        $(this).val('');
        if (!code) { return; }
        var $row = $('#lines tbody tr').filter(function () {
            return String($(this).attr('data-sku')).toLowerCase() === code;
        }).first();
        var $msg = $('#scan-msg');
        if (!$row.length) { $msg.text('Not found: ' + code).attr('class', 'text-red'); return; }
        var $in = $row.find('.count-input');
        if ($('#scan-mode').is(':checked')) {
            var cur = parseFloat($in.val());
            $in.val(isNaN(cur) ? 1 : cur + 1);
        } else {
            $in.focus().select();
        }
        $msg.text($row.children().eq(1).text() + ' = ' + $in.val()).attr('class', 'text-green');
        $row[0].scrollIntoView({block: 'center'});
        $row.addClass('info'); setTimeout(function () { $row.removeClass('info'); }, 800);
        recalc();
    });
});
</script>
@endif
@endsection
