<?php
/**
 * Hawk
 *
 * 
 * HTTP authentication scheme using a message authentication code (MAC)
 * algorithm to provide partial HTTP request cryptographic verification.
 *
 * See for details: https://github.com/hueniverse/hawk
 * 
 */
class Hawk {

	// MAC normalization format version
	const HeaderVersion = 1;

	// Suported algorithm
	protected $algo = array('poly1305', 'sha1', 'sha256', 'sha512');

	protected $_error = null;

	protected static $_instance = null;

	public static function instance() {
		if (!isset(static::$_instance)) {
			static::$_instance = new Hawk();
		}
		return static::$_instance;
	}

	public function getError() {
		return $this->_error;
	}

	public function getErrorCode() {
		return $this->getVal($this->_error, 0);
	}

	public function getErrorMsg() {
		return $this->getVal($this->_error, 1);
	}

	protected function setError() {
		$this->_error = func_get_args();
		return false;
	}

	/**
	 * @param array
	 * @param mixed
	 * @param mixed optional
	 * @return mixed
	 */
	public function getVal($array, $key, $default = null) {
		return isset($array[$key]) ? $array[$key] : $default;
	}

	/**
	 * @param array
	 * @param array
	 * @return array
	 */
	public function arrMerge($arr1, $arr2) {
		return array_merge($arr1, array_intersect_key($arr2, $arr1));
	}

	/**
	 * @param array
	 * @param string
	 * @return mixed
	 */
	public function parseHost($req, $hostHeaderName = null) {
		$hostHeaderName =  $hostHeaderName ? strtolower($hostHeaderName) : 'host';

		$hostHeader = $this->getVal($req, $hostHeaderName);

		if (!$hostHeader) {
			return false;
		}

		// TODO: clean up
		$hostHeaderRegex = '/^(?:(?:\r\n)?\s)*((?:[^:]+)|(?:\[[^\]]+\]))(?::(\d+))?(?:(?:\r\n)?\s)*$/iD';

		preg_match($hostHeaderRegex, $hostHeader, $hostParts);

		if (!$hostParts) {
			return false;
		}

		return array(
			'name' => $this->getVal($hostParts, 1),
			'port' => $this->getVal($hostParts, 2, $this->getVal($req, 'port'))
		);
	}

	/**
	 * @param string
	 * @param mixed
	 * @return array
	 */
	public function parseAuthorizationHeader($header, $keys = null) {
		$authKeys = array('id', 'ts', 'nonce', 'hash', 'ext', 'mac', 'app', 'dlg');

		$keys = $keys ? $keys : $authKeys;

		if (!$header || trim($header) === '') {
			return $this->setError(401);
		}

		if (!preg_match('/^(\w+)(?:\s+(.*))?$/iD', $header, $headerParts)) {
			return $this->setError(400, 'Invalid header syntax');
		}

		if (!isset($headerParts[1])) {
			return $this->setError(401);
		}

		if (strtolower($headerParts[1]) !== 'hawk') {
			return $this->setError(401);
		}

		if (!isset($headerParts[2])) {
			return $this->setError(400, 'Invalid header syntax');
		}

		$attributes = array();
		$errorMsg = null;

		$expectedValue = function($v) {
			// Allowed attribute value characters:
			//    !#$%&'()*+,-./:;<=>?@[]^_`{|}~ and space, a-z, A-Z, 0-9
			if ($v > 31 && $v < 127) {
				return ($v !== 34) && ($v !== 92);
			}
			return false;
		};

		$verify = preg_replace_callback(
			'/(\w+)="([^"]*)"\s*(?:,\s*|$)/iD',
			function ($matches) use ($keys, &$attributes, &$errorMsg, $expectedValue) {
				$m1 = $matches[1];
				$m2 = $matches[2];

				if (!in_array($m1, $keys)) {
					$errorMsg = 'Unknown attribute: ' . $m1;
					return;
				}

				for ($i = strlen($m2); $i--;) {
					if (!$expectedValue(ord($m2[$i]))) {
						$errorMsg = 'Bad attribute value: ' . $m1;
						return;
					}
				}

				if (isset($attributes[$m1])) {
					$errorMsg = 'Duplicate attribute: ' . $m1;
					return;
				}

				$attributes[$m1] = $m2;
				return '';
			},
			$headerParts[2]
		);

		if ($verify !== '' || $errorMsg) {
			return $this->setError(401, $errorMsg ? $errorMsg : 'Bad header format');
		}

		return $attributes;

	}

