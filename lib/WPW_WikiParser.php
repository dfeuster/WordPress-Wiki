<?php
require_once('class_WikiParser.php');

class WPW_WikiParser extends WikiParser {
	//parent::WikiParser();
	function wiki_link($topic) {
		$wiki = get_page_by_title($topic);
		
		if (!$wiki)
			return 'new?redlink=1&title='.$topic;
		else
			return strtolower(str_replace(' ','-',$topic));
	}
	
	function handle_internallink($matches) {
		//var_dump($matches);
		$nolink = false;
		
		$href = $matches[4];
		$title = $matches[6] ? $matches[6] : $href.$matches[7];
		$namespace = $matches[3];

		if ($namespace=='Image') {
			$options = explode('|',$title);
			$title = array_pop($options);
			
			return $this->handle_image($href,$title,$options);
		}		
		
		$title = preg_replace('/\(.*?\)/','',$title);
		$title = preg_replace('/^.*?\:/','',$title);
		$wiki = get_page_by_title($href);
		
		if(!$wiki)
			$redlink = 'style="color:red"';
		else
			$redlink = false;
		
		if ($this->reference_wiki) {
			$href = $this->reference_wiki.($namespace?$namespace.':':'').$this->wiki_link($href);
		} else {
			$nolink = true;
		}

		if ($nolink) return $title;
		
		return sprintf(
			'<a %s href="%s"%s>%s</a>',
			$redlink,
			$href,
			($newwindow?' target="_blank"':''),
			$title
		);
	}
	
	function parse_line($line) {
		$line_regexes = array(
			'preformat'=>'^\s(.*?)$',
			'definitionlist'=>'^([\;\:])\s*(.*?)$',
			'newline'=>'^$',
			'list'=>'^([\*\#]+)(.*?)$',
			'sections'=>'^(={1,6})(.*?)(={1,6})$',
			'horizontalrule'=>'^----$',
		);
		$char_regexes = array(
//			'link'=>'(\[\[((.*?)\:)?(.*?)(\|(.*?))?\]\]([a-z]+)?)',
			'internallink'=>'('.
				'\[\['. // opening brackets
					'(([^\]]*?)\:)?'. // namespace (if any)
					'([^\]]*?)'. // target
					'(\|([^\]]*?))?'. // title (if any)
				'\]\]'. // closing brackets
				'([a-z]+)?'. // any suffixes
				')',
			'externallink'=>'('.
				'\['.
					'([^\]]*?)'.
					'(\s+[^\]]*?)?'.
				'\]'.
				')',
			'emphasize'=>'(\'{2,5})',
			'eliminate'=>'(__TOC__|__NOTOC__|__NOEDITSECTION__)',
			'variable'=>'('. '\{\{' . '([^\}]*?)' . '\}\}' . ')',
		);
				
		$this->stop = false;
		$this->stop_all = false;

		$called = array();
		
		$line = trim($line);
				
		foreach ($line_regexes as $func=>$regex) {
			if (preg_match("/$regex/i",$line,$matches)) {
				$called[$func] = true;
				$func = "handle_".$func;
				$line = $this->$func($matches);
				if ($this->stop || $this->stop_all) break;
			}
		}
		if (!$this->stop_all) {
			$this->stop = false;
			foreach ($char_regexes as $func=>$regex) {
				$line = preg_replace_callback("/$regex/i",array(&$this,"handle_".$func),$line);
				if ($this->stop) break;
			}
		}
		
		$isline = strlen(trim($line))>0;
		
		// if this wasn't a list item, and we are in a list, close the list tag(s)
		if (($this->list_level>0) && !$called['list']) $line = $this->handle_list(false,true) . $line;
		if ($this->deflist && !$called['definitionlist']) $line = $this->handle_definitionlist(false,true) . $line;
		if ($this->preformat && !$called['preformat']) $line = $this->handle_preformat(false,true) . $line;
		
		// suppress linebreaks for the next line if we just displayed one; otherwise re-enable them
		if ($isline) $this->suppress_linebreaks = ($called['newline'] || $called['sections']);
		
		return $line."\n";
	}

}
?>