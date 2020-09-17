<?php
namespace MY;

class Crontab
{
    public static function validate($val)
    {
        $val = preg_replace('@^#|\s*(mail|alarm|index)$@u', '', $val);

        if ($val === '') return TRUE;

        try {
            $cron = \Cron\CronExpression::factory($val);

            return TRUE;

        } catch (Exception $e) {

            return FALSE;
        }
    }

    public static function build()
    {
        log_message('Build crontab start', LOG_INFO);

        $output = \GG\Config::get('cron.output', '/dev/null');

        $rows = M('menuItem')->select([
            'crontab' => [
                [ '!=' => '' ],
                [ 'not like' => '#%' ],
                \GG\Db\Sql::LOGIC => 'AND',
            ],
        ], [
            'select' => 'id,crontab'
        ]);

        $crontab_lines = [];
        $cli_command = APPLICATION_PATH . '/script/dataddy_cli';

        if ($php = \GG\Config::get('cron.php_path')) {
            $cli_command = "$php $cli_command";
        }

        foreach ($rows as $row) {
            $action = 'index';
            if (preg_match('@(\w+)$@', $row['crontab'], $ma)) {
                $action = $ma[1];
            }
            $crontab = preg_replace('@(.+?)(\w+)?$@', '$1' . " $cli_command $action {$row['id']}", $row['crontab']);
            $crontab_lines[] = $crontab . " >> $output 2>&1";
        }

        $crontab_content = implode("\n", $crontab_lines);

        $cron = self::_exec('crontab -l', $ret);

        if (!preg_match('@^(# DATADDY_CRON_START[^\n]*)(?:.*)(# DATADDY_CRON_END)@usm', $cron)) {
            $cron = <<<EOT
$cron
# NOTICE: LINES BELOW HERE ARE MANAGED BY DATADDY, DO NOT EDIT!
# DATADDY_CRON_START
# DATADDY_CRON_END
EOT;
        }

        $replace = "\$1\n" . $crontab_content . "\n\$2";

        $cron = preg_replace('@^(# DATADDY_CRON_START[^\n]*)(?:.*)(# DATADDY_CRON_END)@usm', $replace, $cron);

        log_message("crontab content:$crontab_content", LOG_INFO);

        $temp_file = tempnam(sys_get_temp_dir(), 'dataddy_cron_');
        file_put_contents($temp_file, $cron);

        $out = self::_exec('crontab ' . $temp_file . ' 2>&1', $ret);

        log_message('Build crontab finish, result:' . ($ret == 0 ? 'OK' : 'FAIL'), LOG_INFO);

        unlink($temp_file);

        return $ret == 0;
    }

    protected static function _exec($command, &$return_var)
    {
        ob_start();
        system($command, $return_var);
        $output = ob_get_clean();

        return $output;
    }
}
