<?php

namespace Drupal\twig_functions_xtra\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;


class Fetch extends AbstractExtension {

  /**
  * {@inheritdoc}
  */
  public function getFunctions() {
    return [
      new TwigFunction('fetch', [$this, 'fetchFunction']),
    ];
  }

  public function fetchFunction ($url, $internal=1,$username="", $password="")
//  public function fetchFunction ($url, $internal=True)
  {
    $myusername=$username;
    $mypassword=$password;   

    if($url){
      if($internal==1) {
        $current_path = \Drupal::request()->getHost();

        if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')   
             $url_prefix = "https://";   
        else  
             $url_prefix = "http://";   
        $myurl = $url_prefix.$current_path."/".$url;
      }
      else
        {
          $myurl = $url;
        }   
      try {
        $ch = curl_init($myurl);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        if ($username!="") {
          //This is using Basic Auth
          curl_setopt($ch, CURLOPT_USERPWD, "$myusername:$mypassword");
        } 
        $data = curl_exec($ch);
        curl_close($ch);
      }
      catch (Exception $e) {
        return "Connection failed.";
      }

      return $data;
    }
}

  public function getName ()
  {
    return 'fetch.twig_extension';
  }
}
