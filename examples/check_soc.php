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
    $data = file_get_contents('https://my_bms/', false, $context); // read bms data
    if ($data) {
        print_r('['.$data.'] ');
        $data = explode(",", $data); // comma separated data
        $d = explode(".", $data[0]); // date in format d.m.Y
        $t = explode(":", $data[1]); // time in format H:i:s
        if (time() < mktime($t[0], $t[1], $t[2], $d[1], $d[0], $d[2]) + 60) { // check if the data is not outdated
            $limit = [];
            if ($data[3] >= 99) {   // if SOC is greater or equal 99 % (battery is full)
                $limit['min'] = 150;    // set inverter minimum power to 150 W
            } else {
                foreach ($power_max_array as $soc => $max) {
                    if ($data[3] < $soc) {
                        $limit['max'] = $max;
                        if ($limit['max'] > 0 && $data[2] > 0) {    // battery is currently not discharging
                            $limit['max'] += 25;                        // add 25 W to limit
                        }
                        break;
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
