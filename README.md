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

[porto](https://github.com/nunomaduro/porto) is a dependency audit ledger for PHP. It shows you what a `composer update` is about to write into the `vendor/` directory, and lets you (or your agent) review those changes, one by one, before they land.

```
❯ composer update carbonphp/carbon-doctrine-types

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

   WARN  composer holds those bytes out of vendor/ until you record them. Then run `composer install`.
```

Composer stopped before it wrote anything. Read the diffs, record them with `porto trust carbonphp/carbon-doctrine-types`, then run `composer install`: the bytes that land are the bytes you read, and your build passes.

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

This command asks no questions, and it reaches the network only for a version that `composer.lock` names and `vendor/` does not hold yet. It is the baseline of a project that has never been audited, so review the tree in `vendor/` before you run it on a project you do not know.

<a name="auditing-your-dependencies"></a>
## Auditing Your Dependencies

Once the ledger exists, run the `audit` command whenever you want to know where you stand. It reports the packages that have no entry, the packages whose contents changed, the versions that `composer.lock` names and `vendor/` does not hold yet, and the trees in `vendor/` that disagree with `composer.lock` — worst first, with the changed paths of each one, the source of each change and the number of files the review costs you:

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
  symfony/console .................................................... v7.4.16
  hash .............................. tree-v1:6bcaeda34b1df22209a4ea023cf74641
  source ................................................................ dist
  contents ............................................... 140 files, 618.2 KB
  path ................................................ vendor/symfony/console
```

When the ledger already covers the installed version, the report stays local and instant. When the ledger holds an earlier version, porto fetches that version from Packagist and shows you the delta. If you would like to compare against some other version, you may name it using the `--from` option:

```shell
porto audit carbonphp/carbon-doctrine-types --from=3.1.0
```

<a name="reviewing-the-source-of-a-change"></a>
### Reviewing the Source of a Change

Changes arrive in four buckets, worst first: the install-time manifest, where `scripts`, `bin` and plugin classes live; the opaque artifacts that cannot be reviewed; the runtime source; and the inert files, such as tests and documentation.

Every report holds the source of the changes it shows. A delta shows the first five changed paths of each bucket and counts the paths that remain, and the `-v` option shows every one of them, on the full report or on a single package:

```shell
porto audit carbonphp/carbon-doctrine-types -v
```

Some files hold no readable source. porto tells you so, and shows you no diff of them. You may read one bucket at a time using the `--bucket` option:

```shell
porto audit laravel/pint --from=v1.30.4 --bucket=opaque
```

```
  laravel/pint ....................................................... v1.30.5
  hash .............................. tree-v1:157af76b649beca07fd5edf43dc16476
  source ................................................................ dist
  contents .................................................. 8 files, 21.5 MB
  path ................................................... vendor/laravel/pint

  delta (v1.30.4 → v1.30.5)
  identity ................................ 012d3f194967 → 157af76b649b (dist)
  compared against ....................................... your installed tree

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

  psr/container 2.0.2 .......................... whole package to review (new)
      composer would add this package to the tree
  psr/simple-cache 3.0.0 ......................... nothing to review (removed)
      composer would take this package out of the tree

   INFO  3 package(s) change. Read every change with `porto preview -v`, then run `composer update`.
```

A package that arrives for the first time has nothing to compare against, so the whole package is the review. A package that leaves takes its files with it. Every version that moves arrives as a delta, which shows the first five changed paths of each bucket. The `-v` option shows every one of them:

```shell
porto preview -v
```

The command reads the plan from the `composer` binary on your PATH, and it asks you nothing. It exits with a zero status code whenever the preview is complete, so you may put it in front of the update itself:

```shell
porto preview && composer update && porto audit
```

<a name="running-on-every-update"></a>
## Running on Every Update

porto ships a Composer plugin, so the audit runs by itself, before Composer writes a single file. Composer asks you to trust the plugin when you install porto, and you may also allow it by hand:

```json
{
    "config": {
        "allow-plugins": {
            "nunomaduro/porto": true
        }
    }
}
```

From then on, every `composer update`, `composer require` and `composer remove` stops at the review. Composer has resolved the versions and written `composer.lock`; porto fetches the archive of every version that is about to arrive, hashes it, and compares it against your ledger. Nothing reaches `vendor/` until you record it:

```
❯ composer update carbonphp/carbon-doctrine-types

  Loading composer repositories with package information
  Updating dependencies
  Writing lock file
  Installing dependencies from lock file (including require-dev)

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

  audited ........................................................ 1 / 2 (50%)

   ERROR  1 package(s) are not covered. Read every change with `composer update -v`, then record them with `porto trust`.

   WARN  composer holds those bytes out of vendor/ until you record them. Then run `composer install`.
```

Composer runs the audit, so the report asks for `composer update -v`. That option reaches the audit, and every changed path arrives under the output of Composer.

The version in the diff is the version you do not have yet. Read it, then record it. The `trust` command shows you the delta once more, and the entry it writes holds the hash of the tree that is about to arrive:

```
❯ porto trust carbonphp/carbon-doctrine-types

   INFO  Recorded carbonphp/carbon-doctrine-types 3.2.0 at ad33848c07e8.

   INFO  Run `composer install` to write those bytes to vendor/.
```

`composer install` takes the versions that are already in your lock file. The audit runs again, finds them covered, and Composer writes them:

```
❯ composer install

  Installing dependencies from lock file (including require-dev)

   INFO  All 2 packages are covered.

  Package operations: 0 installs, 1 update, 0 removals
    - Upgrading carbonphp/carbon-doctrine-types (3.1.0 => 3.2.0): Extracting archive
```

The bytes that land are the bytes you read: the hash porto recorded before the install is the hash `porto audit carbonphp/carbon-doctrine-types` reads from `vendor/` after it. If you would rather abandon the update, `git checkout composer.lock` puts your lock file back.

Two runs are not gated. A `--dry-run` writes nothing, so it is left alone. The first install of a project has no tree to audit an update against, so the plugin says as much and audits the result when the install is done:

```
porto audits an update against the installed tree. This project installs no package yet, so the audit runs after this install.
```

A project that has no ledger yet is not failed either. The plugin says so once, and leaves your update alone:

```
porto has no ledger in this project yet. Run `porto trust` to record what you trust today.
```

<a name="available-commands"></a>
## Available Commands

| Command | Function |
|---|---|
| `porto audit` | show what is unaudited, worst first, with the reviewable delta of each package, the source of each change and the count of files that each review costs, and exit non-zero when a package is ungranted, when its contents changed, or when `vendor/` disagrees with `composer.lock`; `--plan` names the operations that Composer is about to run |
| `porto audit -v` | show the same report with every changed path, and not the first 5 of a bucket |
| `porto audit <package>` | show the content hash, the count of files, the size and the reviewable delta in buckets, with the source of each change; `--from` names the version to compare from, and `--bucket` holds the delta to one bucket |
| `porto audit <package> -v` | show the same report with every changed path, and not the first 5 of a bucket |
| `porto preview` | show what the next `composer update` changes, with the reviewable delta of each package and the source of each change, before `vendor/` is touched |
| `porto preview -v` | show the same report with every changed path, and not the first 5 of a bucket |
| `porto trust` | trust each package that `porto audit` reports, at the tree on disk and at the tree of each pending install, and make the ledger |
| `porto trust <package> …` | show the delta of each package that the user names, and write the entry of each one in `porto.json` |

`porto` with no argument runs `porto audit`. `porto audit` and `porto preview` write the same report as JSON with the `--json` option.

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
