<?php
class SQStatement{
	const MYSQL_DATE_FMT = 'Y-m-d';
	const MYSQL_TIME_FMT = 'H:i:s';
	const MYSQL_DATETIME_FMT = 'Y-m-d H:i:s';
	const MYSQL_TIMESTAMP_FMT = 'Y-m-d H:i:s';
	
	protected $dbcxn;
	protected $query;
	protected $result;
	
	public function __construct(mysqli $dbcxn){
		$this->dbcxn = $dbcxn;
		return $this;
	}
	
	public function prepare(string $safe_query, /* mixed */ ...$replacements) : ?SQStatement{
		try{
			if(!$parts = preg_split('/([\'"`]|\\\'|\\")(.*?)(\1)(*SKIP)(*FAIL)|(?:\B\?)([dfistux]|[0-9]+)(?:\b)/mi', ' ' . $safe_query, null, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE)){
				throw new Exception('Failed to parse SQStatement');
			}else{
				for($i = 1, $len = count($parts), $replIndex = 0; $i < $len; $i += 2){
					if(ctype_digit($parts[$i])){
						$brIndex = (int)$parts[$i] - 1;
						if($replIndex == 0 || $brIndex < 0 || $brIndex > $replIndex){
							throw new Exception('Encountered backreference ['.$brIndex.'] to placeholder that does not exist or has not yet been prepared');
						}else{
							$parts[$i] = $replacements[$brIndex];
						}
					}else{
						$replacements[$replIndex] = $parts[$i] = $this->{'prep_'.$parts[$i]}($replacements[$replIndex]);
						$replIndex++;
					}
				}
				$this->query = trim(implode($parts));
				//echo $this->query . '<br>';
				return $this;
			}
		}catch(Exception $e){
			/* Downgrade to warning, return null - allow to continue */
			trigger_error($e->getMessage(), E_USER_WARNING);
			return null;
		}
	}
	
	public function execute(int $resultmode = MYSQLI_STORE_RESULT) : bool{
		$this->result = $this->dbcxn->query($this->query, $resultmode) ?: null;
		return (bool)$this->result;
	}
	
	public function get_result() : ?mysqli_result{
		return $this->result;
	}
	
	protected function prep_d/*ate*/($date) : string{
		return $this->dateTimeF($date, self::MYSQL_DATE_FMT);
	}
	
	protected function prep_f/*loat*/($float) : float{
		if(!is_numeric($float)){
			throw new Exception('Invalid float value [' . $float . '] encountered');
		}
		return $float;
	}
	
	protected function prep_i/*nt*/($int) : int{
		if(!is_numeric($int)){
			throw new Exception('Invalid integer value [' . $int . '] encountered');
		}
		return $int;
	}
	
	protected function prep_s/*ring*/($str) : string{
		return '\'' . $this->dbcxn->escape_string(trim($str, '"\'')) . '\'';
	}
	
	protected function prep_t/*imestamp*/($timestamp) : string{
		return $this->dateTimeF($timestamp, self::MYSQL_TIMESTAMP_FMT);
	}
	
	protected function prep_u/*int*/($uint) : int{
		if(!is_numeric($uint) || $uint < 0){
			throw new Exception('Invalid unsigned integer value [' . $uint . '] encountered');
		}
		return $uint;
	}
	
	protected function prep_x/*identifier*/($identifier) : string{
		if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $identifier)){
			$identifier = '`' . trim($identifier, '`') . '`';
		}
		return $identifier;
	}
	
	protected function dateTimeF($date, string $format) : string{
		$type = util\getTypeX($date);
		switch($type){
			case 'DateTime':
				/* continue... */
			case 'DateTimeImmutable':
				$date = $date->format($format);
				break;
			case 'string':
				$intVal = strtotime($date);
				if(!$intVal){
					throw new Exception('Invalid string "' . $date . '" could not be converted to MySQL date');
				}
				$date = $intVal;
				/* continue... */
			case 'integer':
				$date = date($format, $date);
				break;
			default:
				throw new Exception('Invalid type [' . $type . '] could not be converted to MySQL date');
		}
		return '\'' . $date . '\'';
	}
}

class SafeMysqli extends mysqli{
	
	public function sq_init() : SQStatement{
		return new SQStatement($this);
	}
	
	public function sq_prepare(string $safe_query, /* mixed */ ...$replacements) : ?SQStatement{
		return call_user_func_array([new SQStatement($this), 'prepare'], func_get_args());
	}
}
?>
