<?php

/*
 * This file is part of Linfo (c) 2010 Joseph Gillotti.
 * 
 * Linfo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * Linfo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with Linfo.  If not, see <http://www.gnu.org/licenses/>.
 * 
*/


defined('IN_INFO') or exit;

// Show it all..
function showInfo($info, $settings) {
	
	// Start compressed output buffering
	ob_start('ob_gzhandler');

	// Proceed to letting it all out
	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<title>'.AppName.' - '.$info['HostName'].'</title>
	<link href="'.WEB_PATH.'layout/favicon.ico" type="image/x-icon" rel="shortcut icon" />
	<link href="'.WEB_PATH.'layout/styles.css" type="text/css" rel="stylesheet" />
</head>
<body id="info">
<h1>System Health: '.$info['HostName'].'</h1>
<div class="col2">
	<div class="col">
		<div class="infoTable">
			<h2>Core</h2>
			<table>';
			
			// Linfo Core. Decide what to show.
			$core = array();
			if (!empty($settings['show']['os']))
				$core[] = array('OS', $info['OS']);
			if (!empty($settings['show']['kernel']))
				$core[] = array('Kernel', $info['Kernel']);
			$core[] = array('Accessed IP', (isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : 'Unknown'));
			if (!empty($settings['show']['uptime']))
				$core[] = array('Uptime', $info['UpTime']);
			if (!empty($settings['show']['hostname']))
				$core[] = array('Hostname', $info['HostName']);
			if (!empty($settings['show']['cpu'])) {
				$cpus = '';
				foreach ($info['CPU'] as $cpu) 
					$cpus .= $cpu['Vendor'] . ' - ' . $cpu['Model'] . ' ('.$cpu['MHz'].' MHz)<br />';
				$core[] = array('CPUs ('.count($info['CPU']).')', $cpus);
			}
			if (!empty($settings['show']['load']))
				$core[] = array('Load', implode(' ', $info['Load']));
			
			// Show
			foreach ($core as $coreInfo)
				echo '
				<tr>
					<th>'.$coreInfo[0].'</th>
					<td>'.$coreInfo[1].'</td>
				</tr>
				';

			echo '
			</table>
		</div>';

		// Show memory?
		if (!empty($settings['show']['ram'])) {
			
			// Show detailed swap info?
			$show_detailed_swap = is_array($info['RAM']['swapInfo']) && count($info['RAM']['swapInfo']) > 0;
		echo '
		<div class="infoTable">
			<h2>Memory</h2>
			<table>
				<colgroup>
					<col style="width: 10%;" />
					<col style="width: 30%;" />
					<col style="width: 30%;" />
					<col style="width: 30%;" />
				</colgroup>
				<tr>
					<th>Type</th>
					<th>Free</th>
					<th>Used</th>
					<th>Size</th>
				</tr>
				<tr>
					<td>Real</td>
					<td>'.byte_convert($info['RAM']['free']).'</td>
					<td>'.byte_convert($info['RAM']['total'] - $info['RAM']['free']).'</td>
					<td>'.byte_convert($info['RAM']['total']).'</td>
				</tr>
				<tr>
					<td'.($show_detailed_swap ? ' rowspan="2"' : '').'>Swap</td>
					<td>'.byte_convert($info['RAM']['swapFree']).'</td>
					<td>'.byte_convert($info['RAM']['swapTotal'] - $info['RAM']['swapFree']).'</td>
					<td>'.byte_convert($info['RAM']['swapTotal']).'</td>
				</tr>';
				
				if ($show_detailed_swap) {
				echo '
				<tr>
					<td colspan="3">
						<table class="mini center">
							<colgroup>
								<col style="width: 25%;" />
								<col style="width: 25%;" />
								<col style="width: 25%;" />
								<col style="width: 25%;" />
							</colgroup>
							<tr>
								<th>Device</th>
								<th>Type</th>
								<th>Size</th>
								<th>Used</th>
							</tr>';
							foreach($info['RAM']['swapInfo'] as $swap)
								echo '
								<tr>
									<td>'.$swap['device'].'</td>
									<td>'.ucfirst($swap['type']).'</td>
									<td>'.byte_convert($swap['size']).'</td>
									<td>'.byte_convert($swap['used']).'</td>
								</tr>
								';
							echo '
						</table>
					</td>
				</tr>';
				}

				echo '
			</table>
		</div>';
		}

		// Network Devices?
		if (!empty($settings['show']['network'])) {
		echo '
		<div class="infoTable">
			<h2>Network Devices</h2>
			<table>
				<tr>
					<th>Device Name</th>
					<th>Type</th>
					<th>Amount Sent</th>
					<th>Amount Received</th>
					<th>State</th>
				</tr>
			';

			if (count($info['Network Devices']) > 0)
				foreach($info['Network Devices'] as $device => $stats)
					echo '
						<tr>
							<td>'.$device.'</td>
							<td>'.$stats['type'].'</td>
							<td>'.byte_convert($stats['sent']['bytes']).'</td>
							<td>'.byte_convert($stats['recieved']['bytes']).'</td>
							<td class="net_'.$stats['state'].'">'.ucfirst($stats['state']).'</td>
						</tr>';
			else
				echo '<tr><td colspan="3" class="none">None found</td></tr>';
			echo '
			</table>
		</div>';
		}

		// Show temps?
		if (!empty($settings['show']['temps'])) {
		echo '
		<div class="infoTable">
			<h2>Temps / Voltages</h2>
			<table>
				<tr><th>Path</th><th>Device</th><th>Value</th></tr>
				';
			if (count($info['Temps']) > 0)
				foreach($info['Temps'] as $stat) {
					echo '
					<tr>
						<td>'.$stat['path'].'</td>
						<td>'.$stat['name'].'</td>
						<td>'.$stat['temp'].$stat['unit'].'</td>
					</tr>
					';
				}
			else
				echo '<tr><td colspan="3" class="none">None found</td></tr>';
				echo '
			</table>
		</div>';
		}

		// Show battery?
		if (!empty($settings['show']['battery'])) {
		echo '
		<div class="infoTable">
			<h2>Batteries</h2>
			<table>
				<tr><th>Device</th><th>State</th><th>Charge %</th></tr>
				';
			if (count($info['Battery']) > 0)
				foreach ($info['Battery'] as $bat) {
					echo '
					<tr>
						<td>'.$bat['device'].'</td>
						<td>'.$bat['state'].'</td>
						<td>'.$bat['percentage'].($bat['percentage'] < 0 ? ' <span class="caption">(wtf?)</span>' : '').'</td>
					</tr>
					';
				}
			else
				echo '<tr><td colspan="3" class="none">None found</td></tr>';
				echo '
			</table>
		</div>';
		}

		echo '
	</div>
	<div class="col">';

		// Show hardware?
		if (!empty($settings['show']['devices'])) {
		echo '
		<div class="infoTable">
			<h2>Hardware</h2>
			<table>
				<tr>
					<th>Type</th>
					<th>Vendor</th>
					<th>Device</th>
				</tr>
				';
			if (count($info['Devices']) > 0)
				foreach($info['Devices'] as $device)
					echo '
						<tr>
							<td class="center">'.$device['type'].'</td>
							<td>'.$device['vendor'].'</td>
							<td>'.$device['device'].'</td>
						</tr>';
			else
				echo '<tr><td colspan="3" class="none">None found</td></tr>';
				echo '
			</table>
		</div>';
		}

		// Show drives?
		if (!empty($settings['show']['hd'])) {
		echo '
		<div class="infoTable">
			<h2>Drives</h2>
			<table>
				<tr>
					<th>Path</th>
					<th>Vendor</th>
					<th>Name</th>
					<th>Reads</th>
					<th>Writes</th>
					<th>Size</th>
				</tr>
				';
			if (count($info['HD']) > 0)
				foreach($info['HD'] as $drive)
					echo '
						<tr>
							<td>'.$drive['device'].'</td>
							<td>'.$drive['vendor'].'</td>
							<td>'.$drive['name'].'</td>
							<td>',$drive['reads'] !== false ? number_format($drive['reads']) : 'Unknown','</td>
							<td>',$drive['writes'] !== false ? number_format($drive['writes']) : 'Unknown','</td>
							<td>'.byte_convert($drive['size']).'</td>
						</tr>';
			else
				echo '<tr><td colspan="4" class="none">None found</td></tr>';
				echo '
			</table>
		</div>';
		}

		// Show sound card stuff?
		if (!empty($settings['show']['sound'])) {
		echo '
		<div class="infoTable">
			<h2>Sound Cards</h2>
			<table>
				<tr>
					<th>Number</th>
					<th>Card</th>
				</tr>';
				foreach ($info['SoundCards'] as $card)
					echo '<tr><td>'.$card['number'].'</td><td>'.$card['card'].'</td></tr>';
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
echo '
<div class="infoTable">
	<h2>Filesystem Mounts</h2>
	<table>
		<tr>
			<th>Device</th>
			<th>Mount Point</th>
			<th>Filesystem</th>
			<th>Size</th>
			<th>Used</th>
			<th>Free</th>
		</tr>
		';

		// Calc totals
		$total_size = 0;
		$total_used = 0;
		$total_free = 0;

		if (count($info['Mounts']) > 0)
			// Go through each
			foreach($info['Mounts'] as $mount) {
				$total_size += $mount['size'];
				$total_used += $mount['used'];
				$total_free += $mount['free'];
				echo '<tr>
					<td>'.$mount['device'].'</td>
					<td>'.$mount['mount'].'</td>
					<td>'.$mount['type'].'</td>
					<td>'.byte_convert($mount['size']).'</td>
					<td>'.byte_convert($mount['used']).
					' <span class="perc">('.($mount['used_percent'] !== false ? $mount['used_percent'] : 'N/A').'%)</span></td>
					<td>'.byte_convert($mount['free']).
					' <span class="perc">('.($mount['free_percent'] !== false ? $mount['free_percent'] : 'N/A').'%)</span></td>
				</tr>';
			}
		else
			echo '<tr><td colspan="6" class="none">None found</td></tr>';

		// Show totals and finish table
		echo '
		<tr class="alt">
			<td colspan="3">Totals: </td>
			<td>'.byte_convert($total_size).'</td>
			<td>'.byte_convert($total_used).'</td>
			<td>'.byte_convert($total_free).'</td>
		</tr>
	</table>
</div>';
}

// Show RAID Arrays?
if (!empty($settings['show']['raid'])) {
echo '
<div class="infoTable">
	<h2>Raid Arrays</h2>
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
			<th>Name</th>
			<th>Level</th>
			<th>Status</th>
			<th>Blocks</th>
			<th>Devices</th>
			<th>Active</th>
		</tr>
		';
		if (count($info['Raid']) > 0)
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
				<td>'.$raid['blocks'].'</td>
				<td><table class="mini center margin_auto"><tr><th>Device</th><th>State</th></tr>';
				
				foreach ($raid['drives'] as $drive)
					echo '<tr><td>'.$drive['drive'].'</td><td class="raid_'.$drive['state'].'">'.ucfirst($drive['state']).'</td></tr>';

				echo '</table></td>
				<td>'.$active[1].'/'.$active[0].'</td>
				</tr>
				';
			}
		else
			echo '<tr><td colspan="6" class="none">None found</td></tr>';

		echo '
	</table>
</div>';
}

// Feel like showing errors? Are there any even?
if (!empty($settings['show_errors']) && LinfoError::Fledging()->num() > 0) {

	echo '
	<div id="errorList" class="infoTable">
		<h2>Data gathering errors</h2>
		<table>
			<tr>
				<th>From Where</th>
				<th>Message</th>
			</tr>';

			foreach (LinfoError::Fledging()->show() as $error) {
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

echo '
<div id="foot">
	Generated by <a href="http://linfo.sf.net"><em>'.VERSION.'</em></a>  in '.round(microtime(true) - TIME_START,2).' seconds.<br />
	<em>'.AppName.'</em> &copy; 2010 Joseph Gillotti. Source code licensed under GPL.
</div>
</body>
</html>';

// End output buffering
ob_end_flush();
}
