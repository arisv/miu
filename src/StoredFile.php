<?php

namespace Meow
{
    use \Doctrine\DBAL\Connection;
    use \Symfony\Component\HttpFoundation\File\UploadedFile;
    use \Symfony\Component\HttpFoundation\File\Exception;

    class StoredFile
    {
        private $id;
        private $originalName;
        private $internalName;
        private $customUrl;
        private $serviceUrl;
        private $originalExtension;
        private $internalMimetype;
        private $originalSize;
        private $date;
        private $visibilityStatus;

        private function __construct($rowData)
        {
            if(isset($rowData['id']))
                $this->id = $rowData['id'];
            else
                $this->id = 0;
            $this->originalName = $rowData['original_name'];
            $this->internalName = $rowData['internal_name'];
            $this->customUrl = $rowData['custom_url'];
            $this->serviceUrl = $rowData['service_url'];
            $this->originalExtension = $rowData['original_extension'];
            $this->internalMimetype = $rowData['original_mimetype'];
            $this->originalSize = $rowData['original_size'];
            $this->date = $rowData['date'];
            if(isset($rowData['visibility_status']))
                $this->visibilityStatus = $rowData['visibility_status'];
            else
                $this->visibilityStatus = 1;
        }

        public function GetFilePath()
        {
            $dt = new \DateTime();
            $dt->setTimestamp($this->date);
            $path = __DIR__.'/../storage/'.$dt->format('Y-m').'/'.$this->internalName;
            if(file_exists($path))
                return $path;
            else
                return "";
        }

        public static function LookupFile(Connection $db, $fileid, $type)
        {
            $result = null;
            $routes = array(
                'direct' => 'custom_url'
            );
            $qb = $db->createQueryBuilder();
            $qb->select('*')
                ->from('filestorage')
                ->where($routes[$type] . ' = ?' )
                ->setParameter(0, $fileid);
            $result = $qb->execute();
            $rows = $result->fetchAll();
            if(!empty($rows)) //we have an image, yay!
            {
                $result = new StoredFile($rows[0]);
            }
            return $result;
        }

        public function MoveToStorage(UploadedFile $file)
        {
            $dt = new \DateTime();
            $dt->setTimestamp($this->date);
            $storagePath = __DIR__.'/../storage/'. $dt->format('Y-m'). '/';

            $movestatus = true;
            try{
                $file->move($storagePath, $this->internalName);
            }
            catch(Exception\FileException $e)
            {
                dump($e->getMessage());
                $movestatus = false;
            }
            return $movestatus;
        }

        public function SaveToDB(Connection $db)
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
                ->setParameter(0, $this->originalName)
                ->setParameter(1, $this->internalName)
                ->setParameter(2, $this->customUrl)
                ->setParameter(3, $this->serviceUrl)
                ->setParameter(4, $this->originalExtension)
                ->setParameter(5, $this->internalMimetype)
                ->setParameter(6, $this->originalSize)
                ->setParameter(7, $this->date);

            $result = $queryBuilder->execute();
            return $db->lastInsertId(); //TODO: error checking, assume everything is always fine for now
        }

        public function GetCustomUrl()
        {
            return 'http://'.$_SERVER['SERVER_NAME'] . '/i/' . $this->customUrl . '.png';
        }

        public static function AddFileToStorage(UploadedFile $file, Connection $db, \Meow\UserManager $userManager, $remoteToken = null)
        {
            $rowData = array();
            $rowData['original_name'] = $file->getClientOriginalName(); //Original filename supplied by client
            $sha = sha1_file($file->getPathName()); //filename to be used internally
            $rowData['original_size'] = $file->getSize(); //filesize
            $rowData['original_extension'] = $file->guessExtension(); //detect extension from mimetype to append
            $rowData['original_mimetype'] = $file->getMimeType(); //store mimetipe for grouping and shit
            $rowData['date'] = time();
            $rowData['internal_name'] = $sha .'_'. $rowData['date'];

            do{
                $rowData['service_url'] = bin2hex(openssl_random_pseudo_bytes(16)); //URL for client to delete the image
            }while(!Util::AssertUniqueness('filestorage', $rowData['service_url'], 'service_url', $db));

            do{
                $rowData['custom_url'] = Util::GenerateCustomUrl(); //shorter custom URL like example.com/qafsds.jpg
            }while(!Util::AssertUniqueness('filestorage', $rowData['custom_url'], 'custom_url', $db));

            dump('Creating new StoreFile');
            $fileToStore = new StoredFile($rowData);

            if($fileToStore->MoveToStorage($file))
            {
                $fileId = $fileToStore->SaveToDB($db);
                dump('File id: '.$fileId);
                if($remoteToken) //upload by token
                {
                    $userId = $userManager->GetUserIDByToken($remoteToken);
                    dump('Associating file by token '. $remoteToken.' with id ' . $userId);
                    $queryBuilder = $db->createQueryBuilder();
                    $queryBuilder->insert('uploadlog')
                        ->setValue('image_id', '?')
                        ->setValue('user_id', '?')
                        ->setParameter(0, $fileId)
                        ->setParameter(1, $userId);
                    $queryBuilder->execute();
                    return $fileToStore;
                }
                else if($userManager->HasLoggedUser()) //upload by web form
                {
                    $userId = $userManager->GetCurrentUserID();
                    dump('Associating file by login with id ' . $userId);
                    $queryBuilder = $db->createQueryBuilder();
                    $queryBuilder->insert('uploadlog')
                        ->setValue('image_id', '?')
                        ->setValue('user_id', '?')
                        ->setParameter(0, $fileId)
                        ->setParameter(1, $userId);
                    $queryBuilder->execute();
                    return $fileToStore;
                }
                else
                {
                    dump('Uploading anonymous file');
                    return $fileToStore; //anonymous upload
                }
            }
            else
                return null;
        }

    }
}