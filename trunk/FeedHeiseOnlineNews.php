<?php
/* heise News */

/* Heise Feed Version 0.2 MODIFIED by Sören Jentzsch */
header("Content-type: text/xml; charset=UTF-8");

/* URL zu dieser Datei bzw. zu dem Ordner */
define('URL', 'http://feeds.legonomy.de/');

/* Cache-Ordner, in dem die Artikel zwischengespeichert werden */
define('CACHEFOLDER', 'FeedHeiseOnlineNews');

/* Sekunden, in denen der Feed nicht aktualisiert wird. Das verhindert bei mehreren Clients, dass der Server und Heise belastet werden. */
define('FEEDINTERVAL', 60);

/* Anzahl der Artikel, die im Cache-Ordner gespeichert werden sollen */
define('MAXARTIKELS', 300);

/* Anzahl der Zeichen des Teasers (genau: Teaser = die ersten MAXCHARS Zeichen minus zuletzt angefangener Satz (wenn Rest mind. 200 Zeichen umfasst, ansonsten einfach abschneiden)) */
define('MAXCHARS', 600);

/* Hole den Artikel mit ID und Datum von dem Cache-Ordner bzw. von Heise */
function getArtikel($id, $date)
{
	/* Artikel aus dem Cacheordner holen, falls er existiert und nicht reload angegeben ist. */
	if($_GET["do"] != "reload" && file_exists(CACHEFOLDER."/".$date."-".$id.".txt"))
		$artikelFertig = file_get_contents(CACHEFOLDER."/".$date."-".$id.".txt");
	else
	{
		if(!($artikelRoh = file_get_contents('http://www.heise.de/newsticker/meldung/'.$id.'.html?view=print')))
			$artikelFertig = "Fehler: Artikel konnte nicht geladen werden";
		else
		{
			if(preg_match("/<div class=\"meldung_wrapper\">(.+)<\/div>(.+)<\/div>/si", $artikelRoh, $treffer))
			{
				$artikelFertig = $treffer[0];
				$artikelFertig = strip_tags($artikelFertig, '<p><span>');
				$artikelFertig = preg_replace("/<span[^>]*>(.*?)<\/span>/", "", $artikelFertig); // lösche ggf. Bildunterschriften
				$artikelFertig = preg_replace("/\[\d+\]/", "", $artikelFertig);
				$artikelFertig = trim(htmlspecialchars($artikelFertig));
				
				/*$posMarker = 0;
				$posB = -1;
				for($i=1; $i<5; $i++)
				{
					$posMarker = strpos($artikelFertig, '['.$i.']', $posMarker);
					if($posMarker && $posMarker <= 1000)
					{
						$startURL = strpos($artikelFertig, 'http', strpos($artikelFertig, '['.$i.']', $posMarker+1));
						$URL = substr($artikelFertig, $startURL, strpos($artikelFertig, '&nbsp;', $startURL)-$startURL);
						strpos($artikelFertig, '['.$i.']', $posMarker);
						$posB = strpos($artikelFertig, '<b>', $posB+1);
						//echo "<br>".$URL."<br>";
					}
					else
						break;
				}*/
				
				// Abschneiden nach: Die ersten MAXCHARS Zeichen minus zuletzt angefangener Satz (wenn Rest mind. 200 Zeichen umfasst)
				$artikelFertig = substr($artikelFertig, 0, MAXCHARS);
				$endePunkt = strrpos($artikelFertig, '. ');
				$endeFragezeichen = strrpos($artikelFertig, '? ');
				if($endePunkt > $endeFragezeichen && $endePunkt > 200)
				{
					$artikelFertig = substr($artikelFertig, 0, $endePunkt);
					$artikelFertig .= ". ...";
				}
				else if($endeFragezeichen > 200)
				{
					$artikelFertig = substr($artikelFertig, 0, $endeFragezeichen);
					$artikelFertig .= "? ...";
				}
				else
					$artikelFertig .= " ...";
				
				file_put_contents(CACHEFOLDER."/".$date."-".$id.".txt", $artikelFertig);
			}
			else
				$artikelFertig = "Es konnte kein Artikeltext extrahiert werden";
		}
	}
	
	/* Alte Artikel im Cache-Ordner löschen */
	$files = scandir(CACHEFOLDER);
	while(count($files) > MAXARTIKELS)
	{
		unlink(CACHEFOLDER."/".$files[2]);
		$files = scandir(CACHEFOLDER);
	}
	
	return $artikelFertig;
}

/* Mit ?do=reload werden alle Artikel des Feeds neu eingelesen. Mit ?do=sync wird nur überprüft ob es neue Artikel gibt. */
if($_GET["do"]=="reload" || $_GET["do"]=="sync" || !file_exists(CACHEFOLDER."/"."FeedHeiseOnlineNews.txt") || time() - filemtime(CACHEFOLDER."/"."FeedHeiseOnlineNews.txt") > FEEDINTERVAL)
{
	$xml = DOMDocument::load("http://www.heise.de/newsticker/heise-atom.xml");
	$entrys = $xml->getElementsByTagName('entry');
	
	/* Eigene URL setzen */
	$xml->getElementsByTagName('link')->item(1)->attributes->item(1)->nodeValue=URL;

	for($i=0; $i<$entrys->length; $i++)
	{
		$entry = $entrys->item($i);
		$id = substr($entry->getElementsByTagName('id')->item(0)->nodeValue, 39, -17);
		$date = $entry->getElementsByTagName('updated')->item(0)->nodeValue;
		
		$element = $xml->createElement('summary');
		$entry->appendChild($element);
		$entry->getElementsByTagName('summary')->item(0)->setAttribute('type','html');

		/* Setze das Datum zurück, falls der Artikel nicht aktualisiert wurde. 
		* Heise verändert das Datum bei der Hälfte der Artikel, ohne dass sich
		* der Inhalt ändert. Darum wird nur ein Update gemacht, wenn auch
		* "Update" im Title steht.
		*/
		
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
		
		$artikelInhalt = getArtikel($id, $date);
		$entrys->item($i)->getElementsByTagName('summary')->item(0)->nodeValue = $artikelInhalt;
	}
	
	$feed = $xml->saveXML();
	file_put_contents(CACHEFOLDER."/"."FeedHeiseOnlineNews.txt", $feed);
	echo $feed;
}
else
	echo file_get_contents(CACHEFOLDER."/"."FeedHeiseOnlineNews.txt");
?>