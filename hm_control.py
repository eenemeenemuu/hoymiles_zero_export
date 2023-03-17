#!/usr/bin/env python
import time
import board
import struct
import crcmod
import sys
import threading
import enum
from digitalio import DigitalInOut

# if running this on a ATSAMD21 M0 based board
# from circuitpython_nrf24l01.rf24_lite import RF24
from circuitpython_nrf24l01.rf24 import RF24

# invalid default values for scoping
SPI_BUS, CSN_PIN, CE_PIN = (None, None, None)

import spidev

SPI_BUS = spidev.SpiDev()  # for a faster interface on linux
CSN_PIN = 0  # use CE0 on default bus (even faster than using any pin)
CE_PIN = DigitalInOut(board.D22)  # using pin gpio22 (BCM numbering)


nrf = RF24(SPI_BUS, CSN_PIN, CE_PIN)

import hm_control_config

nrf.data_rate = 250
nrf.channel = hm_control_config.channel
nrf.auto_ack = True
nrf.set_auto_retries(3,15)
nrf.crc = 2
nrf.dynamic_payloads = True
nrf.pa_level = hm_control_config.pa_level
nrf.address_length = 5

dtu_ser = hm_control_config.dtu_ser
inverter_ser = hm_control_config.inverter_ser

nrf.open_rx_pipe(1, b'\01' + bytearray.fromhex(str(dtu_ser)[-8:]))



f_crc_m = crcmod.predefined.mkPredefinedCrcFun('modbus')
f_crc8 = crcmod.mkCrcFun(0x101, initCrc=0, xorOut=0)

class CMD:
    ON = 0
    OFF = 1
    RElastAction = 2
    LOCK = 3
    UNLOCK = 4
    ACTIVE_POWER_LIMIT = 11
    REACTIVE_POWER_LIMIT = 12
    POWER_FACTOR = 13
    LOCK_AND_ALARM = 20
    SELF_INSPECT = 40

class PacketType:
    TX_REQ_INFO = 0x15
    TX_REQ_DEVCONTROL = 0x51

# 0x100 -> persistent
# 0x1 -> relative (%)    
def setPowerLimit(dst, limit, relative = False, persist = False):
    mod = persist * 0x100
    mod+= relative 
    sendControl(dst, CMD.ACTIVE_POWER_LIMIT, limit * 10, mod)


def sendControl(dst, cmd, data = None, mod = 0):
    payload = bytearray(2)
    payload[0] = cmd
    if data is not None:
        payload+= data.to_bytes(2, 'big')
        payload+= mod.to_bytes(2, 'big')
        
    sendPacket(dst, PacketType.TX_REQ_DEVCONTROL, payload)


def sendPacket(dst, type, payload, frame_id = 0):
    packet = bytes([type])
    packet += bytearray.fromhex(str(dst)[-8:])
    packet += bytearray.fromhex(str(dtu_ser)[-8:])
    packet += int(0x80 + frame_id).to_bytes(1, 'big')

    packet += payload
    
    if len(payload) > 0:
        packet += struct.pack('>H', f_crc_m(payload)) 
    packet += struct.pack('B', f_crc8(packet))

    transmitPackage(packet) 


mutex = threading.Lock()

def transmitPackage(package):
    inv_esb_addr = b'\01' + package[1:5]

    mutex.acquire()
    nrf.listen = False
    nrf.open_tx_pipe(inv_esb_addr)
    nrf.auto_ack = True
    #print("sending",package[0:1].hex(), package[1:5].hex(),"->",package[5:9].hex(),package[9:10].hex(), ":",package[10:].hex())
    result = nrf.send(package)
    nrf.auto_ack = False
    nrf.listen = True 
    mutex.release()


def hm_control_load_config_override():
    global inverter_power_min, inverter_power_max, power_target, power_target_lower_threshold, power_target_upper_threshold

    inverter_power_min = hm_control_config.inverter_power_min
    inverter_power_max = hm_control_config.inverter_power_max
    power_target = hm_control_config.power_target
    power_target_lower_threshold = hm_control_config.power_target_lower_threshold
    power_target_upper_threshold = hm_control_config.power_target_upper_threshold

    if 'hm_control_config_override' in sys.modules:
        sys.modules.pop('hm_control_config_override')

    try:
        import hm_control_config_override
    except ImportError:
        pass
    else:
        try:
            hm_control_config_override.override_valid_until
        except:
            pass
        else:
            if (time.time() < hm_control_config_override.override_valid_until):
                try:
                    inverter_power_min = hm_control_config_override.inverter_power_min
                    if (inverter_power_min < hm_control_config.inverter_power_min):
                        inverter_power_min = hm_control_config.inverter_power_min
                except AttributeError:
                    pass
                try:
                    inverter_power_max = hm_control_config_override.inverter_power_max
                    if (inverter_power_max > hm_control_config.inverter_power_max):
                        inverter_power_max = hm_control_config.inverter_power_max
                except AttributeError:
                    pass
                try:
                    power_target = hm_control_config_override.power_target
                except AttributeError:
                    pass
                try:
                    power_target_lower_threshold = hm_control_config_override.power_target_lower_threshold
                except AttributeError:
                    pass
                try:
                    power_target_upper_threshold = hm_control_config_override.power_target_upper_threshold
                except AttributeError:
                    pass

