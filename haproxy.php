#!/usr/bin/php
<?php
/**
 * haproxy_check_mk is a PHP program to get stats from HAProxy via
 * UNIX Sockets
 *
 * haproxy_check_mk was written by Andrea Tartaglia, 2014 and is distributed
 * via GitHub at https://github.com/shaps/haproxy_check_mk
 *
 *  @author Andrea Tartaglia - me@andreatartaglia.com
 *  @copyright Andrea Tartaglia, 2014
 *  @license GNU GPLv3
 *
 * This file is part of haproxy_check_mk.
 *
 * haproxy_check_mk is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * haproxy_check_mk is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with haproxy_check_mk.  If not, see <http://www.gnu.org/licenses/>.
 */


###### CONFIG ############

$warn = 70;
$crit = 90;
$socket = '/var/lib/haproxy/stats';
$default_limit = 200;

###### END CONFIG ########


require_once('HAProxyAPI/autoload.php');

$exec = new HAProxy\Executor($socket,HAProxy\Executor::SOCKET); // Create a new HAProxy Executor through the socket

$stats = HAProxy\Stats::get($exec); // Create the stats object


foreach($stats->getBackendNames() as $backend){ 			// Iterate through the current config
  $servers = $stats->getServerNames($backend); 				// Get the server names for the current backend
  if($servers[count($servers)-1] == 'BACKEND'){ 			// If this is really a backend, then proceed, skip otherwise
    $total_limit = 0;
    $total_curr = 0;										// We're in a new backend, reset the stats
    $alert_crit = false;
    $alert_warn = false;
    $out = "";


    foreach($servers as $server) {							// Iterate through the servers in this backend
      if($server != "BACKEND"){								// HAProxy returns also BACKEND as servername, skip it
        $server = $stats->getServiceStats($backend,$server);
        $total_curr += floatval($server->session->scur);
        if ($server->session->slim != "" ){					// If a limit is set, use it, use the default otherwise
          $total_limit += floatval($server->session->slim);
        }else{
          $total_limit += $default_limit;
        }
      }
    }
    if ($total_limit == 0 ){								// If the limit is 0 then there is no usage percentage, calculate it otherwise
      $usage_p = 0;
    }else{
      $usage_p = ((floatval($total_curr)/floatval($total_limit))*100);
      $warn_lim = intval(($warn/100)*$total_limit);
      $crit_lim = intval(($crit/100)*$total_limit);

    }

    $usage_p = round($usage_p,2);

	// Build the output

    if ($usage_p > $crit) {
      $alert_crit = true;
      $out = " Session CRIT - Total connections: ".$total_curr." - ".$usage_p.'%';
    }elseif ($usage_p > $warn) {
      $alert_warn = true;
      $out = "Session WARN - Total connections: ".$total_curr." - ".$usage_p.'%';
    }else {
      $out = "Session OK - Total connections: ".$total_curr." - ".$usage_p.'%';
    }
    $perfdata = "connections=".$total_curr.";".$warn_lim.";".$crit_lim;

	// Print the statistics

    if ($alert_crit) {
      echo "2 HAProxy_".$backend." ".$perfdata." CRITICAL - ".$out."\n";
    }elseif ($alert_warn) {
      echo "1 HAProxy_".$backend." ".$perfdata." WARNING - ".$out."\n";
    }else{
      echo "0 HAProxy_".$backend." ".$perfdata." OK - ".$out."\n";
    }
  }
}

?>
