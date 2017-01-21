<?php


namespace Meow;


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

    public function GetPageStructure($currentPage, $numPerPage, $totalItems)
    {
        return ceil($totalItems / $numPerPage);
    }

}