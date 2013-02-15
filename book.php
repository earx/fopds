<?php
/**
 * COPS (Calibre OPDS PHP Server) class file
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Sébastien Lucas <sebastien@slucas.fr>
 */

require_once('base.php');
require_once('serie.php');
require_once('author.php');
require_once('tag.php');
require_once ("customcolumn.php");
require_once('data.php');
require_once('php-epub-meta/epub.php');

// Silly thing because PHP forbid string concatenation in class const

define ('SQL_BOOK_COLUMNS',
        "libbook.BookID as id, libbook.title as title, time, year, keywords, librate.Rate, lang, fileType, fileSize"
        .",libbook.md5 as uuid"
        //.",GenreCode, GenreDesc, GenreMeta, "
        //.",SeqName "
        );
        
//TODO: bug: some books are repeated twice in list
//example: http://test.lo/opds/cops/index.php?page=7&id=19416
define ('SQL_BOOKS_LEFT_JOIN',
            "LEFT OUTER JOIN librate ON librate.BookId = libbook.BookId"
            //." LEFT OUTER JOIN libgenre ON libbook.BookId = libgenre.BookId"
            //." LEFT JOIN libgenrelist ON libgenrelist.GenreId = libgenre.GenreId"
            //." LEFT JOIN libseq ON libbook.BookId = libseq.BookId"
            //." LEFT JOIN libseqname ON libseqname.SeqId = libseq.SeqId"
        );

