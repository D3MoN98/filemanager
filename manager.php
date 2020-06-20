<?php 
$is_https = isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1)
|| isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https';


class Delete{
    public $localdir = '.';

    public static function deleteDir($dir) {
        if(is_dir($dir)) {
            try {
                $dir = $dir . DIRECTORY_SEPARATOR;
                $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
                $files = new RecursiveIteratorIterator($it,
                            RecursiveIteratorIterator::CHILD_FIRST);
                foreach($files as $file) {
                    if ($file->isDir()){
                        rmdir($file->getRealPath());
                    } else {
                        unlink($file->getRealPath());
                    }
                }
                rmdir($dir);
                return true;
            } catch (Exception $e) {
                return $e;
            }
        } else if(file_exists($dir)){
            unlink($dir);
        } else {
            return 'File or folder not exist';
        }
    }
}

class Zipper {
    /**
     * Add files and sub-directories in a folder to zip file.
     *
     * @param string $folder
     *   Path to folder that should be zipped.
     *
     * @param ZipArchive $zipFile
     *   Zipfile where files end up.
     *
     * @param int $exclusiveLength
     *   Number of text to be exclusived from the file path.
     */
    private static function folderToZip($folder, &$zipFile, $exclusiveLength) {
        
        try {
            $handle = opendir($folder);
            while (FALSE !== $f = readdir($handle)) {
                // Check for local/parent path or zipping file itself and skip.
                if ($f != '.' && $f != '..' && $f != basename(__FILE__)) {
                $filePath = "$folder/$f";
                // Remove prefix from file path before add to zip.
                $localPath = substr($filePath, $exclusiveLength);
        
                if (is_file($filePath)) {
                    $zipFile->addFile($filePath, $localPath);
                }
                elseif (is_dir($filePath)) {
                    // Add sub-directory.
                    $zipFile->addEmptyDir($localPath);
                    self::folderToZip($filePath, $zipFile, $exclusiveLength);
                }
                }
            }
            closedir($handle);
            return true;
      } catch (Exception $e) {
          return $e;
      }
  
      
    }
  
    /**
     * Zip a folder (including itself).
     *
     * Usage:
     *   Zipper::zipDir('path/to/sourceDir', 'path/to/out.zip');
     *
     * @param string $sourcePath
     *   Relative path of directory to be zipped.
     *
     * @param string $outZipPath
     *   Relative path of the resulting output zip file.
     */
    public static function zipDir($sourcePath, $outZipPath) {

        try {
            $pathInfo = pathinfo($sourcePath);
            $parentPath = $pathInfo['dirname'];
            $dirName = $pathInfo['basename'];
    
            $z = new ZipArchive();
            $z->open($outZipPath, ZipArchive::CREATE);
            $z->addEmptyDir($dirName);
            if ($sourcePath == $dirName) {
            self::folderToZip($sourcePath, $z, 0);
            }
            else {
            self::folderToZip($sourcePath, $z, strlen("$parentPath/"));
            }
            $z->close();
            return true;
        } catch (Exception $e) {
            return $e;
        }
        
    }
}

class Unzipper {
    public $localdir = '.';
    public $zipfiles = array();
  
    public function __construct() {
      // Read directory and pick .zip, .rar and .gz files.
      if ($dh = opendir($this->localdir)) {
        while (($file = readdir($dh)) !== FALSE) {
          if (pathinfo($file, PATHINFO_EXTENSION) === 'zip'
            || pathinfo($file, PATHINFO_EXTENSION) === 'gz'
            || pathinfo($file, PATHINFO_EXTENSION) === 'rar'
          ) {
            $this->zipfiles[] = $file;
          }
        }
        closedir($dh);
  
        if (!empty($this->zipfiles)) {
          $GLOBALS['status'] = array('info' => '.zip or .gz or .rar files found, ready for extraction');
        }
        else {
          $GLOBALS['status'] = array('info' => 'No .zip or .gz or rar files found. So only zipping functionality available.');
        }
      }
    }
  
