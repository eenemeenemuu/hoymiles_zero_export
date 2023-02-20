hm_control_cfg_dtu_ser = 99978563001
hm_control_cfg_inverter_ser = 116180215597
hm_control_cfg_channel = 23 # [3,23,40,61,75]
hm_control_cfg_pa_level = -18 # [-18,-12,-6,0]

hm_control_cfg_inverter_power_min = 30 # power that should be generated at minimum
hm_control_cfg_inverter_power_max = 300 # power that should be generated at maximum
hm_control_cfg_inverter_power_multiplier = 1 # e.g. set to "2", if a set power limit of 50 W results in 100 W generation

hm_control_cfg_power_target = 0 # set to < 0 if you want to generate more power than needed; set to > 0 to generate less power than needed
hm_control_cfg_power_target_lower_threshold = 5 # the measured power value which needs to be deceeded (in relation to "hm_control_cfg_power_target") before setting a new power limit - must be >= 0
hm_control_cfg_power_target_upper_threshold = 5 # the measured power value which needs to be exceeded (in relation to "hm_control_cfg_power_target") before setting a new power limit - must be >= 0

hm_control_cfg_power_set_pause = 5 # delay after power adjustment
hm_control_cfg_shelly3em = '192.168.1.2' # host name or ip address of Shelly 3EM

hm_control_cfg_fail_threshold = 30 # how often may the retrieval of the energy consumption fail before "hm_control_cfg_fail_power_limit" is applied?
hm_control_cfg_fail_power_limit = hm_control_cfg_inverter_power_min # power that should be generated if energy consumption cannot be retrieved from Shelly 3EM
