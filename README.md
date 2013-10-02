
Sidecar _(beta)_
=========

Sidecar is the Missing Plugin API for WordPress

Features
-
Sidecar provides an API for easily creating a **WordPress Plugin** that has the following features:

- **Admin Pages** &ndash; One (1) of more linked from one or more Admin Menu(s)/Submenu(s).
- **Admin Tabs** &ndash; Zero (0) or more per Admin Page.
- **Forms** &ndash; Zero (0) or more per Admin Tab and/or tab-less Admin Page.
- **Form Fields** &ndash; One (1) or more per Form.
- **Shortcodes** &ndash; Zero (0) or more.
- **Settings** &ndash; Management object for lists of forms and their fields.

Philosophy
-

Sidecar is designed to allow the plugin developer to get the most functionality with the least amount of work and to minimize time required for testing and adding common features. Sidecar follows the [D.R.Y. principle](http://en.wikipedia.org/wiki/Don't_repeat_yourself) and the [Decisions, not Options philosophy](http://wordpress.org/about/philosophy/#decisions). Developers subclass the Plugin Base class and add all features required in the class, directly or indirectly. The Plugin Base class leverages the additional

PHP Classes
-
Sidecar has the classes for the following functionality:

- Plugin
- Admin Page
- Admin Tab
- Form
- Field
- Shortcodes

### Current Feature Caveats
Sidecar currently only supports the following:
- While in beta, **the API is guaranteed to change**. If you try it, expect to have to change your code that uses it.
- Admin pages can only currently have a link appear in the _"Settings"_ menu _(without additional coding.)_
- Settings are associated with an Form; each Form has a 1-to-1 correspondence to a group of settings.
- The Forms and Fields leverage [WordPress' Settings API](http://codex.wordpress.org/Settings_API) although Sidecar may in the future add functionality that exceeds what the Settings API can support in which case use of the Settings API will be optional.
- Settings groups are currently not implemented.
- Shortcodes are currently not implemented.

More Documentation to Come...
--