    /**
     * Prepare and check zipfile for extraction.
     *
     * @param string $archive
     *   The archive name including file extension. E.g. my_archive.zip.
     * @param string $destination
     *   The relative destination path where to extract files.
     */
    public function prepareExtraction($archive, $destination = '') {
      // Determine paths.
      if (empty($destination)) {
        $extpath = $this->localdir;
      }
      else {
        $extpath = $this->localdir . '/' . $destination;
        // Todo: move this to extraction function.
        if (!is_dir($extpath)) {
          mkdir($extpath);
        }
      }
      // Only local existing archives are allowed to be extracted.
      if (in_array($archive, $this->zipfiles)) {
        self::extract($archive, $extpath);
      }
    }
  
    /**
     * Checks file extension and calls suitable extractor functions.
     *
     * @param string $archive
     *   The archive name including file extension. E.g. my_archive.zip.
     * @param string $destination
     *   The relative destination path where to extract files.
     */
    public static function extract($archive, $destination) {
      $ext = pathinfo($archive, PATHINFO_EXTENSION);
      switch ($ext) {
        case 'zip':
          self::extractZipArchive($archive, $destination);
          break;
        case 'gz':
          self::extractGzipFile($archive, $destination);
          break;
        case 'rar':
          self::extractRarArchive($archive, $destination);
          break;
      }
  
    }
  
    /**
     * Decompress/extract a zip archive using ZipArchive.
     *
     * @param $archive
     * @param $destination
     */
    public static function extractZipArchive($archive, $destination) {
      // Check if webserver supports unzipping.
      if (!class_exists('ZipArchive')) {
        $GLOBALS['status'] = array('error' => 'Error: Your PHP version does not support unzip functionality.');
        return;
      }
  
      $zip = new ZipArchive;
  
      // Check if archive is readable.
      if ($zip->open($archive) === TRUE) {
        // Check if destination is writable
        if (is_writeable($destination . '/')) {
          $zip->extractTo($destination);
          $zip->close();
          $GLOBALS['status'] = array('success' => 'Files unzipped successfully');
        }
        else {
          $GLOBALS['status'] = array('error' => 'Error: Directory not writeable by webserver.');
        }
      }
      else {
        $GLOBALS['status'] = array('error' => 'Error: Cannot read .zip archive.');
      }
    }
  
    /**
     * Decompress a .gz File.
     *
     * @param string $archive
     *   The archive name including file extension. E.g. my_archive.zip.
     * @param string $destination
     *   The relative destination path where to extract files.
     */
    public static function extractGzipFile($archive, $destination) {
      // Check if zlib is enabled
      if (!function_exists('gzopen')) {
        $GLOBALS['status'] = array('error' => 'Error: Your PHP has no zlib support enabled.');
        return;
      }
  
      $filename = pathinfo($archive, PATHINFO_FILENAME);
      $gzipped = gzopen($archive, "rb");
      $file = fopen($destination . '/' . $filename, "w");
  
      while ($string = gzread($gzipped, 4096)) {
        fwrite($file, $string, strlen($string));
      }
      gzclose($gzipped);
      fclose($file);
  
      // Check if file was extracted.
      if (file_exists($destination . '/' . $filename)) {
        $GLOBALS['status'] = array('success' => 'File unzipped successfully.');
  
        // If we had a tar.gz file, let's extract that tar file.
        if (pathinfo($destination . '/' . $filename, PATHINFO_EXTENSION) == 'tar') {
          $phar = new PharData($destination . '/' . $filename);
          if ($phar->extractTo($destination)) {
            $GLOBALS['status'] = array('success' => 'Extracted tar.gz archive successfully.');
            // Delete .tar.
            unlink($destination . '/' . $filename);
          }
        }
      }
      else {
        $GLOBALS['status'] = array('error' => 'Error unzipping file.');
      }
  
    }
  
    /**
     * Decompress/extract a Rar archive using RarArchive.
     *
     * @param string $archive
     *   The archive name including file extension. E.g. my_archive.zip.
     * @param string $destination
     *   The relative destination path where to extract files.
     */
    public static function extractRarArchive($archive, $destination) {
      // Check if webserver supports unzipping.
      if (!class_exists('RarArchive')) {
        $GLOBALS['status'] = array('error' => 'Error: Your PHP version does not support .rar archive functionality. <a class="info" href="http://php.net/manual/en/rar.installation.php" target="_blank">How to install RarArchive</a>');
        return;
      }
      // Check if archive is readable.
      if ($rar = RarArchive::open($archive)) {
        // Check if destination is writable
        if (is_writeable($destination . '/')) {
          $entries = $rar->getEntries();
          foreach ($entries as $entry) {
            $entry->extract($destination);
          }
          $rar->close();
          $GLOBALS['status'] = array('success' => 'Files extracted successfully.');
        }
        else {
          $GLOBALS['status'] = array('error' => 'Error: Directory not writeable by webserver.');
        }
      }
      else {
        $GLOBALS['status'] = array('error' => 'Error: Cannot read .rar archive.');
      }
    }
  
}


