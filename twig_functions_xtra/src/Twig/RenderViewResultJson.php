<?php

namespace Drupal\twig_functions_xtra\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Drupal\Component\Serialization;

class RenderViewResultJson extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new TwigFunction('view_result_json', [$this, 'renderViewResultJson']),
    ];
  }

  /**
   * Returns the results of a View as a JSON string.  
   * Generally should be used with Simple View Results that are unordered lists.
   * @return JSON string
   *   
   */

  public function renderViewResultJson($view_name, $display_id)
  {
      $view = views_get_view_result($view_name,$display_id);
      $string = json_encode($view);
      $mycount = count($view);
      $string = "Length: ".$mycount." First Element: ".json_encode($view[0]);
      return $string;
  }
  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'view_result_json.twig_extension';
  }

}
