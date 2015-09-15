<?php

spl_autoload_register(function($n) {

  $path = __DIR__.'/'.str_replace('\\', '/', $n).'.php';

  if (!$path)
    return;

  if (!is_file($path)) 
    return;

  require_once $path;

  if (!class_exists($n) && !interface_exists($n)) {
    echo "$n not exists in $path\n";
    die(1);
  }
});

if (!defined('LINFO_TESTING')) {

  try {

    $linfo = new \Linfo\Linfo();
    $linfo->scan();

    switch (array_key_exists('out', $_GET) ? strtolower($_GET['out']) : 'html') {
      case 'html':
      default:
        $output = new \Linfo\Output\Html($linfo);
        $output->output();
      break;

      case 'json':
      case 'jsonp': // To use JSON-P, pass the GET arg - callback=function_name
        $output = new \Linfo\Output\Json($linfo, array_key_exists('callback', $_GET) ? $_GET['callback'] : null);
        $output->output();
      break;

      case 'php_array':
        $output = new \Linfo\Output\Serialized($linfo);
        $output->output();
      break;

      case 'xml':
        $output = new \Linfo\Output\Xml($linfo);
        $output->output();
      break;
    }

  }
  catch (\Linfo\Exceptions\FatalException $e) {
    echo $e->getMessage()."\n";
    exit(1);
  }

}
