<?php

namespace lecodeurdudimanche\EmailListener\Tests\Unit;

use lecodeurdudimanche\EmailListener\Tests\IMAPTestCase;
use lecodeurdudimanche\EmailListener\Filter\Filter;
use lecodeurdudimanche\EmailListener\Filter\EmailFilter;
use Webklex\IMAP\Message;


class EmailFilterTest extends IMAPTestCase
{

    public static function setUpBeforeClass() : void
    {
        parent::setUpBeforeClass();
        self::sendMailWithHeaders("This is an original subject", "Hello buddy ! How are you ?", ["cc" => ["a@localhost", "b@localhost", "other@localhost"]]);
        self::sendMailWithHeaders("URGENT: S", "Body", ["cc" => "anotherone@localhost"]);
        self::sendMailFrom("bot@localhost", "!!Identifier!!", "#This is a special mail intended to trigger an action on server side\nSendMail OrderShipped client@mail.com");
        self::sendMail("RE: News", "Hello,\nThis is intended to be a normal email.\nBye,\nSender");
        self::sendMailWithAttachments("RE: Document you needed", "There are the 3 documents you wanted", ["att-png", "att-xml.xml", "att-html.pdf"]);
        self::sendMailWithAttachments("This is a simple mail", "There are attachments in there", ["att-png", "att-1.pdf"]);
    }

    public function test_can_filter_using_saved_filter()
    {
        $emails = Filter::load('sentByTestUser', self::$filtersSaveFile)
            ->run(self::getInbox());

        $this->assertEmails([1, 2, 4, 5, 6], $emails);
    }

    public function test_can_filter_using_saved_filter_with_attachment_filter()
    {
        $emails = Filter::load('sentByTestUserWithAttachments', self::$filtersSaveFile)
            ->run(self::getInbox());

        $this->assertEmails([5, 6], $emails);
    }

    public function test_loads_correctly_from_array_with_attachments()
    {
        $array = [
            "type" => "email",
            "filters" => [["method" => "from", "args" => "adress"]],
            "attachments" => [
                "type" => "attachment",
                "filters" => [
                    ["method" => "min", "args" => 1 ]
                ]
            ]
        ];
        $filter = (new EmailFilter())->fromArray($array);
        $this->assertEquals($array, $filter->toArray());
    }

    public function test_loads_correctly_from_array_without_attachments()
    {
        $array = [
            "type" => "email",
            "filters" => [["method" => "from", "args" => "address"]]
        ];
        $filter = (new EmailFilter())->fromArray($array);
        $this->assertEquals($array, $filter->toArray());
    }

    public function test_can_run_from_filter()
    {
        $emails = (new EmailFilter())
            ->from('bot@localhost')
            ->run(self::getInbox());

        $this->assertEmails([3], $emails);


        $emails = (new EmailFilter())
            ->from('from@localhost')
            ->run(self::getInbox());

        $this->assertEmails([1, 2, 4, 5, 6], $emails);
    }

    public function test_can_run_subject_filter()
    {
        $emails = (new EmailFilter())
            ->subject('this is')
            ->run(self::getInbox());

        $this->assertEmails([1, 6], $emails);

        $emails = (new EmailFilter())
            ->subject('RE:')
            ->run(self::getInbox());

        $this->assertEmails([4, 5], $emails);

        $emails = (new EmailFilter())
            ->subject('!!Identifier!!')
            ->run(self::getInbox());

        $this->assertEmails([3], $emails);
    }

    public function test_can_run_text_filter()
    {
        $emails = (new EmailFilter())
            ->text('you')
            ->run(self::getInbox());

        $this->assertEmails([1, 5], $emails);

    }

    public function test_can_run_date_filter()
    {
        $emails = (new EmailFilter())
            ->before(now())
            ->run(self::getInbox());

        $this->assertEmails([], $emails);

        $emails = (new EmailFilter())
            ->since(now())
            ->run(self::getInbox());

        $this->assertEmails([1, 2, 3, 4, 5, 6], $emails);
    }

    public function test_can_run_cc_filter()
    {
        $emails = (new EmailFilter())
            ->cc("other@localhost")
            ->run(self::getInbox());

        $this->assertEmails([1], $emails);
    }

    public function test_can_run_complex_filter()
    {
        $emails = (new EmailFilter())
            ->cc("a@localhost")
            ->subject("This is")
            ->text("are")
            ->run(self::getInbox());

        $this->assertEmails([1], $emails);
    }

    public function test_can_chain_with_attachment_filter()
    {
        $emails = (new EmailFilter())
            ->text('you')
            ->attachments(function($attachmentFilter) {
                $attachmentFilter->min(1);
            })
            ->run(self::getInbox());

        $this->assertEmails([5], $emails);
    }
}
