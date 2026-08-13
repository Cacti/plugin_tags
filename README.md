# Plugin Tags

The plugin allows you to create colour tags and display them in graphs. 
It supports both manually entered tags and automatic tags.

Tags can be assigned to a device (displayed on all its graphs), 
to a specific graph for a single device, to all graphs for all devices 
within the same site ID, or to all graphs. There is also the option 
to set a default device and display, for example, system tags only on that device.

It is possible to enable automatic graphs, for example, for device restarts 
or reindexing, plugins (enabling, disabling, updating), Cacti version, etc.

When there are a large number of tags, not all of them are displayed in the graph. 
Instead, they are summarised and only the most recent few are shown.

It is also possible to archive old tags. These are then not displayed in the graph.


## Installation

To install the servcheck plugin, simply copy the plugin_servcheck directory to
Cacti's plugins directory and rename it to simply 'tags'. Once you have done
this, goto Cacti's Plugin Management page, Install and Enable the Tags plugin. Once
this is complete, you can grant users permission to manage tags.

Go to Management -> Tags


## Bugs and Feature Enhancements

Bug and feature enhancements for the servcheck plugin are handled in GitHub. If
you find a first search the Cacti forums for a solution before creating an issue
in GitHub - https://github.com/Cacti/plugin_tags

You can find more information on our forum - http://forums.cacti.net/

-----------------------------------------------
Copyright (c) 2004-2026 - The Cacti Group, Inc.

