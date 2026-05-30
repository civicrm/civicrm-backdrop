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

class BridgeBackdrop {

  /**
   * @param array $params
   * @param string $op
   */
  public static function nodeapi(&$params, $op) {

    $transaction = new CRM_Core_Transaction();

    // first create or update the CiviCRM group
    $groupParams = $params;
    $groupParams['source'] = BridgeUtils::ogSyncName($params['og_id']);
    $groupParams['group_type'] = ['2' => 1];
    self::updateCiviGroup($groupParams, $op);

    if (BridgeUtils::aclEnabled()) {
      // next create or update the CiviCRM ACL group
      $aclParams = $params;
      $aclParams['name'] = $aclParams['title'] = "{$aclParams['name']}: Administrator";
      $aclParams['source'] = BridgeUtils::ogSyncACLName($params['og_id']);
      $aclParams['group_type'] = ['1'];
      self::updateCiviGroup($aclParams, $op);

      $aclParams['acl_group_id'] = $aclParams['group_id'];
      $aclParams['civicrm_group_id'] = $groupParams['group_id'];

      self::updateCiviACLTables($aclParams, $op);
    }

    $transaction->commit();
  }

  /**
   * @param array $params
   * @param string $op
   * @param null $groupType
   */
  public static function updateCiviGroup(&$params, $op, $groupType = NULL) {
    $abort = FALSE;
    $params['version'] = 3;
    $params['id'] = BridgeUtils::groupID($params['source'], $params['title'], $abort);

    if ($op == 'add') {
      if ($groupType) {
        $params['group_type'] = $groupType;
      }

      $group = civicrm_api('group', 'create', $params);
      if (!civicrm_error($group)) {
        $params['group_id'] = $group['id'];
      }
    }
    else {
      // do this only if we have a valid id
      if ($params['id']) {
        CRM_Contact_BAO_Group::discard($params['id']);
        $params['group_id'] = $params['id'];
      }
    }
    unset($params['id']);
  }

  /**
   * @param array $aclParams
   * @param string $op
   */
  public static function updateCiviACLTables($aclParams, $op) {
    if ($op == 'delete') {
      self::updateCiviACL($aclParams, $op);
      self::updateCiviACLEntityRole($aclParams, $op);
      self::updateCiviACLRole($aclParams, $op);
    }
    else {
      self::updateCiviACLRole($aclParams, $op);
      self::updateCiviACLEntityRole($aclParams, $op);
      self::updateCiviACL($aclParams, $op);
    }
  }

  /**
   * @param array $params
   * @param string $op
   */
  public static function updateCiviACLRole(&$params, $op) {

    $optionGroupID = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_OptionGroup',
      'acl_role',
      'id',
      'name'
    );

    $dao = new CRM_Core_DAO_OptionValue();
    $dao->option_group_id = $optionGroupID;
    $dao->description = $params['source'];

    if ($op == 'delete') {
      $dao->delete();
      return;
    }

    $dao->label = $params['title'];
    $dao->is_active = 1;

    $weightParams = ['option_group_id' => $optionGroupID];
    $dao->weight = CRM_Utils_Weight::getDefaultWeight('CRM_Core_DAO_OptionValue',
      $weightParams
    );
    $dao->value = CRM_Utils_Weight::getDefaultWeight('CRM_Core_DAO_OptionValue',
      $weightParams,
      'value'
    );

    $query = "
SELECT v.id
  FROM civicrm_option_value v
 WHERE v.option_group_id = %1
   AND v.description     = %2
";
    $queryParams = [
      1 => [$optionGroupID, 'Integer'],
      2 => [$params['source'], 'String'],
    ];
    $dao->id = CRM_Core_DAO::singleValueQuery($query, $queryParams);
    $dao->save();
    $params['acl_role_id'] = $dao->value;
  }

  /**
   * @param array $params
   * @param string $op
   */
  public static function updateCiviACLEntityRole(&$params, $op) {
    $dao = new CRM_ACL_DAO_EntityRole();

    $dao->entity_table = 'civicrm_group';
    $dao->entity_id = $params['acl_group_id'];
    if ($op == 'delete') {
      $dao->delete();
      return;
    }

    $dao->acl_role_id = $params['acl_role_id'];

    $dao->find(TRUE);
    $dao->is_active = TRUE;
    $dao->save();
    $params['acl_entity_role_id'] = $dao->id;
  }

  /**
   * @param array $params
   * @param string $op
   */
  public static function updateCiviACL(&$params, $op) {
    $dao = new CRM_ACL_DAO_ACL();

    $dao->object_table = 'civicrm_group';
    $dao->object_id = $params['civicrm_group_id'];

    if ($op == 'delete') {
      $dao->delete();
      return;
    }

    $dao->find(TRUE);

    $dao->entity_table = 'civicrm_acl_role';
    $dao->entity_id = $params['acl_role_id'];
    $dao->operation = 'Edit';

    $dao->is_active = TRUE;
    $dao->save();
    $params['acl_id'] = $dao->id;
  }

  /**
   * @param array $params
   * @param string $op
   *
   * @throws Exception
   */
  public static function og(&$params, $op) {

    $contactID = BridgeUtils::contactID($params['uf_id']);
    if (!$contactID) {
      throw new CRM_Core_Exception(' no contact found');
    }

    // get the group id of this OG
    $groupID = BridgeUtils::groupID(BridgeUtils::ogSyncName($params['og_id']),
      NULL, TRUE
    );

    $groupParams = [
      'contact_id' => $contactID,
      'group_id' => $groupID,
      'version' => 3,
    ];

    if ($op == 'add') {
      $groupParams['status'] = $params['is_active'] ? 'Added' : 'Pending';
      civicrm_api('GroupContact', 'Create', $groupParams);
    }
    else {
      $groupParams['status'] = 'Removed';
      civicrm_api('GroupContact', 'Delete', $groupParams);
    }

    if (BridgeUtils::aclEnabled() &&
      $params['is_admin'] !== NULL
    ) {
      // get the group ID of the acl group
      $groupID = BridgeUtils::groupID(BridgeUtils::ogSyncACLName($params['og_id']),
        NULL, TRUE
      );

      $groupParams = [
        'contact_id' => $contactID,
        'group_id' => $groupID,
        'status' => $params['is_admin'] ? 'Added' : 'Removed',
        'version' => 3,
      ];

      if ($params['is_admin']) {
        civicrm_api('GroupContact', 'Create', $groupParams);
      }
      else {
        civicrm_api('GroupContact', 'Delete', $groupParams);
      }
    }
  }

}
