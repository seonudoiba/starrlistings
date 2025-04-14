<?php
namespace Drupal\starrlisting\Plugin\Block;
use Drupal\Core\Block\BlockBase;

/**
 * Provides an 'About' Block.
 *
 * @Block(
 *   id = "footer_block",
 *   admin_label = @Translation("Footer Block"),
 *   category = @Translation("Starrlisting custom")
 * )
 */
class FooterBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#theme' => 'footer_block',
    ];
  }

}