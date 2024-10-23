<?php

namespace Drupal\commerce_buy_together\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\commerce_order\Entity\Order;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\file\FileUrlGeneratorInterface;
use Drupal\commerce_price\Price;



/**
 * Provides a 'Buy Together' block with products frequently bought together as sets.
 *
 * @Block(
 *   id = "buy_together_block",
 *   admin_label = @Translation("Buy Together Block"),
 *   category = @Translation("Commerce")
 * )
 */
class BuyTogetherBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Fetch the most frequently bought together set of products.
    $set_data = $this->getMostFrequentProductSet();
  
    if (empty($set_data)) {
      return [
        '#markup' => $this->t('No products to display.'),
      ];
    }
  
    $product_render_data = [];
    $product_ids = [];
    $total_price = new Price('0.00', 'USD'); // Initialize with a default currency.
  
    // Load the file URL generator service.
    /** @var \Drupal\file\FileUrlGeneratorInterface $file_url_generator */
    $file_url_generator = \Drupal::service('file_url_generator');
  
    // Load the currency formatter service.
    /** @var \Drupal\commerce_price\CurrencyFormatterInterface $currency_formatter */
    $currency_formatter = \Drupal::service('commerce_price.currency_formatter');
    
    // Limit the products array to only the first three products.
    $limited_products = array_slice($set_data['products'], 0, 3);
    // kint($limited_products);
    // exit;
    foreach ($limited_products as $product_variation) {
      $product = $product_variation->getProduct();
      $product_url = $product->toUrl()->toString();
      $product_image = '';
  
      // Get the product image field from the parent product.
      if ($product->hasField('field_product_image') && !$product->get('field_product_image')->isEmpty()) {
        $image_field = $product->get('field_product_image')->entity;
        if ($image_field) {
          // Generate the URL for the image file.
          $product_image = $file_url_generator->generateAbsoluteString($image_field->getFileUri());
        }
      }
  
      // Format the price using the currency formatter service.
      $formatted_price = $currency_formatter->format($product_variation->getPrice()->getNumber(), $product_variation->getPrice()->getCurrencyCode());
  
      // Accumulate the total price as a Price object for proper formatting later.
      $total_price = $total_price->add($product_variation->getPrice());
  
      // Add product ID to the array.
      $product_ids[] = $product_variation->id();
  
      $product_render_data[] = [
        'url' => $product_url,
        'image' => $product_image,
        'price' => $formatted_price,
        'title' => $product->getTitle(),
      ];
    }
  
    // Format the total price with currency symbol.
    $formatted_total_price = $currency_formatter->format($total_price->getNumber(), $total_price->getCurrencyCode());
  
    // Generate the Add to Cart URL only if there are product IDs.
    $add_to_cart_url = !empty($product_ids) 
      ? Url::fromRoute('commerce_buy_together.add_to_cart', ['product_ids' => implode(',', $product_ids)])->toString() 
      : '';
  
    return [
      '#theme' => 'buy_together_block',
      '#products' => $product_render_data,
      '#total_price' => $formatted_total_price,
      '#add_to_cart_url' => $add_to_cart_url,
      '#attached' => [
        'library' => [
          'commerce_buy_together/buy_together_block',
        ],
      ],
    ];
  }
  
  
  /**
   * Get the most frequent product set from orders with at least 3 items.
   *
   * @return array|null
   *   An array containing the most frequent product set, or NULL if no valid sets are found.
   */
  protected function getMostFrequentProductSet() {
    $set_count = [];

    // Fetch completed orders.
    $query = \Drupal::entityQuery('commerce_order')
      ->condition('state', 'completed')
      ->accessCheck(FALSE);
    
    $order_ids = $query->execute();
    
    if (empty($order_ids)) {
      return NULL;
    }

    // Load all orders.
    $orders = Order::loadMultiple($order_ids);

    // Process each order that has at least 3 order items.
    foreach ($orders as $order) {
      $order_items = $order->getItems();

      // Only consider orders with at least 3 items.
      if (count($order_items) < 3) {
        continue;
      }

      // Extract product IDs from the order items.
      $product_ids = [];
      $products = [];
      foreach ($order_items as $order_item) {
        $purchased_entity = $order_item->getPurchasedEntity();
        if ($purchased_entity) {
          $product_ids[] = $purchased_entity->id();
          $products[] = $purchased_entity;
        }
      }

      // Sort product IDs to ensure consistent key for the set.
      sort($product_ids);
      $set_key = implode('_', $product_ids);

      // Increment the count for this set.
      if (isset($set_count[$set_key])) {
        $set_count[$set_key]['count']++;
      } else {
        $set_count[$set_key] = [
          'products' => $products,
          'count' => 1,
        ];
      }
    }

    // Find the most common set.
    uasort($set_count, function ($a, $b) {
      return $b['count'] - $a['count'];
    });

    return !empty($set_count) ? reset($set_count) : NULL;
  }
}
