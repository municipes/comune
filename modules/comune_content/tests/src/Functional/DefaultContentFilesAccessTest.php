<?php

namespace Drupal\Tests\comune_content\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests that files provided by comune_content are not accessible.
 *
 * @group comune_content
 */
class DefaultContentFilesAccessTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that sample images, recipes and articles are not accessible.
   */
  public function testAccessDeniedToFiles() {
    // The comune profile should not be used because we want to ensure that
    // if you install another profile these files are not available.
    $this->assertNotSame('comune', \Drupal::installProfile());

    $files_to_test = [
      'images/heritage-carrots.jpg',
      'languages/en/recipe_instructions/mediterranean-quiche-umami.html',
      'languages/en/article_body/lets-hear-it-for-carrots.html',
      'languages/en/node/article.csv',
    ];
    foreach ($files_to_test as $file) {
      // Hard code the path since the comune profile is not installed.
      $content_path = "core/profiles/comune/modules/comune_content/default_content/$file";
      $this->assertFileExists($this->root . '/' . $content_path);
      $this->drupalGet($content_path);
      $this->assertSession()->statusCodeEquals(403);
    }
  }

}
