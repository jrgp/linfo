<?php

// Just used for when visiting Linfo's standalone web UI after a fresh checkout
spl_autoload_register(function ($n) {

  $path = __DIR__.'/src/'.str_replace('\\', '/', $n).'.php';

  if (!$path) {
      return;
  }

  if (!is_file($path)) {
      return;
  }

  require_once $path;

  if (!class_exists($n) && !interface_exists($n)) {
      echo "$n not exists in $path\n";
      die(1);
  }
});

use \Linfo\Exceptions\FatalException;
use \Linfo\Linfo;
use \Linfo\Common;

if (!defined('LINFO_TESTING')) {
    try {

        // Load settings file..
        // Support legacy config files
        define('IN_LINFO', '1');
        if (!is_file(__DIR__.'/config.inc.php') && is_file(__DIR__.'/sample.config.inc.php')) {
            throw new FatalException('Make changes to sample.config.inc.php then rename as config.inc.php');
        } elseif (!is_file(__DIR__.'/config.inc.php')) {
            throw new FatalException('Config file not found.');
        }

        $settings = Common::getVarFromFile(__DIR__.'/config.inc.php', 'settings');

        $linfo = new Linfo($settings);
        $linfo->scan();

        switch (array_key_exists('out', $_GET) ? strtolower($_GET['out']) : 'html') {
            default:
            case 'html':
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
    } catch (FatalException $e) {
        echo $e->getMessage()."\n";
        exit(1);
    }
}
