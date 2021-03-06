<?php
/**
 * Object Relational Persistence Mapping Class for table: widget_form
 *
 * @package	com.clear-health.celini
 * @author	Joshua Eichorn <jeichorn@mail.com>
 */
class WidgetForm extends ORDataObject {

	/**#@+
	 * Fields of table: widget_form mapped to class members
	 */
	var $widget_form_id		= '';
	var $name = '';
	var $form_id		= '';
	var $report_id		= '';
	var $width		= '';
	var $height		= '';
	var $type		= '';
	var $show_on_medical_history	= '';
	var $controller_name = '';
	var $_widgetTypes = array();
	/**#@-*/


	/**
	 * DB Table
	 */
	var $_table = 'widget_form';

	/**
	 * Primary Key
	 */
	var $_key = 'widget_form_id';

	/**
	 * Handle instantiation
	 */
	function WidgetForm() {
		parent::ORDataObject();
	}
	
	/*function setup() {
		
	}*/

        function getWidgetTypes() {
                if (count($this->_widgetTypes) <= 0) {
                        $enum = ORDataObject::factory('Enumeration');
                        $this->_widgetTypes = $enum->get_enum_list('widget_type');
                }
                return $this->_widgetTypes;
        }

	function getWidgetFields($widget_form_id) {
		
		$db =& new clniDB();
		$sql = "select summary_column_id, type, name from summary_columns where widget_form_id = '" . (int)$widget_form_id . "' order by name";
		$results = $db->execute($sql);
		while ($results && !$results->EOF) {
			$ret[$results->fields[summary_column_id]] = $results->fields;
			$results->MoveNext();
		}
		return $ret;

	}

	function getWidgetColumn($widget_form_id) {
		$db =& new clniDB();
		$sql = "select max(summary_column_id) as summary_column_id from summary_columns where widget_form_id = '" . (int)$widget_form_id . "'";
		$results = $db->execute($sql);
		$ret = $results->fields["summary_column_id"];

		if (!$ret) {
			$ret = 0;
		}
		return $ret;

	}

        function getWidgetFormControllerName() {
		return $this->get('controller_name');
        }


}
?>
