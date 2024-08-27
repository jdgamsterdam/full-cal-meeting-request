<?php

namespace Drupal\twig_functions_xtra\Twig;

use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class GetUserTimezoneExtension extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new TwigFunction('get_user_timezone', [$this, 'GetUserTimezoneFunction']),
    ];
  }

  /**
   * Gets the time zone of a userID. Returns the system timezone if one is not set
   *
   * @return string
   *   Gets the time zone of a userID. Returns the system timezone if one is not set
   */
  public function GetUserTimezoneFunction($uid=1) {
    //sets admin if no value set as Twig is finnicky sending NULL
    
    // By default set the timezone Variable to the PHP default

    $timezone = date_default_timezone_get();
    
    if ($uid === NULL) {
      $user = \Drupal::currentUser();
    }
    else {
      $user = User::load($uid);
    }
  
    if ($user instanceof UserInterface) {

      // Check if the user has a timezone set.

      try {
        $timezone = $user->get('timezone')->value;
      } 
      catch (Exception $e) {
        $timezone = \Drupal::config('system.date')->get('timezone.default');       
      }
   
    }

    $my_return = $timezone;
    return $my_return;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'get_user_timezone.twig_extension';
  }

}