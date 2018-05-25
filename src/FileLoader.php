<?php

namespace Meow
{
    use \Silex\Application;
    use Symfony\Component\Config\Definition\Exception\Exception;
    use \Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\HttpFoundation\ResponseHeaderBag;
    use Symfony\Component\HttpFoundation\File\UploadedFile;
    use \Symfony\Component\HttpFoundation\File;


    class FileLoader
    {
        private $maxFileSize = 1024 * 1024 * 100;
        public function AddNewFile(Request $request, Application $app)
        {
            if ($request->files->has('meowfile')) {
                /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $file */
                $file = $request->files->get('meowfile');
                if (!empty($file) && $file->isValid()) {
                    dump('Uploading file:');
                    $storedFile = StoredFile::AddFileToStorage($file, $app['db'], $app['usermanager.service']);
                    if($storedFile)
                    {
                        return $app['twig']->render('uploadresult.twig',
                            array('success' => true,
                                'link' => $storedFile->GetCustomUrl()));
                    }
                    else
                    {
                        return $app['twig']->render('uploadresult.twig',
                            array('success' => false,
                                'text' => 'Upload failed.'));
                    }
                } else
                    return $app['twig']->render('uploadresult.twig',
                        array('success' => false,
                            'text' => 'No file specified'));
            }
            else if($request->files->has('meowfile_remote')){
                $file = $request->files->get('meowfile_remote');
                $remoteToken = $request->request->get('private_key');
                if (!empty($file) && $file->isValid()){
                    $storedFile = StoredFile::AddFileToStorage($file, $app['db'], $app['usermanager.service'], $remoteToken);
                    if($storedFile)
                    {
                        if($request->request->get('plaintext'))
                        {
                            return new Response($storedFile->GetCustomUrl(), 201);
                        }
                        $jsn = array(
                            'file' => $storedFile->GetCustomUrl()
                        );
                        return $app->json($jsn);
                    }
                }
            }
            return $app['twig']->render('uploadresult.twig',
                array('success' => false,
                    'text' => 'No input specified'));
        }

        public function HandleDropzoneRequest(Request $request, Application $app)
        {
            $status = array('success' => false, 'message' => 'No input supplied');
            if($request->files->has('meowfile'))
            {
                /** @var UploadedFile $file */
                $file = $request->files->get('meowfile');
                if (!empty($file) && $file->isValid()) {
                    dump('Handling dropzone request:');
                    $storedFile = StoredFile::AddFileToStorage($file, $app['db'], $app['usermanager.service']);
                    if($storedFile)
                    {
                        $status = array(
                            'success' => true,
                            'download' => $storedFile->GetCustomUrl()
                        );
                    }
                    else
                    {
                        $status = array(
                            'success' => false,
                            'message' => "Upload failed"
                        );
                    }
                } else {
                    dump($file->getErrorMessage());
                    $status = array(
                        'success' => false,
                        'message' => $file->getErrorMessage()
                    );
                }
            }
            return $app->json($status);
        }

        public function MirrorRemoteFile(Request $request, Application $app)
        {
            if($request->request->has('mirrorfile'))
            {
                $path = $request->request->get('mirrorfile');
                $remoteSize = $this->QueryRemoteFileSize($path);

                if($remoteSize < 1)
                {
                    return $app['twig']->render('uploadresult.twig',
                        array('success' => false,
                            'text' => 'Unable to get file size from the target server, upload aborted'));
                }

                if($remoteSize > $this->maxFileSize)
                {
                    return $app['twig']->render('uploadresult.twig',
                        array('success' => false,
                            'text' => 'Upload failed due to file size constraints'));
                }

                $savedFile = $this->FetchRemoteFile($path, $remoteSize);
                $extractedFilename = $this->ExtractFilenameFromUrl($path);

                $uploadedFile = new UploadedFile(
                    $savedFile,
                    $extractedFilename,
                    null,
                    $remoteSize,
                    0,
                    true
                );


                dump('Uploading file:');
                $storedFile = StoredFile::AddFileToStorage($uploadedFile, $app['db'], $app['usermanager.service']);
                if($storedFile)
                {
                    return $app['twig']->render('uploadresult.twig',
                        array('success' => true,
                            'link' => $storedFile->GetCustomUrl()));
                }
                else
                {
                    return $app['twig']->render('uploadresult.twig',
                        array('success' => false,
                            'text' => 'Upload failed.'));
                }

            }
            return $app['twig']->render('uploadresult.twig',
                array('success' => false,
                    'text' => 'No input specified'));
        }

        public function ServeFileDirect(Request $request, Application $app, $customUrl, $fileExtension)
        {
            if($result = StoredFile::LookupFile($app['db'], $customUrl, 'direct'))
            {
                dump('Serving: ' . $result->GetFilePath());
                ob_end_clean();
                if($result->ShouldEmbed())
                    return $app->sendFile($result->GetFilePath(), 200, array());
                else
                {
                    return $app->sendFile($result->GetFilePath(), 200, array())
                        ->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $result->GetOriginalName());
                }

            }
            else
                throw new Exception('404');
        }

        public function ServeFileGeneric(Request $request, Application $app, $customUrl)
        {
            dump($customUrl);
            return $customUrl;
        }

        public function ServeFileService(Request $request, Application $app, $serviceUrl)
        {
            dump($serviceUrl);
            return $serviceUrl;
        }

        public function QueryRemoteFileSize($url)
        {
            $ch = curl_init($url);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HEADER, TRUE);
            curl_setopt($ch, CURLOPT_NOBODY, TRUE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $data = curl_exec($ch);
            $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

            curl_close($ch);
            return $size;
        }

        public function FetchRemoteFile($url, $fileSize)
        {
            $tempPath = tempnam(sys_get_temp_dir(), 'miufile');
            dump("Attempting to write {$fileSize} bytes from ${url} to ${tempPath}");
            $fp = fopen($tempPath, 'w');
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_FILE, $fp);

            $data = curl_exec($ch);

            curl_close($ch);
            fclose($fp);
            dump("Written");
            return $tempPath;
        }

        public function ExtractFilenameFromUrl($url)
        {
            $pathinfo = pathinfo($url);
            if(!isset($pathinfo['filename']) || $pathinfo['filename'] == "")
            {
                return time();
            }
            return $pathinfo['filename'];
        }

    }
}