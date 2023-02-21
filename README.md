# Python script to control Hoymiles HM inverters
This script was designed for zero export control (aka "Nulleinspeisung") of Hoymiles inverters in connection with with a Rasperry Pi, an nRF24L01+ board and a Shelly 3EM.

It is mainly based on the work of: https://github.com/Knedox/hoymiles_control

## Parts needed
- Hoymiles HM-* inverter (tested with Hoymiles HM-300, HM-400 and HM-600, but any should work)
- Raspberry Pi (tested on a Raspberry Pi 1B, any should work)
- nRF24L01+ module
- 8 jumper wires
- Shelly 3EM

## Prerequisites
- Properly installed Shelly 3EM with connection to your home network
- Working Hoymiles inverter
- Working Raspberry Pi 

## Setup
- Connect the nRF24L01+ module to your Rasperry Pi as follows:

| nRF |      | Pi |         |
|-----|------|----|---------|
| 1   | GND  | 20 | GND     |
| 2   | VCC  | 17 | + 3,3V  |
| 3   | CE   | 15 | GPIO 22 |
| 4   | CSN  | 24 | GPIO 8  |
| 5   | SCK  | 23 | GPIO 11 |
| 6   | MOSI | 19 | GPIO 10 |
| 7   | MISO | 21 | GPIO 9  |
| 8   | IRQ  | 16 | GPIO 12 |

- Install required software on Raspberry Pi:
```
pip3 install circuitpython-nrf24l01 Adafruit-Blinka crcmod
```

- Download this repository and edit the configuration file `hm_control_config.py` (you need to adjust at least `hm_control_cfg_inverter_ser` and `hm_control_cfg_shelly3em` to match your conditions)

- Upload all files from this repository to your Raspberry Pi (e.g. to `/home/pi/`) and make `hm_control.py` executable:
```
chmod +x hm_control.py
```

## Usage
You can run the script with an optional parameter. If you run the script without a parameter, the initial power limit will be set to `hm_control_cfg_inverter_power_min`. If you run the script like
```
./hm_control.py 150
```
the initial power limit is set to 150 W.

Setting an initial power is needed as the script doesn't know the current power limit of the inverter (but this is necessary for further operation). Afterwards the power limit is set according to the parameters in the configuration file.
