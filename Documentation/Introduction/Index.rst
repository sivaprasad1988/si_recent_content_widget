..  _introduction:

============
Introduction
============

..  _what-it-does:

What does it do?
================

The extension adds the dashboard widget :guilabel:`Recent Content` to the
widget group :guilabel:`Content`. It lists the content elements
(`tt_content`) that were changed last, newest first.

Every entry shows:

*   the header of the content element (or :guilabel:`[Untitled]`)
*   badges for new, hidden, scheduled and expired elements
*   the content element type, the content element ID and the page ID
*   the date of the last modification
*   a button to edit the content element
*   a button to open the page in the :guilabel:`Layout` module, scrolled to
    the content element

Only content elements on pages the backend user is allowed to see are listed.
See :ref:`access-rules`.

..  figure:: /Images/add-widget.png
    :class: with-shadow
    :alt: The "Add widget" modal showing the Recent Content widget

    The widget in the :guilabel:`Add widget` modal (screenshot of version 1.x)

..  figure:: /Images/widget-on-dashboard.png
    :class: with-shadow
    :alt: The Recent Content widget on the dashboard

    The widget on the dashboard (screenshot of version 1.x)

..  _compatibility:

Compatibility
=============

..  csv-table:: Version matrix
    :header: "Extension version", "TYPO3", "PHP"

    "2.0.x", "14", "8.2 or higher"
    "1.2.x", "10.4, 11.5, 12.4", "Depends on the TYPO3 version"
