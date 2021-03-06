<?php

$loader->requireOnce('ordo/Occurence.class.php');

/**
 * 
 */
 
class Event extends ORDataObject{
	
	/**
	 *	
	 *	@var $id
	 */
	 var $id;

	/**
	 *	
	 *	@var title
	 */
	var $title;
	
	/**
	 *	
	 *	@var description
	 */
	var $description;
	
	/**
	 *	
	 *	@var website
	 */
	var $website;
	
	/**
	 *	
	 *	@var contact_person
	 */
	var $contact_person;
	
	/**
	 *	
	 *	@var email
	 */
	var $email;
	
	/**
	 *	
	 *	@var foreign_id
	 */
	var $foreign_id;
	
	/**
	 *	
	 *	@var occurences
	 */
	var $occurences;
	var $_table = "events";

	/**
	 * Constructor sets all attributes to their default value
	 *  
	 */
	function Event($id = "",$load_occurences = true)	{
		//call the parent constructor so we have a _db to work with
		parent::ORDataObject();
		
		//shore up the most basic ORDataObject bits
		$this->id = $id;

		$this->title = "";
		$this->description = "";
		$this->website = "";
		$this->contact_person = "";
		$this->email = "";
		$this->foreign_id = "";
		$this->load_occurences = $load_occurences;
		$this->occurences = array();
		
		
		if ($id != "") {
			$this->populate();
		}
	}
	
	function populate() {
		parent::populate();
		if ($this->load_occurences) {
			$this->occurences = Occurence::occurences_factory($this->id);
		}
	}

	function persist() {
		parent::persist();
		foreach ($this->occurences as $occurence) {
			$occurence->persist($this->id);
		}
	}
	
	/**
	 * Convenience function to get an array of many objects
	 * 
	 * @param int $foreign_id optional id use to limit array on to a specific relation, otherwise every document object is returned 
	 */
	function events_factory($foreign_id = "") {
		$events = array();
		
		if (empty($foreign_id)) {
			 $foreign_id= "like '%'";
		}
		else {
			$foreign_id= " = '" . mysql_real_escape_string(strval($foreign_id)) . "'";
		}
		
		$e = new Event();
		$sql = "SELECT * FROM  " . $e->_prefix . $e->_table . " WHERE foreign_id " .$foreign_id ;
		$result = $e->_Execute($sql);
		
		$i = 0;
		while ($result && !$result->EOF) {
			$events[$i] = new Event();
			$events[$i]->populate_array($result->fields);
			$i++;
			$result->MoveNext();
		}

		return $events;
	}
	
