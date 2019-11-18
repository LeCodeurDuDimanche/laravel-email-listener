<?php
namespace lecodeurdudimanche\EmailListener;

use Webklex\IMAP\Client;
use Webklex\IMAP\Message;
use Webklex\IMAP\Facades\Client as ClientFacade;
use lecodeurdudimanche\EmailListener\Filter\Filter;

class EmailListener {

    protected $actions;
    // Keep a trace of loaded filters in orded to be able to keep unused filters
    // if the save() method is called
    protected $loadedFilters;

    public function __construct()
    {
        $this->actions = [];
        $this->loadedFilters = [];
    }

    public function addActions(array $actions) : EmailListener
    {
        foreach ($actions as $action)
        {
            if (! is_array($action) || ! array_key_exists('client', $action) || ! array_key_exists('action', $action))
                throw new \InvalidArgumentException("An action must be an array containing both keys 'client' and 'action'.");
            $this->addActionImpl($action['client'], $action['action']);
        }

        return $this;
    }

    public function addActionsForClient(string $client, array $actions) : EmailListener
    {
        foreach ($actions as $action)
            $this->addActionImpl($client, $action);

        return $this;
    }


    public function addAction(string $client, $action, $filter = null) : EmailListener
    {
        if (($action instanceof Action) !== ($filter === null))
            throw new \InvalidArgumentException("You must either provide an Action object and a null filter or a callback and a filter to addAction() method");

        if ($action instanceof Action && $filter == null)
            $this->addActionImpl($client, $action);
        else {
            $callback = $action;
            $this->addActionArrayImpl(compact("client", "filter", "callback"));
        }

        return $this;
    }
    protected function addActionArrayImpl(array $data)
    {
        if (! isset($data['client']) ||
            ! isset($data['filter']) ||
            ! isset($data['callback']))
            throw new \InvalidArgumentException("Missing parameter");

        if (! is_string($data['client']) || strpos($data['client'], '.') !== FALSE || ! is_array(config("imap.accounts.$data[client]")))
            throw new \InvalidArgumentException("Client must be a valid IMAP Client name");

        $action = new Action($data['filter'], $data['callback']);

        $this->addActionImpl($data['client'], $action);
    }

    protected function addActionImpl(string $client, Action $action)
    {
        if (! array_key_exists($client, $this->actions))
            $this->actions[$client] = [];

        $this->actions[$client][] = $action;
    }

    public function load(string $path = null) : EmailListener
    {
        $path = Config::getPath($path);
        $data = json_decode(file_get_contents($path), true);
        if (!$data)
            throw new CorruptedDataException("The file $path is either empty or corrupted");
        if (! is_array($data) || !array_key_exists("actions", $data) || ! is_array($data['actions']))
            throw new CorruptedDataException("The actions key must be present and its value must be an array");

        // Load the filters of the file, keeping the others
        $this->loadedFilters =  array_merge($this->loadedFilters, $data['filters']);
        // Retrieve the names of the filters
        $names = array_keys($this->loadedFilters);
        // Create the filters from the filter data contained in the file, adding the names
        $this->loadedFilters = array_map([Filter::class, 'createFromArray'], $this->loadedFilters, $names);
        // Put the names as filter key
        $this->loadedFilters = array_combine($names, $this->loadedFilters);

        foreach($data['actions'] as $action)
        {
            if (! is_array($action) ||
                ! array_key_exists("client", $action) || ! is_string($action['client']) ||
                ! array_key_exists("filter", $action) ||
                ! array_key_exists("callback", $action) || ! is_string($action['callback'])
                )
                throw new CorruptedDataException("An action must be an array containing a client identifier, a filter and a string callback");

            if (! array_key_exists($action['filter'], $this->loadedFilters))
                throw new CorruptedDataException("Filter $action[filter] is not present in file and is not loaded (file is $path)");

            $action['filter'] = $this->loadedFilters[$action['filter']];

            $this->addActionArrayImpl($action);
        }

        return $this;
    }

    public function toArray() : array
    {
        $actions = [];
        $filters = [];
        
        $objectFilters = array_map(function($f) {return $f->toArray();}, $this->loadedFilters);
        foreach ($objectFilters as $filter)
            $filters = array_merge($filters, $filter);

        foreach($this->actions as $client => $clientActions)
        {
            foreach($clientActions as $action)
            {
                $callback = $action->getCallback();

                if (! is_array($callback))
                    throw new \InvalidArgumentException("Cannot serialize EmailListener if containing closures callbacks");

                $filterName = $action->getFilter()->getName();
                $class = is_string($callback[0]) ? $callback[0]  : get_class($callback[0]);

                $actions[] = [
                    "client" => $client,
                    "filter" => $filterName,
                    "callback" => $class . "@" . $callback[1]
                ];
                if (array_search($filterName, $filters) === FALSE)
                    $filters = array_merge($filters, $action->getFilter()->toArray());
            }
        }
        return compact("actions", "filters");
    }

    public function save(string $path = null) : EmailListener
    {
        $path = Config::getPath($path);

        $json = json_encode($this->toArray(), JSON_PRETTY_PRINT);

        file_put_contents($path, $json);
        return $this;
    }

    public function run() : int
    {
        $clientsOk = 0;
        foreach($this->actions as $client => $clientData)
        {
            try {
                $inbox = ClientFacade::account($client)->getFolder("INBOX");

                foreach ($clientData as $action)
                {
                    $filteredEmails = $action->getFilter()->run($inbox);
                    foreach($filteredEmails as $email)
                    {
                        $ret = $action->call($email);
                        if ($ret)
                            $email->setFlag('Seen');
                    }
                }
                $clientsOk++;
            } catch (ConnectionFailedException $e)
            {
            }
        }
        return $clientsOk;
    }

    public function __invoke() : void
    {
        $this->load();
        $this->run();
    }
}
