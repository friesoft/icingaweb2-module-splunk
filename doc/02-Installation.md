# Installation

## Requirements

* Icinga Web 2 >= 2.4.2
* PHP version 5.6.x or 7.x
* php-curl
* Splunk >= 5.x

## Installation
As with any Icinga Web 2 module, installation is pretty straight-forward. You just have to drop the module into the
`/usr/share/icingaweb2/mdouels/splunk` directory. Please note that the directory name **must** be `splunk`
and nothing else. If you want to use a different directory, make sure it is within the module path of Icinga Web 2.

```shell
git clone https://github.com/icinga/icingaweb2-module-splunk.git /usr/share/icingaweb2/modules/splunk
```

The module can be enabled through the web interface (`Configuration -> Modules -> splunk`) or via the CLI:

```shell
icingacli module enable splunk
```

## Configuration
Before the Splunk mdoule can display anything, you have to configure at least one instance and one event type.
Read the [Configuration](03-Configuration.md) section for details.