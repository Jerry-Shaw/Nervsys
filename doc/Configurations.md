# Configurations

As you may notice that, there are two .example files in the root of NS. One with the name of "app.ini.example" is the main configuration file, which controls the core behavior.  

Main configuration is NOT required for system as default values are already defined in core. But, in some cases, some of the configurations need to be changed for advanced users. See below for details.  

## File path & notice
1. Rename "app.ini.example" to "app.ini"  
2. Move "app.ini" into app path (default: ROOT/app, or, modify definition of "APP_PATH" in ns.php)  
3. Only keep the settings that need to change. Remove others, or, leave others as-is. Remember NOT delete section keys if there are settings below.  
    
## Sections & default values

#### [SYS]

This section only contains settings for core system, now, only has two fields.  
* "timezone": PHP system default timezone. "UTC" will be used if not set.  
* "auto_call": Open TrustZone auto call mode switch. For automatically calling matched methods in target classes if method is NOT specific.  

```ini
timezone = PRC
auto_call = off
```

#### [LOG]

This section controls the behavior of kernel logger system.  
* Log levels  
    * Fields of "error", "warning", "notice" are used by PHP core to log code errors.  
    * Fields of "emergency", "alert", "critical", "info", "debug" are reserved but not in use now.  
* "display": Log screen output switch. Very useful when in development.  
* "save_path": An absolute path to save logs if is specific. Leave it NOT defined (do NOT set to empty) to keep all logs in project ROOT/logs.  

```ini
emergency = on
alert     = on
critical  = on
error     = on
warning   = on
notice    = on
info      = on
debug     = on

display   = on

; save_path examples

; in linux
save_path = /root/myLogs

; in windows
save_path = D:\myLogs

; default setting
; save_path = 
```

#### [CLI]

"CLI" field defines the external programs which can be called directly by NervSys. In another words, this section controls the authority of calling external programs. Only defined values can be called while passing the key as a param of "c".  

```ini
; example
PHP = /usr/local/bin/php
```

#### [CORS]

"CORS" mean Cross Origin Resource Sharing permission.  
Setting format in this section is as follows:

Origin HOST (should include port if NOT is 80 or 443) = Allowed headers

```ini
; example
http://your_domain = X-Requested-With, Content-Type, Content-Length
http://your_domain_2:8080 = X-Requested-With, Content-Type, Content-Length, My_Auth_Key
```

#### [INIT]

Defines pre-calling functions before calling any API-related code blocks, even routers (internal default or external registered), data reader, etc...  

```ini
; example
start = app/start-my_sys_boot
start_2 = app/start-my_sys_self_test
```

#### [CALL]

Defines pre-calling functions before entering namespace-level-based method.  

A detail example as follows:  

```php
namespace app\level_1;

class my_class {
    public $tz = '*';

    public function test()
    {
        //my codes
    }
}
```

```ini
/app = app/pre_call-one
/app/level_1 = app/pre_call-two
/app/level_1/my_class = app/pre_call-three
```
