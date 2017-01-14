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
            if($request->files->has('meowfile'))
            {
                /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $file */
                $file = $request->files->get('meowfile');
                if(!empty($file) && $file->isValid())
                {
                    $shahash = sha1_file($file->getPathName());
                    return $file->getClientOriginalName() . 'Sha-1: ' . $shahash;
                }
                else
                    return "No file specified";

            }
            return "Wtf";
        }
    }
}