<?php

$pushBulletEndpoint = "https://api.pushbullet.com/v2/pushes";

$pushBulletNotifications = array
(
	"addition" => array
	(
		"title" => "Torrent added",
		"body" => "Name: {name}\n".
			  "Label: {label}\n".
			  "Size: {size}\n".
			  "Time: {added}\n".
			  "Tracker: {tracker}",
	),
	"deletion" => array
	(
		"title" => "Torrent deleted",
		"body" => "Name: {name}\n".
			  "Label: {label}\n".
			  "Size: {size}\n".
			  "Downloaded: {downloaded}\n".
			  "Uploaded: {uploaded}\n".
			  "Ratio: {ratio}\n".
			  "Creation: {creation}\n".
			  "Added: {added}\n".
			  "Finished: {finished}\n".
			  "Tracker: {tracker}",
	),
	"finish" => array
	(
		"title" => "Torrent finished",
		"body" => "Name: {name}\n".
			  "Label: {label}\n".
			  "Size: {size}\n".
			  "Downloaded: {downloaded}\n".
			  "Uploaded: {uploaded}\n".
			  "Ratio: {ratio}\n".
			  "Creation: {creation}\n".
			  "Added: {added}\n".
			  "Finished: {finished}\n".
			  "Tracker: {tracker}",
	),
);
