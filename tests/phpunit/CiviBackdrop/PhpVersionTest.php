<?php

namespace CiviBackdrop;

use Civi\Test\EndToEndInterface;

class PhpVersionTest extends \PHPUnit\Framework\TestCase implements EndToEndInterface {

  /**
   * CIVICRM_BACKDROP_PHP_MINIMUM (civicrm.module) should match MINIMUM_PHP_VERSION (CRM/Upgrade/Form.php).
   */
  public function testConstantMatch() {
    $constantFile = $this->getBackdropModulePath() . '/civicrm.module';
    $this->assertFileExists($constantFile);
    $content = file_get_contents($constantFile);
    if (preg_match(";define\\('CIVICRM_BACKDROP_PHP_MINIMUM', '(.*)'\\);", $content, $m)) {
      $this->assertEquals(\CRM_Upgrade_Form::MINIMUM_PHP_VERSION, $m[1]);
    }
    else {
      $this->fail('Failed to find CIVICRM_BACKDROP_PHP_MINIMUM in ' . $constantFile);
    }
  }

  /**
   * "php" requirement (civicrm.info) should match MINIMUM_PHP_VERSION (CRM/Upgrade/Form.php).
   */
  public function testInfoMatch() {
    $infoFile = $this->getBackdropModulePath() . '/civicrm.info';
    $this->assertFileExists($infoFile);
    $info = backdrop_parse_info_file($infoFile);
    $expectMajorMinor = preg_replace(';^(\d+\.\d+)\..*$;', '\1', \CRM_Upgrade_Form::MINIMUM_PHP_VERSION);
    $this->assertEquals($expectMajorMinor, $info['php']);
  }

  /**
   * @return string
   *   Ex: '/var/www/sites/all/modules/civicrm/backdrop'
   */
  protected function getBackdropModulePath() {
    return dirname(dirname(dirname(__DIR__)));
  }

}
