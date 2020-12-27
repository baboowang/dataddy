<?php
/**
 * Escapes text to make it safe to use with Javascript
 *
 * It is usable as, e.g.:
 *  echo '<script>aiert(\'begin'.escape_js_quotes($mid_part).'end\');</script>';
 * OR
 *  echo '<tag onclick="aiert(\'begin'.escape_js_quotes($mid_part).'end\');">';
 * Notice that this function happily works in both cases; i.e. you don't need:
 *  echo '<tag onclick="aiert(\'begin'.txt2html_old(escape_js_quotes($mid_part)).'end\');">';
 * That would also work but is not necessary.
 *
 * @param  string $str    The data to escape
 * @param  bool   $quotes should wrap in quotes (isn't this kind of silly?)
 * @return string         Escaped data
 */
function escape_js_quotes($str, $quotes = FALSE) {
    if ($str === null) {
        return;
    }
    $str = strtr($str, array('\\'=>'\\\\', "\n"=>'\\n', "\r"=>'\\r', '"'=>'\\x22', '\''=>'\\\'', '<'=>'\\x3c', '>'=>'\\x3e', '&'=>'\\x26'));
    return $quotes ? '"'. $str . '"' : $str;
}

if (! function_exists('str_startwith')) {
    function str_startwith($haystack, $needle)
    {
        return strpos($haystack, $needle) === 0;
    }
}

function is_empty_string($str)
{
	return $str === NULL || $str === '';
}

#按字符宽度截取字符串,一个半角字符为一个宽度,全角字符为两个宽度
function str_truncate($str, $len)
{
     if (empty($str)) return $str;

    $gbkStr = @iconv('UTF-8', 'gbk', $str);

    if ($gbkStr == '') {
        //Convert encoding to gbk failed
        $i = 0;
        $wi = 0;
        $n = strlen($str);
        $newStr = '';
        while ($i < $n) {
            $ord = ord($str[$i]);
            if ($ord > 224) {
                $newStr .= substr($str, $i, 3);
                $i += 3;
                $wi += 2;
            } else if ($ord > 192) {
                $newStr .= substr($str, $i, 2);
                $i += 3;
                $wi += 2;
            } else {
                $newStr .= substr($str, $i, 1);
                $i += 1;
                $wi += 1;
            }
            if ($wi >= $len) {
                break;
            }
        }
        if ($wi < $len || ($wi == $len && $i == $n)) {
            return $str;
        }
        return preg_replace('@([\x{00}-\x{ff}]{3}|.{2})$@u', '...', $newStr);
    }

    if ($len < 3 || strlen($gbkStr) <= $len) {
        return $str;
    }

    $cutStr = mb_strcut($gbkStr, 0, $len - 3, 'gbk');
    $cutStr = iconv('gbk', 'UTF-8', $cutStr);
    return $cutStr . '...';
}

/**
 * Format string camelize
 */
function camelize($str, $upperFirstChar = TRUE)
{
	$segments = explode('_', $str);
	$ret = '';
	for($i = 0, $n = count($segments); $i < $n; $i++) {
		$segment = $segments[$i];
		if (strlen($segment) == 0) {
			continue;
		}
		if ($i == 0 && !$upperFirstChar) {
			$ret .= $segment;
		} else {
			$ret .= strtoupper($segment[0]);
			if (strlen($segment) > 1) {
				$ret .= substr($segment, 1);
			}
		}
	}
	return $ret;
}

// funnyThing => funny_thing
function underscore($str)
{
	return trim(preg_replace_callback(
		'@[A-Z]@',
		create_function('$m', 'return "_".strtolower($m[0]);'),
		$str
	), '_');
}

// Generate a random character string
function rand_str($length = 32, $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890')
{
    // Length of character list
    $chars_length = (strlen($chars) - 1);

    // Start our string
    $string = $chars[ rand(0, $chars_length) ];

    // Generate random string
    for ($i = 1; $i < $length; $i = strlen($string))
    {
        // Grab a random character from our list
        $r = $chars[ rand(0, $chars_length) ];

        // Make sure the same two characters don't appear next to each other
        if ($r != $string[ $i - 1 ]) $string .=  $r;
    }

    // Return the string
    return $string;
}

function get_str_var($template, $star_get = "{", $end_tag = "}")
{
    preg_match_all('/' . $star_get . '([\s\S]*?)' . $end_tag . '/', $template, $match);

    return $match && $match[1] ? $match[1] : [];
}

function str_replace_var($str, $data)
{
    $vars = get_str_var($str);
    foreach($vars as $var) {
        $var_value = array_get_node($var, $data);
        $str = str_replace('{' . $var . '}', $var_value, $str);
    }
    return $str;
}
