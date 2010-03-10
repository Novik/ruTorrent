
    Plug-in "autotools" version 1.5

    for rutorrent Webui (rtorrent web gui)
    (http://code.google.com/p/rutorrent)


    Plugin Author: Dmitry Romanovsky (dmrom)

Plugin Features:
1. "AutoLabel": Automatic creation of labels when adding a new torrent to rutorrent WebUI

2. "AutoMove":  Automatically moves torrents which have finished downloading from the 
		rtorrent download directory to a new directory.  rtorrent will seed from\
		this new location.

3. "Autowatch: Automatically add torrents to rtorrent via a nested set of watch directories.


---------------------------------------------------------------------------------------------
"AutoLabel"
---------------------------------------------------------------------------------------------
Label will be created automatically if:

- "Label" field is empty in the "add torrent" window.

- The new torrent is set to download to a subdirectory of the "directory =" setting in .rtorrent.rc

For example, if your download directory is "directory = /usr/p2p/downloads"
and the new torrent is set to download to /usr/p2p/downloads/Video/DVD/ then the label would be "Video/DVD"

---------------------------------------------------------------------------------------------
"AutoMove"
---------------------------------------------------------------------------------------------
Downloaded torrent data is moved to a new directory preserving the directory scruture relative to the 
original download directory set in ".rtorrent.rc"

This works in much the same way as AutoLabel.  If you create a nested hierarchy of Directories like:
/usr/p2p/downloads/Video/DVD/
/usr/p2p/downloads/Video/Blueray/
/usr/p2p/downloads/Video/TV/

and your download path is set to /usr/p2p/downloads in .rtorrent.rc, torrents saved in these subdirectories 
will be automatically moved, on completion of downloading, to the "AutoMove" Directory
so, for example, if you set the "AutoMove" Directory to /media/p2p/ and you set a torrent to download to
/usr/p2p/downloads/Video/DVD/  when it is finished downloading, it will be moved to /media/p2p/Video/DVD/

Torrents will then seed from this new location.

It was planned to use this plugin with devices mounted from another location, SMB or NFS shares.  This solution
works on FreeBSD, but according to Novik "under the 2.4 linux kernel (which is used on many routers), the smbfs and nfs 
mounts will not work.  Calling mmap, does not work in these conditions"  
If possible, use FreeBSD or any linux kernel past 2.6.26

This plugin has no problem with multiple torrents stored in the same directory, it will move only the files listed from that 
torrent, and not the base directory.  However, if new downloads with the same name, in the same directory are moved, they will overwrite 
the older ones. (for example, if you have /usr/p2p/downloads/Video/DVD/movie.avi and it is moved, then you download another file named
/usr/p2p/downloads/Video/DVD/movie.avi  it will overwrite the old one.  The best solution for this is to create a subdirectory that is
different from the first, in this very unlikely situation.

For your convenience, it is recommended that you install the plugin "_getdir"  This will make navigating the filesystem from the webgui
much easier.

----------------------------------------------------------------------------------------------
"AutoWatch"
----------------------------------------------------------------------------------------------
 
*.torrent files are placed in nested subdirectories of a desired structure on
some base directory.  This base directory is set in the plugin settings.

This works very much like normal watch directories in rtorrent, except in a nested 
hierarchy.  Torrents added in this way, will be downloaded to a corresponding directory
in the path of whatever dir you've set in .rtorrent.rc

With this, you can create a system of watch directories to drop .torrent files into.  



----------------------------------------------------------------------------------------------
Version History:
----------------------------------------------------------------------------------------------

    1.5
    - Plugin was adapted for ruTorrent 3.0

    1.4
    - Added function AutoWatch
    - Removed scripts *. sh, to initialize the plug-in rtorrent.rc recommended
      initplugins.php use the script from the main directory ruTorrent:
      execute = (sh,-c, full_path_to_php full_path_to_rutorrent / initplugins.php &)

    1.3
    - Fixed bug that caused it to crash rTorrent, if the names of subdirectories
      torrent service marks are used, such as, 'etc.
    - An attempt to avoid unnecessary reheshirovaniya, which sometimes occurs.

    1.2
    - Plugin renamed and merged with autotools automove 1.0
    - Added ability to set the settings in the options ruTorrent

    1.1
    - Ensuring compatibility with plug-ins retrackers and edit
    - Plugin now runs until retracker (runlevel.info: 5)

    1.0
    - First version

----------------------------------------------------------------------------------------------
Thanks to Thomas Burgess for readme translation.
----------------------------------------------------------------------------------------------
