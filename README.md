# Sous Content Types Drupal Recipe
A recipe that contains default text and date formats.

## Configuring Drupal for Recipes

See https://www.drupal.org/files/issues/2023-10-01/Configuring%20Drupal%20to%20Apply%20Recipes.md

## Installing this Recipe

`composer require fourkitchens/sous-formats`

## Applying this Recipe

If you used the Sous Project as your starterkit:
- `lando install-recipe fourkitchens/sous-formats` 

Manually applying the recipe to your own project:
From your webroot run: 
- `php core/scripts/drupal recipe recipes/-formats`
- `drush cr`
- `composer unpack fourkitchens/sous-formats`
