# Laravel Email Listener
A simple library to do actions when receiving emails, desgined to work with Laravel

## Installation
Via composer :
```bash
composer require lecodeurdudimanche/laravel-email-listener
```

You will need to install and enable some `php` extensions (`php-imap`, `php-mbstring` and `php-mcrypt`).
With Ubuntu :
```bash
sudo apt install php*-imap php*-mbstring php*-mcrypt
```

## Configuration

You need to set the configuration of your IMAP or POP accounts.
First run the following command to publish the configration in your `config` folder :
```bash
php artisan vendor:publish --provider="Webklex\IMAP\Providers\LaravelServiceProvider"
```

Next, modify the `accounts` configuration to add your own accounts.

This library uses [laravel-imap](https://github.com/Webklex/laravel-imap) for IMAP and POP communication, so if you need more help about IMAP and POP configuration take a look at [their documentation](https://github.com/Webklex/laravel-imap#configuration).

## Basic usage

In the [schedule()](https://laravel.com/docs/5.7/scheduling#defining-schedules) method of your `App\Console\Kernel` class, add this line :  
```php
$schedule->call(new EmailListener)->everyfiveMinutes(); // or any other [frequency option](https://laravel.com/docs/5.7/scheduling#schedule-frequency-options)
```

This will cause the 'EmailListener' to load actions and filters from the configuration file (whose path must be provided in `email-listener.config-file` configuration value) and then run.

In order to create the configuration programtically, or to use the library without loading anything, you'll need to register `Actions`.
First, you'll need to create an `Action` object, that contains a callback to be executed and a filter determining which email will trigger that action :
```php
$action = (new Action())
    ->filter((new EmailFilter())
        ->from("an.adress@example.com")
        )
    ->callback($callback);
// callback can be a closure, a array with class and method or a string with format 'class@method'
// Tou can also use the constructor
$action = new Action(
    (new EmailFilter())->from("an.adress@example.com"),
    $callback);
```
You can now bind that action to an `EmailListener` :
```php
$emailListener = (new EmailListener())
    ->addAction('imap_client', $action);

// Where 'imap_client' is the name of your IMAP or POP account in config/imap.php file
```

And then run it or save it :
```php
$emailListener->run();
$emailListener->save();
```

For instance, to add actions to an existing listener and then save it :
```php
(new EmailListener())
    ->load()
    ->addAction('imap_client', $action)
    ->save();
```


You can load filters individually from JSON with the `Filter::load()` function and save them with the `Filter::save()` fonction like so :
```php
// You must either set config("email-listener.config-file") to the path of your JSON file
// or pass the path as the second argument of Filter::load
$filter = Filter::load('fancyFilterName', 'path/to/file.json');
$filter->save('path/to/file.json');
// You can also save the filter with another name like so :
$filter->saveAs('another name', 'path/to/file.json');
```

Description of the expected format :
```
  {
      "actions" : [
        {
            "client" : <client_name>,
            "filter" : <filter_name>,
            "callback" : "class@method"
        },
        ...
      ],
      "filters" : [
          <filterName> : {
            "type": "email" | "attachment",
            "filters": [
              {
                  "method" : <filter>,
                  "args" : <argument> | <array of arguments>
              },
              ...
            ]
            ("attachments" : <filterDescription>)(only if type == "email", optional)
          },
          ...
      ]
}
```
An example can be found in the [tests directory](tests/data/filters.json).

## Full API
_Coming soon_
