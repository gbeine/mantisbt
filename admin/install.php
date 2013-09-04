<?php
	# Mantis - a php based bugtracking system
	# Copyright (C) 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
	# Copyright (C) 2002 - 2004  Mantis Team   - mantisbt-dev@lists.sourceforge.net
	# This program is distributed under the terms and conditions of the GPL
	# See the README and LICENSE files for details

	# --------------------------------------------------------
	# $Id: install.php,v 1.22 2005-08-10 17:10:12 thraxisp Exp $
	# --------------------------------------------------------
?>
<?php
	error_reporting( E_ALL );

	//@@@ put this somewhere
	set_time_limit ( 0 ) ;
	$g_skip_open_db = true;  # don't open the database in database_api.php
	@require_once( dirname( dirname( __FILE__ ) ) . DIRECTORY_SEPARATOR . 'core.php' );
	$g_error_send_page_header = false; # bypass page headers in error handler

	define( 'BAD', 0 );
	define( 'GOOD', 1 );
	$g_failed = false;

	# -------
	# print test result
	function print_test_result( $p_result, $p_hard_fail=true, $p_message='' ) {
		global $g_failed;
		echo '<td ';
		if ( BAD == $p_result ) {
			if ( $p_hard_fail ) {
				$g_failed = true;
				echo 'bgcolor="red">BAD';
			} else {
				echo 'bgcolor="pink">POSSIBLE PROBLEM';
			}
			if ( '' != $p_message ) {
				echo '<br />' . $p_message;
			}
		}

		if ( GOOD == $p_result ) {
			echo 'bgcolor="green">GOOD';
		}
		echo '</td>';
	}

	# -------
	# print test header and result
	function print_test( $p_test_description, $p_result, $p_hard_fail=true, $p_message='' ) {

		echo "\n<tr><td bgcolor=\"#ffffff\">$p_test_description</td>";
		print_test_result( $p_result, $p_hard_fail, $p_message );
		echo "</tr>\n";
	}

	# --------
	# create an SQLArray to insert data
	function InsertData( $p_table, $p_data ) {
		$query = "INSERT INTO " . $p_table . $p_data;
		return Array( $query );
	}



	# install_state
	#   0 = no checks done
	#   1 = server ok, get database information
	#   2 = check the database information
	#   3 = install the database
	#   4 = get additional config file information
	#   5 = write the config file
	#	6 = post install checks
	#	7 = done, link to login or db updater
	$t_install_state = gpc_get_int( 'install', 0 );

	# read control variables with defaults
	$f_hostname = gpc_get( 'hostname', config_get( 'hostname', 'localhost' ) );
	$f_db_type = gpc_get( 'db_type', config_get( 'db_type', '' ) );
	$f_database_name = gpc_get( 'database_name', config_get( 'database_name', 'bugtrack') );
	$f_db_username = gpc_get( 'db_username', config_get( 'db_username', '' ) );
	$f_db_password = gpc_get( 'db_password', config_get( 'db_password', '' ) );
	$f_admin_username = gpc_get( 'admin_username', '' );
	$f_admin_password = gpc_get( 'admin_password', '');
	$f_log_queries = gpc_get_bool( 'log_queries', false );
	$f_db_exists = gpc_get_bool( 'db_exists', false );
?>
<html>
<head>
<title> Mantis Administration - Installation  </title>
<link rel="stylesheet" type="text/css" href="admin.css" />
</head>
<body>
<table width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#ffffff">
	<tr class="top-bar">
		<td class="links">
			[ <a href="index.php">Back to Administration</a> ]
		</td>
		<td class="title">
		<?php
			switch ( $t_install_state ) {
				case 6:
					echo "Post Installation Checks";
					break;
				case 5:
					echo "Install Configuration File";
					break;
				case 4:
					echo "Additional Configuration Information";
					break;
				case 3:
					echo "Install Database";
					break;
				case 2:
					echo "Check and Install Database";
					break;
				case 1:
					echo "Database Parameters";
					break;
				case 0:
				default:
					echo "Pre-Installation Check";
					break;
			}
		?>
		</td>
	</tr>
</table>
<br /><br />

