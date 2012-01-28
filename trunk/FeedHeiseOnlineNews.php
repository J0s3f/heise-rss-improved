<?php
header("Content-type: text/xml; charset=UTF-8");


/* 
 * heise-rss-improved
 * Project-site: https://code.google.com/p/heise-rss-improved/
 *
 * heise RSS feed
 */


/* the source-feed which we want to improve and provide */
define('SOURCEFEED', 'http://www.heise.de/newsticker/heise-atom.xml');

/* URL pointing to the directory containing this file */
define('URL', 'http://feeds.legonomy.de/');

/* name of the cache-folder containing the articles (folder has to be writable and placed in the same folder as is this script) */
define('CACHEFOLDER', 'FeedHeiseOnlineNews');

/* seconds in which the feed is not being updated, prevents the server and heise from being stressed too much */
define('FEEDINTERVAL', 60);

/* maximum of articles being stored in the cache folder */
define('MAXARTICLES', 300);

/* set this to 0 if you want just the teaser, set this to 1 if you want the whole text of the article in your RSS feed (MAXCHARS and MINCHARS will be ignored, CARE: NOT quite tested yet!) */
define('FULLTEXT', 0);

/* amount of characters of the teaser (minus last started sentences, if the remaining characters are at least MINCHARS) */
define('MAXCHARS', 600);
define('MINCHARS', 300);



/* fetch the article with the given $id and $date from the cache-folder or (if not cached yet) from heise */
function getArticle($id, $date)
{
  /* fetch the article from the cache-folder if it exists there and "reload" is not denoted */
	if($_GET["do"] != "reload" && file_exists(CACHEFOLDER."/".$date."-".$id.".txt"))
		$articleDone = file_get_contents(CACHEFOLDER."/".$date."-".$id.".txt");
	/* else: fetch the article from heise */
  else
	{
		if(!($articleRaw = file_get_contents('http://www.heise.de/newsticker/meldung/'.$id.'.html?view=print')))
			$articleDone = 'Error: Article '.$id.' could not be fetched from heise';
		else
		{
		  /* start with extracting right after the html-element "<div class="meldung_wrapper">" */
			if(!preg_match("/<div class=\"meldung_wrapper\">(.+)<\/div>(.+)<\/div>/si", $articleRaw, $matches))
        $articleDone = 'Error: No teaser could be extracted in the article '.$id;
      else
      {
				$articleDone = $matches[0];
				$articleDone = strip_tags($articleDone, '<p><span>'); // delete html-style-formatting, except <p> and <span>
				$articleDone = preg_replace("/<span[^>]*>(.*?)<\/span>/", "", $articleDone); // delete text under pictures (~"Bildunterschriften")
				$articleDone = preg_replace("/\[\d+\]/", "", $articleDone); // delete link-referrers
				$articleDone = trim(htmlspecialchars($articleDone));  // delete htmlspecialchars
				
        if(FULLTEXT == 0)
        {
  				/* cut the teaser after MAXCHARS characters minus last beginning sentence (if the rest is at least MINCHARS characters long) */
          $articleDone = substr($articleDone, 0, MAXCHARS);
  				$posPoint = strrpos($articleDone, '. ');
  				$posQuestionMark = strrpos($articleDone, '? ');
  				if($posPoint > $posQuestionMark && $posPoint > MINCHARS)
  					$articleDone = substr($articleDone, 0, $posPoint) . '. ...';
  				else if($posQuestionMark > MINCHARS)
  					$articleDone = substr($articleDone, 0, $posQuestionMark) . '? ...';
  				else
  					$articleDone .= " ...";
        }
				
        /* write this resulting teaser in a file */
				file_put_contents(CACHEFOLDER."/".$date."-".$id.".txt", $articleDone);
			}
		}
	}
	
  /* delete old articles in the cache-folder if necessary */
	$files = scandir(CACHEFOLDER);
	while(count($files) > MAXARTICLES)
	{
		unlink(CACHEFOLDER."/".$files[2]);
		$files = scandir(CACHEFOLDER);
	}
	
	return $articleDone;
}

/* modify our RSS feed only if necessary */
if($_GET["do"]=="reload" || $_GET["do"]=="sync" || !file_exists(CACHEFOLDER."/"."FeedHeiseOnlineNews.txt") || time() - filemtime(CACHEFOLDER."/"."FeedHeiseOnlineNews.txt") > FEEDINTERVAL)
{
	$xml = DOMDocument::load(SOURCEFEED);
	$entrys = $xml->getElementsByTagName('entry');
	
	/* set the field 'link' to our Server-URL */
	$xml->getElementsByTagName('link')->item(1)->attributes->item(1)->nodeValue = URL;
  
  /* now we improve/modify every single entry of the RSS feed */
	for($i=0; $i<$entrys->length; $i++)
	{
		$entry = $entrys->item($i);
    
    /* example: http://www.heise.de/newsticker/meldung/Gewinnwachstum-bei-Freenet-1241460.html/from/atom10 -> $id = Gewinnwachstum-bei-Freenet-1241460 */
		$id = substr($entry->getElementsByTagName('id')->item(0)->nodeValue, 39, -17);
    
    /* example: $date = 2011-05-11T13:41:15+02:00 */
		$date = $entry->getElementsByTagName('updated')->item(0)->nodeValue;
		
    /* now we add the new element "summary" to our rss feed */
		//$element = $xml->createElement('summary');
		//$entry->appendChild($element);
		$entry->getElementsByTagName('summary')->item(0)->setAttribute('type', 'html');

		/* heise seems to change the date of many articles without changing the content. make sure, to only update, when there is an "update" in the title of the article */
		if(!preg_match("/\[update\]/i", $entry->getElementsByTagName('title')->item(0)->nodeValue))
		{
			$files = scandir(CACHEFOLDER);
			foreach($files as $file)
			{
				if(preg_match("/.+-".$id.".txt/", $file))
				{
					$date = substr($file, 0, 25);
					$entry->getElementsByTagName('updated')->item(0)->nodeValue = $date;
				}
			}
		}
		
    /* now we fill the new element "summary" with our teaser */
    $summary_heise = trim(htmlspecialchars(strip_tags($entrys->item($i)->getElementsByTagName('summary')->item(0)->nodeValue)));
		$entrys->item($i)->getElementsByTagName('summary')->item(0)->nodeValue = "<p><i>".$summary_heise."</i></p>".getArticle($id, $date);
	}
	
  /* save the xml-file and publish it */
	$feed = $xml->saveXML();
	file_put_contents(CACHEFOLDER."/"."FeedHeiseOnlineNews.txt", $feed);
	echo $feed;
}
else
	echo file_get_contents(CACHEFOLDER."/"."FeedHeiseOnlineNews.txt");
?>