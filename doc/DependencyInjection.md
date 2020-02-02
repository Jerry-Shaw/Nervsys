# Dependency Injection

As requested, NervSys factory and API caller support simple DI (Dependency Injection) when a function is being called, while an object of stdClass is defined in arguments.  

Example as following two scripts:

```php
<?php

namespace demo;

class test
{
    public $tz = '*';
    
    public function __construct()
    {
    }
    
    public function myTest(demo\dep $dep, $other_agrv)
    {
        //You can get demo\dep object here when this function is being called
        //"c" is like "demo/test-myTest"
    }
}
```

```php
<?php

namespace demo;

class dep
{
    public $dep_prop = 'I am a dep class';
}
```