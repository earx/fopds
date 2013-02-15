<?php
/**
 * COPS (Calibre OPDS PHP Server) 
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gordon Page <gordon@incero.com> with integration/modification by Sébastien Lucas <sebastien@slucas.fr>
 */
    
    /** send file to browser
     * @param string $filename full path
     * @param string $type mime type. for zipped - 'ZIP'
     */
    function sendFile($filename, $type = 'zip') {

        global $config;
        
        $expires = 60*60*24*14;
        
        header("Pragma: public");
        header("Cache-Control: maxage=".$expires);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time()+$expires) . ' GMT');

        header("Content-Type: " . Data::$mimetypes[$type]);
        
        if ($type == "jpg") {
            header('Content-Disposition: filename="' . basename ($filename) . '"');
        } else {
            header('Content-Disposition: attachment; filename="' . basename ($filename) . '"');
        }
        
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
    $filename = $book->id.'.'.$book->fileType.'.zip';
    
    if (! file_exists( $cache_dir ) ) {
        
        if ( mkdir($cache_dir, 0777) )
            echo "cache created: $cache_dir <br>";
        else 
            echo "FAIL: unable to create cache: $cache_dir <br>";
    }
    else {

        //check if the file already extracted
        //TODO: break folder to few folders to avoid ahving too much files in one folder
        
        if (file_exists($cache_dir.DS.$filename)) {
            
            sendFile($cache_dir.DS.$filename);
            die;
        }
        
    }

    $list = glob($config['flibusta_folder'].DS.'*fb*.zip');

    //search for large zip file which contains selected book    
    $matches = array();
    $file = null;

    foreach ($list as $line) {
        
        preg_match_all('/(-)(?P<start>[0-9]+)-(?P<end>[0-9]+)/', $line, $matches);
        //echo "<p>$line";
        
        if ($book->id >= $matches['start'][0] && $book->id <= $matches['end'][0]) {
            
            $file = $line;
            break;
        }
    }
    
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
        
        echo "<p>".$cache_dir.DS.$filename."<BR>";

        if ($zip->open($cache_dir.DS.$filename,  ZipArchive::CREATE) === TRUE) {
            
            $zip->addFile($cache_dir.DS.$book->id.'.'.$book->fileType, $book->id.'.'.$book->fileType);
            $zip->close();
            
            unlink($cache_dir.DS.$book->id.'.'.$book->fileType); //remove uncompressed one

            sendFile($cache_dir.DS.$filename);

        } else {
            echo 'failed to create archive';
        }        

    }
    else {
        
        echo 'FAIL: unable to open input file:'.$file;
    }

?>