# Server Configuration
To configure the server provisioning script, you will need to create/modify configurations in the `config/servers` directory.

The directory is structured as
```
servers
    └ {service-name}
        └ {server-type}.php
```
Where `{service-name}` is some name given to the service. For exmaple, `soapbox-core` could be the service name given to the servers that will run API and the frontend. `{server-type}` is the type of server, for example `web` or `worker`.

## Configuration File
### Root Object
```php
return [
    'config' => [],
    'network' => [],
    'scripts' => [],
    'sites' => [],
    'tags' => [],
];
```

| Field   | Type                                          | Description                                                                                        |
| ------- | --------------------------------------------- | -------------------------------------------------------------------------------------------------- |
| config  | [Server Configuration](#server-configuration) | This contains the configuration for creating the server through forge                              |
| network | Array of strings                              | This contains an array of server names that the new server should be able to communicate with.     |
| scripts | Array of [Script]($script) objects            | This contains the configuration for which scripts should be run after provisioning the server      |
| sites   | array of [Site]($site) objects                | This contains the configuration for the sites that should be added to the server                   |
| tags    | Array or key-value pairs                      | The keys are the tag names and the values are the tag values. The keys and values must be strings. |

### <a name="server-configuration"></a> Server Configuration
```php
'config' => [
    'database-type' => DatabaseTypes::NONE,
    'name' => 'soapbox-web',
    'php-version' => PHPVersions::PHP72,
    'region' => Regions::N_CALIFORNIA,
    'size' => ServerSizes::T3_SMALL,
]
```

| Field         | Type   | Description                                                                                                                            |
| ------------- | ------ | -------------------------------------------------------------------------------------------------------------------------------------- |
| database-type | string | This is the database type to provision with the server. Check `App\Forge\Constants\DatabaseTypes` for possible values.                 |
| name          | string | The name prefix for the server. This will be appended with an auto incrementing number.                                                |
| php-version   | string | The version of php to use. Check `\App\Forge\Constants\PHPVersions` for possible values.                                               |
| region        | string | The region to create the server in. Our stack should be in North California. Check `\App\Forge\Constants\Regions` for possible values. |
| size          | string | The size of the server. Check `\App\Forge\Constants\ServerSizes` for possible values.                                                  |


### <a name="script"></a> Script
```PHP
'scripts' => [
    [
        'script' => 'install-datadog-agent.sh',
        'arguments' => [
            'key' => config('services.datadog.key'),
        ],
    ],
]
```
| Field     | Type                     | Description                                                                                                                                                                                        |
| --------- | ------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| script    | String                   | The file name for the script in the `resources/scripts` directory.                                                                                                                                 |
| arguments | Array of key-value pairs | This is an array of arguments for the script. The key will correspond to a placeholder in the script, in the form of `{{key}}`. The value is the value that the placeholder will be replaced with. |

### <a name="sites"></a> Site
```PHP
'sites' => [
    [
        'config' => [],
        'nginx' => 'soapbox-api-nginx',
        'scripts' => [],
    ],
]
```
| Field   | Type                                      | Description                                                                                   |
| ------- | ----------------------------------------- | --------------------------------------------------------------------------------------------- |
| config  | [Site Configuration](#site-configuration) | This contains the configuration for creating the site through forge                           |
| nginx   | String or null                            | The name of the nginx file, in the `resources/nginx` directory.                               |
| scripts | Array of [Script]($script) objects        | This contains the configuration for which scripts should be run after provisioning the server |

### <a name="sites"></a> Site Configuration
```PHP
'config' => [
    'domain' => 'soapboxhq.com',
    'type' => SiteTypes::PHP,
    'aliases' => [
        'goodtalk.soapboxhq.com',
    ],
    'directory' => '/public/current',
    'wildcards' => false,
]
```
| Field     | Type             | Description                                                                       |
| --------- | ---------------- | --------------------------------------------------------------------------------- |
| domain    | String           | The domain for this site. This is esentially the URL of the site.                 |
| type      | String           | The type of the site. Check `\App\Forge\Constants\SiteTypes` for available types. |
| aliases   | Array of strings | An array of domains that this site is an alias for.                               |
| directory | String           | The path to where the index.php file will exist.                                  |
| wildcards | Boolean          | True if the domain is a wildcard subdomain, otherwise false.                      |