<form method='POST'>
<?php
if ( 0 == $t_install_state ) {
?>
<table width="100%" bgcolor="#222222" border="0" cellpadding="10" cellspacing="1">
<tr>
	<td bgcolor="#e8e8e8" colspan="2">
		<span class="title">Checking Installation...</span>
	</td>
</tr>

<!-- Check PHP Version -->
<tr>
	<td bgcolor="#ffffff">
		Checking  PHP Version (Your version is <?php echo phpversion(); ?>)
	</td>
	<?php
		if (phpversion() == '4.0.6') {
			print_test_result( GOOD );
		} else {
			if ( function_exists ( 'version_compare' ) ) {
				if ( version_compare ( phpversion() , '4.0.6', '>=' ) ) {
					print_test_result( GOOD );
				} else {
					print_test_result( BAD, true, 'Upgrade the version of PHP to a more recent version' );
				}
			} else {
			 	print_test_result( BAD, true, 'Upgrade the version of PHP to a more recent version' );
			}
		}
	?>
</tr>

<!-- Check Safe Mode -->
<?php print_test( 'Checking If Safe mode is enabled for install script',
		! ini_get ( 'SAFE_MODE' ),
		true,
		'Disable safe_mode in php.ini before proceeding' ) ?>

</table>
<?php
	if ( false == $g_failed ) {
		$t_install_state++;
	}
} # end install_state == 0

# got database information, check and install
if ( 2 == $t_install_state ) {
?>

<table width="100%" border="0" cellpadding="10" cellspacing="1">
<!-- Setting config variables -->
<?php print_test( 'Setting Database Hostname', '' !== $f_hostname , true, 'host name is blank' ) ?>

<!-- Setting config variables -->
<?php print_test( 'Setting Database Type', '' !== $f_db_type , true, 'database type is blank?' ) ?>

<!-- Checking DB support-->
<tr>
	<td bgcolor="#ffffff">
		Checking PHP support for database type
	</td>
	<?php
			$t_support = false;
			switch ($f_db_type) {
				case 'mysql':
					$t_support = function_exists('mysql_connect');
					break;
				case 'pgsql':
					$t_support = function_exists('pg_connect');
					break;
				case 'mssql':
				case 'odbc_mssql':
				case 'ado_mssql':
					$t_support = function_exists('mssql_connect');
					break;
				default:
					$t_support = false;
			}

			if ( $t_support ) {
				print_test_result( GOOD );
			} else {
				print_test_result( BAD, true, 'database is not supported by PHP. Check that it has been compiled into your server.' );
			}
	?>
</tr>

<?php print_test( 'Setting Database Username', '' !== $f_db_username , true, 'database username is blank' ) ?>
<?php print_test( 'Setting Database Password', '' !== $f_db_password , false, 'database password is blank' ) ?>
<?php print_test( 'Setting Database Name', '' !== $f_database_name , true, 'database name is blank' )?>
<tr>
	<td bgcolor="#ffffff">
		Setting Admin Username
	</td>
	<?php
			if ( '' !== $f_admin_username ) {
				print_test_result( GOOD );
			} else {
				print_test_result( BAD, false, 'admin user name is blank, using database user instead' );
				$f_admin_username = $f_db_username;
			}
	?>
</tr>
<tr>
	<td bgcolor="#ffffff">
		Setting Admin Password
	</td>
	<?php
			if ( '' !== $f_admin_password ) {
				print_test_result( GOOD );
			} else {
				print_test_result( BAD, false, 'admin user password is blank, using database user password instead' );
				$f_admin_password = $f_db_password;
			}
	?>
</tr>

<!-- connect to db -->
<tr>
	<td bgcolor="#ffffff">
		Attempting to connect to database as admin
	</td>
	<?php
		$g_db = ADONewConnection($f_db_type);
		$t_result = @$g_db->Connect($f_hostname, $f_admin_username, $f_admin_password);

		if ( $t_result == true ) {
			print_test_result( GOOD );
			# check if db exists for the admin
			$t_result = @$g_db->Connect($f_hostname, $f_admin_username, $f_admin_password, $f_database_name);
			if ( $t_result == true ) {
				$f_db_exists = true;
			}
		} else {
			print_test_result( BAD, true, 'Does administrative user have access to the database? ( ' .  db_error_msg() . ' )' );
		}
	?>
</tr>

<!-- display database version -->
<tr>
	<td bgcolor="#ffffff">
		Checking Database Server Version
		<?php
			$t_version_info = $g_db->ServerInfo();
			echo '<br /> Running ' . $f_db_type . ' version ' . $t_version_info['description'];
		?>
	</td>
	<?php
		$t_warning = '';
		$t_error = '';
		switch ( $f_db_type ) {
			case 'mysql':
				if ( function_exists ( 'version_compare' ) ) {
					if ( version_compare ( $t_version_info['version'] , '4.1.0', '>' ) ) {
						$t_warning = 'Please ensure that you installation supports the new password scheme used in MySQL 4.1.0 and later. See ' .
							'<a href="http://dev.mysql.com/doc/mysql/en/password-hashing.html">http://dev.mysql.com/doc/mysql/en/password-hashing.html</a>.';
					}
				}
				break;
			case 'pgsql':
			case 'mssql':
			case 'odbc_mssql':
			case 'ado_mssql':
			default:
		}
			
		print_test_result( ( '' == $t_error ) && ( '' == $t_warning ), ( '' != $t_error ), $t_error . ' ' . $t_warning );
	?>
</tr>
<?php
	if ( $f_db_exists ) {
?>
<tr>
	<td bgcolor="#ffffff">
		Attempting to connect to database as user
	</td>
	<?php
		$g_db = ADONewConnection($f_db_type);
		$t_result = @$g_db->Connect($f_hostname, $f_db_username, $f_db_password, $f_database_name);

		if ( $t_result == true ) {
			print_test_result( GOOD );
		} else {
			print_test_result( BAD, false, 'Database user doesn\'t have access to the database ( ' .  db_error_msg() . ' )' );
		}
	?>
</tr>

<?php
	}
	if ( false == $g_failed ) {
		$t_install_state++;
	} else {
		$t_install_state--;	# a check failed, redisplay the questions
	}
} # end 2 == $t_install_state

