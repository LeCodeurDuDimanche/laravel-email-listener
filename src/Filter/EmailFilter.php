<?php
namespace lecodeurdudimanche\EmailListener\Filter;

use Webklex\IMAP;

// Dates does not include time
// The text searches are not exact match searches

class EmailFilter extends Filter{

    protected $attachmentFilter;

    public function __construct()
    {
        parent::__construct();
        $this->attachmentFilter = new AttachmentFilter();
    }

    public function getAttachmentFilter() : AttachmentFilter
    {
        return $this->attachmentFilter;
    }

    public function attachments($callback) : EmailFilter
    {
        if (! is_callable($callback))
            throw new \InvalidArgumentException("Argument 1 of method attachments must be callable");

        call_user_func($callback, $this->attachmentFilter);
        return $this;
    }

    public function getPrettyFilterName(string $filter) : string
    {
        return strpos($filter, "where") === 0 ? strtolower($filter[5]) . substr($filter, 6) : $filter;
    }

    public function fromArray(array $array) : Filter
    {
        parent::fromArray($array);
        if (array_key_exists("attachments", $array))
            $this->attachmentFilter->fromArray($array["attachments"]);
        return $this;
    }

    public function toArray() : array
    {
        $array = parent::toArray();
        $attachmentsArray = $this->attachmentFilter->toArray();
        if ($attachmentsArray['filters'])
            $array['attachments'] = $attachmentsArray;
        return $array;
    }

    public function __call(string $name, array $arguments) : EmailFilter
    {
        // If method name already begins by where, keep it like that
        if (substr($name, 0, 5) != 'where')
        {
            // Else add where in front (and capitalize first letter)
            // In order to allow for instance cc to call wherecc method
            $name = "where" . strtoupper($name[0]) . substr($name, 1);
        }

        if (! method_exists(IMAP\Query\WhereQuery::class, $name))
            throw new \BadMethodCallException("Method $name does not exist");

        $this->filters[] = ["method" => $name, "args" => $arguments];
        return $this;
    }

    protected function doFilter(IMAP\Query\WhereQuery $query) : array
    {
        foreach($this->filters as $filter)
            call_user_func_array([$query, $filter['method']], $filter['args']);

        $emails = $query->get();

        return $this->attachmentFilter->doFilter($query);
    }

    protected function getType() : string
    {
        return "email";
    }

}
