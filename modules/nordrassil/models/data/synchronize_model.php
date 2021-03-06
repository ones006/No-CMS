<?php
class Synchronize_Model extends CMS_Model{
	private $connection;
	private $db_server;
	private $db_user;
	private $db_port;
	private $db_password;
	private $db_schema;
	
	public function synchronize($project_id){
		// make project_id save of SQL injection		
		$save_project_id = addslashes($project_id);		
		
		// delete related nds_column_option
		$where = "column_id IN (SELECT column_id FROM nds_column, nds_table WHERE nds_column.table_id = nds_table.table_id AND project_id='$save_project_id')";
		$this->db->delete('nds_column_option',$where);
		
		// delete related nds_column
		$where = "table_id IN (SELECT table_id FROM nds_table WHERE project_id='$save_project_id')";
		$this->db->delete('nds_column',$where);
		
		// delete related nds_table_option
		$where = "table_id IN (SELECT table_id FROM nds_table WHERE project_id='$save_project_id')";
		$this->db->delete('nds_table_option',$where);
		
		// delete from nds_table
		$where = array('project_id'=>$project_id);
		$this->db->delete('nds_table',$where);
		
		
		// select the current nor_project
		$query = $this->db->select('db_server, db_user, db_password, db_schema, db_port')
			->from('nds_project')
			->where(array('project_id'=>$project_id))
			->get();
		if($query->num_rows()>0){
			$row = $query->row();
			$this->db_server = $row->db_server;
			$this->db_port = $row->db_port;
			$this->db_user = $row->db_user;
			$this->db_password = $row->db_password;
			$this->db_schema = $row->db_schema;
			if(!isset($this->db_port) || $this->db_port == ''){
				$this->db_port = '3306';
			}
			$this->connection = mysqli_connect($this->db_server, $this->db_user, $this->db_password, 'information_schema', $this->db_port);
			mysqli_select_db($this->connection, 'information_schema');
			$this->db_log('Will Create Table');
			$this->create_table($project_id);
			return TRUE;
		}else{
			$this->db_log('Error Synchronizing');
			return FALSE;
		}		
	}
	
	private function create_table($project_id){
		$this->load->helper('inflector');
		$save_db_schema = addslashes($this->db_schema);
		$SQL = "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='$save_db_schema'";
		$this->db_log($SQL);
		$this->db_log(print_r($this->connection, True));
		$result = mysqli_query($this->connection, $SQL);
		while($row = mysqli_fetch_array($result)){
			// inserting the table
			$data = array(
					'project_id' => $project_id,
					'name'=> $row['TABLE_NAME'],
					'caption' => humanize($row['TABLE_NAME'])
				);
			$this->db->insert('nds_table', $data);
			// inserting the field
			$table_id = $this->db->insert_id();
			$table_name = $row['TABLE_NAME'];
			$this->create_field($table_id, $table_name);
		}
	}
	
	private function create_field($table_id, $table_name){
		$this->load->helper('inflector');
		$save_db_schema = addslashes($this->db_schema);
		$save_table_name = addslashes($table_name);
		$SQL = 
			"SELECT 
				COLUMN_NAME, DATA_TYPE, IS_NULLABLE, CHARACTER_MAXIMUM_LENGTH, 
				NUMERIC_PRECISION, NUMERIC_SCALE, COLUMN_KEY 
			FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$save_db_schema' AND TABLE_NAME='$save_table_name'";
		$this->db_log($SQL);		
		$result = mysqli_query($this->connection, $SQL);
		while($row = mysqli_fetch_array($result)){
			if($row['COLUMN_KEY'] == 'PRI'){
				$role = 'primary';
			}else{
				$role = '';
			}
			$data_type = $row['DATA_TYPE'];
			// get length (data_size) of the column
			$length = NULL;
			if($data_type == 'int' || $data_type=='tinyint' || $data_type=='double'){
				$length = $row['NUMERIC_PRECISION'];
			}else{
				$length = $row['CHARACTER_MAXIMUM_LENGTH'];
			}
			if(!isset($length)){
				$length = 10;
			}
			// inserting the field
			$data = array(
					'table_id' => $table_id,
					'name'=> $row['COLUMN_NAME'],
					'caption' => humanize($row['COLUMN_NAME']),
					'data_type' => $data_type,
					'data_size' => $length,
					'role' => $role
				);
			$this->db->insert('nds_column', $data);
		}
		
	}

	
	private function db_log($content){
		$this->db->insert('nds_log',array('content'=>$content));
	}
}
?>