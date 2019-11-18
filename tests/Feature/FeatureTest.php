<?php

namespace lecodeurdudimanche\EmailListener\Tests\Feature;

use lecodeurdudimanche\EmailListener\Tests\IMAPTestCase;
use lecodeurdudimanche\EmailListener\EmailListener;
use lecodeurdudimanche\EmailListener\Filter\Filter;
use lecodeurdudimanche\EmailListener\Filter\EmailFilter;
use lecodeurdudimanche\EmailListener\Action;
use Webklex\IMAP;

class FeatureTest extends IMAPTestCase
{

    protected static $value = 0;
    protected static $logs = [];
    protected static $orders = [];

    public function log(IMAP\Message $email, Filter $filter)
    {
        self::$logs[] = $email->getTextBody();
    }

    public function executeAssigments(IMAP\Message $email, Filter $filter)
    {
        $code = array_filter(explode("\n", $email->getTextBody()), function($value) { return $value[0] != "#";});
        foreach($code as $line)
        {
            $line = explode("=", $line);
            $var = trim($line[0]);
            $value = trim($line[1]);
            if (property_exists($this, $var))
                self::$$var = $value;
        }
    }

    public function processOrder(IMAP\Message $email, Filter $filter)
    {
        // Could do some data extraction with the awesome lecodeurdudimanche/document-data-extractor \o/
        foreach($email->getAttachments() as $attachment)
            self::$orders[] = $attachment->getName();
    }

    public function somePublicMethod()
    {

    }

    public function testBasicAction()
    {
        self::sendMail("Subject", "Body");

        config(['email-listener.config-file' => self::$configFile]);
        $emailReceived = false;
        $callback = function($email, $filter) use(&$emailReceived) {
            $this->assertInstanceOf(IMAP\Message::class, $email);
            $emailReceived = true;
        };

        $emailListener = (new EmailListener())
            ->addAction('to', (new Action())
                ->filter(Filter::load('sentByTestUser'))
                ->callback($callback)
            );

        $emailListener->run();

        $this->assertTrue($emailReceived);
    }

    public function testSimpleSyntaxWithConfigFile()
    {
        self::sendMail("This is a test mail log!!", "Body");
        self::sendMail("!!log!!", "That test will be logged");
        self::sendMail("!!log!!", "This one too");
        self::sendMail("!!exec!!", "#This is way to unsecure for real usage\nvalue=42");


        config(['email-listener.config-file' => self::$configFile]);

        $emailListener = new EmailListener;
        $emailListener();

        $this->assertEquals(42, self::$value);
        $this->assertEquals(["That test will be logged",  "This one too"], self::$logs);
    }

    public function testSimpleSyntaxWithAnAttachment()
    {
        self::sendMail("This is a test mail log!!", "Body");
        self::sendMailWithAttachments("This is a false order message", "I am so malicious", ['order-000066.pdfx']);
        self::sendMailWithAttachments("This is another false order message", "I am so malicious", ['order-000066.pdf', 'some-malicious-file.pdf']);
        self::sendMailWithAttachments("Your order #1014 has been shipped", "Hello !\nYour order has been shipped and will be delivered tomorrow.\nRegards", ['order-001014.pdf']);


        config(['email-listener.config-file' => self::$configFile]);

        $emailListener = new EmailListener;
        $emailListener();

        $this->assertEquals(["order-001014.pdf"], self::$orders);
    }

    public function testAddAnActionAndSave()
    {
        config(['email-listener.config-file' => __DIR__ . "/../data/test.json"]);
        system("cp -f " . self::$configFile . " " . config('email-listener.config-file') . " 1> /dev/null");

        $expectedFileContent = json_decode(file_get_contents(config("email-listener.config-file")), true);
        $expectedFileContent['actions'][] = [
            "client" => "to",
            "filter" => "Another filter",
            "callback" => "lecodeurdudimanche\\EmailListener\\Tests\\Feature\\FeatureTest@somePublicMethod"
        ];
        $expectedFileContent['actions'][] = [
            "client" => "to",
            "filter" => "hasAttachments",
            "callback" => "lecodeurdudimanche\\EmailListener\\Tests\\Feature\\FeatureTest@somePublicMethod"
        ];
        $expectedFileContent['filters']["Another filter"] = [
            "type"=> "email",
            "filters"=> [
                [
                    "method"=> "cc",
                    "args"=> "bot@localhost"
                ],
                [
                    "method"=> "text",
                    "args"=> "auto"
                ]
            ],
            "attachments"=> [
                "type"=> "attachment",
                "filters"=> [
                    [
                        "method"=> "num",
                        "args"=> 0
                    ]
                ]
            ]
        ];
        $expectedFileContent = json_encode($expectedFileContent, JSON_PRETTY_PRINT);

        $action = (new Action())
            ->filter((new EmailFilter())
                ->setName('Another filter')
                ->cc('bot@localhost')
                ->text('auto')
                ->attachments(function ($a){
                    $a->num(0);
                }))
            ->callback(FeatureTest::class . '@somePublicMethod');

        (new EmailListener())
            ->load()
            ->addAction("to", $action)
            ->addAction("to", [$this, "somePublicMethod"], "hasAttachments")
            ->save();

        $this->assertEquals($expectedFileContent, file_get_contents(config('email-listener.config-file')));
    }
}
