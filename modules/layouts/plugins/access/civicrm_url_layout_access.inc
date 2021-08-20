<?php
/**
 * @file
 * Plugin to provide access control based on CiviCRM path and query parameters.
 */

class CivicrmUrlLayoutAccess extends LayoutAccess {

  /**
   * Constructor for a Layout access rule.
   */
  public function __construct($plugin_name, array $data = array()) {
    parent::__construct($plugin_name, $data);
    $this->settings += array(
      'civicrm_urls' => array(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    $civicrm_urls = $this->settings['civicrm_urls'];
    if (!empty($civicrm_urls)) {
      $lines = array_map('trim', explode("\n", $civicrm_urls));
      return t('CiviCRM URLs: @urls', array('@urls' => implode(', ', $lines)));
    }
    else {
      return t('CiviCRM URLs: none defined.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function checkAccess() {
    if (arg(0) != 'civicrm') {
      return FALSE;
    }
    if (arg(1) == 'upgrade' || arg(1) == 'ajax') {
      return FALSE;
    }
    if (!civicrm_initialize()) {
      return FALSE;
    }

    $paths = array_map('trim', explode("\n", $this->settings['civicrm_urls']));
    foreach ($paths as $path) {
      $path_parts = explode('?', $path);
      if ($_GET['q'] != $path_parts[0]) {
        continue;
      }
      $query_parts = array();
      parse_str($path_parts[1], $query_parts);
      $match = TRUE;
      foreach ($query_parts as $key => $value) {
        $match &= (isset($_GET[$key]) && $_GET[$key] == $value);
      }
      if ($match) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function form(&$form, &$form_state) {
    parent::form($form, $form_state);
    $form['civicrm_urls'] = array(
      '#title' => t('CiviCRM URLs'),
      '#type' => 'textarea',
      '#description' => t('Use this layout only for CiviCRM pages whose path and query parameters match one of the lines above. Enter just the path and query parameters (not the domain), like "civicrm/event/info?id=1", one URL per line. Query parameter order is irrelevant and extra query parameters in the page URL will be ignored, but all query parameters in the above must be present in the page URL.'),
      '#default_value' => $this->settings['civicrm_urls'],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formSubmit($form, &$form_state) {
    parent::formSubmit($form, $form_state);
    $this->settings['civicrm_urls'] = $form_state['values']['civicrm_urls'];
  }

}
