# The project

`porto` is a dependency audit trust file for PHP. `porto` records the bytes of each installed package that the user trusts, shows the reviewable delta between the version that the user trusted and the version that the user installed, and exits non-zero when a package in `composer.lock` is ungranted or when its bytes changed. Two users read the output: a PHP developer who reviews the dependencies of a project, and the CI job of that project.

Install the dependencies with `composer install`. Run the program with `./porto`. Run the tests with `composer test` after the user approves the implementation, and see section 4.

`porto` is a [Laravel Zero](https://laravel-zero.com) application. `App\Commands\AuditCommand` is the default command in `config/commands.php`, thus `./porto` with no argument shows what is unaudited.

| Path | Function |
|---|---|
| `app/Commands` | the commands of the CLI: the input, the output and the flags |
| `app/Providers` | the service registration of Laravel Zero |
| `app/Actions` | the tasks of the audit engine: the hash of a tree, the fetch of an archive, the delta, the audit decision, the read of `porto.json` and the write of `porto.json` |
| `app/ValueObjects` | the data of the audit engine: the tree, the package, the change, the entry and the trust file |
| `app/Enums` | the enumerations of the audit engine |
| `app/Exceptions` | the exceptions of the audit engine |
| `app/Support` | the JSON codec, the paths, the size of a file, the safe print of the text of a package and the helpers of a command |
| `bootstrap` and `config` | the configuration of Laravel Zero |
| `tests` | the tests, which Pest runs |
| `porto.json` | the trust file of this project |

Audit this project with `./porto`, and record the result in `porto.json`.

---

## 1. The reader of this file

This file is for a coding agent. No user reads it. Write an instruction that an agent obeys during its work, then stop. Do not write an introduction, a conclusion, an argument for a decision that the project made, or a sentence that no agent can obey. Give a reason only when the reason changes the next decision of the agent.

Start each rule with a verb in the imperative. Put the condition before the instruction. Give the exact path, the exact command and the exact name that the agent must use. If a rule needs a test, give the command that does the test.

The public files are different. `README.md`, the website, the release notes and the `description` in each manifest are for a user. Section 3 controls them.

---

## 2. How to write this file

Write in [ASD-STE100](https://www.asd-ste100.org) Simplified Technical English: one instruction in one sentence, the imperative, the active voice, one meaning for one word, no contraction, and no synonym for variety. The standard holds the full rules. Do not copy them here. Four items keep their exact form: a quotation from a standard, an identifier in the code, a path or a command, and a name from a different supplier.

Write GitHub flavored Markdown. Put one `#` heading in a file. Write one paragraph on one line, thus a change stays small in `git diff`. Number the sections of a long file, thus a different file can point to "section 3". Put a path, a command and a name from the code in `code font`.

Write the intention of the project in this file. Write a decision about one directory in the `AGENTS.md` file of that directory. If this file and the `AGENTS.md` file of a directory disagree, obey this file and correct the other file.

Each task teaches you a fact that these files do not hold. Write the fact in the same change, or the session ends and you lose it. A correction from the user is a rule: write it in a file before you continue the work. Replace an old rule. Do not add a second rule near it. Delete a rule that the project does not obey. Do not keep a record of what the project stopped doing, in a file or in a directory. Git holds the history.

---

## 3. How to write the public text

These rules control `README.md`, the website, the release notes, the announcement and the `description` in each manifest. Sections 1 and 2 do not control these files.

The voice is calm and certain. Say what the project does, then stop. Do not say that the project is fast, simple, powerful or intelligent. Show the command and let the reader form that opinion. Cut each superlative. Cut each sentence that a competitor can also write about itself.

Write for one person and call that person "you". Put the result first and the mechanism second. Keep the sentences short, and let one sentence stand alone when it carries the idea. A demonstration is the strongest argument: one command in a terminal is worth one paragraph of adjectives.

The care is in the small parts. The alignment of the output, the words in an error message, the space around the text on the page: the reader feels this work before the reader reads one sentence. The project is for people who enjoy their work, thus the text is warm. Put one dry joke on one page, at the most. Do not explain it.

Obey three limits. Do not promise a feature that does not exist today. Do not give a number without its source. Do not name a different product to make a comparison.

---

## 4. How to write the code

These rules control each file of source code and each manifest in this repository. Sections 1, 2 and 3 do not control these files.

Write no comment. Delete each comment that you find in a file that you change. Turn off the lint that asks for a documentation comment, because that lint asks you to say the name of an item again.

Put the intention in the code. Give each item a name that says what the item does. Give each value a type that makes a wrong value impossible. Split a long function into two functions with two names. A name and a type stay correct, and a comment does not.

Write a reason that the code cannot hold in this file, with the path of the code. Do not write it above the code.

When a library reads the text of a comment as data, such as the description of a command on a help screen, write that text in an attribute or a field of that library instead.

A tool that writes a comment into a file that it owns keeps that comment. Do not delete it: the tool fails until it writes the comment again.

Put each class under `app/` in the `App\` namespace, and choose the namespace of a class by the kind of the class. Put a class that performs a task, that reaches the network, that reads the filesystem or that runs a process in `app/Actions`, and start the name of that class with a verb: `FetchArchive`, `BuildDelta`, `AuditProject`. Put a class that carries data in `app/ValueObjects`, and give it the name of the thing that it holds. Put a small helper that many namespaces call in `app/Support`. Put each enum under `app/Enums` in the `App\Enums` namespace, and declare an enum in no other namespace.

Name the work method of an action `handle()` when the action holds one job, and keep a name for each method when the action holds more than one: `FetchArchive::handle()` fetches, and `RequestUrl` keeps `get()` and `download()`.

Use Laravel Zero, Illuminate and Symfony in `app/Commands`, `app/Providers`, `app/Actions` and `app/Support` only. `app/Enums`, `app/Exceptions` and `app/ValueObjects` hold the data of the audit engine and reach no framework: the `$core` list in `tests/Arch.php` names those three namespaces, thus a namespace that the list does not name reaches anything.

Give a class that prints outside `app/Commands` the output of the command and let it build its own components: `$this->components` is protected on `Illuminate\Console\Concerns\InteractsWithIO`, thus that class cannot read it. `App\Actions\RenderDelta` takes an `Illuminate\Console\OutputStyle` and makes an `App\Support\ControlSafeComponents` of it.

Print each text that a package holds through `App\Support\ControlSafe`, and make each components factory an `App\Support\ControlSafeComponents`. `App\Support\ControlSafeFormatter` cleans the text that `line()` and `writeln()` write, and a component reaches the formatter with its control characters already gone: Termwind parses the HTML of a component with `DOMDocument`, and libxml 2.9 deletes a control character that libxml 2.15 keeps.

Keep `platform.php` at `8.3.0` in `config` of `composer.json`. `builds/porto` holds the tree that Composer resolved, thus a tree that Composer resolves against PHP 8.4 puts Symfony 8 in the PHAR and that PHAR stops on PHP 8.3 in `vendor/composer/platform_check.php`.

Add no package to `require-dev` that requires PHP 8.4 or higher, because the pin of `platform.php` rejects it. `pestphp/pest` stays at `^4.7.8` for this reason.

Call `trim`, `ltrim` and `rtrim`, and call no `mb_trim`, `mb_ltrim` or `mb_rtrim`: PHP 8.4 added those three functions and `porto` runs on PHP 8.3. `pint.json` keeps `mb_str_functions` false, because that fixer writes them.

Do not add an entry to `require` in `composer.json`. The audit engine uses the core of PHP alone, and `laravel-zero/framework` serves `app/Commands` and `app/Providers`. A new dependency also makes a version conflict when a user installs `porto` in the tree of a Laravel application.

Point `bin` of `composer.json` at `builds/porto`, and give the dist five files: `builds/porto`, `LICENSE.md`, `composer.json`, `app/Composer/Gate.php` and `app/Composer/Plugin.php`. `.gitattributes` sets `export-ignore` on each other path. Test the shape with `git archive --format=tar $(git write-tree) | tar -t`.

Add the path to the `export-ignore` list of `.gitattributes` when you add a file or a directory at the root of the repository. A path that the list does not name goes into the dist of the next release.

Give `app/Composer` no dependency on a different namespace under `App\`. Composer reads `extra.class` of `composer.json` and fails the install when `App\Composer\Plugin` is absent, and the dist holds `app/Composer` alone. `App\Composer\Gate` owns the constant `ENVIRONMENT` for this reason, and `App\Support\Invitation` reads that constant from `Gate`.

Run no `php porto app:build`, and build no PHAR. The user builds the PHAR and commits `builds/porto`. Tell the user to build after you change a file under `app/`, `bootstrap/` or `config/`, because the dist holds the PHAR and holds no source of the application, thus a release of an old PHAR ships the code of the previous version.

Keep `exclude-dev-files` false in `box.json`. `laravel-zero/framework` is in `require-dev`, thus Box dumps an autoloader that holds no framework when that option is true, and the PHAR stops with `Class "LaravelZero\Framework\Application" not found`. The user runs `./builds/porto audit` after each build, with PHP 8.3, with PHP 8.4 and with PHP 8.5: the three runs print one report.

Name each class under `app/Exceptions` with the suffix `Exception`, and give that suffix to no other class.

Declare `abstract` on a class that a different class extends, because `pint.json` sets `final_class` and thus makes each other class final. `app/Exceptions/PortoException.php` is abstract for this reason. To signal an error that has no dedicated class, throw `App\Exceptions\FailureException`.

Treat each failure of a fetch as fatal. When a body is empty, or a call returns `false`, throw `App\Exceptions\FetchFailedException`. An error must never look like a result that holds no change. A delta that the user did not ask for is the one exception: `porto audit <package>` warns and keeps the local report when it cannot fetch the delta of the granted version, and fails when the user names a version with `--from` and the fetch fails.

Ask no question, in any command. The user records a grant with `porto trust`, thus a question asks the user to say the same thing two times, and a question that reads no terminal answers itself with its own default.

Grant no permission, and add no scan that reads the code of a package. The user trusts the bytes of a package or the user does not trust them, and a list of permissions asks the user to make a second decision that `porto` cannot prove. Report a fact of the tree instead: the hash, the count of files, the bytes, the changed paths and the source of each change.

Stop after you change a file of source code, name each file that you changed, and ask the user if the implementation is correct. Run `composer test` after the user answers yes, and run no test and no part of a test before that answer. When the user answers no, change the code and ask again. The command runs `rector process --dry-run`, `pint --test`, `./porto audit`, `phpstan analyse` and `pest --parallel`. The workflow `.github/workflows/tests.yml` runs the same steps on PHP 8.3, PHP 8.4 and PHP 8.5.

---

## 5. The commands

| Command | Function |
|---|---|
| `porto audit` | show what is unaudited, worst first, with the reviewable delta of each package, the source of each change and the count of files that each review costs, and exit non-zero when a package is ungranted, when its contents changed, or when `vendor/` disagrees with `composer.lock`; `--plan` names the operations that Composer is about to run |
| `porto audit -v` | show the same report with every changed path, and not the first 5 of a bucket |
| `porto audit <package>` | show the content hash, the count of files, the size and the reviewable delta in buckets, with the source of each change; `--from` names the version to compare from, and `--bucket` holds the delta to one bucket |
| `porto audit <package> -v` | show the same report with every changed path, and not the first 5 of a bucket |
| `porto preview` | show what the next `composer update` changes, with the reviewable delta of each package and the source of each change, before `vendor/` is touched |
| `porto preview -v` | show the same report with every changed path, and not the first 5 of a bucket |
| `porto trust` | trust each package that `porto audit` reports, at the tree on disk and at the tree of each pending install, and make the trust file |
| `porto trust <package> …` | show the delta of each package that the user names, and write the entry of each one in `porto.json` |

`porto` with no argument runs `porto audit`. `porto audit` and `porto preview` write the same report as JSON with the `--json` option.

Audit two subjects in each command: the tree that `vendor/` holds, and the tree that composer is about to write. A package that `composer.lock` names and `vendor/` holds at that version is `installed`, and `App\Actions\AuditProject` hashes the bytes on disk. A package that `composer.lock` names at a version that `vendor/` does not hold is `pending`, and the auditor fetches the dist archive of that version and hashes it. `App\Enums\PackageStatus` holds the two cases.

Read the operations of composer from the `--plan` file when the plugin gives one, and derive them from `composer.lock` against `vendor/composer/installed.json` in each other run. `App\ValueObjects\ComposerPlan::fromFile()` reads the file and `ComposerPlan::between()` derives them. `ComposerPlan::explains()` separates the two: a plan that composer wrote explains why `vendor/` disagrees with the lock file, thus `lockDiscrepancies()` reports no disagreement for a package that such a plan names. A plan that porto derived explains nothing, thus the disagreement stays an error and a job of CI that never ran `composer install` still fails.

Report a pending package that porto cannot fetch as `AuditStatus::Unknown`, and fail. A package with no dist URL, an unreachable repository and a fetch that fails each reach this case. `porto trust` refuses to record that package and names the reason: an audit that waves through the one package it could not read is worse than no gate.

Record the bytes of the pending version in `porto trust`, and not the bytes on disk. The user reviews the tree that composer is about to write, thus the entry holds the hash of that tree and the `composer install` that follows writes bytes that the trust file already covers. Tell the user to run `composer install` after a run that recorded a pending package.

Compare a pending tree against the tree in `vendor/`, and reach no network for the metadata of that comparison. `App\Actions\ResolveDelta::incoming()` takes the target package and the installed package, thus the delta costs one archive and no request to Packagist. Take the dist URL and the dist reference of the target from the operation of composer first, from the entry of `composer.lock` second, and from Packagist last.

Show the delta of `porto audit <package>` from the `--from` option when the user gives one, and from the version in `porto.json` when that version is not the installed version. Fetch nothing in each other case, thus the report of a covered package stays local and instant.

Print the source of each change in each report, and show the first 5 changed paths of each bucket. `-v` shows each changed path of each bucket, thus one command holds the review and no command points at a second command. `App\Actions\RenderDelta` counts the paths that it does not show and invites `-v`.

Print the source of each change under the path of that change, and indent it. `App\Actions\RenderDelta` owns that output, thus `porto audit` and `porto trust` print one format.

Fetch no archive for a package that `vendor/` holds in `porto trust` with no argument, and build no delta for it. That command makes the baseline of a project that holds no trust file, thus it records the bytes on disk of each package that `porto audit` reports, and a fetch of one archive for each package makes the command too slow to use. `porto trust` fetches the archive of each pending package, because the bytes of that package are not on disk and the count of those packages is the size of the update. `porto trust <package>` fetches the delta, because the user asks about one package.

Make `porto trust` with no argument show each package with its status and the reason of that status, and record each one. The command asks nothing: section 4 holds the reason.

Give `porto trust` a list of packages, thus the user records the result of one review of many packages in one run. The command records each package that the user names, shows the delta of each one, and writes `porto.json` one time.

Read each installed package in each command, and add no option that selects a part of the tree. A dev dependency runs on the machine of the developer and in the job of CI, thus it holds the same reach as a package of `require`, and a report that hides it makes the gate pass on a tree that nobody audited.

List each package that needs review, and add no option that limits the count. `porto audit` with no package is the full report, and a report that stops at a count hides the work that remains.

Reject `--from` in `porto trust` with no package, and in `porto trust` with more than one package. That option asks about one package, thus the command names the option and tells the user to give one package.

Exit non-zero from `porto audit <package>` when that package is ungranted or when its bytes changed. One rule holds for the two forms of the command, thus a script gates on one package.

Compare `vendor/` against `composer.lock` in each run of `porto audit` with no package, and add no option for it. The read of `composer.lock` costs one file, and a report of a tree that disagrees with the lock file names a version that the user did not install.

Keep this table and the command table of `README.md` equal.

---

## 6. The gate of composer and the job of CI

Make `porto audit` the gate of CI and the gate of composer: the command prints the report and exits non-zero when a package is ungranted, when its bytes changed, when porto cannot read the bytes of a pending package, or when the tree in `vendor/` disagrees with `composer.lock`. `composer test` runs the gate as the script `test:dependencies`, thus a change that breaks the gate breaks the test suite.

Run the gate of composer before composer writes `vendor/`. `App\Composer\Plugin` subscribes `InstallerEvents::PRE_OPERATIONS_EXEC`, writes the operations of the transaction to a plan file with `Gate::writePlan()`, runs `porto audit --plan=<file>`, and throws `ScriptExecutionException` when that run fails. Composer stops and writes no file of a package. The plugin keeps `ScriptEvents::POST_UPDATE_CMD` and `ScriptEvents::POST_INSTALL_CMD` too: that run reads the bytes on disk, costs no request and proves that the tree that landed is the tree that the user trusted. It also covers the first install of a project, which the gate skips.

Run the gate in `composer install` and in `composer update`, and add no step of CI for the audit. `Installer::run()` of composer calls `doUpdate()` for an update and calls `doInstall()` for an install, `doUpdate()` calls `doInstall()` too, and `doInstall()` dispatches `InstallerEvents::PRE_OPERATIONS_EXEC`, thus the two commands reach `App\Composer\Plugin::gate()`. `Application::doRun()` of composer returns the code of `ScriptExecutionException`, thus the command that the job ran exits non-zero and the job fails.

Skip the gate of composer in three cases: a run that executes no operation (`InstallerEvent::isExecutingOperations()` is false, thus `--dry-run` writes nothing), a run inside a composer that porto started (`Gate::nested()` reads `PORTO_INSIDE_COMPOSER`, thus `porto preview` does not call itself), and a project that holds no `vendor/composer/installed.json`. The third case is the first install of a project: porto audits an update against the installed tree, thus that run has no tree to audit against and the hook of `POST_UPDATE_CMD` reports the result. `Gate::firstInstallNotice()` says this to the user.

Audit the fresh checkout of a job at `ScriptEvents::POST_INSTALL_CMD`, and audit it at no earlier event. A job checks out the project and holds no `vendor/`, thus composer installs the plugin after it dispatched `PRE_OPERATIONS_EXEC` and no listener answers that event. `PluginInstaller::install()` calls `PluginManager::registerPackage()` while composer executes the operations, thus the plugin is active at `POST_INSTALL_CMD` and `App\Composer\Plugin::audit()` reads the bytes that the job installed.

Tell the user to write `nunomaduro/porto` in `config.allow-plugins` of `composer.json` and to commit that line. `PluginManager::isPluginAllowed()` asks the user about a plugin that the list does not name, and throws `PluginBlockedException` when the run reads no terminal, thus a job of CI stops before composer installs one package.

Tell the user to run `vendor/bin/porto audit` as a step of the job in three cases, because composer runs the plugin in none of them: a run with `--no-plugins`, a run with `--no-scripts`, and a run as root that reads no terminal and that holds no `COMPOSER_ALLOW_SUPERUSER=1`. `--no-scripts` keeps `PRE_OPERATIONS_EXEC` and skips `POST_INSTALL_CMD`, thus a fresh checkout gets no audit.

Install the dependencies of CI with `composer install`, and run no `composer update` in a job. `composer test` runs the gate `./porto audit`, thus a job that resolves a different tree reports a package that `porto.json` does not cover and fails on a change that the commit does not hold.

Keep the count of files to review in the gate, thus a failing job of CI names the cost of each review. The gate fetches one archive for a package that changed, and reports the whole package when that fetch fails.

---

## 7. The content identity

Compute the identity of a package from the installed tree with `App\ValueObjects\TreeHash`. Do not compute it from the metadata of Composer, because Composer records no digest of the bytes that Composer installed.

Hash the raw bytes of each file. Exclude no file. Sort the relative paths bytewise ascending. Do not normalize a line ending, a space or an encoding.

Write one line for each file in the manifest: the `sha256` of the contents, two spaces, the relative path, and one `\n`. Take the `sha256` of the manifest, and record the first 32 hexadecimal characters of it after the prefix `tree-v1:`.

Do not change the scheme `tree-v1`. Each audit in each trust file holds a hash of that scheme, thus a change to the scheme makes each audit invalid. Add a new prefix beside `tree-v1` instead.

Record no install source in the entry. A dist tree and a source tree of one version hash differently, thus the hash already separates them and a second field says the same thing again. When the hash of a tree that Composer installed with `--prefer-source` does not match the entry, `App\ValueObjects\PackageAudit` says that the tree came from `--prefer-source`. Keep that sentence: without it the user reads a byte change where the cause is the install source.

When Packagist publishes an immutable artifact with a provenance attestation, add that digest beside `tree-v1`, and keep `tree-v1`.

---

## 8. The bucket of a change

`App\Actions\ClassifyPath` puts each changed path in one case of `App\Enums\BucketType`.

| Case | The path that it matches | The reviewer |
|---|---|---|
| `InstallManifest` | `composer.json`, and thus `scripts`, `bin`, the class of a plugin and `autoload` | a person, first of all |
| `Opaque` | an extension in `OPAQUE_EXTENSIONS` of `App\Actions\ClassifyPath`, and each file that holds no readable source | nobody |
| `RuntimeSource` | a path in a non-dev `autoload` root, in `files`, in `classmap` or in `bin` | a person |
| `Inert` | each other path, for example `tests/`, `docs/`, `.github/` and a fixture | the machine |

Test `Opaque` before you test a rule that reads a path. A native extension is in no `autoload` root, thus a rule that reads a path puts native code in `Inert`.

Show `InstallManifest` first, always. A new `scripts` hook, a new plugin and a new `bin` entry appear in `composer.json`.

Tell the user that nobody can read an `Opaque` file, and that the entry accepts those bytes on the trust of the publisher alone.

Print no source of an `Opaque` change, and print the source of each other change with `-v`. A `.phar` and a `.so` file hold no readable source, thus a diff of one shows the user nothing.

---

## 9. The trust file

Keep the trust file in `porto.json` at the root of the project. Use JSON, because PHP reads JSON in its core and Composer uses JSON.

Write one entry for each package: the `version` and the `hash` of the installed tree. Put an entry of a package that Composer installed as a dependency of the project in `require`, and an entry of a dev dependency in `require-dev`. `App\ValueObjects\Grant` holds one entry.

Keep the `version` in the entry. Packagist addresses an archive by version and no package carries a digest of its dist, thus `porto audit <package>` cannot fetch the tree that the user reviewed from the hash alone.

Write one entry for each package and overwrite that entry on each `porto trust`. Keep no history of a version that the project stopped using: git holds the history, and section 2 forbids a record of what the project stopped doing.

`App\Actions\PersistTrustFile` owns each read of `porto.json` and each write of `porto.json`. Do not read that file and do not write that file in a different class. `App\ValueObjects\TrustFile` is a view on that object and touches no file.

Read the file again before each write and merge the sections, thus a writer never deletes a section that the writer does not own. `TrustFile` owns `require` and `require-dev` and writes both in full.

Order the packages of each section, thus a `git diff` of `porto.json` stays reviewable.

Keep the `notes` of the entry when the user records a package again and gives no `--notes`. A grant that the user annotated keeps that sentence until the user replaces it.

Make a `Grant` of many packages from the `App\ValueObjects\PackageAudit` that `App\Actions\AuditProject::report()` holds. That object carries the version, the hash, the dev flag, the count of files and the bytes of each package, thus a second call to `FingerprintPackage::ofPackage()` hashes the tree one more time. `App\Commands\TrustCommand` records each grant and calls `save()` one time, after the loop.

Report a package as covered when the entry holds the hash of the installed tree. `App\Enums\AuditStatus` names each other result: `Ungranted` when no entry exists, and `Changed` when the hash differs.

Raise the constant `SCHEMA` of `App\Actions\PersistTrustFile` when you change the shape of a section, and write the message that tells the user what to do. Schema 1 recorded an audit under a criterion, schema 2 recorded a list of permissions, and `PersistTrustFile` tells the user to delete the file and run `porto trust` again.

Read `porto.json` for the shape of each section. The project audits itself, thus that file shows the current shape.

---

## 10. Facts that you must not derive again

A prototype measured each item in this section against this repository on 2026-08-13.

No package carries a digest of its dist: 0 of 66 packages in `composer.lock` hold a `dist.shasum`, and an entry for a GitHub zipball holds the git reference and `"shasum": ""`. A GitHub zipball is not reproducible byte for byte, and `export-ignore` in `.gitattributes` makes the dist different from the tagged tree.

A GitHub zipball holds each file under one directory with the name `vendor-package-<sha>`. `App\Actions\ExtractZip` removes that common root. Hash no path that holds that root, or each path differs between two versions.

A GitHub zipball answers 403 to a request that carries no `User-Agent` header, and the prototype printed `runtime changes (0)` for that failure. `App\Actions\RequestUrl` sends the header, and section 4 makes the failure fatal.

`laravel/pint` ships no source in its dist. The dist holds `builds/`, `composer.json`, `LICENSE.md`, `overrides/` and `resources/`, and the psr-4 roots in `composer.json` point at directories that the dist does not hold. `builds/pint` executes, and that PHAR went from 23,815,394 bytes to 22,497,998 bytes between v1.30.4 and v1.30.5. Test a change of a rule of section 8 against this package.

`phpstan/phpstan` ships `phpstan.phar` and 29 prebuilt `.so` files under `turbo-ext/`, one for each platform and each minor version of PHP. No `.so` file is in an `autoload` root. Test a change of a rule of section 8 against this package too.

`symfony/var-dumper` changed `composer.json` alone between v7.4.14 and v7.4.15. This is the common delta.

`pestphp/pest` ships an empty file at `.temp/.gitkeep`, and Pest gives that directory to PHPUnit as the cache directory. A tree that lost that file fails the gate of CI, because the hash of the tree of a fresh install holds it. When `porto audit` reports that the bytes of this package changed, run `ls vendor/pestphp/pest/.temp` first: when the directory is absent, make the file again with `mkdir -p vendor/pestphp/pest/.temp && : > vendor/pestphp/pest/.temp/.gitkeep`, and then run `./porto trust pestphp/pest`.

Ubuntu 24.04 holds libxml 2.9.14, and the machine of the developer holds libxml 2.15. The HTML parser of libxml 2.9 deletes a character between `\x00` and `\x1f`, and the parser of libxml 2.15 keeps it. Termwind gives the HTML of a component to `DOMDocument`, thus a component that receives a raw control character prints `1.0.0[2K` on the job of CI and `1.0.0?[2K` on the machine of the developer. Clean the text before the component reads it.

Symfony 8 requires PHP `>=8.4.1`, and Pest 5 requires PHP `^8.4`, as do `pestphp/pest-plugin-phpstan` and `pestphp/pest-plugin-rector`. A resolution against PHP 8.4 holds those versions, thus the pin of `platform.php` in `composer.json` keeps Symfony 7.4 and Pest 4 in the tree and in the PHAR.

PHPStan reads the `extra.phpstan.includes` of a package through `phpstan/extension-installer`, the tree holds no `phpstan/extension-installer` and `phpstan.neon.dist` holds no `includes` section: a package that ships a rule of PHPStan thus registers nothing. Write the path of `extension.neon` of that package in `includes` when you add one.

Composer 2.10.1 writes `composer.lock` in `Installer::doUpdate()` before it calls `doInstall()`, `doInstall()` dispatches `InstallerEvents::PRE_OPERATIONS_EXEC`, and `InstallationManager::execute()` writes `vendor/` after that event. Thus a plugin that throws at that event stops a run that already rewrote the lock file and that wrote no file of a package. `InstallerEvents` holds that one case: no later event of composer precedes the write.

A prototype read the source of composer 2.10.1 for each item of this paragraph on 2026-08-20. `Installer::run()` calls `doInstall()` for `composer install` and calls `doUpdate()` for `composer update`, thus `InstallerEvents::PRE_OPERATIONS_EXEC` reaches the plugin in the two commands. `Installer::run()` guards `ScriptEvents::POST_INSTALL_CMD` and `ScriptEvents::POST_UPDATE_CMD` with the flag that `--no-scripts` clears, and guards `PRE_OPERATIONS_EXEC` with no flag, thus `--no-scripts` leaves the gate and removes the audit that reads the bytes on disk. `EventDispatcher::getListeners()` reads that same flag for a listener of the `scripts` of `composer.json` alone, thus `--no-scripts` keeps each listener of a plugin. `Application::doRun()` sets `--no-plugins` for a run as root that reads no terminal and that holds no `COMPOSER_ALLOW_SUPERUSER=1`, thus an image of Docker that runs as root needs that variable. `PluginManager::isPluginAllowed()` throws `PluginBlockedException` for a plugin that `config.allow-plugins` does not name when the run reads no terminal, and returns false with no exception when `extra.plugin-optional` of that package is true. `Application::doRun()` catches `ScriptExecutionException` and returns the code of it.

Tell the user this after the gate stops a run: record the packages with `porto trust`, then run `composer install`. The lock file already names the new versions, thus `composer install` writes them and `git checkout composer.lock` abandons them.

Composer loads a plugin from the local repository alone. `PluginManager::loadInstalledPlugins()` calls `loadRepository($repo, false, $rootPackage)` and gives the root package to the filter of allowed plugins, thus composer never activates the plugin of the root package. `composer update` in this repository runs `./porto audit` through the script `post-update-cmd` of `composer.json` and does not run `App\Composer\Plugin`. To test the plugin by hand, make a project, add this repository as a `path` repository, and require `nunomaduro/porto` in it.

The dist archive of a version and the tree that composer installs from it hash the same. A prototype trusted `psr/log` 3.0.2 from the fetched archive at `tree-v1:0f88d9371ba05c1791394f2e0a56979e` before the install, and `porto audit psr/log` read that same hash from `vendor/psr/log` after the install. `App\Actions\ExtractZip` skips a directory entry and `App\ValueObjects\Manifest` walks files alone, thus an empty directory that composer creates changes no hash. This holds for a dist install: a tree that composer clones with `--prefer-source` holds `.git` and hashes differently.

The metadata of a package is at `https://repo.packagist.org/p2/<vendor>/<name>.json`. The array `packages.<name>` holds the newest version first, and each entry holds `version` and `dist.url`. `App\Actions\FetchPackageMetadata` reads this endpoint.

Read the full tree, and add no cache for speed. A hash of 6,953 files and 91.2 MB in `vendor/` takes 0.85 s.

---

## 11. How to test a change by hand

Run `./porto audit symfony/console` two times, and compare the two hashes: the hashes are equal. Change one byte of a file of that package in `vendor/`: the hash changes.

Run `./porto audit carbonphp/carbon-doctrine-types --from=3.1.0`: the report names the two changed paths and holds the two hunks, and each hunk is under the path of its file.

Run `./porto audit laravel/pint --from=v1.30.4 --bucket=opaque`: the report shows the PHAR as an opaque artifact, holds no diff of it, and warns that nobody reads those bytes.

Delete `porto.json` and run `./porto trust`: the command lists each installed package with its status and the reason of that status, asks nothing, and writes the file. Run `./porto audit`: the command exits 0.

Turn the network off and run `./porto audit <package> --from=<version>`: the command fails with an error, and prints no delta that holds no change. Turn the network off and run `./porto audit <package>` for a package that holds a grant of an earlier version: the command warns, prints the local report and exits non-zero, because that package is not covered.

Run `./porto audit laravel/pint` while `porto.json` grants the installed version: the report holds no delta and reaches no network.

Run `./porto audit` while `porto.json` covers each installed package: the command exits 0. Add one byte to a file of one package in `vendor/` and run the command again: the command exits non-zero, reports that package as `changed` and prints the two hashes. Delete one entry of `porto.json` by hand and run the command again: the command reports that package as `ungranted`, and `./porto audit <that package>` exits non-zero too.

Run `./porto trust <package> <package>`: the command shows the delta of each one, writes the two entries, and asks nothing. Run `./porto trust <package> <package> --from=<version>`: the command fails and tells the user to give one package.

Test the gate of composer in a different project, because composer does not activate the plugin of the root package. Make a project, add this repository as a `path` repository with `symlink`, allow the plugin, require a package at an old version, and run `porto trust`. Raise the constraint of that package and run `composer update <that package>`: the command prints the delta of the version that is not installed yet, exits non-zero, writes the new version in `composer.lock` and writes no file in `vendor/`. Run `porto trust <that package>`: the entry holds the new version. Run `composer install`: the audit passes and composer extracts the archive. Run `porto audit <that package>`: the hash on disk is the hash that `porto trust` recorded before the install.

---

## 12. Out of scope

Do not write an entry of `config.policy`. The Composer plugin in `app/Composer` runs `porto audit` and writes no configuration of composer.

Do not add the match of a tree against a subtree of a different package. `laravel/framework` declares `replace` for each `illuminate/*` package, thus Composer installs the monorepo or the split packages and never installs both, and the tree that the match reads is not in `vendor/`.

Do not add the exchange of a trust file between two organizations. No publisher publishes an audit feed today. When a publisher exists, distribute the trust file as a Composer package, and not as a URL that the tool fetches, thus the trust file inherits a version and immutability.

Do not add the verification of a Sigstore signature or of a TUF signature. PHP has no mature library for this work.

Do not add a second ecosystem, for example npm or Cargo.

Do not add a web interface or a hosted service.

Do not add `nikic/php-parser`. `porto` reads no PHP source, and a parser in the tree of a user makes a version conflict with Rector and with PHPStan.

Ship no compiled binary. `porto` must run against itself, thus each artifact must stay readable: a PHAR of PHP source is extractable and diffable. The PHAR comes from `php porto app:build`, which reads `box.json`, and section 4 says who runs that command.
