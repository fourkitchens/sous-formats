# Sous Formats Drupal Recipe

Owns reusable text/editor formats and the shared Linkit profile defaults.

## Supported baselines

This recipe works without another Sous recipe on the supported Minimal baseline
in the [platform guide](https://github.com/fourkitchens/sous-drupal-project/blob/main/docs/PLATFORM.md), after its Composer dependencies are installed.
It enables CKEditor 5, Linkit, Media, and Node; Sous Admin, Sous Content Types, and Sous Media are not prerequisites.
The media matcher becomes useful when media bundles such as `file` and `image`
are added by Sous Media or the site. It uses Linkit's built-in `media`
substitution to link directly to the media entity's source file.

Existing editor and format configuration must match this recipe. Exported
site UUIDs and default configuration hashes do not belong in recipe defaults.
An existing Linkit `default` profile is intentionally retained even when its
matchers differ. UUIDs nested under `matchers` identify Linkit plugins and must
be preserved.
Linkit-enabled editors enforce their module and profile dependencies so Drupal
retains them when recalculating configuration dependencies on save.

## Shared Linkit configuration ownership

`recipes/linkit` owns the reusable default profile. Both the full Formats recipe
and Sous Admin apply this native subrecipe; Admin does not apply Formats' editor
or text-format defaults. New profiles consistently contain node, media, and
external matchers regardless of application order. Existing profile UUIDs,
labels, descriptions, and matcher configuration remain project-owned and are
never merged or overwritten by this subrecipe. This includes older Admin-only
profiles without media matching; add that matcher as an explicit reviewed site
configuration change if desired, then export the site's complete configuration.

Recipe packages must use the documented Drupal-root `recipes/` directory
(`web/recipes/` in Sous). The include path
`recipes/sous-formats/recipes/linkit` uses Drupal's native root-relative recipe
resolution. The subrecipe can also be applied from the consuming project root with
`ddev drush recipe /var/www/html/web/recipes/sous-formats/recipes/linkit`. It installs
Linkit, Node, and Media, but no CKEditor configuration. It is safe to apply again
when the goal is to create a missing default profile while preserving an
existing one; reapplying the full Formats recipe still checks its strict editor
and format defaults and is not an existing-site update mechanism.

## Installing this Recipe

These examples select the current unreleased implementation on `dev-main`.
Run them in a configured consuming site, not in this recipe source checkout.
[Setup and recipe prerequisites](https://github.com/fourkitchens/sous-drupal-project/blob/main/docs/SETUP.md) define the required
repositories, installer paths, core unpacking plugin, and explicit root stability
exceptions. Use reviewed stable tags once the coordinated releases exist.

`ddev composer require fourkitchens/sous-formats:dev-main`

## Applying this Recipe

If you used the Sous Project as your starterkit:

- `ddev install-recipe fourkitchens/sous-formats`

Without the Sous DDEV command, use a configured consuming project and run from its root:

- `ddev drush recipe /var/www/html/web/recipes/sous-formats`
- `ddev drush cache:rebuild`

Basic HTML sanitizes HTML before the Linkit converter runs. Its allowlist keeps
the `data-entity-type`, `data-entity-uuid`, and `data-entity-substitution` anchor
attributes so Linkit can resolve linked entities, while the editor permits the
same markup. Limited HTML also supports Linkit; Full HTML is intended only for
trusted authors. Restricted HTML does not enable Linkit URL conversion.

## Regression checks

In a disposable Drupal installation with this recipe applied, run from the
project root:

```sh
ddev drush php:script web/recipes/sous-formats/tests/verify-formats.php
```

The check validates all four editor/format pairs, instantiates Linkit matchers,
checks recipe metadata, and creates temporary nodes to verify URL substitution
and HTML sanitization. When a `file` or `image` media bundle exists, it also
checks media UUID links against a temporary file and media entity. It removes
the test entities, files, and node bundle afterward.

The guarded ownership probe is used by disposable integration fixtures for
fresh profiles, both Admin/Formats application orders, and an existing project
profile (including deliberately changed node matcher settings):

```sh
ddev exec env SOUS_DISPOSABLE_TEST=1 SOUS_LINKIT_CASE=fresh \
  vendor/bin/drush php:script web/recipes/sous-formats/tests/verify-linkit-ownership.php
```

For customization fixtures, enable Linkit/Node/Media, run the probe with
`SOUS_LINKIT_CASE=prepare-custom`, apply the chosen recipe(s), then run it with
`SOUS_LINKIT_CASE=custom`. These are disposable checks, not production migration
commands.

## Development, integration, and releases

This repository is reusable recipe source, not a generated Drupal application.
Its Composer requirements and `recipe.yml` describe installation defaults; the
consuming project owns its runtime, complete configuration export, custom
theme, and application deployment. Requiring `dev-main` downloads remote source;
it does not test uncommitted sibling checkout changes.

Use the shared documentation instead of maintaining another setup procedure:

- [Supported runtime and package relationships](https://github.com/fourkitchens/sous-drupal-project/blob/main/docs/PLATFORM.md)
- [Fresh installation, contributor clones, restoration, and recovery](https://github.com/fourkitchens/sous-drupal-project/blob/main/docs/SETUP.md)
- [Sibling-source development and integration checks](https://github.com/fourkitchens/sous-drupal-project/blob/main/docs/INTEGRATION.md)
- [Configuration ownership and existing-site upgrades](https://github.com/fourkitchens/sous-drupal-project/blob/main/docs/MAINTENANCE.md)
- [Frontend development and production assets](https://github.com/fourkitchens/sous-drupal-project/blob/main/docs/BUILD_TOOLS.md)
- [Production deployment](https://github.com/fourkitchens/sous-drupal-project/blob/main/docs/DEPLOYMENT.md)
- [Coordinated releases and Conventional Commits](https://github.com/fourkitchens/sous-drupal-project/blob/main/docs/RELEASES.md)
- [Maintenance ownership and review cadence](https://github.com/fourkitchens/sous-drupal-project/blob/main/docs/OPERATIONS.md)

Recipe release tooling runs with the Node/npm versions pinned in `.nvmrc` and
`package.json`; Node is not required to execute a recipe in Drupal. The private
npm package publishes no browser assets or npm release. The thin workflow calls
the shared integration gate before the guarded GitHub publisher. Its central
workflow must be rolled out first; source configuration is not proof of a live
GitHub run or release. See the release guide for actual tag state, stable-tag
adoption, and explicit development exceptions.

Package updates do not automatically change configuration already applied to a
site. Preserve its complete export and adopt changes through reviewed native
configuration migrations and project-owned overrides. Do not routinely replay
the entire recipe on a customized site.

## License

Sous recipe source is licensed under [GPL-2.0-or-later](LICENSE). Dependencies
retain their own license terms; see the shared license policy in the platform
guide.
