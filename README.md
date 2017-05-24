# powerctrl
Answer modbus requests for Infinisolar hybrid solar inverters to run power compensation mode

This script creates a server and answers power compensation mode querys (feed-in control) from modbusII cards installed in voltronic/infinisolar/effekta hx/westech hybrid solar inverters.
Instead of connecting the modbusII card directly to the SDM630(modbus) powermeter with 2 wires, I connected my card to a serial-eth converter (in my case USR-TCP232 in UDP mode).
My powermeter gets polled every 2 seconds from another script (will be release too) and this information gets written into the file ACTsdm630.txt in the filesystem (in my case /tmp). The powerctrl.php script reads this info and sends it to the modbus card.
So it is possible to use the powermeter's infos in other programms too (e.g. a logger like meterN) and the user has the chance, to adjust/control power feed-in of the inverter.
You have the chance to use powermeters other than the SDM630 as well.
