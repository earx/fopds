<?php
/**
 * COPS (Calibre OPDS PHP Server) class file
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Sébastien Lucas <sebastien@slucas.fr>
 */

require_once('base.php');

define(
        'SERIES_SQL',
        "SELECT n.SeqId AS id, SeqName AS sort, SeqName AS name "
        ." FROM libseqname n"
    );

class Genre extends Base {
    const ALL_SERIES_ID = "calibre:genres";
    
    public $id;
    public $name;
    
    public function __construct($pid, $pname) {
        $this->id = $pid;
        $this->name = $pname;
    }
    
    public function getUri () {
        return "?page=".parent::PAGE_GENRE_DETAIL."&id=$this->id";
    }
    
    public function getEntryId () {
        return self::ALL_SERIES_ID.":".$this->id;
    }

    public static function getCount() {
        
        $nGenres = parent::getDb ()->query('select count(*) from libseqname')->fetchColumn();
        
        $entry = new Entry (localize("genres.title"), self::ALL_SERIES_ID, 
            str_format (localize("genres.alphabetical"), $nGenres), "text", 
            array ( new LinkNavigation ("?page=".parent::PAGE_ALL_SERIES)));
        return $entry;
    }
    
    public static function getAllGenresByFirstLetter() {
        
        $query ='SELECT SUBSTRING(UPPER(SeqName), 1, 1) as title, count(*) as count'
                .' FROM libseqname'
                .' GROUP BY SUBSTRING(UPPER(SeqName), 1, 1)'
                .' ORDER BY SUBSTRING(UPPER(SeqName), 1, 1)'
                ;
        
        $result = parent::getDb()->query($query);
        $entryArray = array();
        while ( $post = $result->fetchObject () )
        {
            array_push ($entryArray, new Entry ($post->title, self::getEntryIdByLetter ($post->title), 
                str_format (localize("genresword", $post->count), $post->count), "text", 
                array ( new LinkNavigation ("?page=".parent::PAGE_SERIES_FIRST_LETTER."&id=". rawurlencode ($post->title)))));
        }
        
        
        return $entryArray;
    }
    
    public static function getGenresByStartingLetter($letter) {
    
        $SQL_AUTHORS_BY_FIRST_LETTER= "SELECT {0} FROM libseqname "
            ." JOIN libseq ON libseq.SeqId= libseqname.SeqId"
            ." WHERE UPPER(libseqname.SeqName) like ? "
            ." GROUP BY libseq.SeqId, libseqname.SeqName"
            ." ORDER BY libseqname.SeqName";
        
        return self::getEntryArray ($SQL_AUTHORS_BY_FIRST_LETTER, array ($letter . "%"));
    }
    
    public static function getEntryArray ($query, $params) {

        $COLUMNS = "libseqname.SeqId, libseqname.SeqName, COUNT(*)";
        
        $entryArray = array();
        list ($totalNumber, $result) = parent::getDb()->executeQuery($query, $COLUMNS, "", $params, -1);

        $aid =null;
        $aname =null;
        $count =null;
        
        $result->bind_result($aid, $aname, $count);
        
        while ($result->fetch())
        {
            $serie = new Genre ($aid, $aname);

            array_push ($entryArray, new Entry ($aname, $serie->getEntryId (), 
                str_format (localize("bookword", $count), $count), "text", 
                array ( new LinkNavigation ($serie->getUri ()))));
        }
        
        $result->close();

        return $entryArray;
    }    
    
    public static function getEntryIdByLetter ($startingLetter) {
        return self::ALL_SERIES_ID.":letter:".$startingLetter;
    }
    
    public static function getGenreByBookId ($bookId) {
        
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
            return new Genre ($post->id, $post->name);
        else
            return NULL;
    }
    
    public static function getGenreById ($serieId) {
        
        $query = 'SELECT SeqId AS id, SeqName AS name'
            ." FROM libseqname "
            ." WHERE SeqId = ?"
            ;

        $params = array ($serieId);
        
        list ($totalNumber, $stmt) = parent::getDb()->executeQuery($query, '', '', $params);
            
        $result = $stmt->get_result();
        $post = $result->fetch_object();
        
        if ($post)
            return new Genre ($post->id, $post->name);
        else
            return NULL;

    }
    
    //TODO
    public static function getAllGenres() {

        $query = "SELECT n.SeqId AS id, SeqName AS sort, SeqName AS name, count(*) as count"
        ." FROM libseqname n"
        ." JOIN libseq a ON a.SeqId = n.SeqId"
        ." GROUP BY n.SeqId, n.SeqName"
        ." ORDER BY n.SeqName"
        ;

        $params = array ();
        
        list ($totalNumber, $stmt) = parent::getDb()->executeQuery($query, '', '', $params);
            
        $result = $stmt->get_result();

        $entryArray = array();
        while ($post = $result->fetch_object())
        {
            $serie = new Genre ($post->id, $post->sort);
            array_push ($entryArray, new Entry ($serie->name, $serie->getEntryId (), 
                str_format (localize("bookword", $post->count), $post->count), "text", 
                array ( new LinkNavigation ($serie->getUri ()))));
        }

        return $entryArray;
    }
}
?>