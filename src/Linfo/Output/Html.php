<?php

namespace Linfo\Output;

use Linfo\Linfo;
use Linfo\Common;
use Linfo\Meta\Errors;
use Linfo\Meta\Timer;

class Html implements Output
{
    protected $linfo;

    public function __construct(Linfo $linfo)
    {
        $this->linfo = $linfo;
    }

    /**
     * Create a progress bar looking thingy. Put into a function here
     * as its being increasingly used elsewhere. TODO refactor linfo and
     * stop leaving functions in global namespace.
     * @param $percent
     * @param bool $text
     * @return string
     */
    public static function generateBarChart($percent, $text = false)
    {
        return '
			<div class="new_bar_outer">
				<div class="new_bar_bg" style="width: '.$percent.'%; "></div>
				<div class="new_bar_text">'.($text ?: $percent.'%').'</div>
			</div>
		';
    }

    public static function fadedText($text)
    {
        return '<span class="faded">'.$text.'</span>';
    }

    // Create a table out of an array. Mostly used by extensions
    /*
        Example array structure:

        $structure = array(
            'root_title' => 'Name',
            'rows' => array(
                01 = array(
                    'type' => 'header',
                    'columns' => array(
                        'Column 1',
                        'Column 2',
                        // OR array(colspannumber, 'value')
                    )
                )
                02 => array(
                    'type' => 'values',
                    'columns' => array(
                        'Value 1',
                        'Value 2',
                        // OR array(colspannumber, 'value')
                    )
                )
            )
        );
    */
    public static function createTable($structure)
    {

        // Start it off
        $html = '
	<div class="infoTable">
		<h2>'.$structure['root_title'].'</h2>
		<table>';

        // Go throuch each row
        foreach ($structure['rows'] as $row) {

            // Let stuff be killed
            $row['columns'] = array_filter($row['columns'], function($value) {
                return $value !== false;
             });

            // Ignore this if it's empty
            if (empty($row['columns'])) {
                continue;
            }

            // Start the typical tr
            $html .= '
			<tr>';

            // Is this row a header?
            if ($row['type'] == 'header') {
                foreach ($row['columns'] as $v) {
                    $html .= is_array($v) ? '
				<th colspan="'.$v[0].'"'.(array_key_exists('2', $v) ? ' style="width: '.$v[2].';"' : '').'>'.$v[1].'</th>' : '
				<th>'.$v.'</th>';
                }
            }

            // Or is it a row saying nothing was found?
            elseif ($row['type'] == 'none') {
                foreach ($row['columns'] as $v) {
                    $html .= is_array($v) ? '
				<td colspan="'.$v[0].'" class="none">'.$v[1].'</td>' : '
				<td class="none">'.$v.'</td>';
                }
            }

            // Or is it values?
            elseif ($row['type'] == 'values') {
                foreach ($row['columns'] as $v) {
                    $html .= is_array($v) ? '
				<td colspan="'.$v[0].'">'.$v[1].'</td>' : '
				<td>'.$v.'</td>';
                }
            }

            // End the usual tr
            $html .= '
			</tr>';
        }

        // Closing tags
        $html .= '
		</table>
	</div>';

        // Give it
        return $html;
    }

