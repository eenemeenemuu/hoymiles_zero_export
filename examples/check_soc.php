<?php
/* Example script for "temporary override min/max power values" functionality
 *
 * The quite complex if/else construct below is due to the fact that an additional battery protection
 * has been installed, which should not trigger prematurely due to voltage drops (caused by high currents).
 *
 * If you don't have an additional battery protection, you can simply set the shutdown level for the desired SOC.
 * */

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
            if ($data[3] < 16) {                                    // if SOC is below 16 %
                file_put_contents_print('inverter_power_max', 0);   // set inverter maximum power to 0 W (turn off)
            } elseif ($data[3] < 17 && $data[2] < 0) {              // if SOC is below 17 % and the battery is currently discharging
                file_put_contents_print('inverter_power_max', 25);  // set inverter maximum power to 25 W
            } elseif ($data[3] < 17) {                              // if SOC is below 17 %
                file_put_contents_print('inverter_power_max', 50);  // set inverter maximum power to 50 W
            } elseif ($data[3] < 18 && $data[2] < 0) {              // if SOC is below 18 % and the battery is currently discharging
                file_put_contents_print('inverter_power_max', 50);  // set inverter maximum power to 50 W
            } elseif ($data[3] < 18) {                              // if SOC is below 18 %
                file_put_contents_print('inverter_power_max', 75);  // set inverter maximum power to 75 W
            } elseif ($data[3] < 19 && $data[2] < 0) {              // if SOC is below 19 % and the battery is currently discharging
                file_put_contents_print('inverter_power_max', 75);  // set inverter maximum power to 75 W
            } elseif ($data[3] < 19) {                              // if SOC is below 19 %
                file_put_contents_print('inverter_power_max', 100); // set inverter maximum power to 100 W
            } elseif ($data[3] < 20 && $data[2] < 0) {              // if SOC is below 20 % and the battery is currently discharging
                file_put_contents_print('inverter_power_max', 100); // set inverter maximum power to 100 W
            } elseif ($data[3] < 20) {                              // if SOC is below 20 %
                file_put_contents_print('inverter_power_max', 150); // set inverter maximum power to 150 W
            } elseif ($data[3] < 25 && $data[2] < 0) {              // if SOC is below 25 % and the battery is currently discharging
                file_put_contents_print('inverter_power_max', 150); // set inverter maximum power to 150 W
            } elseif ($data[3] >= 99) {                             // if SOC is greater or equal 99 % (battery is full)
                file_put_contents_print('inverter_power_min', 200); // set inverter minimum power to 200 W
            } else {
                echo 'nothing to do';
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
