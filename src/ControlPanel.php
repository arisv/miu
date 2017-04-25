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
        $limit = 12;
        dump('after:' . $afterOffset);
        dump('before:' . $beforeOffset);
        return StoredFile::GetUserFiles($this->app['db'], intval($userId), $afterOffset, $beforeOffset, $limit);
    }

    public function GetPageStructure($currentPage, $numPerPage, $totalItems)
    {
        return ceil($totalItems / $numPerPage);
    }

}