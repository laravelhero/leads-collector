# Leads Collector

WordPress plugin that connects a site to [Lead Manager](https://leads.websolutiontx.com): modern lead forms (block, Elementor widget, shortcode) and automatic call-click tracking.

Author: Abu Taher Hero · License: GPL-2.0-or-later · Requires WordPress 6.3+ and PHP 7.4+

## Install

In Lead Manager open **Websites → your website → Download WordPress plugin**. That zip is already connected to the website. In WordPress: **Plugins → Add New → Upload Plugin**, then activate.

Or download **leads-collector.zip** from the [latest release](https://github.com/laravelhero/leads-collector/releases/latest) and enter the site key under **Settings → Leads Collector**. Use the release zip, not **Code → Download ZIP**: that one unpacks to a `leads-collector-main` folder, which WordPress then treats as a different plugin when updating.

## Publish an update

```bash
./release.sh 1.2.0
```

The script checks every PHP file, sets the version, commits, tags, pushes and publishes `leads-collector.zip` as a GitHub release (it needs the `gh` command, logged in to GitHub). Every site then shows **Update now** on its Plugins screen (within 6 hours, or at once with **Dashboard → Updates → Check again**). Turn on **Enable auto-updates** on the Plugins screen to install updates without clicking.

## If something breaks

If the plugin causes a PHP error it pauses itself instead of taking the site down: the forms and tracking script stop loading and admins see a notice with the error. The site stays online. The next update turns it back on automatically, or click **Try again** in the notice.