	/**
	 * Convenience function to get an array of many objects
	 * 
	 * @param int $foreign_id optional id use to limit array on to a specific relation, otherwise every document object is returned 
	 */
	function get_events_between($start,$end,$key_type = "day",$foreign_id = "",$code_filter = "",$code_state=true) {
		$events = array();
		$e = new Event();
		
		if (!empty($foreign_id)) {
			$foreign_id= " AND e.foreign_id = '" . mysql_real_escape_string(strval($foreign_id)) . "'";
		}
		
		if (!empty($code_filter)) {
			if ($code_state) {
					$code_state = " IN ";	
				}
				else {
					$code_state = " NOT IN";
				}
			$in_str = mysql_real_escape_string(strval($code_filter));
			$in_ta = split(",",$in_str);
			$in_str = "'" . implode("','",$in_ta) . "'";
			$code_filter = " AND (c.schedule_code $code_state (" . $in_str . ") ";
			if ($code_state == " NOT IN") {
				$code_filter .= " OR c.schedule_code IS NULL) ";
			}
			else {
				$code_filter .= " ) ";
			}
		}
		
		
		//set default facility
		
		$filters = $_SESSION['calendar']['filters'];
		$filter_sql = "";
		if (is_array($filters)) {
			foreach ($filters as $type => $filter) {
				if (!empty($filter)) {
					switch($type) {
						case 'user': 
							$filter_sql .= " AND (u.user_id = " . $e->_db->qstr($filter) . "  OR c.schedule_code = 'ADM')";
							break;
						case 'location':
							$filter_sql .= " AND o.location_id = " . $e->_db->qstr($filter) . " ";
							break;	
					}
				}
			}
		}
		
		//echo $filter_sql;
		$sql = "
			SELECT 
				o.*,
				e.*,
				c.*, 
				o.id AS occurence_id,
				c.id AS schedule_id,
				UNIX_TIMESTAMP(o.start) AS start_ts,
				UNIX_TIMESTAMP(o.end) AS end_ts, 
				UNIX_TIMESTAMP(DATE_FORMAT(o.start,'%Y-%m-%d')) AS start_day,
				b.name AS building,
				r.name AS room,
				IF(schedule_code = 'PS',1,0) AS schedule_sort,
				u.color AS color,
				u.nickname AS nickname,
				u2.nickname AS last_change_nickname, 
				psn.last_name AS p_lastname,
				psn.person_id AS person_id,
				psn.first_name AS p_firstname,
				DATE_FORMAT(psn.date_of_birth,'%m/%d/%Y') AS dob,
				pt.record_number AS p_record_number, 
				pt.record_number AS p_patient_number,
				n.number AS p_phone,
				IF(n.active, 0, 1) AS dnc,
				rm.name AS room_name, 
				u3.nickname AS creator_nickname, 
				u3.username AS creator_username, 
				DATE_FORMAT(NOW(), '%Y') - DATE_FORMAT(psn.date_of_birth, '%Y') - (DATE_FORMAT(NOW(), '00-%m-%d') < DATE_FORMAT(psn.date_of_birth, '00-%m-%d')) AS age, 
				o.`timestamp` AS last_change,
				if(pt.confidentiality < 2,reason_code,0) reason_code,
				ab.*,
				concat(pro.first_name,' ',pro.last_name) provider_name
			FROM 
				`".$GLOBALS['frame']['config']['db_name']."`.".$e->_prefix."occurences AS o
				LEFT JOIN `".$e->_prefix."events` AS e ON o.event_id = e.id
				LEFT JOIN ".$e->_prefix."schedules AS c ON c.id = e.foreign_id 
				LEFT JOIN rooms AS rm ON c.room_id=rm.id 
				LEFT JOIN ".$e->_prefix."rooms AS r ON r.id = o.location_id 
				LEFT JOIN ".$e->_prefix."buildings AS b ON b.id = r.building_id 
				LEFT JOIN ".$e->_prefix."user AS u ON u.user_id= o.user_id
				LEFT JOIN ".$e->_prefix."user AS u2 ON u2.user_id = o.last_change_id
				LEFT JOIN ".$e->_prefix."user as u3 ON u3.user_id = o.creator_id
				LEFT JOIN patient AS pt ON pt.person_id=o.external_id
				LEFT JOIN person AS psn ON psn.person_id=pt.person_id
				LEFT JOIN person_number AS p2n ON psn.person_id=p2n.person_id
				LEFT JOIN person AS pro ON u.person_id = pro.person_id
				
				/* this will be the first value in the number_types enum */
				LEFT JOIN number AS n ON n.number_id=p2n.number_id and n.number_type =1

				LEFT JOIN (".Event::_accountBalanceSql().") ab on pt.person_id = ab.patient_id
			WHERE 
				o.start BETWEEN '$start'  AND '$end' AND 
				(c.schedule_code != 'NS' OR c.schedule_code IS NULL) 
				$foreign_id 
				$code_filter 
				$filter_sql 
			GROUP BY o.id 
			ORDER BY
				schedule_sort DESC,
				o.start,
				o.end";
		//echo $sql . "<br>";

		$result = $e->_Execute($sql);
		
		return Event::event_array_builder($result,$key_type);
	}

