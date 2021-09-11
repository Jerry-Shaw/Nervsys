# Start

## Install

### Requirements

- PHP >= 7.4.0

### Create project

Let's create folders firstly.

- NervSys location: `/data/code/ns`.
- Project location: `/data/code/hello`.

Firstly, clone Nervsys source code to `/data/code/ns`.

```bash
git clone https://github.com/Jerry-Shaw/NervSys.git /data/code/ns
```

Let's do some work to build our project environment. All the job we do is under the main project
folder `/data/code/hello`.

Do the following command to create project directory.

```bash
mkdir -p /data/code/hello/public/  
```

Then, create a entry file using following command:

```bash
cat <<EOF >  /data/code/hello/public/index.php
<?php
require '/data/code/ns/NS.php';

//Optional. If needed, please review "Ext/libCoreApi.php"
\Ext\libCoreApi::new()
    //Open core debug mode (all error info will be output with results)
    ->setCoreDebug(true)
    //Open CORS for all requests (with defaule request headers)
    ->addCorsRecord('*')
    //Set output content type to "application/json; charset=utf-8"
    ->setContentType('application/json');

NS::new();
EOF
```

### Run project

Run command under `/data/code/hello/public/`:

```bash
php -S localhost:8000 
```

When `[]` is shown, project runs successfully.

## Ceare the first API function.

Create a class file under `api` folder named `user.php`.

```php
<?php

namespace api;

class user
{

    public function login(): array
    {
        return [
            "a" => 1
        ];
    }
}
```

Open browser and go to `http://localhost/?c=user/login`.

We'll get `{"a":1}` in response.

## Via CLI

Create a class file under `api` folder named `command.php`.

```php
<?php

namespace app;

class command
{

    public function demo(): array
    {
        return "hello";
    }
}
```

Run the command to get response:

```bash
$ php public/index.php -r /app/command/demo
success
```