<?php
function build_and_release($slug, $rootPath, $deployKey, $url){
  if(!isset($deployKey) || $deployKey === ""){
    echo("Deploy key not set.");
    exit(2);
  }

  $zip = new ZipArchive();
  $filename = $rootPath . '/' . $slug . '.zip';

  if(file_exists($filename)){
    unlink($filename);
  }

  if($zip->open($filename, ZipArchive::CREATE) !== true){
    exit("Could not create {$filename}");
  }

  $files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($rootPath),
    RecursiveIteratorIterator::LEAVES_ONLY
  );

  foreach ($files as $name => $file){
    // Skip directories (they would be added automatically)
    if(!$file->isDir()){
      // Get real and relative path for current file
      $filePath = $file->getRealPath();
      $relativePath = substr($filePath, strlen($rootPath) + 1);

      $dirs = explode(DIRECTORY_SEPARATOR, $relativePath);

      if(
        $dirs[0] !== '.git'
        &&
        $dirs[0] !== '.circleci'
      ){
        $zip->addFile($filePath, $slug . '/' . $relativePath);
      }    
    }
  }

  $zip->close();

  echo('Zip Built' . PHP_EOL);

  $postOpts = array(
    'action' => 'wup_release',
    'deployKey' => $deployKey,
    'release' => new CURLFile($filename)
  );


  $request = curl_init($url);
  curl_setopt($request, CURLOPT_POST, true);
  curl_setopt(
    $request,
    CURLOPT_POSTFIELDS,
    $postOpts
  );
  curl_setopt($request, CURLOPT_RETURNTRANSFER, true);

  $result = curl_exec($request);
  $details = json_decode($result);

  curl_close($request);

  if(!isset($details->error) && !isset($details->success)){
    var_dump($details);
    echo('Something went wrong submitting to ' . $url . PHP_EOL);
    exit(1);
    return;
  }

  if(isset($details->error)){
    echo($details->error);
  }else{
    echo($details->success);
  }
}