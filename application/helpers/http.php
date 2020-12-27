<?php
/**
 * HTTP POST a URL with query parameters, using x-www-form-urlencoded
 * Content-Type. When redirecting, post data will also be re-posted (watch
 * potential security implication of this).
 *
 * @param $url      string  URL with or without some query parameters
 * @param $params   array   post data in name/value pairs
 * @param $options  array   associative array of extra options to pass in. can include:
 *         max_redir          int    how many levels of redirection to attempt (def 1)
 *         conn_timeout       int    number of seconds waiting for a connection (def 3)
 *         timeout            int    total number of seconds to wait, should be
 *                                   strictly bigger than $conn_timeout (def 12)
 *         block_internal     bool   security check - prevent retrieving internal facebook urls (def true)
 *         internal_whitelist array  whitelist these internal domains (def empty)
 *         close_conns        bool   whether to shut down all our db connections
 *                                   before making the request (def true)
 */
function http_post($url, $params, $options=array())
{
	$default_options = array(
		'max_redir'          => 1,
		'conn_retry'         => 2,
		'conn_timeout'       => 3,
		'timeout'            => 12,
		'use_post'           => true,
		'https'              => false,
		'http_header'        => array('Expect:')
	);
	if(!empty($options["proxy"]))
	{
		$use_proxy = $options["proxy"];
		unset($options["proxy"]);
	}
	else
	{
		$use_proxy = "";
	}
	// $options + $default_options results in an assoc array with overlaps
	// deferring to the value in $options
	extract($options + $default_options);

	$curl = curl_init();
	if ($max_redir < 1)
	{
		$max_redir = 1;
	}

	if (isset($options['user_agent'])) {
	    $user_agent = $options['user_agent'];
	} else {
	    $user_agent = 'Mozilla/5.0 (Windows; U; Windows NT 5.1; zh-CN; rv:1.9.2.3) Gecko/20100401 Firefox/3.6.3';
	}

	$curl_opts = array(CURLOPT_URL => $url,
		CURLOPT_CONNECTTIMEOUT => $conn_timeout,
		CURLOPT_TIMEOUT => $timeout,
		CURLOPT_USERAGENT => $user_agent,
		CURLOPT_RETURNTRANSFER => 1,
		CURLOPT_ENCODING => 'gzip',
		CURLOPT_POST => $use_post,
		CURLOPT_HTTPHEADER => $http_header
    );

    if ($https) {
        $curl_opts = $curl_opts + array(
            CURLOPT_SSL_VERIFYPEER => FALSE,
            CURLOPT_SSL_VERIFYHOST => FALSE,
        );
    }

	if($use_proxy)
	{
		//error_log("2222:".$use_proxy);
		$curl_opts[CURLOPT_PROXY] = $use_proxy;
	}

	curl_setopt_array($curl, $curl_opts);

	$last_url   = $url;
	$redirects  = 0;
	$retries    = 0;

	if(is_array($params))
	{
		$post_str = http_build_query($params);
	}
	elseif(is_string($params))
	{
		$post_str = $params;
	}
	else
	{
		$post_str = "";
	}

	if ($max_redir == 1)
	{
		if ($use_post) curl_setopt($curl, CURLOPT_POSTFIELDS, $post_str);
		$response = curl_exec($curl);
	}
	else
	{
		$start_time = microtime(true);
		for ($attempt = 0; $attempt < $max_redir; $attempt++)
		{
			curl_setopt($curl, CURLOPT_HEADER, 1);
			if ($use_post) curl_setopt($curl, CURLOPT_POSTFIELDS, $post_str);
			$orig_response = curl_exec($curl);

			// Remove any HTTP 100 headers
			$response = preg_replace('/HTTP\/1\.[01] 100.*\r\n(.+\r\n)*\r\n/', '', $orig_response);
			if (preg_match('/^HTTP\/1\.. 30[1237].*\nLocation: ([^\r\n]+)\r\n/si',$response, $matches))
			{
				$new_url = $matches[1];
				// if $new_url is relative path, prefix with domain name
				if (!preg_match('/^http(|s):\/\//', $new_url) && preg_match('/^(http(?:|s):\/\/.*?)\/(.+\/)?|$/', $url, $matches)) {
				    if (strpos($new_url, '/') === 0) {
				        $new_url = $matches[1].$new_url;
				    } else {
					   $new_url = $matches[0].$new_url;
				    }
				}
				//error_log("\$attempt = $attempt: $new_url");
				$last_url = $new_url;
				curl_setopt($curl, CURLOPT_URL, $new_url);
				// remember cookies
				preg_match_all('/Set\-Cookie: ([^=]+=[^;]*).+\s/', $response, $matches);
				$cookies =  implode('; ', $matches[1]);
				if (!empty($cookies)) curl_setopt($curl, CURLOPT_COOKIE, $cookies);
				// reduce the timeout, but keep it at least 1 or we wind up with an infinite timeout
				curl_setopt($curl, CURLOPT_TIMEOUT, max($start_time + $timeout - microtime(true), 1));
				++$redirects;
			}
			else if ($conn_retry && strlen($orig_response) == 0)
			{
				// probably a connection failure...if we have time, try again...
				$time_left = $start_time + $timeout - microtime(true);
				if ($time_left < 1)
				{
					break;
				}
				//error_log("\$attempt = $attempt: $last_url");
				// ok, we've got some time, let's retry
				curl_setopt($curl, CURLOPT_URL, $last_url);
				curl_setopt($curl, CURLOPT_TIMEOUT, $time_left);
				++$retries;
			}
			else
			{
				//error_log("\$attempt = $attempt $response");
				break; // we have a good response here
			}
		}
		// NOTE: quicker to use strpos for headers, do not compile a RE
		if (false !== ($pos = strpos($response, "\r\n\r\n")))
		{
			$response = substr($response, $pos+4);
		}
	}

	$curl_info = curl_getinfo($curl);

	if (in_array($curl_info['http_code'], array(301, 302, 303, 307)))
	{
		throw new HTTPTooManyRedirsException($url);
	}
	if ($curl_info['http_code'] >= 400)
	{
		throw new HTTPErrorException($url, $curl_info['http_code'], $response);
	}
	if (strlen($response) == 0)
	{
		if ($curl_info['http_code'])
		{
			throw new HTTPNoDataException($url, $curl_info['http_code']);
		}
		else
		{
			throw new HTTPNoResponseException($url);
		}
	}

	curl_close($curl);

	// take into account http hosts that don't use utf-8
	if (!empty($curl_info['content_type']) && preg_match('#charset=([^;]+)#i', $curl_info['content_type'], $matches))
	{
		$encoding = strtoupper($matches[1]);
		if ($encoding != 'UTF8' && $encoding != 'UTF-8')
		{
			$response = iconv($encoding, 'UTF-8', $response);
		}
	}

	return $response;
}

