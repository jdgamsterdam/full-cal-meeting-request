<?php

namespace Drupal\twig_functions_xtra\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ReplaceTimeInDateExtension extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new TwigFunction('replace_time_in_date', [$this, 'ReplaceTimeInDate']),
    ];
  }

  /**
   * Returns a hello world string.
   *
   * @return string
   *   A simple hello world string.
   */
  public function ReplaceTimeInDate($dateText,$newTime="00:00:00") {
    // Convert the text to a date object
    $date = strtotime($dateText);
    if ($date === false) {
        return "Invalid date format.";
    }
    
    // Extract the date part
    $datePart = date("Y-m-d", $date);
    
    // Combine the date part with the new time
    $newDateTime = $datePart . ' ' . $newTime;
    
    // Convert the new datetime string back to a date object to validate it
    $finalDate = strtotime($newDateTime);
    if ($finalDate === false) {
        return "Invalid time format.";
    }
    
    // Return the new datetime string
    return date("Y-m-d H:i:s", $finalDate);
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'replace_time_in_date.twig_extension';
  }

}
