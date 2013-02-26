<?php
/**
 * COPS (Calibre OPDS PHP Server) class file
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Sébastien Lucas <sebastien@slucas.fr>
 */

require_once('base.php');

define(
        'AUTHORS_SQL',
        "SELECT n.AvtorId AS id, CONCAT(n.FirstName,' ',n.MiddleName,' ',n.LastName) AS sort, CONCAT(n.FirstName,' ',n.MiddleName,' ',n.LastName) AS name "
        ." FROM libavtorname n"
    );

class Author extends Base {
    const ALL_AUTHORS_ID = "calibre:authors";    
    const SQL_ALL_AUTHORS = "select {0} from authors, books_authors_link where author = authors.id group by authors.id, authors.name, authors.sort order by sort";
    
    public $id;
    public $name;
    public $sort;
    
    public function __construct($pid, $pname) {
        $this->id = $pid;
        $this->name = $pname;
    }
    
    public function getUri () {
        return "?page=".parent::PAGE_AUTHOR_DETAIL."&id=$this->id";
    }
    
    public function getEntryId () {
        return self::ALL_AUTHORS_ID.":".$this->id;
    }
    
    public static function getEntryIdByLetter ($startingLetter) {
        return self::ALL_AUTHORS_ID.":letter:".$startingLetter;
    }

    public static function getCount() {

        $nAuthors = parent::getDb ()->query('select count(*) as cnt from libavtorname')->fetchColumn();
        $entry = new Entry (localize("authors.title"), self::ALL_AUTHORS_ID, 
            str_format (localize("authors.alphabetical"), $nAuthors), "text", 
            array ( new LinkNavigation ("?page=".parent::PAGE_ALL_AUTHORS)));
        return $entry;
    }
    
    public static function getAllAuthorsByFirstLetter() {

        global $config;
        
        $id     = parent::getDb()->escape( getURLParam('id','') );
        $level  = parent::getDb()->escape( getURLParam('level',1) );

        if ($level >=1)
            $where = " WHERE lastname LIKE '$id%'";
        else
            $where = '';
            
        $query ="SELECT SUBSTRING(UPPER(lastname), 1, $level) as title, count(*) as count"
                ." FROM libavtorname"
                //." JOIN libavtor ON libavtor.AvtorId = libavtorname.AvtorId"
                .$where
                ." GROUP BY SUBSTRING(UPPER(lastname), 1, $level)"
                ." ORDER BY SUBSTRING(UPPER(lastname), 1, $level)"
                ;
        
        //fb($query);
        
        $result = parent::getDb()->query($query);
        $entryArray = array();
        
        while ( $post = $result->fetchObject () )
        {
            
            if ( $post->count <= $config['items_max_on_level'] )
            {
                $linkstr = "?page=".parent::PAGE_AUTHORS_FIRST_LETTER ."&id=%s";
            }
            else
            {
                $linkstr = "?page=".parent::PAGE_ALL_AUTHORS ."&id=%s&level=".($level + 1);
            }
            
            array_push ($entryArray, new Entry ($post->title, Author::getEntryIdByLetter ($post->title), 
                str_format (localize("authorword", $post->count), $post->count), "text", 
                array (
                    new LinkNavigation (sprintf($linkstr, rawurlencode ($post->title) )  )
                )
            ));
        }
        
        return $entryArray;
    }
    
    public static function getAuthorsByStartingLetter($letter) {
    
        $SQL_AUTHORS_BY_FIRST_LETTER= "SELECT {0} FROM libavtorname "
            ." JOIN libavtor ON libavtor.AvtorId = libavtorname.AvtorId"
            ." WHERE UPPER(libavtorname.lastname) like ? "
            ." GROUP BY libavtorname.AvtorId"
            ." ORDER BY LastName";
        
        //fb($SQL_AUTHORS_BY_FIRST_LETTER);
        
        return self::getEntryArray ($SQL_AUTHORS_BY_FIRST_LETTER, array ($letter . "%"));
    }
    
    public static function getAllAuthors() {
        return self::getEntryArray (self::SQL_ALL_AUTHORS, array ());
    }
    
    public static function getEntryArray ($query, $params) {

        $AUTHOR_COLUMNS = "libavtorname.AvtorId, CONCAT(FirstName,' ',MiddleName,' ',LastName), COUNT(*)";
        
        $entryArray = array();
        list ($totalNumber, $result) = parent::getDb()->executeQuery($query, $AUTHOR_COLUMNS, "", $params, -1);

        $aid =null;
        $aname =null;
        $count =null;
        
        $result->bind_result($aid, $aname, $count);
        
        while ($result->fetch())
        {
            $author = new Author ($aid, $aname);
            
            array_push ($entryArray, new Entry ($aname, $author->getEntryId (), 
                str_format (localize("bookword", $count), $count), "text", 
                array ( new LinkNavigation ($author->getUri ()))));
        }
        
        $result->close();

        return $entryArray;
    }
        
    public static function getAuthorById ($authorId) {

        $query = AUTHORS_SQL
            ." JOIN libavtor a ON a.AvtorId = n.AvtorId"
            ." WHERE a.AvtorId = ?"
            ;
        
        $prep_query = parent::getDb()->prepare($query);
        $prep_query->bind_param('i', $authorId);

        //execute
        if ( ! $prep_query->execute () ) {
            
            trigger_error("Cannot execute query");
            die; 
        }

        $prep_query->bind_result($data);
        $prep_query->fetch();
        
        $prep_query->close();
        
        return new Author ($authorId, $data );
    }
    
    public static function getAuthorByBookId ($bookId, $type = 'author') {
        
        if ($type == 'author')
            $join = " JOIN libavtor a ON a.AvtorId = n.AvtorId";
        else
            $join = " JOIN libtranslator a ON a.TranslatorId = n.AvtorId";
        
        $query = AUTHORS_SQL
            .$join
            ." WHERE a.BookId = ?"
            ;

        $params = array ($bookId);
        
        list ($totalNumber, $stmt) = parent::getDb()->executeQuery($query, '', '', $params);
            
        $result = $stmt->get_result();
        
        if ($post = $result->fetch_object())
        {
            return array( new Author ($post->id, $post->sort) );
        }
        
        return NULL;

    }
    
    //TODO: show author details
    public static function getAuthorAnnotations ($AuthorId) {
        
        $query = "SELECT * "
            ." FROM libaannotations a"
            ." WHERE a.AvtorId = ?"
            ;
            
        $params = array ($AvtorId);
        
        list ($totalNumber, $stmt) = parent::getDb()->executeQuery($query, '', '', $params);
            
        $result = $stmt->get_result();
        
        $annotations = array();
        
        while ($post = $result->fetch_object())
        {
            $annotations[] = $post;
        }
        
        return $annotations;

    }    
}
?>