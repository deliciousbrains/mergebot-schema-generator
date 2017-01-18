## Mergebot Schema Generator

This is an work in progress, rough round the edges plugin used to create custom data schemas to describe plugins used by the Mergebot app.

### Setup

Symlink the `src/mergebot-schema-generator` dir to your `wp-content/plugins` dir

Generate a schema with the WP-CLI command:

First of all the command must be run from the plugin directory:
`cd wp-content/plugins/mergebot-schema-generator`

`wp mergebot-schema generate --plugin=woocommerce`

To run it for a specific version:

`wp mergebot-schema generate --plugin=woocommerce --version=2.0`

For WordPress core use:

`wp mergebot-schema generate`

### Scope

The generator will attempt define the following:

- Primary keys of custom tables
- Foreign key of custom tables
- Key / Value relationship data (CLI prompts)
- Shortcode attributes (CLI prompts)

When the command needs a human decision (eg. shortcodes) it will present helpful information and ask simple questions so the definition can be created.

The generator cannot define:

- Content that has IDs
- Queries that need to be ignored
- Custom shortcode search locations
- Table prefixes in meta keys