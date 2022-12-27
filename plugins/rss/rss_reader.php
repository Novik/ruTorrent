<?php
require_once(dirname(__FILE__) . "/../../php/util.php");

class RegexRSSReader
{
	private $encoding = 'utf-8';
	private $data = null;
	public $errors = [];


	public function __construct($data)
	{
		ini_set("pcre.backtrack_limit", max(strlen($data), 100000));
		$this->data = $data;
		$enc = $this->searchText('/?xml/@encoding');
		$this->encoding = empty($enc) ? 'utf-8' : strtolower($enc);
	}

	private function match($regex, $offset)
	{
		$match = preg_match($regex, $this->data, $matches, PREG_OFFSET_CAPTURE, $offset);
		if ($match) {
			foreach (['value', 'value2'] as $v) {
				if (array_key_exists($v, $matches) && $matches[$v][1] !== -1) {
					return $matches[$v];
				}
			}
		}
		return null;
	}

	private function nextNode($name, $ctx = null)
	{
		$offset = $ctx ? $ctx['offset'] : 0;
		$parentName = $ctx ? preg_quote($ctx['name']) : null;
		if ($parentName && Utility::str_starts_with($name, 'text()')) {
			// find text of node
			$regex = '<' . $parentName . '.*?>\s*((?P<value><!\[CDATA\[.*?\]\]>)|(?P<value2>.*?))<\/' . $parentName . '>';
		} elseif ($parentName && Utility::str_starts_with($name, '@')) {
			// find attr of node
			$attrib = substr($name, 1);
			$regex = '<' . $parentName . '\s+([a-z0-9:_]+?\s*=\s*((".*?")|(\'.*?\'))?\s*)*?'
				. preg_quote($attrib) . '\s*=\s*(("(?P<value>.*?)")|(\'(?P<value2>.*?)\')).*?>';
		} else {
			// find child of node (or next root)
			if ($ctx && Utility::str_ends_with($ctx['value'], '/>')) {
				// self closing tag
				return null;
			}
			$offset = $ctx ? $ctx['offset'] + strlen($ctx['value']) : 0;
			$regex = '((<!\[CDATA\[.*?\]\]>|<!--.*?-->)|.*?)*?('
				. ($parentName ? '(?P<value><\/' . $parentName . '>)|' : '')
				. '(?P<value2><' . preg_quote($name) . '.*?>))';
		}
		if (($m = $this->match('/' . $regex . '/si', $offset)) !== null) {
			return [
				'name' => $name,
				'value' => $m[0],
				'offset' => $m[1],
				'close' => $parentName && $m[0] == '</' . $ctx['name'] . '>'
			];
		}
		return null;
	}

	public function searchNodes($path, $ctx = null)
	{
		if (!is_array($path)) {
			yield from $this->searchNodes(explode('/', $path), $ctx);
		} elseif (count($path) > 0) {
			// find parent nodes
			while (count($path) > 1) {
				$name = array_shift($path);
				if (!empty($name) && (($ctx = $this->nextNode($name, $ctx)) === null || $ctx['close'])) {
					return;
				}
			}
			// find target nodes
			while (($nctx = $this->nextNode($path[0], $ctx)) !== null && !$nctx['close']) {
				if (!Utility::str_starts_with($nctx['value'], '</')) {
					yield $nctx;
				}
				if ($ctx !== null) {
					// move parent offset
					$nctx['name'] = $ctx['name'];
					$ctx = $nctx;
				}
			}
			if ($ctx && !$nctx) {
				$this->errors[] = '<' . $ctx['name'] . '> not closed! @ ' . $ctx['offset'];
			}
		}
	}

	public function searchText($path, $ctx = null)
	{
		foreach ($this->searchNodes($path, $ctx) as $c) {
			$text = '';
			if (
				Utility::str_starts_with($c['name'], '@')
				|| ($c = $this->nextNode('text()', $c)) !== null
			) {
				$text = trim($c['value']);
			}
			if ($this->encoding != 'utf-8') {
				if (function_exists('iconv'))
					$text = iconv($this->encoding, 'UTF-8//TRANSLIT', $text);
				elseif (function_exists('mb_convert_encoding'))
					$text = mb_convert_encoding($text, 'UTF-8', $this->encoding);
				else
					$text = UTF::win2utf($text);
			}
			if (Utility::str_starts_with($text, '<![CDATA[')) {
				return substr($text, 9, strlen($text) - (1 + 9 + 2));
			}
			return html_entity_decode(strip_tags($text), ENT_QUOTES, 'utf-8');
		}
	}
}

function rssXpath($data)
{
	if (extension_loaded('libxml')) {
		$doc = new DOMDocument();
		$doc->recover = true;
		// note: as of php8 (libxml 2.9.0) entity substitution is disabled by default
		if(PHP_VERSION_ID < 80000) {
			libxml_disable_entity_loader(true);
		}
		libxml_use_internal_errors(true);
		$doc->loadXML(str_replace('xmlns=', 'ns=', $data), LIBXML_NOBLANKS | LIBXML_COMPACT);
		$errs = [];
		foreach (libxml_get_errors() as $error) {
			$errs[] = '[' . $error->code . '] ' . $error->message;
		}
		$errors = function () use ($errs) {
			return $errs;
		};

		libxml_use_internal_errors(false);
		libxml_clear_errors();
		$xpath = new DOMXPath($doc);
		$xText = function ($xpathExprs, &$ctx = null) use (&$xpath) {
			$text = '';
			foreach ($xpathExprs as $path) {
				if (strpos($path, '/@') !== 0) {
					$text = $xpath->evaluate('string(' . $path . ')', $ctx);
				} elseif (($res = $xpath->evaluate($path . '/text()', $ctx)) !== null) {
					$text = $res->data;
				}
				if ($text != '') {
					return $text;
				}
			}
			return '';
		};
		$xIter = function ($expr, &$ctx = null) use (&$xpath) {
			return $xpath->query($expr, $ctx);
		};
	} else {
		$regxSearch = new RegexRSSReader($data);
		$xText = function ($xpathExprs, &$ctx = null) use (&$regxSearch) {
			$text = '';
			foreach ($xpathExprs as $path) {
				if (($text = $regxSearch->searchText($path, $ctx)) != '') {
					return $text;
				}
			}
			return '';
		};
		$xIter = function ($expr, &$ctx = null) use (&$regxSearch) {
			foreach (explode('|', $expr) as $e)
				yield from $regxSearch->searchNodes($e, $ctx);
		};
		$errors = function () use (&$regxSearch) {
			return $regxSearch->errors;
		};
	}

	return ([function ($xpathExprs, &$ctx = null) use (&$xText) {
		return $xText(is_array($xpathExprs) ? $xpathExprs : [$xpathExprs], $ctx);
	}, $xIter, $errors]);
}
