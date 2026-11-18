Changelog
=========

3.1.0
-----

- Added `SimpleCacheUsedKeys` - keeps the used codes in a PSR-16 cache

3.0.0
-----

- Removed the deprecated `Captcha` class. Use `Session` or `Crypt` - see "Upgrading from 1.x" in the README

2.0.0
-----

Bridge release - everything from 1.x is still here, but deprecated.

- Added `Crypt` (implementing the new `CaptchaInterface`) - a CAPTCHA that needs no sessions
- Added `Session` (same interface) - keeps the code in the session, the closest replacement of `Captcha`
- Added `UsedKeysInterface` with PDO (`PdoUsedKeys`) and PSR-6 (`CacheUsedKeys`) implementations
- Deprecated the `Captcha` class - it triggers `E_USER_DEPRECATED` and will be removed in 3.0
- `Captcha` generates the code with `random_int()` instead of `mt_rand()`
- `Captcha` no longer triggers "implicit conversion from float" deprecations on PHP 8.1+
- `Captcha::output()` sends the correct `image/jpeg` content type
- PHP 7.4+ is required, as well as the gd, mbstring and openssl extensions
- The sources are moved to `src/`
- Added tests

1.0.0
-----

- `Captcha` class - image generator; the code has to be kept in the session
