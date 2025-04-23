# SwagStoreAPICache

## Experimental!

This plugin is an experimental implementation of a reverse-proxy-cache for the Store API.

## How to use it:

* Install the plugin
* Change fastly config based on the config provided in `src/Resources/fastly_config` (the changes from the base config are marked with `# Cache Store-API requests`)
* Adjust the api client(s) to always send the `sw-cache-hash` cookie they get in the response with the following requests (otherwise wrong data might be shown (e.g. logged-in state))