# system checks have passed, get the database information
if ( 1 == $t_install_state ) {
?>

<table width="100%" border="0" cellpadding="10" cellspacing="1">
<tr>
	<td bgcolor="#e8e8e8" colspan="2">
		<span class="title">Installation Options</span>
	</td>
</tr>

<tr>
	<td>
		Type of Database
	</td>
	<td>
		<select name="db_type">
		<option value="mysql">MySql (default)</option>
		<option value="odbc_mssql">Microsoft SQL Server ODBC (experimental)</option>
		<option value="ado_mssql">Microsoft SQL Server ADO (experimental)</option>
		<option value="pgsql">PGSQL (experimental)</option>
	</td>
</tr>

<tr>
	<td>
		Hostname (for Database Server)
	</td>
	<td>
		<input name="hostname" type="textbox" value="<?php echo $f_hostname ?>"></input>
	</td>
</tr>

<tr>
	<td>
		Username (for Database)
	</td>
	<td>
		<input name="db_username" type="textbox" value="<?php echo $f_db_username ?>"></input>
	</td>
</tr>

<tr>
	<td>
		Password (for Database)
	</td>
	<td>
		<input name="db_password" type="password" value="<?php echo $f_db_password ?>"></input>
	</td>
</tr>

<tr>
	<td>
		Database name (for Database)
	</td>
	<td>
		<input name="database_name" type="textbox" value="<?php echo $f_database_name ?>"></input>
	</td>
</tr>
<tr>
	<td>
		Admin Username (to create Database)
	</td>
	<td>
		<input name="admin_username" type="textbox" value="<?php echo $f_admin_username ?>"></input>
	</td>
</tr>

<tr>
	<td>
		Admin Password (to create Database)
	</td>
	<td>
		<input name="admin_password" type="password" value="<?php echo $f_admin_password  ?>"></input>
	</td>
</tr>

<tr>
	<td>
		Print SQL Queries instead of Writing to the Database
	</td>
	<td>
		<input name="log_queries" type="checkbox" value="1" <?php echo ( $f_log_queries ? 'checked="checked"' : '' ) ?>></input>
	</td>
</tr>

<tr>
	<td>
		Attempt Installation
	</td>
	<td>
		<input name="go" type="submit" value="Install/Upgrade Database"></input>
	</td>
</tr>
<input name="install" type="hidden" value="2"></input>

</table>
<?php
}  # end install_state == 1

