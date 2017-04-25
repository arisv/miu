<?php

namespace Meow
{
    use \Silex\Application;
    use Symfony\Component\Config\Definition\Exception\Exception;
    use \Symfony\Component\HttpFoundation\Request;
    use \Symfony\Component\HttpFoundation\File;


    class FileLoader
    {
        public function AddNewFile(Request $request, Application $app)
        {
            if ($request->files->has('meowfile')) {
                /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $file */
                $file = $request->files->get('meowfile');
                if (!empty($file) && $file->isValid()) {
                    dump('Uploading file:');
                    $storedFile = StoredFile::AddFileToStorage($file, $app['db'], $app['usermanager.service']);
                    if($storedFile)
                        return "Upload Successful :" . $storedFile->GetCustomUrl();
                    else
                        return "Upload Failed";
                } else
                    return "No file specified";
            }
            else if($request->files->has('meowfile_remote')){
                $file = $request->files->get('meowfile_remote');
                $remoteToken = $request->request->get('private_key');
                if (!empty($file) && $file->isValid()){
                    $storedFile = StoredFile::AddFileToStorage($file, $app['db'], $app['usermanager.service'], $remoteToken);
                    if($storedFile)
                    {
                        $jsn = array(
                            'file' => $storedFile->GetCustomUrl()
                        );
                        return $app->json($jsn);
                    }
                }
            }
            dump($request);
            return "Wtf";
        }

        public function ServeFileDirect(Request $request, Application $app, $customUrl)
        {
            if($result = StoredFile::LookupFile($app['db'], $customUrl, 'direct'))
            {
                dump('Serving: ' . $result->GetFilePath());
                ob_end_clean();
                return $app->sendFile($result->GetFilePath());
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

    }
}