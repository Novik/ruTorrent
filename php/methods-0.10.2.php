<?php

$this->aliases = array_merge($this->aliases,array(
"dht"                           => array( "name"=>"dht.mode.set", "prm"=>1 ),
"throttle.max_uploads.div"      => array( "name"=>"throttle.max_uploads.div._val", "prm"=>1 ),
"throttle.max_uploads.global"   => array( "name"=>"throttle.max_uploads.global._val", "prm"=>1 ),
"throttle.max_downloads.div"    => array( "name"=>"throttle.max_downloads.div._val", "prm"=>1 ),
"throttle.max_downloads.global" => array( "name"=>"throttle.max_downloads.global._val", "prm"=>1 ),
"ratio.enable"                  => array( "name"=>"group.seeding.ratio.enable", "prm"=>1 ),
"ratio.disable"                 => array( "name"=>"group.seeding.ratio.disable", "prm"=>1 ),
"ratio.min"                     => array( "name"=>"group2.seeding.ratio.min", "prm"=>0 ),
"ratio.max"                     => array( "name"=>"group2.seeding.ratio.max", "prm"=>0 ),
"ratio.upload"                  => array( "name"=>"group2.seeding.ratio.upload", "prm"=>0 ),
"ratio.min.set"                 => array( "name"=>"group2.seeding.ratio.min.set", "prm"=>1 ),
"ratio.max.set"                 => array( "name"=>"group2.seeding.ratio.max.set", "prm"=>1 ),
"ratio.upload.set"              => array( "name"=>"group2.seeding.ratio.upload.set", "prm"=>1 ),
"connection_leech"              => array( "name"=>"protocol.connection.leech.set", "prm"=>1 ),
"connection_seed"               => array( "name"=>"protocol.connection.seed.set", "prm"=>1 ),
));
