<?php
/**
 * COPS (Calibre OPDS PHP Server) class file
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Sébastien Lucas <sebastien@slucas.fr>
 */

require_once('base.php');

class Serie extends Base {
    const ALL_SERIES_ID = "calibre:series";
    
    public $id;
    public $name;
    
    public function __construct($pid, $pname) {
        $this->id = $pid;
        $this->name = $pname;
    }
    
    public function getUri () {
        return "?page=".parent::PAGE_SERIE_DETAIL."&id=$this->id";
    }
    
    public function getEntryId () {
        return self::ALL_SERIES_ID.":".$this->id;
    }

    public static function getCount() {
        
        $nSeries = parent::getDb ()->query('select count(*) from libseqname')->fetchColumn();
        
        $entry = new Entry (localize("series.title"), self::ALL_SERIES_ID, 
            str_format (localize("series.alphabetical"), $nSeries), "text", 
            array ( new LinkNavigation ("?page=".parent::PAGE_ALL_SERIES)));
        return $entry;
    }
    
    public static function getSerieByBookId ($bookId) {
        
        $query = "SELECT a.SeqId AS id, SeqName AS name"
            ." FROM libseq a, libseqname n"
            ." WHERE a.BookId = ?"
            ." AND a.SeqId = n.SeqId"
            ;
            
        $params = array ($bookId);
        
        list ($totalNumber, $stmt) = parent::getDb()->executeQuery($query, '', '', $params);
            
        $result = $stmt->get_result();
        $post = $result->fetch_object();
        
        if ($post)
            return new Serie ($post->id, $post->name);
        else
            return NULL;
    }
    
    public static function getSerieById ($serieId) {
        
        $query = 'SELECT SeqId AS id, SeqName AS name'
            ." FROM libseqname "
            ." WHERE SeqId = ?"
            ;

        $params = array ($serieId);
        
        list ($totalNumber, $stmt) = parent::getDb()->executeQuery($query, '', '', $params);
            
        $result = $stmt->get_result();
        $post = $result->fetch_object();
        
        if ($post)
            return new Serie ($post->id, $post->name);
        else
            return NULL;

    }
    
    //TODO
    public static function getAllSeries() {
        
        $query = 'SELECT SeqId AS id, SeqName AS name'
            ." FROM libseqname "
            ." WHERE SeqId = ?"
            ;

        $params = array ($serieId);
        
        list ($totalNumber, $stmt) = parent::getDb()->executeQuery($query, '', '', $params);
            
        $result = $stmt->get_result();
        $post = $result->fetch_object();

        $entryArray = array();
        while ($post = $result->fetchObject ())
        {
            $serie = new Serie ($post->id, $post->sort);
            array_push ($entryArray, new Entry ($serie->name, $serie->getEntryId (), 
                str_format (localize("bookword", $post->count), $post->count), "text", 
                array ( new LinkNavigation ($serie->getUri ()))));
        }
        return $entryArray;
    
        
//        $result = parent::getDb ()->query('select series.id as id, series.name as name, series.sort as sort, count(*) as count
//from series, books_series_link
//where series.id = series
//group by series.id, series.name, series.sort
//order by series.sort');

    }
}
?>