    public function output()
    {
        $lang = $this->linfo->getLang();
        $settings = $this->linfo->getSettings();
        $info = $this->linfo->getInfo();
        $appName = $this->linfo->getAppName();
        $version = $this->linfo->getVersion();

        // Fun icons
        $show_icons = array_key_exists('icons', $settings) ? !empty($settings['icons']) : true;
        $os_icon = $info['OS'] == 'Windows' ? 'windows' : strtolower(str_replace(' ', '', current(explode('(', $info['OS']))));
        $distro_icon = $info['OS'] == 'Linux' && is_array($info['Distro']) && $info['Distro']['name'] ? strtolower(str_replace(' ', '', $info['Distro']['name'])) : false;

        // Start compressed output buffering. Try to not do this if we've had errors or otherwise already outputted stuff
        if ((!function_exists('error_get_last') || !error_get_last()) && (!isset($settings['compress_content']) || $settings['compress_content'])) {
            ob_end_clean();
            ob_start(isset($settings['gzip']) && $settings['gzip'] && function_exists('ob_gzhandler') ? 'ob_gzhandler' : null);
        }

        // If we're allowed to change themes client-side, generate a list of custom ones to use as a white list
        $allowed_themes = ['default'];
        if ($settings['allow_changing_themes']) {
            foreach (glob('layout/theme_*.css') as $theme_path) {
                $theme_name = substr(basename($theme_path), 6, -4);
                if ($theme_name != 'default') {
                    $allowed_themes[] = $theme_name;
                }
            }
        }

        // See if we allow changing themes in the frontend and the theme specified exists and it doesn't have nasty chars in it
        if ($settings['allow_changing_themes'] && isset($_COOKIE['linfo-theme']) && in_array($_COOKIE['linfo-theme'], $allowed_themes) && preg_match('/^[a-z0-9-_]+$/i', $_COOKIE['linfo-theme']) ) {
            $chosen_theme = $_COOKIE['linfo-theme'];
        }

        // See if we have a specific theme file installed
        elseif (isset($settings['theme']) && strpos($settings['theme'], '..') === false && file_exists('layout/theme_'.$settings['theme'].'.css')) {
            $chosen_theme = $settings['theme'];
        }

        // Else default to default theme
        else {
            $chosen_theme = 'default';
        }


    // Proceed to letting it all out
    echo '<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>'.$appName.' - '.$info['HostName'].'</title>
	<link href="./layout/favicon.ico" type="image/x-icon" rel="shortcut icon">
	<link id="themeCssLink" href="./layout/theme_'.$chosen_theme.'.css" rel="stylesheet">'.($show_icons ? '
	<link href="./layout/icons.css" rel="stylesheet">' : ''
    ).'
	<script src="./layout/scripts.min.js"></script>
	<meta name="generator" content="'.$appName.' ('.$version.')">
	<meta name="author" content="Joseph Gillotti &amp; friends">
	<!--[if lt IE 8]>
	<link href="./layout/old_ie.css" type="text/css" rel="stylesheet">
	<![endif]-->
	<link rel="stylesheet" type="text/css" href="./layout/mobile.css" media="screen and (max-width: 640px)">
</head>
<body id="info">
<div id="header">';

  if ($settings['allow_changing_themes']) {
  echo '
  <div id="themeChanger">Theme: <select id="themeChangerSelect">';

    foreach ($allowed_themes as $theme_option)
      echo '<option'.($theme_option == $chosen_theme ? ' selected' : '').'>'.$theme_option.'</option>';

  echo '</select>
  </div>
  ';
  }

echo '
	<h1>'.$info['HostName'].'</h1>
	<div class="subtitle">'.$lang['header'].'</div>
</div>
<div class="col2">
	<div class="col">
		<div class="infoTable">
			<h2>'.$lang['core'].'</h2>
			<table>';

    // Linfo Core. Decide what to show.
    $core = [];

    // OS? (with icon, if we have it)
    if (!empty($settings['show']['os'])) {
        $core[] = array($lang['os'], ($show_icons && (file_exists($this->linfo->getLocalDir().'layout/icons/os_'.$os_icon.'.gif') || file_exists($this->linfo->getLocalDir().'layout/icons/os_'.$os_icon.'.png')) ? '<span class="icon icon_os_'.$os_icon.'"></span>' : '').$info['OS']);
    }

    // Distribution? (with icon, if we have it)
    if (!empty($settings['show']['distro']) && array_key_exists('Distro', $info) && is_array($info['Distro'])) {
        $core[] = array($lang['distro'], ($show_icons && $distro_icon && (file_exists($this->linfo->getLocalDir().'layout/icons/distro_'.$distro_icon.'.gif') || file_exists($this->linfo->getLocalDir().'layout/icons/distro_'.$distro_icon.'.png')) ? '<span class="icon icon_distro_'.$distro_icon.'"></span>' : '').$info['Distro']['name'].($info['Distro']['version'] ? ' - '.$info['Distro']['version'] : ''));
    }

    // Virtualization
    if (!empty($settings['show']['virtualization']) && isset($info['virtualization']) && !empty($info['virtualization'])) {
        $vmval = false;

        if ($info['virtualization']['type'] == 'guest') {
            $vmval = '<span class="icon icon_vm_'.str_replace('/', '_', strtolower($info['virtualization']['method'])).'"></span>'.$info['virtualization']['method'].' '.$lang['guest'];
        } elseif ($info['virtualization']['type'] == 'host') {
            $vmval = '<span class="icon icon_vm_'.str_replace('/', '_', strtolower($info['virtualization']['method'])).'"></span>'.$info['virtualization']['method'].' '.$lang['host'];
        }

        if ($vmval) {
            $core[] = array($lang['virtualization'], $vmval);
        }
    }

    // Kernel
    if (!empty($settings['show']['kernel'])) {
        $core[] = array($lang['kernel'], $info['Kernel']);
    }

