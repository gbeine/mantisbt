<?php
	# Mantis - a php based bugtracking system
	# Copyright (C) 2002 - 2004  Mantis Team   - mantisbt-dev@lists.sourceforge.net
	# Copyright (C) 2004  Gerrit Beine - gerrit.beine@pitcom.de
	# This program is distributed under the terms and conditions of the GPL
	# See the README and LICENSE files for details

	# --------------------------------------------------------
	# $Id$
	# --------------------------------------------------------

	require_once( 'core.php' );
	
	$t_core_path = config_get( 'core_path' );
	
	require_once( $t_core_path . 'mail_api.php' );

	$f_project_id	= gpc_get_int( 'project_id' );
	$f_pop3_host	= gpc_get_string( 'pop3_host' );
	$f_pop3_user	= gpc_get_string( 'pop3_user' );
	$f_pop3_pass	= gpc_get_string( 'pop3_pass' );
	$f_category	= gpc_get_string( 'category' );

	access_ensure_project_level( config_get( 'manage_project_threshold' ), $f_project_id );

	if ( is_blank( $f_pop3_host ) ) {
			trigger_error( ERROR_EMPTY_FIELD, ERROR );
	}
	else {
		if ( is_blank( $f_pop3_user ) ) {
			trigger_error( ERROR_EMPTY_FIELD, ERROR );
        }
		if ( is_blank( $f_pop3_pass ) ) {
			trigger_error( ERROR_EMPTY_FIELD, ERROR );
        }
	}

	mail_category_update( $f_project_id, $f_category, $f_pop3_host, $f_pop3_user, $f_pop3_pass );

	$t_redirect_url = 'manage_proj_cat_edit_page.php?project_id=' . $f_project_id . '&category=' . $f_category;

	html_page_top1();

	html_meta_redirect( $t_redirect_url );

	html_page_top2();
?>

<br />
<div align="center">
<?php
	echo lang_get( 'operation_successful' ) . '<br />';

	print_bracket_link( $t_redirect_url, lang_get( 'proceed' ) );
?>
</div>

<?php html_page_bottom1( __FILE__ ) ?>
