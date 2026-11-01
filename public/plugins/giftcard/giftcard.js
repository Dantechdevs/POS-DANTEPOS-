/* Gift Card payment helper for the POS payment modal. */
(function ($) {
    'use strict';

    $(document).on('input', '[id^="giftcard_code_"]', function () {
        this.value = this.value.toUpperCase();
    });

    $(document).on('click', '.giftcard-check-btn', function () {
        var i = $(this).data('row-index'), $btn = $(this);
        var code = $.trim($('#giftcard_code_' + i).val());
        var $s = $('#giftcard_status_' + i);
        var base = (window.MPESA_CONFIG && window.MPESA_CONFIG.base_path) || '';

        if (!code) { toastr.warning('Enter the gift card code.'); return; }

        $btn.prop('disabled', true);
        $s.removeClass('hide alert-danger alert-success').addClass('alert-info').text('Checking...');

        $.get(base + '/gift-cards/check', { code: code })
            .done(function (r) {
                if (!r.success) { $s.removeClass('alert-info').addClass('alert-danger').text(r.message); return; }

                $s.removeClass('alert-info').addClass('alert-success')
                  .text('Balance: KSh ' + r.balance.toFixed(2) + (r.expires ? ' (expires ' + r.expires + ')' : ''));

                var $amt = $('#amount_' + i);
                var cur = typeof __read_number === 'function' ? __read_number($amt) : (parseFloat(($amt.val() || '').replace(/,/g, '')) || 0);
                if (cur > r.balance) {
                    if (typeof __write_number === 'function') { __write_number($amt, r.balance); } else { $amt.val(r.balance); }
                    $amt.trigger('input').trigger('change');
                    $s.append(' Amount set to the card balance. Add another payment for the rest.');
                }
            })
            .fail(function () { $s.removeClass('alert-info').addClass('alert-danger').text('Could not check the card. Try again.'); })
            .always(function () { $btn.prop('disabled', false); });
    });
})(jQuery);