    // Model?
    if (!empty($settings['show']['model']) && array_key_exists('Model', $info) && !empty($info['Model'])) {
        $core[] = array($lang['model'], $info['Model']);
    }

    // IP
    if (!isset($settings['show']['ip']) || !empty($settings['show']['ip'])) {
        $core[] = array($lang['accessed_ip'], $info['AccessedIP']);
    }

    // Uptime
    if (!empty($settings['show']['uptime']) && $info['UpTime']) {
        $core[] = array($lang['uptime'],
            $info['UpTime']['text'].
                (isset($info['UpTime']['bootedTimestamp']) && $info['UpTime']['bootedTimestamp'] ? '; booted '.date($settings['dates'], $info['UpTime']['bootedTimestamp']) : ''), );
    }

    // Hostname
    if (!empty($settings['show']['hostname'])) {
        $core[] = array($lang['hostname'], $info['HostName']);
    }

    //Web server
    if (!empty($settings['show']['webservice'])) {
        $core[] = array($lang['webservice'], $info['webService']);
    }

    //Php version
    if (!empty($settings['show']['phpversion'])) {
        $core[] = array($lang['phpversion'], $info['phpVersion']);
    }

    // The CPUs
    if (!empty($settings['show']['cpu'])) {
        $cpus = [];

        foreach ((array) $info['CPU'] as $cpu) {
            $cpu_html =
                (array_key_exists('Vendor', $cpu) && !empty($cpu['Vendor']) ? $cpu['Vendor'].' - ' : '').
                $cpu['Model'].
                (array_key_exists('MHz', $cpu) ?
                    ($cpu['MHz'] < 1000 ? ' ('.$cpu['MHz'].' MHz)' : ' ('.round($cpu['MHz'] / 1000, 3).' GHz)') : '').
                        (array_key_exists('usage_percentage', $cpu) ? ' ('.$cpu['usage_percentage'].'%)' : '');

            if (array_key_exists('usage_percentage', $cpu)) {
                $cpu_html = '<div class="new_bar_left" style="margin-top: 3px; margin-bottom: 3px;">'.self::generateBarChart($cpu['usage_percentage'], $cpu_html).'</div>';
            } else {
                $cpu_html .= '<br>';
            }

            $cpus[] = $cpu_html;
        }
        $core[] = array('CPUs ('.count($info['CPU']).')', implode('', $cpus));
    }

    // CPU Usage?
    if (!empty($settings['cpu_usage']) && isset($info['cpuUsage']) && $info['cpuUsage'] !== false) {
        $core[] = array($lang['cpu_usage'],self::generateBarChart($info['cpuUsage']));
    }

    // System Load
    if (!empty($settings['show']['load'])) {
        $core[] = array($lang['load'],implode(' ', (array) $info['Load']));
    }

    // CPU architecture. Permissions goes hand in hand with normal CPU
    if (!empty($settings['show']['cpu']) && array_key_exists('CPUArchitecture', $info)) {
        $core[] = array($lang['cpu_arch'], $info['CPUArchitecture']);
    }

    // We very well may not have process stats
    if (!empty($settings['show']['process_stats']) && $info['processStats']['exists']) {

        // Different os' have different keys of info
        $proc_stats = [];

        // Load the keys
        if (array_key_exists('totals', $info['processStats']) && is_array($info['processStats']['totals'])) {
            foreach ($info['processStats']['totals'] as $k => $v) {
                $proc_stats[] = $k.': '.number_format($v);
            }
        }

        // Total as well
        $proc_stats[] = 'total: '.number_format($info['processStats']['proc_total']);

        // Show them
        $core[] = array($lang['processes'], implode('; ', $proc_stats));

        // We might not have threads
        if ($info['processStats']['threads'] !== false) {
            $core[] = array($lang['threads'], number_format($info['processStats']['threads']));
        }
    }

    // Users with active shells
    if (!empty($settings['show']['numLoggedIn']) && array_key_exists('numLoggedIn', $info) && $info['numLoggedIn']) {
        $core[] = array($lang['numLoggedIn'], $info['numLoggedIn']);
    }

    // Show
    foreach ($core as $val) {
        echo '
				<tr>
					<th>'.$val[0].'</th>
					<td>'.$val[1].'</td>
				</tr>
				';
    }

        echo '
			</table>
		</div>';