	function _accountBalanceSql() {
		return
		'
		select
			feeData.patient_id,
                        charge,
                        (ifnull(credit,0.00) + ifnull(coPay.amount,0.00)) credit,
			(charge - (ifnull(credit,0.00)+ifnull(coPay.amount,0.00))) balance
		from
			/* Fee total */
			(
			select
				e.patient_id,
				sum(cd.fee) charge
			from
				encounter e
				inner join clearhealth_claim cc using(encounter_id)
				inner join coding_data cd on e.encounter_id = cd.foreign_id and cd.parent_id = 0
			group by
				e.patient_id
			) feeData
			left join
			/* Payment totals */
			(
			select
				e.patient_id,
				(sum(pl.paid) + sum(pl.writeoff)) credit
			from
				encounter e
				inner join clearhealth_claim cc using(encounter_id)
				inner join payment p on cc.claim_id = p.foreign_id
				inner join payment_claimline pl on p.payment_id = pl.payment_id
			group by
				e.patient_id
			) paymentData on feeData.patient_id = paymentData.patient_id
                        left join
                        /* Co-Pay Totals */
                        (
                        select
                            p.foreign_id patient_id,
                            sum(p.amount) amount
                        from
                            payment p
                        where encounter_id = 0
                        group by
                            p.foreign_id
                        ) coPay on feeData.patient_id = coPay.patient_id
		';
	}
	
	/**
	 * Convenience function to get an array of many objects
	 * 
	 * @param string $where_sql 
	 */
	function get_events($where_sql,$key_type = "day") {
		$events = array();
		$e = new Event();
		
		if(empty($where_sql)){
			$where_sql = '1';
		}
		$sql = "SELECT 
				o.*,
				e.*,
				c.*, 
				o.id as occurence_id, 
				c.id as schedule_id, 
				UNIX_TIMESTAMP(o.start) as start_ts,
				UNIX_TIMESTAMP(o.end) as end_ts, 
				UNIX_TIMESTAMP(DATE_FORMAT(o.start,'%Y-%m-%d')) as start_day, 
				b.name as building, 
				r.name as room, 
				IF(schedule_code = 'PS',1,0) as schedule_sort,
				u.color as color, 
				u.nickname as nickname, 
				u2.nickname as last_change_nickname, 
				psn.last_name as p_lastname, 
				psn.person_id as person_id, 
				psn.first_name as p_firstname, 
				DATE_FORMAT(psn.date_of_birth,'%m/%d/%Y') as dob,  
				pt.record_number as p_record_number, 
				pt.record_number as p_patient_number, 
				n.number as p_phone, 
				n.active as dnc, 
				rm.name as room_name, 
				DATE_FORMAT(NOW(), '%Y') - DATE_FORMAT(psn.date_of_birth, '%Y') - 
					(DATE_FORMAT(NOW(), '00-%m-%d') < DATE_FORMAT(psn.date_of_birth, '00-%m-%d')) AS age, 
				o.`timestamp` last_change,
				if(pt.confidentiality < 2,reason_code,0) reason_code
			FROM `".$GLOBALS['frame']['config']['db_name']."`.".$e->_prefix."occurences as o 
			LEFT JOIN `".$e->_prefix."events` as e on o.event_id = e.id LEFT JOIN ".$e->_prefix."schedules as c on c.id = e.foreign_id "
		." LEFT JOIN rooms as rm on c.room_id=rm.id "
		." LEFT JOIN ".$e->_prefix."rooms as r on r.id = o.location_id LEFT JOIN ".$e->_prefix."buildings as b on b.id = r.building_id LEFT JOIN ".$e->_prefix."user as u on u.user_id= o.user_id"
		." LEFT JOIN ".$e->_prefix."user as u2 on u2.user_id = o.last_change_id "
		." LEFT JOIN patient as pt on pt.person_id=o.external_id "
		." LEFT JOIN person as psn on psn.person_id=pt.person_id "
		." LEFT JOIN person_number as p2n on psn.person_id=p2n.person_id "
		." LEFT JOIN number as n on n.number_id=p2n.number_id and n.number_type =" .  "1" //this will be the first value in the number_types enum
 		." WHERE $where_sql group by o.id ORDER BY o.start";
		//echo $sql;
	
		$result = $e->_Execute($sql);
		
		return Event::event_array_builder($result,$key_type);
	}
	
