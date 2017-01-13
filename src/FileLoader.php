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
                $file = $request->files->get('meowfile');
                if(!empty($file) && $file->isValid())
                    return $file->getClientOriginalName();
                else
                    return "No file specified";

            }
            return "Wtf";
        }
    }
}