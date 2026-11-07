# Sourced by the unpack scripts. unzip writes through a symbolic link it
# finds below the directory it extracts into, including one an earlier
# archive made, and unrar through one at that directory itself. So every
# extractor run gets an empty private directory, and place.php then moves
# what was written there into the output: it drops every symbolic link an
# archive brought and writes nothing into or through a link in the output.
#
# The scripts' $3 is the output directory, $6 the unpack temp directory if
# any, and $7-$10 are for place.php: PHP, "automatic" or "manual", the
# download's hash and the user.

unpack_output="$3"
unpack_root="${6:-$3}"
unpack_php="$7"
unpack_how="$8"
unpack_hash="$9"
unpack_user="${10}"
unpack_skipped=0

# place.php, with this unpack's context: see rUnpack::place() and report().
unpack_php()
{
	"$unpack_php" "$(dirname "$0")/place.php" "$unpack_how" "$unpack_hash" "$unpack_user" "$@"
}

# unrar -ol- skips symbolic links without a word. Names those in $2, an
# archive or a directory's archives as "dir/.", and counts them in
# unpack_skipped, so that they are refused like a zip's.
unpack_rar_links()
{
	# -v lists every volume, -p- never asks for a password. A directory's
	# volumes are each listed again with -v, so a name is counted once.
	unpack_skipped=$("$1" lt -v -p- -- "$2" 2>/dev/null | awk '
		/^ *Name: / { sub(/^ *Name: /, ""); name = $0; next }
		tolower($0) ~ /^ *type: .*(symbolic link|junction)/ && !(name in seen) {
			seen[name] = 1
			print "unpack: refused archive-symbolic-link: " name > "/dev/stderr"; n++ }
		END { print n + 0 }')
}

# Runs the extractor command "$2"... with a private directory, plus the
# subdirectory $1 (empty or with tail slash), as its last argument, then
# places what it wrote. Returns the extractor's status, or 1 if the
# placement failed.
unpack_extract()
{
	unpack_subdirectory="$1"
	shift
	if ! mkdir -p "$unpack_root" ||
		! unpack_stage=$(mktemp -d "${unpack_root%/}/.rutorrent-unpack.XXXXXX") ; then
		unpack_php refuse private-directory-unavailable "$unpack_root"
		return 1
	fi
	"$@" "$unpack_stage/$unpack_subdirectory"
	unpack_status=$?
	# Without u+rwX a directory from an archive could not be entered, moved
	# to another parent or emptied.
	chmod -R u+rwX "$unpack_stage"
	if [ "$unpack_skipped" -ne 0 ] || ! rmdir "$unpack_stage" 2>/dev/null ; then
		if ! mkdir -p "$unpack_output" ||
			! unpack_php place "$unpack_stage" "$unpack_output" "$unpack_skipped" ; then
			unpack_status=1
		fi
		rm -rf "$unpack_stage"
	fi
	[ "$unpack_root" = "$unpack_output" ] || rmdir "$unpack_root" 2>/dev/null
	return $unpack_status
}