	protected $_request = array(
		'method' => null,
		'url' => null,
		'host' => null,
		'port' => null,
		//'resource' => null,
		'contentType' => null,
		'authorization' => null
	);

	protected $_options = array(
		'hostHeaderName' => null,
		'nonceFunc' => null,
		'timestampSkewSec' => 60,
		'localtimeOffsetSec' => 0 // seconds
	);

	/**
	 * @param array
	 * @param callback
	 * @param array
	 * @return mixed
	 */
	public function authenticate($request, $credentialsFunc, $options) {
		$req = $this->arrMerge($this->_request, $request);
		$opt = $this->arrMerge($this->_options, $options);

		$opt['timestampSkewSec'] = $this->getVal($opt, 'timestampSkewSec', 60);

		// TODO: get sntp (?)
		$now = time() + $this->getVal($opt, 'localtimeOffsetSec', 0);

		$attributes = $this->parseAuthorizationHeader($req['authorization']);
		if (!$attributes) {
			return false;
		}

		$artifacts = array(
			'method' => $req['method'],
			'resource' => $req['url'],
			'ts' => $this->getVal($attributes, 'ts'),
			'nonce' => $this->getVal($attributes, 'nonce'),
			'hash' => $this->getVal($attributes, 'hash'),
			'ext' => $this->getVal($attributes, 'ext'),
			'app' => $this->getVal($attributes, 'app'),
			'dlg' => $this->getVal($attributes, 'dlg'),
			'mac' => $this->getVal($attributes, 'mac'),
			'id' => $this->getVal($attributes, 'id')
		);

		$hostport = null;
		if (!isset($options['host']) || !isset($options['port'])) {
			$hostport = $this->parseHost($req, $opt['hostHeaderName']);
			if (!$hostport) {
				return $this->setError(500, 'Invalid host header');
			}
		}

		$artifacts['host'] = $this->getVal($options, 'host', $hostport['name']);
		$artifacts['port'] = $this->getVal($options, 'port', $hostport['port']);

		if (!$artifacts['id'] ||
			!$artifacts['ts'] ||
			!$artifacts['nonce'] ||
			!$artifacts['mac']
		) {
			return $this->setError(400, 'Missing attributes', null, $artifacts);
		}

		$creds = $credentialsFunc($attributes['id']);
		if (!$creds) {
			return $this->setError(401, 'Unknown credentials', null, $artifacts);
		}

		if (!$this->getVal($creds, 'key') || !$this->getVal($creds, 'algorithm')) {
			return $this->setError(500, 'Invalid credentials', $creds, $artifacts);
		}

		if (!in_array($creds['algorithm'], $this->algo)) {
			return $this->setError(500, 'Unknown algorithm', $creds, $artifacts);
		}

		// Check MAC
		$mac2 = base64_decode($attributes['mac'], true);
		if (!$mac2) {
			return $this->setError(401, 'Bad mac', $creds, $artifacts);
		}

		$mac1 = $this->calculateMac('header', $creds, $artifacts);

		if (!$this->bytesEqual($mac1, $this->bytesFromString($mac2))) {
			return $this->setError(401, 'Bad mac', $creds, $artifacts);
		}

		// Check payload hash
		if (isset($options['payload'])) {
			if (!isset($attributes['hash'])) {
				return $this->setError(401, 'Missing required payload hash', $creds, $artifacts);
			}

			$payloadHash = base64_decode($attributes['hash'], true);
			if (!$payloadHash) {
				return $this->setError(401, 'Bad payload hash', $creds, $artifacts);
			}

			$hashed  = $this->calculatePayloadHash($options['payload'], $creds, $req['contentType']);
			if (!$this->bytesEqual($hashed, $this->bytesFromString($payloadHash))) {
				return $this->setError(401, 'Bad payload hash', $creds, $artifacts);
			}
		}

		// Check nonce
		if ($opt['nonceFunc']) {
			if (!$opt['nonceFunc']($artifacts['nonce'], $artifacts['ts'])) {
				return $this->setError(401, 'Invalid nonce', $creds, $artifacts);
			}
		}

		// Check timestamp staleness
		if (abs(intval($attributes['ts']) - $now) > $opt['timestampSkewSec']) {
			$tsm = $this->timestampMessage($creds, $opt['localtimeOffsetSec']);
			return $this->setError(401, 'Stale timestamp', $creds, $artifacts, $tsm);
		}

		// Successful authentication
		return array($creds, $artifacts);
	}

