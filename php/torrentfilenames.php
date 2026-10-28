<?php

require_once(__DIR__.'/utility/utf.php');

// Labels for torrent titles and file names that are not UTF-8. A label is
// for display only: daemon names, paths, file indices and the cells of an
// existing response stay as they are, and a caller sends labels beside them.
// Names and paths are raw bytes, as the metadata holds them: a value read
// through rXMLRPCRequest goes through rXMLRPCRequest::unescapeValue() first.
class TorrentFileNames
{
	public static function needsTitleMetadata($name)
	{
		return(self::legacy($name));
	}

	public static function needsMetadata($rows)
	{
		foreach($rows as $row)
			if(self::legacy($row[0] ?? null))
				return(true);
		return(false);
	}

	// A label for a daemon title, from the caller's rTorrent::getSource().
	public static function title($name, $hash, $source)
	{
		return(self::legacy($name) ? self::label($name, $hash, $source) : $name);
	}

	// A label for one title. $knownPaths is what rTorrent::getSource()
	// accepts: false means the source is unknown and nothing is asked.
	public static function displayTitle($name, $hash, $knownPaths)
	{
		return(self::legacy($name) ? self::label($name, $hash, self::source($hash, $knownPaths)) : $name);
	}

	// Labels for hash => daemon title pairs. The source paths of every
	// torrent are read in one request, and only when a title needs them.
	public static function titles($names)
	{
		$paths = null;
		foreach($names as $hash=>$name)
			if(self::legacy($name))
			{
				if($paths === null)
				{
					self::loadDaemon();
					$paths = rTorrent::getSourcePaths();
				}
				// A torrent missing from the batch is not asked about again.
				$names[$hash] = self::label($name, $hash, self::source($hash, $paths[strtoupper($hash)] ?? false));
			}
		return($names);
	}

	// A download label for the file $filename names, or null to keep the
	// default name.
	public static function downloadName($hash, $index, $filename)
	{
		self::loadDaemon();
		$leaf = FileUtil::getFileName($filename);
		if(UTF::isValid($leaf))
			return(null);
		$req = new rXMLRPCRequest(array(
			new rXMLRPCCommand('f.get_path', array($hash, $index)),
			new rXMLRPCCommand('f.get_size_bytes', array($hash, $index)),
			new rXMLRPCCommand('d.get_size_files', $hash)));
		if(!$req->success() || (count($req->val) !== 3))
			return(UTF::display($leaf));
		$path = rXMLRPCRequest::unescapeValue($req->val[0]);
		if(FileUtil::getFileName($path) !== $leaf)
			return(UTF::display($leaf));
		return(self::fileName($path, $index, $req->val[1], $req->val[2], $hash, self::source($hash)));
	}

	// Labels for the f.multicall rows of one torrent whose path is not UTF-8,
	// keyed by row index; the rows are not changed. A row is [0] the path and
	// [3] the size in bytes, as in httprpc's fls answer. Rows under one raw
	// directory show it under one label, and where their metadata disagrees
	// it is shown in the \xNN form. $source is the caller's
	// rTorrent::getSource().
	public static function resolve($rows, $hash, $source)
	{
		$files = null;
		$components = array();
		$prefixes = array();
		foreach($rows as $index=>$row)
		{
			if(!self::legacy($row[0] ?? null))
				continue;
			if($files === null)
				$files = self::sourceFiles($source, $hash, count($rows), $encoding);
			$path = explode('/', $row[0]);
			list($alias, $rowEncoding) = self::attested($files, $index, $path, $row[3] ?? null, $encoding);
			$raw = null;
			foreach($path as $depth=>$part)
			{
				// A component is known by its raw path from the torrent root.
				$raw = ($raw === null) ? $part : $raw.'/'.$part;
				$prefix = null;
				if(UTF::isValid($part))
					$label = $part;
				else
				{
					$label = ($alias !== false) ? $alias[$depth] : self::fallback($part, $rowEncoding);
					$prefix = $raw;
					if(!array_key_exists($prefix, $prefixes))
						$prefixes[$prefix] = $label;
					else if($prefixes[$prefix] !== $label)
						$prefixes[$prefix] = false;
				}
				$components[$index][] = array($part, $label, $prefix);
			}
		}
		$names = array();
		foreach($components as $index=>$entries)
		{
			$display = array();
			foreach($entries as list($part, $label, $prefix))
				$display[] = (($prefix !== null) && ($prefixes[$prefix] === false)) ? UTF::display($part) : $label;
			$names[$index] = implode('/', $display);
		}
		return($names);
	}

	private static function legacy($text)
	{
		return(is_string($text) && !UTF::isValid($text));
	}

