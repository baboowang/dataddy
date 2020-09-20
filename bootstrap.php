<?php
define('VERSION', '1.0.2');

function ddy_version()
{
    return VERSION;
}

function ddy_version_cmp($cmp_version)
{
    $segments = array_map('intval', explode('.', VERSION));
    $cmp_segments = array_map('intval', explode('.', $cmp_version));

    for ($i = 0, $n = count($segments); $i < $n; $i++) {
        if ($segments[$i] > ($cmp_segments[$i] ?? 0)) {
            return 1;
        } elseif ($segments[$i] < ($cmp_segments[$i] ?? 0)) {
            return -1;
        }
    }

    return 0;
}
