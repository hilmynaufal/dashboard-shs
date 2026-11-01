<?php

namespace App\Support;

final class UploadLimits
{
    public static function kilobytes(int $cap): int
    {
        $limits=[$cap];
        foreach(['upload_max_filesize','post_max_size']as$key){$raw=trim((string)ini_get($key));$n=(float)$raw;$suffix=strtolower(substr($raw,-1));$bytes=$n*match($suffix){'g'=>1073741824,'m'=>1048576,'k'=>1024,default=>1};if($bytes>0){$limits[]=max(1,(int)floor($bytes/1024)-($key==='post_max_size'?64:0));}}

        return min($limits);
    }

    public static function label(int $cap): string
    {
        return rtrim(rtrim(number_format(self::kilobytes($cap)/1024,1,',','.'),'0'),',').' MB';
    }
}
