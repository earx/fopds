<?php
/**
 * COPS (Calibre OPDS PHP Server) class file
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Sébastien Lucas <sebastien@slucas.fr>
 */

require_once('base.php');

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
        
        $query ='SELECT SUBSTRING(UPPER(lastname), 1, 1) as title, count(*) as count'
                .' FROM libavtorname'
                .' GROUP BY SUBSTRING(UPPER(lastname), 1, 1)'
                .' ORDER BY SUBSTRING(UPPER(lastname), 1, 1)'
                ;
        
        $result = parent::getDb()->query($query);
        $entryArray = array();
        while ($post = $result->fetchObject ())
        {
            
            array_push ($entryArray, new Entry ($post->title, Author::getEntryIdByLetter ($post->title), 
                str_format (localize("authorword", $post->count), $post->count), "text", 
                array ( new LinkNavigation ("?page=".parent::PAGE_AUTHORS_FIRST_LETTER."&id=". rawurlencode ($post->title)))));
        }
        
        
        return $entryArray;
    }
    
    public static function getAuthorsByStartingLetter($letter) {
    
        $SQL_AUTHORS_BY_FIRST_LETTER= "SELECT {0} FROM libavtorname "
            ." JOIN libavtor ON libavtor.AvtorId = libavtorname.AvtorId"
            ." WHERE UPPER(libavtorname.lastname) like ? "
            ." GROUP BY libavtorname.AvtorId, libavtorname.FirstName, libavtorname.LastName"
            ." ORDER BY LastName";
        
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
        $result = parent::getDb ()->prepare('select sort from authors where id = ?');
        $result->execute (array ($authorId));
        return new Author ($authorId, $result->fetchColumn ());
    }
    
    public static function getAuthorByBookId ($bookId) {
        $result = parent::getDb ()->prepare('select authors.id as id, authors.sort as sort
from authors, books_authors_link
where author = authors.id
and book = ?');
        $result->execute (array ($bookId));
        $authorArray = array ();
        while ($post = $result->fetchObject ()) {
            array_push ($authorArray, new Author ($post->id, $post->sort));
        }
        return $authorArray;
    }
}
?>