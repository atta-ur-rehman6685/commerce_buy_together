<?php

namespace Drupal\commerce_buy_together\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\commerce_order\Entity\OrderItem;

/**
 * Controller for adding frequently bought together products to the cart.
 */
class BuyTogetherController extends ControllerBase {

  /**
   * The cart manager.
   *
   * @var \Drupal\commerce_cart\CartManagerInterface
   */
  protected $cartManager;

  /**
   * The cart provider.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $cartProvider;

  /**
   * Constructs a new BuyTogetherController.
   *
   * @param \Drupal\commerce_cart\CartManagerInterface $cart_manager
   *   The cart manager.
   * @param \Drupal\commerce_cart\CartProviderInterface $cart_provider
   *   The cart provider.
   */
  public function __construct(CartManagerInterface $cart_manager, CartProviderInterface $cart_provider) {
    $this->cartManager = $cart_manager;
    $this->cartProvider = $cart_provider;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_cart.cart_manager'),
      $container->get('commerce_cart.cart_provider')
    );
  }

  /**
   * Adds products to the cart based on product IDs.
   *
   * @param string $product_ids
   *   A comma-separated string of product IDs.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirects to the cart page after adding products.
   */
  public function addToCart($product_ids) {
    // Split the product IDs into an array.
    $product_ids_array = explode(',', $product_ids);

    // Load the current user.
    $user = \Drupal::currentUser();

    // Load the current store.
    $store = \Drupal::service('commerce_store.current_store')->getStore();

    // Load the current cart for the user and store.
    $cart = $this->cartProvider->getCart('default', $store);

    // If no cart exists, create one programmatically.
    if (!$cart) {
      $cart = $this->cartProvider->createCart('default', $store, $user);
    }

    if ($cart) {
      foreach ($product_ids_array as $product_id) {
        // Load the product variation.
        $product_variation = \Drupal::entityTypeManager()->getStorage('commerce_product_variation')->load($product_id);
        
        
        if ($product_variation) {
          // Create a new order item for this product.
          $order_item = OrderItem::create([
            'type' => 'default',
            'purchased_entity' => $product_variation,
            'quantity' => 1,
          ]);
          // Add the order item to the cart.
          $this->cartManager->addOrderItem($cart, $order_item);
        }
      }
    }

    // Redirect to the cart page after adding items.
    return new RedirectResponse('/cart');
  }
}