	function event_array_builder($result,$key_type) {

		$config =& Celini::configInstance('Practice');
		$increment = $config->get('CalendarIncrement',900);

		$events = array();
		while ($result && !$result->EOF) {
			$key = null;
			switch($key_type) {
				case "month":
				case "week":
				
					$key = $result->fields['start_day'];
					$key2 = ($result->fields['start_ts'] - ($result->fields['start_ts']%$increment));
					//echo "end ts " . date("Y-m-d H:i",$result->fields['end_ts']) . " start ts " .date("Y-m-d H:i",$result->fields['start_ts']) . " di " . ceil(($result->fields['end_ts'] - $result->fields['start_ts'])/900) . "<br>";
					$result->fields['duration_increments'] = ceil(($result->fields['end_ts'] - $result->fields['start_ts'])/$increment);
					$events[$key][$key2][] = $result->fields;
					break;
				case "week_schedule":
					$key = $result->fields['start_day'];
					$key2 = ($result->fields['start_ts'] - ($result->fields['start_ts']%$increment));
					//echo "end ts " . date("Y-m-d H:i",$result->fields['end_ts']) . " start ts " .date("Y-m-d H:i",$result->fields['start_ts']) . " di " . ceil(($result->fields['end_ts'] - $result->fields['start_ts'])/900) . "<br>";
					$di=ceil(($result->fields['end_ts'] - $result->fields['start_ts'])/$increment);
					$result->fields['duration_increments'] = 1;
					$result->fields['first_inc'] = true;
					$events[$key][$key2][] = $result->fields;
					$result->fields['first_inc'] = false;
					$result->fields['last_inc'] = false;
					for($i=1;$i<$di;$i++) {	
						$events[$key][$key2+($i*$increment)][] = $result->fields;
						if ($i+1 == $di) {
							$result->fields['last_inc'] = true;		
							$events[$key][$key2+($i*$increment)+$increment][] = $result->fields;
						}
					}
					break;
				case "day":
					$key = ($result->fields['start_ts'] - ($result->fields['start_ts']%$increment));
					$di=ceil(($result->fields['end_ts'] - $result->fields['start_ts'])/$increment);
					$result->fields['duration_increments'] = ceil(($result->fields['end_ts'] - $result->fields['start_ts'])/$increment);
					$events[$key][] = $result->fields;
					break;
				case "day_schedule":
					$key = ($result->fields['start_ts'] - ($result->fields['start_ts']%$increment));
					$di=ceil(($result->fields['end_ts'] - $result->fields['start_ts'])/$increment);
					$result->fields['duration_increments'] = 1;
					$result->fields['first_inc'] = true;
					$events[$key][] = $result->fields;
					$result->fields['first_inc'] = false;
					$result->fields['last_inc'] = false;
					if (is_null($result->fields['color'])) {
						$result->fields['color'] = "FFF";
					}
					for($i=1;$i<$di;$i++) {	
						$events[$key+($i*$increment)][] = $result->fields;
						if ($i + 1 == $di) {
							$result->fields['last_inc'] = true;		
							$events[$key+($i*$increment)+$increment][] = $result->fields;
						}
					}
					break;	
				case "day_brief":
					//floor to the correct 15 minute increment in seconds
					$key = ($result->fields['start_ts'] - ($result->fields['start_ts']%$increment));
					$di=ceil(($result->fields['end_ts'] - $result->fields['start_ts'])/$increment);
					$result->fields['duration_increments'] = 1;
					$result->fields['first_inc'] = true;
					$events[$key][] = $result->fields;
					break;
				case "find_first":
					$key2 = ($result->fields['start_ts'] - ($result->fields['start_ts']%$increment));
					//echo "end ts " . date("Y-m-d H:i",$result->fields['end_ts']) . " start ts " .date("Y-m-d H:i",$result->fields['start_ts']) . " di " . ceil(($result->fields['end_ts'] - $result->fields['start_ts'])/900) . "<br>";
					$di=ceil(($result->fields['end_ts'] - $result->fields['start_ts'])/$increment);
					$events[] = $key2;
					for($i=1;$i<$di;$i++) {	
						$events[] = $key2+($i*$increment);
					}
					break;
			}
			
			$result->MoveNext();
		}

		return $events;
	}
	
