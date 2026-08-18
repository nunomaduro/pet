# The project

`pet` is a dependency audit ledger for PHP. `pet` records the bytes of each installed package that the user trusts, shows the reviewable delta between the version that the user trusted and the version that the user installed, and exits non-zero when a package in `composer.lock` is ungranted or when its bytes changed. Two users read the output: a PHP developer who reviews the dependencies of a project, and the CI job of that project.

Install the dependencies with `composer install`. Run the tests with `composer test`. Run the program with `./pet`.

`pet` is a [Laravel Zero](https://laravel-zero.com) application. `App\Commands\AuditCommand` is the default command in `config/commands.php`, thus `./pet` with no argument shows what is unaudited.

| Path | Function |
|---|---|
| `app/Commands` | the commands of the CLI: the input, the output and the flags |
| `app/Providers` | the service registration of Laravel Zero |
| `app/Identity` | the content identity of an installed tree |
| `app/Lock` | the readers of `composer.lock` and of `vendor/composer/installed.json` |
| `app/Registry` | the metadata of Packagist, the fetch of an archive and the extraction of an archive |
| `app/Delta` | the difference between two trees, the classifier of a change and the unified diff |
| `app/Ledger` | the read of `pet.json`, the write of `pet.json`, the entry and the audit decision |
| `app/Exceptions` | the exceptions of the audit engine |
| `app/Support` | the cache, the HTTP client, the JSON codec, the paths, the version constraints and the helpers of a command |
| `bootstrap` and `config` | the configuration of Laravel Zero |
| `tests` | the tests, which Pest runs |
| `pet.json` | the ledger of this project |

Audit this project with `./pet`, and record the result in `pet.json`.

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

Put each class under `app/` in the `App\` namespace. Use Laravel Zero, Illuminate and Symfony in `app/Commands`, `app/Providers` and `app/Support` only. Each other namespace under `App\` is the audit engine and uses the audit engine alone. When you add a namespace to the engine, add its name to the `$core` list in `tests/Arch.php`, because the test reads that list and does not find the namespace without it.

Give a helper under `app/Support` the output of the command and let it build its own components: `$this->components` is protected on `Illuminate\Console\Concerns\InteractsWithIO`, thus a helper cannot read it. `App\Support\DeltaRenderer` takes an `Illuminate\Console\OutputStyle` and makes an `Illuminate\Console\View\Components\Factory` of it.

Do not add an entry to `require` in `composer.json`. The audit engine uses the core of PHP alone, and `laravel-zero/framework` serves `app/Commands` and `app/Providers`. A new dependency also makes a version conflict when a user installs `pet` in the tree of a Laravel application.

Declare `abstract` on a class that a different class extends, because `pint.json` sets `final_class` and thus makes each other class final. `app/Exceptions/PetException.php` is abstract for this reason. To signal an error that has no dedicated class, throw `App\Exceptions\Failure`.

Treat each failure of a fetch as fatal. When a body is empty, or a call returns `false`, throw `App\Exceptions\FetchFailed`. An error must never look like a result that holds no change. A delta that the user did not ask for is the one exception: `pet audit <package>` warns and keeps the local report when it cannot fetch the delta of the granted version, and fails when the user names a version with `--from` and the fetch fails.

Ask no question, in any command. The user records a grant with `pet trust`, thus a question asks the user to say the same thing two times, and a question that reads no terminal answers itself with its own default.

Grant no permission, and add no scan that reads the code of a package. The user trusts the bytes of a package or the user does not trust them, and a list of permissions asks the user to make a second decision that `pet` cannot prove. Report a fact of the tree instead: the hash, the count of files, the bytes, the changed paths and the source of each change.

Run `composer test` after you change a file of source code. The command runs `rector process --dry-run`, `pint --test`, `./pet audit`, `phpstan analyse` and `pest --parallel`. The workflow `.github/workflows/tests.yml` runs the same steps on PHP 8.3, PHP 8.4 and PHP 8.5, with `--prefer-lowest` and with `--prefer-stable`.

---

## 5. The commands

| Command | Function |
|---|---|
| `pet audit` | show what is unaudited, worst first, with the count of files that each review costs, and exit non-zero when a package is ungranted, when its bytes changed, or when `vendor/` disagrees with `composer.lock` |
| `pet audit <package>` | show the content hash, the count of files, the bytes and the reviewable delta in buckets; `--from` names the version to compare from |
| `pet audit <package> -v` | show the same report with the source of each change |
| `pet trust` | trust each installed package at the bytes on disk, and make the ledger |
| `pet trust <package> …` | show the delta of each package that the user names, and write the entry of each one in `pet.json` |

`pet` with no argument runs `pet audit`.

Show the delta of `pet audit <package>` from the `--from` option when the user gives one, and from the version in `pet.json` when that version is not the installed version. Fetch nothing in each other case, thus the report of a covered package stays local and instant.

Print the source of each change with `-v`, in `pet audit <package>` and in `pet trust <package>`. The report of each command names the count of changes and invites `-v` when the user gives no `-v`, thus one command holds the review and no command points at a second command.

Print the source of each change under the path of that change, and indent it. `App\Support\DeltaRenderer` owns that output, thus `pet audit` and `pet trust` print one format.

Fetch nothing in `pet trust` with no argument, and build no delta. That command makes the baseline of a project that holds no ledger, thus it records the bytes on disk of each package that `pet audit` reports, and a fetch of one archive for each package makes the command too slow to use. `pet trust <package>` fetches the delta, because the user asks about one package.

Make `pet trust` with no argument show each package with its status and the reason of that status, and record each one. The command asks nothing: section 4 holds the reason.

Give `pet trust` a list of packages, thus the user records the result of one review of many packages in one run. The command records each package that the user names, shows the delta of each one, and writes `pet.json` one time.

Read each installed package in each command, and add no option that selects a part of the tree. A dev dependency runs on the machine of the developer and in the job of CI, thus it holds the same reach as a package of `require`, and a report that hides it makes the gate pass on a tree that nobody audited.

List each package that needs review, and add no option that limits the count. `pet audit` with no package is the full report, and a report that stops at a count hides the work that remains.

Reject `--from` in `pet trust` with no package, and in `pet trust` with more than one package. That option asks about one package, thus the command names the option and tells the user to give one package.

Make `pet audit` the gate of CI: the command prints the report and exits non-zero when a package is ungranted, when its bytes changed, or when the tree in `vendor/` disagrees with `composer.lock`. `composer test` runs the gate as the script `test:dependencies`, thus a change that breaks the gate breaks the test suite.

Exit non-zero from `pet audit <package>` when that package is ungranted or when its bytes changed. One rule holds for the two forms of the command, thus a script gates on one package.

Compare `vendor/` against `composer.lock` in each run of `pet audit` with no package, and add no option for it. The read of `composer.lock` costs one file, and a report of a tree that disagrees with the lock file names a version that the user did not install.

Keep the count of files to review in the gate, thus a failing job of CI names the cost of each review. The gate fetches one archive for a package that changed, and reports the whole package when that fetch fails.

Keep this table and the command table of `README.md` equal.

---

## 6. The content identity

Compute the identity of a package from the installed tree with `App\Identity\TreeHash`. Do not compute it from the metadata of Composer, because Composer records no digest of the bytes that Composer installed.

Hash the raw bytes of each file. Exclude no file. Sort the relative paths bytewise ascending. Do not normalize a line ending, a space or an encoding.

Write one line for each file in the manifest: the `sha256` of the contents, two spaces, the relative path, and one `\n`. Take the `sha256` of the manifest, and record the first 32 hexadecimal characters of it after the prefix `tree-v1:`.

Do not change the scheme `tree-v1`. Each audit in each ledger holds a hash of that scheme, thus a change to the scheme makes each audit invalid. Add a new prefix beside `tree-v1` instead.

Record no install source in the entry. A dist tree and a source tree of one version hash differently, thus the hash already separates them and a second field says the same thing again. When the hash of a tree that Composer installed with `--prefer-source` does not match the entry, `App\Ledger\PackageAudit` says that the tree came from `--prefer-source`. Keep that sentence: without it the user reads a byte change where the cause is the install source.

When Packagist publishes an immutable artifact with a provenance attestation, add that digest beside `tree-v1`, and keep `tree-v1`.

---

## 7. The bucket of a change

`App\Delta\Classifier` puts each changed path in one case of `App\Delta\Bucket`.

| Case | The path that it matches | The reviewer |
|---|---|---|
| `InstallManifest` | `composer.json`, and thus `scripts`, `bin`, the class of a plugin and `autoload` | a person, first of all |
| `Opaque` | an extension in `OPAQUE_EXTENSIONS` of `App\Delta\Classifier`, and each file that holds no readable source | nobody |
| `RuntimeSource` | a path in a non-dev `autoload` root, in `files`, in `classmap` or in `bin` | a person |
| `Inert` | each other path, for example `tests/`, `docs/`, `.github/` and a fixture | the machine |

Test `Opaque` before you test a rule that reads a path. A native extension is in no `autoload` root, thus a rule that reads a path puts native code in `Inert`.

Show `InstallManifest` first, always. A new `scripts` hook, a new plugin and a new `bin` entry appear in `composer.json`.

Tell the user that nobody can read an `Opaque` file, and that the entry accepts those bytes on the trust of the publisher alone.

Print no source of an `Opaque` change, and print the source of each other change with `-v`. A `.phar` and a `.so` file hold no readable source, thus a diff of one shows the user nothing.

---

## 8. The ledger

Keep the ledger in `pet.json` at the root of the project. Use JSON, because PHP reads JSON in its core and Composer uses JSON.

Write one entry for each package: the `version` and the `hash` of the installed tree. Put an entry of a package that Composer installed as a dependency of the project in `require`, and an entry of a dev dependency in `require-dev`. `App\Ledger\Grant` holds one entry.

Keep the `version` in the entry. Packagist addresses an archive by version and no package carries a digest of its dist, thus `pet audit <package>` cannot fetch the tree that the user reviewed from the hash alone.

Write one entry for each package and overwrite that entry on each `pet trust`. Keep no history of a version that the project stopped using: git holds the history, and section 2 forbids a record of what the project stopped doing.

`App\Ledger\Document` owns each read of `pet.json` and each write of `pet.json`. Do not read that file and do not write that file in a different class. `App\Ledger\Ledger` is a view on the document and touches no file.

Read the file again before each write and merge the sections, thus a writer never deletes a section that the writer does not own. `Ledger` owns `require` and `require-dev` and writes both in full.

Order the packages of each section, thus a `git diff` of `pet.json` stays reviewable.

Keep the `notes` of the entry when the user records a package again and gives no `--notes`. A grant that the user annotated keeps that sentence until the user replaces it.

Make a `Grant` of many packages from the `App\Ledger\PackageAudit` that `App\Ledger\Auditor::report()` holds. That object carries the version, the hash, the dev flag, the count of files and the bytes of each package, thus a second call to `Fingerprinter::ofPackage()` hashes the tree one more time. `App\Commands\TrustCommand` records each grant and calls `save()` one time, after the loop.

Report a package as covered when the entry holds the hash of the installed tree. `App\Ledger\AuditStatus` names each other result: `Ungranted` when no entry exists, and `Changed` when the hash differs.

Raise the constant `SCHEMA` of `App\Ledger\Document` when you change the shape of a section, and write the message that tells the user what to do. Schema 1 recorded an audit under a criterion, schema 2 recorded a list of permissions, and `Document` tells the user to delete the file and run `pet trust` again.

Read `pet.json` for the shape of each section. The project audits itself, thus that file shows the current shape.

---

## 9. Facts that you must not derive again

A prototype measured each item in this section against this repository on 2026-08-13.

No package carries a digest of its dist: 0 of 66 packages in `composer.lock` hold a `dist.shasum`, and an entry for a GitHub zipball holds the git reference and `"shasum": ""`. A GitHub zipball is not reproducible byte for byte, and `export-ignore` in `.gitattributes` makes the dist different from the tagged tree.

A GitHub zipball holds each file under one directory with the name `vendor-package-<sha>`. `App\Registry\Zip` removes that common root. Hash no path that holds that root, or each path differs between two versions.

A GitHub zipball answers 403 to a request that carries no `User-Agent` header, and the prototype printed `runtime changes (0)` for that failure. `App\Support\Http` sends the header, and section 4 makes the failure fatal.

`laravel/pint` ships no source in its dist. The dist holds `builds/`, `composer.json`, `LICENSE.md`, `overrides/` and `resources/`, and the psr-4 roots in `composer.json` point at directories that the dist does not hold. `builds/pint` executes, and that PHAR went from 23,815,394 bytes to 22,497,998 bytes between v1.30.4 and v1.30.5. Test a change of a rule of section 7 against this package.

`phpstan/phpstan` ships `phpstan.phar` and 29 prebuilt `.so` files under `turbo-ext/`, one for each platform and each minor version of PHP. No `.so` file is in an `autoload` root. Test a change of a rule of section 7 against this package too.

`symfony/var-dumper` changed `composer.json` alone between v7.4.14 and v7.4.15. This is the common delta.

The metadata of a package is at `https://repo.packagist.org/p2/<vendor>/<name>.json`. The array `packages.<name>` holds the newest version first, and each entry holds `version` and `dist.url`. `App\Registry\Packagist` reads this endpoint.

`Symfony\Component\Console\Formatter\OutputFormatter` renders the `href` style as a terminal hyperlink, and `App\Support\SourceDiffUrl` builds compare URLs for HTTPS GitHub and GitLab source URLs without a network call.

Read the full tree, and add no cache for speed. A hash of 6,953 files and 91.2 MB in `vendor/` takes 0.85 s.

---

## 10. How to test a change by hand

Run `./pet audit symfony/console` two times, and compare the two hashes: the hashes are equal. Change one byte of a file of that package in `vendor/`: the hash changes.

Run `./pet audit carbonphp/carbon-doctrine-types --from=3.1.0`: the report names the two changed paths, holds no source, and invites `-v`. Run the same command with `-v`: the report holds the two hunks, and each hunk is under the path of its file.

Run `./pet audit laravel/pint --from=v1.30.4 -v`: the report shows the PHAR as an opaque artifact, holds no diff of it, and warns that nobody reads those bytes.

Delete `pet.json` and run `./pet trust`: the command lists each installed package with its status and the reason of that status, asks nothing, and writes the file. Run `./pet audit`: the command exits 0.

Turn the network off and run `./pet audit <package> --from=<version>`: the command fails with an error, and prints no delta that holds no change. Turn the network off and run `./pet audit <package>` for a package that holds a grant of an earlier version: the command warns, prints the local report and exits non-zero, because that package is not covered.

Run `./pet audit laravel/pint` while `pet.json` grants the installed version: the report holds no delta and reaches no network.

Run `./pet audit` while `pet.json` covers each installed package: the command exits 0. Add one byte to a file of one package in `vendor/` and run the command again: the command exits non-zero, reports that package as `changed` and prints the two hashes. Delete one entry of `pet.json` by hand and run the command again: the command reports that package as `ungranted`, and `./pet audit <that package>` exits non-zero too.

Run `./pet trust <package> <package>`: the command shows the delta of each one, writes the two entries, and asks nothing. Run `./pet trust <package> <package> --from=<version>`: the command fails and tells the user to give one package.

---

## 11. Out of scope

Do not add a Composer plugin, and do not write an entry of `config.policy`.

Do not add the match of a tree against a subtree of a different package. `laravel/framework` declares `replace` for each `illuminate/*` package, thus Composer installs the monorepo or the split packages and never installs both, and the tree that the match reads is not in `vendor/`.

Do not add the exchange of a ledger between two organizations. No publisher publishes an audit feed today. When a publisher exists, distribute the ledger as a Composer package, and not as a URL that the tool fetches, thus the ledger inherits a version and immutability.

Do not add the verification of a Sigstore signature or of a TUF signature. PHP has no mature library for this work.

Do not add a second ecosystem, for example npm or Cargo.

Do not add a web interface or a hosted service.

Do not add `nikic/php-parser`. `pet` reads no PHP source, and a parser in the tree of a user makes a version conflict with Rector and with PHPStan.

Ship no compiled binary. `pet` must run against itself, thus each artifact must stay readable: a PHAR of PHP source is extractable and diffable. Build the PHAR with `php pet app:build`, which reads `box.json`.
