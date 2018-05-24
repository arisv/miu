<?php

namespace Meow
{
    use \Doctrine\DBAL\Connection;
    use Symfony\Component\HttpFoundation\File\File;
    use \Symfony\Component\HttpFoundation\File\UploadedFile;
    use \Symfony\Component\HttpFoundation\File\Exception;
    use \Symfony\Component\Filesystem\Filesystem;
    use \Symfony\Component\Filesystem\Exception\IOException;

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
        private $protocol;

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
            $this->internalMimetype = $rowData['internal_mimetype'];
            $this->originalSize = $rowData['internal_size'];
            $this->date = $rowData['date'];
            if(isset($rowData['visibility_status']))
                $this->visibilityStatus = $rowData['visibility_status'];
            else
                $this->visibilityStatus = 1;

            $isSSL = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || $_SERVER['SERVER_PORT'] == 443;

            $this->protocol = $isSSL ? 'https://' : 'http://';

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
            $qb = $db->createQueryBuilder();
            $qb->select('*')
                ->from('filestorage');
            if($type == 'direct'){
                $qb->where('custom_url = ?' );
                $qb->setParameter(0, $fileid);
            }
            $result = $qb->execute();
            $rows = $result->fetchAll();
            if(!empty($rows)) //we have an image, yay!
            {
                return new StoredFile($rows[0]);
            }
            else
                return null;
        }

        public function MoveToStorage(UploadedFile $file)
        {
            $dt = new \DateTime();
            $dt->setTimestamp($this->date);
            $storagePath = __DIR__.'/../storage/'. $dt->format('Y-m'). '/';

            dump($this);

            $movestatus = true;
            try{
                $file->move($storagePath, $this->internalName);
            }
            catch(Exception\FileException $e)
            {
                dump("Move to storage failed: {$e->getMessage()}");
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
            return $this->protocol.$_SERVER['SERVER_NAME'] . '/i/' . $this->customUrl . '.' . $this->originalExtension;
        }

        public function GetMIME()
        {
            return $this->internalMimetype;
        }

        public function GetID()
        {
            return $this->id;
        }

        //determine whether browser should embed file in the page or serve it as attachment (e.g archives)
        public function ShouldEmbed()
        {
            return $this->IsType(array('image', 'audio', 'video/webm'));
        }

        public function IsImage()
        {
            return $this->IsType(array('image'));
        }

        public function IsAudio()
        {
            return $this->IsType(array('audio'));
        }

        public function IsVideo()
        {
            return $this->IsType(array('video/webm'));
        }

        private function IsType($types)
        {
            foreach($types as $type)
            {
                if(strpos($this->internalMimetype, $type) === 0)
                    return true;
            }
            return false;
        }

        public static function AddFileToStorage(UploadedFile $file, Connection $db, \Meow\UserManager $userManager, $remoteToken = null)
        {
            $rowData = array();
            $rowData['original_name'] = $file->getClientOriginalName(); //Original filename supplied by client
            $sha = sha1_file($file->getPathName()); //filename to be used internally
            $rowData['internal_size'] = $file->getSize(); //filesize
            $rowData['original_extension'] = $file->guessExtension(); //detect extension from mimetype to append
            $rowData['internal_mimetype'] = $file->getMimeType(); //store mimetipe for grouping and shit
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

        public function GetOriginalName()
        {
            return $this->originalName;
        }

        public function getDate()
        {
            return $this->date;
        }

        public function getVisibilityStatus()
        {
            return $this->visibilityStatus;
        }

        public function IsDeleted()
        {
            return ($this->visibilityStatus == 2);
        }


        /**
         * Toggles file as marked for deletion but does not delete anything on itself*
         *
         * @param Connection $db
         * @param $fileId
         * @param $action
         * @return \Doctrine\DBAL\Driver\Statement|int
         */
        public static function SetDeleteStatus(Connection $db, $fileId, $action)
        {
            $qb = $db->createQueryBuilder();

            $qb->update('filestorage');
            if($action == 'del')
                $qb->set('filestorage.visibility_status', 2); //marked for deletion
            else
                $qb->set('filestorage.visibility_status', 1); //undo deletion mark
            $qb->where('filestorage.id = :fileid')
                ->setParameter('fileid', $fileId);
            return $qb->execute();
        }

        //removes file from disk and removes record in filestorage if disk removal did not fail
        public static function DeleteFile(Connection $db, $fileId)
        {
            $qb = $db->createQueryBuilder();
            $qb->select('internal_name, date')
                ->from('filestorage')
                ->where('id = :fileid')
                ->setParameter('fileid', $fileId);
            $query = $qb->execute()->fetchAll();
            if(empty($query))
                return false;
            $dt = new \DateTime();
            $dt->setTimestamp($query[0]['date']);
            $storagePath = __DIR__.'/../storage/'. $dt->format('Y-m'). '/';
            $fileName = $storagePath . $query[0]['internal_name'];
            $fs = new Filesystem();
            try
            {
                $fs->remove($fileName);
            }
            catch(IOException $e)
            {
                echo $e->getMessage(). ' ' . $e->getPath();
                return false;
            }

            $qb->delete('filestorage')
                ->where('id = :fileid')
                ->setParameter('fileid', $fileId);
            $qb->execute();
            return true;

        }

        public static function GetAllFiles(Connection $db, $offset, $limit)
        {
            $result = array('data' => array(), 'total' => 0);
            $limit = (intval($limit) > 0) ? intval($limit) : 25;
            $offset = (intval($offset) >= 0) ? $offset : 0;

            $sql = 'SELECT SQL_CALC_FOUND_ROWS * FROM filestorage ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
            $totalsql = 'SELECT FOUND_ROWS()';
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $collectedFiles = $stmt->fetchAll();

            $stmt = $db->prepare($totalsql);
            $stmt->execute();
            $totalFiles = $stmt->fetchAll();

            foreach ($collectedFiles as $rawFile)
            {
                $result['data'][] = new StoredFile($rawFile);
            }

            $result['total'] = $totalFiles[0]["FOUND_ROWS()"];

            return $result;


        }

        public static function GetUserFiles(Connection $db, $userId, $afterOffset = null, $beforeOffset = null, $limit)
        {
            $sql = "SELECT * FROM uploadlog
JOIN filestorage ON uploadlog.image_id = filestorage.id AND uploadlog.user_id = :user
";
            if($afterOffset > 0)
                $sql .= "WHERE uploadlog.upload_id < :offset ";
            else if($beforeOffset > 0)
                $sql .= "WHERE uploadlog.upload_id > :offset ";

            $shouldReverse = false;
            if($beforeOffset > 0)
            {
                $sql .= "ORDER BY uploadlog.upload_id ASC
LIMIT :limit";
                $shouldReverse = true;
            }

            else
                $sql .= "ORDER BY uploadlog.upload_id DESC
LIMIT :limit";




            $stmt = $db->prepare($sql);
            $stmt->bindValue("user", $userId);
            if($afterOffset > 0)
                $stmt->bindValue("offset", $afterOffset, \PDO::PARAM_INT);
            else if($beforeOffset > 0)
                $stmt->bindValue("offset", $beforeOffset, \PDO::PARAM_INT);
            $stmt->bindValue('limit', $limit + 1, \PDO::PARAM_INT);
            $stmt->execute();
            $totalFiles = $stmt->fetchAll();

            $resultIterator = 0;
            $result = array();
            $result['status'] = true;

            if(empty($totalFiles)) //no files found, return like this
            {
                $results['status'] = false;
                return $results;
            }

            if(count($totalFiles) > $limit)
                $result['hasNextPage'] = true;
            else
                $result['hasNextPage'] = false;

            if($afterOffset > 0)
                $result['prev'] = 'after';
            else if($beforeOffset > 0)
                $result['prev'] = 'before';
            else
                $result['prev'] = 'home';


            foreach($totalFiles as $rawFile)
            {
                if($resultIterator < $limit)
                    $result['files'][] = new StoredFile($rawFile);
                $resultIterator++;
            }

            if($shouldReverse) //before returns files in inverse order
            {
                $result['files'] = array_reverse($result['files']);
                $result['beforeId'] = $totalFiles[(count($result['files']) - 1)]['upload_id'];
                $result['afterId'] = $totalFiles[0]['upload_id'];
            }
            else
            {
                $result['beforeId'] = $totalFiles[0]['upload_id'];
                $result['afterId'] = $totalFiles[(count($result['files']) - 1)]['upload_id'];
            }

            return $result;
        }


    }
}