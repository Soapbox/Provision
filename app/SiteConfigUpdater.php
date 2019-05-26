<?php

namespace App;

class SiteConfigUpdater
{
    private function readConfigFor(string $service): string
    {
        $config = config_path("sites/$service.php");
        return file_get_contents($config);
    }

    private function parseServerTypeConfig(string $config, string $serverType): string
    {
        $matches = [];
        if (preg_match("/(\s+)'$serverType' => \[\n.*?\n\g1\]/s", $config, $matches)) {
            return $matches[0];
        }

        throw new \Exception('Replace me with a real exception.');
    }

    private function parseServiceList(string $config): string
    {
        $matches = [];
        if (preg_match('/(\s+)\'servers\' => \[\n(.*\n)*?\g1\]/', $config, $matches)) {
            return $matches[0];
        }

        throw new \Exception('Replace me with a real exception.');
    }

    private function addServerToList(string $serverList, string $server): string
    {
        $servers = explode("\n", $serverList);
        $end = $servers[count($servers) - 1];

        $indent = strlen($end) - strlen(ltrim($end, ' ')) + 4;
        $line = str_repeat(' ', $indent) . "'$server',";

        array_splice($servers, count($servers) - 1, 0, $line);

        return implode("\n", $servers);
    }

    public function addServerToConfig(string $service, string $serverType, string $server): void
    {
        $file = config_path("sites/$service.php");

        $config = file_get_contents($file);
        $serverTypeConfig = $this->parseServerTypeConfig($config, $serverType);

        $serverList = $this->parseServiceList($serverTypeConfig);
        $newServerList = $this->addServerToList($serverList, $server);

        $newServerTypeConfig = str_replace($serverList, $newServerList, $serverTypeConfig);
        $newConfig = str_replace($serverTypeConfig, $newServerTypeConfig, $config);

        file_put_contents($file, $newConfig);
    }
}