	public function generateNormalizeString($type, $options) {
		$resource = $this->getVal($options, 'resource', '');
		if ($resource && $resource[0] !== '/') {
			$url = parse_url($resource);
			if ($url) {
				$resource = $this->getVal($url, 'path');
				$query = $this->getVal($url, 'query');
				if ($query) $resource .= '?' . $query; // Includes query
			}
		}

		$normalized = 'hawk.' . static::HeaderVersion . '.' . $type . "\n";
		$normalized .= $options['ts'] . "\n" . $options['nonce'] . "\n";
		$normalized .= strtoupper($this->getVal($options, 'method', '')) . "\n";
		$normalized .= $resource . "\n";
		$normalized .= strtolower($options['host']) . "\n" . $options['port'] . "\n";
		$normalized .= $this->getVal($options, 'hash', '') . "\n";

		if (isset($options['ext'])) {
			$ext = str_replace('\\', '\\\\', $options['ext']);
			$ext = str_replace('\n', '\\n', $ext);
			$normalized .= str_replace("\n", '\\n', $ext); // just in case
		}

		$normalized .= "\n";

		if (isset($options['app'])) {
			$normalized .= $options['app'] . "\n" . $this->getVal($options, 'dlg') . "\n";
		}

		return $normalized;
	}

	public function calculateMac($type, $credentials, $options) {
		$data = $this->generateNormalizeString($type, $options);

		return $this->createMac(
			$credentials['key'],
			$credentials['algorithm'],
			$data
		);
	}

	public function calculatePayloadHash($payload, $credentials, $contentType = '') {
		$data = trim($contentType);
		if ($data !== '') {
			$data = explode(';', $contentType, 1);
			$data = strtolower(trim($this->getVal($data, 0)));
		}
		$data = 'hawk.' . static::HeaderVersion . '.payload'. "\n" . $data . "\n" . $payload . "\n";

		if ($credentials['algorithm'] !== 'poly1305')
			return $this->bytesFromString(hash($credentials['algorithm'], $data, true));

		return Poly1305::auth(
			$this->bytesFromString($data),
			$this->bytesFromHex($credentials['key'])
		);
	}

	public function timestampMessage($credentials, $localtimeOffsetSec) {
		$now = time() + $localtimeOffsetSec;

		$mac = $this->createMac(
			$credentials['key'],
			$credentials['algorithm'],
			'hawk.' . static::HeaderVersion . '.ts' . "\n" . $now . "\n"
		);

		return array($now, base64_encode($this->bytesToString($mac)));
	}

	public function bytesFromString($str) {
		return SplFixedArray::fromArray(unpack("C*", $str), false);
	}

