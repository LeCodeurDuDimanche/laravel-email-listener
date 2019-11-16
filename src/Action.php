<?php
namespace lecodeurdudimanche\EmailListener;

use Webklex\IMAP\Message;

class Action {

    protected $filter;
    protected $action;

    public function __construct($filter = null, $action = null, string $filtersFile = null)
    {
        if ($filter !== null)
            $this->filter($filter, $filtersFile);
        if ($action !== null)
            $this->callback($action);
    }

    public function callback($action) : Action
    {
        // Resolves Laravel style callables Class@method
        if (is_string($action) && $posAt = strpos($action, '@'))
            $action = [resolve(substr($action, 0, $posAt)), substr($action, $posAt + 1)];

        if (! is_callable($action))
            throw new \InvalidArgumentException("Action must be callable");

        $this->action = $action;
        return $this;
    }

    public function filter($filter, string $filtersFile = null) : Action
    {

        // If filter is a string, try to retrieve the corresponding filter in in the save file
        if (is_string($filter))
            $filter = Filter\Filter::load($filter, $filtersFile);

        if (! $filter instanceof Filter\Filter)
            throw new \InvalidArgumentException("Filter must be a valid filter name or a Filter object");

        $this->filter = $filter;
        return $this;
    }


    public function call(Message $email) : bool
    {
        if ($this->action === null || $this->filter === null)
            throw new \Exception("You must provide valid filter and action before calling call() method");

        return call_user_func_array($this->action, [$email, $this->filter]) ?? true;
    }

    public function getFilter() : ?Filter\Filter
    {
        return $this->filter;
    }

    public function getAction() : ?callable
    {
        return $this->action;
    }

}
