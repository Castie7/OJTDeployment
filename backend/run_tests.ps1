$env:XDEBUG_MODE='off'
vendor\bin\phpunit --colors=never --group unit tests\unit\LoginConfigTest.php > output.log 2>&1
Get-Content output.log
