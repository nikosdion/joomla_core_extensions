# Core Joomla Extensions

A list of all core Joomla! extensions since Joomla! 1.7

Download it as [JSON](extensions.json) - [Text](extensions.md)

Last Updated: Wed, 02 Sep 2026 13:48:24 +0000 with Joomla! 6.2.0-beta2

## What is this?

This repository contains an automatically generated list of the core extensions shipped with the Joomla! CMS since version 1.7. Each extension is tagged with the minimum and maximum Joomla! version family (in the form `major.minor`, e.g. `1.7`, `2.5` etc) it has been shipped with.

## The `extensions.json` format

The file contains a JSON array with one object per core extension. All values are strings, including `client_id`.

```json
[
    {
        "type": "plugin",
        "element": "pagebreak",
        "folder": "content",
        "client_id": "0",
        "min": "1.7",
        "max": "6.2"
    }
]
```

Each object has the following fields:

| Field | Description |
|------|------|
| `type` | Joomla extension type. The current data contains `component`, `file`, `language`, `library`, `module`, `package`, `plugin`, and `template`. |
| `element` | The extension identifier stored in Joomla's `#__extensions.element` column, such as `com_content`, `mod_login`, or `pagebreak`. |
| `folder` | The plugin group for plugins, such as `content` or `system`. It is an empty string for other extension types. |
| `client_id` | Joomla application client as a string: `"0"` for the site (frontend) and `"1"` for the administrator (backend). |
| `min` | The earliest Joomla version family in which the extension was found. |
| `max` | The latest Joomla version family in which the extension was found. |

The `min` and `max` values are inclusive `major.minor` version families, not full release numbers or extension compatibility constraints. For example, `"min": "4.0"` and `"max": "5.4"` means the extension was found in the generated data from Joomla! 4.0 through Joomla! 5.4. Compare these values as version strings, not decimal numbers.

Consumers should identify an extension using the combination of `type`, `element`, `folder`, and `client_id`, and should not rely on the order of objects in the array.

## Why does it even exist?

I needed a way to validate that my sites do not have obsolete core extensions installed. 

Yes, sure, obsolete extensions _should_ have been automatically removed when I updated Joomla. The reality, however, is a bit more complicated.

When Joomla 5 removed CAPTCHA plugins from the core, it did not uninstall the Google reCAPTCHA plugins it used to ship up to and including Joomla! 4.4. Worse yet, they were left behind in a state which made them impossible to uninstall.

Other sites have had a complicated history with newer backups restored over older versions of the site, leaving behind core extensions which were no longer part of Joomla but got reinstalled using Discover.

The real world is messy. I needed a **reliable** way to know which Joomla core extensions are included in which version of Joomla, so I can find any leftovers on my sites and remove them.

## How does it work?

It lists the Git tags of Joomla's GitHub repository to find out the (tagged) versions of each Joomla version. It only keeps the latest version in each version family. It then downloads the MySQL installation file for each of these versions. It finds the `INSERT INTO #__extensions` SQL statements and parses them to find out the extensions installed with this version of Joomla!. It then goes through this version-by-version list of extensions to create a unified list of all extensions ever installed with Joomla!, as well as the minimum and maximum Joomla version these existed. 

## Notable issues 

1. Versions before 1.7.3 are not included. Joomla does not provide tags for these historic versions.
2. `com_joomlaupdate` was added in 2.5.2. Versions 2.5.0 and 2.5.1 did not have that component. Despite that, it is reported as having first appeared in Joomla! 2.5.
3. If there's ever again an extension which appears past an x.y.0 release the same as the above issue with `com_joomlaupdate` will happen.
4. If there's ever a core extension which disappears before the final patch version of a version family, it will be listed as being part of the entire version family.

Note that problems 3 and 4 **SHOULD NOT BE POSSIBLE ANYMORE** as Joomla! started following Semantic Versioning. Even though its SemVer compliance is a bit iffy, it has at least committed to not add or remove core extensions in the middle of a release cycle (any given version family).

## Using

After checking out your working copy, run `composer install`.

Copy `env.dist` to `.env` and put your [GitHub Personal Access Token (PAT)](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/managing-your-personal-access-tokens#creating-a-fine-grained-personal-access-token) in there; without it, the rate limiting on GitHub's side would make this impractical.

Run `php create.php` to retrieve the information and create the `extensions.md` and `extensions.json` files.

## License

This repository is licensed under the [GNU Affero General Public License version 3 or later](LICENSE.txt) (`AGPL-3.0-or-later`).
