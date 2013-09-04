<?php
	# Mantis - a php based bugtracking system
	# Copyright (C) 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
	# Copyright (C) 2002 - 2004  Mantis Team   - mantisbt-dev@lists.sourceforge.net
	# This program is distributed under the terms and conditions of the GPL
	# See the README and LICENSE files for details
    # Copyright (C) 2004  Gerrit Beine - gerrit.beine@pitcom.de
    # Copyright (C) 2004  pitcom GmbH, Plauen, Germany

	# --------------------------------------------------------
	# $Id$
	# --------------------------------------------------------
?>
<?php
	# This page receives an E-Mail via POP3 and generates an Report
    header("Content-type: text/plain");
?>
<?php
	require_once( 'core.php' );
	
	$t_core_path = config_get( 'core_path' );
	
	require_once( $t_core_path.'string_api.php' );
	require_once( $t_core_path.'bug_api.php' );
	require_once( $t_core_path.'mail_api.php' );
?>
<?php
	$t_projects = mail_project_get_all_rows();
	foreach ($t_projects as $t_project) {
		$t_mails = mail_get_all_mails($t_project);
        foreach ($t_mails as $t_mail)
        {
            mail_add_bug($t_mail, $t_project);
        }
	}
?>