# all checks have passed, install the database
if ( 3 == $t_install_state ) {
?>

<table width="100%" border="0" cellpadding="10" cellspacing="1">
<tr>
	<td bgcolor="#e8e8e8" colspan="2">
		<span class="title">Installing Database</span>
	</td>
</tr>
<?php if ( ! $f_log_queries ) { ?>
<tr>
	<td bgcolor="#ffffff">
		Create database if it does not exist
	</td>
	<?php
		$t_result = @$g_db->Connect( $f_hostname, $f_admin_username, $f_admin_password, $f_database_name );
		$g_db->Close();

		if ( $t_result == true ) {
			print_test_result( GOOD );
		} else {
			// create db
			$g_db = ADONewConnection( $f_db_type );
			$t_result = $g_db->Connect( $f_hostname, $f_admin_username, $f_admin_password );
			$dict = NewDataDictionary( $g_db );
			$sqlarray = $dict->CreateDatabase( $f_database_name );
			$ret = $dict->ExecuteSQLArray( $sqlarray );
			if( $ret == 2) {
				print_test_result( GOOD );
			} else {
				print_test_result( BAD, true, 'Does administrative user have access to create the database? ( ' .  db_error_msg() . ' )' );
				$t_install_state--;	# db creation failed, allow user to re-enter user/password info
			}
			$g_db->Close();
		}
	?>
</tr>
<tr>
	<td bgcolor="#ffffff">
		Attempting to connect to database as user
	</td>
	<?php
		$g_db = ADONewConnection($f_db_type);
		$t_result = @$g_db->Connect($f_hostname, $f_db_username, $f_db_password, $f_database_name);

		if ( $t_result == true ) {
			print_test_result( GOOD );
		} else {
			print_test_result( BAD, false, 'Database user doesn\'t have access to the database ( ' .  db_error_msg() . ' )' );
		}
		$g_db->Close();
	?>
</tr>
<?php
	}
	# install the tables
	if ( false == $g_failed ) {
		$g_db_connected = false; # fake out database access routines used by config_get
		require_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'schema.php' );
		$g_db = ADONewConnection( $f_db_type );
		$t_result = @$g_db->Connect( $f_hostname, $f_admin_username, $f_admin_password, $f_database_name );
		if ( ! $f_log_queries ) {
			$g_db_connected = true; # fake out database access routines used by config_get
		}
		$t_last_update = config_get( 'database_version', -1 );
		$lastid = sizeof( $upgrade ) - 1;
		$i = $t_last_update + 1;
		if ( $f_log_queries ) {
			echo '<tr><td bgcolor="#ffffff" col_span="2"> Database Creation Suppressed, SQL Queries follow <pre>';
		}

		while ( ( $i <= $lastid ) && ! $g_failed ) {
			if ( ! $f_log_queries ) {
				echo '<tr><td bgcolor="#ffffff">Create Schema ( ' . $upgrade[$i][0] . ' on ' . $upgrade[$i][1][0] . ' )</td>';
			}

			$dict = NewDataDictionary($g_db);
			if ( $upgrade[$i][0] == 'InsertData' ) {
				$sqlarray = call_user_func_array( $upgrade[$i][0], $upgrade[$i][1] );
			} else {
				$sqlarray = call_user_func_array(Array($dict,$upgrade[$i][0]),$upgrade[$i][1]);
			}
			if ( $f_log_queries ) {
				foreach ( $sqlarray as $sql ) {
					echo htmlentities( $sql ) . ";\r\n\r\n";
				}
			} else {
				$ret = $dict->ExecuteSQLArray($sqlarray);
				if ( $ret == 2 ) {
					print_test_result( GOOD );
					config_set( 'database_version', $i );
				} else {
					print_test_result( BAD, true, $sqlarray[0] . '<br />' . $g_db->ErrorMsg() );
				}
				echo '</tr>';
			}
			$i++;
		}
		if ( $f_log_queries ) {
			# add a query to set the database version
			echo 'INSERT INTO mantis_config_table ( value, type, access_reqd, config_id, project_id, user_id ) VALUES (\'' . $lastid . '\', 1, 90, \'database_version\', 20, 0 );' . "\r\n";
			echo '</pre></br /><p style="color:red">Your database has not been created yet. Please create the database, then install the tables and data using the information above before proceeding</td></tr>';
		}

	}
	if ( false == $g_failed ) {
		$t_install_state++;
	} else {
		$t_install_state--;
	}

?>
</table>
<?php
}  # end install_state == 3