    // Show memory?
    if (!empty($settings['show']['ram'])) {
        echo '
		<div class="infoTable">
			<h2>'.$lang['memory'].'</h2>
			<table>
				<colgroup>
					<col style="width: 12%;" />
					<col style="width: 23%;" />
					<col style="width: 23%;" />
					<col style="width: 23%;" />
					<col style="width: 23%;" />
				</colgroup>
				<tr>
					<th>'.$lang['type'].'</th>
					<th>'.$lang['size'].'</th>
					<th>'.$lang['used'].'</th>
					<th>'.$lang['free'].'</th>
					<th>'.$lang['percent_used'].'</th>
				</tr>
				<tr>
					<td>'.$info['RAM']['type'].'</td>
					<td>'.Common::byteConvert($info['RAM']['total']).'</td>
					<td>'.Common::byteConvert($info['RAM']['total'] - $info['RAM']['free']).'</td>
					<td>'.Common::byteConvert($info['RAM']['free']).'</td>
					<td>'.self::generateBarChart(round(($info['RAM']['total'] - $info['RAM']['free']) * 100 / $info['RAM']['total'])).'</td>
				</tr>';
        $have_swap = (isset($info['RAM']['swapFree']) || isset($info['RAM']['swapTotal']));
        if ($have_swap && !empty($info['RAM']['swapTotal'])) {
            // Show detailed swap info?
                    $show_detailed_swap = is_array($info['RAM']['swapInfo']) && count($info['RAM']['swapInfo']) > 0;
            echo'
					<tr>
						<td'.($show_detailed_swap ? ' rowspan="2"' : '').'>Swap</td>
						<td>'.Common::byteConvert(@$info['RAM']['swapTotal']).'</td>
						<td>'.Common::byteConvert(@$info['RAM']['swapTotal'] - $info['RAM']['swapFree']).'</td>
						<td>'.Common::byteConvert(@$info['RAM']['swapFree']).'</td>
						<td>'.self::generateBarChart(round(($info['RAM']['swapTotal'] - $info['RAM']['swapFree']) * 100 / $info['RAM']['swapTotal'])).'</td>
					</tr>';

                    // As in we have at least one swap device present. Show them.
                    if ($show_detailed_swap) {
                        echo '
						<tr>
							<td colspan="4">
								<table class="mini center">
									<colgroup>
										<col style="width: 25%;" />
										<col style="width: 25%;" />
										<col style="width: 25%;" />
										<col style="width: 25%;" />
									</colgroup>
									<tr>
										<th>'.$lang['device'].'</th>
										<th>'.$lang['type'].'</th>
										<th>'.$lang['size'].'</th>
										<th>'.$lang['used'].'</th>
									</tr>';
                        foreach ($info['RAM']['swapInfo'] as $swap) {
                            echo '
										<tr>
											<td>'.$swap['device'].'</td>
											<td>'.ucfirst($swap['type']).'</td>
											<td>'.Common::byteConvert($swap['size']).'</td>
											<td>'.Common::byteConvert($swap['used']).'</td>
										</tr>
										';
                        }
                        echo '
								</table>
							</td>
						</tr>';
                    }
        }

        echo '
			</table>
		</div>';
    }

    // Network Devices?
    if (!empty($settings['show']['network'])) {
        $show_type = array_key_exists('nic_type', $info['contains']) ? $info['contains']['nic_type'] : true;
        $show_speed = array_key_exists('nic_port_speed', $info['contains']) ? $info['contains']['nic_port_speed'] : true;
        echo '
		<div class="infoTable network_devices">
			<h2>'.$lang['network_devices'].'</h2>
			<table>
				<tr>
					<th>'.$lang['device_name'].'</th>'.($show_type ? '
					<th>'.$lang['type'].'</th>' : '').($show_speed ? '
					<th>'.$lang['port_speed'].'</th>' : '').'
					<th>'.$lang['amount_sent'].'</th>
					<th>'.$lang['amount_received'].'</th>
					<th>'.$lang['state'].'</th>
				</tr>';

        if (count($info['Network Devices']) > 0) {
            foreach ($info['Network Devices'] as $device => $stats) {
                echo '
				<tr>
					<td>'.$device.'</td>'.($show_type ? '
					<td>'.$stats['type'].'</td>' : '').($show_speed ? '
					<td>'.(isset($stats['port_speed']) && $stats['port_speed'] !== false ? $stats['port_speed'].'Mb/s' : '').'</td>' : '').'
					<td>'.Common::byteConvert($stats['sent']['bytes']).'</td>
					<td>'.Common::byteConvert($stats['recieved']['bytes']).'</td>
					<td class="net_'.$stats['state'].'">'.ucfirst($stats['state']).'</td>
				</tr>';
            }
        } else {
            echo '<tr><td colspan="5" class="none">'.$lang['none_found'].'</td></tr>';
        }
        echo '
			</table>
		</div>';
    }

