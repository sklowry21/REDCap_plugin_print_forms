# REDCap_print_forms
Two plugins to display the forms for a project in a format that could be copied &amp; pasted into Word, with or without data for one record

The print_forms.php plugin will display a copy of all of the forms to which you have access in your project.  You must pass in the project ID (e.g. ?pid=43).

The print_forms_with_data.php plugin will display a copy of all of the forms that have data for the record in your project.  You must pass in the project ID and the record (e.g. ?record=52&pid=12). If the project is longitudinal, it will display a copy of the form for every event for which there is data for the record in that form.

In both print_forms.php and in print_forms_with_data.php, you can select a single form from a drop down list to have only that form displayed.