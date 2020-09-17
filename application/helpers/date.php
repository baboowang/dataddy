<?php
function short_date($date)
{
	if (! is_numeric($date)) {
		$time = strtotime($date);
	} else {
		$time = $date;
	}
	if (date('Y', $time) == date('Y')) {
		if (date('m-d', $time) == date('m-d')) {
			return date('H:i', $time);
		}
		return date('m-d', $time);
	}
	return date('Y-m');
}

/**
  * 获取指定日期之间的天数
  * @param String $fdate
  * @param String $edate
  * return Integer
  * Mingshi
  * 2014-03-05
 */
function get_date_diff($fdate, $edate) {
    $i = 0;

    for ($t = $fdate; $t <= $edate; $t = date('Y-m-d', (strtotime($t) + 86400))) {
        $i++;
    }

    return $i;
}


function fdate($format, $date)
{
	if ( ! is_int($date)) {
		$date = strtotime($date);
	}
	return date($format, $date);
}

function last_day_of_month($date, $format = 'Y-m-d')
{
	$lastDayOfMonth = date(
		$format,
		strtotime('next month', strtotime(fdate('m/01/y', $date))) - 1
	);
	return $lastDayOfMonth;
}

//根据指定数字计算指定日期前后多少个星期的开始时间
function relative_weeks($num, $time = 0)
{
    if (empty($time)) {
        $time = time();
    }

    $time += $num * 86400 * 7;
    $d = date('N', $time);
    $toMonday = 0;
    if ($d != 1) {
        $toMonday = $d - 1;
    }

    $time -= $toMonday * 86400;
    return strtotime(date('Y-m-d', $time));
}

function relative_months($num, $time = 0)
{
    if (empty($time)) {
        $time = time();
    }

    $time = strtotime(date('Y-m-01', $time));

    $str = $num . ' month';
    $time = strtotime($str, $time);
    return $time;
}

function is_null_date($date)
{
    return empty($date) || $date == '0000-00-00' || $date == '0000-00-00 00:00:00';
}
