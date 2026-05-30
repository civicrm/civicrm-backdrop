<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

class BridgeCivicrm {

  /**
   * @param int $groupID
   * @param object $group
   * @param string $op
   */
  public static function group($groupID, $group, $op) {
    if ($op == 'add') {
      self::groupAdd($groupID, $group);
    }
    else {
      self::groupDelete($groupID, $group);
    }
  }

  /**
   * @param int $groupID
   * @param object $group
   */
  public static function groupAdd($groupID, $group) {
    $ogID = BridgeUtils::ogID($groupID);

    $enabled_group_type = config_get('civicrm_og_sync.settings', 'enabled_group_type');

    if (!$enabled_group_type) {
      return;
    }

    $node = entity_create('node', array());
    if ($ogID) {
      $node->nid = $ogID;
    }

    global $user;
    $node->uid = $user->uid;
    $node->title = $group->title;
    $node->type = $enabled_group_type;
    $node->status = 1;

    // set the og values
    $node->og_description = $group->description;

    node_save($node);

    // also change the source field of the group.
    CRM_Core_DAO::setFieldValue('CRM_Contact_DAO_Group',
      $groupID,
      'source',
      BridgeUtils::ogSyncName($node->nid)
    );
  }

  /**
   * @param int $groupID
   * @param $group
   */
  public static function groupDelete($groupID, $group) {
    $ogID = BridgeUtils::ogID($groupID);
    if (!$ogID) {
      return;
    }

    node_delete($ogID);
  }

  /**
   * @param int $groupID
   * @param array $contactIDs
   * @param string $op
   */
  public static function groupContact($groupID, $contactIDs, $op) {
    $ogID = BridgeUtils::ogID($groupID);

    if (!$ogID) {
      return;
    }

    foreach ($contactIDs as $contactID) {
      $uid = CRM_Core_BAO_UFMatch::getUFId($contactID);
      if ($uid) {
        if ($op == 'add') {
          civicrm_og_sync_wrapper_og_membership_create($ogID, $uid);
        }
        else {
          civicrm_og_sync_wrapper_og_membership_delete($ogID, $uid);
        }
      }
    }
  }

}
