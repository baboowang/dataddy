[yaf]
application.directory = APPLICATION_PATH "/application/"
application.modules="Index,Admin"
db_physical.default.write.host = localhost
db_physical.default.write.port = 3306
db_physical.default.db_user = root
db_physical.default.db_pwd  = ''
db_singles.dataddy.map = default
db_singles.dataddy.db_name = dataddy
log.level = error
log.dir = APPLICATION_PATH "/logs"
secret.key = '1ff769490c8aa6a361c213f8f8d9c6e7'
plugins = Test\TestFilter

editor.vim_mode = true
report.number_format = true

cookie.expire = 3600
cookie.salt = VNRlWMYcxMltbo4dr3gaEaMcG6hoplhG
cookie.hash_method = md5
cookie.session = session

mail.host = smtp.xxx.com
mail.username = xxx@xxx.com
mail.password = ******
mail.name = DATADDY

cron.php_path = '/usr/bin/php'
cron.output = '/tmp/dataddy_cron.out'

;; EXTRA CONFIG

[product:yaf]

[test:yaf]

[develop:yaf]
log.level = debug
