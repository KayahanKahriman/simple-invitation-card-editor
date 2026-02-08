<?php
/**
 * Handles Cart and Order integration for Simple Invitation Editor
 */

if (!defined('ABSPATH')) {
    exit;
}

class SIE_Cart_Handler
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
        // Add design data to cart item
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_item_data'), 10, 3);

        // Display design data in cart and checkout
        add_filter('woocommerce_get_item_data', array($this, 'get_item_data'), 10, 2);

        // Save design data to order line item
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'checkout_create_order_line_item'), 10, 4);

        // Add hidden input to single product page
        add_action('woocommerce_before_add_to_cart_button', array($this, 'render_editor_container'));
    }

    public function render_editor_container()
    {
        global $post;
        $config = get_post_meta($post->ID, '_invitation_json_config', true);
        if (empty($config)) {
            return;
        }

        // Trigger Button
        echo '<button type="button" id="open-card-designer" class="button alt" style="width:100%; margin-bottom:15px;">Davetiye Tasarla</button>';

        // Modal Container (Hidden by default)
        echo '<div id="card-designer" class="sie-modal" style="display:none;">';
        echo '<div class="sie-modal-header">';
        echo '<h2>Tasarımcı</h2>';
        echo '<button type="button" id="sie-add-to-cart-btn" class="sie-add-to-cart-btn">Sepete Ekle</button>';
        echo '<button type="button" id="close-card-designer" class="sie-close-btn">&times;</button>';
        echo '</div>';
        echo '<div id="sie-editor-app"></div>';
        echo '</div>';

        echo '<input type="hidden" name="sie_custom_data" id="sie-custom-data" value="">';

        // Inline JS for Modal
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var modal = document.getElementById('card-designer');
                var btn = document.getElementById('open-card-designer');
                var closeBtn = document.getElementById('close-card-designer');

                if (btn && modal) {
                    // Move modal to body end immediately to fix z-index issues
                    document.body.appendChild(modal);

                    btn.onclick = function () {
                        modal.style.display = "flex";
                        document.body.style.overflow = "hidden"; // Prevent scrolling behind modal
                    }
                }

                if (closeBtn && modal) {
                    closeBtn.onclick = function () {
                        modal.style.display = "none";
                        document.body.style.overflow = "auto";
                    }
                }
            });
        </script>
        <?php
    }

    public function add_cart_item_data($cart_item_data, $product_id, $variation_id)
    {
        if (isset($_POST['sie_custom_data']) && !empty($_POST['sie_custom_data'])) {
            $custom_data = json_decode(stripslashes($_POST['sie_custom_data']), true);
            if ($custom_data) {
                $cart_item_data['sie_design_data'] = $custom_data;
            }
        }
        return $cart_item_data;
    }

    public function get_item_data($item_data, $cart_item)
    {
        // Only show in Checkout, not in the Cart to keep it clean
        if (is_cart()) {
            return $item_data;
        }

        if (isset($cart_item['sie_design_data'])) {
            foreach ($cart_item['sie_design_data'] as $layer_id => $layer_info) {
                if (isset($layer_info['label']) && isset($layer_info['text'])) {
                    $value = $layer_info['text'];
                    if (!empty($layer_info['fontFamily'])) {
                        // Just a clean way to show font if it differs from default, 
                        // though usually users just want to see the text.
                        // $value .= ' (' . $layer_info['fontFamily'] . ')'; 
                    }
                    $item_data[] = array(
                        'key' => $layer_info['label'],
                        'value' => $value,
                    );
                }
            }
        }
        return $item_data;
    }

    public function checkout_create_order_line_item($item, $cart_item_key, $values, $order)
    {
        if (isset($values['sie_design_data'])) {
            $item->add_meta_data('_sie_design_data', $values['sie_design_data']);

            foreach ($values['sie_design_data'] as $layer_id => $layer_info) {
                if (isset($layer_info['label']) && isset($layer_info['text'])) {
                    $item->add_meta_data($layer_info['label'], $layer_info['text']);
                }
            }
        }
    }
}