define ('SQL_BOOKS_BY_FIRST_LETTER', "select {0} from libbook " . SQL_BOOKS_LEFT_JOIN . "
                                                    where libbook.deleted = 0 and upper (libbook.title) like ? order by libbook.title");
define ('SQL_BOOKS_BY_TAG', "select {0} from libbook_tags_link, libbook " . SQL_BOOKS_LEFT_JOIN . "
                                                    where libbook.deleted = 0 and libbook_tags_link.book = libbook.BookId and tag = ? {1} order by sort");
define ('SQL_BOOKS_BY_CUSTOM', "select {0} from {2}, libbook " . SQL_BOOKS_LEFT_JOIN . "
                                                    where {2}.book = libbook.BookId and {2}.{3} = ? {1} order by sort");
define ('SQL_BOOKS_QUERY', "select {0} from libbook " . SQL_BOOKS_LEFT_JOIN . "
                                                    where (exists (select null from authors, libbook_authors_link where book = libbook.BookId and author = authors.id and authors.name like ?) or title like ?) {1} order by libbook.sort");
define ('SQL_BOOKS_RECENT', "select {0} from libbook " . SQL_BOOKS_LEFT_JOIN . "
                                                    where 1=1 {1} order by timestamp desc limit ");

class Book extends Base {
    const ALL_BOOKS_UUID = "urn:uuid";
    const ALL_BOOKS_ID = "calibre:books";
    const ALL_RECENT_BOOKS_ID = "calibre:recentbooks";
    const BOOK_COLUMNS = SQL_BOOK_COLUMNS;
    
    const SQL_BOOKS_LEFT_JOIN = SQL_BOOKS_LEFT_JOIN;
    const SQL_BOOKS_BY_FIRST_LETTER = SQL_BOOKS_BY_FIRST_LETTER;
    //const SQL_BOOKS_BY_SERIE = SQL_BOOKS_BY_SERIE;
    const SQL_BOOKS_BY_TAG = SQL_BOOKS_BY_TAG;
    const SQL_BOOKS_BY_CUSTOM = SQL_BOOKS_BY_CUSTOM;
    const SQL_BOOKS_QUERY = SQL_BOOKS_QUERY;
    const SQL_BOOKS_RECENT = SQL_BOOKS_RECENT;
    
    public $id;
    public $title;
    public $time;
    public $timestamp;
    public $year;
    public $pubdate;
    public $path;
    public $uuid;
    public $hasCover;
    public $relativePath;
    public $seriesIndex;
    public $comments;
    public $rating;
    public $datas = NULL;
    public $authors = NULL;
    public $translators = NULL;
    public $serie = NULL;
    public $keywords = NULL;
    public $format = array();
    public $lang;
    public $fileType;
    public $fileSize; 
    public $genres;
    public $seqname;

    
    public function __construct($line) {
        
        global $config;
        
        $this->id = $line->id;
        $this->title = $line->title;
        $this->lang = $line->lang;
        $this->keywords = $line->keywords;
        $this->fileType = $line->fileType;
        $this->fileSize = $line->fileSize;

        $this->time = strtotime ($line->time);
        $this->timestamp = strtotime ($line->time);
        $this->year = strtotime ($line->year);
        $this->pubdate = strtotime ($line->year);

        $this->hasCover = false;
        
        //$this->seqname=$line->SeqName;        
        $this->rating = $line->Rate;

        $this->uuid = $line->uuid;
        
        //TODO: construct path
        //$this->path = $config['calibre_directory'] . $line->path;
        ///$this->relativePath = $line->path;
        //
        //$this->seriesIndex = $line->series_index;
        //$this->comment = $line->comment;
        //$this->hasCover = $line->has_cover;
        //if (!file_exists ($this->getFilePath ("jpg"))) {
        //    // double check
        //    $this->hasCover = 0;
        //}
        
    }
        
    public function getEntryId () {
        
        return self::ALL_BOOKS_UUID.":".$this->uuid;
    }
    
    public static function getEntryIdByLetter ($startingLetter) {
        return self::ALL_BOOKS_ID.":letter:".$startingLetter;
    }
    
    public function getUri () {
        return "?page=".parent::PAGE_BOOK_DETAIL."&amp;id=$this->id";
    }
    
    public function getDetailUrl () {
        global $config;
        if ($config['cops_use_fancyapps'] == 0) { 
            return 'index.php' . $this->getUri (); 
        } else { 
            return 'bookdetail.php?id=' . $this->id; 
        }
    }
    
    public function getTitle () {
        
        return $this->title;
    }
    
    public function getAuthors () {
        
        if (is_null ($this->authors)) {
            $this->authors = Author::getAuthorByBookId ($this->id, 'author');
        }
        return $this->authors;
    }

    public function getTranslators() {
        
        if (is_null ($this->translators)) {
            
            $this->translators = Author::getAuthorByBookId ($this->id, 'translator');
        }
        
        return $this->translators;
    }
    
    public function getFilterString () {
        
        return "";
    
    //TODO: use keywords, author, book name, etc..
        $filter = getURLParam ("tag", NULL);
        if (empty ($filter)) return "";
        
        $exists = true;
        if (preg_match ("/^!(.*)$/", $filter, $matches)) {
            $exists = false;
            $filter = $matches[1];    
        }
        
        $result = "exists (select null from libbook_tags_link, tags where libbook_tags_link.book = libbook.BookId and libbook_tags_link.tag = tags.id and tags.name = '" . $filter . "')";
        
        if (!$exists) {
            $result = "not " . $result;
        }
    
        return "and " . $result;
    }
    
    public function getAuthorsName() {

        $authorList = null;
        foreach ($this->getAuthors () as $author) {
            if ($authorList) {
                $authorList = $authorList . ", " . $author->name;
            }
            else
            {
                $authorList = $author->name;
            }
        }
        return $authorList;
    
    }

    public function getTranslatorsName() {

        $authorList = null;
        foreach ($this->getTranslators () as $author) {
            if ($authorList) {
                $authorList = $authorList . ", " . $author->name;
            }
            else
            {
                $authorList = $author->name;
            }
        }
        return $authorList;
    
    }
    

    public function getReviews() {
        
        return 'TODO';
    }
    
    public function getGenres() {
        
        return 'TODO';
    }
    
    public function getSerie() {
        
        if (is_null ($this->serie)) {
            $this->serie = Serie::getSerieByBookId ($this->id);
        }
        return $this->serie;
        
    }
    
    public function getLanguages () {

        return $this->lang;
    }
    
    public function getTags () {

        return $this->keywords;
    }
    
    public function getDatas ()
    {
        if (is_null ($this->datas)) {
            $this->datas = array( new Data ($this, $this) );
        }
        
        //var_dump($this->datas);
        return $this->datas;
    }
    
    public function getDataById ($idData)
    {
        foreach ($this->getDatas () as $data) {
            if ($data->id == $idData) {
                return $data;
            }
        }
        return NULL;
    }

    
    public function getTagsName () {
        $tagList = null;
        foreach ($this->getTags () as $tag) {
            if ($tagList) {
                $tagList = $tagList . ", " . $tag->name;
            }
            else
            {
                $tagList = $tag->name;
            }
        }
        return $tagList;
    }
    
    public function getRating () {
        if (is_null ($this->rating) || $this->rating == 0) {
            return "";
        }
        $retour = "";
        for ($i = 0; $i < $this->rating / 2; $i++) {
            $retour .= "&#9733;";
        }
        for ($i = 0; $i < 5 - $this->rating / 2; $i++) {
            $retour .= "&#9734;";
        }
        return $retour;
    }
    
    public function getComment ($withSerie = true) {
        
        if ( ! $this->comments ) {
            
            $comments = $this->getBookAnnotations($this->id);
            
            $this->comments = implode('<br />', $comments);
        }
        
        return $this->comments; 
    }
    
    public function getDataFormat ($format) {
        foreach ($this->getDatas () as $data)
        {
            if ($data->format == $format)
            {
                return $data;
            }
        }
        return NULL;
    }
    
    public function getFilePath ($extension, $idData = NULL, $relative = false)
    {
        $file = NULL;
        if ($extension == "jpg")
        {
            $file = "cover.jpg";
        }
        else
        {
            $data = $this->getDataById ($idData);
            $file = $data->name . "." . strtolower ($data->format);
        }

        if ($relative)
        {
            return $this->relativePath."/".$file;
        }
        else
        {
            return $this->path."/".$file;
        }
    }
    
    public function getUpdatedEpub ($idData)
    {
        $data = $this->getDataById ($idData);
            
        try
        {
            $epub = new EPub ($data->getLocalPath ());
            
            $epub->Title ($this->title);
            $authorArray = array ();
            foreach ($this->getAuthors() as $author) {
                $authorArray [$author->sort] = $author->name;
            }
            $epub->Authors ($authorArray);
            $epub->Language ($this->getLanguages ());
            $epub->Description ($this->getComment (false));
            $epub->Subjects ($this->getTagsName ());
            $se = $this->getSerie ();
            if (!is_null ($se)) {
                $epub->Serie ($se->name);
                $epub->SerieIndex ($this->seriesIndex);
            }
            $epub->download ($data->getFilename ());
        }
        catch (Exception $e)
        {
            echo "Exception : " . $e->getMessage();
        }
    }
    
    public function getLinkArray ()
    {
        global $config;
        $linkArray = array();
        
        //TODO: covers
        //if ($this->hasCover)
        //{
        //    array_push ($linkArray, Data::getLink ($this, "jpg", "image/jpeg", Link::OPDS_IMAGE_TYPE, "cover.jpg", NULL));
        //    $height = "50";
        //    if (preg_match ('/feed.php/', $_SERVER["SCRIPT_NAME"])) {
        //        $height = $config['cops_opds_thumbnail_height'];
        //    }
        //    else
        //    {
        //        $height = $config['cops_html_thumbnail_height'];
        //    }
        //    array_push ($linkArray, new Link ("fetch.php?id=$this->id&height=" . $height, "image/jpeg", Link::OPDS_THUMBNAIL_TYPE));
        //}
        
        $res = $this->getDatas();
        
        foreach ($res as $data)
        {
            if ($data->isKnownType())
            {
                array_push ($linkArray, $data->getDataLink (Link::OPDS_ACQUISITION_TYPE, "Download"));
            }
        }
                
        //foreach ($this->getAuthors () as $author) {
        //    array_push ($linkArray, new LinkNavigation ($author->getUri (), "related", str_format (localize ("bookentry.author"), localize ("splitByLetter.book.other"), $author->name)));
        //}
        //
        //$serie = $this->getSerie ();
        //if (!is_null ($serie)) {
        //    array_push ($linkArray, new LinkNavigation ($serie->getUri (), "related", str_format (localize ("content.series.data"), $this->seriesIndex, $serie->name)));
        //}
        
        return $linkArray;
    }

    
    public function getEntry () {
        
        return new EntryBook (
            $this->getTitle(),
            $this->getEntryId(),
            $this->getComment(),
            "text/html", 
            $this->getLinkArray(),
            $this
        );
    }

    public static function getCount() {
        global $config;
        $nBooks = parent::getDb ()->query('select count(*) from libbook')->fetchColumn();
        $result = array();
        $entry = new Entry (localize ("allbooks.title"), 
                          self::ALL_BOOKS_ID, 
                          str_format (localize ("allbooks.alphabetical"), $nBooks), "text", 
                          array ( new LinkNavigation ("?page=".parent::PAGE_ALL_BOOKS)));
        array_push ($result, $entry);
        $entry = new Entry (localize ("recent.title"), 
                          self::ALL_RECENT_BOOKS_ID, 
                          str_format (localize ("recent.list"), $config['cops_recentbooks_limit']), "text", 
                          array ( new LinkNavigation ("?page=".parent::PAGE_ALL_RECENT_BOOKS)));
        array_push ($result, $entry);
        return $result;
    }
        
    public static function getBooksByAuthor($authorId, $n) {

        $SQL_BOOKS_BY_AUTHOR ="SELECT {0} FROM libavtor, libbook "
            . SQL_BOOKS_LEFT_JOIN 
            ." WHERE libavtor.bookID = libbook.bookID"
                ." AND libavtor.AvtorId = ? {1}"
            ." ORDER BY libbook.Year"
            ;
            //TODO: exlude deleted?
        
        return self::getEntryArray ($SQL_BOOKS_BY_AUTHOR, array ($authorId), $n);
    }

    
    public static function getBooksBySeries($serieId, $n) {
        
        $SQL_BOOKS_BY_SERIE = "SELECT {0} FROM libbook "
            . SQL_BOOKS_LEFT_JOIN
            ." LEFT JOIN libseq ON libbook.BookId = libseq.BookId"
            ." WHERE libseq.SeqId = ? {1} "
            ." ORDER BY libbook.title"
            ;
        
        return self::getEntryArray ($SQL_BOOKS_BY_SERIE, array ($serieId), $n);
    }
    
    public static function getBooksByTag($tagId, $n) {
        return self::getEntryArray (self::SQL_BOOKS_BY_TAG, array ($tagId), $n);
    }
    
    public static function getBooksByCustom($customId, $id, $n) {
        $query = str_format (self::SQL_BOOKS_BY_CUSTOM, "{0}", "{1}", CustomColumn::getTableLinkName ($customId), CustomColumn::getTableLinkColumn ($customId));
        return self::getEntryArray ($query, array ($id), $n);
    }
    
    public static function getBookById($bookId) {
        
        $query = 'SELECT ' . self::BOOK_COLUMNS
            ." FROM libbook "
            . self::SQL_BOOKS_LEFT_JOIN
            ." WHERE libbook.BookId = ?"
            ;

        $params = array ($bookId);
        
        list ($totalNumber, $stmt) = parent::getDb()->executeQuery($query, '', '', $params);
            
        $result = $stmt->get_result();
        $post = $result->fetch_object();
        
        if ($post)
            return new Book($post);
        else
            return NULL;

    }
    
    public static function getBookByDataId($dataId) {
        $result = parent::getDb ()->prepare('select ' . self::BOOK_COLUMNS . ', data.name, data.format
from data, libbook ' . self::SQL_BOOKS_LEFT_JOIN . '
where data.book = libbook.BookId and data.id = ?');
        $result->execute (array ($dataId));
        while ($post = $result->fetchObject ())
        {
            $book = new Book ($post);
            $data = new Data ($post, $book);
            $data->id = $dataId;
            $book->datas = array ($data);
            return $book;
        }
        return NULL;
    }
    
    public static function getBooksByQuery($query, $n) {
        return self::getEntryArray (self::SQL_BOOKS_QUERY, array ("%" . $query . "%", "%" . $query . "%"), $n);
    }
    
    public static function getAllBooks() {
        
        $result = parent::getDb ()->query("SELECT title, COUNT(*) AS COUNT FROM libbook GROUP BY title");
        $entryArray = array();
        while ($post = $result->fetchObject ())
        {
            array_push ($entryArray, new Entry ($post->title, Book::getEntryIdByLetter ($post->title), 
                str_format (localize("bookword", $post->count), $post->count), "text", 
                array ( new LinkNavigation ("?page=".parent::PAGE_ALL_BOOKS_LETTER."&id=". rawurlencode ($post->title)))));
        }
        return $entryArray;
    }
    
    public static function getBooksByStartingLetter($letter, $n) {
        return self::getEntryArray (self::SQL_BOOKS_BY_FIRST_LETTER, array ($letter . "%"), $n);
    }
    
    public static function getEntryArray ($query, $params, $n) {
        
        list ($totalNumber, $stmt) = parent::getDb()->executeQuery($query, self::BOOK_COLUMNS, self::getFilterString(), $params, $n);
        
        $entryArray = array();
        $post = array();
        
        //will work only with native MYSQLI driver!
        //@link http://www.php.net/manual/en/mysqli-stmt.get-result.php
        $result = $stmt->get_result();
        
        while ($post = $result->fetch_object())
        {

            $book = new Book ($post);
            array_push ($entryArray, $book->getEntry ());
        }

        $result->close();
        
        //var_dump($entryArray, $totalNumber);
        return array ($entryArray, $totalNumber);
    }

    
    public static function getAllRecentBooks() {
        global $config;
        list ($entryArray, $totalNumber) = self::getEntryArray (self::SQL_BOOKS_RECENT . $config['cops_recentbooks_limit'], array (), -1);
        return $entryArray;
    }

    public static function getBookAnnotations($BookId) {
        
        $query = "SELECT DISTINCT body "
            ." FROM libbannotations a"
            ." WHERE a.BookId = ?"
            ;
            
        $params = array ($BookId);
        
        list ($totalNumber, $stmt) = parent::getDb()->executeQuery($query, '', '', $params);
            
        $result = $stmt->get_result();
        
        $annotations = array();
        
        while ($post = $result->fetch_row())
        {
            //fix flubusta html bug:
            $str = str_replace("<p class=book>", "<p class='f_book'>", $post[0] );
            $str = str_replace("[hr]", "<hr/>", $str);
            $annotations[] = $str;
        }
        
        return $annotations;

    }
    
    public static function getAllBooksByFirstLetter() {
        
        $query ='SELECT SUBSTRING(UPPER(title), 1, 1) as title, count(*) as count'
                .' FROM libbook'
                .' GROUP BY SUBSTRING(UPPER(title), 1, 1)'
                .' ORDER BY SUBSTRING(UPPER(title), 1, 1)'
                ;
        
        $result = parent::getDb()->query($query);
        $entryArray = array();
        while ( $post = $result->fetchObject () )
        {
            array_push ($entryArray, new Entry ($post->title, self::getEntryIdByLetter ($post->title), 
                str_format (localize("bookword", $post->count), $post->count), "text", 
                array ( new LinkNavigation ("?page=".parent::PAGE_BOOKS_FIRST_LETTER."&id=". rawurlencode ($post->title)))));
        }
        
        
        return $entryArray;
    }    
    
}
?>