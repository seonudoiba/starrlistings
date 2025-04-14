<?php
namespace Drupal\starrlisting\Plugin\Block;
use Drupal\Core\Block\BlockBase;

/**
 * Provides an 'About' Block.
 *
 * @Block(
 *   id = "cta_block",
 *   admin_label = @Translation("CTA Block"),
 *   category = @Translation("Starrlisting custom")
 * )
 */
class CTABlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#theme' => 'cta_block',
    ];
  }

}