    // Show temps?
    if (!empty($settings['show']['temps']) && count($info['Temps']) > 0) {
        echo '
		<div class="infoTable">
			<h2>'.$lang['temps_voltages'].'</h2>
			<table>
				<tr><th>'.$lang['path'].'</th><th>'.$lang['device'].'</th><th>'.$lang['value'].'</th></tr>
				';
        if (count($info['Temps']) > 0) {
            foreach ($info['Temps'] as $stat) {
                echo '
					<tr>
						<td>'.$stat['path'].'</td>
						<td>'.$stat['name'].'</td>
						<td>'.(
                            array_key_exists('bar', $stat) && $stat['bar'] && $stat['unit'] == '%' ?
                            '<div class="bar_chart">
								<div class="bar_inner" style="width: '.$stat['temp'].'%;">
									<div class="bar_text">
										'.($stat['temp'] > -1 ? $stat['temp'] : '?').'%
									</div>
								</div>
							</div>
							':
                        $stat['temp'].' '.$stat['unit']).'</td>
					</tr>
					';
            }
        } else {
            echo '<tr><td colspan="3" class="none">'.$lang['none_found'].'</td></tr>';
        }
        echo '
			</table>
		</div>';
    }

    // Show battery?
    if (!empty($settings['show']['battery']) && count($info['Battery']) > 0) {
        echo '
		<div class="infoTable">
			<h2>'.$lang['batteries'].'</h2>
			<table>
				<tr>
					<th>'.$lang['device'].'</th>
					<th>'.$lang['state'].'</th>
					<th>'.$lang['charge'].' %</th>
				</tr>
				';
        foreach ($info['Battery'] as $bat) {
            echo '
					<tr>
						<td>'.$bat['device'].'</td>
						<td>'.$bat['state'].'</td>
						<td>'.self::generateBarChart((int) $bat['percentage'], $bat['percentage'] > -1 ? $bat['percentage'].'%' : 'N/A').'</td>
					</tr>
					';
        }
        echo '
			</table>
		</div>';
    }

    // Show services?
    if (!empty($settings['show']['services']) && count($info['services']) > 0) {
        echo '
		<div class="infoTable">
			<h2>'.$lang['services'].'</h2>
			<table>
				<tr>
					<th>'.$lang['service'].'</th>
					<th>'.$lang['state'].'</th>
					<th>'.$lang['pid'].'</th>
					<th>Threads</th>
					<th>'.$lang['memory_usage'].'</th>
				</tr>
				';

        // Show them
        foreach ($info['services'] as $service => $state) {
            $state_parts = explode(' ', $state['state'], 2);
            // set as empty if is not set
            if(!isset($state['pid'])){
                $state['pid'] = '';
                if(isset($state['name'])){
                    $state['pid'] = $state['name'];
                }
            }
            if(!isset($state['threads'])){
                $state['threads'] = '';
            }
            if(!isset($state['memory_usage'])){
                $state['memory_usage'] = '';
            }
            echo '
				<tr>
					<td>'.$service.'</td>
					<td>
						<span class="service_'.strtolower($state_parts[0]).'">'.$state_parts[0].'</span>
						'.(array_key_exists(1, $state_parts) ? self::fadedText($state_parts[1]).'</span>' : '').'</td>
					<td>'.$state['pid'].'</td>
					<td>',$state['threads'] ? $state['threads'] : '?','</td>
					<td>',$state['memory_usage'] ? Common::byteConvert($state['memory_usage']) : '?','</td>
				</tr>
			';
        }

        echo '
			</table>
		</div>';
    }

        echo '
	</div>
	<div class="col">';

    // Show hardware?
    if (!empty($settings['show']['devices'])) {

        // Don't show vendor?
        $show_vendor = array_key_exists('hw_vendor', $info['contains']) ? ($info['contains']['hw_vendor'] === false ? false : true) : true;

        echo '
		<div class="infoTable">
			<h2>'.$lang['hardware'].'</h2>
			<table>
				<tr>
					<th>'.$lang['type'].'</th>
					',($show_vendor ? '<th>'.$lang['vendor'].'</th>' : ''),'
					<th>'.$lang['device'].'</th>
				</tr>
				';
        $num_devs = count($info['Devices']);
        if ($num_devs > 0) {
            for ($i = 0; $i < $num_devs; ++$i) {
                echo '
				<tr>
					<td class="center">'.$info['Devices'][$i]['type'].'</td>
					',$show_vendor ? '<td>'.($info['Devices'][$i]['vendor'] ? $info['Devices'][$i]['vendor'] : 'Unknown').'</td>' : '','
					<td>'.$info['Devices'][$i]['device'].'</td>
				</tr>';
            }
        } else {
            echo '<tr><td colspan="3" class="none">'.$lang['none_found'].'</td></tr>';
        }
        echo '
			</table>
		</div>';
    }

    // Show drives?
    if (!empty($settings['show']['hd'])) {

        // Should we not show the Reads and Writes columns?
        $show_stats = array_key_exists('drives_rw_stats', $info['contains']) ? ($info['contains']['drives_rw_stats'] === false ? false : true) : true;

        // Or vendor columns?
        $show_vendor = array_key_exists('drives_vendor', $info['contains']) ? ($info['contains']['drives_vendor'] === false ? false : true) : true;

        echo '
		<div class="infoTable">
			<h2>'.$lang['drives'].'</h2>
			<table>
				<tr>
					<th>'.$lang['path'].'</th>
					',$show_vendor ? '<th>'.$lang['vendor'] : '','</th>
					<th>'.$lang['name'].'</th>
					',$show_stats ? '<th>'.$lang['reads'].'</th>
					<th>'.$lang['writes'].'</th>' : '','
					<th>'.$lang['size'].'</th>
				</tr>';
        if (count($info['HD']) > 0) {
            foreach ($info['HD'] as $drive) {
                echo '
				<tr>
					<td>'.$drive['device'].'</td>
					',$show_vendor ? '<td>'.($drive['vendor'] ? $drive['vendor'] : $lang['unknown']).'</td>' : '','
					<td>',$drive['name'] ? $drive['name'] : $lang['unknown'],'</td>
					', $show_stats ? '<td>'.($drive['reads'] !== false ? number_format($drive['reads']) : $lang['unknown']).'</td>
					<td>'.($drive['writes'] !== false ? number_format($drive['writes']) : $lang['unknown']).'</td>' : '','
					<td>',$drive['size'] ? Common::byteConvert($drive['size']) : $lang['unknown'],'</td>
				</tr>';

                // If we've got partitions for this drive, show them too
                if (array_key_exists('partitions', $drive) && is_array($drive['partitions']) && count($drive['partitions']) > 0) {
                    echo '
				<tr>
					<td colspan="6">';

                    // Each
                    foreach ($drive['partitions'] as $partition) {
                        echo '
						&#9492; '.(isset($partition['number']) ? $drive['device'].$partition['number'] : $partition['name']).' - '.Common::byteConvert($partition['size']).'<br />';
                    }

                    echo '
					</td>
				</tr>
				';
                }
            }
        } else {
            echo '<tr><td colspan="6" class="none">'.$lang['none_found'].'</td></tr>';
        }

        echo '
			</table>
		</div>';
    }

    // Show sound card stuff?
    if (!empty($settings['show']['sound']) && count($info['SoundCards']) > 0) {
        echo '
		<div class="infoTable">
			<h2>'.$lang['sound_cards'].'</h2>
			<table>
				<tr>
					<th>'.$lang['number'].'</th>
					<th>'.$lang['vendor'].'</th>
					<th>'.$lang['card'].'</th>
				</tr>';
        foreach ($info['SoundCards'] as $card) {
            if (empty($card['vendor'])) {
                $card['vendor'] = 'Unknown';
            }
            echo '
				<tr>
					<td>'.$card['number'].'</td>
					<td>'.$card['vendor'].'</td>
					<td>'.$card['card'].'</td>
				</tr>';
        }
        echo '
			</table>
		</div>
		';
    }

        echo '
	</div>
