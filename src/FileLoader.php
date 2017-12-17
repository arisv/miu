<?php

namespace Meow
{
    use \Silex\Application;
    use Symfony\Component\Config\Definition\Exception\Exception;
    use \Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\HttpFoundation\ResponseHeaderBag;
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
            return "Wtf";
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

    }
}