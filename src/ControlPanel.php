<?php


namespace Meow;
use Symfony\Component\HttpFoundation\Request;


class ControlPanel
{
    private $app;
    /** @var \Doctrine\DBAL\Connection $db */
    private $db;

    public function __construct($app)
    {
        $this->app = $app;
        $this->db = $this->app['db'];
    }

    public function GetAllImages($page, $numPerPage)
    {
        $offset = ($page - 1) * $numPerPage;

        return StoredFile::GetAllFiles($this->db, $offset, $numPerPage);

    }

    public function GetCurrentUserImages(Request $request, $userId)
    {
        $afterOffset = $request->query->getInt('after');
        $beforeOffset = $request->query->getInt('before');
        $limit = 9;
        $result = StoredFile::GetUserFiles($this->db, intval($userId), $afterOffset, $beforeOffset, $limit);
        $result['dateTree'] = array();
        $dateTree = $this->GetUserDateReport($userId);
        foreach($dateTree as $dateTreeReport)
        {
            $result['dateTree'][$dateTreeReport['dyear']][$dateTreeReport['dmonth']] = $dateTreeReport['dcount'];
        }
        return $result;
    }

    public function GetAllUserIndex(Request $request)
    {
        $qb = $this->db->createQueryBuilder();
        $qb->select('*')
            ->from('users')
            ->orderBy('id', 'ASC');

        $exec = $qb->execute();
        $result = $exec->fetchAll();
        $anonUser = array(
            'id' => '0',
            'login' => 'Anonymous uploads',
            'email' => '',
            'role' => '1',
            'active' => '1'
        );
        array_unshift($result, $anonUser);
        return $result;

    }

    public function GetStorageStats($userId)
    {

        if($userId > 0)
        {
            $sql = "SELECT uploadlog.image_id, filestorage.id, uploadlog.user_id, SUM(filestorage.internal_size) as total FROM uploadlog
JOIN filestorage ON uploadlog.image_id = filestorage.id
WHERE uploadlog.user_id = :user
GROUP BY uploadlog.user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue('user', $userId);
            $stmt->execute();
            $result = $stmt->fetchAll();
        }
        if($userId == 0)
        {
            $sql = "SELECT filestorage.id, uploadlog.image_id, uploadlog.user_id, SUM(filestorage.internal_size) as total FROM filestorage
LEFT OUTER JOIN uploadlog ON uploadlog.image_id = filestorage.id
WHERE uploadlog.user_id is null
GROUP BY uploadlog.user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetchAll();

        }

        if(!empty($result))
        {
            $sizeReport = $this->FormatSize($result[0]['total']);
        }
        else
            $sizeReport = 'No storage data found.';

        return $sizeReport;
    }

    public function SetDeleteStatus($uploadid, $userid, $action)
    {
        $response = array(
            'status' => 'ok',
            'message' => ''
        );

        $qb = $this->db->createQueryBuilder();
        $qb->select('*')
            ->from('uploadlog')
            ->where('uploadlog.user_id = :user')
            ->andWhere('uploadlog.image_id = :uploadid')
            ->setParameter('user', $userid)
            ->setParameter('uploadid', $uploadid);

        $fetch = $qb->execute();
        $fetch = $fetch->fetchAll();

        if(empty($fetch))
        {
            $response['status'] = 'error';
            $response['message'] = 'Invalid file and/or username';
        }
        else
        {
            if(!StoredFile::SetDeleteStatus($this->db, $uploadid, $action))
            {
                $response['status'] = 'error';
            }
        }

        return $response;
    }


    public function GetPageStructure($currentPage, $numPerPage, $totalItems)
    {
        return ceil($totalItems / $numPerPage);
    }

    private function FormatSize($size, $pres = 2)
    {
        $names = array('B', 'KB', 'MB', 'G', 'T');
        $i = 0;
        while($size > 1024)
        {
            $size /= 1024;
            $i++;
        }
        return round($size, $pres) . ' ' . $names[$i];
    }

    private function GetUserDateReport($userId)
    {
        $sql = 'SELECT YEAR(FROM_UNIXTIME(filestorage.date)) as dyear, MONTH(FROM_UNIXTIME(filestorage.date)) as dmonth, COUNT(filestorage.id) as dcount FROM uploadlog
JOIN filestorage ON uploadlog.image_id = filestorage.id AND uploadlog.user_id = :user
GROUP BY YEAR(FROM_UNIXTIME(filestorage.date)), MONTH(FROM_UNIXTIME(filestorage.date))';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue("user", $userId);

        $stmt->execute();
        $report = $stmt->fetchAll();

        return $report;
    }

}