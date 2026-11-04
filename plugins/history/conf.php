<?php

$pushBulletEndpoint = "https://api.pushbullet.com/v2/pushes";

$pushBulletNotifications
= [
    "addition"
    => [
        "title" => "Torrent added",
        "body" => "Name: {name}\n"
              . "Label: {label}\n"
              . "Size: {size}\n"
              . "Time: {added}\n"
              . "Tracker: {tracker}",
    ],
    "deletion"
    => [
        "title" => "Torrent deleted",
        "body" => "Name: {name}\n"
              . "Label: {label}\n"
              . "Size: {size}\n"
              . "Downloaded: {downloaded}\n"
              . "Uploaded: {uploaded}\n"
              . "Ratio: {ratio}\n"
              . "Creation: {creation}\n"
              . "Added: {added}\n"
              . "Finished: {finished}\n"
              . "Tracker: {tracker}",
    ],
    "finish"
    => [
        "title" => "Torrent finished",
        "body" => "Name: {name}\n"
              . "Label: {label}\n"
              . "Size: {size}\n"
              . "Downloaded: {downloaded}\n"
              . "Uploaded: {uploaded}\n"
              . "Ratio: {ratio}\n"
              . "Creation: {creation}\n"
              . "Added: {added}\n"
              . "Finished: {finished}\n"
              . "Tracker: {tracker}",
    ],
];
