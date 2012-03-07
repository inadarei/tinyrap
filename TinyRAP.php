<?php

define ('TINYRAP_MAX_NUM_OF_ITEMS_PER_FEED', 50);

function tinyrap_parse($text, $suppress_errors = false) {

  if ($suppress_errors) {
      $feed = @simplexml_load_string($text);	
  } else {
      $feed = simplexml_load_string($text);	
  }
  
  if ($feed) {
  	if ($feed->channel && $feed->channel->item) {
  	  return makeRssFeed($feed->channel);
  	} else if ($feed->channel && $feed->item) {
  		return makeRssFeed($feed);
  	} else if ($feed->getName() == 'feed') {
  		return makeAtomFeed($feed);
  	}
  }
  
  return FALSE;
}

class TinyRAPFeedEntry {
	
	public $id;
	public $title;
	public $link;
	public $summary;
	public $content;	
	public $date;
	public $media;
	
	private static function stripHTML($str, $tags = '') {
		return preg_replace("/\n/", '' , strip_tags(html_entity_decode((string) $str), $tags));
	}
	
	function setId($str) {
		$this->id = md5(preg_replace('/[^\w]/si', '', $str));
	}
	
	function setLink($link) {
		$this->link = (string) $link;
	}
	
	function setTitle($title) {
		$this->title = self::stripHTML($title);
	}
	
	function setSummary($summary) {
		$this->summary = self::stripHTML($summary);
	}
	
	function setContent($content) {
		$this->content = self::stripHTML($content, '<img><b><i><strong><em><br><span><div><a>');
	}	
	
	function setDate($date) {
		$this->date = (string) $date;
	}
	
	function addMedia($media) {
		$this->media = $media;
	}
	
}

class TinyRAPFeedMedia {		
	public $title;
	public $description;
	public $thumbnail;
	public $content = array();
	public $player = array(); //?		
}

class Feed {
		
	public $title;
	public $link;
	public $description;
	public $author;
	public $entries = array();
	public $hash;
			
	function addEntry($entry) {
		$this->entries[] = $entry;
	}
	
}

function makeRssFeed($feed) {
	
	$wf = new Feed();
	
	if ($feed->channel) {
		
		$wf->title = (string)$feed->channel->title;
		$wf->link = (string)$feed->channel->link;
		$wf->description = (string)$feed->channel->description;
		$wf->author = (string)$feed->channel->author;
		
	} else {
		
		$wf->title = (string)$feed->title;
		$wf->link = (string)$feed->link;
		$wf->description = (string)$feed->description;
		$wf->author = (string)$feed->author;
		
	}
	
	$count = 1;
	
	foreach ($feed->item as $key=>$item) {
	     
		if ($count > TINYRAP_MAX_NUM_OF_ITEMS_PER_FEED) break;
		
		$entry = new TinyRAPFeedEntry();
	    
	    if (!empty($item->guid)) {
	      $entry->setId($item->guid);
	    }
	    else {
	      $entry->setId($item->link);
	    }
	    $entry->setLink($item->link);
	    $entry->setTitle($item->title);
	    $entry->setSummary($item->description);
	    $entry->setContent($item->description);
	    $entry->setDate($item->pubDate);
					
		$media = $item->children('media', true);
		if ($media) {
			if ($media->group) {
				$media = $media->group;
			}
			$fm = new TinyRAPFeedMedia();
			if ($media->title) {
				$fm->title = strip_tags(html_entity_decode((string)$media->title));
			} else {
				$fm->title = $entry->title;
			}
			if ($media->description) {
				$fm->description = strip_tags(html_entity_decode((string)$media->description));
			} else {
				$fm->description = $entry->content;
			}
			if ($media->thumbnail) {
				$attributes = $media->thumbnail->attributes();
				foreach($attributes as $attr=>$value) {
					$fm->thumbnail[$attr] = (string)$value;
				}
			}			
			if ($media->player) {
				$attributes = $media->player->attributes();
				foreach($attributes as $attr=>$value) {
					$fm->player[$attr] = (string)$value;
				}					
			}
			if ($media->content) {
				$attributes = $media->content->attributes();
				$image = false;
				foreach($attributes as $attr=>$value) {
					$value = (string) $value;					
					if ($attr == 'medium' && $value == 'image') {
						$image = true;
					}
					$fm->content[$attr] = (string)$value;
				}
				if ($image == true && isset($fm->content['url'])) {
					$fm->thumbnail['url'] = $fm->content['url'];
				}	
			}
			if ($item->enclosure && !$fm->content) {
				$attributes = $item->enclosure->attributes();
				foreach($attributes as $attr=>$value) {
					if (!isset($fm->content[$attr])) {
						$fm->content[$attr] = (string)$value;
					}
				}
			}			
			$entry->addMedia($fm);
		}
		
		$wf->addEntry($entry);
		
		$count++;
		
	}
	
	$wf->hash = tinyrap_calculate_feed_hash($wf);
	
	return $wf;
	
}

function makeAtomFeed($feed) {
	
	function getAtomLink($links) {
		$link = null;
		foreach($links as $link) {
	    	$attributes = $link->attributes();
	    	if (isset($attributes['type']) &&
	    		isset($attributes['href']) &&
	    		strtolower($attributes['type']) == 'text/html') {
	    		$link = $attributes['href'];
	    		break;
	    	}
	    }
	    return (string) $link;
	}
	
	$wf = new Feed();
	$wf->title = (string) $feed->title;
	$wf->link = (string) getAtomLink($feed->link);
	$wf->description = (string) $feed->summary;
	
	$count = 1;
	
	foreach ($feed->entry as $key=>$item) {
	    
		if ($count > TINYRAP_MAX_NUM_OF_ITEMS_PER_FEED) break;
		
		$entry = new TinyRAPFeedEntry();
	    
	    if (!empty($item->id)) {
	      $entry->setId($item->id);
	    }
	    else {
    		$link = getAtomLink($item->link);
	      $entry->setId($item->link);
	    }
	    
	    $entry->setLink($link);
	    $entry->setTitle($item->title);
	    $entry->setSummary($item->summary);
	    $entry->setContent($item->content);
	    $entry->setDate($item->published);
	    
	    $wf->addEntry($entry);
	    
	    $count++;
	    
	}
	
	$wf->hash = tinyrap_calculate_feed_hash($wf);
	return $wf;
	
}	

function tinyrap_calculate_feed_hash($wf) {
  $ids = array();
  if (is_array($wf->entries)) {
    foreach($wf->entries as $entry) {
      $ids[] = $entry->id;
    }
    sort($ids);
    $allids = "";
    foreach ($ids as $id) {
      $allids .= $id;
    }
    return md5($allids);
  }
  else {
    return md5(microtime());
  }
}
