<?php

$dir = ''; // optional: relative or absolute path to the directory with the "Hoymiles zero export" python scripts - must end with trailing slash (/)!

function read_python_file($file) {
    global $dir;
    $contents = file_get_contents($dir.$file);
    $contents = str_replace("\r", "\n", $contents);
    $contents = str_replace("\n\n", "\n", $contents);
    $lines = explode("\n", $contents);
    $values = [];
    foreach ($lines as $line) {
        if (preg_match('/([a-z_]+)[\s]*=[\s]*([a-z_0-9-]+)[\s]?/', $line, $match)) {
            $values[$match[1]] = $match[2];
        }
    }
    return $values;
}

if (isset($_POST['save'])) {
    $override_valid_until = time() + $_POST['override_valid_until_d']*60*60*24 + $_POST['override_valid_until_h']*60*60 + $_POST['override_valid_until_m']*60 + $_POST['override_valid_until_s'];
    $contents[] = 'override_valid_until = '.$override_valid_until;
    if (is_numeric($_POST['inverter_power_min'])) {
        $contents[] = 'inverter_power_min = '.$_POST['inverter_power_min'];
    }
    if (is_numeric($_POST['inverter_power_max'])) {
        $contents[] = 'inverter_power_max = '.$_POST['inverter_power_max'];
    }
    if (is_numeric($_POST['power_target'])) {
        $contents[] = 'power_target = '.$_POST['power_target'];
    }
    if (is_numeric($_POST['power_target_lower_threshold'])) {
        $contents[] = 'power_target_lower_threshold = '.$_POST['power_target_lower_threshold'];
    }
    if (is_numeric($_POST['power_target_upper_threshold'])) {
        $contents[] = 'power_target_upper_threshold = '.$_POST['power_target_upper_threshold'];
    }
    $message = 'Saved.';
} elseif (isset($_GET['delete'])) {
    $contents[] = '#';
    $message = 'Deleted.';
}

if (is_array($contents)) {
    if (!file_put_contents($dir.'hm_control_config_override.py', implode("\n", $contents))) {
        $message = 'Error writing file!';
    }
}

$hm_control_config = read_python_file('hm_control_config.py');
$hm_control_config_override = read_python_file('hm_control_config_override.py');

if ($hm_control_config_override['override_valid_until'] > time()) {
    $countdown = $hm_control_config_override['override_valid_until'] - time();
    $hm_control_config_override['override_valid_until_d'] = floor($countdown / (60*60*24));
    $countdown -= $hm_control_config_override['override_valid_until_d']*60*60*24;
    $hm_control_config_override['override_valid_until_h'] = floor($countdown / (60*60));
    $countdown -= $hm_control_config_override['override_valid_until_h']*60*60;
    $hm_control_config_override['override_valid_until_m'] = floor($countdown / 60);
    $countdown -= $hm_control_config_override['override_valid_until_m']*60;
    $hm_control_config_override['override_valid_until_s'] = $countdown;
}

$slider_js = "oninput=document.getElementById('power_target').value=this.value;document.getElementById('power_target_lower_threshold').value=this.value;document.getElementById('power_target_upper_threshold').value=this.value;";

echo '<html><head><title>Hoymiles zero export</title><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"><meta name="viewport" content="width=device-width" /></head><body>';
if ($message) {
    echo '<div id="message">'.$message.'</div><script type="text/javascript">function hide_message() { document.getElementById(\'message\').style.display = \'none\' } setTimeout(hide_message, 5000)</script>';
}
echo '<form method="post"><table border="1"><tr><th onclick="window.location=\'?\'"></th><th>config value</th><th>config override value</th><th>new value</th></tr>';
echo '<tr><td>override_valid_until</td><td>N/A</td><td>'.date("d.m.Y H:i:s", $hm_control_config_override['override_valid_until']).'</td><td align="right">Days: <input type="text" size="1" name="override_valid_until_d" value="'.$hm_control_config_override['override_valid_until_d'].'" /><br />Hours: <input type="text" size="1" name="override_valid_until_h" value="'.$hm_control_config_override['override_valid_until_h'].'" /><br />Minutes: <input type="text" size="1" name="override_valid_until_m" value="'.$hm_control_config_override['override_valid_until_m'].'" /><br />Seconds: <input type="text" size="1" name="override_valid_until_s" value="'.$hm_control_config_override['override_valid_until_s'].'" /></td></tr>';
echo '<tr><td>inverter_power_min</td><td>'.$hm_control_config['inverter_power_min'].'</td><td>'.$hm_control_config_override['inverter_power_min'].'</td><td align="right"><input type="text" size="1" name="inverter_power_min" value="'.$hm_control_config_override['inverter_power_min'].'" /></td></tr>';
echo '<tr><td>inverter_power_max</td><td>'.$hm_control_config['inverter_power_max'].'</td><td>'.$hm_control_config_override['inverter_power_max'].'</td><td align="right"><input type="text" size="1" name="inverter_power_max" value="'.$hm_control_config_override['inverter_power_max'].'" /></td></tr>';
echo '<tr><td>power_target</td><td>'.$hm_control_config['power_target'].'</td><td>'.$hm_control_config_override['power_target'].'</td><td align="right"><input type="text" size="1" name="power_target" id="power_target" value="'.$hm_control_config_override['power_target'].'" /></td></tr>';
echo '<tr><td>power_target_lower_threshold</td><td>'.$hm_control_config['power_target_lower_threshold'].'</td><td>'.$hm_control_config_override['power_target_lower_threshold'].'</td><td align="right"><input type="text" size="1" name="power_target_lower_threshold" id="power_target_lower_threshold" value="'.$hm_control_config_override['power_target_lower_threshold'].'" /></td></tr>';
echo '<tr><td>power_target_upper_threshold</td><td>'.$hm_control_config['power_target_upper_threshold'].'</td><td>'.$hm_control_config_override['power_target_upper_threshold'].'</td><td align="right"><input type="text" size="1" name="power_target_upper_threshold" id="power_target_upper_threshold" value="'.$hm_control_config_override['power_target_upper_threshold'].'" /></td></tr>';
echo '<tr><td colspan="4" align="right"><input type="range" min="-'.round($hm_control_config['inverter_power_max']/2).'" max="'.round($hm_control_config['inverter_power_max']/2).'" value="'.(isset($hm_control_config_override['power_target']) ? $hm_control_config_override['power_target'] : $hm_control_config['power_target']).'" '.$slider_js.' /></td></tr>';
echo '<tr><td colspan="3" align="right"><input type="button" name="delete" onclick="window.location=\'?delete\'" value="Delete values" /></td><td align="right"><input type="submit" name="save" value="Save values" /></td></tr>';
echo '</table></form></body></html>';

//EOF
