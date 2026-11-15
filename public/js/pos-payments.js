/* Till payment helpers: pinned change/balance strip, bigger M-Pesa button, "pay the rest" buttons.
   Display only. It never changes how a sale is calculated or saved. */
(function ($) {
    'use strict';
    if (!/\/pos\/(create|\d+\/edit)\/?$/.test(location.pathname)) { return; }

    var CSS = '' +
    '.pos-express-finalize[data-pay_method="mpesa"]{background:#0a7d3b!important;color:#fff!important;border-radius:8px;' +
    'min-width:8.5rem;height:54px;display:inline-flex!important;flex-direction:row!important;align-items:center;justify-content:center;gap:8px;' +
    'font-weight:700;font-size:16px;padding:6px 12px;margin:0 6px;box-shadow:0 2px 0 rgba(0,0,0,.15)}' +
    '.pos-express-finalize[data-pay_method="mpesa"]:hover{background:#086a32!important}' +
    '.pos-express-finalize[data-pay_method="mpesa"] img{background:#fff;border-radius:4px;padding:2px 6px;height:26px!important}' +
    '#dp-pay-strip{float:left;margin:0;padding:8px 16px;border-radius:8px;font-size:22px;font-weight:700;line-height:1.2;color:#fff;background:#64748b}' +
    '#dp-pay-strip.change{background:#16a34a}#dp-pay-strip.due{background:#dc2626}#dp-pay-strip.ok{background:#0f766e}' +
    '#dp-rest{margin-top:10px}#dp-rest .btn{font-weight:700;margin-right:8px;border-radius:6px}';

    function num(t) { return parseFloat(String(t || '').replace(/[^0-9.]/g, '')) || 0; }
    function say(t) { if (window.toastr) { toastr.warning(t); } else { alert(t); } }
    function $modal() { return $('#payment_rows_div').closest('.modal'); }

    function ensureUi() {
        if (!$('#dp-css').length) { $('head').append('<style id="dp-css">' + CSS + '</style>'); }

        // M-Pesa express button: add a text label next to the logo
        $('.pos-express-finalize[data-pay_method="mpesa"]').each(function () {
            if (!$(this).find('.dp-mp').length) { $(this).append('<span class="dp-mp">M-PESA</span>'); }
        });

        var $m = $modal();
        if ($m.length && !$('#dp-pay-strip').length) {
            $m.find('.modal-footer').first().prepend('<div id="dp-pay-strip">Enter the amount received</div>');
        }
        if ($('#add-payment-row').length && !$('#dp-rest').length) {
            $('#add-payment-row').after(
                '<div id="dp-rest">' +
                '<button type="button" class="btn btn-success" id="dp-rest-mpesa">Rest by M-Pesa</button>' +
                '<button type="button" class="btn btn-default" id="dp-rest-cash">Rest by Cash</button>' +
                '<small class="text-muted">Type the first amount above, then press one of these for the remaining balance.</small></div>'
            );
        }
    }

    function refreshStrip() {
        var $m = $modal(), $s = $('#dp-pay-strip');
        if (!$m.length || !$s.length || !$m.is(':visible')) { return; }
        var change = num($m.find('span.change_return_span').first().text());
        var due = num($m.find('span.balance_due').first().text());
        var paying = num($m.find('span.total_paying').first().text());
        var changeTxt = $m.find('span.change_return_span').first().text();
        var dueTxt = $m.find('span.balance_due').first().text();
        if (change > 0.04) { $s.attr('class', 'change').text('Change to give: ' + changeTxt); }
        else if (due > 0.04) { $s.attr('class', 'due').text('Still due: ' + dueTxt); }
        else if (paying > 0) { $s.attr('class', 'ok').text('Paid in full, no change'); }
        else { $s.attr('class', '').text('Enter the amount received'); }
    }

    function payRest(method) {
        var due = __read_number($('#in_balance_due'));
        if (!(due > 0.04)) { say('Nothing left to pay.'); return; }
        var $div = $('#payment_rows_div'), before = $div.find('.payment-amount').length, tries = 0;
        $('#add-payment-row').trigger('click');
        var t = setInterval(function () {
            var $a = $div.find('.payment-amount');
            if ($a.length > before) {
                clearInterval(t);
                var $last = $a.last(), $row = $last.closest('.payment_row');
                if (!$row.length) { $row = $last.closest('.box, .row'); }
                var $sel = $row.find('select.payment_types_dropdown').first();
                $sel.val(method).trigger('change');
                if ($sel.val() !== method) { say('That payment method is not available here.'); return; }
                __write_number($last, due);
                $last.trigger('change');
                if (typeof calculate_balance_due === 'function') { calculate_balance_due(); }
            } else if (++tries > 50) {
                clearInterval(t);
                say('Could not add the payment row. Press Add Payment Row yourself.');
            }
        }, 100);
    }

    $(document).on('click', '#dp-rest-mpesa', function () { payRest('mpesa'); });
    $(document).on('click', '#dp-rest-cash', function () { payRest('cash'); });

    setInterval(ensureUi, 700);
    setInterval(refreshStrip, 250);
    $(ensureUi);
})(jQuery);
