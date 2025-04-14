<?php
namespace Drupal\starrlisting\Plugin\Block;
use Drupal\Core\Block\BlockBase;

/**
 * Provides an 'About' Block.
 *
 * @Block(
 *   id = "about_block",
 *   admin_label = @Translation("About Block"),
 *   category = @Translation("Starrlisting custom")
 * )
 */
class AboutBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#theme' => 'about_block',
    //   '#attributes' => [
    //     'class' => ['custom-about-block'],
    //   ],
    //   '#attached' => [
    //     'library' => [
    //       'my_custom_blocks/about_block_styles',
    //     ],
    //   ],
    ];
  }

}