# Easyjob API

Adds integration for the Easyjob.

This is an API module only. Following modules use this to provide further functionality:

* TO UPDATE

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