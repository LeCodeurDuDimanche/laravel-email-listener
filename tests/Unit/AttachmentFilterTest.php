<?php

namespace lecodeurdudimanche\EmailListener\Tests\Unit;

use lecodeurdudimanche\EmailListener\Tests\IMAPTestCase;
use lecodeurdudimanche\EmailListener\Filter\AttachmentFilter;
use lecodeurdudimanche\EmailListener\Filter\Filter;
use Webklex\IMAP\Message;

class AttachmentFilterTest extends IMAPTestCase
{
    public static function setUpBeforeClass() : void
    {
        parent::setUpBeforeClass();
        self::sendMailWithAttachments("1 attachment", "1", ["att-1.pdf"]);
        self::sendMail("No attachment", "2");
        self::sendMailWithAttachments("3 attachments", "3", ["att-1.pdf", "att-2.pdf", "att-3.pdf"]);
        self::sendMailWithAttachments("Various types of attachments", "4", ["att-png", "att-xml.xml", "att-html.pdf"]);
    }


    public function test_can_filter_using_saved_filter()
    {
        $emails = Filter::load('hasAttachments', self::$filtersSaveFile)
            ->run(self::getInbox());

        $this->assertEmails([1, 3, 4], $emails);
    }

    public function test_can_filter_using_number_of_attachments()
    {
        $emails = (new AttachmentFilter())
            ->num(1)
            ->run(self::getInbox());

        $this->assertEmails([1], $emails);

        $emails = (new AttachmentFilter())
            ->num(3)
            ->run(self::getInbox());

        $this->assertEmails([3, 4], $emails);
    }

    public function test_can_filter_using_maximum_number_of_attachments()
    {
        $emails = (new AttachmentFilter())
            ->max(1)
            ->run(self::getInbox());

        $this->assertEmails([1, 2], $emails);

        $emails = (new AttachmentFilter())
            ->max(-1)
            ->run(self::getInbox());

        $this->assertEmails([], $emails);
    }

    public function test_can_filter_using_minimum_number_of_attachments()
    {
        $emails = (new AttachmentFilter())
            ->min(2)
            ->run(self::getInbox());

        $this->assertEmails([3, 4], $emails);

        $emails = (new AttachmentFilter())
            ->min(3)
            ->run(self::getInbox());

        $this->assertEmails([3, 4], $emails);

        $emails = (new AttachmentFilter())
            ->min(4)
            ->run(self::getInbox());

        $this->assertEmails([], $emails);
    }

    public function test_can_filter_using_name_of_attachments()
    {
        $emails = (new AttachmentFilter())
            ->name("att-1.pdf")
            ->run(self::getInbox());

        $this->assertEmails([1, 3], $emails);

        $emails = (new AttachmentFilter())
            ->name("att-1.xml")
            ->run(self::getInbox());

        $this->assertEmails([], $emails);
    }

    public function test_can_filter_using_type_of_attachments()
    {
        $emails = (new AttachmentFilter())
            ->type("application/pdf")
            ->run(self::getInbox());

        $this->assertEmails([1, 3], $emails);

        $emails = (new AttachmentFilter())
            ->type("pdf")
            ->run(self::getInbox());

        $this->assertEmails([1, 3], $emails);

        $emails = (new AttachmentFilter())
            ->type("image/png")
            ->run(self::getInbox());

        $this->assertEmails([4], $emails);
    }

    public function test_can_filter_using_regex()
    {
        $emails = (new AttachmentFilter())
            ->match("/att-[13]\.pdf/")
            ->run(self::getInbox());

        $this->assertEmails([1, 3], $emails);
    }

    public function test_can_filter_using_custom_callback()
    {
        $emails = (new AttachmentFilter())
            ->has(function($attachment) {
                return strlen($attachment->getName()) == 9;
            })
            ->run(self::getInbox());

        $this->assertEmails([1, 3], $emails);

        $emails = (new AttachmentFilter())
            ->no(function($attachment) {
                return $attachment->getContentType() != $attachment->getMimeType();
            })
            ->run(self::getInbox());

        $this->assertEmails([1, 2, 3], $emails);

        $emails = (new AttachmentFilter())
            ->every(function($attachment) {
                return strpos($attachment->getName(), "p") === FALSE;
            })
            ->run(self::getInbox());

        $this->assertEmails([2], $emails);

        $emails = (new AttachmentFilter())
            ->filter(function($attachments) {
                $count =  $attachments->count();
                return $count >= 2 && $count <= 3;
            })
            ->run(self::getInbox());

        $this->assertEmails([3, 4], $emails);
    }

    public function test_can_filter_chaining_filters()
    {
        $emails = (new AttachmentFilter())
            ->min(1)
            ->max(3)
            ->run(self::getInbox());

        $this->assertEmails([1, 3, 4], $emails);

        $emails = (new AttachmentFilter())
            ->type("application/pdf", true)
            ->min(2)
            ->run(self::getInbox());

        $this->assertEmails([3], $emails);

        $emails = (new AttachmentFilter())
            ->type("application/pdf", true)
            ->match("/att-.*/")
            ->num(1)
            ->run(self::getInbox());

        $this->assertEmails([1], $emails);

        $emails = (new AttachmentFilter())
            ->max(2)
            ->min(3)
            ->run(self::getInbox());

        $this->assertEmails([], $emails);
    }
}
