# CGI Examples

### Project structure example

```text
/
├─NS/                  DIR: NervSys submodule directory
├─app/                 DIR: app directory
│    ├─lib/            DIR: lib directory
│    │    └─model.php  model script
│    ├─app.ini         app configuration file
│    ├─test_1.php      test_1 script
│    ├─test_2.php      test_2 script
│    └─test_3.php      test_3 script
└─entry.php            Entry script
```

### model script code example

```php
<?php

namespace app\lib;

class model
{
    public $pdo_instance;

    public function __construct() 
    {
        $this->pdo_instance = new \PDO('your dsn', 'usr', 'pwd');
    }
    
    public function read(string $name, int $id): array 
    {
        //some codes
    }
}
```

### test script code example
Just do the same to other test scripts

```php
<?php

namespace app;

class test_1
{
    public $tz = '*';
    
    public function go(app\lib\model $model, string $name, int $id = 0): array 
    {
        return $model->read($name, $id);
    }
    
    public function another_go(string $needed_param): array 
    {
        //some code
    }
}
```

### Attention

Always follow PSR4, let namespace to match path structure, filename must be the same of its class name, unless you have registered your own autoload function.  
In CGI mode, NervSys accept GET, POST, both form data and json/xml body are supported. Also, it grabs command from URL when exists.  

### Usage

Let's using GET for example below

### How to call a function
http://your_domain/entry.php/test_1-go?name=somename[&id=1]
http://your_domain/entry.php?c=test_1-go&name=somename[&id=1]

Above two are the same, calling "go" function in "app\test_1", passing name(required) and id(not required)

### How to call multiple specific functions
http://your_domain/entry.php/test_1-go-test_2-go?name=somename[&id=1]
http://your_domain/entry.php?c=test_1-go-test_2-go&name=somename[&id=1]

Above two are the same, calling "go" function both in "app\test_1" and "app\test_2", passing name(required) and id(not required)

### How to call multiple functions in auto call mode
First, "auto_call" in "app.ini" should be set to "on" or "1".

http://your_domain/entry.php/test_1?name=somename&needed_param=someparam
http://your_domain/entry.php?c=test_1&name=somename&needed_param=someparam

Above two are the same, calling all functions in "app\test_1", passing different arguments to functions accordingly. If there are params missing for some functions, these functions will be skipped with errors output/logged.

Also, auto call in multiple classes is supported like follows:

http://your_domain/entry.php/test_1-test_2-test_3?name=somename&needed_param=someparam
http://your_domain/entry.php?c=test_1-test_2-test_3&name=somename&needed_param=someparam
