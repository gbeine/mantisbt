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
	require_once( 'Mail/Parser.php' );

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
	# Update the mail account data for a project
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
	# Removes the mail account data from a project
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
	# Activate the 'Mail per Category' feature for a project
	function mail_categories( $p_project_id, $p_active ) {
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
		$v_project_id	= db_prepare_int( $p_project_id );
		$v_category	= db_prepare_string( $p_category );

		$t_project_category_table = config_get( 'mantis_project_category_table' );

		$query = "SELECT pop3_host, pop3_user, pop3_pass
				FROM $t_project_category_table 
				WHERE project_id='$v_project_id' AND category='$v_category'";

		$result = db_query( $query );

		return db_fetch_array( $result );
	}

	# --------------------
	# Update the mail account data for a category
	function mail_category_update( $p_project_id, $p_category, $p_pop3_host, $p_pop3_user, $p_pop3_pass ) {
		$v_project_id	= db_prepare_int( $p_project_id );
		$v_category	= db_prepare_string( $p_category );
		$v_pop3_host	= db_prepare_string( $p_pop3_host );
		$v_pop3_user	= db_prepare_string( $p_pop3_user );
		$v_pop3_pass	= db_prepare_string( $p_pop3_pass );

		$t_project_category_table = config_get( 'mantis_project_category_table' );

		$query = "UPDATE $t_project_category_table 
			 	SET pop3_host='$v_pop3_host',
				pop3_user='$v_pop3_user',
				pop3_pass='$v_pop3_pass'
				WHERE project_id='$v_project_id' AND category='$v_category'";

		db_query( $query );

		return true;
	}

	# --------------------
	# Removes the mail account data for a category
	function mail_category_delete( $p_project_id, $p_category ) {
		$v_project_id	= db_prepare_int( $p_project_id );
		$v_category	= db_prepare_string( $p_category );
		
		$t_project_table = config_get( 'mantis_project_table' );

		$t_project_category_table = config_get( 'mantis_project_category_table' );

		$query = "UPDATE $t_project_category_table 
			 	SET pop3_host=NULL,
				pop3_user=NULL,
				pop3_pass=NULL
				WHERE project_id='$v_project_id' AND category='$v_category'";

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
	# return all categories from a project with valid data for mail access
	#  return an empty array if there are no such categories
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
	function mail_process_all_mails( &$p_account ) {
		$t_mail_fetch_max	= config_get( 'mail_fetch_max' );
		$t_mail_delete		= config_get( 'mail_delete' );
		$t_mail_auth_method	= config_get( 'mail_auth_method' );


		$t_pop3 = &new Net_POP3();
		$t_pop3_host = $p_account['pop3_host'];
		$t_pop3_user = $p_account['pop3_user'];
		$t_pop3_password = $p_account['pop3_pass'];
		$t_pop3->connect($t_pop3_host, 110);
		$t_result = $t_pop3->login($t_pop3_user, $t_pop3_password, $t_mail_auth_method);

		if (PEAR::isError($t_result)) {
		    echo "\n\nerror:".$p_account['pop3_user']."\n";
		    echo $t_result->toString();
		}

		if ( 0 == $t_pop3->numMsg() ) {
			return;
		}

		
		for ($j = 1; $j <= $t_pop3->numMsg(); $j++ )
		{
			for ($i = $j; $i < $j+$t_mail_fetch_max; $i++) {
				$t_msg = $t_pop3->getMsg($i);

				$t_mail = mail_parse_content( $t_msg );


				if ( $t_mail_debug ) {
					print_r($t_mail);
				}

				mail_save_message_to_file( $t_msg );
				mail_add_bug( $t_mail, $p_account );

				if ( $t_mail_delete ) {
					$t_pop3->deleteMsg($i);
				}
			}
		}

		$t_pop3->disconnect();
	}

	# --------------------
	# return the mail parsed for Mantis
	function mail_parse_content ( &$p_mail ) {
		$t_mail_debug		= config_get( 'mail_debug' );
		$t_mail_parse_mime	= config_get( 'mail_parse_mime' );
		$t_mail_parse_html	= config_get( 'mail_parse_html' );
		$t_mail_html_parser	= config_get( 'mail_html_parser' );
		$t_mail_tmp_directory	= config_get( 'mail_tmp_directory' );
		$t_mail_use_bug_priority = config_get( 'mail_use_bug_priority' );
		$t_mail_bug_priority_default = config_get( 'mail_bug_priority_default' );
		$t_mail_bug_priority	= config_get( 'mail_bug_priority' );
		$t_mail_additional	= config_get( 'mail_additional' );

		$t_options = array();
		$t_options['parse_mime'] = $t_mail_parse_mime;
		$t_options['parse_html'] = $t_mail_parse_html;
		$t_options['htmlparser'] = $t_mail_html_parser;
		$t_options['htmltmpdir'] = $t_mail_tmp_directory;

		$t_mp = new Mail_Parser( $t_options );
		$t_mp->setInputString( $p_mail );
		$t_mp->parse();

		$t_mail = array();
		$t_mail['From'] = $t_mp->from();

		$t_mail['Subject'] = $t_mp->subject();
                if ( 0 == strlen( $t_mail['Subject'] ) ) {
		    $t_mail['Subject'] = "No subject found";
		}

		$t_mail['X-Mantis-Body'] = $t_mp->body();
                if ( 0 == strlen( $t_mail['X-Mantis-Body'] ) ) {
		    $t_mail['X-Mantis-Body'] = "No description found";
		}

                $t_mail['X-Mantis-Parts'] = $t_mp->parts();

		if ( true == $t_mail_use_bug_priority ) {
			$t_priority =  strtolower($t_mp->priority());
			$t_mail['Priority'] = $t_mail_bug_priority[$t_priority];
		} else {
			$t_mail['Priority'] = gpc_get_int( 'priority', $t_mail_bug_priority_default );
		}
		if ( true == $t_mail_additional ) {
			$t_mail['X-Mantis-Complete'] = $t_msg;
		}

		return $t_mail;
	}

	# --------------------
	# return the mailadress from the mail's 'From'
	function mail_parse_address ( $p_mailaddress ) {
		if (preg_match("/<(.*?)>/", $p_mailaddress, $matches)) {
			$v_mailaddress = $matches[1];
		}

		return $v_mailaddress;
	}

	# --------------------
	# return the a valid username from an email address
	function mail_user_name_from_address ( $p_mailaddress ) {
		return strtolower(preg_replace("/[@\.-]/", '_', $p_mailaddress));
	}

	# --------------------
	# return true if there is a valid mantis bug referernce in subject
	function mail_is_a_bugnote ( $p_mail_subject ) {
		return preg_match("/\[([A-Za-z0-9-_\. ]*\s[0-9]{1,7})\]/", $p_mail_subject);
	}

	# --------------------
	# return the bug's id from the subject
	function mail_get_bug_id_from_subject ( $p_mail_subject ) {
		preg_match("/\[([A-Za-z0-9-_\. ]*\s([0-9]{1,7}?))\]/", $p_mail_subject, $v_matches);

		return $v_matches[2];
	}
	
	# --------------------
	# return the user id for the mail reporting user
	function mail_get_user ($p_mailaddress) {
		$t_mail_use_reporter	= config_get( 'mail_use_reporter' );
		$t_mail_auto_signup	= config_get( 'mail_auto_signup' );
		$t_mail_reporter	= config_get( 'mail_reporter' );
		
		$v_mailaddress = mail_parse_address( $p_mailaddress );

		if ( $t_mail_use_reporter ) {
			// Always report as mail_reporter
			$t_reporter_id = user_get_id_by_name( $t_mail_reporter );
			$t_reporter = $t_mail_reporter;
		} else {
			// Try to get the reporting users id
			$t_reporter_id = user_get_id_by_mail ( $v_mailaddress );
			print_r($t_reporter_id);
			if ( ! $t_reporter_id && $t_mail_auto_signup ) {
				// So, we've to sign up a new user...
				$t_reporter = mail_user_name_from_address ( $v_mailaddress );
				user_signup ( $t_reporter, $v_mailaddress );
				$t_reporter_id = user_get_id_by_name ( $t_reporter );
			} elseif ( ! $t_reporter_id ) {
				// Fall back to the default mail_reporter
				$t_reporter_id = user_get_id_by_name( $t_mail_reporter );
				$t_reporter = $t_mail_reporter;
			} else {
				$t_reporter = user_get_field( $t_reporter_id, 'username' );
			}
		}

		auth_attempt_script_login($t_reporter);

		return $t_reporter_id;
	}

	# --------------------
	# Very dirty: Adds a file to a bug.
	function mail_add_file( $p_bug_id, $p_part, $number ) {
		$GLOBALS['_mail_file_'] = $p_part['name'];
		if ( 0 < strlen($p_part['name']) ) {
			$t_file_name = '/tmp/'.$p_part['name'];
			file_put_contents($t_file_name, $p_part['body']);
			file_add($p_bug_id, $t_file_name,  $number."-".$p_part['name'], $p_part['ctype'], 'bug');
			unlink($t_file_name);
		}
	}

	function mail_save_message_to_file ( &$p_msg ) {
		$t_mail_debug		= config_get( 'mail_debug' );
		$t_mail_directory	= config_get( 'mail_directory' );
		
		if ( is_dir($t_mail_directory) && is_writeable($t_mail_directory) ) {
			$t_file_name = $t_mail_directory . '/' . time() . md5($p_msg);
			file_put_contents($t_file_name, $p_msg);
		}
	}

	# --------------------
	# Adds a bug which is reported via email
	# Taken from bug_report.php and 
	function mail_add_bug ( &$p_mail, &$p_account ) {
		$t_mail_save_from	= config_get( 'mail_save_from' );

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
		$t_bug_data->priority			= $p_mail['Priority'];
		$t_bug_data->summary			= $p_mail['Subject'];
		if ( $t_mail_save_from ) {
			$t_bug_data->description	= "Report from: ".$p_mail['From']."\n\n".$p_mail['X-Mantis-Body'];
		} else {
			$t_bug_data->description	= $p_mail['X-Mantis-Body'];
		}
		$t_bug_data->steps_to_reproduce		= gpc_get_string( 'steps_to_reproduce', '' );
		$t_bug_data->additional_information	= $p_mail['X-Mantis-Complete'];

		$t_bug_data->project_id			= $p_account['id'];

		$t_bug_data->reporter_id		= mail_get_user( $p_mail['From'] );

		if ( mail_is_a_bugnote( $p_mail['Subject'] ) ) {
			# Add a bug note
			$t_bug_id = mail_get_bug_id_from_subject( $p_mail['Subject'] );
			if ( ! bug_is_readonly( $t_bug_id ) ) {
				bugnote_add ( $t_bug_id, $p_mail['X-Mantis-Body'] );
				email_bugnote_add ( $t_bug_id );
				if ( bug_get_field( $t_bug_id, 'status' ) > config_get( 'bug_reopen_status' ) )
				{
				    bug_reopen( $t_bug_id );
				}
			}
		} else	{
			# Create the bug
			$t_bug_id = bug_create( $t_bug_data );
			email_new_bug( $t_bug_id );
		}
		# Add files
		if ( null != $p_mail['X-Mantis-Parts'] ) {
			$number = 1;
			foreach ($p_mail['X-Mantis-Parts'] as $part) {
				mail_add_file ( $t_bug_id, $part, $number );
				$number++;
			}
		}

	}

?>