	public function bytesFromHex($hex) {
		$hex = preg_replace('/[^0-9a-f]/', '', $hex);
		return $this->bytesFromString(pack("H*", $hex));
	}

	public function bytesToString($bytes) {
		$bytes->rewind();
		$buf = "";
		while ($bytes->valid()) {
			$buf .= chr($bytes->current());
			$bytes->next();
		}
		$bytes->rewind();
		return $buf;
	}

	public function bytesEqual($x, $y) {
		$len = count($x);

		if ($len !== count($y)) {
			return false;
		}

		$diff = 0;
		for ($i = 0; $i < $len; $i++) {
			$diff |= $x[$i] ^ $y[$i];
		}
		$diff = ($diff - 1) >> 31;

		return (($diff & 1) === 1);
	}

	public function bytesToHex($bytes) {
		$bytes->rewind();
		$hextable = "0123456789abcdef";
		$buf = "";
		while ($bytes->valid()) {
			$c = $bytes->current();
			$buf .= $hextable[$c>>4];
			$buf .= $hextable[$c&0x0f];
			$bytes->next();
		}
		$bytes->rewind();
		return $buf;
	}

	public function createMac($key, $algo, $data) {
		$k = $this->bytesFromHex($key);

		if ($algo === 'poly1305') {
			return Poly1305::auth($this->bytesFromString($data), $k);
		}

		return $this->bytesFromString(
			hash_hmac($algo, $data, $this->bytesToString($k), true)
		);
	}

	/**
	 * EscapeHeaderAttribute escape attribute value for use in HTTP header.
	 * Allowed characters:
	 *   !#$%&'()*+,-./:;<=>?@[]^_`{|}~ and space, a-z, A-Z, 0-9, \, "
	 *
	 * @param string
	 * @return mixed
	 */
	public function escapeHeaderAttribute($value) {
		$expected = function($v) {
			if ($v <= 0x7f) {
				return 0x20 <= $v && $v <= 0x7e;
			}
			return false;
		};

		for ($i = 0, $l = strlen($value); $i < $l; ++$i) {
			if (!$expected(ord($value[$i]))) {
				$value = 'Bad attribute value (' . $value . ')';
				break;
			}
		}

		$value = str_replace('\\', '\\\\', $value);
		$value = str_replace('"', '\\"', $value);

		return $value;
	}

	/**
	 * Generate a Server-Authorization header for a given response
	 *
	 * @param array received from authenticate()
	 * @param array received from authenticate()
	 * @param array
	 * @return string
	 */
	public function generateHeader($credentials, $artifacts, $options) {
		unset($artifacts['mac']);
		$artifacts['hash'] = $this->getVal($options, 'hash');
		$artifacts['ext'] = $this->getVal($options, 'ext');

		if (!$artifacts['hash'] && (isset($options['payload']) || $options['payload'] === '')) {
			$digest = $this->calculatePayloadHash(
				$options['payload'],
				$credentials,
				$options['contentType']
			);
			$artifacts['hash'] = base64_encode($this->bytesToString($digest));
		}

		$mac = $this->calculateMac('response', $credentials, $artifacts);
		$mac = base64_encode($this->bytesToString($mac));

		$header = 'Hawk mac="' . $mac . '"';
		$header .= $artifacts['hash'] ? ', hash="' . $artifacts['hash'] . '"' : '';

		if ($artifacts['ext'] && trim($artifacts['ext']) !== '') {
			$header .= ', ext="' . $this->escapeHeaderAttribute($artifacts['ext']) . '"';
		}

		return $header;
	}

