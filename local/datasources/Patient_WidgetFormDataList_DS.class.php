<?php

require_once CELINI_ROOT . '/includes/Datasource_sql.class.php';

class Patient_WidgetFormDataList_DS extends Datasource_sql  {
	/**
	 * Stores the case-sensative class name for this ds and should be considered
	 * read-only.
	 *
	 * This is being used so that the internal name matches the filesystem
	 * name.  Once BC for PHP 4 is no longer required, this can be dropped in
	 * favor of using get_class($ds) where ever this property is referenced.
	 *
	 * @var string
	 */
	var $_internalName = 'Patient_WidgetFormDataList_DS';

	/**
	 * The form type to show
	 *
	 * @var string
	 */
	var $patient_id = '';
	var $fields = '';
	var $case_sql = '';
	var $form_id = '';


	function Patient_WidgetFormDataList_DS($patient_id,$form_id = '',$allCols = false) {
		$this->patient_id = intval($patient_id);
		$form_id = (int)$form_id;
		$this->form_id = $form_id;
		$colList = '';
		if ($form_id >0 && $allCols) {
			$colList = " , " . $this->_buildColList();
		}
		$this->setup(Celini::dbInstance(),
			array(
				'cols'    => "type, last_edit, f.name, form_data_id, external_id {$colList} ",
				'from'    => "widget_form AS wf " .
							 "INNER JOIN form AS f USING(form_id) ".
							 "INNER JOIN form_data AS fd using (form_id) ".
							 "LEFT JOIN storage_int ON storage_int.foreign_key = fd.form_data_id ".
							 "LEFT JOIN storage_string ON storage_string.foreign_key = fd.form_data_id ".
							 "LEFT JOIN storage_text ON storage_text.foreign_key = fd.form_data_id ".
							 "LEFT JOIN storage_date ON storage_date.foreign_key = fd.form_data_id ",
				'orderby' => 'name, last_edit DESC',
				'where'   => "fd.external_id = " . $this->patient_id,
				'groupby'   => "fd.form_data_id"
			),
			false);
	}

	function set_form_type($form_id) {
		$this->form_id = $form_id;
		$this->_query['where'] = "fd.external_id = " . EnforceType::int($this->patient_id) . " and wf.form_id = " . EnforceType::int($form_id);
		
		$db = Celini::dbInstance();
		
		$sql = "select DISTINCT value_key, 'storage_int' as source  from storage_int si LEFT JOIN form_data fd ON si.foreign_key = fd.form_data_id and fd.form_id = " . (int)$form_id . " where fd.form_data_id IS NOT NULL
				UNION
				select DISTINCT value_key, 'storage_date' as source from storage_date sd LEFT JOIN form_data fd ON sd.foreign_key = fd.form_data_id and fd.form_id = " . (int)$form_id . " where fd.form_data_id IS NOT NULL
				UNION
				select DISTINCT value_key, 'storage_string' as source from storage_string ss LEFT JOIN form_data fd ON ss.foreign_key = fd.form_data_id and fd.form_id = " . (int)$form_id . " where fd.form_data_id IS NOT NULL
				UNION
				select DISTINCT value_key, 'storage_text' as source from storage_text st LEFT JOIN form_data fd ON st.foreign_key = fd.form_data_id and fd.form_id = " . (int)$form_id . " where fd.form_data_id IS NOT NULL";
//echo $sql ."<br>";
		$res = $db->query($sql);
		$fields = array();
		$labels = array("last_edit" => "Last Edit");
		while($res && !$res->EOF) {
			$ta = array();
			$ta['source'] = $res->fields['source'];
			$ta['name'] = $res->fields['value_key'];
			$pretty_name = str_replace('_summary','',$res->fields['value_key']);
            $pretty_name = str_replace('.','',$pretty_name);
            $pretty_name = str_replace('_',' ',$pretty_name);
            $pretty_name = ucwords($pretty_name);
            $labels[$res->fields['value_key']] = $pretty_name;
		 	$fields[] = $ta; 
			$res->MoveNext();	
		}

		$this->fields = $fields;
		$this->_build_case_sql();
				
		//echo $this->case_sql;
		
		$this->_query['cols'] .= $this->case_sql;
		$this->_labels = $labels;
		
//		echo "<!--" . $this->preview()."<br>-->";
	}
	
	function _build_case_sql() {
    	$case_sql = "";
    	if (count($this->fields > 0)) $case_sql = " ,";
        foreach ($this->fields as $ar) {
        	$table = $ar['source'];
        	
        	$value_name = $ar['name'];
            
            $case_sql .= " MAX(CASE WHEN $table.value_key = '$value_name' THEN $table.value END)  as '$value_name', ";
		if (strpos($value_name,"_active") >0) {
			$this->registerFilter($value_name, array($this, '_enumVal'));
		}
        }
        $case_sql = substr($case_sql,0,-2);             
        $this->case_sql = $case_sql;
	}
	function _buildColList() {

	}
	function _enumVal($value) {

		if ($value == 1) {
			return "Yes";
		}
		return "No";
	}
}

