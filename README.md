# Flupp CMS v.0.0.1

Flupp CMS is a lightning-fast flat file CMS

Features:
* Fast because of caching
* Easy configuration
* Configure structures
* Change content in files
* Include different themes and modules

## Requirements

Before usage check for following requirements

* .htaccess support
* file write-access for caching 


## Installation

You can either download the script by github+composer or use the download page.

```
composer update
```

After download you can deploy this script to your apache directory. To use fast caching make sure having write permissions in /system/cache directory.

## Configuration

You can configure the structure of your website via the /modules/ directory. Contents have to be stored in the /content/ directory. Make sure, that both data-directories have the same structure.
The theme you can select from /themes/ directory via /config.yaml and make own extensions in /theme directory.

## Documentation

See full documentation at website [under construction]

## Third party

Using:
* [slim/slim]
* [erusev/parsedown]
* [mustangostang/spyc]
* [crodas/Haanga]
* [christianklisch/phpcache]
* [uikit/uikit]

## Contributors

* Christian Klisch http://www.christian-klisch.de


## Copyright and license

Copyright 2014 Christian Klisch, released under [GPLv3](LICENSE) license.