</div>';

    // Show file system mounts?
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
        $addcolumns = 0;
        if ($settings['show']['mounts_options']) {
            $addcolumns++;
        }
        if ($has_devices) {
            $addcolumns++;
        }
        if ($has_labels) {
            $addcolumns++;
        }
        if ($has_types) {
            $addcolumns++;
        }
        echo '
<div class="infoTable filesystem_mounts">
	<h2>'.$lang['filesystem_mounts'].'</h2>
	<table>
		<tr>';
        if ($has_types) {
            echo '<th>'.$lang['type'].'</th>';
        }
        if ($has_devices) {
            echo '<th>'.$lang['device'].'</th>';
        }
        echo '<th>'.$lang['mount_point'].'</th>';
        if ($has_labels) {
            echo '<th>'.$lang['label'].'</th>';
        }
        echo'
			<th>'.$lang['filesystem'].'</th>',$settings['show']['mounts_options'] ? '
			<th>'.$lang['mount_options'].'</th>' : '','
			<th>'.$lang['size'].'</th>
			<th>'.$lang['used'].'</th>
			<th>'.$lang['free'].'</th>
			<th style="width: 12%;">'.$lang['percent_used'].'</th>
		</tr>
		';

        // Calc totals
        $total_size = 0;
        $total_used = 0;
        $total_free = 0;

        // Don't add totals for duplicates. (same filesystem mount twice in different places)
        $done_devices = [];

        // Are there any?
        if (count($info['Mounts']) > 0) {

            // Go through each
            foreach ($info['Mounts'] as $mount) {

                // Only add totals for this device if we haven't already
                if (!in_array($mount['device'], $done_devices)) {
                    $total_size += $mount['size'];
                    $total_used += $mount['used'];
                    $total_free += $mount['free'];
                    if (!empty($mount['device'])) {
                        $done_devices[] = $mount['device'];
                    }
                }

                // Possibly don't show this twice
                elseif (array_key_exists('duplicate_mounts', $settings['show']) && empty($settings['show']['duplicate_mounts'])) {
                    continue;
                }

                // If it's an NFS mount it's likely in the form of server:path (without a trailing slash),
                // but if the path is just / it likely just shows up as server:,
                // which is vague. If there isn't a /, add one
                if (preg_match('/^.+:$/', $mount['device']) == 1) {
                    $mount['device'] .= DIRECTORY_SEPARATOR;
                }

                echo '<tr>';
                if ($has_types) {
                    echo '<td>'.$mount['devtype'].'</td>';
                }
                if ($has_devices) {
                    echo '<td>'.$mount['device'].'</td>';
                }
                echo '<td>'.$mount['mount'].'</td>';
                if ($has_labels) {
                    echo '<td>'.$mount['label'].'</td>';
                }
                echo'
					<td>'.$mount['type'].'</td>', $settings['show']['mounts_options'] ? '
					<td>'.(empty($mount['options']) ? '<em>unknown</em>' : '<ul><li>'.implode('</li><li>', $mount['options']).'</li></ul>').'</td>' : '','
					<td>'.Common::byteConvert($mount['size']).'</td>
					<td>'.Common::byteConvert($mount['used']).
                    ($mount['used_percent'] !== false ? ' <span class="perc">('.$mount['used_percent'].'%)</span>' : '').'</td>
					<td>'.Common::byteConvert($mount['free']).
                    ($mount['free_percent'] !== false ? ' <span class="perc">('.$mount['free_percent'].'%)</span>' : '').'</td>
					<td>
						'.self::generateBarChart((int) $mount['used_percent'], $mount['used_percent'] ? $mount['used_percent'].'%' : 'N/A').'
					</td>
				</tr>';
            }
        } else {
            echo '<tr><td colspan="',6 + $addcolumns,'" class="none">None found</td></tr>';
        }

        // Show totals and finish table
        $total_used_perc = $total_size > 0 && $total_used > 0 ? round($total_used / $total_size, 2) * 100 : 0;
        echo '
		<tr class="alt">
			<td colspan="',2 + $addcolumns,'">Totals: </td>
			<td>'.Common::byteConvert($total_size).'</td>
			<td>'.Common::byteConvert($total_used).'</td>
			<td>'.Common::byteConvert($total_free).'</td>
			<td>
				'.self::generateBarChart($total_used_perc, $total_used_perc.'%').'
			</td>
		</tr>
	</table>