	public function authenticateBewit($request, $credentialsFunc, $options = array()) {
		$req = $this->arrMerge($this->_request, $request);
		$opt = $this->arrMerge($this->_options, $options);

		$now = time() + $this->getVal($opt, 'localtimeOffsetSec', 0);

		$hostport = null;
		if (!isset($options['host']) || !isset($options['port'])) {
			$hostport = $this->parseHost($req, $opt['hostHeaderName']);
			if (!$hostport) {
				return $this->setError(500, 'Invalid host header');
			}
		}

		$req['host'] = $this->getVal($options, 'host', $hostport['name']);
		$req['port'] = $this->getVal($options, 'port', $hostport['port']);

		if (!preg_match('/^(\/.*)([\?&])bewit\=([^&$]*)(?:&(.+))?$/iD', $req['url'], $resource)) {
			return $this->setError(401);
		}

		if (!isset($resource[3])) {
			return $this->setError(401, 'Empty bewit');
		}

		$m1 = strtoupper($req['method']);
		if ($m1 !== 'GET' && $m1 !== 'HEAD') {
			return $this->setError(401, 'Invalid method');
		}

		if ($req['authorization']) {
			return $this->setError(400, 'Multiple authentications');
		}

		// TODO: test RFC 4648
		$bewitString = base64_decode($resource[3], true);
		if (!$bewitString) {
			return $this->setError(400, 'Invalid bewit encoding');
		}

		$bewitParts = explode('\\', $bewitString);
		if (count($bewitParts) !== 4) {
			return $this->setError(400, 'Invalid bewit structure');
		}

		$bewit = array(
			'id' => $bewitParts[0],
			'exp' => intval($bewitParts[1], 10),
			'mac' => base64_decode($bewitParts[2]),
			'ext' => $bewitParts[3]
		);

		if (!$bewit['id'] || !$bewit['exp'] || !$bewit['mac'] ){
			return $this->setError(400, 'Missing bewit attributes');
		}

		$url = $resource[1];
		if (isset($resource[4])) {
			$url .= $resource[2] . $resource[4];
		}

		if ($bewit['exp'] <= $now) {
			return $this->setError(401, 'Access expired');
		}

		$creds = $credentialsFunc($bewit['id']);
		if (!$creds) {
			return $this->setError(500, 'Invalid credentials', $creds, $bewit);
		}

		if (!$this->getVal($creds, 'key') || !$this->getVal($creds, 'algorithm')) {
			return $this->setError(500, 'Invalid credentials', $creds, $bewit);
		}

		if (!in_array($creds['algorithm'], $this->algo)) {
			return $this->setError(500, 'Unknown algorithm');
		}

		$mac1 = $this->calculateMac('bewit', $creds, array(
			'ts' => $bewit['exp'],
			'nonce' => '',
			'method' => 'GET',
			'resource' => $url,
			'host' => $request['host'],
			'port' => $request['port'],
			'ext' => $bewit['ext']
		));

		$mac2 = $this->bytesFromString($bewit['mac']);

		if (!$this->bytesEqual($mac1, $mac2)) {
			return $this->setError(401, 'Bad mac', $creds, $bewit);
		}

		return array($creds, $bewit);
	}

	/**
	 * Helper function to generate random string.
	 *
	 * @param  int
	 * @return string
	 */
	public static function randombytes($length) {
		$raw = '';
		if (is_readable('/dev/urandom')) {
			$fp = true;
			if ($fp === true) {
				$fp = @fopen('/dev/urandom', 'rb');
			}
			if ($fp !== true && $fp !== false) {
				$raw = fread($fp, $length);
			}
		} else if (function_exists('mcrypt_create_iv')) {
			$raw = mcrypt_create_iv($length, MCRYPT_DEV_URANDOM);
		} else if (function_exists('openssl_random_pseudo_bytes')) {
			$raw = openssl_random_pseudo_bytes($length);
		}
		if (!$raw || strlen($raw) !== $length) {
			throw new Exception('Unable to generate randombytes');
		}
		return $raw;
	}

