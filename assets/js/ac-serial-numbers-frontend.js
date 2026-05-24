(function ($) {
    'use strict';

    $(document).on('click', '.ac-sn-see-license', function (e) {
        e.preventDefault();

        var $btn = $(this);
        var serialId = $btn.data('serial-id');
        var orderId = $btn.data('order-id');
        var productId = $btn.data('product-id');
        var productTitle = $btn.data('product-title');
        var orderKey = $btn.data('order-key');
        var nonce = $btn.data('nonce');

        $btn.prop('disabled', true).text('Loading...');

        wp.ajax.send('ac_serial_numbers_view_license', {
            data: {
                serial_id: serialId,
                order_id: orderId,
                product_id: productId,
                product_title: productTitle,
                order_key: orderKey,
                nonce: nonce
            },
            success: function (res) {
                if (res.status === 'no_serials') {
                    $btn.replaceWith(
                        '<p style="margin:0 0 8px 0;"><em>No license keys assigned yet.</em></p>' +
                        '<button class="ac-sn-see-license button" ' +
                        'data-serial-id="0" ' +
                        'data-order-id="' + orderId + '" ' +
                        'data-product-id="' + productId + '" ' +
                        'data-product-title="' + productTitle + '" ' +
                        'data-order-key="' + orderKey + '" ' +
                        'data-nonce="' + nonce + '">' +
                        'Check Again</button>'
                    );
                    return;
                }
                if (res.status === 'processing') {
                    $btn.replaceWith(
                        '<p style="margin:0 0 8px 0;"><em>Order Is Processing.</em></p>' +
                        '<button class="ac-sn-see-license button" ' +
                        'data-serial-id="' + serialId + '" ' +
                        'data-order-id="' + orderId + '" ' +
                        'data-product-id="' + productId + '" ' +
                        'data-product-title="' + productTitle + '" ' +
                        'data-order-key="' + orderKey + '" ' +
                        'data-nonce="' + nonce + '">' +
                        'Check Again</button>'
                    );
                    return;
                }
                if (res.key) {
                    $btn.replaceWith('<code>' + res.key + '</code>');
                }
            },
            error: function (res) {
                $btn.prop('disabled', false).text('See Your License Key');
                if (res && res.message) {
                    alert(res.message);
                }
            }
        });
    });

})(jQuery);
