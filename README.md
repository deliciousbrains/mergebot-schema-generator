## Mergebot Schema Generator

This is an internal plugin used to create custom data schemas to describe plugins used by the Mergebot app.

### Setup

Symlink the `src/mergebot-schema-generator` dir to your `wp-content/plugins` dir

Generate a schema with the WP-CLI command:

First of all the command must be run from the plugin directory:
`cd wp-content/plugins/mergebot-schema-generator`

`wp mergebot-schema generate --plugin=woocommerce`

For WordPress core use:

`wp mergebot-schema generate`

### TODO

* More intelligent handling of foreign keys
* More intelligent handling of shortcodes
* Relationships - deal with custom plugin meta tables
* Content
* Clean up and doc blocks
* Don't duplicate core relationships, eg. a plugin adds '_thumbnail_id'