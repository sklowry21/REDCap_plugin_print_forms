<?php
/**
 * PLUGIN NAME: print_forms_with_data.php
 * DESCRIPTION: This displays the forms for a project in a format that could be copied and pasted into Word
 *              and includes the data from the record for which the ID is passed in
 *              Required parameter: record for unique identifier for record to be displayed
 *              Required parameter: pid for project id
 *              Optional parameter: lws=y for less white space (e.g. ?pid=1&lws=y)
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
// Set the number at which a SQL field should no longer show all of the options
$too_many = 21;
// Set the less white space option variables
if ($_REQUEST['lws'] == 'y') { $lws = true; $not_lws = false; } else { $lws = false; $not_lws = true; }

$record = $_REQUEST['record'];
$this_pid = $_REQUEST['pid'];

if ( !$_REQUEST['record'] or !$_REQUEST['pid'] ) {
    die( "Both record and pid are required" );
}

$user_dag = "";
// build the sql statement to find the data
if (!SUPER_USER) {
    $sql = sprintf( "
            SELECT p.app_title, p.headerlogo, p.institution, p.project_name, u.group_id
              FROM redcap_projects p
              LEFT JOIN redcap_user_rights u
                ON u.project_id = p.project_id
             WHERE p.project_id = %d AND (u.username = '%s' OR p.auth_meth = 'none')",
                     $this_pid, $userid);

    // execute the sql statement
    $result = $conn->query( $sql );
    if ( ! $result ) {  // sql failed
        die( "Could not execute SQL: <pre>$sql</pre> <br />" .  mysqli_error($conn) );
    }
    if ( mysqli_num_rows($result) == 0 ) {
        die( "You are not validated for project # $project_id ($app_title)<br />" );
    }
    $user_record = $result->fetch_assoc( );
    if ($user_record['group_id'] > "") { $user_dag = $user_record['group_id']; }
}

// If the user is in a DAG, find out if the record is in their DAG
if ($user_dag > "") {
	$sql = sprintf( "
	        SELECT d.value as record_dag
	          FROM redcap_data d
	         WHERE d.project_id = %d
	           AND d.record = '%s'
	           AND d.field_name = '__GROUPID__'",
	                 $this_pid, $record );

	$dag_result = $conn->query( $sql );
	if ( ! $dag_result ) { // sql failed
	        die( "Could not execute SQL: <pre>$sql</pre> <br />" .  mysqli_error($conn) );
	}
	$dag_record = $dag_result->fetch_assoc( );
	if ($dag_record['record_dag'] > "") { $record_dag = $dag_record['record_dag']; } else { $record_dag = ""; }
	if ( $record_dag <> $user_dag ) {
	        die( "You do not have access to see record $record.<br />" );
	}
}

// Build the ddl field with the list of forms
$ddl = 'Select a data collection instrument to view <select id="record_select1" onchange="';
$ddl .= "var disp='none'; ";
$ddl .= "if (document.getElementById('record_select1').value == '') {disp='';} ";
// Set up the onchange for each form
foreach ($Proj->forms as $form_name=>$attr) {
    if ($user_rights['forms'][$form_name] > 0) {
	$ddl .= "document.getElementById('".$form_name."_form').style.display=disp; ";
    }
}
$ddl .= "document.getElementById(document.getElementById('record_select1').value + '_form').style.display=''; ".'">';
$ddl .= '<option value="">- select an instrument -</option>';
// Set up the select option for each form
foreach ($Proj->forms as $form_name=>$attr) {
    if ($user_rights['forms'][$form_name] > 0) {
	$ddl .= '<option value="'.$form_name.'">'.$attr['menu'].'</option>' ;
    }
}
$ddl .= '</select>';

#print "<pre>"; print_r($Proj->events); print "</pre>";

// Build the ddl field with the list of events
$ddl2 = 'Select an event to view <select id="event_select1" onchange="';
$ddl2 .= "var disp='none'; ";
$ddl2 .= "if (document.getElementById('event_select1').value == '') {disp='';} ";
// Set up the onchange for each event
foreach ($Proj->events as $this_arm=>$arm_attr) {
    #print "Arm name: ".$arm_attr['name']."<br/>";
    foreach ($arm_attr[events] as $this_event=>$event_attr) {
        #print "Event ID: ".$this_event.", name: ".$event_attr['descrip']."<br/>";
        $ddl2 .= "x = document.getElementsByClassName('event_".$this_event."'); var i; for (i=0; i < x.length; i++) { x[i].style.display=disp; } ";
    }
    $ddl2 .= "x = document.getElementsByClassName('event_none'); var i; for (i=0; i < x.length; i++) { x[i].style.display=disp; } ";
}
$ddl2 .= "x = document.getElementsByClassName('event_' + document.getElementById('event_select1').value); var i; for (i=0; i < x.length; i++) { x[i].style.display='';}".'">';
$ddl2 .= '<option value="">- select an event -</option>';
// Set up the select option for each event
foreach ($Proj->events as $this_arm=>$arm_attr) {
    foreach ($arm_attr[events] as $this_event=>$event_attr) {
	$ddl2 .= '<option value="'.$this_event.'">'.$arm_attr['name']." ".$event_attr['descrip'].'</option>' ;
    }
}
$ddl2 .= '</select>';

print '<span style="color:#800000;font-size:175%;">'.RCView::escape($table_pk_label).RCView::SP.RCView::b(RCView::escape($_GET['id'])).": $record</span><br/>";
// PRINT PAGE button
print  "<div style='text-align:right;width:700px;max-width:700px;'><table><tr><td>". $ddl ."</td>
             <td>&nbsp; &nbsp; <button class='jqbuttonmed' onclick='window.print();'><img src='".APP_PATH_IMAGES."printer.png' class='imgfix'> Print page</button></td></tr>";
if ($longitudinal) {
    print "             <tr><td>". $ddl2 ."</td><td></td></tr>";
}
print "</table></div>";

// Loop through all the forms
foreach ($Proj->forms as $form_name=>$attr)
{
  if ($user_rights['forms'][$form_name] > 0)
  {
    print '<div id="'.$form_name.'_form" style="max-width:700px;">';

    // Get information about the events in which there is data for the form for this record
    if ($longitudinal) {
        $sql = sprintf( "
                SELECT distinct em.event_id, em.descrip
                  FROM redcap_data d, redcap_metadata m, redcap_events_metadata em, redcap_events_forms ef 
                 WHERE d.project_id = %d
                   AND d.record = '%s'
                   AND m.project_id = d.project_id
                   AND m.form_name = '%s'
                   AND m.field_name = d.field_name
                   AND em.event_id = d.event_id
                   AND ef.event_id = d.event_id
                   AND ef.form_name = m.form_name
                   ORDER BY em.day_offset, em.descrip",
                      $this_pid, $record, $form_name );
    } else {
        $sql = sprintf( "
                SELECT distinct em.event_id, em.descrip
                  FROM redcap_data d, redcap_metadata m, redcap_events_metadata em
                 WHERE d.project_id = %d
                   AND d.record = '%s'
                   AND m.project_id = d.project_id
                   AND m.form_name = '%s'
                   AND m.field_name = d.field_name
                   AND em.event_id = d.event_id
                   ORDER BY em.day_offset, em.descrip",
                      $this_pid, $record, $form_name );
    }
#print "sql: $sql<br/>";

    $events_result = $conn->query( $sql );
    if ( ! $events_result ) {  // sql failed
            die( "Could not execute SQL: <pre>$sql</pre> <br />" .  mysqli_error($conn) );
    }

    // Loop through the events and store them
    $eventsHash = array();
    while ($events_record = $events_result->fetch_assoc( )) {
        $eventsHash[] = $events_record;
    }
    // If there is no data for this form, let them know that
    if (count($eventsHash) == 0) {
        if ($longitudinal) {
            print '<div class="event_none">';
        }
	print '<h3 style="color:#800000;max-width:700px;border-bottom:2pt solid #800000;font-size:175%;">'.$attr['menu'].'</h3>';
        print 'There is no data for this record and form';
        if ($longitudinal) {
            print ' in any events';
            print '</div>';
        }
    }
    // Loop through the events and display them
    foreach ($eventsHash as $thisEvent)
    {
        if ($longitudinal) {
            print '<div class="event_'.$thisEvent['event_id'].'">';
        }
	print '<h3 style="color:#800000;max-width:700px;border-bottom:2pt solid #800000;font-size:175%;">'.$attr['menu'].'</h3>';
        if ($longitudinal) {
	    print '<p style="color:#800000;max-width:700px;border-bottom:1pt solid #800000;font-size:100%;">Event: '.$thisEvent['descrip'].'</p>';
        }

        // Get information about the fields in the form for the event, with the data values
        $sql = sprintf( "
                SELECT d.event_id, m.branching_logic, m.custom_alignment, m.element_enum,
                       m.element_label, m.element_note, m.element_preceding_header,
                       m.element_type, m.element_validation_max, m.element_validation_min,
                       m.element_validation_type, m.field_name, m.field_order,
                       m.field_phi, m.field_req, m.form_name, m.question_num, m.stop_actions,
                       d.value as field_value
                  FROM redcap_metadata m
                  LEFT JOIN redcap_data d
                    ON d.project_id = m.project_id
                   AND d.field_name = m.field_name
                   AND d.record = '%s'
                   AND d.event_id = %d
                 WHERE m.project_id = %d
                   AND   m.form_name = '%s'
                   /* AND   m.element_type <> 'descriptive' */
                   ORDER BY m.field_order",
                      $record, $thisEvent['event_id'], $this_pid, $form_name );

        $fields_result = $conn->query( $sql );
        if ( ! $fields_result ) {
                die( "Could not execute SQL: <pre>$sql</pre> <br />" .  mysqli_error($conn) );
        }

        // Loop through the fields and store them
        $variableHash = array();
        while ($fields_record = $fields_result->fetch_assoc( )) {
		$variableHash[] = $fields_record;
        }
        // Loop through the fields and display them
	// Instead of using foreach, we'll use a counter so we can advance for checkbox fields with multiple values
        for ($fld_nbr = 0; $fld_nbr < count($variableHash); $fld_nbr++)
        {
        	$fields_record = $variableHash[$fld_nbr];
                $field_name = $fields_record['field_name'];
 		$field_value = $fields_record['field_value'];
		// Don't print form_complete fields
		if ( $field_name == $form_name . '_complete' ) { continue; }

                if ( $fields_record['element_enum'] > "" and  $fields_record['element_type'] != 'calc') {
                        $element_enums = explode('|',str_replace('\n','|',$fields_record['element_enum'])) ;
                }
                $this_element_label = str_replace("\n","<br />",$fields_record['element_label']);
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
                if ($fields_record['element_enum'] > "" and  $fields_record['element_type'] != 'calc') {
			if ($fields_record['custom_alignment'] == "LH" and $not_lws) {
                            $print_type .= '<br />';
                        }
                        if ( $fields_record['element_type'] == 'sql' ) {
                            $sql = $fields_record['element_enum'];
                            // execute the sql statement
                            $options_result = $conn->query( $sql );
                            if ( ! $options_result ) { // sql failed
                                die( "Could not execute SQL: <pre>$sql</pre> <br />" .  mysqli_error($conn) );
                            }
                            $options_count = mysqli_num_rows($options_result);
                            // Loop through the fields and display them
                            while ($options_record = $options_result->fetch_assoc( ))
                            {
                                $which = 0;
                                $id = '';
                                $label = '';
                                foreach ($options_record as $key => $value) { 
                                    $which += 1;
                                    if ($which == 1) { $id = $value; }
                                    if ($which == 2) { $label = $value; }
                                }
                                if ($label == '') { $label = $id; }
			        if ($id == $field_value) { $x_or_space = "X"; } else { $x_or_space = " &nbsp; "; }
                                # Since sql type fields can have a LONG list, we'll only print the selected option ...
                                # ... unless there aren't too many options
                                if ($id == $field_value or $options_count < $too_many) {
				    if (substr($fields_record['custom_alignment'], -1) == "H" or $lws) {
                                        $print_type .= "&nbsp; &nbsp; ($x_or_space) $label";
                                    } else {
                                        $print_type .= "&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; ($x_or_space) $label<br />";
                                    }
                                }
                            }
                        } else {
				$all_vals = array();
				$all_vals[1] = $field_value;
				if ($fields_record['element_type'] == 'checkbox' and $record > "" and $fld_nbr < count($variableHash)) {
					while ($variableHash[$fld_nbr + 1]['field_name'] == $field_name) {
						$fld_nbr += 1;
						$all_vals[] =  $variableHash[$fld_nbr]['field_value'];
					}
				}
                                foreach ($element_enums as &$this_element_enum) {
					$pos = strpos($this_element_enum, ",");
					$val = substr($this_element_enum, 0, $pos);
					$label = substr($this_element_enum, $pos + 1);
					if ($record > "" and array_search($val, $all_vals) > 0 ) { $x_or_space = "X"; } else { $x_or_space = " &nbsp; "; }
					if (substr($fields_record['custom_alignment'], -1) == "H" or $lws) {
                                            if ($fields_record['element_type'] == 'checkbox' ) {
						$print_type .= '&nbsp; &nbsp; ['.$x_or_space.'] '.$label;
                                            } else {
						$print_type .= '&nbsp; &nbsp; ('.$x_or_space.') '.$label;
                                            }
					} else {
                                            if ($fields_record['element_type'] == 'checkbox' ) {
						$print_type .= '&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; ['.$x_or_space.'] '.$label;
                                            } else {
						$print_type .= '&nbsp; &nbsp; &nbsp; &nbsp; ('.$x_or_space.') '.$label;
                                            }
                                            $print_type .= '<br />';
					}
                                }
                        }
                }
		elseif ($fields_record['element_type'] == 'descriptive') {
			$print_type .= '';
		}
		elseif ($fields_record['element_type'] == 'textarea') {
                    if ($fields_record['element_note'] > "") {
                	if (substr($fields_record['element_note'], 0, 1) == "(") {
                        	$print_type .= " ".$fields_record['element_note'];
                	} else {
                        	$print_type .= " (".$fields_record['element_note'].")";
                	}
		    }
		    if ($record > "" and $field_value > "") {
		        $print_type .= '<br /><u> '.str_replace(chr(13),'<br/>',$field_value).' </u>';
		    } else {
		        if ($not_lws) { 
                            $print_type .= '<br /><br /><hr><br /><hr><br /><hr>'; 
                        } else {
			    $print_type .= ' ______________________________________________________________________';
                        }
		    }
		}
		elseif ($fields_record['element_validation_type'] == 'date_mdy') {
		    if ($record > "" and $field_value > "") {
			$tmp = DateTimeRC::date_ymd2mdy($field_value);
		        $print_type .= '<u> '.$tmp.' </u>';
		    } else {
			$print_type .= '_____-_____-__________';
		    }
		}
		elseif ($fields_record['element_validation_type'] == 'date_dmy') {
		    if ($record > "" and $field_value > "") {
			$tmp = DateTimeRC::date_ymd2dmy($field_value);
		        $print_type .= '<u> '.$field_value.' </u>';
		    } else {
			$print_type .= '_____-_____-__________';
		    }
		}
		elseif ($fields_record['element_validation_type'] == 'date_ymd') {
		    if ($record > "" and $field_value > "") {
		        $print_type .= '<u> '.$field_value.' </u>';
		    } else {
			$print_type .= '__________-_____-_____';
		    }
		}
		elseif ($fields_record['element_validation_type'] == 'datetime_mdy' or $fields_record['element_validation_type'] == 'datetime_seconds_mdy') {
		    if ($record > "" and $field_value > "") {
		        $print_type .= '<u> '.substr($field_value,5,2).'-'.substr($field_value,8,2).'-'.substr($field_value,0,4).' '.substr($field_value,11,99).' </u>';
		    } else {
			$print_type .= '________________________';
		    }
		}
		elseif ($fields_record['element_validation_type'] == 'datetime_dmy' or $fields_record['element_validation_type'] == 'datetime_seconds_dmy') {
		    if ($record > "" and $field_value > "") {
		        $print_type .= '<u> '.substr($field_value,8,2).'-'.substr($field_value,5,2).'-'.substr($field_value,0,4).' '.substr($field_value,11,99).' </u>';
		    } else {
			$print_type .= '________________________';
		    }
		}
		else {
		    if ($record > "" and $field_value > "") {
		        $print_type .= '_<u>'.$field_value.'</u>_';
		    } else {
			$print_type .= '________________________';
		    }
		}
                if ($fields_record['element_note'] > "") {
		    if ($fields_record['element_type'] != 'textarea') {
                	if (substr($fields_record['element_note'], 0, 1) == "(") {
                        	$print_type .= " ".$fields_record['element_note'];
                	} else {
                        	$print_type .= " (".$fields_record['element_note'].")";
                	}
                        if ($not_lws) { $print_type .= '<br />';}
                    }
                }

                // Print the preceding header above the field, if there is one
                if ( $this_element_preceding_header > "" ) {
			$hr = ( strpos(' '.$fields_record['element_preceding_header'], '<hr') );
			if ($not_lws or $hr == false) { print '<br />'; }
                        print str_replace('<hr>','<hr style="color:black;">',$fields_record['element_preceding_header']);
                        if ($not_lws) { print '<br />'; }
                }
                // Print the information about the field
               	print $print_label;
                if ($fields_record['element_enum'] > "" && substr($fields_record['custom_alignment'], -1) != "H" && $not_lws && $fields_record['element_type'] != 'calc') {
               		print "<br />" . $print_type;
		} else {
               		print " &nbsp; " . $print_type . "<br />";
		}
                if ($not_lws) { print "<br />"; }
        }
	print "<br />";
	if ($not_lws) {print "<br /><br />"; }
        if ($longitudinal) { print "</div>"; }
    }
    print "</div>";
  }
}


// OPTIONAL: Display the project footer
//require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';

