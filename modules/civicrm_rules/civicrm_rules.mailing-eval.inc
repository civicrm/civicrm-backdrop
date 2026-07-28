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

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 * $Id$
 *
 */

require_once 'civicrm_rules_utils.inc';

function civicrm_rules_rules_action_mailing_send_email($to,
  $subject,
  $message,
  $from = NULL,
  $settings,
  RulesState $state,
  RulesPlugin $element
) {
  if (isset($state->variables['mailing']) && $state->variables['mailing']->id) {
    $to = str_replace(array("\r", "\n"), ',', $to);
    $toEmails = explode(',', $to);
    if (empty($toEmails)) {
      return;
    }
    $tokens['mailing'] = array(
      'mailing.editUrl',
      'mailing.scheduleUrl',
      'mailing.html',
      'mailing.creatorEmail',
      'mailing.creator',
    );

    $params            = array();
    $params['from']    = !empty($from) ? str_replace(array(
      "\r", "\n",
    ), '', $from) : 'Admin';
    $params['subject'] = CRM_Utils_Token::replaceMailingTokens($subject, $state->variables['mailing'], NULL, $tokens);
    $params['html']    = CRM_Utils_Token::replaceMailingTokens($message, $state->variables['mailing'], NULL, $tokens);

    // also allow some user permission tokens for the toEmail
    $tokens['user'] = array(
      'user.permission-schedule mailings',
      'user.permission-create mailings',
      'user.permission-approve mailings',
    );
    foreach ($toEmails as $toEmail) {
      $params['toEmail'] = CRM_Utils_Token::replaceMailingTokens($toEmail, $state->variables['mailing'], NULL, $tokens);

      //only process user permission tokens if used in toEmail
      if (in_array(trim($toEmail, '{}'), $tokens['user'])) {
        //we require a TO email, but can only send one; so we strip the first off and send the rest as CCs
        $userEmails = explode(',', _civicrm_rules_replaceUserTokens($params['toEmail'], $tokens));
        $params['toEmail'] = $userEmails[0];
        array_shift($userEmails);
        $params['cc'] = implode(',', $userEmails);
      }

      CRM_Utils_Mail::send($params);
    }
  }
}

function civicrm_rules_condition_mailing_approved($mailing, $settings) {
  return $mailing->approval_status_id == 1;
}

function civicrm_rules_condition_mailing_rejected($mailing, $settings) {
  return $mailing->approval_status_id != 1;
}

/**
 * Replace all user tokens in $str
 *
 * @param string $str
 *   The string with tokens to be replaced.
 *
 * @param null $knownTokens
 * @param bool $escapeSmarty
 *
 * @return string
 *   The processed string
 *
 */
function _civicrm_rules_replaceUserTokens($str, $knownTokens = NULL, $escapeSmarty = FALSE) {
  $key = 'user';
  if (!$knownTokens ||
    !isset($knownTokens[$key])
  ) {
    return $str;
  }

  $str = preg_replace_callback(
    _civicrm_rules_tokenRegex($key),
    function ($matches) use ($escapeSmarty) {
      return _civicrm_rules_getUserTokenReplacement($matches[1], $escapeSmarty);
    },
    $str
  );
  return $str;
}

/**
 * @param $token
 * @param bool $escapeSmarty
 *
 * @return string
 */
function _civicrm_rules_getUserTokenReplacement($token, $escapeSmarty = FALSE) {
  $value = '';

  [$objectName, $objectValue] = explode('-', $token, 2);

  switch ($objectName) {
    case 'permission':
      $value = CRM_Core_Config::singleton()->userPermissionClass->permissionEmails($objectName);
      break;

    case 'role':
      $value = CRM_Core_Config::singleton()->userRoleClass->roleEmails($objectValue);
      break;
  }

  if ($escapeSmarty) {
    $value = _civicrm_rules_tokenEscapeSmarty($value);
  }

  return $value;
}

function _civicrm_rules_tokenEscapeSmarty($string) {
  // need to use negative look-behind, as both str_replace() and preg_replace() are sequential
  return preg_replace(['/{/', '/(?<!{ldelim)}/'], ['{ldelim}', '{rdelim}'], $string);
}

function _civicrm_rules_tokenRegex(string $token_type) {
  return '/(?<!\{|\\\\)\{' . $token_type . '\.([\w]+(:|\.)?\w*(\-[\w\s]+)?)\}(?!\})/';
}