	/**
	 * @param string
	 * @param mixed
	 * @param string
	 * @param array
	 * @param callbacks
	 * @param array
	 * @return mixed
	 */
	public function authenticateMessage(
		$host,
		$port,
		$message,
		$authorization,
		$credentialsFunc,
		$options = array()
	) {
		$opt = $this->arrMerge($this->_options, $options);

		$now = time() + $this->getVal($opt, 'localtimeOffsetSec', 0);

		if (!isset($authorization['id'])
			|| !isset($authorization['ts'])
			|| !isset($authorization['nonce'])
			|| !isset($authorization['hash'])
			|| !isset($authorization['mac'])
		) {
			return $this->setError(400, 'Invalid authorization');
		}

		$creds = $credentialsFunc($authorization['id']);
		if (!$creds) {
			return $this->setError(401, 'Unknown credentials');
		}

		if (!$this->getVal($creds, 'key') || !$this->getVal($creds, 'algorithm')) {
			return $this->setError(500, 'Invalid credentials', $creds);
		}

		if (!in_array($creds['algorithm'], $this->algo)) {
			return $this->setError(500, 'Unknown algorithm', $creds);
		}

		$artifacts = array(
			'ts' => $authorization['ts'],
			'nonce' => $authorization['nonce'],
			'host' => $host,
			'port' => $port,
			'hash' => $authorization['hash']
		);

		$mac1 = $this->calculateMac('message', $creds, $artifacts);

		$mac2 = base64_decode($authorization['mac'], true);
		if (!$mac2) {
			return $this->setError(401, 'Bad mac');
		}
		$mac2 = $this->bytesFromString($mac2);

		if (!$this->bytesEqual($mac1, $mac2)) {
			return $this->setError(401, 'Bad mac');
		}

		$msgHash = base64_decode($artifacts['hash'], true);
		if (!$msgHash) {
			return $this->setError(401, 'Bad message hash', $creds, $artifacts);
		}

		$digest  = $this->calculatePayloadHash($message, $creds);
		if (!$this->bytesEqual($digest, $this->bytesFromString($msgHash))) {
			return $this->setError(401, 'Bad message hash', $creds);
		}

		if (isset($options['nonceFunc'])) {
			if (!$options['nonceFunc']($artifacts['nonce'], $artifacts['ts'])) {
				return $this->setError(401, 'Invalid nonce', $creds);
			}
		}

		if (abs(intval($authorization['ts']) - $now) > $opt['timestampSkewSec']) {
			$tsm = $this->timestampMessage($creds, $opt['localtimeOffsetSec']);
			return $this->setError(401, 'Stale timestamp', $creds);
		}

		// Successful authentication
		return $creds;
	}

	/**
	 * Generate bewit value from a given request
	 *
	 *
	 * $request is an array with the following
	 *   required keys:
	 *     method, nonce, resource, host, port, ttlSec
	 *   optional keys:
	 *     ext                // Application specific data sent via the ext attribute
	 *     localtimeOffsetSec //Time offset to sync with server time
	 *
	 * @param array
	 * @param array
	 * @return string
	 */
	public function generateClientBewit($request, $credentials) {
		$keys = array(
			'method' => null,
			'nonce' => null,
			'resource' => null,
			'host' => null,
			'port' => null,
			'ttlSec' => null,
			'ext' => null,
			'localtimeOffsetSec' => null
		);

		$req = $this->arrMerge($keys, $request);

		$exp = time() + intval($req['ttlSec'], 10);
		$req['ts'] = $exp;

		$mac = $this->calculateMac('bewit', $credentials, $req);
		$mac = $this->bytesToString($mac);

		$bewit = $credentials['id'] . '\\' . $exp . '\\';
		$bewit .= base64_encode($mac) . '\\' . $req['ext'];

		// TODO: test RFC 4648
		return base64_encode($bewit);
	}

