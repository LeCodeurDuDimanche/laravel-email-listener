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

This library uses [laravel-imap]() for IMAP and POP communication, so if you need more help about IMAP and POP configuration take a look at [their documentation](https://github.com/Webklex/laravel-imap#configuration).

## Basic usage
First, you'll need to create an `Action` object, that contains a callback to be executed and a filter determining which email will trigger that action :
```php
$action = (new Action())
    ->filter((new EmailFilter())
        ->from("an.adress@example.com")
        )
    ->callback($callback);
// Or using the constructor
$action = new Action(
    (new EmailFilter())->from("an.adress@example.com"),
    $callback);
```
You can now bind that action to an `EmailListener` :
```php
// Where 'imap_client' is the name of your IMAP or POP account in config/imap.php file
$emailListener = (new EmailListener())
    ->addAction(Client::account('imap_client'), $action);
```

And then run it :
```php
    $emailListener->run();
```
\
With Laravel, you can put this code in a Closure and then call it in your [schedule() fonction] to run it automatically.

You can load filters from JSON with the `Filter::load()` fonction like so :
```php
// You must either set config("filter.file") to the path of your JSON file
// or pass the path as the second argument of Filter::load
$filter = Filter::load('fancyFilterName', 'path/to/file.json');
```

Description of the expected format :
```
  <filterName> : {
    "type": "email" | "attachment",
    "filters": [
      {
          "method" : <filter>,
          "args" : <argument> | <array of arguments>
      },
      ...
    ]
    ("attachments" : <filterDescription>)(optional, if type == "email")
  },
  ...
}
```
An example can be found in the [tests directory](tests/data/filters.json).

## Full API
_Coming soon_


[schedule() fonction](https://laravel.com/docs/5.7/scheduling#defining-schedules)
