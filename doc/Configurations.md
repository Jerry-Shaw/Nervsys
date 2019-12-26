# Configurations

As you may notice that, there are two .example files in the root of NS. One with the name of "app.ini.example" is the main configuration file, which controls the core behavior.  

Main configuration is NOT required for system as default values are already defined in core. But, in some cases, some of the configurations need to be changed for advanced users. See below for details.  

## File path & Notice
1. Rename "app.ini.example" to "app.ini"  
2. Move "app.ini" into app path (default: ROOT/app, or, modify definition of "APP_PATH" in ns.php)  
3. Only keep the settings that need to change. Remove others, or, leave others as-is. Remember NOT delete section keys if there are settings below.  
    
## Sections & default values

#### [SYS]
```ini
timezone = PRC
auto_call = off
```

#### [LOG]
```ini
emergency = on
alert     = on
critical  = on
error     = on
warning   = on
notice    = on
info      = on
debug     = on
display = on
; save_path =
```

#### [CLI]
```ini

```

#### [CORS]
```ini
; * = X-Requested-With, Content-Type, Content-Length
```

#### [INIT]
```ini

```

#### [CALL]
```ini

```
