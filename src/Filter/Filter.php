<?php
namespace lecodeurdudimanche\EmailListener\Filter;

use lecodeurdudimanche\EmailListener\CorruptedDataException;
use lecodeurdudimanche\EmailListener\Config;
use Webklex\IMAP;

abstract class Filter {

    protected $filters;
    protected $name;

    private static $UID = 0;

    public function __construct()
    {
        $this->filters = [];
        $this->name = "filter".now()->format("U") . "@" . self::$UID++;
    }

    protected abstract function getType() : string;
    protected abstract function doFilter(IMAP\Query\WhereQuery $query) : array;

    protected function getPrettyFilterName(string $filter) : string
    {
        return $filter;
    }

    public function setName(string $name) : Filter
    {
        $this->name = $name;
        return $this;
    }

    public function getName() : string
    {
        return $this->name;
    }

    public static function create(string $type) : Filter
    {
        switch($type){
        case 'email':
            $filter = new EmailFilter();
            break;
        case 'attachment':
            $filter = new AttachmentFilter();
            break;
        default:
            throw new CorruptedDataException("Invalid filter type $type");
        }

        return $filter;
    }

    public static function createFromArray(array $data, string $name = null) : Filter
    {
        if (! array_key_exists('type', $data) || ! is_string($data['type']))
            throw new CorruptedDataException("The filter data must contain a field 'type' of type string");
        $filter = self::create($data['type']);

        if ($name) $filter->setName($name);
        return $filter->fromArray($data);
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
        return [$this->name => ["type" => $this->getType(), "filters" => $filters]];
    }

    protected static function validateFilterData(array $data  = null) : void
    {
        if (!$data || ! is_array($data) || ! array_key_exists("filters", $data) || ! is_array($data['filters']))
            throw new CorruptedDataException("The key 'filters' must exist and its value must be an array");
    }

    public static function load(string $name, string $path = null) : ?Filter
    {
        $path = Config::getPath($path);

        $data = json_decode(file_get_contents($path), true);

        if ($data == null)
            throw new CorruptedDataException("The file $path contains invalid JSON data");

        self::validateFilterData($data);

        if (! array_key_exists($name, $data['filters']))
            return null;

        return self::createFromArray($data['filters'][$name], $name);
    }

    public function save(string $path = null) : Filter
    {
        return $this->saveAs($path, $this->name);
    }

    public function saveAs(string $name, string $path = null) : Filter
    {
        $path = Config::getPath($path);
        $data = json_decode(file_get_contents($path, true));

        self::validateFilterData($data);

        $data['filters'][$name] = $this->toArray();

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
