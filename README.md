Captcha
=======

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/SugiPHP/Captcha/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/SugiPHP/Captcha/?branch=master)

Image CAPTCHA in two flavours, sharing the same interface (`CaptchaInterface`):

- `CryptRndChars` - does not need sessions
- `SessionRndChars` - keeps the code in the session; no secret key or database needed

### CryptRndChars

The code from the image travels with the form in an encrypted and signed form (the *checksum*), together with
the time it was generated at. On submit the user input is checked against the checksum. Each CAPTCHA is accepted
only once - the used ones are remembered (in a database or in a cache) until they expire, which by default
happens one hour after they are generated.

Installation
------------

```bash
composer require sugiphp/captcha
```

Requires PHP 7.4+ with the GD (with FreeType support), mbstring and openssl extensions.

Usage
-----

### Without sessions

```php
use SugiPHP\Captcha\CryptRndChars;
use SugiPHP\Captcha\UsedKeysProviderPdo;

// $pdo is a PDO connection to a database having the table from schema.sql
$captcha = new CryptRndChars(new UsedKeysProviderPdo($pdo), $secret);

// showing the form
$captcha->create();
$image = "data:image/jpeg;base64," . base64_encode($captcha->getImage()); // <img src="...">
$checksum = $captcha->getChecksum();                                      // <input type="hidden" ...>

// processing the form
if (!$captcha->verify($_POST["captcha"], $_POST["checksum"])) {
    echo $captcha->getLastErrorMessage();
}
```

`$secret` is a private string, at least 32 characters long. A full working form is in [examples/index.php](examples/index.php).

### With sessions

```php
use SugiPHP\Captcha\SessionRndChars;

session_start();
$captcha = new SessionRndChars();

// showing and processing the form is the same as above
```

Here the checksum is just a random token pointing to the code in the session, so several forms (browser tabs)
can be opened at the same time. Each CAPTCHA can be checked only once, no matter if the answer is right or wrong.
A full working form is in [examples/session.php](examples/session.php).

If the application does not use `$_SESSION` directly, pass the array to work with as a second parameter:
`new SessionRndChars($config, $sessionData)`.

### Used codes storage (CryptRndChars)

- `UsedKeysProviderPdo` - any PDO connection. Create the table from [schema.sql](schema.sql).
- `UsedKeysProviderCache` - any PSR-6 cache pool (`composer require psr/cache` and an implementation).
- or your own `UsedKeysProviderInterface` implementation.

### Configuration

The configuration is an array (third constructor parameter of `CryptRndChars`, first of `SessionRndChars`) with any of:

| Option     | Default            | Description                                   |
|------------|--------------------|-----------------------------------------------|
| `width`    | `120`              | width of the image in pixels                  |
| `height`   | `40`               | height of the image in pixels                 |
| `length`   | `5`                | number of chars in the code                   |
| `charset`  | `0123456789`       | chars used for the code (UTF-8)               |
| `font`     | bundled TTF font   | absolute path to a TTF font                   |
| `quality`  | `15`               | JPEG quality                                  |
| `lifetime` | `3600`             | seconds the code is valid for                 |
| `cipher`   | `aes-256-cbc`      | `CryptRndChars` only: openssl cipher used to encrypt the code |
| `session_key` | `sugiphp_captcha` | `SessionRndChars` only: where in the session the codes are kept |
| `max_pending` | `10`           | `SessionRndChars` only: how many not checked codes are kept in the session |

Upgrading from 1.x
------------------

Version 2 is the bridge between 1.x and 3.x: it has both the old `SugiPHP\Captcha\Captcha` class and the new API,
so the upgrade can be done in steps.

1. Update to `^2.0` (PHP 7.4+ is required). Existing code continues to work, but creating a `Captcha` instance
   triggers an `E_USER_DEPRECATED` error.
2. Replace the usages of `Captcha` with `SessionRndChars` (the closest to what 1.x does - the code stays in the
   session) or with `CryptRndChars`:

   | 1.x (`Captcha`)                            | 2.x / 3.x                                                |
   |--------------------------------------------|----------------------------------------------------------|
   | `new Captcha($config)`                     | `new SessionRndChars($config)` or `new CryptRndChars($provider, $secret, $config)` |
   | `build()`                                  | `create()`                                               |
   | `get()`                                    | `getImage()`                                             |
   | `base64()`                                 | `"data:image/jpeg;base64," . base64_encode(getImage())`  |
   | `output()`                                 | `header("Content-Type: image/jpeg"); echo getImage();`   |
   | `$_SESSION["captcha"] = getCode()`         | hidden form field with `getChecksum()`                   |
   | `$_SESSION["captcha"] === $input`          | `verify($input, $checksum)`                              |
   | `setCode()`, the `code` option             | not available - the code is always random                |

   Note that the default `charset` is now digits only. Pass `"charset" => "abcdefhjkmnprstuvwxyz23456789"` to keep the old one.
3. When there are no more deprecation messages update to `^3.0`, where the `Captcha` class is removed.

The path to the bundled font changed from `captcha.ttf` to `src/captcha.ttf`.
