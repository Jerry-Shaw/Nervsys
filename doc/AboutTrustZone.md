# About TrustZone

All API exposed methods should be put in a public variable named "$tz".  
$tz defined in string will be transformed to array which contains the string as the only value.  
When $tz defined as "*", or, contains "*", it means all public methods in this class can be requested via API command.  
All class which has no "$tz", or, equals empty, cannot be requested via API command.

```php
<?php

namespace demo;

/**
* Class example
 * @package demo
 */
class example
{
    public $tz = [
        'method_a',
        'method_b',
    ];

}
```

```php
<?php

namespace demo;

/**
* Class example
 * @package demo
 */
class example
{
    public $tz = 'myMethod';//will be transformed to ["myMethod"]

}
```

```php
<?php

namespace demo;

/**
* Class example
 * @package demo
 */
class example
{
    public $tz = [
        'otherMethod',//non-sense because it already contains "*"
        '*'
    ];

}
```

```php
<?php

namespace demo;

/**
* Class example
 * @package demo
 */
class example
{
    public $tz = '*';//will be transformed to ["*"]

}
```