class HTTPException extends Exception {
}

/**
 * An exception that gets thrown when there is an HTTP error (404, 500, etc.)
 */
class HTTPErrorException extends HTTPException {
  public function __construct($url, $error, $content) {
    $msg = 'Received HTTP error code ' . $error . ' while loading ' . $url;
    parent::__construct($msg, $error);
    $this->_content = $content;
  }
}

class HTTPInvalidUrlException extends HTTPException {
  public function __construct($url) {
    $msg = 'The URL ' . $url .  ' is not valid.';
    parent::__construct($msg);
  }
}

class HTTPNoResponseException extends HTTPException {
  public function __construct($url) {
    $msg = 'The URL ' . $url . '  did not respond.';
    parent::__construct($msg);
  }
}

class HTTPNoDataException extends HTTPException {
  public function __construct($url, $code) {
    $msg = 'The URL ' . $url . ' returned HTTP code ' . $code . ' and no data.';
    parent::__construct($msg, $code);
  }
}

class HTTPTooManyRedirsException extends HTTPException {
  public function __construct($url) {
    $msg = 'The URL ' . $url . ' caused too many redirections.';
    parent::__construct($msg);
  }
}

/**
 * Return the raw POST vars
 * @return   dict   {<post-param-1>: <val>, <post-param-2>: <val>, ...}
 *
 * PHP does some weird things with POST var names.  Two examples of this are:
 * 1.  If you have vars named like this x[0], x[1], etc., then PHP will
 * put those into an array for you.
 * 2.  If you any dots (.) in your post var names, then PHP will replace
 * those with underscores.
 *
 * This function returns the POST vars without any of those transformations
 * applied.  It may be useful to do the same thing for GET parameters.
 *
 * Note that the vars returned by this function will never be slash-escaped,
 * regardless of whether you have magic quotes on or off.  yay.
 *
 * IMPORTANT NOTE: this function currently fails to handle 2 things being POSTed
 * with the same value.
 *
 */
function php_input_raw_post_vars() {
  global $PHP_INPUT_RAW_POST_VARS;
  if (isset($PHP_INPUT_RAW_POST_VARS)) {
    return $PHP_INPUT_RAW_POST_VARS;
  }

  $post_string = file_get_contents('php://input');
  $assignments = empty($post_string) ? array() : explode('&', $post_string);
  $post_vars = array();
  foreach ($assignments as $asst) {
    if (strstr($asst, '=')) {
      list($param_e, $val_e) = explode('=', $asst, 2);
      $param = urldecode($param_e);
      $val = urldecode($val_e);
    } else {
      $param = urldecode($asst);
      $val = '';
    }
    $post_vars []= array($param, $val);
  }

  return ($PHP_INPUT_RAW_POST_VARS = $post_vars);

}

/**
 * Tells if this request includes a POSTed multipart form
 * @return    bool    true if the request includes a multipart form
 *
 */
