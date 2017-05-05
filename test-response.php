<?php

class F3pInjectTester {

	public function __construct() {
		$request = $this->test($_SERVER, $_POST, $_GET, file_get_contents("php://input"));

		// if it gets this far
		plain_print($request);
	}

	public function get_headers() {
		return getallheaders();
	}

	public function test($server, $post, $get, $raw) {

		$type = $server["CONTENT_TYPE"];
		if(empty($type)) {
			$headers = $this->get_headers();
			if(isset($headers['Accept'])) $type = $headers['Accept'];
			if(empty($type) && isset($headers['Content-Type'])) $type = $headers['Content-Type'];
		}

		// echo $type, "\n";
		// alter keys so it will look different for echo
		$prerequest = $post + $get;

		if(strpos($type, 'json') !== false) {
			// also attach decoded raw body
			$prerequest += json_decode($raw, true);
		}
		// probably do the same for xml, but too much trouble

		$request = array();
		// dynamically modify the 'request'
		$dyn = array('prefix' => null, 'suffix' => null);
		foreach($dyn as $k => &$o)
			if(isset($prerequest[$k])) {
				$o = $prerequest[$k];
				unset($prerequest[$k]);
			}

		foreach($prerequest as $k => $v) {
			$request[ 'req-' . $k ] = $dyn['prefix'] . $v . $dyn['suffix'];
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

		// maybe it gets this far
		return $request;
	}


	public function plain_print($request, $depth = 0) {
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

	public static function get_local_ip() {
		if(isset($_SERVER['SERVER_ADDR'])) {
			$name = $_SERVER['SERVER_ADDR'];
			// but...testing locally is blank... http://stackoverflow.com/questions/30759362/php-variable-serverserver-addr-is-blank-when-using-crontab
			if (!empty($name)) return $name;
		}

		// the following might be the local ip, not public ip? but it could be all we have...
		if(!function_exists('socket_create')) return getHostByName(getHostName());

		// http://stackoverflow.com/a/36604437/1037948

		$sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		socket_connect($sock, "8.8.8.8", 53);
		socket_getsockname($sock, $name); // $name passed by reference

		return $name;
	}
}


// protect

// same ip?
$visitor = $_SERVER['REMOTE_ADDR'];
$me = F3pInjectTester::get_local_ip();

if($visitor == $me) new F3pInjectTester();
else {
	echo json_encode(array(
		// 'me' => $me, 'visitor' => $visitor,
		'error' => 'rejected'));
	exit;
}