</div>';
    }

    // Show RAID Arrays?
    if (!empty($settings['show']['raid']) && count($info['Raid']) > 0) {
        echo '
<div class="infoTable drives">
	<h2>'.$lang['raid_arrays'].'</h2>
	<table>
		<colgroup>
			<col style="width: 10%;" />
			<col style="width: 30%;" />
			<col style="width: 10%;" />
			<col style="width: 10%;" />
			<col style="width: 30%;" />
			<col style="width: 10%;" />
		</colgroup>
		<tr>
			<th>'.$lang['name'].'</th>
			<th>'.$lang['level'].'</th>
			<th>'.$lang['status'].'</th>
			<th>'.$lang['size'].'</th>
			<th>'.$lang['devices'].'</th>
			<th>'.$lang['active'].'</th>
		</tr>
		';
        if (count($info['Raid']) > 0) {
            foreach ($info['Raid'] as $raid) {
                $active = explode('/', $raid['count']);
                // http://en.wikipedia.org/wiki/Standard_RAID_levels
                switch ($raid['level']) {
                    case 0:
                        $type = 'Stripe';
                    break;
                    case 1:
                        $type = 'Mirror';
                    break;
                    case 10:
                        $type = 'Mirrored Stripe';
                    break;
                    case 5:
                    case 6:
                        $type = 'Distributed Parity Block-Level Striping';
                    break;
                    default:
                        $type = false;
                    break;
                }
                echo '
				<tr>
				<td>'.$raid['device'].'</td>
				<td>'.$raid['level'].($type ? ' <span class="caption">('.$type.')</span>' : '').'</td>
				<td>'.ucfirst($raid['status']).'</td>
				<td>'.$raid['size'].'</td>
				<td><table class="mini center margin_auto"><tr><th>'.$lang['device'].'</th><th>'.$lang['state'].'</th></tr>';

                foreach ($raid['drives'] as $drive) {
                    echo '<tr><td>'.$drive['drive'].'</td><td class="raid_'.$drive['state'].'">'.ucfirst($drive['state']).'</td></tr>';
                }

                echo '</table></td>
				<td>'.$active[1].'/'.$active[0].'</td>
				</tr>
				';
            }
        } else {
            echo '<tr><td colspan="6" class="none">'.$lang['none_found'].'</td></tr>';
        }

        echo '
	</table>
</div>';
    }

    // Feel like showing errors? Are there any even?
    if (!empty($settings['show_errors']) && Errors::num() > 0) {
        echo '
	<div id="errorList" class="infoTable">
		<h2>'.$lang['error_head'].'</h2>
		<table>
			<tr>
				<th>'.$lang['from_where'].'</th>
				<th>'.$lang['message'].'</th>
			</tr>';

        foreach (Errors::show() as $error) {
            echo '
				<tr>
					<td>'.$error[0].'</td>
					<td>'.$error[1].'</td>
				</tr>
				';
        }

        echo '
		</table>
	</div>
	';
    }

    // Additional extensions
    if (count($info['extensions']) > 0) {
        foreach ($info['extensions'] as $ext) {
            if (is_array($ext) && count($ext) > 0) {

                // Decide how to show something extra
                switch (array_key_exists('extra_type', $ext) && !empty($ext['extra_vals']) ? $ext['extra_type'] : false) {

                    // Table with a key->value table to the right of it
                    // Useful for stats or other stuff pertaining to
                    // the main info to the left
                    case 'k->v':
                        echo '
<div class="col2_side">
	<div class="col2_side_left">
	'.self::createTable($ext).'
	</div>
	<div class="col2_side_right">
		<div class="infoTable">
			<h2>'.$ext['extra_vals']['title'].'</h2>
			<table>';

            // Give each value
            foreach (array_filter($ext['extra_vals']['values']) as $v) {
                echo '
				<tr>
					<th>'.$v[0].'</th>
					<td>'.$v[1].'</td>
				</tr>';
            }
            echo'
			</table>
		</div>
	</div>
</div>
						';
                    break;

                    // Nothing extra; just the table
                    default:
                        echo self::createTable($ext);
                    break;
                }
            }
        }
    }

    // Feel like showing timed results?
    if (!empty($settings['timer'])) {
        echo '
	<div id="timerList" class="infoTable">
		<h2>'.$lang['timer'].'</h2>
		<table>
			<tr>
				<th>'.$lang['area'].'</th>
				<th>'.$lang['time_taken'].'</th>
			</tr>';

        foreach (Timer::getResults() as $result) {
            echo '
				<tr>
					<td>'.$result['id'].'</td>
					<td>'.round($result['duration'], 3).' '.$lang['seconds'].'</td>
				</tr>
				';
        }

        echo '
		</table>
	</div>
	';
    }

        echo '
<div id="foot">
	'.sprintf($lang['footer_app'], '<a href="https://github.com/jrgp/linfo"><em>'.$appName.'</em></a> ('.$version.')', round(microtime(true) - $this->linfo->getTimeStart(), 2)).'<br>
	<em>'.$appName.'</em> &copy; 2010 &ndash; '.(date('Y') > 2011 ? date('Y') : 2011).'
	Joseph Gillotti '.(date('m/d') == '06/03' ? ' (who turns '.(date('Y') - 1993).' today!)' : '').'&amp; friends. Source code licensed under MIT.
</div>
<div id="foot_time">
	<br />
	Generated on '.date($settings['dates']).'
</div>
<script>Linfo.init()</script>
</body>
</html>';
    }
}
