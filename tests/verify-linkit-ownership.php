<?php

declare(strict_types=1);

/**
 * Verify shared Linkit defaults or preservation in a disposable installation.
 *
 * SOUS_DISPOSABLE_TEST=1 SOUS_LINKIT_CASE=fresh|prepare-custom|custom
 * vendor/bin/drush php:script web/recipes/sous-formats/tests/verify-linkit-ownership.php
 */

use Drupal\linkit\Entity\Profile;
use Symfony\Component\Yaml\Yaml;

if (getenv('SOUS_DISPOSABLE_TEST') !== '1') {
  throw new RuntimeException('Set SOUS_DISPOSABLE_TEST=1 only for a disposable installation.');
}
$case = getenv('SOUS_LINKIT_CASE') ?: 'fresh';
if (!in_array($case, ['fresh', 'prepare-custom', 'custom'], TRUE)) {
  throw new InvalidArgumentException('Unknown Linkit ownership case: ' . $case);
}
foreach (['linkit', 'media', 'node'] as $module) {
  if (!\Drupal::moduleHandler()->moduleExists($module)) {
    throw new RuntimeException('Enable Linkit, Node, and Media before the ownership check.');
  }
}
$check = static function (bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
};
$expected = Yaml::parseFile(__DIR__ . '/../recipes/linkit/config/linkit.linkit_profile.default.yml');
$profile = Profile::load('default');
$state_key = 'sous_linkit_ownership.expected_profile';
if ($case === 'prepare-custom') {
  $profile ??= Profile::create($expected);
  $profile->set('label', 'Project-owned Linkit profile');
  $profile->set('description', 'This site owns its matcher choices.');
  $matchers = $expected['matchers'];
  $matchers['556010a3-e317-48b3-b4ed-854c10f4b950']['settings']['limit'] = 3;
  $matchers['556010a3-e317-48b3-b4ed-854c10f4b950']['settings']['metadata'] = 'Project-specific node metadata';
  unset($matchers['ee89d706-682c-42a0-bf7c-b912e6895ceb']);
  $custom_id = 'a6080000-0000-4000-8000-000000000001';
  $matchers[$custom_id] = ['id' => 'external', 'uuid' => $custom_id, 'settings' => [], 'weight' => 5];
  $profile->set('matchers', $matchers);
  $profile->save();
  \Drupal::state()->set($state_key, \Drupal::config('linkit.linkit_profile.default')->getRawData());
  print "PREPARED: project-owned profile with custom node settings, custom matcher, and no media matcher.\n";
  return;
}
$check($profile !== NULL, 'Missing default Linkit profile.');
if ($case === 'custom') {
  $before = \Drupal::state()->get($state_key);
  $check(is_array($before), 'Run the prepare-custom probe before applying the recipe.');
  $check($before === \Drupal::config('linkit.linkit_profile.default')->getRawData(), 'Recipe application changed project-owned Linkit profile data or UUID.');
  \Drupal::state()->delete($state_key);
}
else {
  foreach ($expected as $key => $value) {
    $check($profile->get($key) == $value, 'Shared Linkit default differs from its canonical source: ' . $key);
  }
  $plugin_ids = array_column($profile->get('matchers'), 'id');
  sort($plugin_ids);
  $check($plugin_ids === ['entity:media', 'entity:node', 'external'], 'Fresh profile needs node, media, and external matchers.');
}
foreach ($profile->get('matchers') as $id => $settings) {
  $check($id === $settings['uuid'] && $profile->getMatcher($id)->getUuid() === $id, 'The retained matcher cannot be instantiated: ' . $id);
}
print $case === 'custom'
  ? "PASS: existing profile UUID, node settings, custom matcher, and omitted media matcher are exactly preserved.\n"
  : "PASS: shared canonical node, media, and external Linkit defaults.\n";
