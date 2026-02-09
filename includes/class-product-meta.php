<?php
/**
 * Handles Product Meta for Simple Invitation Editor
 */

if (!defined('ABSPATH')) {
    exit;
}

class SIE_Product_Meta
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Add to WooCommerce Product Data Tabs
        add_filter('woocommerce_product_data_tabs', array($this, 'add_product_data_tab'));
        add_action('woocommerce_product_data_panels', array($this, 'render_product_data_panel'));
        add_action('woocommerce_process_product_meta', array($this, 'save_product_data_tab'));
    }

    public function add_product_data_tab($tabs)
    {
        $tabs['sie_invitation'] = array(
            'label' => __('Invitation Editor', 'simple-invitation-editor'),
            'target' => 'sie_invitation_data',
            'class' => array('show_if_simple', 'show_if_variable'),
            'priority' => 100,
        );
        return $tabs;
    }

    public function render_product_data_panel()
    {
        global $post;
        $config = get_post_meta($post->ID, '_invitation_json_config', true);
        ?>
        <div id="sie_invitation_data" class="panel woocommerce_options_panel hidden">
            <div class="options_group">
                <?php
                woocommerce_wp_textarea_input(array(
                    'id' => 'sie_invitation_json_config_tab',
                    'name' => 'sie_invitation_json_config_tab',
                    'value' => $config,
                    'label' => __('JSON Configuration', 'simple-invitation-editor'),
                    'description' => __('Paste the JSON design configuration here.', 'simple-invitation-editor'),
                    'desc_tip' => true,
                    'style' => 'height: 400px; font-family: monospace;',
                ));
                ?>
            </div>
        </div>
        <?php
    }

    public function save_product_data_tab($post_id)
    {
        if (isset($_POST['sie_invitation_json_config_tab'])) {
            update_post_meta($post_id, '_invitation_json_config', $_POST['sie_invitation_json_config_tab']);
        }
    }
}