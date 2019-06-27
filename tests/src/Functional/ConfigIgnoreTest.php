<?php

namespace Drupal\Tests\config_ignore_collection\Functional;

use Drupal\Tests\config_ignore\Functional\ConfigIgnoreBrowserTestBase;

/**
 * Test functionality of config_ignore module.
 *
 * @package Drupal\Tests\config_ignore_collection\Functional
 *
 * @group config_ignore
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ConfigIgnoreTest extends ConfigIgnoreBrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'system',
    'locale',
    'language',
    'config_translation',
    'config_split',
    'config_ignore_collection',
    'config_filter',
    'config',
  ];

  /**
   * Langcode for tests.
   *
   * @var string
   */
  protected $langcode = 'fr';

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Verify that the settings form works.
   */
  public function testSettingsForm() {
    $this->doTestForm($this->randomString());
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->languageManager = $this->container->get('language_manager');
  }

  /**
   * Verify that ignore works.
   */
  public function testIgnoreCollection() {
    $this->resetAll();
    $admin_user = $this->drupalCreateUser([
      'administer modules',
      'administer site configuration',
      'administer languages',
      'access administration pages',
      'administer permissions',
      'translate configuration',
      'synchronize configuration',
    ]);
    $this->drupalLogin($admin_user);
    // Enable import of translations. By default this is disabled for automated
    // tests.
    $this->config('locale.settings')
      ->set('translation.import_enabled', TRUE)
      ->set('translation.use_source', LOCALE_TRANSLATION_USE_SOURCE_LOCAL)
      ->save();

    $this->drupalPostForm('admin/config/regional/language/add',
      ['predefined_langcode' => $this->langcode],
      t('Add language'));
    $name_first = $this->randomString();
    $this->drupalPostForm('admin/config/system/site-information/translate/' . $this->langcode . '/edit', ['translation[config_names][system.site][slogan]' => $name_first], 'Save translation');
    $this->doExport();
    $this->updateSiteNameConfig($name_first, $this->langcode);
    $this->drupalGet('admin/config/development/configuration');
    $this->assertText('language.fr configuration collection');
    $this->doTestForm('language');
    $name = $this->getSiteNameConfig($this->langcode);
    $this->assertEquals($name, $name_first);
    $name_second = $this->randomString();
    $this->updateSiteNameConfig($name_second, $this->langcode);
    $name = $this->getSiteNameConfig($this->langcode);
    $this->assertEquals($name, $name_second);
    $this->drupalGet('admin/config/development/configuration');
    $this->assertNoText('language.' . $this->langcode . ' configuration collection');
  }

  /**
   * Update name for site config by langcode.
   *
   * @param string $string
   *   New site name.
   * @param string $langcode
   *   Langcode for config.
   */
  protected function updateSiteNameConfig($string, $langcode) {
    $this->languageManager->getLanguageConfigOverride($langcode, 'system.site')
      ->set('name', $string)
      ->save();
  }

  /**
   * Get site name by langcode.
   *
   * @param string $langcode
   *   Langcode.
   *
   * @return mixed
   *   Name from config.
   */
  protected function getSiteNameConfig($langcode) {
    return $this->languageManager->getLanguageConfigOverride($langcode,
      'system.site')->get('name');
  }

  /**
   * Test and update config form.
   *
   * @param string $string
   *   String to check.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function doTestForm($string) {
    // Login with a user that has permission to import config.
    $this->drupalLogin($this->drupalCreateUser(['import configuration']));

    $edit = [
      'ignored_config_collections' => $string,
    ];

    $this->drupalGet('admin/config/development/configuration/ignorecollection');
    $this->submitForm($edit, t('Save configuration'));

    $settings = $this->config('config_ignore_collection.settings')
     ->get('ignored_config_collections');

    $this->assertEqual($settings, [$string]);
  }
}
