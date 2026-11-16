/* Till: Cash button asks "Amount received" and shows the change, then runs the normal checkout. */
(function ($) {
    'use strict';
    if (!/\/pos\/(create|\d+\/edit)\/?$/.test(location.pathname)) { return; }

    var bypass = false, btn = null;
    var CSS = '' +
    '#dp-cash{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,.6);z-index:99999;display:none;align-items:center;justify-content:center}' +
    '#dp-cash .box{background:#fff;border-radius:14px;width:440px;max-width:94%;padding:22px;box-shadow:0 20px 50px rgba(0,0,0,.3)}' +
    '#dp-cash h3{margin:0 0 4px}#dp-cash .tot{font-size:28px;font-weight:800;margin-bottom:12px}' +
    '#dp-cash input{width:100%;font-size:30px;padding:10px 14px;border:2px solid #cbd5e1;border-radius:10px}' +
    '#dp-cash input:focus{border-color:#16a34a;outline:0}' +
    '#dp-cash .quick{margin:10px 0}#dp-cash .quick button{margin:0 6px 6px 0;font-weight:700}' +
    '#dp-cash .chg{font-size:30px;font-weight:800;padding:12px;border-radius:10px;text-align:center;background:#f1f5f9;color:#475569}' +
    '#dp-cash .chg.ok{background:#16a34a;color:#fff}#dp-cash .chg.short{background:#dc2626;color:#fff}' +
    '#dp-cash .acts{margin-top:14px;display:flex;gap:10px}#dp-cash .acts button{flex:1;font-weight:700;padding:12px;font-size:16px}' +
    '.pos-express-finalize[data-pay_method="mpesa"] .dp-mp{white-space:nowrap}' +
    '#pos-finalize{white-space:nowrap}';

    function total() { return __read_number($('#final_total_input')); }
    function fmt(n) { return __currency_trans_from_en(n, true); }

    function build() {
        if ($('#dp-cash').length) { return; }
        $('head').append('<style>' + CSS + '</style>');
        $('body').append(
            '<div id="dp-cash"><div class="box">' +
            '<h3>Cash payment</h3><div class="tot">Total: <span id="dp-c-total"></span></div>' +
            '<label>Amount received</label><input type="number" id="dp-c-in" min="0" step="any" inputmode="decimal" autocomplete="off">' +
            '<div class="quick" id="dp-c-quick"></div>' +
            '<div class="chg" id="dp-c-chg">Type the amount received</div>' +
            '<div class="acts"><button type="button" class="btn btn-default" id="dp-c-cancel">Cancel</button>' +
            '<button type="button" class="btn btn-success" id="dp-c-ok">Complete sale</button></div>' +
            '</div></div>'
        );
    }

    function update() {
        var t = total(), r = parseFloat($('#dp-c-in').val()), $c = $('#dp-c-chg');
        if (isNaN(r)) { $c.attr('class', 'chg').text('Type the amount received'); return; }
        if (r + 0.04 < t) { $c.attr('class', 'chg short').text('Short by ' + fmt(t - r)); }
        else { $c.attr('class', 'chg ok').text('Change to give: ' + fmt(Math.max(0, r - t))); }
    }

    function openBox(b) {
        build(); btn = b;
        var t = total();
        $('#dp-c-total').text(fmt(t));
        var $q = $('#dp-c-quick').empty();
        $q.append($('<button type="button" class="btn btn-info btn-sm">Exact</button>').data('v', t));
        [50, 100, 200, 500, 1000, 2000, 5000].forEach(function (n) {
            if (n >= t) { $q.append($('<button type="button" class="btn btn-default btn-sm"></button>').text(n).data('v', n)); }
        });
        $('#dp-c-in').val('');
        update();
        $('#dp-cash').css('display', 'flex');
        setTimeout(function () { $('#dp-c-in').focus(); }, 50);
    }

    function closeBox() { $('#dp-cash').hide(); }

    function complete() {
        var t = total(), r = parseFloat($('#dp-c-in').val());
        if (isNaN(r)) { r = t; }
        if (r + 0.04 < t) {
            if (window.toastr) { toastr.warning('Amount received is less than the total. Use Multiple Pay for part payments.'); }
            return;
        }
        var $first = $('#payment_rows_div').find('.payment-amount').first();
        __write_number($first, r);
        $first.trigger('change');
        if (typeof calculate_balance_due === 'function') { calculate_balance_due(); }
        closeBox();
        bypass = true;
        try { btn.click(); } finally { bypass = false; }
    }

    document.addEventListener('click', function (e) {
        var b = e.target.closest ? e.target.closest('button.pos-express-finalize[data-pay_method="cash"]') : null;
        if (!b || bypass) { return; }
        e.stopImmediatePropagation();
        e.preventDefault();
        if ($('table#pos_table tbody').find('.product_row').length <= 0) {
            if (window.toastr) { toastr.warning(LANG.no_products_added); }
            return;
        }
        openBox(b);
    }, true);

    $(document).on('input', '#dp-c-in', update);
    $(document).on('click', '#dp-c-quick button', function () { $('#dp-c-in').val($(this).data('v')); update(); $('#dp-c-in').focus(); });
    $(document).on('click', '#dp-c-ok', complete);
    $(document).on('click', '#dp-c-cancel', closeBox);
    $(document).on('keydown', '#dp-c-in', function (e) {
        if (e.which === 13) { e.preventDefault(); complete(); }
        if (e.which === 27) { closeBox(); }
    });
})(jQuery);
