@extends('layouts.app')
@section('title', 'Smart Scanner')

@section('content')

<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">Smart Scanner
        @if($location)<small>{{ $location->name }}</small>@endif
    </h1>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">

            @component('components.widget', ['class' => 'box-primary'])
                <div class="form-group" style="margin-bottom:5px;">
                    <label for="scan_code" style="font-size:16px;">Scan the barcode</label>
                    <input type="text" id="scan_code" class="form-control input-lg" autocomplete="off" autofocus
                           placeholder="Scan the pack, or type the code and press Enter">
                    <p class="help-block" style="margin-bottom:0;">New product: check the name, type the prices and stock, then press Enter. Product already in the system: type how many you are adding.</p>
                </div>
                <div id="scan_message" class="hide" style="margin-top:10px;"></div>
            @endcomponent

            {{-- New product --}}
            <div id="panel_new" class="hide">
                @component('components.widget', ['class' => 'box-success', 'title' => 'New product'])
                    <div class="row">
                        <div class="col-sm-3 text-center">
                            <img id="new_image" src="" alt="" class="img-thumbnail hide" style="max-height:150px;max-width:100%;">
                            <p id="new_image_note" class="text-muted" style="margin-top:8px;">No picture</p>
                        </div>
                        <div class="col-sm-9">
                            <div class="form-group">
                                <label for="new_name">Product name</label>
                                <input type="text" id="new_name" class="form-control" maxlength="255" autocomplete="off">
                                <p class="help-block" id="new_hint" style="margin-bottom:0;"></p>
                            </div>
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="new_unit">Unit</label>
                                        <select id="new_unit" class="form-control">
                                            @foreach($units as $unit)
                                                <option value="{{ $unit->id }}" data-decimal="{{ $unit->allow_decimal ? 1 : 0 }}" @if($unit->id == $default_unit_id) selected @endif>{{ $unit->actual_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="new_category">Category <small class="text-muted">(optional)</small></label>
                                        <select id="new_category" class="form-control">
                                            <option value="">None</option>
                                            @foreach($categories as $id => $name)
                                                <option value="{{ $id }}">{{ $name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label for="new_buying">Buying price</label>
                                        <input type="number" id="new_buying" class="form-control" min="0" step="any" inputmode="decimal">
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label for="new_selling">Selling price</label>
                                        <input type="number" id="new_selling" class="form-control" min="0" step="any" inputmode="decimal">
                                        <p class="help-block" id="new_margin" style="margin-bottom:0;"></p>
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label for="new_quantity">Stock you have</label>
                                        <input type="number" id="new_quantity" class="form-control" min="0" step="any" inputmode="decimal">
                                    </div>
                                </div>
                            </div>
                            <button type="button" id="btn_save_new" class="btn btn-success btn-lg">Save product</button>
                            <button type="button" class="btn btn-default btn-lg btn-cancel">Cancel</button>
                        </div>
                    </div>
                @endcomponent
            </div>

            {{-- Product already in the system --}}
            <div id="panel_own" class="hide">
                @component('components.widget', ['class' => 'box-info', 'title' => 'Already in your system'])
                    <div class="row">
                        <div class="col-sm-3 text-center">
                            <img id="own_image" src="" alt="" class="img-thumbnail hide" style="max-height:150px;max-width:100%;">
                        </div>
                        <div class="col-sm-9">
                            <h4 id="own_name" style="margin-top:0;"></h4>
                            <p>In stock now: <strong id="own_stock"></strong>
                                &nbsp;|&nbsp; Selling price: <strong id="own_selling"></strong></p>
                            <div id="own_blocked" class="alert alert-warning hide"></div>
                            <div id="own_fields">
                                <div class="row">
                                    <div class="col-sm-4">
                                        <div class="form-group">
                                            <label for="own_quantity">Add how many?</label>
                                            <input type="number" id="own_quantity" class="form-control" min="0" step="any" inputmode="decimal">
                                        </div>
                                    </div>
                                    <div class="col-sm-4">
                                        <div class="form-group">
                                            <label for="own_buying">Buying price</label>
                                            <input type="number" id="own_buying" class="form-control" min="0" step="any" inputmode="decimal">
                                        </div>
                                    </div>
                                </div>
                                <button type="button" id="btn_add_stock" class="btn btn-info btn-lg">Add stock</button>
                            </div>
                            <button type="button" class="btn btn-default btn-lg btn-cancel">Cancel</button>
                        </div>
                    </div>
                @endcomponent
            </div>

            {{-- Done in this session --}}
            <div id="panel_recent" class="hide">
                @component('components.widget', ['class' => 'box-default', 'title' => 'Done in this session'])
                    <table class="table table-condensed" style="margin-bottom:0;">
                        <thead><tr><th>Product</th><th>Barcode</th><th>Quantity</th><th>What happened</th></tr></thead>
                        <tbody id="recent_rows"></tbody>
                    </table>
                @endcomponent
            </div>

        </div>
    </div>
</section>

@endsection

@section('javascript')
<script type="text/javascript">
$(function () {
    var URL_LOOKUP = "{{ url('/products/smart-scanner/lookup') }}";
    var URL_SAVE = "{{ url('/products/smart-scanner/save') }}";
    var DEFAULT_UNIT = "{{ $default_unit_id }}";
    var busy = false;
    var current = null; // the barcode being worked on

    function focusScan() { $('#scan_code').val('').focus(); }

    function message(type, text) {
        $('#scan_message').removeClass('hide').empty()
            .append($('<div class="alert alert-' + type + '" style="margin-bottom:0;"></div>').text(text));
    }

    function clearMessage() { $('#scan_message').addClass('hide').empty(); }

    function resetPanels() {
        $('#panel_new, #panel_own').addClass('hide');
        current = null;
    }

    function setImage($img, url) {
        $img.off('error').addClass('hide').attr('src', '');
        if (url) {
            $img.on('error', function () { $img.addClass('hide'); $('#new_image_note').text('No picture'); })
                .attr('src', url).removeClass('hide');
        }
    }

    function updateMargin() {
        var buy = parseFloat($('#new_buying').val()), sell = parseFloat($('#new_selling').val());
        var $m = $('#new_margin').text('').removeClass('text-danger text-muted');
        if (buy > 0 && sell > 0) {
            var pct = ((sell - buy) / buy) * 100;
            $m.text('Profit: ' + pct.toFixed(1) + '%').addClass(pct < 0 ? 'text-danger' : 'text-muted');
        }
    }

    function applyUnitStep() {
        var decimal = $('#new_unit option:selected').data('decimal') == 1;
        $('#new_quantity').attr('step', decimal ? 'any' : '1');
    }

    function showNew(r) {
        $('#new_name').val(r.name || '');
        $('#new_unit').val(DEFAULT_UNIT);
        $('#new_buying, #new_selling, #new_quantity').val('');
        $('#new_margin').text('');
        applyUnitStep();

        var hint = [];
        if (r.brand) { hint.push('Brand: ' + r.brand); }
        if (r.quantity) { hint.push('Size: ' + r.quantity); }
        $('#new_hint').text(hint.join('  |  '));

        $('#new_image').data('imageUrl', r.image_url || '');
        $('#new_image_note').text(r.image_url ? 'Picture from the online database' : 'No picture');
        setImage($('#new_image'), r.image_url);

        $('#panel_new').removeClass('hide');
        if (r.status === 'found') {
            message('success', 'Found online: ' + r.name + '. Check the name, then enter the prices.');
            $('#new_buying').focus();
        } else {
            message(r.status === 'unavailable' ? 'warning' : 'info', r.message);
            $('#new_name').focus();
        }
    }

    function showOwn(r) {
        $('#own_name').text(r.name);
        $('#own_stock').text(r.stock);
        $('#own_selling').text(r.selling_price);
        $('#own_quantity').val('');
        $('#own_buying').val(r.buying_price);
        setImage($('#own_image'), r.image_url);
        if (r.can_add_stock) {
            $('#own_blocked').addClass('hide');
            $('#own_fields').removeClass('hide');
        } else {
            $('#own_fields').addClass('hide');
            $('#own_blocked').removeClass('hide').text('Stock for this product cannot be added here. Use Purchases.');
        }
        $('#panel_own').removeClass('hide');
        message('info', 'Already registered. Nothing new is created. Type how many you are adding.');
        if (r.can_add_stock) { $('#own_quantity').focus(); }
    }

    function lookup(code) {
        if (!code || busy) { return; }
        busy = true;
        resetPanels();
        message('info', 'Looking up ' + code + ' ...');
        $.ajax({ url: URL_LOOKUP, type: 'POST', data: { code: code }, dataType: 'json' })
            .done(function (r) {
                current = r.code || code;
                if (r.status === 'own') { showOwn(r); }
                else if (r.status === 'found' || r.status === 'not_found' || r.status === 'unavailable') { showNew(r); }
                else { message('warning', r.message || 'Scan again.'); focusScan(); }
            })
            .fail(function () {
                message('danger', 'The lookup failed. Check the connection and scan again.');
                focusScan();
            })
            .always(function () { busy = false; });
    }

    function addRecent(item) {
        var $tr = $('<tr></tr>')
            .append($('<td></td>').text(item.name))
            .append($('<td></td>').text(item.code))
            .append($('<td></td>').text(item.quantity))
            .append($('<td></td>').text(item.action));
        $('#recent_rows').prepend($tr);
        $('#panel_recent').removeClass('hide');
    }

    function save(payload, $btn) {
        if (busy) { return; }
        busy = true;
        $btn.prop('disabled', true);
        $.ajax({ url: URL_SAVE, type: 'POST', data: payload, dataType: 'json' })
            .done(function (r) {
                addRecent(r.item);
                resetPanels();
                message('success', r.message + '. Scan the next product.');
                focusScan();
            })
            .fail(function (xhr) {
                var text = (xhr.responseJSON && xhr.responseJSON.message) || 'Could not save. Nothing was changed.';
                message('danger', text);
            })
            .always(function () { busy = false; $btn.prop('disabled', false); });
    }

    function saveNew() {
        var name = $.trim($('#new_name').val());
        var buy = parseFloat($('#new_buying').val());
        var sell = parseFloat($('#new_selling').val());
        var qty = $('#new_quantity').val() === '' ? 0 : parseFloat($('#new_quantity').val());

        if (!name) { message('danger', 'Type the product name.'); $('#new_name').focus(); return; }
        if (isNaN(buy) || buy < 0) { message('danger', 'Type the buying price.'); $('#new_buying').focus(); return; }
        if (isNaN(sell) || sell <= 0) { message('danger', 'Type the selling price.'); $('#new_selling').focus(); return; }
        if (isNaN(qty) || qty < 0) { message('danger', 'Stock cannot be negative.'); $('#new_quantity').focus(); return; }
        if (sell < buy && !window.confirm('The selling price is lower than the buying price. Save anyway?')) { return; }

        save({
            mode: 'register',
            code: current,
            name: name,
            unit_id: $('#new_unit').val(),
            category_id: $('#new_category').val(),
            buying_price: buy,
            selling_price: sell,
            quantity: qty,
            image_url: $('#new_image').data('imageUrl') || ''
        }, $('#btn_save_new'));
    }

    function saveStock() {
        var qty = parseFloat($('#own_quantity').val());
        var buy = parseFloat($('#own_buying').val());
        if (isNaN(qty) || qty <= 0) { message('danger', 'Type how many you are adding.'); $('#own_quantity').focus(); return; }
        if (isNaN(buy) || buy < 0) { message('danger', 'Type the buying price.'); $('#own_buying').focus(); return; }
        save({ mode: 'add_stock', code: current, quantity: qty, buying_price: buy }, $('#btn_add_stock'));
    }

    // Wiring
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    $('#scan_code').on('keydown', function (e) {
        if (e.which === 13) { e.preventDefault(); lookup($.trim($(this).val())); }
    });

    $('#new_name').on('keydown', function (e) { if (e.which === 13) { e.preventDefault(); $('#new_buying').focus(); } });
    $('#new_buying').on('keydown', function (e) { if (e.which === 13) { e.preventDefault(); $('#new_selling').focus(); } });
    $('#new_selling').on('keydown', function (e) { if (e.which === 13) { e.preventDefault(); $('#new_quantity').focus(); } });
    $('#new_quantity').on('keydown', function (e) { if (e.which === 13) { e.preventDefault(); saveNew(); } });
    $('#own_quantity, #own_buying').on('keydown', function (e) { if (e.which === 13) { e.preventDefault(); saveStock(); } });

    $('#new_buying, #new_selling').on('input', updateMargin);
    $('#new_unit').on('change', applyUnitStep);
    $('#btn_save_new').on('click', saveNew);
    $('#btn_add_stock').on('click', saveStock);
    $('.btn-cancel').on('click', function () { resetPanels(); clearMessage(); focusScan(); });

    focusScan();
});
</script>
@endsection
