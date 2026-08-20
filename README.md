# porto

<p>
    <a href="https://github.com/nunomaduro/porto/actions"><img src="https://github.com/nunomaduro/porto/actions/workflows/tests.yml/badge.svg" alt="Build Status"></a>
    <a href="https://packagist.org/packages/nunomaduro/porto"><img src="https://img.shields.io/packagist/dt/nunomaduro/porto" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/nunomaduro/porto"><img src="https://img.shields.io/packagist/v/nunomaduro/porto" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/nunomaduro/porto"><img src="https://img.shields.io/packagist/l/nunomaduro/porto" alt="License"></a>
    <a href="https://youtube.com/@nunomaduro?sub_confirmation=1"><img alt="YouTube Channel Subscribers" src="https://img.shields.io/youtube/channel/subscribers/UCO_hYZF2gb_CyG5sA7ArlGg?style=flat&label=youtube&color=brightgreen"></a>
</p>

<a name="introduction"></a>
## Introduction

[porto](https://github.com/nunomaduro/porto) is a dependency audit for PHP. It shows you what a `composer update` is about to put into the `vendor/` directory, and lets you **or your agent** review those changes, one by one, before they land.

```
❯ composer update

  to review (1, worst first)

  carbonphp/carbon-doctrine-types 3.1.0 → 3.2.0  2 files to review (delta from 3.1.0)
      composer would install these bytes; you trust 3.1.0  ·  8.0 KB

  runtime source (2)
    ~ src/Carbon/Doctrine/DateTimeImmutableType.php
      @@ -17,7 +17,7 @@
           /**
            * @SuppressWarnings(PHPMD.UnusedFormalParameter)
            */
      -    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?DateTimeImmutable
      +    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?CarbonImmutable
           {
               return $this->doConvertToPHPValue($value);
           }

    ~ src/Carbon/Doctrine/DateTimeType.php
      @@ -17,7 +17,7 @@
           /**
            * @SuppressWarnings(PHPMD.UnusedFormalParameter)
            */
      -    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?DateTime
      +    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Carbon
           {
               return $this->doConvertToPHPValue($value);
           }


  audited .................................................. 124 / 125 (99.2%)

   ERROR  1 package(s) are not covered. Read every change with `composer update -v`, then record them with `porto trust`.

```

<a name="installation"></a>
## Installation

> **Requires [PHP 8.3+](https://php.net/releases/)**.

You may install porto into your project via the Composer package manager:

```shell
composer require nunomaduro/porto --dev
```

By default, porto commands are invoked using the `./vendor/bin/porto` script that is included with the package:

```shell
./vendor/bin/porto audit
```

<a name="trusting-your-dependencies"></a>
## Trusting Your Dependencies

Before porto can show you what changed, it needs to know what you trust today. The `trust` command lists every installed package with the reason it needs an entry, records the tree on disk, and writes the `porto.json` trust file:

```shell
porto trust
```

```
  to trust (125)

  brianium/paratest v7.24.0 (dev) ........ no entry; this tree is 3629153db155
  brick/math 0.18.0 ...................... no entry; this tree is 2874e68aa900
  carbonphp/carbon-doctrine-types 3.2.0 .. no entry; this tree is ad33848c07e8
  …

   INFO  Trusted 125 package(s), and wrote porto.json.
```

<a name="auditing-your-dependencies"></a>
## Auditing Your Dependencies

Once the trust file exists, run the `audit` command whenever you want to know where you stand. It reports the packages that have no entry:

```shell
porto audit

   INFO  All 125 packages are covered.

```

<a name="auditing-a-single-package"></a>
### Auditing a Single Package

You may audit one package by passing its name to the `audit` command:

```shell
porto audit symfony/console
```

```
  symfony/console .................................................... v7.4.16
  hash .............................. tree-v1:6bcaeda34b1df22209a4ea023cf74641
  source ................................................................ dist
  contents ............................................... 140 files, 618.2 KB
  path ................................................ vendor/symfony/console
```

When the trust file already covers the installed version, the report stays local and instant. When the trust file holds an earlier version, porto fetches that version from Packagist and shows you the delta. If you would like to compare against some other version, you may name it using the `--from` option:

```shell
porto audit carbonphp/carbon-doctrine-types --from=3.1.0
```

<a name="the-trust-file"></a>
## The Trust File

The trust file lives in `porto.json`, at the root of your project, next to `composer.json`. You should commit it. It holds one entry for each package: the version you reviewed, and the hash of the tree you reviewed.

```json
{
    "schema": 3,
    "require": {
        "carbonphp/carbon-doctrine-types": {
            "version": "3.2.0",
            "hash": "tree-v1:ad33848c07e8c0d58a0f9011341684a2"
        }
    },
    "require-dev": {
        "brianium/paratest": {
            "version": "v7.24.0",
            "hash": "tree-v1:3629153db155b72b307e76e1d79c0b30"
        }
    }
}
```

<a name="continuous-integration"></a>
## Continuous Integration

Your build audits your dependencies the moment it installs them. porto ships a Composer plugin, and the plugin runs the audit after every `composer install`, and again before `composer update` writes anything into `vendor/`. There is no step to add.

## Follow Nuno

- Follow the creator Nuno Maduro:
    - YouTube: **[youtube.com/@nunomaduro](https://youtube.com/@nunomaduro)** — Videos every week
    - Twitch: **[twitch.tv/nunomaduro](https://twitch.tv/nunomaduro)** — Live coding on Mondays, Wednesdays, and Fridays at 9PM UTC
    - Twitter / X: **[x.com/enunomaduro](https://x.com/enunomaduro)**
    - LinkedIn: **[linkedin.com/in/nunomaduro](https://www.linkedin.com/in/nunomaduro)**
    - Instagram: **[instagram.com/enunomaduro](https://www.instagram.com/enunomaduro)**
    - Tiktok: **[tiktok.com/@enunomaduro](https://www.tiktok.com/@enunomaduro)**

## License

**porto** was created by **[Nuno Maduro](https://twitter.com/enunomaduro)** under the **[MIT license](https://opensource.org/licenses/MIT)**.
