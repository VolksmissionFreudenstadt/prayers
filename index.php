<?php

$config = yaml_parse_file('config.yaml');

$db = new mysqli ($config['kOOL']['db']['host'], 
				  $config['kOOL']['db']['user'], 
				  $config['kOOL']['db']['pass'], 
				  $config['kOOL']['db']['name']);
					 

$res = $db->query('SELECT * FROM ko_leute WHERE FIND_IN_SET(\''
				  .sprintf('g%06d:r%06d', 
				  		   $config['kOOL']['group']['id'], 
				  		   $config['kOOL']['group']['role'])
				  .'\', groups);');

$p = array();
while ($row = $res->fetch_assoc()) $p[] = $row;


// do some name sorting:
$pp = array();
foreach ($p as $person) {
	$pp[$p['nachname']][] = $p['vorname'];
}

foreach ($pp as $key => $val) {
	$x = count ($val);
	$pp[$key] = join(', ', $val);
}

die ('<pre>'.print_r($pp, 1));
