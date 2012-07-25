<?php

// ** CONFIG ** //

ini_set('memory_limit', '6G');
$path = '/home/mike/Projects/mediprodirect/';		// Path from this script to medipro root.

// What strings we are looking for
$smarty = '$smarty->';
$assign = 'assign(';
$fetch = 'fetch(';
$fetch2 = 'fetch2(';
$render = 'render(';
$render2 = 'render2(';
$display = 'display(';

//lengths
$smartyl = strlen($smarty);
$assignl = strlen($assign);
$fetchl = strlen($fetch);
$fetch2l = strlen($fetch2);
$renderl = strlen($render);
$render2l = strlen($render2);
$displayl = strlen($display);

function getToComma($content, $nextpos) {
	$return = '';
	$stack = array();
	$nextpos--;
	while($nextpos <= strlen($content)) {
		$nextpos++;
		switch(substr($content,$nextpos,1)) {
			case '\'': 
			case '"':
				if(end($stack) == substr($content,$nextpos,1)) array_pop($stack);
				else $stack[] = substr($content,$nextpos,1);
				break;
			case ')':
				if(end($stack) == '(') array_pop($stack);
				break;
			case '(':
				if(!in_array('\'',$stack) && !in_array('"', $stack)) $stack[] = '(';
				break;
			case ',':
				$nextpos++;
				if(empty($stack)) return $return;
		}
		$return .= substr($content,$nextpos,1);
	}
	
	file_put_contents('php://stderr', $file . 'Couldn\'t find comma separator');
	die('Couldn\'t find comma separator');
}

function getToCloseParen($content, $nextpos) {
	$stack = array();
	$nextpos--;
	$return = '';
	while($nextpos <= strlen($content)) {
		$nextpos++;
		$char = substr($content,$nextpos,1);
		switch($char) {
			case '\'':
				if(end($stack) == '"') break;
				if(end($stack) == $char) array_pop($stack);
				else $stack[] = $char;
				break;
			case '"':
				if(end($stack) == '\'') break;
				if(end($stack) == $char) array_pop($stack);
				else $stack[] = $char;
				break;
			case ')':
				if(empty($stack)) return $return;
				else if(end($stack) == '(') array_pop($stack);
				break;
			case '(':
				if(!in_array('\'',$stack) && !in_array('"', $stack)) $stack[] = '(';
				break;
			case ',':
				break;
		}
		$return .= substr($content,$nextpos,1);
	}
	
	file_put_contents('php://stderr', $file . 'Couldn\'t find ending paren');
	die('Couldn\'t find ending paren');
}


// ** INIT ** //

$file = $path . $argv[1];

if(!file_exists($file)) {
	die('File ' . $file . ' does not exist.' . PHP_EOL);
}

echo $file . PHP_EOL;

$handle = fopen($file,'r');
if(!$handle) {
	file_put_contents('php://stderr', $file . ' failed to read');
	die('Couldn\'t open file for r' . PHP_EOL);
}

$content = file_get_contents($file);

$nextpos = 0;
$out = '';

while(true) {
	$prevpos = $nextpos;
	$nextpos = strpos($content, $smarty, $nextpos);
	
	if($nextpos === false) {
		$out .= substr($content, $prevpos);
		break;
	}
	
	$out .= substr($content, $prevpos, $nextpos - $prevpos);
	
	$nextpos += $smartyl;
	switch($nextpos) {
		case strpos($content, $assign, $nextpos):
			$nextpos += $assignl;
			$out .= '$this->_data[' . getToComma($content, &$nextpos) . '] = ';
			$out .= getToCloseParen($content, &$nextpos);
			$nextpos++;
			break;
		
		case strpos($content, $fetch, $nextpos):
		case strpos($content, $fetch2, $nextpos):
		case strpos($content, $render, $nextpos):
		case strpos($content, $render2, $nextpos):
		case strpos($content, $display, $nextpos):
			$out .= 'STools::assignData(&$smarty, @$_data);' . PHP_EOL;
			// Don't break, we have to output the $smarty-> we read past
			
		default:
			$out .= $smarty;
	}
}

fclose($handle);

$handle = fopen($file,'w');

if(!$handle || !fwrite($handle,$out)) {
	file_put_contents('php://stderr', $file . ' failed to write');
	die('Couldn\'t write to file' . PHP_EOL);
}

fclose($handle);
