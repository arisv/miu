<?php

namespace Meow
{
    use \Silex\Application;
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
                    if($this->AddFileToStorage($file, $app['db']) )
                        return "Upload Successful";
                    else
                        return "Upload Failed";
                } else
                    return "No file specified";

            }
            return "Wtf";
        }

        public function ServeFileDirect(Request $request, Application $app, $customUrl)
        {
            $result = $this->LookupFile($app, $customUrl, 'direct');
            if($result['result'] == 'ok')
            {
                dump('Serving: ' . $result['path']);
                return $app->sendFile($result['path']);
            }
            return "Not found";
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

        private function LookupFile(Application $app, $fileid, $type)
        {
            $status = array(
                'result' => 'error',
                'path' => ''
            );
            $routes = array(
                'direct' => 'custom_url'
            );
            /** @var \Doctrine\DBAL\Connection $db */
            $db = $app['db'];
            $qb = $db->createQueryBuilder();
            $qb->select('*')
                ->from('filestorage')
                ->where($routes[$type] . ' = ?' )
                ->setParameter(0, $fileid);
            $result = $qb->execute();
            $rows = $result->fetchAll();
            if(!empty($rows)) //we have an image, yay!
            {
                $filedata = $rows[0];
                $dt = new \DateTime();
                $dt->setTimestamp($filedata['date']);
                $path = __DIR__.'/../storage/'.$dt->format('Y-m').'/'.$filedata['internal_name'];
                if(file_exists($path))
                {
                    $status['result'] = 'ok';
                    $status['path'] = $path;
                }
            }
            return $status;

        }

        private function AddFileToStorage(\Symfony\Component\HttpFoundation\File\UploadedFile $file,
                                         \Doctrine\DBAL\Connection $db)
        {
            $originalName = $file->getClientOriginalName(); //Original filename supplied by client
            $sha = sha1_file($file->getPathName()); //filename to be used internally
            $fileSize = $file->getSize(); //filesize
            $originalExtension = $file->guessExtension(); //detect extension from mimetype to append
            $mimetype = $file->getMimeType(); //store mimetipe for grouping and shit
            $date = time();
            $dt = new \DateTime();
            $dt->setTimestamp($date);
            $moveFolder = $dt->format('Y-m');
            $internalFilename = $sha .'_'. $date;

            do{
                $serviceUrl = bin2hex(openssl_random_pseudo_bytes(16)); //URL for client to delete the image
            }while(!$this->AssertUniqueness($serviceUrl, 'service_url', $db));

            do{
                $customUrl = $this->GenerateCustomUrl(); //shorter custom URL like example.com/qafsds.jpg
            }while(!$this->AssertUniqueness($customUrl, 'custom_url', $db));

            $path = __DIR__.'/../storage/'.$moveFolder.'/';
            $movestatus = true;
            try{
                $file->move($path, $internalFilename);
            }
            catch(File\Exception\FileException $e)
            {
                $movestatus = false;
                $moveError = $e->getMessage();
            }
            if($movestatus)
            {
                //PREPARE FOR HARD INSERTION
                $queryBuilder = $db->createQueryBuilder();
                $queryBuilder->insert('filestorage')
                    ->setValue('original_name', '?')
                    ->setValue('internal_name', '?')
                    ->setValue('custom_url', '?')
                    ->setValue('service_url', '?')
                    ->setValue('original_extension', '?')
                    ->setValue('internal_mimetype', '?')
                    ->setValue('internal_size', '?')
                    ->setValue('date', '?')
                    ->setParameter(0, $originalName)
                    ->setParameter(1, $internalFilename)
                    ->setParameter(2, $customUrl)
                    ->setParameter(3, $serviceUrl)
                    ->setParameter(4, $originalExtension)
                    ->setParameter(5, $mimetype)
                    ->setParameter(6, $fileSize)
                    ->setParameter(7, $date);
                $result = $queryBuilder->execute();
                dump($result);
                return true;
            }
            else
            {
                dump($moveError);
                return false;
            }

        }

        private function AssertUniqueness($url, $field,\Doctrine\DBAL\Connection $db)
        {
            $test = $db->query('SELECT * FROM filestorage WHERE '.$field.' = "'.$url.'"');
            if($test->rowCount() > 0)
                return false;
            else
                return true;
        }

        private function GenerateCustomUrl()
        {
            $token = "";
            $alphabet = "123456789abcdefghijklmnopqrstuvwkyz";
            $alphabetLength = strlen($alphabet);
            $length = 10;
            for ($i = 0; $i < $length; $i++) {
                $token .= $alphabet[$this->RandomOffset(0, $alphabetLength)];
            }
            return $token;

        }

        private function RandomOffset($min, $max)
        {
            $range = $max - $min;
            if ($range < 1) return $min; // not so random...
            $log = ceil(log($range, 2));
            $bytes = (int)($log / 8) + 1; // length in bytes
            $bits = (int)$log + 1; // length in bits
            $filter = (int)(1 << $bits) - 1; // set all lower bits to 1
            do {
                $rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));
                $rnd = $rnd & $filter; // discard irrelevant bits
            } while ($rnd >= $range);
            return $min + $rnd;
        }

    }
}