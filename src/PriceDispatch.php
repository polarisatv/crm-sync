<?php

namespace CrmSync;

if (!defined('ABSPATH')) {
    exit;
}

class PriceDispatch
{
    private const HOOK = 'crm_sync_update_price';
    private const GROUP = 'crm-sync';

    /**
     * Queries all published products with a SKU and enqueues one async
     * crm_sync_update_price action per product (deduplicates pending jobs).
     */
    public function dispatch(): void
    {
        $product_ids = wc_get_products([
            'limit' => -1,
            'status' => 'publish',
            'return' => 'ids',
        ]);

        $dispatched = 0;

        foreach ($product_ids as $product_id) {
            $sku = get_post_meta($product_id, '_sku', true);
            if (empty($sku)) {
                continue;
            }

//            @todo Temp filter, disable later on
//            if ($sku !== '7082037') {
//                continue;
//            }

            $args = ['product_id' => $product_id, 'sku' => $sku];
            if (as_has_scheduled_action(self::HOOK, $args, self::GROUP)) {
                continue;
            }

            as_enqueue_async_action(self::HOOK, $args, self::GROUP);
            $dispatched++;
        }

        error_log("crm-sync: dispatched price-update jobs for $dispatched / " . count($product_ids) . ' products.');
    }
}
