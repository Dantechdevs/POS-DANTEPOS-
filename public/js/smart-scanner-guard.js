/* Smart Scanner guard: scan box selects its old text, and absurd stock is refused. */
(function ($) {
    'use strict';
    if (!/\/products\/smart-scanner\/?$/.test(location.pathname)) { return; }
    var MAX = 100000;

    $(document).on('focus click', '#scan_code', function () { this.select(); });

    function bad() {
        var bad = false;
        $('#new_quantity:visible, #own_quantity:visible').each(function () {
            if (parseFloat($(this).val()) > MAX) { bad = $(this); }
        });
        return bad;
    }
    function block(e, $f) {
        e.stopImmediatePropagation(); e.preventDefault();
        alert('That stock number is too big. A barcode was probably typed into the stock box. Clear it and type the real quantity.');
        $f.val('').focus();
    }
    document.addEventListener('click', function (e) {
        if (!e.target.closest || !e.target.closest('#btn_save_new, #btn_add_stock')) { return; }
        var $f = bad(); if ($f) { block(e, $f); }
    }, true);
    document.addEventListener('keydown', function (e) {
        if (e.which !== 13 || (e.target.id !== 'new_quantity' && e.target.id !== 'own_quantity')) { return; }
        var $f = bad(); if ($f) { block(e, $f); }
    }, true);
})(jQuery);