function is_multipart_form() {
  if (!isset($_SERVER['CONTENT_TYPE'])) {
    return false;
  }
  return (strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') === 0);
}

function redirect_uri($path, $host = '')
{
	if ($host == '') {
        $host = $_SERVER['HTTP_HOST'];
    }

	if (! preg_match('@^https?://@i', $path)) {
		if ($path)
		{
			$pathchar = ($path[ 0 ] != '/') ? '/' : '';
			$path = $pathchar.$path;
		}
		$path = get_current_protocol()."://".$host.$path;
	}

	return $path;
}

function redirect($path, $permanent_redirect = FALSE, $host = '')
{
    $path = redirect_uri($path, $host);
    if ($permanent_redirect)
    {
        header('HTTP/1.x 301 Moved Permanently');
    }

    header("Location: $path");
    exit();
}

function add_querystring_var($url, $key, $value)
{
	$url = remove_querystring_var($url, $key);
	if (strpos($url, '?') === false)
	{
		return ($url . '?' . $key . '=' . $value);
	}
	else
	{
		return ($url . '&' . $key . '=' . $value);
	}
}

function remove_querystring_var($url, $key)
{
 	$url = preg_replace('/(.*)(\?|&)' . $key . '=[^&]*?(&)(.*)/i', '$1$2$4', $url . '&');
 	$url = substr($url, 0, -1);
 	return $url;
}

function modify_querystring_var($url, $key, $oldValue, $newValue)
{
	$url = preg_replace('/(.*)(\?|&)' . $key . '=[^&]*?(&)(.*)/i', '$1$2'."$key=".urlencode($newValue).'$3$4', $url . '&');
	return substr($url, 0, -1);
}

function append_querystring_params($url, $params)
{
	$params = http_build_query($params);
	return $url . (strpos($url, '?') === FALSE ? '?' : '&') . $params;
}

function get_self_full_url()
{
	return get_current_protocol() . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ;
}

function get_current_protocol()
{
    return ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ||
        $_SERVER['SERVER_PORT'] == 443 ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? "https" : "http";
}

function url_get_request_uri($url=null)
{
	if(empty($url))
	{
		$str = $_SERVER['REQUEST_URI'];
		if(($offset=strpos($str, '?'))!==false)
		{
			$str = substr($str, 0, $offset);
		}
		return $str;
	}
	else
	{
		return parse_url($url, PHP_URL_PATH);
	}
}

function http_cache_header($timeout)
{
	if ($timeout == 0) {
		@header('Expires: Thu, 01 Jan 1970 00:00:01 GMT');
		@header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		@header('Cache-Control: no-cache, must-revalidate, max-age=0');
		@header('Pragma: no-cache');
	} else {
		@header("Cache-Control: max-age=$timeout");
		@header("Pragma: ");
		@header ('Expires: ' . gmdate('D, d M Y H:i:s', time()+$timeout). " GMT");
	}
}

function is_bot_request()
{
	$agent = getenv("HTTP_USER_AGENT");
	if((stripos($agent,'bot')!==false) || (stripos($agent,'spider')!==false) ||  (stripos($agent,'slurp')!==false))
	{
		return true;
	}
	return false;
}

function get_client_ip()
{
	if (getenv('HTTP_CLIENT_IP')) {
		$ip = getenv('HTTP_CLIENT_IP');
	}
	elseif (getenv('HTTP_X_FORWARDED_FOR')) {
		$ip = getenv('HTTP_X_FORWARDED_FOR');
	}
	elseif (getenv('HTTP_X_FORWARDED')) {
		$ip = getenv('HTTP_X_FORWARDED');
	}
	elseif (getenv('HTTP_FORWARDED_FOR')) {
		$ip = getenv('HTTP_FORWARDED_FOR');
	}
	elseif (getenv('HTTP_FORWARDED')) {
		$ip = getenv('HTTP_FORWARDED');
	}
	else {
		$ip = $_SERVER['REMOTE_ADDR'];
	}
	if ($pos=strpos($ip, ',')){
		$ip = substr($ip,0,$pos);
	}
	return $ip;
}

/**
 * 得到URL请求参数串
 * @param string $key 要添加或删除的请求参数
 * @param string $value 要添加的请求参数值
 * @param string $url  指定要重新得到请求url参数的url, 默认获取当前的url
 *
 * @return string eg.  XX=XX&CC=CC&VV=VV 没有任何参数将返回空
 */
function get_request_url_param_str($key='', $value='', $url='')
{
    if ('' == $url) {
        $url = $_SERVER['REQUEST_URI'];
    }

    $request_param_array = array();
    $request_param_str = parse_url($url);
    if (isset($request_param_str['query'])) {
        parse_str($request_param_str['query'], $request_param_array);
    }

    unset($request_param_array[$key]);
    if ($value) {
        $request_param_array[$key] = $value;
    }

    if (is_array($request_param_array)) {
        foreach ($request_param_array as $key => $value) {
            $request_param_array[$key] = "{$key}={$value}";
        }
    }

    return implode('&', $request_param_array);
}
