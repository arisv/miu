<?php


namespace Meow;
use Symfony\Component\HttpFoundation\Request;


class ControlPanel
{
    private $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function GetAllImages($page, $numPerPage)
    {
        $offset = ($page - 1) * $numPerPage;

        return StoredFile::GetAllFiles($this->app['db'], $offset, $numPerPage);

    }

    public function GetCurrentUserImages(Request $request, $userId)
    {
        $afterOffset = $request->query->getInt('after');
        $beforeOffset = $request->query->getInt('before');
        $limit = 9;
        $result =  StoredFile::GetUserFiles($this->app['db'], intval($userId), $afterOffset, $beforeOffset, $limit);
        $result['dateTree'] = array();
        $dateTree = $this->GetUserDateReport($userId);
        foreach($dateTree as $dateTreeReport)
        {
            $result['dateTree'][$dateTreeReport['dyear']][$dateTreeReport['dmonth']] = $dateTreeReport['dcount'];
        }
        return $result;
    }


    public function GetPageStructure($currentPage, $numPerPage, $totalItems)
    {
        return ceil($totalItems / $numPerPage);
    }

    private function GetUserDateReport($userId)
    {
        $db = $this->app['db'];

        $sql = 'SELECT YEAR(FROM_UNIXTIME(filestorage.date)) as dyear, MONTH(FROM_UNIXTIME(filestorage.date)) as dmonth, COUNT(filestorage.id) as dcount FROM uploadlog
JOIN filestorage ON uploadlog.image_id = filestorage.id AND uploadlog.user_id = :user
GROUP BY YEAR(FROM_UNIXTIME(filestorage.date)), MONTH(FROM_UNIXTIME(filestorage.date))';
        $stmt = $db->prepare($sql);
        $stmt->bindValue("user", $userId);

        $stmt->execute();
        $report = $stmt->fetchAll();

        return $report;
    }

}