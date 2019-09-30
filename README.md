# battery-charge-limits
non-root max % charge &amp; max temperature charge solution using HS110+termux+php 

# setup
connect your charger via a [HS110](https://www.tp-link.com/uk/home-networking/smart-plug/hs110/) smartplug, make sure the HS110 and your phone is on the same wifi (you can use a wifi hotspot if none is available), find the HS110 ip address, 
install "Termux" and "Termux API" from Google Play Store,
then open Termux and write 
```sh
apt update;
apt full-upgrade;
apt install php termux-api git;
git clone https://github.com/divinity76/battery-charge-limits.git;
cd battery-charge-limits;
```
(and it is important that you install the termux api both from playstore AND within termux itself, i think)
then just run it like this: 

> php battery_max_temp.php

then it will ask you a bunch of questions (max temp, max charge, hs110 ip address), and it will remember your answers (and make them the default next time you run it), 
and then it will start doing it's thing..

it looks like this:

```sh
$ php battery_max_temp.php
(if we freeze now, you (probably) suffer from https://github.com/termux/termux-packages/issues/334 ... no you don\'t!)
here is your current battery stats: object(Battery_status)#1 (5) {
  ["health"]=>
  string(4) "GOOD"
  ["percentage"]=>
  int(26)
  ["plugged"]=>
  bool(true)
  ["status"]=>
  string(8) "CHARGING"
  ["temperature"]=>
  float(39)
}
stop charging at %?  (default: 100): 100
max battery temperature while charging?  (default: 39): 39
hs110 ip address?  (default: 192.168.1.109): 192.168.1.109
testing hs110 connection...string(0) ""
that\'s all! will now begin doing my thing.

Current status: charging. reason: (default)
Current status: charging. reason: (default)
Current status: charging. reason: (default)
Current status: charging. reason: (default)
Current status: charging. reason: (default)^C
```
