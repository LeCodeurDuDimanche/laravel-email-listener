<?php
namespace lecodeurdudimanche\EmailListener\Tests;

use Webklex\IMAP\Client;

/**
 * Larvel initialization for TestCases
 */
class IMAPTestCase extends TestCase
{
    private static $sender = [
        'host' => 'localhost',
        'port' => 3025,
        'username' => 'from',
        'password' => 'pwd',
    ];

    private static $greenmailJar = __DIR__ . "/bin/greenmail.jar";
    private static $greenmailProc = null;
    private static $greenmailPid = 0;

    protected function getEnvironmentSetUp($app)
    {
        $config = require(self::$vendorDir . "/webklex/laravel-imap/src/config/imap.php");
        foreach($config as $key => $value)
            $app['config']->set($key, $value);
    }

    protected function getPackageProviders($app)
    {
        return [\Webklex\IMAP\Providers\LaravelServiceProvider::class];
    }

    public static function setUpBeforeClass() : void
    {
        //$greenmailProc is actually (on linux) the bash instance hosting the greenmail process
        self::$greenmailProc = proc_open('echo $$&&exec java -Dgreenmail.setup.test.smtp\
                                      -Dgreenmail.setup.test.imap\
                                      -Dgreenmail.users=from:pwd@localhost,to:pwd@localhost\
                                      -jar ' . self::$greenmailJar . " 2>&1 /dev/null",
                                      [1 => ["pipe", "w"]],
                                      $pipes
            );
        self::$greenmailPid = intval(fgets($pipes[1]));

        while(! self::checkServer(self::$sender['host'], self::$sender['port']))
            sleep(1);
    }

    protected static function checkServer(string $hostname, int $port) : bool
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $serverUp = $socket !== FALSE && @socket_connect($socket, $hostname, $port);
        socket_close($socket);
        return $serverUp;
    }

    public static function tearDownAfterClass() : void
    {
        // Kill the greenmail server
        posix_kill(self::$greenmailPid, SIGKILL);
        // Close the hosting process
        proc_terminate(self::$greenmailProc, SIGKILL);
        proc_close(self::$greenmailProc);
    }

    protected function assertEmails($ref, $emails)
    {
        $this->assertCount(count($ref), $emails);

        $bodies = array_map(function($email){return $email->getUid();}, $emails);

        $this->assertEquals([], array_diff($ref, $bodies), "Expected : " . print_r($ref, true) . "\n Got : " . print_r($bodies, true));
    }

    protected static function unseenAll()
    {
        foreach(self::getInbox()->getMessages() as $email)
            $email->unsetFlag('Seen');
    }

    protected static function sendMail($subject, $body)
    {
        self::sendMailWithAttachments($subject, $body, []);
    }

    protected static function sendMailFrom($from, $subject, $body)
    {
        self::sendMailWithAttachments($subject, $body, [], $from);
    }

    protected static function sendMailWithHeaders($subject, $body, $headers)
    {
        self::sendMailWithAttachments($subject, $body, [], null, $headers);
    }

    protected static function sendMailWithAttachments($subject, $body, $attachments, $from = null, $headers = [])
    {
        $transport = (new \Swift_SmtpTransport(self::$sender['host'], self::$sender['port']))
          ->setUsername(self::$sender['username'])
          ->setPassword(self::$sender['password']);

        // Create the Mailer using your created Transport
        $mailer = new \Swift_Mailer($transport);

        // Create a message
        $message = (new \Swift_Message($subject))
          ->setFrom($from ?? self::$sender['username'] . "@localhost")
          ->setTo(self::$fetcher['username'] . "@localhost")
          ->setBody($body)
          ;

        foreach($headers as $header => $value)
        {
            $method = "set$header";
            $message->$method($value);
        }

        foreach ($attachments as $attachment)
            $message->attach(\Swift_Attachment::fromPath(self::$dataDir . '/' . $attachment));

        // Send the message
        $result = $mailer->send($message);
        self::assertGreaterThanOrEqual(1, $result);
    }

    protected static function getInbox()
    {
        $client = new Client(self::$fetcher);
        $client->connect();
        return $client->getFolder('INBOX');
    }


}
