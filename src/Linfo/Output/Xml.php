<?php

namespace Linfo\Output;

use Exception;
use Linfo\Linfo;
use Linfo\Common;
use Linfo\Exceptions\FatalException;
use SimpleXMLElement;

class Xml implements Output
{
    protected $linfo;

    public function __construct(Linfo $linfo)
    {
        if (!extension_loaded('SimpleXML')) {
            throw new FatalException('Cannot generate XML. Install php\'s SimpleXML extension.');
        }

        $this->linfo = $linfo;
    }

    public function output()
    {
        $lang = $this->linfo->getLang();
        $settings = $this->linfo->getSettings();
        $info = $this->linfo->getInfo();

        try {
            // Start it up
            $xml = new SimpleXMLElement('<?xml version="1.0"?><linfo></linfo>');

            // Deal with core stuff
            $core_elem = $xml->addChild('core');
                  $core = [];
                  if (!empty($settings['show']['os'])) {
                      $core[] = array('os', $info['OS']);
                  }
                  if (!empty($settings['show']['distro']) && isset($info['Distro']) && is_array($info['Distro'])) {
                      $core[] = array('distro',  $info['Distro']['name'].($info['Distro']['version'] ? ' - '.$info['Distro']['version'] : ''));
                  }
                  if (!empty($settings['show']['kernel'])) {
                      $core[] = array('kernel', $info['Kernel']);
                  }
                  if (!empty($settings['show']['webservice'])) {
                      $core[] = array('webservice', $info['webService']);
                  }
                  if (!empty($settings['show']['phpversion'])) {
                      $core[] = array('phpversion', $info['phpVersion']);
                  }
                  if (!isset($settings['show']['ip']) || !empty($settings['show']['ip'])) {
                      $core[] = array('accessed_ip', (isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : 'Unknown'));
                  }
                  if (!empty($settings['show']['uptime'])) {
                      $core[] = array('uptime', $info['UpTime']['text']);
                  }
                  if (!empty($settings['show']['hostname'])) {
                      $core[] = array('hostname', $info['HostName']);
                  }
                  if (!empty($settings['show']['cpu'])) {
                      $cpus = '';
                      foreach ((array) $info['CPU'] as $cpu) {
                          $cpus .=
                  (array_key_exists('Vendor', $cpu) && empty($cpu['Vendor']) ? $cpu['Vendor'].' - ' : '').
                  $cpu['Model'].
                  (array_key_exists('MHz', $cpu) ?
                    ($cpu['MHz'] < 1000 ? ' ('.$cpu['MHz'].' MHz)' : ' ('.round($cpu['MHz'] / 1000, 3).' GHz)') : '').
                    '<br />';
                      }
                      $core[] = array('cpus', $cpus);
                  }
                  if (!empty($settings['show']['model']) && array_key_exists('Model', $info) && !empty($info['Model'])) {
                      $core[] = array('model', $info['Model']);
                  }
                  if (!empty($settings['show']['process_stats']) && $info['processStats']['exists']) {
                      $proc_stats = [];
                      if (array_key_exists('totals', $info['processStats']) && is_array($info['processStats']['totals'])) {
                          foreach ($info['processStats']['totals'] as $k => $v) {
                              $proc_stats[] = $k.': '.number_format($v);
                          }
                      }
                      $proc_stats[] = 'total: '.number_format($info['processStats']['proc_total']);
                      $core[] = array('processes', implode('; ', $proc_stats));
                      if ($info['processStats']['threads'] !== false) {
                          $core[] = array('threads', number_format($info['processStats']['threads']));
                      }
                  }
                  if (!empty($settings['show']['load'])) {
                      $core[] = array('load', implode(' ', (array) $info['Load']));
                  }

            // Adding each core stuff
            for ($i = 0, $core_num = count($core); $i < $core_num; ++$i) {
                $core_elem->addChild($core[$i][0], $core[$i][1]);
            }

            // RAM
            if (!empty($settings['show']['ram'])) {
                $mem = $xml->addChild('memory');
                $core_mem = $mem->addChild($info['RAM']['type']);
                $core_mem->addChild('free', $info['RAM']['free']);
                $core_mem->addChild('total', $info['RAM']['total']);
                $core_mem->addChild('used', $info['RAM']['total'] - $info['RAM']['free']);
                if (isset($info['RAM']['swapFree']) || isset($info['RAM']['swapTotal'])) {
                    $swap = $mem->addChild('swap');
                    $swap_core = $swap->addChild('core');
                    $swap_core->addChild('free', $info['RAM']['swapFree']);
                    $swap_core->addChild('total', $info['RAM']['swapTotal']);
                    $swap_core->addChild('used', $info['RAM']['swapTotal'] - $info['RAM']['swapFree']);
                    if (is_array($info['RAM']['swapInfo']) && count($info['RAM']['swapInfo']) > 0) {
                        $swap_devices = $swap->addChild('devices');
                        foreach ($info['RAM']['swapInfo'] as $swap_dev) {
                            $swap_dev_elem = $swap_devices->addChild('device');
                            $swap_dev_elem->addAttribute('device', $swap_dev['device']);
                            $swap_dev_elem->addAttribute('type', $swap_dev['type']);
                            $swap_dev_elem->addAttribute('size', $swap_dev['size']);
                            $swap_dev_elem->addAttribute('used', $swap_dev['used']);
                        }
                    }
                }
            }

            // NET
            if (!empty($settings['show']['network']) && isset($info['Network Devices']) && is_array($info['Network Devices'])) {
                $net = $xml->addChild('net');
                foreach ($info['Network Devices'] as $device => $stats) {
                    $nic = $net->addChild('interface');
                    $nic->addAttribute('device', $device);
                    $nic->addAttribute('type', $stats['type']);
                    $nic->addAttribute('sent', $stats['sent']['bytes']);
                    $nic->addAttribute('recieved', $stats['recieved']['bytes']);
                }
            }

            // TEMPS
            if (!empty($settings['show']['temps']) && isset($info['Temps']) && count($info['Temps']) > 0) {
                $temps = $xml->addChild('temps');
                foreach ($info['Temps'] as $stat) {
                    $temp = $temps->addChild('temp');
                    $temp->addAttribute('path', $stat['path']);
                    $temp->addAttribute('name', $stat['name']);
                    $temp->addAttribute('temp', $stat['temp'].' '.$stat['unit']);
                }
            }

            // Batteries
            if (!empty($settings['show']['battery']) && isset($info['Battery']) && count($info['Battery']) > 0) {
                $bats = $xml->addChild('batteries');
                foreach ($info['Battery'] as $bat) {
                    $bat = $bats->addChild('battery');
                    $bat->addAttribute('device', $bat['device']);
                    $bat->addAttribute('state', $bat['state']);
                    $bat->addAttribute('percentage', $bat['percentage']);
                }
            }

            // SERVICES
            if (!empty($settings['show']['services']) && isset($info['services']) && count($info['services']) > 0) {
                $services = $xml->addChild('services');
                foreach ($info['services'] as $service => $state) {
                    $state_parts = explode(' ', $state['state'], 2);
                    $service_elem = $services->addChild('service');
                    $service_elem->addAttribute('name', $service);
                    $service_elem->addAttribute('state', $state_parts[0].(array_key_exists(1, $state_parts) ? ' '.$state_parts[1] : ''));
                    $service_elem->addAttribute('pid', $state['pid']);
                    $service_elem->addAttribute('threads', $state['threads'] ? $state['threads'] : '?');
                    $service_elem->addAttribute('mem_usage', $state['memory_usage'] ? $state['memory_usage'] : '?');
                }
            }

            // DEVICES
            if (!empty($settings['show']['devices']) && isset($info['Devices'])) {
                $show_vendor = array_key_exists('hw_vendor', $info['contains']) ? ($info['contains']['hw_vendor'] === false ? false : true) : true;
                $devices = $xml->addChild('devices');
                for ($i = 0, $num_devs = count($info['Devices']); $i < $num_devs; ++$i) {
                    $device = $devices->addChild('device');
                    $device->addAttribute('type', $info['Devices'][$i]['type']);
                    if ($show_vendor) {
                        $device->addAttribute('vendor', $info['Devices'][$i]['vendor']);
                    }
                    $device->addAttribute('name', $info['Devices'][$i]['device']);
                }
            }

            // DRIVES
            if (!empty($settings['show']['hd']) && isset($info['HD']) && is_array($info['HD'])) {
                $show_stats = array_key_exists('drives_rw_stats', $info['contains']) ? ($info['contains']['drives_rw_stats'] === false ? false : true) : true;
                $drives = $xml->addChild('drives');
                foreach ($info['HD'] as $drive) {
                    $drive_elem = $drives->addChild('drive');
                    $drive_elem->addAttribute('device', $drive['device']);
                    $drive_elem->addAttribute('vendor', $drive['vendor'] ? $drive['vendor'] : $lang['unknown']);
                    $drive_elem->addAttribute('name', $drive['name']);
                    if ($show_stats) {
                        $drive_elem->addAttribute('reads', $drive['reads'] ? $drive['reads'] : 'unknown');
                        $drive_elem->addAttribute('writes', $drive['writes'] ? $drive['writes'] : 'unknown');
                    }
                    $drive_elem->addAttribute('size', $drive['size'] ? $drive['size'] : 'unknown');
                    if (is_array($drive['partitions']) && count($drive['partitions']) > 0) {
                        $partitions = $drive_elem->addChild('partitions');
                        foreach ($drive['partitions'] as $partition) {
                            $partition_elem = $partitions->addChild('partition');
                            $partition_elem->addAttribute('name', isset($partition['number']) ? $drive['device'].$partition['number'] : $partition['name']);
                            $partition_elem->addAttribute('size', $partition['size']);
                        }
                    }
                }
            }

            // Sound cards? lol
            if (!empty($settings['show']['sound']) && isset($info['SoundCards']) && count($info['SoundCards']) > 0) {
                $cards = $xml->addChild('soundcards');
                foreach ($info['SoundCards'] as $card) {
                    $card_elem = $cards->addChild('card');
                    $card_elem->addAttribute('number', $card['number']);
                    $card_elem->addAttribute('vendor', empty($card['vendor']) ? 'unknown' : $card['vendor']);
                    $card_elem->addAttribute('card', $card['card']);
                }
            }

            // File system mounts
            if (!empty($settings['show']['mounts'])) {
                $has_devices = false;
                $has_labels = false;
                $has_types = false;
                foreach ($info['Mounts'] as $mount) {
                    if (!empty($mount['device'])) {
                        $has_devices = true;
                    }
                    if (!empty($mount['label'])) {
                        $has_labels = true;
                    }
                    if (!empty($mount['devtype'])) {
                        $has_types = true;
                    }
                }
                $mounts = $xml->addChild('mounts');
                foreach ($info['Mounts'] as $mount) {
                    $mount_elem = $mounts->addChild('mount');
                    if (preg_match('/^.+:$/', $mount['device']) == 1) {
                        $mount['device'] .= DIRECTORY_SEPARATOR;
                    }
                    if ($has_types) {
                        $mount_elem->addAttribute('type', $mount['devtype']);
                    }
                    if ($has_devices) {
                        $mount_elem->addAttribute('device', $mount['device']);
                    }
                    $mount_elem->addAttribute('mountpoint', $mount['mount']);
                    if ($has_labels) {
                        $mount_elem->addAttribute('label', $mount['label']);
                    }
                    $mount_elem->addAttribute('fstype', $mount['type']);
                    if ($settings['show']['mounts_options'] && !empty($mount['options'])) {
                        $mount_elem->addAttribute('options', implode(',', $mount['options']));
                    }
                    $mount_elem->addAttribute('size', $mount['size']);
                    $mount_elem->addAttribute('used', $mount['used']);
                    $mount_elem->addAttribute('free', $mount['free']);
                }
            }

            // RAID arrays
            if (!empty($settings['show']['raid']) && isset($info['Raid']) && count($info['Raid']) > 0) {
                $raid_elem = $xml->addChild('raid');
                foreach ($info['Raid'] as $raid) {
                    $array = $raid_elem->addChild('array');
                    $active = explode('/', $raid['count']);
                    $array->addAttribute('device', $raid['device']);
                    $array->addAttribute('level', $raid['level']);
                    $array->addAttribute('status', $raid['status']);
                    $array->addAttribute('size', $raid['size']);
                    $array->addAttribute('active', $active[1].'/'.$active[0]);
                    $drives = $array->addChild('drives');
                    foreach ($raid['drives'] as $drive) {
                        $drive_elem = $drives->addChild('drive');
                        $drive_elem->addAttribute('drive', $drive['drive']);
                        $drive_elem->addAttribute('state', $drive['state']);
                    }
                }
            }

            // Timestamp
            $xml->addChild('timestamp', $info['timestamp']);

            // Extensions
            if (count($info['extensions']) > 0) {
                $extensions = $xml->addChild('extensions');
                foreach ($info['extensions'] as $ext) {
                    $header = false;
                    if (is_array($ext) && count($ext) > 0) {
                        $this_ext = $extensions->addChild(Common::xmlStringSanitize($ext['root_title']));
                        foreach ((array) $ext['rows'] as $i => $row) {
                            if ($row['type'] == 'header') {
                                $header = $i;
                            } elseif ($row['type'] == 'values') {
                                $this_row = $this_ext->addChild('row');
                                if ($header !== false && array_key_exists($header, $ext['rows'])) {
                                    foreach ($ext['rows'][$header]['columns'] as $ri => $rc) {
                                        $this_row->addChild(
                            Common::xmlStringSanitize($rc),
                            $ext['rows'][$i]['columns'][$ri]
                          );
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Out it
            if (!headers_sent()) {
                header('Content-type: text/xml');
            }
                  echo $xml->asXML();

            // Comment which has stats and generator
            echo '<!-- Generated in '.round(microtime(true) - $this->linfo->getTimeStart(), 2).
              ' seconds by '.$this->linfo->getAppName().' ('.$this->linfo->getVersion().')-->';
          } catch (Exception $e) {
            throw new FatalException('Creation of XML error: '.$e->getMessage());
        }
    }
}