	// title() and resolve() never load the daemon side; titles() and
	// displayTitle() load it only for a name that needs metadata.
	private static function loadDaemon()
	{
		require_once(__DIR__.'/rtorrent.php');
	}

	// The daemon names a torrent's session file by its hash in upper case.
	private static function source($hash, $knownPaths = null)
	{
		self::loadDaemon();
		// A source that cannot be read leaves the \xNN form, and its warning
		// stays out of the response the label goes into.
		return(@rTorrent::getSource(strtoupper((string)$hash), $knownPaths));
	}

	// The torrent's own name.utf-8, else its name converted from its
	// declared encoding, else the \xNN form.
	private static function label($name, $hash, $source)
	{
		$encoding = null;
		if(self::sourceMatches($source, $hash) && isset($source->info['name']) && ($source->info['name'] === $name))
		{
			if(isset($source->info['name.utf-8']) && self::component($source->info['name.utf-8']))
				return($source->info['name.utf-8']);
			$encoding = $source->encoding;
		}
		return(self::fallback($name, $encoding));
	}

	// Metadata counts only from the torrent the daemon knows by this hash.
	private static function sourceMatches($source, $hash)
	{
		return(($source instanceof Torrent) && is_string($hash) && ($source->hash_info() === strtoupper($hash)));
	}

	private static function component($text)
	{
		return(UTF::isValid($text) && ($text !== '') && ($text !== '.') && ($text !== '..')
			&& (strpos($text, '/') === false) && (strpos($text, "\0") === false));
	}

	// A path.utf-8 list with one usable component for each raw one.
	private static function alias($path, $width)
	{
		if(!is_array($path) || (count($path) !== $width))
			return(false);
		for($depth = 0; $depth < $width; $depth++)
			if(!isset($path[$depth]) || !self::component($path[$depth]))
				return(false);
		return($path);
	}

	// A declared encoding is evidence; detector guesses and lossy
	// conversions are not, so a conversion must map back to the same bytes.
	private static function converted($text, $encoding)
	{
		if(!is_string($encoding) || (preg_match('/\A[A-Za-z0-9._:-]{1,64}\z/', $encoding) !== 1))
			return(false);
		if(function_exists('iconv'))
		{
			$result = @iconv($encoding, 'UTF-8', $text);
			if(self::component($result) && (@iconv('UTF-8', $encoding, $result) === $text))
				return($result);
		}
		try
		{
			// mbstring is optional: a missing function throws an Error, and
			// PHP 8 throws a ValueError for an encoding mbstring does not know.
			$result = @mb_convert_encoding($text, 'UTF-8', $encoding);
			if(self::component($result) && (@mb_convert_encoding($result, $encoding, 'UTF-8') === $text))
				return($result);
		}
		catch(Throwable $error)
		{
		}
		return(false);
	}

	private static function fallback($text, $encoding)
	{
		$converted = self::converted($text, $encoding);
		return(($converted === false) ? UTF::display($text) : $converted);
	}

	// The metainfo file list of the torrent the daemon knows by this hash,
	// when it has as many files as the daemon reports.
	private static function sourceFiles($source, $hash, $count, &$encoding)
	{
		$encoding = null;
		if(!self::sourceMatches($source, $hash))
			return(false);
		$info = $source->info;
		if(isset($info['files']))
			$files = $info['files'];
		else
		{
			if(!isset($info['name'], $info['length']))
				return(false);
			$files = array(array('path'=>array($info['name']), 'length'=>$info['length']));
			if(isset($info['name.utf-8']))
				$files[0]['path.utf-8'] = array($info['name.utf-8']);
		}
		if(!is_array($files) || (count($files) !== $count))
			return(false);
		$encoding = $source->encoding;
		return($files);
	}

	// The alias and declared encoding for a live file. Both come only from
	// the metainfo entry at its index with the same raw path and size.
	private static function attested($files, $index, $path, $size, $encoding)
	{
		$file = $files[$index] ?? null;
		if((($file['path'] ?? null) !== $path) || !is_int($file['length'] ?? null) || ((string)$file['length'] !== (string)$size))
			return(array(false, null));
		return(array(isset($file['path.utf-8']) ? self::alias($file['path.utf-8'], count($path)) : false, $encoding));
	}

	// A label for one live file whose last component is not UTF-8.
	private static function fileName($path, $index, $size, $count, $hash, $source)
	{
		$parts = explode('/', $path);
		$files = self::sourceFiles($source, $hash, (int)$count, $encoding);
		list($alias, $encoding) = self::attested($files, $index, $parts, $size, $encoding);
		$leaf = $parts[count($parts) - 1];
		return(($alias === false) ? self::fallback($leaf, $encoding) : $alias[count($parts) - 1]);
	}
}
