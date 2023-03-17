dtu_ser = 99978563001 # serial number of DTU - usually doesn't need to be changed
inverter_ser = 116180215597 # serial number of Hoymiles HM-* inverter - must be adjusted!
channel = 23 # modify to one of [3,23,40,61,75] if you dont get a response to the time package
pa_level = -18 # transmission power amplifier - possible values: [-18,-12,-6,0]

inverter_power_min = 20 # power that should be generated at minimum
inverter_power_max = 200 # power that should be generated at maximum
inverter_power_multiplier = 1 # e.g. set to "2", if a set power limit of 100 W results in 50 W generation

power_target = 0 # set to < 0 if you want to generate more power than needed; set to > 0 to generate less power than needed
power_target_lower_threshold = 5 # the measured power value which needs to be deceeded (in relation to "power_target") before setting a new power limit - must be >= 0
power_target_upper_threshold = 5 # the measured power value which needs to be exceeded (in relation to "power_target") before setting a new power limit - must be >= 0

power_set_pause = 5 # delay after power adjustment
shelly3em = '192.168.1.2' # host name or ip address of Shelly 3EM

fail_threshold = 30 # how often may the retrieval of the energy consumption fail before "fail_power_limit" is applied?
fail_power_limit = inverter_power_min # power that should be generated if energy consumption cannot be retrieved more than "fail_threshold" from Shelly 3EM; set to desired value or to "0" if you want to turn off inverter
