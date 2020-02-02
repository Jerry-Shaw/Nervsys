# CLI Examples

In CLI, we using c, r, d, p to pass arguments. See [Reserved Words](../ReservedWords.md)  
If "c" exists in command, it will be taken. Otherwise, the first argument except command options will be taken as "c".  
If "-r" is missing in command, nothing will be output. So, always keep a "-r" after entry script if the output is really needed for further using. The format of result can be transformed into JSON by using -rjson, or, XML by using -rxml, or, plait text only using "-r" or "-rio".

### Continue CGI calling mode in command line console

First, let's take a look at [CGI Usage](CGI.md).  
Take "http://your_domain/entry.php/test_1-go?name=somename&id=1" for example.  
In the console, we can do the same by calling php like follows:  
/php_path/php entry.php -r -c"test_1-go" -d"name=somename&id=1"  
or  
/php_path/php entry.php -r -d"name=somename&id=1" test_1-go

### Calling external program

First, look back to "app.ini", in [CLI] section, see [Configurations](../Configurations.md)  
We MUST provide the binary path of the external program under the [CLI] section, defining the key as its command.  
Like follows:  

```ini
[CLI]
; Linux
php = /php_path/php
py = /python_path/python

; Windows
php = "D:\program files\php\php.exe"
txt = notepad.exe
```

Now we can use the following command to get php version:  
**/php_path/php entry.php php -v**  
or open notepad under windows:  
**php entry.php txt**

Also, we can do it both if both programs support "-v". It's just an example:  
**php entry.php txt-php -v**

If the external program accepts pipe data, for example, python. We can do it as follows:  
**php entry.php -cpy -p"my pipe data" py_script_file_path**