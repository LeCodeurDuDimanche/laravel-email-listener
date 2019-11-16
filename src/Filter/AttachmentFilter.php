<?php
namespace lecodeurdudimanche\EmailListener\Filter;

use Webklex\IMAP\Support\AttachmentCollection as Attachments;
use Webklex\IMAP;

class AttachmentFilter extends Filter {

    public function getPrettyFilterName(string $filter) : string
    {
        return strrpos($filter, "Cb") === strlen($filter) - 2 ? substr($filter, 0, -2) : $filter;
    }

    public function __call(string $method, array $args) : AttachmentFilter
    {
        // Check if the method name as a callback corresponding to it
        $callbackName = "${method}Cb";
        if (! method_exists($this, $callbackName))
            throw new \BadMethodCallException("Method $method does not exist");

        $this->filters[] = ["method" => $callbackName, "args" => $args];
        return $this;
    }

    protected function minCb(Attachments $attachments, int $min) : bool
    {
        return $attachments->count() >= $min;
    }

    protected function maxCb(Attachments $attachments, int $max) : bool
    {
        return $attachments->count() <= $max;
    }

    protected function numCb(Attachments $attachments, int $num) : bool
    {
        return $attachments->count() == $num;
    }

    protected function nameCb(Attachments $attachments, string $name) : bool
    {
        return $this->hasCb($attachments, function($a) use($name) {
            return $a->getName() == $name;
        });
    }

    protected function typeCb(Attachments $attachments, string $type) : bool
    {
        return $this->hasCb($attachments, function($a) use($type) {
            return $a->getMimeType() === $type || $a->getExtension() == $type;
        });
    }

    protected function matchCb(Attachments $attachments, string $regex) : bool
    {
        return $this->hasCb($attachments, function($a) use($regex) {
            return preg_match($regex, $a->getName());
        });
    }

    protected function hasCb(Attachments $attachments, $callable) : bool
    {
        if (! is_callable($callable))
            throw\InvalidArgumentException("First argument of method has() must be callable");

        return $attachments->some($callable);
    }

    protected function noCb(Attachments $attachments, $callable) : bool
    {
        if (! is_callable($callable))
            throw\InvalidArgumentException("First argument of method no() must be callable");

        return ! $this->hasCb($attachments, $callable);
    }

    protected function everyCb(Attachments $attachments, $callable) : bool
    {
        if (! is_callable($callable))
            throw\InvalidArgumentException("First argument of method every() must be callable");

        return $attachments->every($callable);

    }

    protected function filterCb(Attachments $attachments, $callable) : bool
    {
        return boolval(call_user_func($callable, $attachments));
    }


    protected function filterCallback(IMAP\Message $email) : bool
    {
        $attachments = $email->getAttachments();
        foreach($this->filters as $filter)
        {
            if (! call_user_func_array([$this, $filter['method']], array_merge([$attachments], $filter['args'])))
                return false;
        }
        return true;
    }

    protected function doFilter(IMAP\Query\WhereQuery $query) : array
    {
        $emails = $query->get()->all();

        return $this->filters ? array_filter($emails,  [$this, "filterCallback"]) : $emails;
    }

    protected function getType() : string
    {
        return "attachment";
    }

}