	/**
	 * Convenience function to generate string debug data about the object
	 */
	function toString($html = false) {
		$string .= "\n"
		. "ID: " . $this->id."\n"
		."title:" . $this->title."\n"
		."description:" . $this->description."\n"
		."website:" . $this->website."\n"
		."contact_person:" . $this->contact_person."\n"
		."email:" . $this->email."\n"
		."foreign_id:" . $this->foreign_id."\n"
		. "\n";
		if ($html) {
			return nl2br($string);
		}
		else {
			return $string;
		}
	}

	/**#@+
	*	Getter/Setter methods used by reflection to affect object in persist/poulate operations
	*	@param mixed new value for given attribute
	*/
	function set_id($id) {
		$this->id = $id;
	}
	function get_id() {
		return $this->id;
	}
	
	function set_title($value) {
		$this->title = $value;
	}
	function get_title() {
		return $this->title;
	}

	function set_description($value) {
		$this->description = $value;
	}
	function get_description() {
		return $this->description;
	}

	function set_website($value) {
		$this->website = $value;
	}
	function get_website() {
		return $this->website;
	}

	function set_contact_person($value) {
		$this->contact_person = $value;
	}
	function get_contact_person() {
		return $this->contact_person;
	}

	function set_email($value) {
		$this->email = $value;
	}
	function get_email() {
		return $this->email;
	}

	function set_foreign_id($value) {
		$this->foreign_id = $value;
	}
	function get_foreign_id() {
		return $this->foreign_id;
	}
	
	function get_earliest_date() {
		if(isset($this->occurences[0])){
			return $this->occurences[0]->get('start_date');		
		}else{
			return '';	
		}
	}
	
	function get_latest_date() {
		if(isset($this->occurences[count($this->occurences) - 1])){
			return $this->occurences[count($this->occurences) - 1]->get('end_date');
		}else{
			return '';
		}
	}
	
	function get_delete_message() {
		$string = "Event Name: " . $this->get_title() . "\n";
		$ocs = $this->get_occurences();
		$c = new Schedule($this->get_foreign_id());
		foreach ($ocs as $oc) 	{
			$name = "unspecified event";
			$schedule = "unspecified schedule";
			$ename = $this->get_title();
			$cname = $c->get_name();
			if (!empty($ename)) {
				$name = $ename;
			}
			
			if (!empty($cname)) {
				$schedule = $cname;
			}
			
			$string .= "--Scheduled use: " . $oc->get_start() . " - " . $oc->get_end() . " for " . $name . " schedule " . $schedule . "\n";
		}	
		return $string;
	}
	
	function delete() {
		$sql = "DELETE from " . $this->_prefix .$this->_table . " where id=" . $this->_db->qstr($this->id);
		$result = $this->_db->Execute($sql);
		$result = $this->_db->ErrorMsg();
		$ocs = $this->get_occurences();
		$retval = true;
		foreach ($ocs as $oc) {
			$val = $oc->delete();
			($val && $retval) ? $retval=true: $retval = false;	
		}
		if (empty($result) && $retval) {
			return true;
		}
		return false;
		
	}
	
	function get_occurences($newest = -1) {
		if (empty($this->occurences)) {
			$this->occurences = Occurence::occurences_factory($this->id,$newest);	
		}
		return $this->occurences;	
	}
	
	
	

} // end of Class

?>
