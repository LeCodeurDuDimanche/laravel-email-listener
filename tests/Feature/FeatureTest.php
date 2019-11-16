<?php

namespace lecodeurdudimanche\EmailListener\Tests\Feature;

use lecodeurdudimanche\EmailListener\Tests\IMAPTestCase;
use lecodeurdudimanche\EmailListener\EmailListener;
use lecodeurdudimanche\EmailListener\Filter\Filter;
use lecodeurdudimanche\EmailListener\Action;
use Webklex\IMAP;

class FeatureTest extends IMAPTestCase
{

    /**
     * Simple action
     *
     * @return void
     */
    public function testSimpleAction()
    {
        self::sendMail("Subject", "Body");

        config(['filter.file' => self::$filtersSaveFile]);
        $emailReceived = false;
        $callback = function($email, $filter) use(&$emailReceived) {
            $this->assertInstanceOf(IMAP\Message::class, $email);
            $emailReceived = true;
        };

        $emailListener = (new EmailListener())
            ->addAction(new IMAP\Client(IMAPTestCase::$fetcher), (new Action())
                ->filter(Filter::load('sentByTestUser'))
                ->callback($callback)
            );

        $emailListener->run();

        $this->assertTrue($emailReceived);
    }
}
