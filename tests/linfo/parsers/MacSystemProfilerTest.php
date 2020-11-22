<?php

use \Linfo\Parsers\MacSystemProfiler;

use PHPUnit\Framework\TestCase;

class MacSystemProfilerTest extends TestCase
{
  /**
   * @test
   */
  public function profiler()
  {
    $lines = file(dirname(dirname(dirname(__file__))).'/files/darwin/system_profiler.txt');
    $profiler = new MacSystemProfiler($lines);

    $preparsed = json_decode('{"Hardware":{"Hardware Overview":{"Model Name":"MacBook Air","Model Identifier":"MacBookAir10,1","Chip":"Apple M1","Total Number of Cores":"8 (4 performance and 4 efficiency)","Memory":"8 GB","System Firmware Version":"6723.41.11","Serial Number (system)":"REDACTED","Hardware UUID":"REDACTED","Provisioning UDID":"REDACTED","Activation Lock Status":"Enabled"}},"Software":{"System Software Overview":{"System Version":"macOS 11.0 (20A2411)","Kernel Version":"Darwin 20.1.0","Boot Volume":"Macintosh HD","Boot Mode":"Normal","Computer Name":"Joe\u2019s MacBook Air","User Name":"Joe Gillotti (joe)","Secure Virtual Memory":"Enabled","System Integrity Protection":"Enabled","Time since boot":"2 days 9 minutes"}},"Power":{"Battery Information":{"Model Information":{"Device Name":"REDACTED","Pack Lot Code":"0","PCB Lot Code":"0","Firmware Version":"1002","Hardware Revision":"1","Cell Revision":"2735"},"Charge Information":{"The battery\u2019s charge is below the critical level.":"No","Fully Charged":"No","Charging":"No","State of Charge (%)":"65"},"Health Information":{"Cycle Count":"1","Condition":"Normal","Maximum Capacity":"100%"}},"System Power Settings":{"AC Power":{"System Sleep Timer (Minutes)":"1","Disk Sleep Timer (Minutes)":"10","Display Sleep Timer (Minutes)":"10","Sleep on Power Button":"Yes","Wake on LAN":"Yes","PrioritizeNetworkReachabilityOverSleep":"0"},"Battery Power":{"System Sleep Timer (Minutes)":"1","Disk Sleep Timer (Minutes)":"10","Display Sleep Timer (Minutes)":"2","Sleep on Power Button":"Yes","Current Power Source":"Yes","Reduce Brightness":"Yes"}},"Hardware Configuration":{"UPS Installed":"No"},"AC Charger Information":{"Connected":"No","Charging":"No"},"Power Events":{"Next Scheduled Events":{"appPID":"684","Type":"Wake","Scheduled By":"com.apple.alarm.user-visible-Weekly Usage Report","Time":"11\/22\/20, 6:54 AM","UserVisible":"0"}}}}', true);

    $this->assertEquals($profiler->parse()->todict(), $preparsed);
  }
}
