<?php
namespace lecodeurdudimanche\EmailListener\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected static $dataDir = __DIR__  . "/data";
    protected static $vendorDir = __DIR__  . "/../vendor";
    protected static $filtersSaveFile = __DIR__  . "/data/filters.json";
}
