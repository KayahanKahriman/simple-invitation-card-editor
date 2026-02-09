<?php
/**
 * Handles Product Page modifications for Simple Invitation Editor
 * Hides quantity input and default add to cart button for invitation products
 */

if (!defined('ABSPATH')) {
  exit;
}

class SIE_Product_Page_Handler
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
    // Hide quantity input and add to cart button for invitation products
    add_action('wp', array($this, 'maybe_hide_default_cart_elements'));

    // Add custom CSS to hide elements
    add_action('wp_head', array($this, 'add_custom_css'));
  }

  /**
   * Check if current product has invitation editor enabled
   */
  private function is_invitation_product()
  {
    if (!is_product()) {
      return false;
    }

    global $post;
    $config = get_post_meta($post->ID, '_invitation_json_config', true);

    return !empty($config);
  }

  /**
   * Remove default WooCommerce add to cart elements
   */
  public function maybe_hide_default_cart_elements()
  {
    if (!$this->is_invitation_product()) {
      return;
    }

    // Remove quantity input
    add_filter('woocommerce_is_sold_individually', array($this, 'force_sold_individually'), 10, 2);

    // Hide the default add to cart button
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);

    // Add our custom button container in the same position
    add_action('woocommerce_single_product_summary', array($this, 'add_custom_button_container'), 30);
  }

  /**
   * Force product to be sold individually (hides quantity input)
   */
  public function force_sold_individually($is_sold_individually, $product)
  {
    if (!is_product()) {
      return $is_sold_individually;
    }

    global $post;
    $config = get_post_meta($post->ID, '_invitation_json_config', true);

    if (!empty($config)) {
      return true;
    }

    return $is_sold_individually;
  }

  /**
   * Add custom button container
   * The actual button is rendered by class-cart-handler.php
   */
  public function add_custom_button_container()
  {
    // This will be populated by the cart handler's render_editor_container method
    echo '<div class="sie-custom-cart-section"></div>';
  }

  /**
   * Add CSS to hide default elements and style custom button
   */
  public function add_custom_css()
  {
    if (!$this->is_invitation_product()) {
      return;
    }
    ?>
    <style type="text/css">
      /* Hide default WooCommerce add to cart button for invitation products */
      .single-product .product form.cart button.single_add_to_cart_button {
        display: none !important;
      }

      /* Hide quantity input (backup in case sold_individually doesn't work) */
      .single-product .product form.cart .quantity {
        display: none !important;
      }

      /* Style the custom "Davetiye Tasarla" button */
      #open-card-designer {
        background-color: #0071a1;
        color: #ffffff;
        border: none;
        padding: 12px 24px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        border-radius: 4px;
        transition: background-color 0.3s ease;
      }

      #open-card-designer:hover {
        background-color: #005177;
      }

      /* Disabled state styling */
      #open-card-designer:disabled {
        background-color: #cccccc;
        cursor: not-allowed;
        opacity: 0.5;
      }

      #open-card-designer:disabled:hover {
        background-color: #cccccc;
      }

      /* Ensure proper spacing */
      .sie-custom-cart-section {
        margin-top: 20px;
      }
    </style>
    <?php
  }
}