# database installed, get any additional information
if ( 4 == $t_install_state ) {
	# @@@ to be written
	#  must post data gathered to preserve it
?>
		<input name="hostname" type="hidden" value="<?php echo $f_hostname ?>"></input>
		<input name="db_type" type="hidden" value="<?php echo $f_db_type ?>"></input>
		<input name="database_name" type="hidden" value="<?php echo $f_database_name ?>"></input>
		<input name="db_username" type="hidden" value="<?php echo $f_db_username ?>"></input>
		<input name="db_password" type="hidden" value="<?php echo $f_db_password ?>"></input>
		<input name="admin_username" type="hidden" value="<?php echo $f_admin_username ?>"></input>
		<input name="admin_password" type="hidden" value="<?php echo $f_admin_password ?>"></input>
		<input name="log_queries" type="hidden" value="<?php echo ( $f_log_queries ? 1 : 0 ) ?>"></input>
		<input name="db_exists" type="hidden" value="<?php echo ( $f_db_exists ? 1 : 0 ) ?>"></input>
<?php
	# must post <input name="install" type="hidden" value="5"></input>
	# rather than the following line
		$t_install_state++;
}  # end install_state == 4

# all checks have passed, install the database
if ( 5 == $t_install_state ) {
?>
<table width="100%" border="0" cellpadding="10" cellspacing="1">
<tr>
	<td bgcolor="#e8e8e8" colspan="2">
		<span class="title">Write Configuration File(s)</span>
	</td>
</tr>

<tr>
	<td bgcolor="#ffffff">
		Creating Default Config File<br />
		<font color="red">(if this file is not created, create it manually with the contents below)</font>
	</td>
	<?php
		$t_config = '<?php'."\r\n";
		$t_config .= "\t\$g_hostname = '$f_hostname';\r\n";
		$t_config .= "\t\$g_db_type = '$f_db_type';\r\n";
		$t_config .= "\t\$g_database_name = '$f_database_name';\r\n";
		$t_config .= "\t\$g_db_username = '$f_db_username';\r\n";
		$t_config .= "\t\$g_db_password = '$f_db_password';\r\n";
		$t_config .= '?>' . "\r\n";
		$t_write_failed = true;

		$t_config_filename = $g_absolute_path . 'config_inc.php';
		if ( !file_exists ( $t_config_filename ) ) {
			if ( is_writable( $t_config_filename ) ) {
				$fd = fopen( $t_config_filename, 'x' );
				fwrite( $fd, $t_config );
				fclose( $fd );
			}

			if ( file_exists ( $t_config_filename ) ) {
				print_test_result( GOOD );
				$t_write_failed = false;
			} else {
				print_test_result( BAD, false, 'cannot write ' . $t_config_filename );
			}
		} else {
			# already exists, see if the information is the same
			if ( ( $f_hostname != config_get( 'hostname', '' ) ) ||
					( $f_db_type != config_get( 'db_type', '' ) ) ||
					( $f_database_name != config_get( 'database_name', '') ) ||
					( $f_db_username != config_get( 'db_username', '' ) ) ||
					( $f_db_password != config_get( 'db_password', '' ) ) ) {
				print_test_result( BAD, false, 'file ' . $g_absolute_path . 'config_inc.php' . ' already exists and has different settings' );
			} else {
				print_test_result( GOOD, false, 'file not updated' );
				$t_write_failed = false;
			}
		}
	?>
</tr>
<?php
	if ( true == $t_write_failed ) {
		echo '<tr><table width="50%" border="0" cellpadding="10" cellspacing="1" align="center">';
		echo '<tr><td>Please add the following lines to ' . $g_absolute_path . 'config_inc.php before continuing to the database upgrade check</td></tr>';
		echo '<tr><td><pre>' . htmlentities( $t_config ) . '</pre></td></tr></table></tr>';
	}
?>

</table>

<?php
	if ( false == $g_failed ) {
		$t_install_state++;
	}
}  # end install_state == 5

