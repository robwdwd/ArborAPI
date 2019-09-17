# ArborAPI PHP class

PHP class for Arbor/Netscout SP/Siteline API

## What is the ArborAPI PHP Class?

ArborAPI is a php class to interface with Arbor/Netscout SP deployments.

## Features

ArborAPI supports the following:

-   Support for Arbor REST API
-   Support for Arbor Web services API
-   Currently testing with Arbor SP 8.2 but should work with versions above that.

## Requirements

ArborAPI PHP Class requires the following:

-   PHP 7.0 or higher
-   ext/curl, <https://www.php.net/manual/en/book.curl.php>

## Installation

Installation is via Composer (although can install the source directly into your project.)

## Usage

Initialising the class needs a configuration array as follows.

```php
$configiration = Array (
    [ipaddress] => 10.1.1.1,                      # IP address of the leader device
    [hostname] => "sp.example.com",               # Hostname of the leader device
    [apikey] => "9isdfpiosdpfokdssadsadasd",      # Web Service API Key
    [resttoken] => "sanbdnmsduihiunckasdjskldjas" # REST API Token.
);

// Create Arbor\API instance.
//
$arborApi = new Arbor\API($configuration);

// Get all peer managed objects.
//
$arborMOList = $arborApi->getManagedObjects('family', 'peer');

// Check if there is an error and print the error.
//
if ($arborApi->hasError()) {
    echo $arborApi->errorMessage();
    exit();
} else {
    [.. do something ..]
}
```
