<?php

$this->aliases = array_merge($this->aliases, [
    "dht"                           => [ "name" => "dht.mode.set", "prm" => 1 ],
    "throttle.max_uploads.div"      => [ "name" => "throttle.max_uploads.div._val", "prm" => 1 ],
    "throttle.max_uploads.global"   => [ "name" => "throttle.max_uploads.global._val", "prm" => 1 ],
    "throttle.max_downloads.div"    => [ "name" => "throttle.max_downloads.div._val", "prm" => 1 ],
    "throttle.max_downloads.global" => [ "name" => "throttle.max_downloads.global._val", "prm" => 1 ],
    "ratio.enable"                  => [ "name" => "group.seeding.ratio.enable", "prm" => 1 ],
    "ratio.disable"                 => [ "name" => "group.seeding.ratio.disable", "prm" => 1 ],
    "ratio.min"                     => [ "name" => "group2.seeding.ratio.min", "prm" => 0 ],
    "ratio.max"                     => [ "name" => "group2.seeding.ratio.max", "prm" => 0 ],
    "ratio.upload"                  => [ "name" => "group2.seeding.ratio.upload", "prm" => 0 ],
    "ratio.min.set"                 => [ "name" => "group2.seeding.ratio.min.set", "prm" => 1 ],
    "ratio.max.set"                 => [ "name" => "group2.seeding.ratio.max.set", "prm" => 1 ],
    "ratio.upload.set"              => [ "name" => "group2.seeding.ratio.upload.set", "prm" => 1 ],
    "connection_leech"              => [ "name" => "protocol.connection.leech.set", "prm" => 1 ],
    "connection_seed"               => [ "name" => "protocol.connection.seed.set", "prm" => 1 ],
]);
