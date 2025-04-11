<?php

namespace Drupal\starrlist\Service;

use Drupal\taxonomy\TermStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class TaxonomyHelper {

  protected TermStorageInterface $termStorage;

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->termStorage = $entityTypeManager->getStorage('taxonomy_term');
  }

  /**
   * Get taxonomy terms by vocabulary.
   *
   * @param string $vocabulary_id
   *   Vocabulary machine name.
   *
   * @return array
   *   Array of ['tid' => ..., 'name' => ...].
   */
  public function getTerms(string $vocabulary_id): array {
    $terms = [];

    $term_ids = $this->termStorage->getQuery()
      ->accessCheck(TRUE)
      ->condition('vid', $vocabulary_id)
      ->sort('weight')
      ->sort('name')
      ->execute();

    if (!empty($term_ids)) {
      $loaded_terms = $this->termStorage->loadMultiple($term_ids);

      foreach ($loaded_terms as $term) {
        $terms[] = [
          'tid' => $term->id(),
          'name' => $term->label(),
        ];
      }
    }

    return $terms;
  }

}
