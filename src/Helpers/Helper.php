<?php

namespace Telanflow\Binlog\Helpers;

class Helper
{

    /**
     * Clear file content
     *
     * @param $filePath
     *
     * @return bool
     */
    public static function cleanFile($filePath)
    {
        $fh = fopen($filePath, 'r+');
        if ($fh === false) {
            return false;
        }

        if( flock($fh, LOCK_EX) )
        {
            ftruncate($fh, 0);
            rewind($fh);
            flock($fh, LOCK_UN);
        }
        return fclose($fh);
    }


}