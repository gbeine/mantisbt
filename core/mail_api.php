<?php
	# Mantis - a php based bugtracking system
	# Copyright (C) 2002 - 2004  Mantis Team   - mantisbt-dev@lists.sourceforge.net
	# Copyright (C) 2004  Gerrit Beine - gerrit.beine@pitcom.de
	# This program is distributed under the terms and conditions of the GPL
	# See the README and LICENSE files for details

	# --------------------------------------------------------
	# $Id$
	# --------------------------------------------------------

	$t_core_dir = dirname( __FILE__ ).DIRECTORY_SEPARATOR;
	
	require_once( $t_core_dir . 'bug_api.php' );
	require_once( $t_core_dir . 'bugnote_api.php' );
	require_once( $t_core_dir . 'user_api.php' );
	require_once( $t_core_dir . 'project_api.php' );
	require_once( $t_core_dir . 'file_api.php' );

	# This page receives an E-Mail via POP3 and generates an Report

	require_once( 'Net/POP3.php' );
	require_once( 'Mail/mimeDecode.php' );

	# --------------------
	# Return mail account data for the specified project
	function mail_get_account_data( $p_project_id ) {
		$v_project_id = db_prepare_int( $p_project_id );

		$t_project_table = config_get( 'mantis_project_table' );

		$query = "SELECT pop3_host, pop3_user, pop3_pass, pop3_categories
				FROM $t_project_table 
				WHERE id='$v_project_id'";

		$result = db_query( $query );

		return db_fetch_array( $result );
	}

	# --------------------
	# Update the mail account data
	function mail_update( $p_project_id, $p_pop3_host, $p_pop3_user, $p_pop3_pass ) {
		$v_project_id	= db_prepare_int( $p_project_id );
		$v_pop3_host	= db_prepare_string( $p_pop3_host );
		$v_pop3_user	= db_prepare_string( $p_pop3_user );
		$v_pop3_pass	= db_prepare_string( $p_pop3_pass );

		$t_project_table = config_get( 'mantis_project_table' );

		$query = "UPDATE $t_project_table 
			 	SET pop3_host='$v_pop3_host',
				pop3_user='$v_pop3_user',
				pop3_pass='$v_pop3_pass'
				WHERE id='$v_project_id'";
		db_query( $query );
		return true;
	}

	# --------------------
	# Removes the mail account data
	function mail_delete( $p_project_id ) {
		$v_project_id	= db_prepare_int( $p_project_id );

		$t_project_table = config_get( 'mantis_project_table' );

		$query = "UPDATE $t_project_table 
				SET pop3_host=NULL,
				pop3_user=NULL,
				pop3_pass=NULL
				WHERE id='$v_project_id'";

		db_query( $query );

		return true;
	}

	# --------------------
	# Activate the Mail per Category feature for a project
	function mail_categories( $p_project_id , $p_active ) {
		$v_project_id	= db_prepare_int( $p_project_id );

		if ($p_active == 'On') {
			$v_active = 1;
		} else {
			$v_active = 0;
		}

		$t_project_table = config_get( 'mantis_project_table' );

		$query = "UPDATE $t_project_table 
				SET pop3_categories='$v_active'
				WHERE id='$v_project_id'";

		db_query( $query );

		return true;
	}

	# --------------------
	# Return mail account data for the specified project and category
	function mail_category_get_account_data( $p_project_id, $p_category ) {
		$c_project_id = db_prepare_int( $p_project_id );
		$c_category	= db_prepare_string( $p_category );

		$t_project_category_table = config_get( 'mantis_project_category_table' );

		$query = "SELECT pop3_host, pop3_user, pop3_pass
				FROM $t_project_category_table 
				WHERE project_id='$c_project_id' AND category='$c_category'";

		$result = db_query( $query );

		return db_fetch_array( $result );
	}

	# --------------------
	# Update the mail account data for a category
	function mail_category_update( $p_project_id, $p_category, $p_pop3_host, $p_pop3_user, $p_pop3_pass ) {
		$c_project_id	= db_prepare_int( $p_project_id );
		$c_category	= db_prepare_string( $p_category );
		$c_pop3_host	= db_prepare_string( $p_pop3_host );
		$c_pop3_user	= db_prepare_string( $p_pop3_user );
		$c_pop3_pass	= db_prepare_string( $p_pop3_pass );

		$t_project_category_table = config_get( 'mantis_project_category_table' );

		$query = "UPDATE $t_project_category_table 
			 	SET pop3_host='$c_pop3_host',
				pop3_user='$c_pop3_user',
				pop3_pass='$c_pop3_pass'
				WHERE project_id='$c_project_id' AND category='$c_category'";
		db_query( $query );
		return true;
	}

	# --------------------
	# Removes the mail account data for a category
	function mail_category_delete( $p_project_id, $p_category ) {
		$c_project_id	= db_prepare_int( $p_project_id );
		$c_category	= db_prepare_string( $p_category );
		
		$t_project_table = config_get( 'mantis_project_table' );

		$t_project_category_table = config_get( 'mantis_project_category_table' );

		$query = "UPDATE $t_project_category_table 
			 	SET pop3_host=NULL,
				pop3_user=NULL,
				pop3_pass=NULL
				WHERE project_id='$c_project_id' AND category='$c_category'";

		db_query( $query );
		return true;
	}

	# --------------------
	# return all mailaccounts
	#  return an empty array if there are no
	function mail_get_accounts() {
		$v_accounts = array();
		$t_projects = mail_project_get_all_rows();

		foreach ($t_projects as $t_project) {
			if ($t_project['pop3_categories']) {
				$v_categories = mail_categories_get_all_rows( $t_project['id'] );
				$v_accounts = array_merge($v_accounts, $v_categories);
			} else {
				array_push($v_accounts, $t_project);
			}
		}

		return $v_accounts;
	}

	# --------------------
	# return all projects with valid data for mail access
	#  return an empty array if there are no such projects
	function mail_project_get_all_rows() {
		$v_projects = array();
		$t_projects = project_get_all_rows();

		foreach ($t_projects as $t_project) {
			if ($t_project['pop3_host'] || $t_project['pop3_categories']) {
				array_push($v_projects, $t_project);
			}
		}

		return $v_projects;
	}

	# --------------------
	# return all projects with valid data for mail access
	#  return an empty array if there are no such projects
	function mail_categories_get_all_rows( $p_project_id ) {
		$v_categories = array();
		$t_categories = category_get_all_rows( $p_project_id );

		foreach ($t_categories as $t_category) {
			if ($t_category['pop3_host']) {
				$t_category['id'] = $p_project_id;
				array_push($v_categories, $t_category);
			}
		}

		return $v_categories;
	}

	# --------------------
	# return all mails for an account
	#  return an empty array if there are no new mails
	function mail_get_all_mails( $p_account ) {
		$v_mails = array();
		$t_pop3 = &new Net_POP3();
		$t_pop3_host = $p_account['pop3_host'];
		$t_pop3_user = $p_account['pop3_user'];
		$t_pop3_password = $p_account['pop3_pass'];
		$t_pop3->connect($t_pop3_host, 110);
		$t_pop3->login($t_pop3_user, $t_pop3_password);

		for ($i = 1; $i <= $t_pop3->numMsg(); $i++) {
                        $t_mail = mail_parse_content( $t_pop3->getMsg($i) );
			array_push($v_mails, $t_mail);
			$t_pop3->deleteMsg($i);
		}

		$t_pop3->disconnect();
		return $v_mails;
	}

	# --------------------
	# return the mail parsed for Mantis
	function mail_parse_content ( $p_mail ) {
                $t_mail = array ();
		$t_decoder = new Mail_mimeDecode($p_mail);
                $t_params['include_bodies'] = true;
                $t_params['decode_bodies']  = true;
                $t_params['decode_headers'] = true;
		$t_structure = $t_decoder->decode($t_params);
                $t_mail['To'] = $t_structure->headers['to'];
                $t_mail['From'] = $t_structure->headers['from'];
                $t_mail['Subject'] = $t_structure->headers['subject'];
                if (is_array($t_structure->parts))
                {
                    $t_parts = mail_parse_parts( $t_structure->parts );
                }
                else
                {
                    $t_parts = array ( mail_parse_part( $t_structure ) );
                }
                if ($t_parts[0]['Content-Type'] == 'text/plain') {
                    $t_body = array_shift($t_parts);
                }
                else
                {
                    $t_body['Body'] = "It seems, there is no text... :-o";
                }
                $t_mail['X-Mantis-Parts'] = $t_parts;
                $t_mail['X-Mantis-Body'] = $t_body['Body'];
		$t_mail['X-Mantis-Complete'] = $p_mail;
		
		return $t_mail;
	}

	# --------------------
	# return the mail parsed for Mantis
	function mail_parse_parts ( $p_parts ) {
                $t_parts = array ();
                foreach ( $p_parts as $t_part ) {
                    array_push($t_parts, mail_parse_part( $t_part ));
                }
                
                return $t_parts;
        }

	# --------------------
	# return the mail parsed for Mantis
	function mail_parse_part ( $p_part ) {
                $t_part = array ();
                $t_part['Content-Type'] = $p_part->ctype_primary."/".$p_part->ctype_secondary;
                $t_part['Name'] = $p_part->ctype_parameters['name'];
                $t_part['Body'] = $p_part->body;

                return $t_part;
        }

	# --------------------
	# return the mailadress from the mail's 'From'
	function mail_parse_address ( $p_mailaddress ) {
		if (preg_match("/<(.*?)>/", $p_mailaddress, $matches)) {
			$c_mailaddress = $matches[1];
		}

		return $c_mailaddress;
	}

	# --------------------
	# return the a valid username from an email address
	function mail_user_name_from_address ( $p_mailaddress ) {
		return preg_replace("/[@\.-]/", '_', $p_mailaddress);
	}

	# --------------------
	# return true if there is a valid mantis bug referernce in subject
	function mail_is_a_bugnote ($p_mail_subject) {
		return preg_match("/\[([A-Za-z0-9-_\.]*\s[0-9]{7})\]/", $p_mail_subject);
	}

	# --------------------
	# return the bug's id from the subject
	function mail_get_bug_id_from_subject ( $p_mail_subject) {
		preg_match("/\[([A-Za-z0-9-_\.]*\s([0-9]{7}?))\]/", $p_mail_subject, $v_matches);

		return $v_matches[2];
	}

	# --------------------
	# return the user id for the mail reporting user
	function mail_get_user ($p_mailaddress) {
		$t_mail_use_reporter	= config_get( 'mail_use_reporter' );
		$t_mail_auto_signup	= config_get( 'mail_auto_signup' );
		$t_mail_reporter	= config_get( 'mail_reporter' );
		
		$c_mailaddress = mail_parse_address( $p_mailaddress );

		if ( $t_mail_use_reporter ) {
			// Always report as mail_reporter
			$t_reporter_id = user_get_id_by_name( $t_mail_reporter );
		} else {
			// Try to get the reporting users id
			$t_reporter_id = user_get_id_by_mail ( $c_mailaddress );
			if ( ! $t_reporter_id && $t_mail_auto_signup ) {
				// So, we've to sign up a new user...
				$t_user_name = mail_user_name_from_address ( $c_mailaddress );
				user_signup($t_user_name, $c_mailaddress);
				$t_reporter_id = user_get_id_by_name($t_user_name);
			} elseif ( ! $t_reporter_id ) {
				// Fall back to the default mail_reporter
				$t_reporter_id = user_get_id_by_name( $t_mail_reporter );
			}
		}

		return $t_reporter_id;
	}

	# --------------------
	# Adds a file to a bug. Very dirty
	function mail_add_file( $p_bug_id, $p_part ) {
            $GLOBALS['_mail_file_'] = $p_part['Name'];
            $t_file_name = '/tmp/'.$p_part['Name'];
            file_put_contents($t_file_name, $p_part['Body']);
            file_add($p_bug_id, $t_file_name,  $p_part['Name'], $p_part['Content-Type'], 'bug');
            unlink($t_file_name);
        }

	# --------------------
	# Adds a bug which is reported via email
	# Taken from bug_report.php and 
	function mail_add_bug( $p_mail, $p_account ) {
		$t_bug_data = new BugData;
		$t_bug_data->build			= gpc_get_string( 'build', '' );
		$t_bug_data->platform			= gpc_get_string( 'platform', '' );
		$t_bug_data->os				= gpc_get_string( 'os', '' );
		$t_bug_data->os_build			= gpc_get_string( 'os_build', '' );
		$t_bug_data->version			= gpc_get_string( 'product_version', '' );
		$t_bug_data->profile_id			= gpc_get_int( 'profile_id', 0 );
		$t_bug_data->handler_id			= gpc_get_int( 'handler_id', 0 );
		$t_bug_data->view_state			= gpc_get_int( 'view_state', config_get( 'default_bug_view_status' ) );

		if ( $p_account['category']) {
			$t_bug_data->category			= gpc_get_string( 'category', $p_account['category'] );
		} else {
			$t_bug_data->category			= gpc_get_string( 'category', '' );
		}
		$t_bug_data->reproducibility		= 10;
		$t_bug_data->severity			= 50;
		$t_bug_data->priority			= gpc_get_int( 'priority', NORMAL );
		$t_bug_data->summary			= $p_mail['Subject'];
		$t_bug_data->description		= $p_mail['X-Mantis-Body'];
		$t_bug_data->steps_to_reproduce		= gpc_get_string( 'steps_to_reproduce', '' );
		$t_bug_data->additional_information	= $p_mail['X-Mantis-Complete'];

		$t_bug_data->project_id			= $p_account['id'];

		$t_bug_data->reporter_id		= mail_get_user( $p_mail['From'] );

                if (mail_is_a_bugnote($p_mail['Subject']))
		{
			# Add a bug note
			$t_bug_id = mail_get_bug_id_from_subject( $p_mail['Subject'] );
			if ( ! bug_is_readonly( $t_bug_id ) ) {
				bugnote_add ( $t_bug_id, $p_mail['X-Mantis-Body'] );
				email_bugnote_add ( $t_bug_id );
			}
		}
		else
		{
			# Create the bug
			$t_bug_id = bug_create( $t_bug_data );
			email_new_bug( $t_bug_id );
		}
                # Add files
                foreach ($p_mail['X-Mantis-Parts'] as $part) {
                    mail_add_file ( $t_bug_id, $part );
                }

	}

?>
