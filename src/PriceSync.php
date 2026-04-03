<?php

namespace CrmSync;

use Exception;
use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

class PriceSync
{

    public function __construct(
        private readonly string $apiKey,
    )
    {
    }

    /**
     * Fetch price for a single SKU from the external API and update the product.
     */
    public function updateProductPrice(int $productId, string $sku): void
    {
        try {
            $price = $this->fetchPrice($sku);
        } catch (Exception $e) {
            error_log("crm-sync: fetchPrice failed for SKU \"$sku\" (product $productId): " . $e->getMessage());
            throw $e; // re-throw so Action Scheduler marks the job as failed and retries it
        }

        if (null === $price) {
            error_log("crm-sync: no price returned for SKU \"$sku\" (product $productId), skipping.");
            return;
        }

        $product = wc_get_product($productId);
        if (!$product) {
            error_log("crm-sync: product $productId not found, skipping.");
            return;
        }

        $product->set_regular_price($price);
        $product->save();

        error_log("crm-sync: updated product $productId (SKU: $sku) regular_price → $price");
    }

    /**
     * Fetch the price for a given SKU from the external API.
     */
    private function fetchPrice(string $sku): ?float
    {
        $url = add_query_arg([
            'key' => $this->apiKey,
            'sku' => $sku,
        ], 'https://www.poloniacup.eu/api/getProductDetails');

        $force_ipv4 = static function ($handle): void {
            curl_setopt($handle, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        };

        add_action('http_api_curl', $force_ipv4);
        $response = wp_remote_get($url);
        remove_action('http_api_curl', $force_ipv4);

        if (is_wp_error($response)) {
            throw new RuntimeException('HTTP request failed: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($body['result'] !== 'success') {
            throw new RuntimeException('API did not return success! ' . json_encode($body));
        }

        $product = ProductModel::fromArray($body['content']);

        if (!is_numeric($product->price_retail_PLN)) {
            throw new RuntimeException("Invalid price value for SKU \"$sku\": \"{$product->price_retail_PLN}\"");
        }

        return (float)$product->price_retail_PLN;
    }
}
