<?php

namespace Drupal\twig_functions_xtra\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use DateTime;
use DateTimeZone;

class HoursBetweenTimeZonesExtension extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new TwigFunction('hours_between_time_zones', [$this, 'hoursBetweenTimeZonesFunction']),
    ];
  }

  /**
   * Returns a hello world string.
   *
   * @return string
   *   A simple hello world string.
   */
  public function hoursBetweenTimeZonesFunction($timezone1="UTC",$timezone2="UTC") {

    //$timezone1 = "America/New_York";
    //$timezone2 = "Europe/London";

    $datetime1 = new DateTime("now", new DateTimeZone($timezone1));
    $datetime2 = new DateTime("now", new DateTimeZone($timezone2));

    $date1_array = (array) $datetime1;
    $date1_notz = $date1_array['date'];

    $date2_array = (array) $datetime2;
    $date2_notz = $date2_array['date'];

    //Interval in Seconds between TimeZones
    $interval_hours = (strtotime($date1_notz)-strtotime($date2_notz))/3600;

    //return 'Time 1: ' . json_encode($datetime1) . '| Time1 NoTz: '. $date1_notz .  '| Time2 NoTz: '. $date2_notz . '| Interval Hours: '. $interval_hours ;
    return $interval_hours;
    

  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'hours_between_time_zones.twig_extension';
  }

}