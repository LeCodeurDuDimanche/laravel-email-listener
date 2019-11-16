<?php
namespace lecodeurdudimanche\EmailListener;

use Webklex\IMAP\Client;
use Webklex\IMAP\Message;

class EmailListener {

    protected $actions;

    public function __construct()
    {
        $this->actions = [];
    }

    public function addActions(array $actions) : EmailListener
    {
        foreach ($actions as $action)
            $this->addActionImpl($action);

        return $this;
    }

    public function addActionsForClient(Client $client, array $actions) : EmailListener
    {
        foreach ($actions as $action)
        {
            $action['client'] = $client;
            $this->addActionImpl($action);
        }

        return $this;
    }


    public function addAction(Client $client, $action, $filter = null) : EmailListener
    {
        if (($action instanceof Action) !== ($filter === null))
            throw \InvalidArgumentException("You must either provide an Action object and a null filter or a callback and a filter to addAction() method");

        if ($action instanceof Action && $filter == null)
            $this->addActionImpl($client, $action);
        else
            $this->addActionImpl(compact("client", "filter", "action"));

        return $this;
    }
    protected function addActionArrayImpl(array $data)
    {
        if (! isset($data['client']) ||
            ! isset($data['filter']) ||
            ! isset($data['action']))
            throw \InvalidArgumentException("Missing parameter");

        if (! $data['client'] instanceof Client)
            throw new \InvalidArgumentException("Client must be a valid IMAP Client object");

        $action = new Action($data['filter'], $data['action']);

        $this->addActionImpl($data['client'], $action);
    }

    protected function addActionImpl(Client $client, Action $action)
    {
        if (! array_key_exists($client->username, $this->actions))
            $this->actions[$client->username] = ["relatedClient" => $client, "actions" => []];

        $this->actions[$client->username]["actions"][] = $action;
    }

    public function run() : int
    {
        $clientsOk = 0;
        foreach($this->actions as $clientData)
        {
            try {
                $client = $clientData['relatedClient'];
                $client->checkConnection();
                $inbox = $client->getFolder("INBOX");

                foreach ($clientData['actions'] as $action)
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
}
