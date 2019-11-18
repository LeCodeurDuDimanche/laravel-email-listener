<?php

namespace lecodeurdudimanche\EmailListener\Tests\Unit;

use lecodeurdudimanche\EmailListener\Tests\TestCase;
use lecodeurdudimanche\EmailListener\Filter\EmailFilter;
use lecodeurdudimanche\EmailListener\Filter\AttachmentFilter;
use lecodeurdudimanche\EmailListener\Filter\Filter;
use lecodeurdudimanche\EmailListener\CorruptedDataException;


class FilterTest extends TestCase
{
    public function test_can_load_with_filename()
    {
        $filter = Filter::load('sentByTestUser', self::$configFile);

        $this->assertNotNull($filter);
        $this->assertInstanceOf(EmailFilter::class, $filter);
    }

    public function test_can_load_different_filter_types()
    {
        $filter = Filter::load('hasAttachments', self::$configFile);

        $this->assertNotNull($filter);
        $this->assertInstanceOf(AttachmentFilter::class, $filter);
    }

    public function test_can_handle_missing_filter()
    {
        $filter = Filter::load('anyFilter', self::$configFile);

        $this->assertNull($filter);
    }

    public function test_can_handle_invalid_file()
    {
        $this->expectException(CorruptedDataException::class);
        $filter = Filter::load('sentByTestUser', self::$dataDir . '/invalid.json');
    }

    public function test_can_handle_null_filename_with_config()
    {
        config(['email-listener.config-file' => self::$configFile]);
        $filter = Filter::load('sentByTestUser');

        $this->assertNotNull($filter);
        $this->assertInstanceOf(EmailFilter::class, $filter);
    }

    public function test_can_handle_null_filename_without_config()
    {
        $this->expectException(\InvalidArgumentException::class);
        config(['email-listener.config-file' => '']);
        $filter = Filter::load('sentByTestUser');
    }

    /* TODO: Implement laravel filesystems and then do this
   public function test_can_save_filter()
    {

    }*/

    public function test_can_load_from_and_save_to_array()
    {
        $filters = [
            "type" => "email",
            "filters" => [[ "method" => "cc", "args" => "test@mail.com"]]
        ];
        $filter = (new EmailFilter())->fromArray($filters);

        $this->assertEquals([$filter->getName() => $filters], $filter->toArray());
     }

     public function test_handle_correctly_invalid_data()
     {

         $this->expectException(CorruptedDataException::class);
         $filters = [
             "type" => "email",
             "filters" => [[ "cc" => "localhost"]]
         ];
         $filter = (new EmailFilter())->fromArray($filters['filters']);
      }
}
