# battery-charge-limits
non-root max % charge &amp; max temperature charge solution using HS110+termux+php 

it looks like this:

```sh
$ php battery_max_temp.php
(if we freeze now, you (probably) suffer from https://github.com/termux/termux-packages/issues/334 ... no you don't!)
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
stop charging at %?  (default: 100):100
max battery temperature while charging?  (default: 39):39
hs110 ip address?  (default: 192.168.1.109): 192.168.1.109
testing hs110 connection...string(0) ""
that's all! will now begin doing my thing.

Current status: charging. reason: (default)
Current status: charging. reason: (default)
Current status: charging. reason: (default)
Current status: charging. reason: (default)
Current status: charging. reason: (default)^C
```
