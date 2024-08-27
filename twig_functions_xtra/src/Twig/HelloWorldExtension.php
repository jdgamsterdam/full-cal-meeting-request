<?php

namespace Drupal\twig_functions_xtra\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class HelloWorldExtension extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new TwigFunction('hello_world', [$this, 'helloWorldFunction']),
    ];
  }

  /**
   * Returns a hello world string.
   *
   * @return string
   *   A simple hello world string.
   */
  public function helloWorldFunction() {
    return 'Hello, World!';
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'hello_world.twig_extension';
  }

}