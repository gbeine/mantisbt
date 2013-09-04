<?php
	# Mantis - a php based bugtracking system
	# Copyright (C) 2002 - 2004  Mantis Team   - mantisbt-dev@lists.sourceforge.net
	# This program is distributed under the terms and conditions of the GPL
	# See the README and LICENSE files for details
    # Copyright (C) 2004  Gerrit Beine - gerrit.beine@pitcom.de
    # Copyright (C) 2004  pitcom GmbH, Plauen, Germany

	# --------------------------------------------------------
	# $Id$
	# --------------------------------------------------------

	$t_core_dir = dirname( __FILE__ ).DIRECTORY_SEPARATOR;
	
	require_once( $t_core_dir . 'bug_api.php' );
	require_once( $t_core_dir . 'bugnote_api.php' );
	require_once( $t_core_dir . 'user_api.php' );
	require_once( $t_core_dir . 'project_api.php' );
?>
<?php
	# This page receives an E-Mail via POP3 and generates an Report
?>
<?php
	require_once( 'Net/POP3.php' );
    
	# --------------------
	# Update the mail account data
	function mail_update( $p_project_id, $p_pop3_host, $p_pop3_user, $p_pop3_pass ) {
		$c_project_id	= db_prepare_int( $p_project_id );
		$c_pop3_host	= db_prepare_string( $p_pop3_host );
		$c_pop3_user	= db_prepare_string( $p_pop3_user );
		$c_pop3_pass	= db_prepare_string( $p_pop3_pass );
		
        $t_project_table = config_get( 'mantis_project_table' );
        
		$query = "UPDATE $t_project_table 
				  SET pop3_host='$c_pop3_host',
					pop3_user='$c_pop3_user',
					pop3_pass='$c_pop3_pass'
				  WHERE id='$c_project_id'";
		db_query( $query );
		return true;
	}

	# --------------------
	# Removes the mail account data
	function mail_delete( $p_project_id ) {
		$c_project_id	= db_prepare_int( $p_project_id );
		
        $t_project_table = config_get( 'mantis_project_table' );
        
		$query = "UPDATE $t_project_table 
				  SET pop3_host=NULL,
					pop3_user=NULL,
					pop3_pass=NULL
				  WHERE id='$c_project_id'";
		db_query( $query );
		return true;
	}

	#===================================
	# Data Access
	#===================================

	# --------------------
	# Return all versions for the specified project
	function mail_get_account_data( $p_project_id ) {
		$c_project_id = db_prepare_int( $p_project_id );

		$t_project_table = config_get( 'mantis_project_table' );

		$query = "SELECT pop3_host, pop3_user, pop3_pass
				  FROM $t_project_table 
				  WHERE id='$c_project_id'";
		$result = db_query( $query );

		return db_fetch_array( $result );
	}

	# --------------------
	# return all projects with valid data for mail access
	#  return an empty array if there are no such projects
	function mail_project_get_all_rows() {
		$m_projects = array();
		$t_projects = project_get_all_rows();
		foreach ($t_projects as $t_project) {
			if ($t_project['pop3_host']) {
				array_push($m_projects, $t_project);
			}
		}
		return $m_projects;
	}

	# --------------------
	# return all mails for a project
	#  return an empty array if there are no new mails
	function mail_get_all_mails($p_project) {
		$t_mails = array();
		$pop3 = &new Net_POP3();
		$t_pop3_host = $p_project['pop3_host'];
		$t_pop3_user = $p_project['pop3_user'];
		$t_pop3_password = $p_project['pop3_pass'];
		$pop3->connect($t_pop3_host, 110);
		$pop3->login($t_pop3_user, $t_pop3_password);
		for ($i = 1; $i <= $pop3->numMsg(); $i++) {
			$mail = $pop3->getParsedHeaders($i);
			$mail['X-Mantis-Body'] = $pop3->getBody($i);
			$mail['X-Mantis-Complete'] = $pop3->getMsg($i);
			array_push($t_mails, $mail);
			$pop3->deleteMsg($i);
		}
		$pop3->disconnect();
		return $t_mails;
	}
    
	# --------------------
	# return the a valid username from an email address
    function mail_user_name_from_address ($p_mailaddress) {
        $t_mailaddress = $p_mailaddress;
        if (preg_match("/<(.*?)>/", $p_mailaddress, $matches)) {
            $t_mailaddress = $matches[1];
        }
        return preg_replace("/[@\.-]/",'_',$t_mailaddress);
    }

	# --------------------
	# return true if there is a valid mantis bug referernce in subject
    function mail_is_a_bugnote ($p_subject) {
        return preg_match("/\[([A-Za-z0-9-_\.]*\s[0-9]{7})\]/", $p_subject);
    }
    
	# --------------------
	# return the bug's id from the subject
    function mail_get_bug_id_from_subject ($p_subject) {
        preg_match("/\[([A-Za-z0-9-_\.]*\s([0-9]{7}?))\]/", $p_subject, $matches);
        return $matches[2];
    }
	# --------------------
	# return the user id for the mail reporting user
	#  return false if no username can be found
    function mail_get_user ($p_mail) {
        $t_mail_use_reporter = config_get( 'mail_use_reporter' );
        $t_mail_auto_signup = config_get( 'mail_auto_signup' );
        if ($t_mail_use_reporter) {
            $t_mail_reporter = config_get( 'mail_reporter' );
            $t_reporter_id = user_get_id_by_name($t_mail_reporter);
        }
        else {
            $t_user_name = mail_user_name_from_address($p_mail['From']);
            $t_reporter_id = user_get_id_by_name($t_user_name);
            if (!$t_reporter_id) { // try to get the user's id searching for mail address
                $t_reporter_id = user_get_id_by_mail($p_mail['From']);
            }
            if (!$t_reporter_id && $t_mail_auto_signup) { // try to signup the user
                user_signup($t_user_name, $p_mail['From']);
                $t_reporter_id = user_get_id_by_name($t_user_name);
            }
        }
        return $t_reporter_id;
    }
    
    # --------------------
    # Adds a bug reported via email
    # Todo: If there is already a bug, add it as a bug note
    function mail_add_bug($p_mail, $p_project) {
		$f_build				= gpc_get_string( 'build', '' );
		$f_platform				= gpc_get_string( 'platform', '' );
		$f_os					= gpc_get_string( 'os', '' );
		$f_os_build				= gpc_get_string( 'os_build', '' );
		$f_product_version		= gpc_get_string( 'product_version', '' );
		$f_profile_id			= gpc_get_int( 'profile_id', 0 );
		$f_handler_id			= gpc_get_int( 'handler_id', 0 );
		$f_view_state			= gpc_get_int( 'view_state', 0 );

		$f_category				= gpc_get_string( 'category', '' );
		$f_priority				= gpc_get_int( 'priority', NORMAL );
		$f_steps_to_reproduce	= gpc_get_string( 'steps_to_reproduce', '' );

        $f_reproducibility		= 10;
        $f_severity				= 50;
        $f_summary				= $p_mail['Subject'];
        $f_description			= $p_mail['X-Mantis-Body'];
        $f_additional_info		= $p_mail['X-Mantis-Complete'];
        $f_project_id			= $p_project['id'];
        $t_reporter_id		    = mail_get_user($p_mail);

        if (mail_is_a_bugnote($p_mail['Subject']))
        {
            # Add a bug note
            $t_bug_id = mail_get_bug_id_from_subject($p_mail['Subject']);
            bugnote_add ( $t_bug_id, $p_mail['X-Mantis-Body'] );
            email_bugnote_add ( $t_bug_id );
        }
        else
        {
	        # Create the bug
	        $t_bug_id = bug_create( $f_project_id,
		    	            $t_reporter_id, $f_handler_id,
                            $f_priority,
				            $f_severity, $f_reproducibility,
					        $f_category,
					        $f_os, $f_os_build,
					        $f_platform, $f_product_version,
					        $f_build,
					        $f_profile_id, $f_summary, $f_view_state,
					        $f_description, $f_steps_to_reproduce, $f_additional_info );
            email_new_bug( $t_bug_id );
        }

    }

?>
