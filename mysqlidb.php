<?php

class MysqliResult {

    private $mysqli_result;

    /*
     * @param Mysql Result Object 
     */
    public function __construct($result) {
        
        $this->mysqli_result = $result;
    }
    
    /**
     * Get first column of first row from result
     */
    public function fetchColumn() {
        
        $row = $this->mysqli_result->fetch_row();
        $this->mysqli_result->close();
        
        return $row[0]; //return first column
    }
    
    public function fetchObject() {
        
        return $this->mysqli_result->fetch_object();
    }
    
}

class MysqliDB {
    
    private $db;
    
    /*
     * @param array $config - config
     */
    public function __construct($config) {
        
        $this->connect($config);
    }
    
    public function getDB( $config ) {
        
        if ( ! $this->db ) {
            
            $this->connect($config);
        }
        
        return $this->db;
    }
    
    /** Connect to server
     * set internal db link
     * @param array $config - config
     */
    private function connect( $config ) {

        $link = new mysqli( 
                $config['mysql_host'],  
                $config['mysql_user'],  
                $config['mysql_pwd'],  
                $config['mysql_db']
            );           
        
        if (!$link) { 

            trigger_error("Cannot connect mysqli server: ". $link->connect_error);
            die; 
        }
        
        $link->set_charset("utf8");
        
        $this->db = $link;
    }
    
    /** Run query
     * @param string query
     * @param int $flags - mysqli flags
     * @return mysqli result object or die on error
     */
    public function query ($query, $flags = MYSQLI_STORE_RESULT) {
        
        $res = $this->db->query($query, $flags);
        
        if ( ! $res ) {
            
            trigger_error ("Error in the query: ". $this->db->error);
            die;
        }
        
        return new MysqliResult($res);
    }
    
    /**
     * Prepare query
     * @param string $query SQL
     * @return mysqli_stmt object
     * die on error
     */
    public function prepare($query) {
        
        $res = $this->db->prepare( $query );
        
        if ( ! $res ) {
            
            trigger_error ('cannot prepare query: '.$query);
            die;
        }
        
        return $res;
    }


    /**
     *     Prepare and execute query
     *     @param string $query SQL
     *     @param string $columns - columns list
     *     @param string $filter
     *     @param array $params
     *     @param int $n
     */
    public function executeQuery($query, $columns = '', $filter='', $params = array(), $n = -1) {
        
        global $config;
        $totalResult = -1;
        
        if ($config['cops_max_item_per_page'] != -1 && $n != -1)
        {
            echo 'TODO: mysqlidb.php:134';
            die;
            
            // First check total number of results
            $result = $this->db->prepare (str_format ($query, "count(*)", $filter));
            
            $result->execute ($params);
            $totalResult = $result->fetchColumn ();
            
            // Next modify the query and params
            $query .= " limit ?, ?";
            array_push ($params, ($n - 1) * $config['cops_max_item_per_page'], $config['cops_max_item_per_page']);
        }
        
        $final_query = str_format ($query, $columns, $filter);
        $prep_query = $this->db->prepare($final_query);

        if ( ! $prep_query ) {
            
            trigger_error("Cannot prepare query: ". $this->db->error);
            die; 
        }
        
        //bind params
        foreach($params as $p)  {
            $prep_query->bind_param('s', $p);
        }

        //execute
        if ( ! $prep_query->execute () ) {
            
            trigger_error("Cannot execute query: executeQuery:162");
            die; 
        }
        
        return array ($totalResult, $prep_query);
    }

}
?>