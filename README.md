# Easyjob API

Adds integration for the Easyjob.

# Projects using this module:
- top-events: https://github.com/iqual-ch/top-events-sw-project

## Installation and basic usage

* Install the module via composer: composer require iqual/easyjob_api
* run drush en easyjob_api in terminal to activate it.
* Configure the API under /admin/config/services/easyjob-api

## Local Development

This API has an IP restriction.
Currently only the docker-dev.iqual.ch IP is whitelisted.

To be able to test it locally you have to follow these steps:

*  if you don't have it already download / generate the ssh key rsa file for the docker-dev.iqual.ch server and save it on your local machine
* run this command in your host terminal
`sudo ssh -L 0.0.0.0:8008:213.221.221.246:8008 -i /path/to/ssk/key root@docker-dev.iqual.ch`
* when prompted past the ssh dev passphrase (in lastpass)

You should now be able to test the api in postman using http://localhost:8008 as host

To make it work inside your container, update your settings.local.php with the following:

```
$config['easyjob_api.settings']['base_url'] = 'http://host.docker.internal:8008';
$config['easyjob_api.settings']['username'] = '%USERNAME_IN_LASTPASS%';
$config['easyjob_api.settings']['password'] = '%PASSWORD_IN_LASTPASS%';
```

The import use the last import date to determine which products have been edited since then.
To import all products again set the timestamp to 0 in the api configuration or overwrite it in your settings.local.php file
```
$config['easyjob_api.settings']['timestamp'] = 0; 
```

## Troubleshooting
If the initial import crashes due to mysql timeout or memory issue, you can try to add the following in your settings.local.php file
```
if (PHP_SAPI === 'cli') {
  ini_set('memory_limit', '1024M');
  $databases['default']['default']['init_commands'][] = 'SET wait_timeout = 4800';
}
```