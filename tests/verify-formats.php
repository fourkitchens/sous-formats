<?php

/**
 * Verifies applied formats. Run through Drush only in a disposable installation.
 */

use Drupal\ckeditor5\Plugin\Editor\CKEditor5;
use Drupal\Component\Utility\Html;
use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\linkit\Entity\Profile;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use Symfony\Component\Yaml\Yaml;

$check = static function (bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
};

$editor_errors = [];
foreach (['basic_html', 'full_html', 'limited_html', 'restricted_html'] as $id) {
  $editor = Editor::load($id);
  $format = FilterFormat::load($id);
  $check($editor !== NULL && $format !== NULL, "Missing editor or format: $id");
  if ($editor->getSettings()['plugins']['linkit_extension']['linkit_enabled'] ?? FALSE) {
    $dependencies = $editor->getDependencies();
    $check(in_array('linkit.linkit_profile.default', $dependencies['config'] ?? [], TRUE), "Editor lost its Linkit profile dependency: $id");
    $check(in_array('linkit', $dependencies['module'] ?? [], TRUE), "Editor lost its Linkit module dependency: $id");
    $recalculated = clone $editor;
    $recalculated->calculateDependencies();
    $check($recalculated->getDependencies() === $dependencies, "Editor dependencies change when recalculated: $id");
  }
  foreach (CKEditor5::validatePair($editor, $format) as $violation) {
    $editor_errors[] = "$id: {$violation->getPropertyPath()}: {$violation->getMessage()}";
  }
}

foreach (glob(dirname(__DIR__) . '/config/*.yml') as $file) {
  $config = Yaml::parseFile($file);
  $check(!array_key_exists('uuid', $config) && !isset($config['_core']['default_config_hash']), "Export metadata in $file");
}
$profile = Profile::load('default');
$check($profile !== NULL, 'Missing default Linkit profile.');
foreach ($profile->get('matchers') as $id => $configuration) {
  $check($id === $configuration['uuid'], "Nested matcher UUID differs from instance ID: $id");
  $check($profile->getMatcher($id)->getUuid() === $id, "Matcher cannot be instantiated: $id");
  if (isset($configuration['settings']['substitution_type'])) {
    $check(Drupal::service('plugin.manager.linkit.substitution')->hasDefinition($configuration['settings']['substitution_type']), "Matcher references an unknown substitution plugin: $id");
  }
}

$bundle_id = 'sous_format_regression';
$check(NodeType::load($bundle_id) === NULL, 'Regression bundle already exists; use a disposable installation.');
$bundle = NodeType::create(['type' => $bundle_id, 'name' => 'Format regression']);
$node = NULL;
$media = NULL;
$file = NULL;
$account_switcher = Drupal::service('account_switcher');
$account_switcher->switchTo(User::load(1));
try {
  $bundle->save();
  $node = Node::create(['type' => $bundle_id, 'title' => 'Linkit regression target', 'status' => 1, 'uid' => 1]);
  $node->save();
  $raw = '<p><a class="button-sfa unwanted" href="/obsolete?test=1#target" data-entity-type="node" data-entity-uuid="' . $node->uuid() . '" data-entity-substitution="canonical" onclick="alert(1)">Target</a></p>'
    . '<p><a href="javascript:alert(1)" onclick="alert(1)">Unsafe</a></p><script>alert(1)</script>';
  $format = FilterFormat::load('basic_html');
  $sanitized = (string) $format->filters()->get('filter_html')->process($raw, 'en');
  $link = Html::load($sanitized)->getElementsByTagName('a')->item(0);
  foreach (['data-entity-type' => 'node', 'data-entity-uuid' => $node->uuid(), 'data-entity-substitution' => 'canonical'] as $attribute => $value) {
    $check($link->getAttribute($attribute) === $value, "HTML filter removed $attribute before Linkit.");
  }
  $build = ['#type' => 'processed_text', '#text' => $raw, '#format' => 'basic_html', '#langcode' => 'en'];
  $output = (string) Drupal::service('renderer')->renderInIsolation($build);
  $links = Html::load($output)->getElementsByTagName('a');
  $link = $links->item(0);
  $check($link->getAttribute('href') === $node->toUrl()->toString() . '?test=1#target', 'Linkit did not resolve the current canonical URL.');
  $check($link->getAttribute('title') === $node->label(), 'Linkit did not add the linked entity title.');
  $check($link->getAttribute('class') === 'button-sfa', 'Allowed link class was lost or an unwanted class survived.');
  foreach (['data-entity-type' => 'node', 'data-entity-uuid' => $node->uuid(), 'data-entity-substitution' => 'canonical'] as $attribute => $value) {
    $check($link->getAttribute($attribute) === $value, "Processed text lost $attribute.");
  }
  foreach ($links as $link) {
    $check(!$link->hasAttribute('onclick') && !str_contains(strtolower($link->getAttribute('href')), 'javascript:'), 'Unsafe link markup survived filtering.');
  }
  $check(!str_contains(strtolower($output), '<script'), 'Script tag survived filtering.');
  print "PASS: Linkit metadata survives sanitization and resolves entities; unsafe markup is removed.\n";

  // Formats does not create media bundles; exercise file links when provided.
  $media_type = MediaType::load('file') ?: MediaType::load('image');
  if ($media_type) {
    $source_field = $media_type->getSource()->getSourceFieldDefinition($media_type);
    $check($source_field !== NULL, 'Media bundle has no source field.');
    $image = $source_field->getType() === 'image';
    $bytes = $image
      ? base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jV9sAAAAASUVORK5CYII=')
      : "Sous Linkit regression\n";
    $uri = 'public://sous-linkit-' . Drupal::service('uuid')->generate() . ($image ? '.png' : '.txt');
    $file = Drupal::service('file.repository')->writeData($bytes, $uri);
    $media = Media::create([
      'bundle' => $media_type->id(),
      'name' => 'Linkit media regression',
      'status' => 1,
      'uid' => 1,
      $source_field->getName() => ['target_id' => $file->id()] + ($image ? ['alt' => 'Regression image'] : []),
    ]);
    $media->save();
    $build = [
      '#type' => 'processed_text',
      '#format' => 'basic_html',
      '#langcode' => 'en',
      '#text' => '<a href="/obsolete-media" data-entity-type="media" data-entity-uuid="' . $media->uuid() . '" data-entity-substitution="media">Download</a>',
    ];
    $output = (string) Drupal::service('renderer')->renderInIsolation($build);
    $link = Html::load($output)->getElementsByTagName('a')->item(0);
    $check($link->getAttribute('href') === Drupal::service('file_url_generator')->generate($file->getFileUri())->toString(), 'Linkit did not resolve the media file URL.');
    $check($link->getAttribute('data-entity-uuid') === $media->uuid() && $link->getAttribute('data-entity-substitution') === 'media', 'Media link metadata was lost.');
    print "PASS: Linkit resolves a media entity UUID to its file download URL.\n";
  }
  else {
    print "SKIP: media download check requires an existing file or image media bundle.\n";
  }
}
finally {
  $media?->delete();
  $file?->delete();
  $node?->delete();
  $bundle->delete();
  $account_switcher->switchBack();
}

$check($editor_errors === [], "CKEditor 5 format incompatibilities:\n" . implode("\n", $editor_errors));
print "PASS: all four CKEditor 5 pairs validate; recipe metadata and Linkit matcher IDs are valid.\n";
