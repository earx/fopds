<?php
/**
 * COPS (Calibre OPDS PHP Server) 
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gordon Page <gordon@incero.com> with integration/modification by Sébastien Lucas <sebastien@slucas.fr>
 */
    
    /**
     * Get filename for the book
     * @param Book book
     * @return string translitated filename like Tom_Hanna_Somatics.123.fb2
     */
    function getBookFileName($book) {
        
        $tr = array(
         "Ґ"=>"G","Ё"=>"YO","Є"=>"E","Ї"=>"YI","І"=>"I",
         "і"=>"i","ґ"=>"g","ё"=>"yo","№"=>"#","є"=>"e",
         "ї"=>"yi","А"=>"A","Б"=>"B","В"=>"V","Г"=>"G",
         "Д"=>"D","Е"=>"E","Ж"=>"ZH","З"=>"Z","И"=>"I",
         "Й"=>"Y","К"=>"K","Л"=>"L","М"=>"M","Н"=>"N",
         "О"=>"O","П"=>"P","Р"=>"R","С"=>"S","Т"=>"T",
         "У"=>"U","Ф"=>"F","Х"=>"H","Ц"=>"TS","Ч"=>"CH",
         "Ш"=>"SH","Щ"=>"SCH","Ъ"=>"'","Ы"=>"YI","Ь"=>"",
         "Э"=>"E","Ю"=>"YU","Я"=>"YA","а"=>"a","б"=>"b",
         "в"=>"v","г"=>"g","д"=>"d","е"=>"e","ж"=>"zh",
         "з"=>"z","и"=>"i","й"=>"y","к"=>"k","л"=>"l",
         "м"=>"m","н"=>"n","о"=>"o","п"=>"p","р"=>"r",
         "с"=>"s","т"=>"t","у"=>"u","ф"=>"f","х"=>"h",
         "ц"=>"ts","ч"=>"ch","ш"=>"sh","щ"=>"sch","ъ"=>"'",
         "ы"=>"yi","ь"=>"","э"=>"e","ю"=>"yu","я"=>"ya",
         " "=>"_", ',' => '', '.' => '', '!' => '', '"' => ''
        );
        
        
        $author = Author::getAuthorByBookId($book->id);
        $author = $author[0];
        
        $serie = Serie::getSerieByBookId($book->id);
        
        $filename = array();

        //get lastname
        $names = array_reverse( explode(' ', $author->name) );
        $filename[] = strtr($names[0],$tr);

        if ($serie) {
            
            $filename[] = strtr($serie->name,$tr);
        }
        
        $filename[] = strtr($book->title,$tr);

        //var_dump($book, $autor, $serie);
        return strtolower( implode('_', $filename) ).'.'.$book->fileType.'.zip';
    }
    
    /** send file to browser
     * @param string $filename full path
     * @param string $type mime type. for zipped - 'ZIP'
     */
    function sendFile($filename, $type = 'zip') {
        
        global $config;
        
        header("Location: ".dirname($_SERVER['SCRIPT_NAME']).DS.$config['flibusta_cache'].DS.$filename);

        //global $config;
        //
        //$expires = 60*60*24*14;
        //
        //header("Pragma: public");
        //header("Cache-Control: maxage=".$expires);
        //header('Expires: ' . gmdate('D, d M Y H:i:s', time()+$expires) . ' GMT');
        //
        //header("Content-Type: " . Data::$mimetypes[$type]);
        //
        //if ($type == "jpg") {
        //    header('Content-Disposition: filename="' . basename ($filename) . '"');
        //} else {
        //    header('Content-Disposition: attachment; filename="' . basename ($filename) . '"');
        //}
        
    }
    
    define ('DS', DIRECTORY_SEPARATOR);
    
    require_once ("config.php");
    require_once ("book.php");
    require_once ("data.php");
     
    global $config;

    $bookId = getURLParam ("id", NULL);
    $type = getURLParam ("type", "jpg");
    $idData = getURLParam ("data", NULL);
    
    if (is_null ($bookId))
    {
        $book = Book::getBookByDataId($idData);
    }
    else
    {
        $book = Book::getBookById($bookId);
    }
    
    $cache_dir = dirname(__FILE__).DS.$config['flibusta_cache']; //relative to current folder
    
    //extracted files will be stored as zipped
    $filename = getBookFileName($book);
    
    //$book->id.'.'.$book->fileType.'.zip';
    
    if (! file_exists( $cache_dir ) ) {
        
        if ( mkdir($cache_dir, 0777) )
            echo "cache created: $cache_dir <br>";
        else 
            echo "FAIL: unable to create cache: $cache_dir <br>";
    }
    
    //check if the file already extracted
    //TODO: break folder to few folders to avoid ahving too much files in one folder
    
    //http://test.lo/opds/cops/fetch.php?page=3&id=12732
    if (file_exists($cache_dir.DS.$filename)) {
        
        sendFile($filename);
        die;
    }
    

    $list = glob($config['flibusta_folder'].DS.'*fb*.zip');

    //search for large zip file which contains selected book    
    $matches = array();
    $file = null;

    foreach ($list as $line) {
        
        preg_match_all('/(-)(?P<start>[0-9]+)-(?P<end>[0-9]+)/', $line, $matches);
        //echo "<p>$line";

        //echo "<p>{$matches['start'][0]}, {$book->id}, {$matches['end'][0]}";
        
        if ( (int) $book->id >= (int) $matches['start'][0] && (int) $book->id <= (int) $matches['end'][0]) {
            
            $file = $line;
            break;
        }
    }
    //echo "<p>{$book->id}: $file";
    //die;
    
    if (! $file ) {
        
        echo 'cannot find bundle for file:'.$book->id.'.'.$book->fileType;
        die;
    }
    
    //var_dump($book, $file, $book->id.'.'.$book->fileType);
    $zip = new ZipArchive;
    
    $res = $zip->open($file);

    if ($res === TRUE) {

        // extract it to the path we determined above
        $zip->extractTo($cache_dir.DS, $book->id.'.'.$book->fileType );
        $zip->close();

        //pack it ro individual zip file
        $zip = new ZipArchive;
        
        if ($zip->open($cache_dir.DS.$filename,  ZipArchive::CREATE) === TRUE) {
            
            $zip->addFile($cache_dir.DS.$book->id.'.'.$book->fileType, $book->id.'.'.$book->fileType);
            $zip->close();
            
            //unlink($cache_dir.DS.$book->id.'.'.$book->fileType); //remove uncompressed one

    //echo $cache_dir.DS.$filename;
    //die;
            sendFile($filename);

        } else {
            echo 'failed to create archive';
        }        

    }
    else {
        
        echo 'FAIL: unable to open input file:'.$file;
    }

?>