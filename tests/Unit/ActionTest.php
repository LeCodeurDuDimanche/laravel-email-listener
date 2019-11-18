<?php

namespace lecodeurdudimanche\EmailListener\Tests\Unit;

use lecodeurdudimanche\EmailListener\Tests\TestCase;
use lecodeurdudimanche\EmailListener\Tests\MockMessage;
use lecodeurdudimanche\EmailListener\Action;
use lecodeurdudimanche\EmailListener\Filter\Filter;
use Webklex\IMAP\Message;


class ActionTest extends TestCase
{

    protected $filter;
    protected $action;
    protected $mockEmail;

    public static function staticTestMethod()
    {
        return true;
    }

    public function callbackMethod()
    {
        return true;
    }

    public function __construct()
    {
        parent::__construct();
        $this->filter = Filter::load('sentByTestUser', self::$configFile);
        $this->action = [$this, 'callbackMethod'];
        $this->mockEmail = new MockMessage();
    }

    public function test_can_construct_with_objects()
    {
        $action = new Action($this->filter, $this->action);

        $this->assertEquals($this->filter, $action->getFilter(), "Filter mismatch");
        $this->assertEquals($this->action, $action->getCallback(), "Action mismatch");
    }

    public function test_can_construct_with_null_values()
    {
        $action = new Action();

        $this->assertNull($action->getFilter());
        $this->assertNull($action->getCallback());
    }


    public function test_can_construct_with_laravel_style_instance_callable()
    {
        $action = new Action(null, ActionTest::class . "@callbackMethod");

        $this->assertTrue(is_callable($action->getCallback()));
        $this->assertEquals($this->callbackMethod(), call_user_func($action->getCallback()));
    }

    public function test_can_construct_with_laravel_style_static_callable()
    {
        $action = new Action(null, ActionTest::class . "@staticTestMethod");

        $this->assertTrue(is_callable($action->getCallback()));
        $this->assertEquals(self::staticTestMethod(), call_user_func($action->getCallback()));
    }

    public function test_can_construct_with_closure()
    {
        $action = new Action(null, function(){ return true;});

        $this->assertTrue(is_callable($action->getCallback()));
        $this->assertEquals(true, call_user_func($action->getCallback()));
    }

    public function test_can_construct_with_string_filter()
    {
        $action = new Action('sentByTestUser', null, self::$configFile);

        $this->assertInstanceOf(Filter::class, $action->getFilter());
    }

    public function test_call_method_with_null_values()
    {
        $this->expectException(\Exception::class);
        $action = new Action();

        $action->call($this->mockEmail);
    }

    public function test_call_method_with_closure()
    {
        $action = new Action($this->filter, function($email, $filter) {
            $this->assertInstanceOf(Message::class, $email);
            $this->assertEquals($this->filter, $filter, "Filter mismatch");
            return true;
        });

        $ret = $action->call($this->mockEmail);
        $this->assertTrue($ret);
    }

    public function test_call_method_with_laravel_style_callable()
    {
        $action = new Action($this->filter, ActionTest::class . '@callbackMethod');

        $ret = $action->call($this->mockEmail);
        $this->assertEquals($this->callbackMethod(), $ret);
    }
}