Class Manager{

    public function __construct(){

    }

    public static function get_absolute_path($path) {
        $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
        $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
        $absolutes = array();
        foreach ($parts as $part) {
            if ('.' == $part) continue;
            if ('..' == $part) {
                array_pop($absolutes);
            } else {
                $absolutes[] = $part;
            }
        }
        return implode(DIRECTORY_SEPARATOR, $absolutes);
    }
    
    
    public static function fm_clean_path($path, $trim = true){
        $path = $trim ? trim($path) : $path;
        $path = trim($path, '\\/');
        $path = str_replace(array('../', '..\\'), '', $path);
        $path =  Manager::get_absolute_path($path);
        if ($path == '..') {
            $path = '';
        }
        return str_replace('\\', '/', $path);
    }

    public static function readableBytes($bytes) {
        $i = floor(log($bytes) / log(1024));
        $sizes = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
    
        return sprintf('%.02F', $bytes / pow(1024, $i)) * 1 . ' ' . $sizes[$i];
    }

    public static function sorting($array, $flag = null, $order = 'asc'){

        if(is_null($flag)){
            $folders = array();
            $files = array();
            foreach ($array as $key) {
                if($key['type'] == 'folder'){
                    array_push($folders, $key);
                } else {
                    array_push($files, $key);
                }
            }
            return array_merge($folders, Manager::sorting($files, 'name'));
        }
    
        $result = array_column($array, $flag);
    
        switch ($flag) {
            case 'name':
                $flag = SORT_REGULAR;
                break;
            case 'size':
                $flag = SORT_NUMERIC;
                break;
            case 'modified':
                $flag = SORT_NUMERIC;
                break;
            default:
                $flag = SORT_REGULAR;
                break;
        }
    
        switch ($order) {
            case 'asc':
                $order = SORT_ASC;
                break;
            
            default:
                $order = SORT_DESC;
                break;
        }
        array_multisort($result, $order, $flag, $array);
    
        return $array;
    }

    public static function GetDirectorySize($path){
        $bytestotal = 0;
        $path = realpath($path);
        if($path!==false && $path!='' && file_exists($path)){
            foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $object){
                $bytestotal += $object->getSize();
            }
        }
        return $bytestotal;
    }
    
    public function scan($dir){

        // $dir = $this->fm_clean_path($dir);

        $files = array();

        // Is there actually such a folder/file?

        if(file_exists($dir)){
        
            foreach(scandir($dir) as $f) {
            
                if(!$f || $f[0] == '.') {
                    continue; // Ignore hidden files
                }

                if(is_dir($dir . DIRECTORY_SEPARATOR . $f)) {

                    // The path is a folder

                    $files[] = array(
                        "name" => $f,
                        "type" => "folder",
                        "path" => $dir . DIRECTORY_SEPARATOR . $f,
                        "size" => filesize($dir . DIRECTORY_SEPARATOR . $f), // Gets the size of this file
                        "items" => $this->scan($dir . DIRECTORY_SEPARATOR . $f), // Recursively get the contents of the folder
                        "modified" => filemtime($dir . DIRECTORY_SEPARATOR . $f)
                    );
                }
                
                else {

                    // It is a file

                    $files[] = array(
                        "name" => $f,
                        "type" => "file",
                        "path" => $dir . DIRECTORY_SEPARATOR . $f,
                        "size" => filesize($dir . DIRECTORY_SEPARATOR . $f), // Gets the size of this file
                        "modified" => filemtime($dir . DIRECTORY_SEPARATOR . $f)
                    );
                }
            }
        
        }
        
        return $this->sorting($files);
    }

    public function get_files_tbody($files){
        ob_start() ?>
        <tr data-path="<?php echo dirname($this->get_absolute_path($files[0]['path']), 2); ?>" data-type="folder"><td colspan="4">..</td></tr>
        <?php foreach($files as $key): ?>
        <?php $file = pathinfo($key['path']); ?>
        <tr class="task" data-name="<?php echo $key['name']; ?>" data-extention="<?php echo $file['extension'] ?>" data-path="<?php echo $key['path'] ?>" data-type="<?php echo $key['type'] ?>">
            <td scope="row">
                <?php if($key['type'] == 'file'): ?>
                    <i class="fa fa-file"></i> 
                <?php else: ?>
                    <i class="fa fa-folder"></i> 
                <?php endif; ?>
                <?php echo $key['name'] ?>
            </td>
            <td><?php echo date('d M Y, h:i a', $key['modified']) ?></td>
            <td><?php echo $key['type'] == 'file' ? $this->readableBytes($key['size']) : 'Folder' ?></td>
            <!-- <td>
                <button type="button" class="btn btn-danger btn-sm"><i class="fa fa-trash-o" aria-hidden="true"></i></button>
            </td> -->
        </tr>
        <?php endforeach; ?>
        <?php
        return ob_get_clean();
    }

    public static function rename($dir, $new){
        if (is_dir($dir)) {
            if(!is_dir(dirname($dir).DIRECTORY_SEPARATOR.$new)){
                rename($dir, dirname($dir).DIRECTORY_SEPARATOR.$new);
                return true;
            }
            return false;
        } else if(is_file($dir)){
            if(!file_exists(dirname($dir).DIRECTORY_SEPARATOR.$new)){
                rename($dir, dirname($dir).DIRECTORY_SEPARATOR.$new);
                return true;
            }
            return false;
        }
    }
}

