# Core structure

```text
/
├─core/                            DIR: core
│     ├─lib/                       DIR: lib
│     │    ├─os/                   DIR: OS handler
│     │    │   ├─darwin.php        Mac OS handler
│     │    │   ├─linux.php         Linux OS handler
│     │    │   └─winnt.php         WinNT OS handler
│     │    ├─stc/                  DIR: static classes
│     │    │    ├─error.php        Error handler
│     │    │    ├─factory.php      Factory handler
│     │    │    └─trustzone.php    Trustzone handler
│     │    ├─std/                  DIR: standard classes
│     │    │    ├─io.php           Input/Output controller
│     │    │    ├─log.php          Core Log controller
│     │    │    ├─os.php           Main OS controller
│     │    │    ├─pool.php         Data pool controller
│     │    │    ├─reflect.php      Reflection controller
│     │    │    └─router.php       Router controller
│     │    ├─cgi.php               CGI operator
│     │    └─cli.php               CLI operator
│     └─ns.php                     Main NS script
├─ext/                             DIR: extensions
│    ├─fonts/                      DIR: fonts
│    ├─imp/                        DIR: interface implements
│    │    └─key.php                key interface for keygen
│    ├─cache.php                   cache in redis
│    ├─conf.php                    conf file reader
│    ├─core.php                    core api extension
│    ├─crypt.php                   data crypt handler
│    ├─crypt_img.php               crypt image scr:base64/validator
│    ├─doc.php                     document scanner
│    ├─errno.php                   error number/message controller
│    ├─factory.php                 factory extension
│    ├─file.php                    file i/o handler
│    ├─http.php                    http requester
│    ├─image.php                   image size/rotation processor
│    ├─keygen.php                  keygen for crypt
│    ├─lang.php                    language reader
│    ├─lock.php                    lock in redis
│    ├─log.php                     log recorder
│    ├─mpc.php                     multi-process controller
│    ├─mysql.php                   mysql operator via pdo
│    ├─pdo.php                     pdo connector
│    ├─queue.php                   queue in redis
│    ├─redis.php                   redis connector
│    ├─session.php                 session in redis
│    ├─socket.php                  socket server/client
│    └─upload.php                  upload file receiver
├─api.php.example                  entry script example
└─app.ini.example                  configuration file example
```

