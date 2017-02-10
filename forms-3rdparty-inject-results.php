<?php
/*

Plugin Name: Forms: 3rd-Party Inject Results
Plugin URI: https://github.com/zaus/forms-3rdparty-inject-results
Description: Attach the results to the original submission
Author: zaus
Version: 0.1
Author URI: http://drzaus.com
Changelog:
	0.1	it begins
*/

class Forms3rdpartyInjectResults {

	function __construct() {
		add_action('init', array(&$this, 'init'));
	}

	function init() {
		// prepare for attachment -- use this as a convenient hook to access $services and remember $submission
		add_action(Forms3rdPartyIntegration::$instance->N('get_submission'), array(&$this, 'attach'), 100, 3);
		// configure whether to attach or not, how
		add_filter(Forms3rdPartyIntegration::$instance->N('service_settings'), array(&$this, 'service_settings'), 10, 3);
	}

	#region ---------- ui -------------

	const FIELD = 'p2';

	public function service_settings($eid, $P, $entity) {
		$services = [];
		foreach(Forms3rdPartyIntegration::$instance->get_services() as $sid => $service) {
			// but NOT this one
			if($eid != $sid)
				$services []= array('id' => $sid, 'title' => isset($service['name']) ? $service['name'] : 'unknown-form');
		}
		?>
		<fieldset class="postbox"><legend class="hndle"><span><?php _e('Secondary Post', $P); ?></span></legend>
			<div class="inside">
				<?php $field = self::FIELD; ?>
				<div class="field">
					<label for="<?php echo $field, '-', $eid ?>"><?php _e('Secondary Post', $P); ?></label>
					<?php
					Forms3rdPartyIntegration::$instance->form_select_input($services, $eid, isset($entity[$field]) ? $entity[$field] : false, $field);
					?>
					<em class="description"><?php _e('Choose one or more services to perform afterwards.  The response from the previous service will be made available to the mapping as part of the submission.', $P); ?></em>
				</div>
			</div>
		</fieldset>
		<?php
	}
	#endregion ---------- ui -------------

	function attach($submission, $form, $service) {
		if(!isset($service[self::FIELD]) || empty($service[self::FIELD])) return $submission;

		add_action(Forms3rdPartyIntegration::$instance->N('use_submission'), array(&$this, 'attach2'), 100, 3);

		$reposts = (array) $service[self::FIELD];

		// save for later; should be okay in multi-resend scenario since it traverses services in order
		$this->submission = $submission;
		$this->form = $form;
		$this->reposts = $reposts;

		return $submission;
	}

	/**
	 * Actually attach followup, secondary post only to this `service_a` hook
	 * @param bool $use_this_form filter return value
	 * @param $submission user submission
	 * @param $sid the current form's id
	 * @return mixed
	 */
	function attach2($use_this_form, $submission, $sid) {
		if(!$use_this_form) return $use_this_form;

		// so we can reuse the relevant parts not available to the `service_a` hook
		// add_filter(Forms3rdPartyIntegration::$instance->N('service_filter_post_'.$eid), array(&$this, 'remember'));
		// actually perform the secondary post given the response
		add_action(Forms3rdPartyIntegration::$instance->N('service_a'.$sid), array(&$this, 'resend'), 10, 2);
		return $use_this_form;
	}

	function resend($body, $param_ref) {
		extract($param_ref); //array('success'=>false, 'errors'=>false, 'attach'=>'', 'message' => '')

		$f3p = Forms3rdPartyIntegration::$instance;
		$debug = $f3p->get_settings();

		$resultsArgs = $this->parse($body);
		$submission = $resultsArgs + $this->submission;
		### _log('f3p-again--'.__FUNCTION__, $body, $resultsArgs, $submission);

		foreach($this->reposts as $sid) {
			$service = $f3p->get_services()[$sid];
			### _log('f3p-again--'.__FUNCTION__, $sid, $service);
			$sendResult = $f3p->send($submission, $this->form, $service, $sid, $debug);
			if($sendResult === Forms3rdPartyIntegration::RET_SEND_STOP || $sendResult === Forms3rdPartyIntegration::RET_SEND_SKIP) return;

			$response = $sendResult['response'];
			$post_args = $sendResult['post_args'];

			return $f3p->handle_results($submission, $response, $post_args, $this->form, $service, $sid, $debug);
		}
	}

	function parse($body) {
		// what kind of response is it?
		$body = trim($body);
		$first = substr($body, 0, 1);
		/*if(substr(trim($body), 0, 5) == '<?xml') {
			// simplexml can't handle wacky namespaces?
			$body = substr($body, strpos($body, '?>')+2);
			$content = $this->parse( $body );
		}
		else*/if($first == '<') {
			// $content = simplexml_load_string( $body );
			$dom = new DomDocument();
			$dom->loadXML($body);
			$content = $this->xml_to_array($dom);
			### _log('parsed xml dom', $content);
		}
		elseif($first == '{') $content = json_decode($body, true);
		else $content = array('#CONTENT' => $body);

		### _log('f3p-again--'.__FUNCTION__, $content);

		return $this->flattenWithKeys( (array) $content );
	}

	function xml_to_array($root) {
		// based on http://stackoverflow.com/a/14554381/1037948
		// TODO: strip namespaces

		$result = array();

		if ($root->hasAttributes()) {
			foreach ($root->attributes as $attr) {
				$result['@' . $attr->name] = $attr->value;
			}
		}

		if ($root->hasChildNodes()) {
			$children = $root->childNodes;
			if ($children->length == 1) {
				$child = $children->item(0);
				if ($child->nodeType == XML_TEXT_NODE || $child->nodeType == XML_CDATA_SECTION_NODE) {
					$result['#t'] = $child->nodeValue;
					return count($result) == 1
						? $result['#t']
						: $result;
				}
			}
			$groups = array();
			foreach ($children as $child) {
				if($child->nodeType == XML_TEXT_NODE && empty(trim($child->nodeValue))) continue;
				### _log(XML_TEXT_NODE, $child->nodeType, $child->nodeName, json_encode($child->nodeValue));
				if (!isset($result[$child->nodeName])) {
					$result[$child->nodeName] = $this->xml_to_array($child);
				} else {
					if (!isset($groups[$child->nodeName])) {
						$result[$child->nodeName] = array($result[$child->nodeName]);
						$groups[$child->nodeName] = 1;
					}
					$result[$child->nodeName][] = $this->xml_to_array($child);
				}
			}
		}

		return $result;
	}

	function flattenWithKeys(array $array, $childPrefix = '/', $root = '', $result = array()) {
		// https://gist.github.com/kohnmd/11197713#gistcomment-1895523

		foreach($array as $k => $v) {
			if(is_array($v) || is_object($v)) $result = $this->flattenWithKeys( (array) $v, $childPrefix, $root . $k . $childPrefix, $result);
			else $result[ $root . $k ] = $v;
		}
		return $result;
	}

}//---	class

// engage!
new Forms3rdpartyInjectResults();