<?php

if(empty($pathToExternals['sox']))	// May be path already defined?
{
	$pathToExternals['sox'] = '';	// Something like /usr/bin/sox. If empty, will be found in PATH.
}

$extensions = array
(
	"8svx", "aif", "aifc", "aiff", "aiffc", "al", "amb", "au", "avr", "caf", "cdda", "cdr", "cvs", "cvsd", 
	"cvu", "dat", "dvms", "f32", "f4", "f64", "f8", "fap", "flac", "fssd", "gsm", "gsrt", "hcom", "htk", 
	"ima", "ircam", "la", "lpc", "lpc10", "lu", "mat", "mat4", "mat5", "maud", "mp2", "mp3", "nist", "paf", "prc", "pvf",
	"raw", "s1", "s16", "s2", "s24", "s3", "s32", "s4", "s8", "sb", "sd2", "sds", "sf", "sl", "sln", "smp", 
	"snd", "sndfile", "sndr", "sndt", "sou", "sox", "sph", "sw", "txw", "u1", "u16", "u2", "u24", "u3", "u32", 
	"u4", "u8", "ub", "ul", "uw", "vms", "voc", "vox", "w64", "wav", "wavpcm", "wve", "xa", "xi"
);

$arguments = "--multi-threaded --show-progress -n spectrogram";
