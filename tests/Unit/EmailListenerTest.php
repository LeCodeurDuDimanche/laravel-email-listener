<?php

namespace lecodeurdudimanche\EmailListener\Tests\Unit;

use lecodeurdudimanche\EmailListener\Tests\TestCase;
use lecodeurdudimanche\EmailListener\EmailListener;
use lecodeurdudimanche\EmailListener\Action;
use lecodeurdudimanche\EmailListener\Filter\Filter;
use Webklex\IMAP\Message;

//TODO: Test loading multiple files and test unknow filter in config file
class EmailListenerTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set("email-listener.config-file", self::$configFile);
    }

    protected function expectedFromActions(array $data) : array
    {
        $filters = [];
        $actions = [];

        foreach($data as $action)
        {
            $client = array_key_exists('client', $action) ? $action['client'] : 'to';
            $action = is_array($action) ? $action['action'] : $action;

            $filter = $action->getFilter();
            if (array_search($filter->getName(), $filters) === FALSE)
                $filters = array_merge($filters, $filter->toArray());

            $callback = $action->getCallback();
            if (is_array($callback))
            {
                $class = is_string($callback[0]) ? $callback[0] : get_class($callback[0]);
                $callback = $class . "@" . $callback[1];
            }

            $newAction = [];
            $newAction['client'] = $client;
            $newAction['callback'] = $callback;
            $newAction['filter'] = $filter->getName();
            $actions[] = $newAction;
        }
        return compact("actions", "filters");
    }

    public static function somePublicMethod()
    {

    }

    public function test_can_add_array_of_actions()
    {
        $actions = [
            [
                "client" => "to",
                "action" => new Action("hasAttachments", [EmailListenerTest::class, 'somePublicMethod'])
            ],
            [
                "client" => "to",
                "action" => new Action("sentByTestUser", 'lecodeurdudimanche\EmailListener\Tests\Unit\EmailListenerTest@test_can_add_array_of_actions')
            ]
        ];

        $actual = (new EmailListener)
            ->addActions($actions)
            ->toArray();

        $this->assertEquals($this->expectedFromActions($actions), $actual);
    }

    public function test_can_handle_invalid_array_of_actions()
    {
        $this->expectException(\InvalidArgumentException::class);
        $actions = [
            [
                "client" => "to",
            ],
            [
                "action" => new Action("sentByTestUser", 'lecodeurdudimanche\EmailListener\Tests\Unit\EmailListenerTest@test_can_add_array_of_actions')
            ]
        ];

        $actual = (new EmailListener)
            ->addActions($actions);
    }

    public function test_can_add_array_of_actions_for_client()
    {
        $actions = [
            new Action("hasAttachments", [EmailListenerTest::class, 'somePublicMethod']),
            new Action("sentByTestUser", 'lecodeurdudimanche\EmailListener\Tests\Unit\EmailListenerTest@test_can_add_array_of_actions')
        ];

        $actual = (new EmailListener)
            ->addActionsForClient('to', $actions)
            ->toArray();

        $this->assertEquals($this->expectedFromActions($actions), $actual);
    }

    public function test_can_handle_invalid_array_of_actions_for_client()
    {
        $this->expectException(\TypeError::class);

        $actions = [null];

        $actual = (new EmailListener)
            ->addActionsForClient('to', $actions);
    }


    public function test_can_add_simple_action()
    {
        $action = new Action("hasAttachments", [EmailListenerTest::class, 'somePublicMethod']);

        $actual = (new EmailListener)
            ->addAction('to', $action)
            ->toArray();

        $this->assertEquals($this->expectedFromActions([$action]), $actual);
    }

    public function test_can_add_simple_action_and_filter()
    {
        $filter = "hasAttachments";
        $action = EmailListenerTest::class . "@somePublicMethod";

        $actual = (new EmailListener)
            ->addAction('to', $action, $filter)
            ->toArray();

        $this->assertEquals($this->expectedFromActions([new Action($filter, $action)]), $actual);
    }

    public function test_handles_inexistant_client()
    {
        $this->expectException(\InvalidArgumentException::class);

        $filter = "hasAttachments";
        $action = EmailListenerTest::class . "@somePublicMethod";
        $actual = (new EmailListener)
            ->addAction('inexistant', $action, $filter)
            ->toArray();
    }

    public function test_handles_invalid_arguments_adding_simple_action()
    {
        $this->expectException(\InvalidArgumentException::class);

        $actual = (new EmailListener)
            ->addAction('to', EmailListenerTest::class . "@somePublicMethod")
            ->toArray();
    }

    public function test_handles_invalid_arguments_adding_simple_action2()
    {
        $this->expectException(\InvalidArgumentException::class);

        $actual = (new EmailListener)
            ->addAction('to', new Action("hasAttachments", EmailListenerTest::class . "@somePublicMethod"), "hasAttachments")
            ->toArray();
    }

    public function test_saves_correctly()
    {
        $actions = [
            [
                "client" => "to",
                "action" => new Action("hasAttachments", [EmailListenerTest::class, 'somePublicMethod'])
            ],
            [
                "client" => "to",
                "action" => new Action("sentByTestUser", 'lecodeurdudimanche\EmailListener\Tests\Unit\EmailListenerTest@test_can_add_array_of_actions')
            ]
        ];
        $expected = [
            "actions" => [[
                    "client" => "to",
                    "filter" => "hasAttachments",
                    "callback" => "lecodeurdudimanche\\EmailListener\\Tests\\Unit\\EmailListenerTest@somePublicMethod"
                ],
                [
                    "client" => "to",
                    "filter" => "sentByTestUser",
                    "callback" => "lecodeurdudimanche\EmailListener\Tests\Unit\EmailListenerTest@test_can_add_array_of_actions"
                ]
            ],
            "filters" => [
                    "hasAttachments" => Filter::load('hasAttachments')->toArray()["hasAttachments"],
                    "sentByTestUser" => Filter::load('sentByTestUser')->toArray()["sentByTestUser"]
            ]
        ];

        $actual = (new EmailListener)
            ->addActions($actions)
            ->toArray();

        $this->assertEquals($expected, $actual);
    }

    public function test_loads_correctly()
    {
        $expected = json_decode(file_get_contents(self::$configFile), true);
        $actual = (new EmailListener)
            ->load()
            ->toArray();

        $this->assertEquals($expected, $actual);
    }

    public function test_save_fails_with_closure_callback()
    {
        $this->expectException(\InvalidArgumentException::class);

        $actual = (new EmailListener)
            ->addAction('to', function(){return true;}, 'hasAttachments')
            ->toArray();
    }

    public function test_can_be_invoked()
    {
        $this->assertTrue(is_callable(new EmailListener));
    }
}
