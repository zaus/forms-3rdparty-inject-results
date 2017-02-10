<?php

$type = $_SERVER["CONTENT_TYPE"];
if(empty($type)) {
	$headers = getallheaders();
	if(isset($headers['Accept'])) $type = $headers['Accept'];
	if(empty($type) && isset($headers['Content-Type'])) $type = $headers['Content-Type'];
}

// echo $type, "\n";
// alter keys so it will look different dor echo
$prerequest = $_GET + $_POST;

$raw = file_get_contents("php://input");

if(strpos($type, 'json') !== false) {
	// also attach decoded raw body
	$prerequest += json_decode($raw, true);
}
// probably do the same for xml, but too much trouble

$request = array();
foreach($prerequest as $k => $v) {
	$request[ 'req-' . $k ] = $v;
}

// print_r(array('req' => $req, 'raw' => $raw));

if(strpos($type, 'json') !== false) {
	echo json_encode($request);
	exit;
}
if(strpos($type, 'xml') !== false) {
	// http://stackoverflow.com/questions/1397036/how-to-convert-array-to-simplexml
	function to_xml(SimpleXMLElement $object, array $data)
	{
		foreach ($data as $key => $value) {
			if (is_array($value)) {
				$new_object = $object->addChild($key);
				to_xml($new_object, $value);
			} else {
				$object->addChild($key, $value);
			}
		}
	}

	if(empty($raw)) $raw = '<request />';
	echo to_xml(new SimpleXMLElement($raw), $request)->asXML();
	exit;
}

function plain_print($request, $depth = 0) {
	// add some whitespace just to make sure trim works

	if(!is_array($request) && !is_object($request)) echo $request;
	else {
		$indent = str_repeat("\t", $depth);

		foreach($request as $k => $v) {
			echo "\n", $indent, $k, ' = ';
			plain_print($v, $depth+1);
		}
	}
}
plain_print($request);

