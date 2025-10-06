# Core Joomla Extensions

A list of all core Joomla! extensions since Joomla! 1.7

Download it as [JSON](extensions.json) - [Text](extensions.md)

Last Updated: Mon, 06 Oct 2025 19:06:35 +0000 with Joomla! 6.0.0-rc1

## What is this?

This repository contains an automatically generated list of the core extensions shipped with the Joomla! CMS since version 1.7. Each extension is tagged with the minimum and maximum Joomla! version family (in the form `major.minor`, e.g. `1.7`, `2.5` etc) it has been shipped with.

## Why does it even exist?

I needed a way to validate that my sites do not have obsolete core extensions installed. 

Yes, sure, obsolete extensions _should_ have been automatically removed when I updated Joomla. The reality, however, is a bit more complicated.

When Joomla 5 removed CAPTCHA plugins from the core it did not uninstall the Google reCAPTCHA plugins it used to ship up to and including Joomla! 4.4. Worse yet, they were left behind in a state that made them impossible to uninstall.

Other sites have had a complicated history with newer backups restored over older versions of the site, leaving behind core extensions which were no longer part of Joomla but got reinstalled using Discover.

Real world is messy.

## How does it work?

It lists the Git tags of Joomla's GitHub repository to find out the (tagged) versions of each Joomla version. It only keeps the latest version in each version family. It then downloads the MySQL installation file for each of these versions. It finds the `INSERT INTO #__extensions` SQL statements and parses them to find out the extensions installed with this version of Joomla!. It then goes through this version-by-version list of extensions to create a unified list of all extensions ever installed with Joomla!, as well as the minimum and maximum Joomla version these existed. 

## Notable issues 

1. Versions before 1.7.3 are not included. Joomla does not provide tags for these historic versions.
2. `com_joomlaupdate` was added in 2.5.2. Versions 2.5.0 and 2.5.1 did not have that component. Despite that, it is reported as having first appeared in Joomla! 2.5.
3. If there's ever again an extension which appears past an x.y.0 release the same as the above issue with `com_joomlaupdate` will happen.
4. If there's ever a core extension which disappears before the final patch version of a version family, it will be listed as being part of the entire version family.

Note that problems 3 and 4 **SHOULD NOT BE POSSIBLE ANYMORE** as Joomla! started following Semantic Versioning. Even though its SemVer compliance is a bit iffy, it has at least committed to not add or remove core extensions in the middle of a release cycle (any given version family).