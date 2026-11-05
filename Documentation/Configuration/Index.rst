..  _configuration:

=============
Configuration
=============

The widget needs no configuration for administrators. For other backend users
the widget has to be allowed in their backend group.

..  _configuration-permissions:

Allow the widget for backend groups
===================================

#.  Open the module :guilabel:`System > Backend Users` and edit the backend
    group.
#.  Open the tab :guilabel:`Module Permissions`.
#.  Enable :guilabel:`Recent Content` in the field
    :guilabel:`Allowed dashboard widgets`.
#.  Save the backend group.

The widget identifier is `recentContent`.

..  _access-rules:

Which content elements are listed?
==================================

A content element is listed if all of these apply:

*   It is not deleted.
*   It is a live record (no workspace version).
*   It is stored on a page (`pid` greater than `0`).
*   The backend user has the permission :guilabel:`Show page` for this page.

Hidden content elements, elements with a start or end time and elements
restricted to frontend user groups are listed as well. They are marked with a
badge.

The :guilabel:`Edit` button is only shown if the backend user has all of
these permissions:

*   modify the table `tt_content`
*   :guilabel:`Edit content` on the page
*   edit the content element itself (for example the language of the element
    and the field `editlock`)

..  _configuration-options:

Widget options
==============

The widget is registered in
:file:`EXT:si_recent_content_widget/Configuration/Services.yaml` with these
options:

..  confval-menu::
    :display: table
    :type:
    :default:

..  confval:: limit
    :name: widget-limit
    :type: int
    :default: 5

    Number of content elements shown in the widget.

..  confval:: refreshAvailable
    :name: widget-refresh-available
    :type: bool
    :default: true

    Shows the refresh button in the widget header.

To change an option, override the service definition in the
:file:`Configuration/Services.yaml` of your site package:

..  code-block:: yaml
    :caption: EXT:my_sitepackage/Configuration/Services.yaml

    services:
      dashboard.widget.woit.si_recent_content_widget.recentContent:
        class: 'Woit\SiRecentContentWidget\Widgets\RecentContentWidget'
        arguments:
          $options:
            limit: 10
            refreshAvailable: true
        tags:
          - name: dashboard.widget
            identifier: 'recentContent'
            groupNames: 'content'
            title: 'LLL:EXT:si_recent_content_widget/Resources/Private/Language/locallang.xlf:widgets.recentContent.title'
            description: 'LLL:EXT:si_recent_content_widget/Resources/Private/Language/locallang.xlf:widgets.recentContent.description'
            iconIdentifier: 'content-widget-text'
            height: 'large'
            width: 'medium'

..  note::

    The site package has to be loaded after this extension. Add
    `woit/si-recent-content-widget` to the `require` section of the
    :file:`composer.json` of the site package. Flush all caches afterwards.
