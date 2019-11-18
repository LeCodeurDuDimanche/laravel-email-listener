<?php
namespace lecodeurdudimanche\EmailListener;

class Config {
    public static function getPath(string $path = null) : string
    {
        $path = $path ?? config("email-listener.config-file");
        if (! $path)
            throw new \InvalidArgumentException("Could not find a config file");
        return $path;
    }
}
