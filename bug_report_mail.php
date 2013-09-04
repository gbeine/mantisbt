<?php
	# Mantis - a php based bugtracking system
	# Copyright (C) 2002 - 2004  Mantis Team   - mantisbt-dev@lists.sourceforge.net
	# Copyright (C) 2004  Gerrit Beine - gerrit.beine@pitcom.de
	# This program is distributed under the terms and conditions of the GPL
	# See the README and LICENSE files for details

	# --------------------------------------------------------
	# $Id$
	# --------------------------------------------------------

	# This page receives an E-Mail via POP3 and generates an Report
	header("Content-type: text/plain");
	require_once( 'core.php' );

	$t_core_path = config_get( 'core_path' );

	require_once( $t_core_path . 'string_api.php' );
	require_once( $t_core_path . 'bug_api.php' );
	require_once( $t_core_path . 'mail_api.php' );

	$t_mailaccounts = mail_get_accounts();

	foreach ($t_mailaccounts as $t_mailaccount) {
		if ( config_get( 'mail_debug' ) ) {
			print_r($t_mailaccounts);
		}
		$t_mails = mail_get_all_mails($t_mailaccount);
		foreach ($t_mails as $t_mail)
		{
			if ( config_get( 'mail_debug' ) ) {
				print_r($t_mail);
			}
			$GLOBALS['g_cache_current_user_id'] = mail_get_user( $t_mail['From'] );
                        mail_add_bug($t_mail, $t_mailaccount);
		}
	}
?>