def hm_control_set_limit(new_limit, power_measured=None):
    global limit, skip_counter
    if (new_limit < inverter_power_min):
        new_limit = inverter_power_min
    elif (new_limit > inverter_power_max):
        new_limit = inverter_power_max
    if (power_measured is not None):
        print('Intended power generation:\t'+str(new_limit)+' W', end = '')
    skip_counter += 1
    if (skip_counter >= 0 and (power_measured is None or new_limit != limit and (
            power_measured < power_target - power_target_lower_threshold or
            power_measured > power_target + power_target_upper_threshold))):
        skip_counter = -hm_control_config.power_set_pause
        limit = new_limit
        setPowerLimit(inverter_ser, int(limit*hm_control_config.inverter_power_multiplier))
        print('\t[set - skip next '+str(hm_control_config.power_set_pause)+' second(s)]')
    elif (skip_counter < 0):
        print('\t[wait '+str(abs(skip_counter))+' second(s)]')
        # send the limit again, in case it hasn't been received before
        setPowerLimit(inverter_ser, int(limit*hm_control_config.inverter_power_multiplier))
    else:
        print('\t[skipped: '+str(skip_counter+1)+'x]')
        if (skip_counter % hm_control_config.power_set_pause == 0):
            # send the limit again, in case it still hasn't been received before
            setPowerLimit(inverter_ser, int(limit*hm_control_config.inverter_power_multiplier))
    time.sleep(1)

try:
    limit = sys.argv[1]
except:
    limit = hm_control_config.inverter_power_min
else:
    if (limit.isnumeric() is False):
        limit = hm_control_config.inverter_power_min
    else:
        limit = int(limit)

print('Setting power limit to known value ('+str(limit)+' W)...', end = '')

skip_counter = 0
fail_counter = 0
hm_control_load_config_override()
hm_control_set_limit(limit)

import requests
import json

while True:
    try:
        hm_control_load_config_override()

        r = requests.get('http://'+hm_control_config.shelly3em+'/status')
        if (r.status_code == 200):
            fail_counter = 0
            data = json.loads(r.text)
            print()
            print('Inverter power limit:\t\t'+str(limit)+' W', end='')
            if (limit == inverter_power_min):
                print('\t[min: '+str(inverter_power_min)+' W]')
            elif (limit == inverter_power_max):
                print('\t[max: '+str(inverter_power_max)+' W]')
            else:
                print()
            power_measured = round(data['total_power'])
            print('Measured energy consumption:\t'+str(power_measured)+' W', end='')
            if (power_measured >= power_target - power_target_lower_threshold and power_measured <= power_target + power_target_upper_threshold):
                print ('\t[tolerated range: '+str(power_target - power_target_lower_threshold)+' W to '+str(power_target + power_target_upper_threshold)+' W]')
            else:
                print()
            power_calculated = power_measured + limit
            print('Calculated energy consumption:\t'+str(power_calculated)+' W')
            hm_control_set_limit(power_calculated-power_target, power_measured)
        else:
            fail_counter += 1
            if (fail_counter > hm_control_config.fail_threshold):
                print('Fail threshold exceeded, setting power limit to '+str(hm_control_config.fail_threshold)+' W...', end='')
                hm_control_set_limit(hm_control_config.fail_power_limit)
            else:
                print('Failed to get energy consumption, retrying... ['+str(fail_counter)+']')
            time.sleep(0.5)
    except KeyboardInterrupt:
        print()
        print('Keyboard interrupt detected, stopping...')
        break
    except:
        print()
        fail_counter += 1
        if (fail_counter > hm_control_config.fail_threshold):
            print('Fail threshold exceeded, setting power limit to '+str(hm_control_config.fail_threshold)+' W...', end='')
            hm_control_set_limit(hm_control_config.fail_power_limit)
        else:
            print('Something went wrong, retrying... ['+str(fail_counter)+']')
        print()
        time.sleep(0.5)
        continue
