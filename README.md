trepmal/option-cache-cli
========================

WP-CLI: Check option caches

[![Build Status](https://travis-ci.org/trepmal/option-cache-cli.svg?branch=master)](https://travis-ci.org/trepmal/option-cache-cli)

Quick links: [Using](#using) | [Installing](#installing) | [Contributing](#contributing) | [Support](#support)

## Using

This package implements the following commands:

### wp option-cache diagnostic

Check cache values for all options, excluding transients

~~~
wp option-cache diagnostic [--show-all] [--format=<format>]
~~~

Defaults to first 1000 options.

**OPTIONS**

	[--show-all]
		Show all options (still excluding transients)

	[--format=<format>]
		Format to use for the output. One of table, csv or json.
		---
		default: table
		options:
		  - table
		  - json
		  - csv
		  - yaml
		  - count
		---

**EXAMPLES**

    $ wp option-cache diagnostic
    # example output truncated
    +-------------------+------------+------------------+--------------------+-----------------------------+
    | option_name       | autoloaded | db               | cache              | note                        |
    +-------------------+------------+------------------+--------------------+-----------------------------+
    | siteurl           | yes        | https://test.com | https://test.com   | OK: Cache is match          |
    | home              | yes        | https://test.com | https://test.com   | OK: Cache is match          |
    | blogname          | yes        | Test Blog        | Test Blog          | OK: Cache is match          |
    | testing_notoption | yes        | bacon            | bacon              | 🚨 Found in NOTOPTIONS      |
    | moderation_keys   | no         |                  |                    | OK: Cache is unset          |
    | recently_edited   | no         |                  |                    | OK: Cache is unset          |
    | testing           | no         | somevalue        | somedifferentvalue | 🚨 CACHE MISMATCH           |
    | site_logo         | NOTOPTION  | --               | --                 |                             |
    | testing_notoption | NOTOPTION  | --               | --                 | 🚨 NOTOPTION is real option |
    +-------------------+------------+------------------+--------------------+-----------------------------+



### wp option-cache compare

Compare cache and db value for given option

~~~
wp option-cache compare <option-name> [--format=<format>]
~~~

**OPTIONS**

	<option-name>
		Option name to compare

	[--format=<format>]
		Format to use for the output. One of table, csv or json.
		---
		default: table
		options:
		  - table
		  - json
		  - csv
		  - yaml
		  - count
		---

**EXAMPLES**

    $ wp option-cache compare home
    Database value:
    http://local.wordpress.test

    Set to autoload:
    1

    Autoloaded options (alloptions) cache value:
    http://local.wordpress.test
    Success: Cache matches db.

    Standalone options cache value:
    asdf
    Value should not be in options cache.

    notoptions cache:
    Success: Not found in notoptions.

## Installing

Installing this package requires WP-CLI v2.1 or greater. Update to the latest stable release with `wp cli update`.

Once you've done so, you can install the latest stable version of this package with:

```bash
wp package install trepmal/option-cache-cli:@stable
```

To install the latest development version of this package, use the following command instead:

```bash
wp package install trepmal/option-cache-cli:dev-master
```

## Contributing

We appreciate you taking the initiative to contribute to this project.

Contributing isn’t limited to just code. We encourage you to contribute in the way that best fits your abilities, by writing tutorials, giving a demo at your local meetup, helping other users with their support questions, or revising our documentation.

For a more thorough introduction, [check out WP-CLI's guide to contributing](https://make.wordpress.org/cli/handbook/contributing/). This package follows those policy and guidelines.

### Reporting a bug

Think you’ve found a bug? We’d love for you to help us get it fixed.

Before you create a new issue, you should [search existing issues](https://github.com/trepmal/option-cache-cli/issues?q=label%3Abug%20) to see if there’s an existing resolution to it, or if it’s already been fixed in a newer version.

Once you’ve done a bit of searching and discovered there isn’t an open or fixed issue for your bug, please [create a new issue](https://github.com/trepmal/option-cache-cli/issues/new). Include as much detail as you can, and clear steps to reproduce if possible. For more guidance, [review our bug report documentation](https://make.wordpress.org/cli/handbook/bug-reports/).

### Creating a pull request

Want to contribute a new feature? Please first [open a new issue](https://github.com/trepmal/option-cache-cli/issues/new) to discuss whether the feature is a good fit for the project.

Once you've decided to commit the time to seeing your pull request through, [please follow our guidelines for creating a pull request](https://make.wordpress.org/cli/handbook/pull-requests/) to make sure it's a pleasant experience. See "[Setting up](https://make.wordpress.org/cli/handbook/pull-requests/#setting-up)" for details specific to working on this package locally.

## Support

GitHub issues aren't for general support questions, but there are other venues you can try: https://wp-cli.org/#support


*This README.md is generated dynamically from the project's codebase using `wp scaffold package-readme` ([doc](https://github.com/wp-cli/scaffold-package-command#wp-scaffold-package-readme)). To suggest changes, please submit a pull request against the corresponding part of the codebase.*
