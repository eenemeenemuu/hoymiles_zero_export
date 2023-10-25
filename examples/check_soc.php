<?php
/* Example script for "temporary override min/max power values" functionality
 *
 * The $power_max_array construct below is due to the fact that an additional hardware battery protection
 * has been installed, which should not trigger prematurely due to voltage drops (caused by high currents).
 * This way we can smoothly ramp up and down the inverter on low SOC.
 *
 * If you don't have an additional battery protection (or don't want a soft ramp up/down)
 * you can simply set the shutdown value for the desired SOC, like: $power_max_array = [20 => 0];
 * */

$bms = 'https://my_bms/';   // URI of your BMS data (mandatory)
$pv = 'https://my_pv/';     // URI of your PV data (optional)

$power_max_array = [
    18 => 0,    // if SOC is below 18 % set inverter maximum power to 0 W (turn off)
    19 => 25,   // if SOC is below 19 % set inverter maximum power to 25 W
    20 => 50,   // and so on...
    21 => 75,
    22 => 100,
    23 => 125,
    24 => 150,
    25 => 175
];
$wait = 10;
$context = stream_context_create(['http' => ['timeout' => $wait]]);

$this_Hi = date('Hi');
while (date('Hi') == $this_Hi) { // run only in the current minute, so the cronjob must be run every minute
    $check_start = microtime(true);
    echo date('Y-m-d H:i:s ', $check_start);
    $bms_data = file_get_contents($bms, false, $context); // read BMS data
    if ($bms_data) {
        echo '[BMS: '.$bms_data.'] ';
        $bms_data = explode(",", $bms_data);
        $d = explode(".", $bms_data[0]);
        $t = explode(":", $bms_data[1]);
        if (time() < mktime($t[0], $t[1], $t[2], $d[1], $d[0], $d[2]) + 60) { // check if the data is not outdated
            $limit = [];
            if ($bms_data[3] >= 99) {   // if SOC is greater or equal 99 % (battery is full)
                $limit['min'] = 150;    // set inverter minimum power to 150 W
            } else {
                foreach ($power_max_array as $soc => $max) {
                    if ($bms_data[3] < $soc) {
                        $limit['max'] = $max;
                        if ($limit['max'] > 0) {
                            if ($bms_data[2] > 0) {     // battery is currently not discharging
                                $limit['max'] += 25;    // add 25 W to limit
                            }
                            if ($pv) {
                                $pv_data = file_get_contents($pv, false, $context); // read PV data
                                if ($pv_data) {
                                    echo '[PV: '.$pv_data.'] ';
                                    $pv_data = explode(",", $pv_data);
                                    $d = explode(".", $pv_data[0]);
                                    $t = explode(":", $pv_data[1]);
                                    if (time() < mktime($t[0], $t[1], $t[2], $d[1], $d[0], $d[2]) + 60) {   // check if the data is not outdated
                                        $limit['max'] = max($limit['max'], round($pv_data[2] * 0.9));       // if pv power is greater than the current limit, set inverter maximum power to 90 % of the current pv power
                                    }
                                }
                            }
                            break;
                        }
                    }
                }
            }
            if (!count($limit)) {
                echo 'nothing to do';
            } else {
                if (isset($limit['max'])) {
                    file_put_contents_print('inverter_power_max', $limit['max']);
                } 
                if (isset($limit['min'])) {
                    file_put_contents_print('inverter_power_min', $limit['min']);
                }
            }
        } else {
            echo 'timestamp too old';
        }
    } else {
        echo 'error retrieving data';
    }
    echo "\n";
    $seconds = $wait-(microtime(true)-$check_start);
    if ($seconds > 0) {
        usleep(round($seconds*1000000));
    }
}

function file_put_contents_print($filename, $data) {
    if (file_put_contents($filename, $data)) {
        echo "$filename: $data";
    } else {
        echo "error writing to file '$filename'";
    }
}
