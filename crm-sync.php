<?php
/**
 * Plugin Name: CRM Sync
 * Description: Synchronize WordPress data with your CRM via API.
 * Version:     1.0.0
 * Author:      Polaris ATV
 */

use CrmSync\PriceDispatch;
use CrmSync\PriceSync;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;


if (!defined('ABSPATH')) {
    exit;
}

define('CRM_SYNC_OPTION_API_KEY', 'crm_sync_api_key');

require_once __DIR__ . '/vendor/autoload.php';

// ---------------------------------------------------------------------------
// Action Scheduler: schedule / teardown
// ---------------------------------------------------------------------------

register_activation_hook(__FILE__, 'crm_sync_on_activate');
register_deactivation_hook(__FILE__, 'crm_sync_on_deactivate');
add_action('action_scheduler_init', 'crm_sync_ensure_scheduled');

function crm_sync_on_activate(): void
{
    // Intentionally empty — scheduling happens via action_scheduler_init
    // so that Action Scheduler's store is guaranteed to be ready.
}

function crm_sync_on_deactivate(): void
{
    as_unschedule_all_actions('crm_sync_dispatch_price_jobs', [], 'crm-sync');
    as_unschedule_all_actions('crm_sync_update_price', [], 'crm-sync');
}

/**
 * Ensures exactly one recurring dispatch action is scheduled.
 * Runs on action_scheduler_init so the AS store is ready.
 * Unschedules duplicates before re-creating, which also cleans up
 * any extras that were created before this fix was in place.
 */
function crm_sync_ensure_scheduled(): void
{
    $hook = 'crm_sync_dispatch_price_jobs';
    $group = 'crm-sync';

    // If no API key is configured, unschedule any existing action and bail.
    if (empty(get_option(CRM_SYNC_OPTION_API_KEY, ''))) {
        as_unschedule_all_actions($hook, [], $group);
        return;
    }

    $count = count(as_get_scheduled_actions([
            'hook' => $hook,
            'group' => $group,
            'status' => ActionScheduler_Store::STATUS_PENDING,
    ]));

    if ($count === 1) {
        return; // already correct
    }

    // Remove all (handles 0 or >1) then schedule one clean entry.
    as_unschedule_all_actions($hook, [], $group);
    as_schedule_recurring_action(time(), 6 * HOUR_IN_SECONDS, $hook, [], $group);
}

// ---------------------------------------------------------------------------
// Action Scheduler: action handlers
// ---------------------------------------------------------------------------

/**
 * Dispatcher — queries all products with a SKU and queues one update job each.
 */
add_action('crm_sync_dispatch_price_jobs', 'crm_sync_dispatch_price_jobs');

function crm_sync_dispatch_price_jobs(): void
{
    (new PriceDispatch())->dispatch();
}

/**
 * Per-product handler — fetches price from external API and updates the product.
 */
add_action('crm_sync_update_price', 'crm_sync_update_price', 10, 2);

function crm_sync_update_price(int $product_id, string $sku): void
{
    $sync = new PriceSync(get_option(CRM_SYNC_OPTION_API_KEY, ''));
    $sync->updateProductPrice($product_id, $sku);
}

add_action('admin_menu', 'crm_sync_add_menu');
add_action('admin_init', 'crm_sync_register_settings');

function crm_sync_add_menu()
{
    add_menu_page(
            'CRM Sync',
            'CRM Sync',
            'manage_options',
            'crm-sync',
            'crm_sync_settings_page',
            'dashicons-networking',
            80
    );
}

function crm_sync_register_settings()
{
    register_setting(
            'crm_sync_settings_group',
            CRM_SYNC_OPTION_API_KEY,
            array(
                    'sanitize_callback' => 'sanitize_text_field',
            )
    );
}

function crm_sync_settings_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1>CRM Sync Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('crm_sync_settings_group');
            $api_key = get_option(CRM_SYNC_OPTION_API_KEY, '');
            ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="crm_sync_api_key">API Key</label>
                    </th>
                    <td>
                        <input
                                type="text"
                                id="crm_sync_api_key"
                                name="<?php echo esc_attr(CRM_SYNC_OPTION_API_KEY); ?>"
                                value="<?php echo esc_attr($api_key); ?>"
                                class="regular-text"
                                autocomplete="off"
                        />
                        <p class="description">Enter your CRM API key.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Settings'); ?>
        </form>
    </div>
    <?php
}

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/polarisatv/crm-sync/',
    __FILE__,
    'crm-sync'
);
