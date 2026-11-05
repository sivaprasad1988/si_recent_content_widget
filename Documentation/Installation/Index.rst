..  _installation:

============
Installation
============

..  _installation-composer:

Install the extension
=====================

Install the extension with Composer:

..  code-block:: bash

    composer require woit/si-recent-content-widget

Then set up the extension and flush the caches:

..  code-block:: bash

    vendor/bin/typo3 extension:setup
    vendor/bin/typo3 cache:flush

The extension requires the system extension `dashboard`
(`typo3/cms-dashboard`).

..  _installation-add-widget:

Add the widget to a dashboard
=============================

#.  Open the module :guilabel:`Dashboard`.
#.  Click the :guilabel:`+` button in the lower right corner.
#.  Select the group :guilabel:`Content`.
#.  Click :guilabel:`Recent Content`.

The widget is added to the current dashboard. Each backend user adds the
widget to their own dashboard.

Backend users who are not administrators only see the widget in the
:guilabel:`Add widget` modal if their backend group allows it. See
:ref:`configuration-permissions`.
