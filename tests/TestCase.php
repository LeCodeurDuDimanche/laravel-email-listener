<?php
namespace lecodeurdudimanche\EmailListener\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected static $dataDir = __DIR__  . "/data";
    protected static $vendorDir = __DIR__  . "/../vendor";
    protected static $configFile = __DIR__  . "/data/filters.json";
    protected static $fetcher = [
        'host'          => 'localhost',
        'port'          => 3143,
        'encryption'    => false,
        'validate_cert' => false,
        'username'      => 'to',
        'password'      => 'pwd',
        'protocol'      => 'imap'
    ];

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set("imap.accounts.to", self::$fetcher);
    }
}
