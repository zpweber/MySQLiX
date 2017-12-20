<?php
namespace util;

function quick_token(int $length) : string{
	return bin2hex(openssl_random_pseudo_bytes($length * .5));
}

function simple_func_bench(int $intervals, callable $function, ...$args){
	$start = microtime(true);
	for($i = 0; $i < $intervals; $i++){
		call_user_func_array($function, $args);
	}
	$end = microtime(true);
	$timeTot = bcsub($end, $start, 4);
	$timePer = bcdiv($timeTot, $intervals, 10);
	return ['total' => $timeTot, 'per' => $timePer];
}

function getTypeX($var){
	return @get_class($var) ?: getType($var);
}

function addScheme($url, $scheme = 'http://'){
  return parse_url($url, PHP_URL_SCHEME) === null ? $scheme . $url : $url;
}
?>
