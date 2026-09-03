# Allpaypayz SDK bundle

This directory is where the bundled PHP SDK (`allpaypayz/sdk`) and its composer
dependencies are installed.

Run from the extension root:

```bash
cd upload/system/library/allpaypayz
composer require allpaypayz/sdk guzzlehttp/guzzle
```

The extension's catalog controller looks for `vendor/autoload.php` here on
every request.
