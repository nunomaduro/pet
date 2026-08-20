# porto

<p>
    <a href="https://github.com/nunomaduro/porto/actions"><img src="https://github.com/nunomaduro/porto/actions/workflows/tests.yml/badge.svg" alt="Build Status"></a>
    <a href="https://packagist.org/packages/nunomaduro/porto"><img src="https://img.shields.io/packagist/dt/nunomaduro/porto" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/nunomaduro/porto"><img src="https://img.shields.io/packagist/v/nunomaduro/porto" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/nunomaduro/porto"><img src="https://img.shields.io/packagist/l/nunomaduro/porto" alt="License"></a>
    <a href="https://youtube.com/@nunomaduro?sub_confirmation=1"><img alt="YouTube Channel Subscribers" src="https://img.shields.io/youtube/channel/subscribers/UCO_hYZF2gb_CyG5sA7ArlGg?style=flat&label=youtube&color=brightgreen"></a>
</p>

- [Introduction](#introduction)
- [Installation](#installation)
- [Trusting Your Dependencies](#trusting-your-dependencies)
- [Auditing Your Dependencies](#auditing-your-dependencies)
    - [Auditing a Single Package](#auditing-a-single-package)
    - [Reviewing the Source of a Change](#reviewing-the-source-of-a-change)
    - [Recording a Review](#recording-a-review)
- [Previewing an Update](#previewing-an-update)
- [Running on Every Update](#running-on-every-update)
- [Available Commands](#available-commands)
- [The Ledger](#the-ledger)
- [Continuous Integration](#continuous-integration)
    - [GitHub Actions](#github-actions)

<a name="introduction"></a>
## Introduction

[porto](https://github.com/nunomaduro/porto) is a dependency audit ledger for PHP. It shows you what a `composer update` changed in the `vendor/` directory, and lets you (or your agent) review those changes, one by one.

```
❯ composer update
❯ porto audit -v

  to review (1, worst first)

  carbonphp/carbon-doctrine-types 3.2.0 . 2 files to review (delta from 3.1.0)
      3.1.0 was trusted, 3.2.0 is installed  ·  8.0 KB

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

   ERROR  1 package(s) are not covered. Record them with `porto trust`.
```

Read the diffs, then record them with `porto trust carbonphp/carbon-doctrine-types`. The next `porto audit` is green, and your build passes.

<a name="installation"></a>
## Installation

> **Requires [PHP 8.3+](https://php.net/releases/)**.

You may install porto into your project via the Composer package manager:

```shell
composer require nunomaduro/porto --dev
```

By default, porto commands are invoked using the `vendor/bin/porto` script that is included with the package:

```shell
./vendor/bin/porto audit
```

The examples below call `porto` for brevity. If you would like to type `porto` as well, you may configure a shell alias:

```shell
alias porto='./vendor/bin/porto'
```

<a name="trusting-your-dependencies"></a>
## Trusting Your Dependencies

Before porto can show you what changed, it needs to know what you trust today. The `trust` command lists every installed package with the reason it needs an entry, records the tree on disk, and writes the `porto.json` ledger:

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

This command asks no questions and reaches no network. It is the baseline of a project that has never been audited, so review the tree in `vendor/` before you run it on a project you do not know.

<a name="auditing-your-dependencies"></a>
## Auditing Your Dependencies

Once the ledger exists, run the `audit` command after every `composer update`. It reports the packages that have no entry, the packages whose contents changed, and the trees in `vendor/` that disagree with `composer.lock` — worst first, with the changed paths of each one and the number of files the review costs you:

```shell
porto audit
```

```
   INFO  All 125 packages are covered.
```

The command exits with a non-zero status code while a package is unread, so it also serves as the gate of your test suite and your CI job.

<a name="auditing-a-single-package"></a>
### Auditing a Single Package

You may audit one package by passing its name to the `audit` command:

```shell
porto audit symfony/console
```

```
  symfony/console ..................................................... v8.1.4
  hash .............................. tree-v1:6278de8094075df5a00680affe1bc1a0
  source ................................................................ dist
  contents ............................................... 182 files, 755.3 KB
  path ................................................ vendor/symfony/console
```

When the ledger already covers the installed version, the report stays local and instant. When the ledger holds an earlier version, porto fetches that version from Packagist and shows you the delta. If you would like to compare against some other version, you may name it using the `--from` option:

```shell
porto audit carbonphp/carbon-doctrine-types --from=3.1.0
```

<a name="reviewing-the-source-of-a-change"></a>
### Reviewing the Source of a Change

By default, a delta names up to five changed paths of each bucket, counts the paths that remain, and shows no source. You may read every path and the source of every change by providing the `-v` option, on the full report or on a single package:

```shell
porto audit carbonphp/carbon-doctrine-types -v
```

Changes arrive in four buckets, worst first: the install-time manifest, where `scripts`, `bin` and plugin classes live; the opaque artifacts that cannot be reviewed; the runtime source; and the inert files, such as tests and documentation.

Some files hold no readable source. porto tells you so, and shows you no diff of them:

```
❯ porto audit laravel/pint --from=v1.30.4 -v

  install-time manifest (1)
    ~ composer.json  require
      @@ -16,7 +16,7 @@
               }
           ],
           "require": {
      -        "php": "^8.2.0",
      +        "php": "^8.3.0",
               "ext-json": "*",
               "ext-mbstring": "*",
               "ext-tokenizer": "*",

  opaque artifact (1)  cannot be reviewed — trust and provenance only
    ~ builds/pint

   WARN  1 opaque artifact cannot be read: builds/pint.
```

<a name="recording-a-review"></a>
### Recording a Review

When the review is done, record it. You may pass every package you reviewed to the `trust` command, which shows the delta of each one and writes `porto.json` once:

```shell
porto trust carbonphp/carbon-doctrine-types laravel/pint
```

If you would like to keep a sentence next to the entries you record, you may provide the `--notes` option. The note stays in the ledger until you replace it:

```shell
porto trust carbonphp/carbon-doctrine-types --notes="Reviewed with the team."
```

<a name="previewing-an-update"></a>
## Previewing an Update

You may read the review before you take the update. The `preview` command asks Composer what the next `composer update` would do, fetches both sides of every version that moves from Packagist, and shows you the delta. The `vendor/` directory of your project stays as it is:

```shell
porto preview
```

```
  to review (3, worst first)

  carbonphp/carbon-doctrine-types 3.1.0 → 3.2.0 ............ 2 files to review
      you trust 3.1.0

  runtime source (2)
    ~ src/Carbon/Doctrine/DateTimeImmutableType.php
    ~ src/Carbon/Doctrine/DateTimeType.php

  psr/container 2.0.2 .......................... whole package to review (new)
      composer would add this package to the tree
  psr/simple-cache 3.0.0 ......................... nothing to review (removed)
      composer would take this package out of the tree

   INFO  3 package(s) change. Read the source of their changes with `porto preview -v`, then run `composer update`.
```

A package that arrives for the first time has nothing to compare against, so the whole package is the review. A package that leaves takes its files with it. Every version that moves arrives as a delta, and you may read the source of each change with the `-v` option:

```shell
porto preview -v
```

```
  to review (3, worst first)

  carbonphp/carbon-doctrine-types 3.1.0 → 3.2.0 ............ 2 files to review
      you trust 3.1.0

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
```

The command reads the plan from the `composer` binary on your PATH, and it asks you nothing. It exits with a zero status code whenever the preview is complete, so you may put it in front of the update itself:

```shell
porto preview && composer update && porto audit
```

<a name="running-on-every-update"></a>
## Running on Every Update

porto ships a Composer plugin, so the audit runs by itself. Composer asks you to trust the plugin when you install porto, and you may also allow it by hand:

```json
{
    "config": {
        "allow-plugins": {
            "nunomaduro/porto": true
        }
    }
}
```

From then on, every `composer update`, `composer require` and `composer remove` ends with the audit. The report arrives under the output of Composer, and the command exits with a non-zero status code while a package is unread:

```
❯ composer update carbonphp/carbon-doctrine-types

  Lock file operations: 0 installs, 1 update, 0 removals
    - Upgrading carbonphp/carbon-doctrine-types (3.1.0 => 3.2.0)

  to review (1, worst first)

  carbonphp/carbon-doctrine-types 3.2.0 . 2 files to review (delta from 3.1.0)
      3.1.0 was trusted, 3.2.0 is installed  ·  8.0 KB

  runtime source (2)
    ~ src/Carbon/Doctrine/DateTimeImmutableType.php
    ~ src/Carbon/Doctrine/DateTimeType.php

  audited .................................................... 75 / 76 (98.7%)

   ERROR  1 package(s) are not covered. Read the source of their changes with `composer update -v`, then record them with `porto trust`.
```

Composer runs the audit, so the report asks for `composer update -v`. That option reaches the audit, and the source of every change arrives under the output of Composer.

The update itself is already on disk when the report arrives. Nothing is undone, and nothing is blocked: the exit code is what stops the next step of your script or your pipeline. Read the changes, then record them:

```shell
porto trust carbonphp/carbon-doctrine-types
```

A project that has no ledger yet is not failed. The plugin says so once, and leaves your update alone:

```
porto has no ledger in this project yet. Run `porto trust` to record what you trust today.
```

<a name="available-commands"></a>
## Available Commands

| Command | Function |
|---|---|
| `porto audit` | show what is unaudited, worst first, with the reviewable delta of each package, the source of each change and the count of files that each review costs, and exit non-zero when a package is ungranted, when its contents changed, or when `vendor/` disagrees with `composer.lock` |
| `porto audit -v` | show the same report with every changed path, and not the first 5 of a bucket |
| `porto audit <package>` | show the content hash, the count of files, the size and the reviewable delta in buckets, with the source of each change; `--from` names the version to compare from |
| `porto audit <package> -v` | show the same report with every changed path, and not the first 5 of a bucket |
| `porto preview` | show what the next `composer update` changes, with the reviewable delta of each package and the source of each change, before `vendor/` is touched |
| `porto preview -v` | show the same report with every changed path, and not the first 5 of a bucket |
| `porto trust` | trust each installed package at the tree on disk, and make the ledger |
| `porto trust <package> …` | show the delta of each package that the user names, and write the entry of each one in `porto.json` |

`porto` with no argument runs `porto audit`.

<a name="the-ledger"></a>
## The Ledger

The ledger lives in `porto.json`, at the root of your project, next to `composer.json`. You should commit it. It holds one entry for each package: the version you reviewed, and the hash of the tree you reviewed.

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

The packages are ordered and each entry is two lines, so the `git diff` of a `composer update` is the review itself.

> [!NOTE]
> The hash covers every file of the installed tree, sorted by path. A tree installed with `--prefer-source` hashes differently from the same version installed from a dist archive, and porto says so in the report.

<a name="continuous-integration"></a>
## Continuous Integration

Because `porto audit` exits with a non-zero status code while a package is unread, you may add it to the test script of your project:

```json
{
    "scripts": {
        "test": [
            "porto audit",
            "pest"
        ]
    }
}
```

<a name="github-actions"></a>
### GitHub Actions

To audit your dependencies whenever new code is pushed to GitHub, create a `.github/workflows/audit.yml` file with the following content:

```yaml
name: Audit Dependencies

on: [push]

jobs:
  audit:
    runs-on: ubuntu-latest

    steps:
      - name: Checkout code
        uses: actions/checkout@v5

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.4

      - name: Install dependencies
        run: composer install --prefer-dist --no-interaction

      - name: Audit dependencies
        run: ./vendor/bin/porto audit
```

The job fails when a dependency arrives without a review, and names the package, what changed, and the number of files the review costs.

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