$manager = new Manager();
$files = $manager->scan('.');
$tbody = $manager->get_files_tbody($files);
$actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";


if(isset($_POST['action'])){
    if($_POST['action'] == 'get_files'){
        if($_POST['type'] == 'folder'){
            echo json_encode(['html' => $manager->get_files_tbody($manager->scan($_POST['path']))]);
            return;
        } else if($_POST['type'] == 'file'){
            return;
        }
    }
    if($_POST['action'] == 'delete'){
        $delete_result = Delete::deleteDir($_POST['path']);
        if($delete_result === true){
            echo json_encode(['html' => $manager->get_files_tbody($manager->scan(dirname($_POST['path'])))]);
        } else {
            echo json_encode(['error' => $delete_result, 'html' => $manager->get_files_tbody($manager->scan(dirname($_POST['path'])))]);
        }
        return;
    }
    if($_POST['action'] == 'compress'){
        $des = dirname($_POST['path']).'/'.basename($_POST['path']).'-'.time().'.zip';
        $timestart = microtime(TRUE);
        $zip_result =   Zipper::zipDir($_POST['path'], $des);
        $timeend = microtime(TRUE);

        $time = round($timeend - $timestart, 4);

        if($zip_result === true){
            echo json_encode(['name' => basename($des), 'time' => $time, 'html' => $manager->get_files_tbody($manager->scan(dirname($_POST['path'])))]);
        } else {
            echo json_encode(['error' => $zip_result, 'html' => $manager->get_files_tbody($manager->scan(dirname($_POST['path'])))]);
        }
        return;
    }
    if($_POST['action'] == 'download'){
        $dir = $_POST['path'];
        if(is_dir($dir)) {
            $file_name = basename($_POST['path']).'-'.time().'.zip';
            $des = dirname($_POST['path']).'/'.$file_name;
            $zip_result =   Zipper::zipDir($_POST['path'], $des);
            
            if($zip_result === true){
                $file_path = dirname($actual_link).'/'.Manager::fm_clean_path(dirname($_POST['path'])).'/'.$file_name;
                echo json_encode(['is_zipped' => 1, 'name' => basename($des), 'file' => $file_path, 'html' => $manager->get_files_tbody($manager->scan(dirname($_POST['path'])))]);
            } else {
                echo json_encode(['error' => $zip_result, 'html' => $manager->get_files_tbody($manager->scan(dirname($_POST['path'])))]);
            }
            return;
        } else if(file_exists($dir)){
            $file_path = dirname($actual_link).'/'.Manager::fm_clean_path($dir);
            echo json_encode(['file' => $file_path]);
            return;
        }
    }
    if($_POST['action'] == 'extract'){
        
        $timestart = microtime(TRUE);
        try {
            $unzipper = new Unzipper;
            $unzipper->prepareExtraction(basename($_POST['path']), $_POST['destination']);
            $timeend = microtime(TRUE);
            $time = round($timeend - $timestart, 4);
    
            $scan_path = $_POST['destination'] == '' ? '.' : dirname($_POST['destination']);
            echo json_encode(['name' => basename($_POST['path']), 'time' => $time, 'html' => $manager->get_files_tbody($manager->scan($scan_path))]);
            return;
        } catch (Exception $e) {
            echo json_encode(['error' => $e]);
            return;
        }
    }
    if($_POST['action'] == 'rename'){
        try {
            if(Manager::rename($_POST['path'], $_POST['new'])){
                echo json_encode(['name' => $_POST['new'], 'html' => $manager->get_files_tbody($manager->scan(dirname($_POST['path'])))]);
                return;
            } else {
                echo json_encode(['error' => 'Name already exist', 'html' => $manager->get_files_tbody($manager->scan(dirname($_POST['path'])))]);
                return;
            }
        } catch (Exception $e) {
            echo json_encode(['error' => $e]);
            return;
        }
    }
}



