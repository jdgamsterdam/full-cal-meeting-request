<?php

namespace Drupal\twig_functions_xtra\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class JSONDecode extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new TwigFunction('json_decode', [$this, 'JSONDecode']),
    ];
  }

  /**

   */
  public function JSONDecode($myjson) {
    // Return the new datetime string
    return json_decode($myjson);
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'json_decode.twig_extension';
  }

}