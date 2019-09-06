<?php
/*

Plugin Name: Forms: 3rd-Party Inject Results
Plugin URI: https://github.com/zaus/forms-3rdparty-inject-results
Description: Attach the results to the original submission
Author: zaus
Version: 0.2
Author URI: http://drzaus.com
Changelog:
	0.1	it begins
	0.2	working version (at least, with GF) + readme
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

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

	const FIELD = 'i2';
	const DELIM = '/';

	public function service_settings($eid, $P, $entity) {
		?>
		<fieldset class="postbox"><legend class="hndle"><span><?php _e('Inject Results', $P); ?></span></legend>
			<div class="inside">
				<?php $field = self::FIELD; ?>
				<div class="field">
					<label for="<?php echo $field, '-', $eid ?>"><?php _e('Include which results?', $P); ?></label>
					<textarea name="<?php echo $P, "[$eid][$field]"?>" class="text wide fat" id="<?php echo $field, '-', $eid ?>"><?php if(isset($entity[$field])) echo esc_html($entity[$field]) ?></textarea>
					<em class="description"><?php echo sprintf(__('Enter the list of result values (given in url-nested form), one per line, to include in the response.  Provide field aliases with %s', $P), "<code>response" . self::DELIM . "key=alias</code>"); ?></em>
				</div>
			</div>
		</fieldset>
		<?php
	}
	#endregion ---------- ui -------------

	/**
	 * Attach the rest of the hooks if we're supposed to; also remembers what's available here
	 * @param $submission
	 * @param $form
	 * @param $service
	 * @return mixed
	 */
	function attach($submission, $form, $service) {
		### _log(__CLASS__, array('service' => $service['name'], 'inject' => $service[self::FIELD], 'submission' => $submission));

		if(!isset($service[self::FIELD]) || empty($service[self::FIELD])) return $submission;

		// hook elsewhere

		// needed to remember the response; not service-specific (postagain?) but should be fine for where we end up
		add_action(Forms3rdPartyIntegration::$instance->N('service'), array(&$this, 'remember'), 100, 3);
		// do the actual result injection
		add_action(Forms3rdPartyIntegration::$instance->N('remote_success'), array(&$this, 'inject'), 100, 3);

		// save a reference in case it's easier to inject
		$this->submission = $submission;

		## _log(__CLASS__, __FUNCTION__);

		return $submission;
	}

	/**
	 * Remember the response so we can use it in the right place
	 * @param $response
	 * @param $ref
	 */
	function remember($response, $ref, $sid) {
		## _log(__CLASS__, __FUNCTION__);

		$this->response = $response;
	}

	function inject($form, $ref, $service) {
		$reposts = array_map('trim', explode("\n", $service[self::FIELD]));

		$resultsArgs = $this->parse($this->response);

		### _log(__CLASS__ . '.' . __FUNCTION__ . ':' . __LINE__, $reposts, $resultsArgs);

		// get each repost from the results
		$extracted = array();
		foreach($reposts as $repost) {

			// were we given an alias? (optional)
			$alias = explode('=', $repost);
			$repost = reset($alias);
			$alias = end($alias);

			// only set if the desired key is present in the results
			if(isset($resultsArgs[$repost])) {
				$extracted[$alias] = $resultsArgs[$repost];
			}
		}

		// just in case there's some dynamic stuff not already part of the form submission
		$extracted += $this->submission;

		// inject each repost into $form submission
		$form = apply_filters(Forms3rdPartyIntegration::$instance->N('inject'), $form, $extracted);

		### _log(__CLASS__ . '.' . __FUNCTION__ . ':' . __LINE__, 'extracted', $extracted, $form);

		return $form;
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

	function flattenWithKeys(array $array, $childPrefix = self::DELIM, $root = '', $result = array()) {
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