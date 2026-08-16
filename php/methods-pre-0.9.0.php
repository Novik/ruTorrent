<?php

// rTorrent gained d.is_partially_done in 0.9.0. An unknown command faults the
// whole d.multicall rather than one field, so on older daemons route it to the
// harmless no-op "cat": it answers with an empty string, which keeps the field
// in place and reads as "not partially done".

$this->aliases = array_merge($this->aliases,array(
"d.is_partially_done"	=>	array( "name"=>"cat", "prm"=>0 ),
));
