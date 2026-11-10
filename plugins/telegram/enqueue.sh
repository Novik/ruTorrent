#!/bin/sh

# rTorrent runs this synchronously: only the short disk write blocks an event,
# never PHP startup or Telegram. All metadata is argv data, not shell source.
# Arguments: queue directory, event, torrent name, hash, comment, timestamp (us),
# size (bytes), label (d.custom1) and ratio (thousandths). The last three come
# after the timestamp: a handler registered by an older version passes only the
# first six until ruTorrent registers the current one, and its events are kept.
[ "$#" -eq 6 ] || [ "$#" -eq 9 ] || exit 1
case "$2" in added|finished|removed|checkfailed) ;; *) exit 1 ;; esac
case "$6" in ''|*[!0-9]*) exit 1 ;; esac

directory=$1
umask 077
[ ! -L "$directory" ] || exit 1
mkdir -p "$directory" || exit 1
chmod 700 "$directory" || exit 1

# Match rTelegramQueue::MAX_QUEUE_ITEMS. Production writers are serialized by
# rTorrent; the sender only removes records and cannot race to add another one.
queue_full() {
	count=0
	for file in "$directory"/*.event; do
		[ -f "$file" ] || continue
		count=$((count + 1))
		[ "$count" -lt 1000 ] || return 0
	done
	return 1
}
if queue_full; then
	echo 'telegram: notification queue is full; dropping a new event' >&2
	exit 1
fi

# NUL separators preserve newlines, quotes, and shell metacharacters verbatim.
# The leading dot hides incomplete records; rename publishes the finished file.
temporary=$(mktemp "$directory/.$6.XXXXXXXXXX") || exit 1
trap 'rm -f "$temporary"' 0
trap 'exit 1' HUP INT TERM
printf '%s\000' "$2" "$3" "$4" "$5" "$7" "$8" "$9" > "$temporary" || exit 1
record=${temporary##*/}
mv "$temporary" "$directory/${record#.}.event" || exit 1
trap - 0
