[yaf]
application.directory = APPLICATION_PATH'/application/'
application.modules = "Index,Admin"
db_physical.default.write.host =
db_physical.default.write.port =
db_physical.default.db_user =
db_physical.default.db_pwd =
db_singles.dataddy.map = default
db_singles.dataddy.db_name =

log.level =
log.dir = APPLICATION_PATH "/logs"
secret.key = '1ff769490c8aa6a361c213f8f8d9c6e7'
plugins =

sso.page =

editor.vim_mode =
report.number_format =

cookie.expire =
cookie.salt = VNRlWMYcxMltbo4dr3gaEaMcG6hoplhG
cookie.hash_method = md5
cookie.session = session

admin.account =
admin.password =

mail.host =
mail.port =
mail.username =
mail.password =
mail.name = DATADDY

cron.php_path =
cron.output =

;; EXTRA CONFIG

[product:yaf]
log.level = notice
[test:yaf]
log.level = notice
[develop:yaf]
log.level = notice