if ( 6 == $t_install_state ) {
# post install checks
?>
<table width="100%" bgcolor="#222222" border="0" cellpadding="10" cellspacing="1">
<tr>
	<td bgcolor="#e8e8e8" colspan="2">
		<span class="title">Checking Installation...</span>
	</td>
</tr>

<!-- Checking MD5 -->
<?php print_test( 'Checking for MD5 Crypt() support', 1 === CRYPT_MD5, false, 'password security may be lower than expected' ) ?>

<!-- Checking register_globals are off -->
<?php print_test( 'Checking for register_globals are off for mantis', ! ini_get_bool( 'register_globals' ), false, 'change php.ini to disable register_globals setting' ) ?>

<tr>
	<td bgcolor="#ffffff">
		Attempting to connect to database as user
	</td>
	<?php
		$g_db = ADONewConnection($f_db_type);
		$t_result = @$g_db->Connect($f_hostname, $f_db_username, $f_db_password, $f_database_name);

		if ( $t_result == true ) {
			print_test_result( GOOD );
		} else {
			print_test_result( BAD, false, 'Database user doesn\'t have access to the database ( ' .  db_error_msg() . ' )' );
		}
	?>
</tr>
<tr>
	<td bgcolor="#ffffff">
		checking ability to SELECT records
	</td>
	<?php
		$t_mantis_config_table = config_get_global( 'mantis_config_table' );
		$t_query = "SELECT COUNT(*) FROM $t_mantis_config_table";
		$t_result = @$g_db->Execute( $t_query );

		if ( $t_result != false ) {
			print_test_result( GOOD );
			
		} else {
			print_test_result( BAD, true, 'Database user doesn\'t have SELECT access to the database ( ' .  db_error_msg() . ' )' );
		}
	?>
</tr>
<tr>
	<td bgcolor="#ffffff">
		checking ability to INSERT records
	</td>
	<?php
		$t_query = "INSERT INTO $t_mantis_config_table ( value, type, access_reqd, config_id, project_id, user_id ) VALUES ('test', 1, 90, 'database_test', 20, 0 )";
		$t_result = @$g_db->Execute( $t_query );

		if ( $t_result != false ) {
			print_test_result( GOOD );
			
		} else {
			print_test_result( BAD, true, 'Database user doesn\'t have INSERT access to the database ( ' .  db_error_msg() . ' )' );
		}
	?>
</tr>
<tr>
	<td bgcolor="#ffffff">
		checking ability to UPDATE records
	</td>
	<?php
		$t_query = "UPDATE $t_mantis_config_table SET value='test_update' WHERE config_id='database_test'";
		$t_result = @$g_db->Execute( $t_query );

		if ( $t_result != false ) {
			print_test_result( GOOD );
			
		} else {
			print_test_result( BAD, true, 'Database user doesn\'t have UPDATE access to the database ( ' .  db_error_msg() . ' )' );
		}
	?>
</tr>
<tr>
	<td bgcolor="#ffffff">
		checking ability to DELETE records
	</td>
	<?php
		$t_query = "DELETE FROM $t_mantis_config_table WHERE config_id='database_test'";
		$t_result = @$g_db->Execute( $t_query );

		if ( $t_result != false ) {
			print_test_result( GOOD );
			
		} else {
			print_test_result( BAD, true, 'Database user doesn\'t have DELETE access to the database ( ' .  db_error_msg() . ' )' );
		}
	?>
</tr>
</table>
<?php
	if ( false == $g_failed ) {
		$t_install_state++;
	}
}  # end install_state == 6

if ( 7 == $t_install_state ) {
# cleanup and launch upgrade
?>
<p>Install was successful.</p>
<?php if ( $f_db_exists ) { ?>
<p><a href="../login_page.php">Continue</a> to log into Mantis</p>
<?php } else { ?>
<p>Please log in as the administrator and <a href="../manage_proj_create_page.php">create</a> your first project.

<?php }
} # end install_state == 7


if( $g_failed ) {
?>
<table width="100%" bgcolor="#222222" border="0" cellpadding="10" cellspacing="1">
<tr>
	<td bgcolor="#e8e8e8" colspan="2">
		<span class="title">Checks Failed...</span>
	</td>
</tr>
<tr>
	<td bgcolor="#ffffff">Please correct failed checks</td>
	<td bgcolor="#ffffff">
		<input name="install" type="hidden" value="<?php echo $t_install_state ?>"></input>
		<input name="hostname" type="hidden" value="<?php echo $f_hostname ?>"></input>
		<input name="db_type" type="hidden" value="<?php echo $f_db_type ?>"></input>
		<input name="database_name" type="hidden" value="<?php echo $f_database_name ?>"></input>
		<input name="db_username" type="hidden" value="<?php echo $f_db_username ?>"></input>
		<input name="db_password" type="hidden" value="<?php echo $f_db_password ?>"></input>
		<input name="admin_username" type="hidden" value="<?php echo $f_admin_username ?>"></input>
		<input name="admin_password" type="hidden" value="<?php echo $f_admin_password ?>"></input>
		<input name="log_queries" type="hidden" value="<?php echo ( $f_log_queries ? 1 : 0 ) ?>"></input>
		<input name="retry" type="submit" value="Retry"></input>
		<input name="db_exists" type="hidden" value="<?php echo ( $f_db_exists ? 1 : 0 ) ?>"></input>
	</td>
</tr>
</table>

<?php
}
?>

</form>

</body>
</html>