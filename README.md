
Surrogate _(beta)_
=========

Surrogate is a PHP library for WordPress providing APIs to address needs that WordPress Core doesn't currently address &mdash; hence the name _"Surrogate"_. The authors of Surrogate would ideally like to see the features in Surrogate become part of WordPress Core.

Features
-
Surrogate provides an API for easily creating a **WordPress Plugin** that has the following features:

- **Admin Pages** &ndash; One (1) of more linked from one or more Admin Menu(s)/Submenu(s).
- **Admin Page Tabs** &ndash; Zero (0) or more per Admin Page. 
- **Admin Page Forms** &ndash; Zero (0) or more per Admin Page Tab and/or tab-less Admin Page.
- **Admin Page Form Fields** &ndash; One (1) or more per Admin Page Form.
- **Settings Groups** &ndash; Zero (0) or more per Admin Page Tab and/or tab-less Admin Page.
- **Shortcodes** &ndash; Zero (0) or more.

Philosophy
-

Surrogate is designed to allow the plugin developer to get the most functionality with the least amount of work and to minimize time required for testing and adding common features. Surrogate follows the [D.R.Y. principle](http://en.wikipedia.org/wiki/Don't_repeat_yourself) and the [Decisions, not Options philosophy](http://wordpress.org/about/philosophy/#decisions). Developers subclass the Plugin Base class and add all features required in the class, directly or indirectly. The Plugin Base class leverages the additional 

PHP Classes
-
Surrogate has the classes for the following functionality:

- Plugin Base
- Admin Page
- Admin Page Tab
- Admin Page Form
- Admin Page Form Field
- Settings Group
- Shortcodes

### Current Feature Caveats
Surrogate currently only supports the following:
- While in beta, **the API is guaranteed to change**. If you try it, expect to have to change your code that uses it.
- Admin pages can only currently have a link appear in the _"Settings"_ menu _(without additional coding.)_
- Settings Groups currently have a 1-to-1 correspondence with Admin Page Tabs or tab-less Admin Pages that do not have tabs.  More flexibility might be added if important use cases reveal themselves.
- The Forms, Fields and Settings Groups leverage [WordPress' Settings API](http://codex.wordpress.org/Settings_API) although Surrogate may in the future add functionality that exceeds what the Settings API can support in which case use of the Settings API will be optional.
- Settings groups are currently not implemented.
- Shortcodes are currently not implemented.

More Documentation to Come...
--