?>

<!doctype html>
<html lang="en">
  <head>
    <title>Manager</title>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">

    <style>
        *{
            font-family: 'Ubuntu', sans-serif;
        }
        .table>tbody>tr{
            cursor: default;
        }
        .table>tbody>td{
            cursor: default;
        }
        /* context menu */

        .context-menu {
            display: none;
            position: absolute;
            z-index: 10;
            width: 210px;
            background-color: #fff;
            opacity: 0.9;
            border-radius: 10px;

            -webkit-transition: all 0.3s;
            -moz-transition: all 0.3s;
            -ms-transition: all 0.3s;
            -o-transition: all 0.3s;
            transition: all 0.3s;
            max-height: 0;
            overflow: hidden;
            opacity: 0;
        }

        .context-menu--active {
            max-height: fit-content;
            display: block;
            opacity: 1;
        }

        .context-menu__items {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .context-menu__item {
            display: block;
            margin-bottom: 4px;
        }

        .context-menu__item:last-child {
            margin-bottom: 0;
        }

        .context-menu__link {
            display: block;
            /* padding: 4px 12px; */
            padding: 8px 15px;
            color: grey;
            text-decoration: none !important;
            font-size: 14px;
            /* letter-spacing: 1px; */
        }
        .context-menu__link:hover{
            color: #1f202a;
        }
        .context-menu__link .fa{
            margin-right: 10px;
            width: 10px;
        }
        body{
            background: #1f202a;
        }
        .container-otr{
            height: 100vh;
        }
        .table-otr h2{
            color: #a4a6b1;
        }
        .text-white{
            color: #a4a6b1;
        }
        .table-otr{
            margin: 50px 0;
            background: #272935;
            border-radius: 10px;
            padding: 50px 0;
            height: fit-content;
            /* height: 75%; */
        }
       
        tbody {
            display:block;
            height: 300px;
            overflow:auto;
        }
        thead, tbody tr {
            display:table;
            width:100%;
            table-layout:fixed;/* even columns width , fix width of table too*/
        }
        thead {
            width: calc( 100% - 1em )/* scrollbar is average 1em/16px width, remove it from thead width */
        }
        table {
            width:75%;
        }
        .table{
            color: #a4a6b1;
        }
        .task.table-primary{
            /* color: #1f202a; */
            /* background: #cbe2fb; */
            background: #1f202a;
        }
        .table-primary, .table-primary>td, .table-primary>th{
            background: #1f202a;
        }
        .table tbody tr:hover{
            box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
        }
        * {
        -webkit-touch-callout: none; /* iOS Safari */
            -webkit-user-select: none; /* Safari */
            -khtml-user-select: none; /* Konqueror HTML */
            -moz-user-select: none; /* Old versions of Firefox */
                -ms-user-select: none; /* Internet Explorer/Edge */
                    user-select: none; /* Non-prefixed version, currently
                                        supported by Chrome, Edge, Opera and Firefox */
        }
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: none;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #1f202a;
        }
    </style>
  </head>
  <body>
      <div class="container-fluid">
          <div class="row container-otr">
              <div class="col-4">

              </div>
              <div class="col-8">
                <div class="row table-otr">
                    <div class="col-12">
                        <h2>File Manager</h2>
                    </div>
                    <div class="col-12">
                        <table class="table table-borderless">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Modified</th>
                                    <th>Size</th>
                                    <!-- <th>Action</th> -->
                                </tr>
                            </thead>
                            <tbody id="filelist">
                                <?php echo $tbody ?>
                            </tbody>
                        </table>
                    </div>
                </div>
              </div>
          </div>
      </div>
  

    <nav id="context-menu" class="context-menu shadow">
        <ul class="context-menu__items">
            <li class="context-menu__item compress-item">
                <a href="#" class="context-menu__link" data-path="" data-action="compress"><i class="fa fa-compress"></i> Compress</a>
            </li>
            <li class="context-menu__item extract-item">
                <a href="#" class="context-menu__link" data-path="" data-action="extract"><i class="fa fa-expand"></i> Extract</a>
            </li>
            <li class="context-menu__item">
                <a href="#" download="" class="context-menu__link download" data-path="" data-action="download"><i class="fa fa-download"></i> Download</a>
            </li>
            <li class="context-menu__item">
                <a href="#" class="context-menu__link" data-path="" data-action="rename"><i class="fa fa-edit"></i> Rename</a>
            </li>
            <li class="context-menu__item">
                <a href="#" class="context-menu__link delete" data-path="" data-action="delete"><i class="fa fa-trash"></i> Delete</a>
            </li>
        </ul>
    </nav>
    <!-- Optional JavaScript -->
    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@9"></script>

    <script>
        $(document).on('click', '.table>tbody>tr', function(){
            $('.table>tbody>tr').removeClass('table-primary');
            $(this).addClass('table-primary');
        });

        $(document).on('dblclick', '.table>tbody>tr', function(e){
            event.preventDefault();

            var path = $(this).data('path');
            var type = $(this).data('type');
            $.ajax({
                url: '',
                data: {
                    action: 'get_files',
                    path: path,
                    type: type
                },
                type: 'post',
                dataType: 'json',
                
                success:function(data){
                    if(type == 'folder')
                        $('#filelist').html(data.html);
                }
            });
        });

    </script>

    <script>
        (function() {
  
            "use strict";

            /**
             * Function to check if we clicked inside an element with a particular class
             * name.
             * 
             * @param {Object} e The event
             * @param {String} className The class name to check against
             * @return {Boolean}
             */
            function clickInsideElement( e, className ) {
                var el = e.srcElement || e.target;
                
                if ( el.classList.contains(className) ) {
                return el;
                } else {
                while ( el = el.parentNode ) {
                    if ( el.classList && el.classList.contains(className) ) {
                    return el;
                    }
                }
                }

                return false;
            }

            /**
             * Get's exact position of event.
             * 
             * @param {Object} e The event passed in
             * @return {Object} Returns the x and y position
             */
            function getPosition(e) {
                var posx = 0;
                var posy = 0;

                if (!e) var e = window.event;
                
                if (e.pageX || e.pageY) {
                posx = e.pageX;
                posy = e.pageY;
                } else if (e.clientX || e.clientY) {
                posx = e.clientX + document.body.scrollLeft + document.documentElement.scrollLeft;
                posy = e.clientY + document.body.scrollTop + document.documentElement.scrollTop;
                }

                return {
                x: posx,
                y: posy
                }
            }
            
            /**
             * Variables.
             */
            var contextMenuClassName = "context-menu";
            var contextMenuItemClassName = "context-menu__item";
            var contextMenuLinkClassName = "context-menu__link";
            var contextMenuActive = "context-menu--active";

            var taskItemClassName = "task";
            var taskItemInContext;

            var clickCoords;
            var clickCoordsX;
            var clickCoordsY;

            var menu = document.querySelector("#context-menu");
            var menuItems = menu.querySelectorAll(".context-menu__item");
            var menuState = 0;
            var menuWidth;
            var menuHeight;
            var menuPosition;
            var menuPositionX;
            var menuPositionY;

            var windowWidth;
            var windowHeight;

            /**
             * Initialise our application's code.
             */
            function init() {
                contextListener();
                clickListener();
                keyupListener();
                resizeListener();
            }

            /**
             * Listens for contextmenu events.
             */
            function contextListener() {
                document.addEventListener( "contextmenu", function(e) {
                taskItemInContext = clickInsideElement( e, taskItemClassName );

                
                
                if ( taskItemInContext ) {
                    $('.task').removeClass('table-primary');
                    $(taskItemInContext).addClass('table-primary');
                    var extention = $(taskItemInContext).data('extention')

                    if(extention == 'zip'){
                        $('.extract-item').show();
                        $('.compress-item').hide();
                    } else {
                        $('.extract-item').hide();
                        $('.compress-item').show();
                    }

                    e.preventDefault();
                    toggleMenuOn();
                    positionMenu(e);
                } else {
                    $('.task').removeClass('table-primary');
                    taskItemInContext = null;
                    // $(taskItemClassName).removeClass('table-primary');
                    toggleMenuOff();
                }
                });
            }

            /**
             * Listens for click events.
             */
            function clickListener() {
                document.addEventListener( "click", function(e) {
                    var clickeElIsLink = clickInsideElement( e, contextMenuLinkClassName );

                    if ( clickeElIsLink ) {
                        e.preventDefault();
                        menuItemListener( clickeElIsLink );
                    } else {
                        var button = e.which || e.button;
                        if ( button === 1 ) {
                        toggleMenuOff();
                        }
                    }
                });
            }

            /**
             * Listens for keyup events.
             */
            function keyupListener() {
                window.onkeyup = function(e) {
                if ( e.keyCode === 27 ) {
                    toggleMenuOff();
                }
                }
            }

            /**
             * Window resize event listener
             */
            function resizeListener() {
                window.onresize = function(e) {
                toggleMenuOff();
                };
            }

            /**
             * Turns the custom context menu on.
             */
            function toggleMenuOn() {
                if ( menuState !== 1 ) {
                    menuState = 1;
                    menu.classList.add( contextMenuActive );
                }
            }

            /**
             * Turns the custom context menu off.
             */
            function toggleMenuOff() {
                if ( menuState !== 0 ) {
                    menuState = 0;
                    menu.classList.remove( contextMenuActive );
                }
            }

            /**
             * Positions the menu properly.
             * 
             * @param {Object} e The event
             */
            function positionMenu(e) {
                clickCoords = getPosition(e);
                clickCoordsX = clickCoords.x;
                clickCoordsY = clickCoords.y;

                menuWidth = menu.offsetWidth + 4;
                menuHeight = menu.offsetHeight + 4;

                windowWidth = window.innerWidth;
                windowHeight = window.innerHeight;

                if ( (windowWidth - clickCoordsX) < menuWidth ) {
                    menu.style.left = windowWidth - menuWidth + "px";
                } else {
                    menu.style.left = clickCoordsX + "px";
                }

                if ( (windowHeight - clickCoordsY) < menuHeight ) {
                    menu.style.top = windowHeight - menuHeight + "px";
                } else {
                    menu.style.top = clickCoordsY + "px";
                }
            }

            /**
             * Dummy action function that logs an action when a menu item link is clicked
             * 
             * @param {HTMLElement} link The link that was clicked
             */
            function menuItemListener( link ) {
                link.setAttribute("data-path", taskItemInContext.getAttribute("data-path"));
                var path = taskItemInContext.getAttribute("data-path");
                var filename = taskItemInContext.getAttribute("data-name");
                var action = link.getAttribute("data-action");

                if(action == 'delete'){
                    deletefn(action, path);
                } else if(action == 'compress'){
                    compressFn(action, path);
                } else if(action == 'download'){
                    dwnloadFn(action, path);
                } else if(action == 'extract'){
                    extractFn(action, path);
                } else if(action == 'rename'){
                    renameFn(action, path, filename);
                }
                
                toggleMenuOff();
            }

            function deletefn(action, path){
                Swal.fire({
                    title: 'Are you sure to delete this?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.value) {
                        $.ajax({
                            url: '',
                            data: {
                                action: action,
                                path: path,
                            },
                            type: 'post',
                            dataType: 'json',
                            success:function(data){
                                console.log(data);

                                if(data.error){
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Oops...',
                                        text: 'Something went wrong!',
                                    })
                                }

                                $('#filelist').html(data.html);
                            
                                
                            }
                        })
                    }
                })
            }

            function compressFn(action, path){
                Swal.fire({
                    title: 'Are you sure want to compress?',
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, do it!'
                }).then((result) => {
                    if (result.value) {
                        $.ajax({
                            url: '',
                            data: {
                                action: action,
                                path: path,
                            },
                            type: 'post',
                            dataType: 'json',
                            beforeSend:function(){
                                Swal.fire({
                                    // title: 'Auto close alert!',
                                    html: 'Please wait.....',
                                    timer: 2000*30,
                                    timerProgressBar: true,
                                    onBeforeOpen: () => {
                                        Swal.showLoading()
                                    }
                                });
                            },
                            success:function(data){
                                console.log(data);
                                $('#filelist').html(data.html);


                                if(data.error){
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Oops...',
                                        text: 'Something went wrong!',
                                    })
                                } else {
                                    var row = $("#filelist").find(".task[data-name='"+data.name+"']");
                                    row.addClass('table-primary'); 
                                    $('#filelist').animate({ 
                                        scrollTop: row.offset().top - 200
                                    }, 500);
                                }
                                Swal.close();

                                Swal.fire(
                                    'Compress Successfull',
                                    'Successfully created archive '+ data.name + '. Time take '+data.time+' seconds',
                                    'success'
                                )
                            
                                
                            }
                        })
                    }
                })
            }

            function dwnloadFn(action, path){
                $.ajax({
                    url: '',
                    data: {
                        action: action,
                        path: path,
                    },
                    type: 'post',
                    dataType: 'json',
                    beforeSend:function(){
                        Swal.fire({
                            // title: 'Auto close alert!',
                            html: 'Please wait.....',
                            timer: 2000*30,
                            timerProgressBar: true,
                            onBeforeOpen: () => {
                                Swal.showLoading()
                            }
                        });
                    },
                    success:function(data){
                        console.log(data);

                        if(data.error){
                            Swal.fire({
                                icon: 'error',
                                title: 'Oops...',
                                text: 'Something went wrong!',
                            })
                            return;
                        }

                        if(data.is_zipped){
                            $('#filelist').html(data.html);
                            var row = $("#filelist").find(".task[data-name='"+data.name+"']");
                            row.addClass('table-primary'); 
                            $('#filelist').animate({ 
                                scrollTop: row.offset().top - 200
                            }, 500);
                        }

                        var file_path = data.file;
                        var a = document.createElement('A');
                        a.href = file_path;
                        a.download = file_path.substr(file_path.lastIndexOf('/') + 1);
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        Swal.close();
                        
                    }
                })
            }

            function extractFn(action, path){
                Swal.fire({
                    title: 'Extract to (Optional)',
                    input: 'text',
                    inputValue: '',
                    confirmButtonText: 'Extract',
                    showCancelButton: true,
                }).then((result) => {
                    $.ajax({
                        url: '',
                        data: {
                            action: action,
                            path: path,
                            destination: result.value
                        },
                        type: 'post',
                        dataType: 'json',
                        beforeSend:function(){
                            Swal.fire({
                                html: 'Please wait.....',
                                timer: 2000*30,
                                timerProgressBar: true,
                                onBeforeOpen: () => {
                                    Swal.showLoading()
                                }
                            });
                        },
                        success:function(data){
                            console.log(data);

                            if(data.error){
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Oops...',
                                    text: 'Something went wrong!',
                                })
                            }

                            $('#filelist').html(data.html);
                            Swal.close();

                            Swal.fire(
                                'Compress Successfull',
                                'Extract compllete. Time take '+data.time+' seconds',
                                'success'
                            )
                        
                            
                        }
                    })
                })
            }

            function renameFn(action, path, filename){
                Swal.fire({
                    title: 'Raname',
                    input: 'text',
                    inputValue: filename,
                    confirmButtonText: 'Rename',
                    showCancelButton: true,
                }).then((result) => {
                    $.ajax({
                        url: '',
                        data: {
                            action: action,
                            path: path,
                            new: result.value
                        },
                        type: 'post',
                        dataType: 'json',
                        success:function(data){
                            console.log(data);

                            $('#filelist').html(data.html);

                            if(data.error){
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Oops...',
                                    text: data.error,
                                })
                            } else{
                                var row = $("#filelist").find(".task[data-name='"+data.name+"']");
                                row.addClass('table-primary'); 
                                $('#filelist').animate({ 
                                    scrollTop: row.offset().top - 200
                                }, 500);
                            }

                            
                        }
                    })
                })
            }

            /**
             * Run the app.
             */
            init();

            })();
    </script>

   
  </body>
</html>