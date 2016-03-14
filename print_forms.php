<?php
/**
 * PLUGIN NAME: print_forms.php
 * DESCRIPTION: This displays the forms for a project in a format that could be copied and pasted into Word
 * VERSION:     1.0
 * AUTHOR:      Sue Lowry - University of Minnesota
 */

// Call the REDCap Connect file in the main "redcap" directory
require_once "../redcap_connect.php";

// OPTIONAL: Your custom PHP code goes here. You may use any constants/variables listed in redcap_info().
/*
// Example of how to restrict this plugin to a specific REDCap project (in case user's randomly find the plugin's URL)
if (PROJECT_ID != 24) {
        exit('This plugin is only accessible to users from project "So and So", which is project_id 24.');
}
*/

// OPTIONAL: Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

// Your HTML page content goes here
?>
        <style type="text/css">
                table {border-collapse:collapse;}
                table.ReportTableWithBorder {
                  border-right:none;
                  border-left:none;
                  border-right:1pt solid black;
                  border-bottom:1pt solid black;
                  font-size:17px;
                  font-size:13px;
                  font-family:helvetica,arial,sans-serif;
                }
                .ReportTableWithBorder th,
                .ReportTableWithBorder td {
                  border-top: none;
                  border-left: none;
                  border-top: 1pt solid black;
                  border-left: 1pt solid black;
                  padding: 4px 5px;
                  font-weight:normal;
                }
                td.DataA {background-color:##DDDDDD;}
                td.DataB {background-color:##FFFFFF;}
        </style>
<?php
// build the sql statement to find the data
if (!SUPER_USER) {
    $sql = sprintf( "
            SELECT p.app_title, p.headerlogo, p.institution, p.project_name
              FROM redcap_projects p
              LEFT JOIN redcap_user_rights u
                ON u.project_id = p.project_id
             WHERE p.project_id = %d AND (u.username = '%s' OR p.auth_meth = 'none')",
                     $_REQUEST['pid'], $userid);

    // execute the sql statement
    $result = $conn->query( $sql );
    if ( ! $result )  // sql failed
    {
        die( "Could not execute SQL: <pre>$sql</pre> <br />" .  mysqli_error($conn) );
    }

    if ( mysqli_num_rows($result) == 0 )
    {
        die( "You are not validated for project # $project_id ($app_title)<br />" );
    }
}

$sql = sprintf( "
        SELECT m.field_order, m.form_menu_description, m.form_name
          FROM redcap_metadata m
         WHERE m.project_id =%d
           AND m.form_menu_description > ''
         ORDER BY m.field_order",
                 $_REQUEST['pid']);

// execute the sql statement
$form_result = $conn->query( $sql );
if ( ! $form_result )  // sql failed
{
        die( "Could not execute SQL: <pre>$sql</pre> <br />" .  mysqli_error($conn) );
}

$ddl = 'Select a data collection instrument to view <select id="record_select1" onchange="';
while ($form_record = $form_result->fetch_assoc( ))
{
//	$ddl .= 'if (document.) { } else { }; ';
	$ddl .= "document.getElementById('".$form_record['form_name']."_form').style.display='none';";
}
$ddl .= "document.getElementById(document.getElementById('record_select1').value + '_form').style.display='';".'">';
$form_result = $conn->query( $sql );
$ddl .= '<option value="">- select an instrument -</option>';
while ($form_record = $form_result->fetch_assoc( ))
{
	$ddl .= '<option value="'.$form_record['form_name'].'">'.$form_record['form_menu_description'].'</option>' ;
}
$ddl .= '</select>';

//print '<table border="0" cellpadding="2" cellspacing="0" class="ReportTableWithBorder">';
// PRINT PAGE button
print  "<div style='text-align:right;width:700px;max-width:700px;'>". $ddl ."
             <button class='jqbuttonmed' onclick='window.print();'><img src='".APP_PATH_IMAGES."printer.png' class='imgfix'> Print page</button>
         </div>";

$sql = sprintf( "
        SELECT m.field_order, m.form_menu_description, m.form_name
          FROM redcap_metadata m
         WHERE m.project_id =%d
           AND m.form_menu_description > ''
         ORDER BY m.field_order",
                 $_REQUEST['pid']);

// execute the sql statement
$form_result = $conn->query( $sql );
if ( ! $form_result )  // sql failed
{
        die( "Could not execute SQL: <pre>$sql</pre> <br />" .  mysqli_error($conn) );
}

//print '<table border="0" cellpadding="2" cellspacing="0" class="ReportTableWithBorder">';
//print '<div style="max-width:700px;width:700px;"><table border="0" cellpadding="2" cellspacing="0" id="form_table" class="form_border">';
while ($form_record = $form_result->fetch_assoc( ))
{
?>
    <div id="<?php echo $form_record['form_name'] ?>_form" style="max-width:700px;">
	<h3 style="color:#800000;max-width:700px;border-bottom:2pt solid #800000;font-size:175%;"><?php echo $form_record['form_menu_description'] ?></h3>

<?php

        $sql = sprintf( "
                SELECT m.branching_logic, m.custom_alignment, m.element_enum,
                       m.element_label, m.element_note, m.element_preceding_header,
                       m.element_type, m.element_validation_max, m.element_validation_min,
                       m.element_validation_type, m.field_name, m.field_order,
                       m.field_phi, m.field_req, m.form_name, m.question_num, m.stop_actions
                  FROM redcap_metadata m
                 WHERE m.project_id = %d
                   AND   m.form_name = '%s'
                   /* AND   m.element_type <> 'descriptive' */
                   ORDER BY m.field_order",
                      $_REQUEST['pid'], $form_record['form_name'] );


        // execute the sql statement
        $fields_result = $conn->query( $sql );

        if ( ! $fields_result )  // sql failed
        {
                die( "Could not execute SQL: <pre>$sql</pre> <br />" .  mysqli_error($conn) );
        }

        $variableHash = array();

        while ($fields_record = $fields_result->fetch_assoc( ))
        {
                $field_name = $fields_record['field_name'];
		// Don't print calc fielss or form_complete fields
		if ( $fields_record['element_type'] == 'calc' ) { continue; }
		if ( $field_name == $form_record['form_name'] . '_complete' ) { continue; }

                if ( $fields_record['element_enum'] > "" ) {
                        $element_enums = explode('|',str_replace('\n','|',$fields_record['element_enum'])) ;
                }
                //$this_element_label = str_replace('<hr>','',$fields_record['element_label']);
                $this_element_label = str_replace("\n","<br />",$fields_record['element_label']);
                //$this_element_preceding_header = str_replace('<hr>','',$fields_record['element_preceding_header']);
                $this_element_preceding_header = str_replace("\n","<br />",$fields_record['element_preceding_header']);

                $print_field_name =  $field_name;

                $print_number = "" ;
                if ($fields_record['question_num'] > "") {
                        $print_number .= $fields_record['question_num'];
                }

                $print_label = $this_element_label ;
                if ($fields_record['field_req'] == 1) { 
			$print_label = '<span style="color:red;font-size:10px;font-weight:normal;">*&nbsp;</span>' . $print_label;
                }


                $print_type = "";
                if ($fields_record['element_enum'] > "") {
                        if ( $fields_record['element_type'] == 'sql' ) {
//                                $print_type .= '<table border="0" cellpadding="2" cellspacing="0" class="ReportTableWithBorder"><tr><td>' . $fields_record['element_enum'] . '</td></tr></table>';
                        } else {
                                foreach ($element_enums as &$this_element_enum) {
					$pos = strpos($this_element_enum, ",");
					$val = substr($this_element_enum, 1, $pos - 1);
					$label = substr($this_element_enum, $pos + 1);
						if (substr($fields_record['custom_alignment'], -1) == "H") {
                                                	if ($fields_record['element_type'] == 'checkbox' ) {
								$print_type .= '&nbsp; &nbsp; [ &nbsp; ] '.$label;
                                               		} else {
								#$print_type .= '&nbsp; &nbsp; O '.$label;
								$print_type .= '&nbsp; &nbsp; ( &nbsp; ) '.$label;
                                                	}
						} else {
                                                	if ($fields_record['element_type'] == 'checkbox' ) {
								$print_type .= '&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; [ &nbsp; ] '.$label;
                                               		} else {
								#$print_type .= '&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; O '.$label;
								$print_type .= '&nbsp; &nbsp; &nbsp; &nbsp; ( &nbsp; ) '.$label;
                                                	}
                                        		$print_type .= '<br />';
						}
                                }
				if ($fields_record['custom_alignment'] == "LH") {
                                	$print_type .= '<br />';
                                }
                        }
                }
		elseif ($fields_record['element_type'] == 'descriptive') {
			$print_type .= '';
		}
		elseif ($fields_record['element_type'] == 'textarea') {
                    if ($fields_record['element_note'] > "") {
                	if (substr($fields_record['element_note'], 1, 1) == "(") {
                        	$print_type .= " ".$fields_record['element_note'];
                	} else {
                        	$print_type .= " (".$fields_record['element_note'].")";
                	}
		    }
		    $print_type .= '<br /><br /><hr><br /><hr><br /><hr>';
		}
		elseif ($fields_record['element_validation_type'] == 'date_mdy') {
			$print_type .= '_____-_____-__________';
		}
		elseif ($fields_record['element_validation_type'] == 'date_dmy') {
			$print_type .= '_____-_____-__________';
		}
		elseif ($fields_record['element_validation_type'] == 'date_ymd') {
			$print_type .= '__________-_____-_____';
		}
		else {
			$print_type .= '________________________';
		}
                if ($fields_record['element_note'] > "") {
		    if ($fields_record['element_type'] != 'textarea') {
                	if (substr($fields_record['element_note'], 1, 1) == "(") {
                        	$print_type .= " ".$fields_record['element_note'];
                	} else {
                        	$print_type .= " (".$fields_record['element_note'].")";
                	}
                    }
                }

                // Print the preceding header above the field, if there is one
                if ( $this_element_preceding_header > "" ) {
                        print '<br />'.str_replace('<hr>','<hr style="color:black;">',$fields_record['element_preceding_header']) . "<br />";
                }
                // Print the information about the field
               	print $print_label;
                if ($fields_record['element_enum'] > "" && substr($fields_record['custom_alignment'], -1) != "H") {
               		print "<br />" . $print_type;
		} else {
               		print " &nbsp; " . $print_type . "<br />";
		}
                print "<br />";
        }
	print "<br /><br /><br />";
    print "</div>";
}


// OPTIONAL: Display the project footer
//require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';