	/**
	 * Generate an authorization string for a message
	 *
	 * $options is an array with the following optional keys:
	 *     timestamp, nonce, localtimeOffsetSec
	 */
	public function generateClientMessage($host, $port, $message, $credentials, $options = array()) {
		$now = $this->getVal($options, 'timestamp', time()) + $this->getVal($options, 'localtimeOffsetSec', 0);

		if (!isset($credentials['id'])
			|| !isset($credentials['key'])
			|| !isset($credentials['algorithm'])
		) {
			return null;
		}

		if (!in_array($credentials['algorithm'], $this->algo)) {
			return null;
		}

		$nonce = $this->getVal($options, 'nonce');
		if (!$nonce) {
			$nonce = base64_encode($this->randombytes(6));
			$nonce = str_replace('+', '-', $nonce);
			$nonce = str_replace('/', '_', $nonce);
			$nonce = str_replace('=', '', $nonce);
		}

		$digest = $this->calculatePayloadHash($message, $credentials);

		$artifacts = array(
			'ts' => $now,
			'nonce' => $nonce,
			'host' => $host,
			'port' => $port,
			'hash' => base64_encode($this->bytesToString($digest))
		);

		$mac = $this->calculateMac('message', $credentials, $artifacts);

		return array(
			'id' => $credentials['id'],
			'ts' => $artifacts['ts'],
			'nonce' => $artifacts['nonce'],
			'hash' => $artifacts['hash'],
			'mac' => base64_encode($this->bytesToString($mac))
		);
	}

	/**
	 * Generate an authorization header for a given request.
	 *
	 * $request is an array with the following keys:
	 *   method, host, port, url
	 *
	 * $options is an array with the following optional keys:
	 *   ext, timestamp, nonce, localtimeOffsetSec, payload, contentType, hash, app, dlg
	 * 
	 * 
	 * @param string
	 * @param string
	 * @param array
	 * @param array
	 * @return mixed
	 */
	public function generateClientHeader($request, $credentials, $options) {
		$now = $this->getVal($options, 'timestamp', time() + $this->getVal($options, 'localtimeOffsetSec', 0));

		if (!isset($credentials['id'])
			|| !isset($credentials['key'])
			|| !isset($credentials['algorithm'])
		) {
			return $this->setError(500, 'Invalid credentials');
		}

		if (!in_array($credentials['algorithm'], $this->algo)) {
			return $this->setError(500, 'Unknown algorithm');
		}

		$artifacts = array(
			'ts' => $now,
			'nonce' => $this->getVal($options, 'nonce', base64_encode($this->randombytes(6))),
			'method' => $request['method'],
			'host' => $request['host'],
			'port' => $request['port'],
			'resource' => $request['url'],
			'hash' => $this->getVal($options, 'hash'),
			'ext' => $this->getVal($options, 'ext'),
			'app' => $this->getVal($options, 'app'),
			'dlg' => $this->getVal($options, 'dlg')
		);

		if (!$artifacts['hash'] && isset($options['payload'])) {
			$digest = $this->calculatePayloadHash(
				$options['payload'],
				$credentials,
				$this->getVal($options, 'contentType')
			);

			$artifacts['hash'] = base64_encode($this->bytesToString($digest));
		}

		$mac = $this->calculateMac('header', $credentials, $artifacts);

		//$hasExt = $artifacts['ext'] && $artifacts[
		$header = 'Hawk id="' . $credentials['id'];
		$header .= '", ts="' .$artifacts['ts'];
		$header .= '", nonce="' . $artifacts['nonce'];

		if ($this->getVal($artifacts, 'hash')) {
			$header .= '", hash="' . $artifacts['hash'];
		}

		if ($this->getVal($artifacts, 'ext')) {
			$header .= '", ext="' . $this->escapeHeaderAttribute($artifacts['ext']);
		}

		$header .= '", mac="' .base64_encode($this->bytesToString($mac)) . '"';

		if (isset($artifacts['app'])) {
			$header .= ', app="' . $artifacts['app'];
			if (isset($artifacts['dlg'])) {
				$header .= '", dlg="' . $artifacts['dlg'] . '"';
			}
		}

		return array($header, $artifacts);
	}

}
