<?php
namespace lecodeurdudimanche\EmailListener\Filter;

use Webklex\IMAP;

abstract class Filter {

    protected $filters;

    public function __construct()
    {
        $this->filters = [];
    }

    protected abstract function getType() : string;
    protected abstract function doFilter(IMAP\Query\WhereQuery $query) : array;

    protected function getPrettyFilterName(string $filter) : string
    {
        return $filter;
    }

    public function fromArray(array $array) : Filter
    {
        if (! array_key_exists('filters', $array) || ! is_array($array['filters']))
            throw new CorruptedDataException("Key 'filters' must be an array");

        $filters = $array['filters'];
        foreach($filters as $filter)
        {
            if (!is_array($filter) || ! array_key_exists('args', $filter) || ! array_key_exists('method', $filter))
                throw new CorruptedDataException("A filter in the filters array must be an array containing both 'method' and 'args' key");

            $args = $filter['args'];
            $this->__call($filter['method'], is_array($args) ? $args : [$args]);
        }

        return $this;
    }

    public function toArray() : array
    {
        $filters = array_map(function($filter) {
            $filter["method"] = $this->getPrettyFilterName($filter["method"]);
            if (count($filter["args"]) == 1)
                $filter["args"] = $filter["args"][0];
            return $filter;
        }, $this->filters);
        return ["type" => $this->getType(), "filters" => $filters];
    }

    public static function load(string $name, string $path = null) : ?Filter
    {
        if ($path === null)
        {
            $path = config("filter.file");
            if (! $path)
                throw new\InvalidArgumentException("Could not find a filters file");
        }


        $data = json_decode(file_get_contents($path), true);

        if ($data == null)
            throw CorruptedDataException("The file $path contains invalid JSON data");

        if (! array_key_exists($name, $data))
            return null;

        $filterData = $data[$name];
        switch($filterData['type']){
        case 'email':
            $filter = new EmailFilter();
            break;
        case 'attachment':
            $filter = new AttachmentFilter();
            break;
        default:
            throw new CorruptedDataException("Invalid filter type $filterData[type]");
        }

        return $filter->fromArray($filterData);
    }

    public function saveAs(string $path, string $name) : Filter
    {
        $data = json_decode(file_get_contents($path, true));
        $data[$name] = $this->toArray();

        file_put_contents($path, json_encode($data, JSON_PRETTY_FORMAT));

        return $this;
    }

    public function run(IMAP\Folder $inbox) : array
    {
        $query = $inbox->query(null)->all();

        /* TODO: save email ids in filesystem or database in order to remember
        *        which emails have been already treated
        */
        $query->leaveUnread()->unseen();

        return $this->doFilter($query);
    }
}
