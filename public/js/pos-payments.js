/* Till payment helpers: pinned "amount given + change/balance" strip, bigger M-Pesa button, "pay the rest" buttons.
   Display only. It never changes how a sale is calculated or saved. */
(function ($) {
    'use strict';
    if (!/\/pos\/(create|\d+\/edit)\/?$/.test(location.pathname)) { return; }

    var CSS = '' +
    '.pos-express-finalize[data-pay_method="mpesa"]{background:#0a7d3b!important;color:#fff!important;border-radius:8px;' +
    'min-width:8.5rem;height:54px;display:inline-flex!important;flex-direction:row!important;align-items:center;justify-content:center;gap:8px;' +
    'font-weight:700;font-size:16px;padding:6px 12px;margin:0 6px;box-shadow:0 2px 0 rgba(0,0,0,.15);white-space:nowrap}' +
    '.pos-express-finalize[data-pay_method="mpesa"]:hover{background:#086a32!important}' +
    '.pos-express-finalize[data-pay_method="mpesa"] img{background:#fff;border-radius:4px;padding:2px 6px;height:26px!important}' +
    '#dp-pay-strip{float:left;display:flex;gap:8px;align-items:center;margin:0}' +
    '#dp-pay-strip span{display:inline-block;padding:7px 14px;border-radius:6px;font-size:16px;font-weight:700;line-height:1.2;color:#fff;white-space:nowrap}' +
    '#dp-pay-strip .dp-given{background:#334155}' +
    '#dp-pay-strip .dp-status{background:#64748b}' +
    '#dp-pay-strip .dp-status.change{background:#16a34a}#dp-pay-strip .dp-status.due{background:#dc2626}#dp-pay-strip .dp-status.ok{background:#0f766e}' +
    '#dp-rest{margin-top:10px}#dp-rest .btn{font-weight:700;margin-right:8px;border-radius:6px}';

    function num(t) { return parseFloat(String(t || '').replace(/[^0-9.]/g, '')) || 0; }
    function say(t) { if (window.toastr) { toastr.warning(t); } else { alert(t); } }
    function $modal() { return $('#payment_rows_div').closest('.modal'); }

    function ensureUi() {
        if (!$('#dp-css').length) { $('head').append('<style id="dp-css">' + CSS + '</style>'); }

        $('.pos-express-finalize[data-pay_method="mpesa"]').each(function () {
            if (!$(this).find('.dp-mp').length) { $(this).append('<span class="dp-mp">M-PESA</span>'); }
        });

        var $m = $modal();
        if ($m.length && !$('#dp-pay-strip').length) {
            $m.find('.modal-footer').first().prepend(
                '<div id="dp-pay-strip"><span class="dp-given">Given: 0.00</span><span class="dp-status">Enter the amount</span></div>'
            );
        }
        if ($('#add-payment-row').length && !$('#dp-rest').length) {
            $('#add-payment-row').after(
                '<div id="dp-rest">' +
                '<button type="button" class="btn btn-success" id="dp-rest-mpesa">Rest by M-Pesa</button>' +
                '<button type="button" class="btn btn-default" id="dp-rest-cash">Rest by Cash</button>' +
                '<small class="text-muted">Type the first amount, then press one for the balance.</small></div>'
            );
        }
    }

    function refreshStrip() {
        var $m = $modal(), $s = $('#dp-pay-strip');
        if (!$m.length || !$s.length || !$m.is(':visible')) { return; }
        var changeTxt = $m.find('span.change_return_span').first().text();
        var dueTxt = $m.find('span.balance_due').first().text();
        var givenTxt = $m.find('span.total_paying').first().text();
        var change = num(changeTxt), due = num(dueTxt), paying = num(givenTxt);

        $s.find('.dp-given').text('Given: ' + (givenTxt || '0.00'));
        var $st = $s.find('.dp-status');
        if (change > 0.04) { $st.attr('class', 'dp-status change').text('Change: ' + changeTxt); }
        else if (due > 0.04) { $st.attr('class', 'dp-status due').text('Still due: ' + dueTxt); }
        else if (paying > 0) { $st.attr('class', 'dp-status ok').text('Paid in full'); }
        else { $st.attr('class', 'dp-status').text('Enter the amount'); }
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
