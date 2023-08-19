# powerctrl
Answer modbus requests from modbus cards in Infinisolar/MPI/FSP/EASUN/WESTECH/EFFEKTA HX/... hybrid solar inverters to run power compensation mode

This script creates a socket and answers power compensation mode querys (feed-in control) from modbusII cards installed in voltronic hybrid solar inverters.
Instead of connecting the modbusII card directly to the SDM630(modbus) powermeter with 2 wires (rs485), I connected my card to a serial-eth converter (in my case USR-TCP232-304 in UDP mode, a HF2211 in UDP client mode works as well).
The powermeter gets polled every second from another script (sdm630poller) and this information gets written into a shared memory object with ID 6301. The powerctrl.php script reads this info and sends it to the modbus card accordingly.

So it is possible to use the powermeter's complete values in other programms too (e.g. a logger like meterN) and the user has the chance, to adjust/control power feed-in of the inverter.
You also have the chance to use powermeters other than the SDM630 if you write it's values to the shmop.

I added a new version for DEYE/SunSynk hybrid inverters, that answers requests for this kind of inverters.
