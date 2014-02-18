<?php



/**
* Implement word wrapping... 
* 
* Make sure to set the font on the ImagickDraw Object first!
*
* @author BMiner
* @author Christoph Fischer <chris@toph.de>
* @link http://stackoverflow.com/questions/5746537/how-can-i-draw-wrapped-text-using-imagick-in-php/5746551#5746551
* @param image the Imagick Image Object
* @param draw the ImagickDraw Object
* @param text the text you want to wrap
* @param maxWidth the maximum width in pixels for your wrapped "virtual" text box
* @param startX the x coordinate of the text
* @param startY the y coordinate of the text
* @return void
*/
function wordWrapAnnotation(&$image, &$draw, $text, $maxWidth, $startX, $startY) {
    $words = explode(" ", $text);
    $lines = array();
    $i = 0;
    $lineHeight = 0;
    while($i < count($words) ) {
        $currentLine = $words[$i];
        if($i+1 >= count($words)) {
            $lines[] = $currentLine;
            break;
        }
        //Check to see if we can add another word to this line
        $metrics = $image->queryFontMetrics($draw, $currentLine . ' ' . $words[$i+1]);
        while($metrics['textWidth'] <= $maxWidth) {
            //If so, do it and keep doing it!
            $currentLine .= ' ' . $words[++$i];
            if($i+1 >= count($words))
                break;
            $metrics = $image->queryFontMetrics($draw, $currentLine . ' ' . $words[$i+1]);
        }
        //We can't add the next word to this line, so loop to the next line
        $lines[] = $currentLine;
        $i++;
        //Finally, update line height
        if($metrics['textHeight'] > $lineHeight) 
            $lineHeight = $metrics['textHeight'];
    }
	// Write to the image    
    $y = $startY;
    foreach ($lines as $line) {
    	if (substr($line, 0, 1)==utf8_decode('·')) $line=trim(substr($line, 2));
    	$image->annotateImage($draw, $startX, $y, 0, $line);
    	$y += $lineHeight;
    }
}


function createImage ($config, $text, $blessing) {
	// create the image
	$img = new Imagick();
	$img->newImage (1024, 768, new ImagickPixel('white'));
	$img->setImageFormat('jpeg');
	
	// font settings
	$draw = new ImagickDraw();
	$draw->setFillColor('black');
	$draw->setFont(dirname(__FILE__).'/fonts/OpenSans-Regular.ttf');
	$draw->setFontSize(43);
	
	// blessing
	wordWrapAnnotation ($img, $draw, $blessing, 944, 40, 160);
		
	// prayer
	wordWrapAnnotation ($img, $draw, $text, 944, 40, 460);
	
	// headings
	$draw->setFont(dirname(__FILE__).'/fonts/OpenSans-ExtraBold.ttf');
	wordWrapAnnotation ($img, $draw, 'Diese Woche beten wir besonders für:', 944, 40, 100);
	wordWrapAnnotation ($img, $draw, 'Geschwister, die nicht mehr zum Gottesdienst kommen können:', 944, 40, 340);
				

	// write the image to the output folder
	$fileBaseName = $config['output']['prefix'].'.jpg';
	$fileName = $config['output']['path'].'/'.$fileBaseName;
	//echo 'Erstelle Folie als '.$fileName.' ...<br />';
	$img->writeImage($fileName);
	
	return $fileBaseName;
}


function getTime($s, $hour=0, $minute=0, $second=0, $base=NULL) {
	if (!is_null($base)) $tmp = strtotime($s, $base); else $tmp = strtotime($s);
	return mktime($hour, $minute, $second, strftime('%m', $tmp), strftime('%d', $tmp), strftime('%Y', $tmp));
}


//================================================================================================

// global config
define('CONFIG_FILE_NAME', 'config.yaml');
try {
	if (!file_exists(CONFIG_FILE_NAME)) throw new Exception('Konfigurationsdatei '.CONFIG_FILE_NAME.' nicht gefunden.');
	$config = yaml_parse(file_get_contents(CONFIG_FILE_NAME));	
} catch (Exception $e) {}


// get "next sunday"
if (strftime('%w')) $startDate=getTime('next Sunday', 11); else $startDate = getTime('now', 11);
$config['output']['prefix'] = strftime($config['output']['prefix'], $startDate);

$db = new mysqli ($config['kOOL']['db']['host'], 
				  $config['kOOL']['db']['user'], 
				  $config['kOOL']['db']['pass'], 
				  $config['kOOL']['db']['name']);
					 

// get people for prayer 					 
					 
$res = $db->query('SELECT * FROM ko_leute WHERE FIND_IN_SET(\''
				  .sprintf('g%06d:r%06d', 
				  		   $config['kOOL']['prayers']['group']['id'], 
				  		   $config['kOOL']['prayers']['group']['role'])
				  .'\', groups);');

$p = array();
while ($row = $res->fetch_assoc()) $p[] = $row;


// do some name sorting:
$pp = array();
foreach ($p as $person) {
	$pp[$person['nachname']][] = $person['vorname'];
}
ksort($pp);

foreach ($pp as $key => $val) {
	asort($val);
	$last = array_pop($val).' '.$key;
	$pp[$key] = count($val) ? join(', ', $val).' und '.$last : $last;
}

$text = join (utf8_decode(' · '), $pp);

// get people for blessing

$res = $db->query('SELECT title FROM `ko_event` WHERE `eventgruppen_id`='.$config['kOOL']['blessing']['group'].' and `startdatum`=\''.strftime('%Y-%m-%d', $startDate).'\'');
$blessing = $res->fetch_assoc();
$blessing = $blessing['title'];


//die ($text);
$fn = createImage($config, $text, $blessing);
echo '<html><head></head><body style="background-color: black;">';
echo '<img src="output/'.$fn.'" />';


